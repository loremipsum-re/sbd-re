<?php
/**
 * La session : qui est connecté, et d'où vient la requête.
 *
 * -----------------------------------------------------------------------------
 * UN JETON EN BASE, PAS UNE SESSION PHP
 * -----------------------------------------------------------------------------
 * Une session PHP dure 24 minutes par défaut, et sa durée dépend d'un réglage
 * du serveur hors de portée sur un mutualisé. Ici, la connexion tire 32 octets
 * au hasard, les envoie au navigateur dans un cookie, et ne garde en base que
 * leur EMPREINTE SHA-256, dans la table `jeton`. Qui lirait la table par une
 * injection SQL n'y trouverait rien d'utilisable.
 *
 * La session dure 30 jours depuis la connexion, sans prolongation. Bannir ou
 * changer de mot de passe efface tous les jetons du membre : il est déconnecté
 * partout, sur-le-champ.
 *
 * -----------------------------------------------------------------------------
 * LE COOKIE
 * -----------------------------------------------------------------------------
 *   __Host-  le navigateur n'accepte ce nom que posé en HTTPS, sur tout le
 *            site, sans attribut Domain : aucun sous-domaine ne peut l'écraser.
 *   HttpOnly le JavaScript de la page ne le lit pas, ce qui borne les dégâts
 *            d'une faille d'injection de script.
 *   SameSite=Lax  il n'accompagne pas une écriture lancée depuis un autre site.
 *
 * -----------------------------------------------------------------------------
 * L'ORIGINE, À LA PLACE D'UN JETON ANTI-CSRF
 * -----------------------------------------------------------------------------
 * Toute requête qui écrit (POST, PATCH, DELETE) doit porter l'en-tête Origin de
 * sbd.re. Les navigateurs l'envoient d'office sur ces méthodes et une page ne
 * peut pas le falsifier : une écriture déclenchée depuis un autre site est donc
 * refusée, sans jeton à faire circuler dans chaque formulaire. Une requête sans
 * Origin est refusée aussi : un navigateur récent l'envoie toujours.
 */

declare(strict_types=1);

namespace SBDRE;

if (!defined('SBDRE_API')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/bdd.php';

const COOKIE_SESSION      = '__Host-sbdre';
const DUREE_SESSION_JOURS = 30;

/** Les seules origines admises pour une écriture. */
const ORIGINES = ['https://sbd.re', 'https://www.sbd.re'];

/* =========================================================================
 * Décisions pures, éprouvées par db/verifier-autorisation.php
 * ====================================================================== */

/**
 * Une requête de cette méthode, venue de cette origine, peut-elle passer ?
 * La lecture passe toujours ; l'écriture exige une origine de la liste,
 * comparée en entier : « https://sbd.re.exemple.com » n'est pas sbd.re.
 */
function origineAutorisee(string $methode, string $origine): bool
{
    if (in_array(strtoupper($methode), ['GET', 'HEAD'], true)) {
        return true;
    }

    return in_array($origine, ORIGINES, true);
}

/**
 * Le cookie a-t-il la forme d'un jeton émis ici : 64 caractères hexadécimaux ?
 * `\z` et non `$`, qui accepterait aussi un retour à la ligne final.
 */
function jetonBienForme(string $jeton): bool
{
    return preg_match('/^[a-f0-9]{64}\z/', $jeton) === 1;
}

/* =========================================================================
 * Requête courante
 * ====================================================================== */

/**
 * Refuse et met fin à la requête. Ne rend jamais la main : impossible
 * d'oublier un `return` derrière un refus.
 */
function refuser(int $code, string $message): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['erreur' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

/** Refuse une écriture qui ne vient pas de sbd.re. */
function exigerOrigine(): void
{
    $methode = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $origine = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');

    if (!origineAutorisee($methode, $origine)) {
        refuser(403, 'Origine non autorisée.');
    }
}

/**
 * Le membre connecté, ou null.
 *
 * L'identité vient du COOKIE, vérifié en base, jamais du corps de la requête :
 * un champ `membre_id` envoyé par le navigateur n'est lu nulle part. Un membre
 * banni n'a plus de session, même si son jeton n'est pas encore effacé.
 *
 * @return array<string, mixed>|null
 */
function membreCourant(): ?array
{
    static $membre = null;
    static $cherche = false;

    if ($cherche) {
        return $membre;
    }
    $cherche = true;

    $jeton = (string) ($_COOKIE[COOKIE_SESSION] ?? '');
    if (!jetonBienForme($jeton)) {
        return null;
    }

    $membre = ligne(
        "SELECT m.id, m.pseudo, m.role, m.courriel_verifie, m.banni, m.date_inscription
           FROM jeton j
           JOIN membre m ON m.id = j.membre_id
          WHERE j.jeton_hash = :empreinte
            AND j.type = 'session'
            AND j.date_expiration > NOW()
            AND m.banni = 0",
        ['empreinte' => hash('sha256', $jeton)],
    );

    return $membre;
}

/**
 * Ouvre une session pour ce membre, après vérification du mot de passe par
 * l'appelant. Purge au passage cent sessions expirées : aucune tâche
 * planifiée à configurer.
 */
function ouvrirSession(int $membreId): void
{
    $jeton = bin2hex(random_bytes(32));

    executer(
        "INSERT INTO jeton (membre_id, type, jeton_hash, date_expiration)
         VALUES (:membre, 'session', :empreinte, NOW() + INTERVAL " . DUREE_SESSION_JOURS . ' DAY)',
        ['membre' => $membreId, 'empreinte' => hash('sha256', $jeton)],
    );
    executer('DELETE FROM jeton WHERE date_expiration < NOW() LIMIT 100');

    poserCookie($jeton, time() + DUREE_SESSION_JOURS * 86400);
}

/** Ferme la session de CE navigateur. */
function fermerSession(): void
{
    $jeton = (string) ($_COOKIE[COOKIE_SESSION] ?? '');

    if (jetonBienForme($jeton)) {
        executer(
            "DELETE FROM jeton WHERE type = 'session' AND jeton_hash = :empreinte",
            ['empreinte' => hash('sha256', $jeton)],
        );
    }

    poserCookie('', time() - 3600);
}

/** Ferme toutes les sessions d'un membre : bannissement, nouveau mot de passe. */
function fermerToutesSessions(int $membreId): void
{
    executer("DELETE FROM jeton WHERE type = 'session' AND membre_id = :membre", ['membre' => $membreId]);
}

function poserCookie(string $valeur, int $expiration): void
{
    setcookie(COOKIE_SESSION, $valeur, [
        'expires'  => $expiration,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

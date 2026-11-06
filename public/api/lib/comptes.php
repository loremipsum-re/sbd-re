<?php
/**
 * Les comptes : formes acceptées et jetons envoyés par courriel.
 *
 * Les fonctions de forme sont pures et éprouvées sans base par
 * db/verifier-autorisation.php. Les fonctions de jeton parlent à la table
 * `jeton`, dont seule l'empreinte est stockée, comme pour la session.
 */

declare(strict_types=1);

namespace SBDRE;

if (!defined('SBDRE_API')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/autorisation.php';
require_once __DIR__ . '/courriel.php';

/** Le lien de vérification reste valable 24 heures. */
const VERIFICATION_MINUTES = 24 * 60;

/** Le lien de nouveau mot de passe, une heure : une vieille boîte ne doit pas rouvrir un compte. */
const REINITIALISATION_MINUTES = 60;

/** Trois inscriptions par heure et par IP, trois liens par heure et par compte. */
const INSCRIPTIONS_PAR_HEURE = 3;
const LIENS_PAR_HEURE = 3;

/**
 * Freinage des mots de passe essayés en série : 5 échecs en 15 minutes sur
 * une adresse, 20 sur une IP. Plus large pour l'IP : sur l'île, les
 * opérateurs mobiles font sortir beaucoup de clients par la même adresse.
 */
const ECHECS_PAR_COURRIEL = 5;
const ECHECS_PAR_IP = 20;
const FENETRE_ECHECS_MINUTES = 15;

/**
 * 10 caractères au moins, sans règle de composition : la longueur protège,
 * l'obligation d'un chiffre ou d'un symbole fabrique « Motdepasse1! ».
 * 72 octets au plus : bcrypt ignore la suite, deux mots de passe différents
 * au-delà seraient acceptés l'un pour l'autre.
 */
const MOT_DE_PASSE_MIN = 10;
const MOT_DE_PASSE_MAX_OCTETS = 72;

/**
 * Pseudos réservés : ils feraient passer un membre pour le site ou pour son
 * équipe. Comparés après mise en minuscules.
 */
const PSEUDOS_RESERVES = [
    'admin', 'administrateur', 'administration', 'moderateur', 'moderation',
    'sbd.re', 'sbdre', 'sbd_re', 'sbd', 'support', 'contact', 'equipe',
    'officiel', 'ffforce', 'openpowerlifting',
];

/**
 * Empreinte bcrypt d'un mot de passe aléatoire jamais conservé. Quand
 * l'adresse n'a pas de compte, la connexion la vérifie quand même : la
 * réponse prend le même temps, et la durée ne trahit pas quelles adresses
 * sont inscrites.
 */
const EMPREINTE_FACTICE = '$2y$12$M7Pedm8dUWXFjG/O807t/usPDxybhuWPVv9ba4wjhX2TjCK3tJxYW';

/* =========================================================================
 * Formes acceptées, fonctions pures
 * ====================================================================== */

/**
 * Le format d'un nom Instagram, comme la contrainte du schéma : minuscules,
 * chiffres, point, tiret bas, 3 à 30 caractères, au moins une lettre ou un
 * chiffre. `\z` et non `$`, qui accepterait un retour à la ligne final.
 */
function pseudoValide(string $pseudo): bool
{
    return preg_match('/^[a-z0-9._]{3,30}\z/', $pseudo) === 1
        && preg_match('/[a-z0-9]/', $pseudo) === 1
        && !in_array($pseudo, PSEUDOS_RESERVES, true);
}

/** Le message à montrer si le mot de passe ne convient pas, ou ''. */
function erreurMotDePasse(string $motDePasse): string
{
    if (mb_strlen($motDePasse) < MOT_DE_PASSE_MIN) {
        return 'Choisissez un mot de passe de ' . MOT_DE_PASSE_MIN . ' caractères au moins.';
    }
    if (strlen($motDePasse) > MOT_DE_PASSE_MAX_OCTETS) {
        return 'Ce mot de passe est trop long : ' . MOT_DE_PASSE_MAX_OCTETS . ' octets au plus.';
    }

    return '';
}

/** L'adresse en minuscules si elle est valide, sinon ''. */
function normaliserCourriel(string $courriel): string
{
    $courriel = mb_strtolower(trim($courriel));

    if (strlen($courriel) > 254 || filter_var($courriel, FILTER_VALIDATE_EMAIL) === false) {
        return '';
    }

    return $courriel;
}

/* =========================================================================
 * Jetons envoyés par courriel
 * ====================================================================== */

/** Crée un jeton et rend sa valeur en clair, la seule fois où elle existe. */
function creerJeton(int $membreId, string $type, int $minutes): string
{
    $jeton = bin2hex(random_bytes(32));

    executer(
        'INSERT INTO jeton (membre_id, type, jeton_hash, date_expiration)
         VALUES (:membre, :type, :empreinte, NOW() + INTERVAL :minutes MINUTE)',
        ['membre' => $membreId, 'type' => $type, 'empreinte' => hash('sha256', $jeton), 'minutes' => $minutes],
    );

    return $jeton;
}

/**
 * Consomme un jeton et rend l'identifiant de son membre, ou null.
 *
 * L'UPDATE conditionnel fait le contrôle ET la consommation en une seule
 * requête : deux clics simultanés sur le même lien ne peuvent pas le
 * consommer deux fois.
 */
function consommerJeton(string $jeton, string $type): ?int
{
    if (!jetonBienForme($jeton)) {
        return null;
    }

    $empreinte = hash('sha256', $jeton);

    $consomme = executer(
        'UPDATE jeton SET date_utilisation = NOW()
          WHERE jeton_hash = :empreinte AND type = :type
            AND date_utilisation IS NULL AND date_expiration > NOW()',
        ['empreinte' => $empreinte, 'type' => $type],
    );

    if ($consomme !== 1) {
        return null;
    }

    $ligne = ligne('SELECT membre_id FROM jeton WHERE jeton_hash = :empreinte', ['empreinte' => $empreinte]);

    return $ligne === null ? null : (int) $ligne['membre_id'];
}

/** Combien de jetons de ce type le membre a reçus dans l'heure. */
function jetonsRecents(int $membreId, string $type): int
{
    $ligne = ligne(
        'SELECT COUNT(*) AS n FROM jeton
          WHERE membre_id = :membre AND type = :type AND date_creation > NOW() - INTERVAL 1 HOUR',
        ['membre' => $membreId, 'type' => $type],
    );

    return (int) ($ligne['n'] ?? 0);
}

/** Envoie le lien de vérification d'adresse. */
function envoyerLienVerification(int $membreId, string $pseudo, string $courriel): bool
{
    $jeton = creerJeton($membreId, 'verification', VERIFICATION_MINUTES);

    return envoyerCourriel($courriel, '[sbd.re] Confirmez votre adresse', implode("\n", [
        "Bonjour $pseudo,",
        '',
        'Pour activer votre compte sur sbd.re, ouvrez ce lien dans les 24 heures :',
        '',
        'https://sbd.re/api/verifier.php?jeton=' . $jeton,
        '',
        "Vous n'avez rien demandé ? Ignorez ce message : le compte reste inactif.",
    ]));
}

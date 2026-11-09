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
 * Un formulaire d'inscription rempli en moins de 3 secondes n'a pas été lu :
 * c'est un robot. Au-delà de 2 heures, la page traînait dans un onglet. Même
 * piège que contact.php, à 3 secondes au lieu de 4 : un gestionnaire de mots
 * de passe remplit vite, et la case à cocher reste à cliquer.
 */
const DELAI_MINIMAL_SECONDES = 3;
const DELAI_MAXIMAL_SECONDES = 7200;

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

/**
 * Les domaines d'Apple, qui refusent nos courriels. Mesuré le 7 novembre
 * 2026 : « 554 5.7.1 [HM08] Message rejected due to local policy », alors que
 * DKIM, DMARC et SPF passent tous. Apple bloque les serveurs d'envoi partagés
 * d'OVH, pas la signature. Un compte iCloud ne recevrait jamais son lien et
 * s'effacerait au bout de 24 heures : l'inscription le refuse d'emblée, en le
 * disant. Choix de l'auteur du même jour.
 *
 * À retirer le jour où un envoi vers iCloud passe : Apple joint par
 * icloudadmin@apple.com, ou un service d'envoi tiers.
 */
const DOMAINES_INJOIGNABLES = ['icloud.com', 'me.com', 'mac.com'];

const MESSAGE_INJOIGNABLE = 'Les adresses iCloud (icloud.com, me.com, mac.com) ne reçoivent pas encore '
    . 'les courriels de sbd.re. Utilisez une autre adresse pour le moment.';

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

/**
 * Les pseudos de membres actifs qui commencent par `$debut`, huit au plus,
 * pour les suggestions après « @ » (api/pseudos.php). Un début illisible
 * ne rend rien.
 *
 * @return list<string>
 */
function pseudosCommencantPar(string $debut): array
{
    $debut = mb_strtolower(trim($debut));
    if (preg_match('/^[a-z0-9._]{1,30}\z/', $debut) !== 1) {
        return [];
    }

    return array_column(lignes(
        'SELECT pseudo FROM membre WHERE courriel_verifie = 1 AND banni = 0 AND pseudo LIKE :d ORDER BY pseudo LIMIT 8',
        // « _ » est un joker de LIKE, et il est permis dans un pseudo.
        ['d' => addcslashes($debut, '%_\\') . '%'],
    ), 'pseudo');
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

/** Le formulaire a-t-il été rempli à vitesse humaine ? */
function delaiHumain(int $affiche, int $maintenant): bool
{
    $ecoule = $maintenant - $affiche;

    return $affiche > 0 && $ecoule >= DELAI_MINIMAL_SECONDES && $ecoule <= DELAI_MAXIMAL_SECONDES;
}

/* =========================================================================
 * Le nom Instagram, distinct du pseudo
 *
 * Choix de l'auteur du 7 novembre 2026 : le lien du profil vise un nom
 * Instagram à part, que le membre choisit, et qu'il ne change qu'une fois
 * tous les six mois. Changer à volonté permettrait d'emprunter le nom d'un
 * autre le temps d'une capture d'écran. Retirer le nom reste possible à tout
 * moment, sans remettre l'horloge à zéro.
 * ====================================================================== */

const DELAI_INSTAGRAM_MOIS = 6;

/**
 * Le pseudo, lui, se change une fois par an, demande de l'auteur du même
 * jour. Le premier changement est libre, pour rattraper une faute de frappe
 * à l'inscription. Au-delà, l'admin peut en accorder un plus tôt sur
 * demande : `UPDATE membre SET date_pseudo = NULL WHERE pseudo = '…'`.
 *
 * L'ancien pseudo reste réservé au membre, et son adresse de profil renvoie
 * vers la nouvelle : un lien partagé ne meurt pas, et personne ne reprend un
 * pseudo qu'on vient de quitter pour se faire passer pour son ancien
 * titulaire.
 */
const DELAI_PSEUDO_MOIS = 12;

/** Ce que le membre a tapé, ramené à un nom : « @Jean », une adresse instagram.com/jean/. */
function normaliserInstagram(string $saisie): string
{
    $saisie = trim($saisie);
    if (preg_match('~instagram\.com/([^/?#\s]+)~i', $saisie, $m) === 1) {
        $saisie = $m[1];
    }

    return mb_strtolower(ltrim($saisie, '@'));
}

/** Le format d'Instagram : minuscules, chiffres, point, tiret bas, 30 au plus. */
function instagramValide(string $nom): bool
{
    return preg_match('/^[a-z0-9._]{1,30}\z/', $nom) === 1
        && preg_match('/[a-z0-9]/', $nom) === 1;
}

/**
 * Le moment où un nouveau nom sera accepté, 0 si c'est déjà le cas : pour
 * Instagram avec DELAI_INSTAGRAM_MOIS, pour le pseudo avec DELAI_PSEUDO_MOIS.
 */
function prochainChangement(?string $dernierChangement, int $mois, int $maintenant): int
{
    if ($dernierChangement === null || $dernierChangement === '') {
        return 0;
    }
    $possible = strtotime($dernierChangement . ' +' . $mois . ' months');

    return $possible !== false && $possible > $maintenant ? $possible : 0;
}

/** Une date en toutes lettres : « 7 mai 2027 », ou « mai 2027 » sans le jour. */
function dateFr(int $moment, bool $avecJour = true): string
{
    $mois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet',
        'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $jour = (int) date('j', $moment);

    return ($avecJour ? ($jour === 1 ? '1er' : (string) $jour) . ' ' : '')
        . $mois[(int) date('n', $moment) - 1] . ' ' . date('Y', $moment);
}

/** L'adresse, déjà normalisée, est-elle chez un domaine qui refuse nos courriels ? */
function adresseInjoignable(string $courriel): bool
{
    return in_array(substr((string) strrchr($courriel, '@'), 1), DOMAINES_INJOIGNABLES, true);
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

/**
 * Efface cent comptes jamais confirmés, vieux de plus de 24 heures, avec
 * leurs jetons. La base ne garde aucun faux compte, sans tâche planifiée :
 * chaque inscription fait le ménage en passant.
 */
function purgerNonConfirmes(): void
{
    executer(
        'DELETE FROM membre
          WHERE courriel_verifie = 0
            AND date_inscription < NOW() - INTERVAL ' . VERIFICATION_MINUTES . ' MINUTE
          LIMIT 100',
    );
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
        "Sans clic sur ce lien, le compte s'efface de lui-même au bout de 24 heures.",
        "Vous n'avez rien demandé ? Ignorez simplement ce message.",
    ]));
}

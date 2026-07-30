<?php
/**
 * QUI A LE DROIT DE FAIRE QUOI.
 *
 * -----------------------------------------------------------------------------
 * LE FICHIER LE PLUS SENSIBLE DU PROJET
 * -----------------------------------------------------------------------------
 * Le plan initial reposait sur Supabase et ses politiques RLS : la base
 * refusait elle-même une écriture interdite, même si le code du navigateur
 * avait un bug, même si quelqu'un forgeait une requête à la main.
 *
 * MySQL n'a pas d'équivalent. La base exécute ce qu'on lui demande. Le contrôle
 * des droits vit donc ENTIÈREMENT ici, et c'est le seul endroit du projet où
 * une erreur laisserait un inconnu trafiquer le classement.
 *
 * Trois règles ont guidé l'écriture, et il faut les tenir en modifiant :
 *
 *   1. UN SEUL ENDROIT DÉCIDE. Aucun point d'entrée n'écrit sa propre
 *      vérification : il appelle une fonction d'ici. Une règle à revoir se
 *      revoit alors une fois.
 *
 *   2. LE REFUS EST LE DÉFAUT. Chaque fonction commence par refuser et
 *      n'autorise qu'ensuite. Écrite dans l'autre sens, un `if` oublié
 *      laisserait passer au lieu de bloquer, et la faille serait invisible à
 *      la lecture.
 *
 *   3. RIEN DE SENSIBLE NE VIENT DU CLIENT. L'identité se lit dans la session,
 *      jamais dans le corps de la requête. Les champs modifiables sont
 *      énumérés un par un, jamais déduits de ce qui a été envoyé.
 */

declare(strict_types=1);

namespace SBDRE;

if (!defined('SBDRE_API')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/bdd.php';

/* =========================================================================
 * Rôles
 * ====================================================================== */

const ROLE_MEMBRE     = 'membre';
const ROLE_MODERATEUR = 'moderateur';
const ROLE_ADMIN      = 'admin';

/**
 * Un modérateur peut examiner la file d'attente et publier ou refuser.
 *
 * @param array<string, mixed>|null $membre
 */
function estModerateur(?array $membre): bool
{
    if ($membre === null) {
        return false;
    }

    return in_array($membre['role'] ?? '', [ROLE_MODERATEUR, ROLE_ADMIN], true);
}

/** @param array<string, mixed>|null $membre */
function estAdmin(?array $membre): bool
{
    return $membre !== null && ($membre['role'] ?? '') === ROLE_ADMIN;
}

/**
 * Le membre est-il l'auteur de cette performance ?
 *
 * Comparaison sur des entiers : « 12 » et 12 doivent désigner la même personne,
 * et une comparaison relâchée ferait qu'une chaîne vide vaudrait 0.
 *
 * @param array<string, mixed>      $performance
 * @param array<string, mixed>|null $membre
 */
function estAuteur(array $performance, ?array $membre): bool
{
    if ($membre === null) {
        return false;
    }

    return (int) ($performance['membre_id'] ?? 0) === (int) ($membre['id'] ?? -1)
        && (int) ($membre['id'] ?? 0) > 0;
}

/* =========================================================================
 * Ce qu'on a le droit de LIRE
 * ====================================================================== */

/**
 * Une performance publiée se lit par tout le monde, y compris sans compte :
 * c'est un classement public. Les autres ne se lisent que par leur auteur et
 * par les modérateurs.
 *
 * Ce point n'est pas cosmétique : le lien vidéo montre et fait entendre la
 * personne. Il ne doit pas circuler tant que la performance n'est pas publiée.
 *
 * @param array<string, mixed>      $performance
 * @param array<string, mixed>|null $membre
 */
function peutVoirPerformance(array $performance, ?array $membre): bool
{
    if (($performance['etat'] ?? '') === 'publiee') {
        return true;
    }

    return estAuteur($performance, $membre) || estModerateur($membre);
}

/* =========================================================================
 * Ce qu'on a le droit d'ÉCRIRE
 * ====================================================================== */

/**
 * Soumettre exige un compte ET une adresse vérifiée.
 *
 * Sans la vérification, n'importe qui inscrirait l'adresse d'un tiers pour
 * soumettre en son nom.
 *
 * @param array<string, mixed>|null $membre
 */
function peutSoumettre(?array $membre): bool
{
    if ($membre === null) {
        return false;
    }

    return (int) ($membre['courriel_verifie'] ?? 0) === 1;
}

/**
 * Seul l'auteur modifie sa performance. Un modérateur ne corrige pas le
 * contenu : il publie ou il refuse avec un motif. Confondre les deux
 * reviendrait à laisser un modérateur réécrire la performance de quelqu'un.
 *
 * La modification reste possible dans les trois états, y compris après un
 * refus : corriger une date mal saisie est précisément l'usage attendu.
 *
 * @param array<string, mixed>      $performance
 * @param array<string, mixed>|null $membre
 */
function peutModifierPerformance(array $performance, ?array $membre): bool
{
    return estAuteur($performance, $membre);
}

/** @param array<string, mixed> $performance */
function peutSupprimerPerformance(array $performance, ?array $membre): bool
{
    return estAuteur($performance, $membre);
}

/**
 * État d'une performance après modification par son auteur.
 *
 * TOUJOURS « en attente ». Sans cette règle, on publierait 150 kg avant de le
 * remplacer par 300 kg sans repasser devant personne.
 */
function etatApresModification(): string
{
    return 'en_attente';
}

/* =========================================================================
 * Modération
 * ====================================================================== */

/**
 * Publier ou refuser une performance.
 *
 * Un modérateur ne modère PAS la sienne : il la publierait sans qu'aucun regard
 * extérieur ne s'y pose, ce qui viderait la modération de son sens. L'exception
 * est l'administrateur, faute de quoi le seul modérateur du site ne pourrait
 * jamais publier ses propres barres.
 *
 * Cette exception est assumée et traçable : `performance.moderateur_id`
 * enregistre qui a décidé, et une décision prise sur soi-même se voit.
 *
 * @param array<string, mixed>      $performance
 * @param array<string, mixed>|null $membre
 */
function peutModerer(array $performance, ?array $membre): bool
{
    if (!estModerateur($membre)) {
        return false;
    }

    if (estAuteur($performance, $membre)) {
        return estAdmin($membre);
    }

    return true;
}

/* =========================================================================
 * Champs modifiables : des listes fermées
 *
 * Recopier en base ce que le navigateur a envoyé est la faute la plus banale
 * et la plus coûteuse d'une API. Il suffirait d'ajouter « role: admin » ou
 * « etat: publiee » au formulaire pour se promouvoir ou se publier seul.
 *
 * D'où des listes de champs AUTORISÉS, jamais de champs interdits : un champ
 * ajouté au schéma demain sera refusé par défaut, alors qu'une liste
 * d'interdits l'aurait laissé passer sans que personne n'y pense.
 * ====================================================================== */

/** Ce qu'un membre peut changer sur lui-même. */
const CHAMPS_PROFIL = [
    'pseudo',
    'nom_reel',
    'afficher_nom_reel',
    'tranche_taille',
    'sexe',
];

/** Ce qu'un auteur peut changer sur sa performance. */
const CHAMPS_PERFORMANCE = [
    'mouvement',
    'equipement',
    'charge_kg',
    'tranche_poids',
    'date_performance',
    'lieu',
    'lien_video',
];

/**
 * Ne garde que les champs autorisés.
 *
 * Volontairement absents des deux listes, et donc impossibles à écrire depuis
 * une requête : `id`, `membre_id`, `role`, `courriel`, `courriel_verifie`,
 * `mot_de_passe_hash`, `google_sub`, `etat`, `motif_refus`, `moderateur_id`,
 * et toutes les colonnes de date.
 *
 * @param  array<string, mixed> $entree
 * @param  list<string>         $autorises
 * @return array<string, mixed>
 */
function filtrerChamps(array $entree, array $autorises): array
{
    return array_intersect_key($entree, array_flip($autorises));
}

/* =========================================================================
 * Passerelles HTTP
 *
 * Elles traduisent les décisions ci-dessus en réponses, et s'arrêtent net en
 * cas de refus. Aucune ne rend la main quand elle refuse : impossible d'oublier
 * un `return` derrière un appel.
 * ====================================================================== */

/**
 * Refuse et met fin à la requête.
 */
function refuser(int $code, string $message): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['erreur' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Le membre connecté, ou null.
 *
 * L'identité vient de la SESSION, jamais du corps de la requête. Un champ
 * `membre_id` envoyé par le navigateur permettrait de soumettre au nom de
 * n'importe qui : il n'est lu nulle part.
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

    $id = $_SESSION['membre_id'] ?? null;

    if (!is_int($id) || $id <= 0) {
        return null;
    }

    $membre = ligne(
        'SELECT id, courriel, pseudo, slug, nom_reel, afficher_nom_reel,
                sexe, tranche_taille, role, courriel_verifie
           FROM membre
          WHERE id = :id',
        ['id' => $id],
    );

    return $membre;
}

/** @return array<string, mixed> */
function exigerMembre(): array
{
    $membre = membreCourant();

    if ($membre === null) {
        refuser(401, 'Connexion requise.');
    }

    return $membre;
}

/** @return array<string, mixed> */
function exigerVerifie(): array
{
    $membre = exigerMembre();

    if (!peutSoumettre($membre)) {
        refuser(403, 'Adresse de courriel non vérifiée.');
    }

    return $membre;
}

/** @return array<string, mixed> */
function exigerModerateur(): array
{
    $membre = exigerMembre();

    if (!estModerateur($membre)) {
        // 404 et non 403 : répondre « interdit » confirmerait l'existence de la
        // file de modération à qui la cherche. Mieux vaut qu'elle n'existe pas
        // pour ceux qui n'y ont pas droit.
        refuser(404, 'Introuvable.');
    }

    return $membre;
}

/**
 * Exige que le membre connecté soit l'auteur de la performance.
 *
 * @param array<string, mixed> $performance
 */
function exigerAuteur(array $performance): array
{
    $membre = exigerMembre();

    if (!estAuteur($performance, $membre)) {
        refuser(403, 'Cette performance ne vous appartient pas.');
    }

    return $membre;
}

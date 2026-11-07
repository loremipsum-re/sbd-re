<?php
/**
 * QUI A LE DROIT DE FAIRE QUOI.
 *
 * -----------------------------------------------------------------------------
 * LE FICHIER LE PLUS SENSIBLE DU PROJET
 * -----------------------------------------------------------------------------
 * MySQL n'a pas de sécurité par ligne : la base exécute ce qu'on lui demande.
 * Le contrôle des droits vit donc ENTIÈREMENT ici, et c'est le seul endroit du
 * projet où une erreur laisserait un inconnu publier, voter ou bannir à la
 * place d'un autre. Les règles sont celles de docs/comptes.md, section 4.
 *
 * Trois règles d'écriture, à tenir en modifiant :
 *
 *   1. UN SEUL ENDROIT DÉCIDE. Aucun point d'entrée n'écrit sa propre
 *      vérification : il appelle une fonction d'ici.
 *
 *   2. LE REFUS EST LE DÉFAUT. Chaque fonction rend false tant qu'une
 *      condition n'est pas prouvée. Écrite dans l'autre sens, un `if` oublié
 *      laisserait passer au lieu de bloquer.
 *
 *   3. RIEN DE SENSIBLE NE VIENT DU CLIENT. L'identité se lit dans la session
 *      (lib/session.php). Les champs modifiables sont énumérés un par un.
 *
 * Les décisions prennent des tableaux en argument au lieu de lire l'état
 * courant : db/verifier-autorisation.php les éprouve toutes sans base.
 */

declare(strict_types=1);

namespace SBDRE;

if (!defined('SBDRE_API')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/session.php';

const ROLE_ADMIN = 'admin';

/** Le badge communauté : 20 votes au moins, dont 60 % favorables. */
const VOTES_MIN           = 20;
const FAVORABLES_POURCENT = 60;

/** L'ancienneté exigée pour voter, contre les comptes créés en série. */
const ANCIENNETE_VOTE_JOURS = 30;

/* =========================================================================
 * Qui est qui
 * ====================================================================== */

/**
 * Un membre « actif » : adresse vérifiée et non banni. C'est le seuil de tout
 * ce qui publie : profil, performance, vote, chat, signalement.
 *
 * Sans la vérification, n'importe qui inscrirait l'adresse d'un tiers pour
 * publier en son nom.
 *
 * @param array<string, mixed>|null $membre
 */
function estActif(?array $membre): bool
{
    if ($membre === null || (int) ($membre['id'] ?? 0) <= 0) {
        return false;
    }

    return (int) ($membre['courriel_verifie'] ?? 0) === 1
        && (int) ($membre['banni'] ?? 1) === 0;
}

/**
 * Un admin banni n'est plus admin. Le rôle se donne à la main en base, aucun
 * point d'entrée ne l'écrit.
 *
 * @param array<string, mixed>|null $membre
 */
function estAdmin(?array $membre): bool
{
    return estActif($membre) && ($membre['role'] ?? '') === ROLE_ADMIN;
}

/**
 * Le membre est-il l'auteur de cette performance ?
 *
 * Comparaison sur des entiers : « 12 » et 12 désignent la même personne, et
 * un identifiant nul n'appartient à personne.
 *
 * @param array<string, mixed>      $performance
 * @param array<string, mixed>|null $membre
 */
function estAuteur(array $performance, ?array $membre): bool
{
    if ($membre === null) {
        return false;
    }

    $id = (int) ($membre['id'] ?? 0);

    return $id > 0 && (int) ($performance['membre_id'] ?? 0) === $id;
}

/* =========================================================================
 * Les gestes, un par ligne du tableau de la section 4
 * ====================================================================== */

/**
 * Modifier son profil, ajouter une performance, écrire dans le chat,
 * signaler : le même seuil.
 *
 * @param array<string, mixed>|null $membre
 */
function peutPublier(?array $membre): bool
{
    return estActif($membre);
}

/**
 * Retirer une performance : son auteur, ou l'admin.
 *
 * @param array<string, mixed>      $performance
 * @param array<string, mixed>|null $membre
 */
function peutRetirerPerformance(array $performance, ?array $membre): bool
{
    return (estActif($membre) && estAuteur($performance, $membre)) || estAdmin($membre);
}

/**
 * Voter : actif, inscrit depuis 30 jours, jamais sur sa propre barre.
 *
 * shortcut: l'horloge de PHP est comparée à une date écrite par MySQL ; un
 * écart de fuseau décale la règle de quelques heures sur 30 jours, à corriger
 * si OVH sépare un jour les deux fuseaux.
 *
 * @param array<string, mixed>      $performance
 * @param array<string, mixed>|null $membre
 */
function peutVoter(array $performance, ?array $membre, int $maintenant): bool
{
    if (!estActif($membre) || estAuteur($performance, $membre)) {
        return false;
    }

    $inscription = strtotime((string) ($membre['date_inscription'] ?? ''));
    if ($inscription === false) {
        return false;
    }

    return $inscription <= $maintenant - ANCIENNETE_VOTE_JOURS * 86400;
}

/**
 * Poser le badge admin, masquer un message, bannir, traiter un signalement.
 *
 * @param array<string, mixed>|null $membre
 */
function peutModerer(?array $membre): bool
{
    return estAdmin($membre);
}

/**
 * Le badge communauté, calculé et jamais stocké. En entiers : 12 sur 20 vaut
 * exactement 60 %, sans arrondi de virgule flottante pour le faire tomber à
 * 59,999.
 */
function badgeCommunaute(int $votes, int $favorables): bool
{
    return $votes >= VOTES_MIN
        && $favorables >= 0
        && $favorables <= $votes
        && $favorables * 100 >= FAVORABLES_POURCENT * $votes;
}

/* =========================================================================
 * Champs acceptés depuis une requête : des listes fermées
 *
 * Recopier en base ce que le navigateur envoie est la faute la plus banale
 * d'une API : il suffirait d'ajouter « role: admin » au formulaire. D'où des
 * listes de champs AUTORISÉS : une colonne ajoutée demain au schéma sera
 * refusée par défaut.
 *
 * Absents des deux listes, donc impossibles à écrire depuis une requête :
 * `id`, `membre_id`, `courriel`, `mot_de_passe_hash`, `pseudo_precedent`,
 * `role`, `courriel_verifie`, `banni`, `ip_inscription`, `admin_id`,
 * `date_verif_admin` et toutes les dates.
 * ====================================================================== */

/**
 * Ce qu'un membre change sur son profil. `pseudo` et `instagram` passent en
 * plus par leur délai, un an et six mois, dans moi.php : leurs dates de
 * changement et l'ancien pseudo ne viennent jamais de la requête.
 */
const CHAMPS_PROFIL = ['pseudo', 'instagram', 'instagram_visible', 'bio', 'salle'];

/** Ce qu'un membre saisit en ajoutant une performance. */
const CHAMPS_PERFORMANCE = [
    'mouvement',
    'equipement',
    'charge_kg',
    'categorie',
    'date_performance',
    'lien_video',
];

/**
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
 * Elles traduisent les décisions en réponses et s'arrêtent net en cas de
 * refus. Chacune contrôle d'abord l'origine : un point d'entrée qui en
 * appelle une est protégé, même s'il oublie `exigerOrigine()`.
 * ====================================================================== */

/** @return array<string, mixed> */
function exigerMembre(): array
{
    exigerOrigine();
    $membre = membreCourant();

    if ($membre === null) {
        refuser(401, 'Connexion requise.');
    }

    return $membre;
}

/** @return array<string, mixed> */
function exigerActif(): array
{
    $membre = exigerMembre();

    if (!estActif($membre)) {
        refuser(403, 'Adresse de courriel non vérifiée.');
    }

    return $membre;
}

/** @return array<string, mixed> */
function exigerAdmin(): array
{
    $membre = exigerMembre();

    if (!estAdmin($membre)) {
        // 404 et non 403 : « interdit » confirmerait l'existence de la page
        // d'administration à qui la cherche.
        refuser(404, 'Introuvable.');
    }

    return $membre;
}

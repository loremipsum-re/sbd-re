<?php
/**
 * Vérification des règles d'autorisation.
 *
 * Ce script n'a besoin NI de base de données NI de session : il éprouve les
 * décisions pures de `public/api/lib/autorisation.php` sur une table de
 * vérité. C'est possible parce que ces fonctions prennent des tableaux en
 * argument au lieu d'aller chercher l'état courant elles-mêmes, et ce n'est pas
 * un hasard : du code de sécurité qu'on ne peut pas éprouver est du code de
 * sécurité qu'on ne relit jamais.
 *
 * Il couvre six des tentatives listées en section 8 de docs/communaute.md,
 * avant même qu'un seul point d'entrée existe :
 *
 *   - publier sa propre performance en envoyant etat=publiee
 *   - modifier la performance d'un autre membre
 *   - lire la file de modération avec un compte simple
 *   - se promouvoir modérateur en modifiant son profil
 *   - soumettre au nom d'un autre en envoyant membre_id
 *   - lire une performance en attente qui n'est pas la sienne
 *
 * -----------------------------------------------------------------------------
 * USAGE
 * -----------------------------------------------------------------------------
 * Déposer ce fichier sur le serveur, ainsi que les trois bibliothèques
 * `configuration.php`, `bdd.php` et `autorisation.php` dans un même dossier.
 * Puis :
 *
 *   php verifier-autorisation.php /chemin/vers/autorisation.php
 *
 * Sans argument, le script suppose l'arborescence du dépôt.
 *
 * Aucune connexion à la base n'a lieu : `configuration.php` et `bdd.php` ne
 * font que DÉFINIR des fonctions, et aucune n'est appelée ici. Le fichier de
 * secrets n'a donc même pas besoin d'exister.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Les bibliothèques refusent de se charger sans cette constante, qui prouve
// qu'elles sont incluses par du code du projet et non appelées par le web.
define('SBDRE_API', true);

$chemin = $argv[1] ?? dirname(__DIR__) . '/public/api/lib/autorisation.php';

if (!is_file($chemin)) {
    echo "autorisation.php introuvable ici : $chemin\n";
    echo "Passer son chemin complet en argument.\n";
    exit(1);
}

require_once $chemin;

// Les fonctions sont appelées par leur nom complet, « \SBDRE\… », plutôt
// qu'importées par `use function`. Un `use` placé après un `require` reste
// légal mais déroute la lecture, et rien ici ne justifie cette économie.

/* -------------------------------------------------------------------------
 * Affichage
 * ---------------------------------------------------------------------- */

$succes = 0;
$echecs = 0;

function titre(string $t): void
{
    echo "\n", $t, "\n", str_repeat('-', strlen($t)), "\n";
}

function verifie(string $intitule, bool $obtenu, bool $attendu): void
{
    global $succes, $echecs;

    if ($obtenu === $attendu) {
        $succes++;
        echo '  [ok]     ', $intitule, "\n";
        return;
    }

    $echecs++;
    printf(
        "  [ECHEC]  %s (attendu %s, obtenu %s)\n",
        $intitule,
        $attendu ? 'autorisé' : 'REFUSÉ',
        $obtenu ? 'autorisé' : 'REFUSÉ',
    );
}

/* -------------------------------------------------------------------------
 * Personnages
 * ---------------------------------------------------------------------- */

$anonyme     = null;
$alice       = ['id' => 1, 'role' => 'membre',     'courriel_verifie' => 1];
$bob         = ['id' => 2, 'role' => 'membre',     'courriel_verifie' => 1];
$nonVerifie  = ['id' => 3, 'role' => 'membre',     'courriel_verifie' => 0];
$moderateur  = ['id' => 4, 'role' => 'moderateur', 'courriel_verifie' => 1];
$admin       = ['id' => 5, 'role' => 'admin',      'courriel_verifie' => 1];

// Performances d'Alice, dans les trois états.
$publiee   = ['id' => 10, 'membre_id' => 1, 'etat' => 'publiee'];
$enAttente = ['id' => 11, 'membre_id' => 1, 'etat' => 'en_attente'];
$refusee   = ['id' => 12, 'membre_id' => 1, 'etat' => 'refusee'];

// Une performance du modérateur lui-même.
$duModerateur = ['id' => 13, 'membre_id' => 4, 'etat' => 'en_attente'];
$deLAdmin     = ['id' => 14, 'membre_id' => 5, 'etat' => 'en_attente'];

echo "\nVérification des règles d'autorisation\n";
echo "======================================\n";

/* -------------------------------------------------------------------------
 * Rôles
 * ---------------------------------------------------------------------- */

titre('Rôles');

verifie('Un visiteur anonyme n\'est pas modérateur',        \SBDRE\estModerateur($anonyme),    false);
verifie('Un membre simple n\'est pas modérateur',           \SBDRE\estModerateur($alice),      false);
verifie('Un modérateur est modérateur',                     \SBDRE\estModerateur($moderateur), true);
verifie('Un administrateur est modérateur',                 \SBDRE\estModerateur($admin),      true);
verifie('Un modérateur n\'est pas administrateur',          \SBDRE\estAdmin($moderateur),      false);
verifie('Un administrateur est administrateur',             \SBDRE\estAdmin($admin),           true);

/* -------------------------------------------------------------------------
 * Propriété
 * ---------------------------------------------------------------------- */

titre('Propriété');

verifie('Alice est l\'auteur de sa performance',            \SBDRE\estAuteur($publiee, $alice),   true);
verifie('Bob n\'est pas l\'auteur de celle d\'Alice',       \SBDRE\estAuteur($publiee, $bob),     false);
verifie('Un anonyme n\'est l\'auteur de rien',              \SBDRE\estAuteur($publiee, $anonyme), false);

// Les identifiants transitent parfois en chaîne : « 1 » et 1 doivent désigner
// la même personne, sans qu'une comparaison relâchée fasse valoir 0 à autre
// chose qu'un identifiant nul.
verifie('Un identifiant en chaîne vaut le même en entier',
    \SBDRE\estAuteur(['membre_id' => '1'], ['id' => 1]), true);
verifie('Un identifiant nul n\'ouvre aucun droit',
    \SBDRE\estAuteur(['membre_id' => 0], ['id' => 0]), false);

/* -------------------------------------------------------------------------
 * Lecture
 * ---------------------------------------------------------------------- */

titre('Lecture d\'une performance');

verifie('Une performance publiée se lit sans compte',
    \SBDRE\peutVoirPerformance($publiee, $anonyme), true);
verifie('Une performance en attente NE se lit PAS sans compte',
    \SBDRE\peutVoirPerformance($enAttente, $anonyme), false);
verifie('Une performance en attente NE se lit PAS par un autre membre',
    \SBDRE\peutVoirPerformance($enAttente, $bob), false);
verifie('Une performance refusée NE se lit PAS par un autre membre',
    \SBDRE\peutVoirPerformance($refusee, $bob), false);
verifie('Son auteur lit sa performance en attente',
    \SBDRE\peutVoirPerformance($enAttente, $alice), true);
verifie('Un modérateur lit une performance en attente',
    \SBDRE\peutVoirPerformance($enAttente, $moderateur), true);

/* -------------------------------------------------------------------------
 * Soumission
 * ---------------------------------------------------------------------- */

titre('Soumission');

verifie('Un anonyme ne peut pas soumettre',                 \SBDRE\peutSoumettre($anonyme),    false);
verifie('Une adresse non vérifiée ne peut pas soumettre',   \SBDRE\peutSoumettre($nonVerifie), false);
verifie('Un membre vérifié peut soumettre',                 \SBDRE\peutSoumettre($alice),      true);

/* -------------------------------------------------------------------------
 * Modification et suppression
 * ---------------------------------------------------------------------- */

titre('Modification et suppression');

verifie('Alice modifie sa performance',
    \SBDRE\peutModifierPerformance($publiee, $alice), true);
verifie('Bob NE modifie PAS celle d\'Alice',
    \SBDRE\peutModifierPerformance($publiee, $bob), false);
verifie('Un modérateur NE modifie PAS le contenu d\'un autre',
    \SBDRE\peutModifierPerformance($publiee, $moderateur), false);
verifie('Un anonyme ne modifie rien',
    \SBDRE\peutModifierPerformance($publiee, $anonyme), false);
verifie('Bob NE supprime PAS la performance d\'Alice',
    \SBDRE\peutSupprimerPerformance($publiee, $bob), false);
verifie('Alice supprime la sienne',
    \SBDRE\peutSupprimerPerformance($publiee, $alice), true);

/* -------------------------------------------------------------------------
 * Modération
 * ---------------------------------------------------------------------- */

titre('Modération');

verifie('Un anonyme ne modère pas',
    \SBDRE\peutModerer($enAttente, $anonyme), false);
verifie('Un membre simple ne modère pas',
    \SBDRE\peutModerer($enAttente, $bob), false);
verifie('Un modérateur modère la performance d\'un autre',
    \SBDRE\peutModerer($enAttente, $moderateur), true);
verifie('Un modérateur NE modère PAS la sienne',
    \SBDRE\peutModerer($duModerateur, $moderateur), false);
verifie('Un administrateur modère la sienne (seul modérateur du site)',
    \SBDRE\peutModerer($deLAdmin, $admin), true);

/* -------------------------------------------------------------------------
 * Champs modifiables
 *
 * C'est la protection contre le geste le plus simple et le plus efficace :
 * ajouter un champ au formulaire pour écrire une colonne qui ne regarde pas
 * l'utilisateur.
 * ---------------------------------------------------------------------- */

titre('Champs acceptés depuis une requête');

$profilEnvoye = \SBDRE\filtrerChamps([
    'pseudo'           => 'nouveau',
    'role'             => 'admin',      // tentative de promotion
    'courriel_verifie' => 1,            // tentative d'auto-validation
    'id'               => 999,
], \SBDRE\CHAMPS_PROFIL);

verifie('Le pseudo est accepté',
    array_key_exists('pseudo', $profilEnvoye), true);
verifie('Se promouvoir « admin » est ignoré',
    array_key_exists('role', $profilEnvoye), false);
verifie('Valider soi-même son courriel est ignoré',
    array_key_exists('courriel_verifie', $profilEnvoye), false);
verifie('Changer son identifiant est ignoré',
    array_key_exists('id', $profilEnvoye), false);

$perfEnvoyee = \SBDRE\filtrerChamps([
    'charge_kg'     => 200,
    'etat'          => 'publiee',   // tentative d'auto-publication
    'membre_id'     => 2,           // tentative de soumission au nom d'un autre
    'moderateur_id' => 4,
], \SBDRE\CHAMPS_PERFORMANCE);

verifie('La charge est acceptée',
    array_key_exists('charge_kg', $perfEnvoyee), true);
verifie('Se publier soi-même est ignoré',
    array_key_exists('etat', $perfEnvoyee), false);
verifie('Soumettre au nom d\'un autre est ignoré',
    array_key_exists('membre_id', $perfEnvoyee), false);
verifie('Se désigner modérateur de sa performance est ignoré',
    array_key_exists('moderateur_id', $perfEnvoyee), false);

/* -------------------------------------------------------------------------
 * Bilan
 * ---------------------------------------------------------------------- */

titre('Bilan');

echo "  Réussis : $succes\n";
echo "  Échecs  : $echecs\n\n";

if ($echecs > 0) {
    echo "  Une règle d'autorisation ne fait pas ce qu'elle annonce.\n";
    echo "  Ne rien construire par-dessus avant correction.\n\n";
    exit(1);
}

echo "  Règles d'autorisation vérifiées.\n\n";
exit(0);

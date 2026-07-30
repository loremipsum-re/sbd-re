<?php
/**
 * Vérification de la fondation de la partie communauté.
 *
 * Ce script ne construit rien. Il répond par la mesure à cinq questions dont
 * tout le reste dépend, sur le serveur OVH lui-même, puisque la base n'est
 * joignable que de là.
 *
 *   1. PDO et son pilote MySQL sont-ils présents ?
 *   2. La connexion à la base passe-t-elle ?
 *   3. Le `sql_mode` imposé à la session est-il bien pris en compte ?
 *   4. Les quatre tables existent-elles ?
 *   5. Le mode strict REFUSE-t-il réellement une saisie aberrante ?
 *
 * La cinquième est la vraie raison d'être de ce fichier. Le serveur d'OVH
 * tourne en `sql_mode` permissif : une charge de 1500 kg y entrerait rabotée à
 * 999,99 kg, sans un mot, et la contrainte CHECK du schéma n'y changerait rien
 * puisqu'elle s'applique après le rabotage. Vérifier qu'une sécurité BLOQUE vaut
 * mieux que vérifier qu'un site marche.
 *
 * -----------------------------------------------------------------------------
 * COMMENT S'EN SERVIR
 * -----------------------------------------------------------------------------
 * 1. Copier `db/config.exemple.php` en `config.php` et y mettre les vraies
 *    valeurs de la base. Ce fichier ne part JAMAIS sur GitHub.
 * 2. Déposer par SFTP `config.php` et `verifier.php` dans un même dossier, hors
 *    de l'arborescence servie par le web.
 * 3. En SSH, depuis ce dossier :  php verifier.php
 * 4. Supprimer ensuite `verifier.php`. Il n'a plus d'utilité.
 *
 * Ce script N'ÉCRIT RIEN de durable : ses essais d'insertion se déroulent dans
 * une transaction systématiquement annulée. La base ressort telle qu'elle est
 * entrée.
 */

declare(strict_types=1);

/* -------------------------------------------------------------------------
 * Refus de s'exécuter par le web
 *
 * Ce fichier lit la configuration et se connecte à la base. Servi par HTTP, il
 * répondrait à quiconque le demande. Le garde-fou est ici, en plus du fait de
 * le déposer hors de la racine web : deux protections indépendantes valent mieux
 * qu'une bien intentionnée.
 * ---------------------------------------------------------------------- */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/* -------------------------------------------------------------------------
 * Petit affichage
 * ---------------------------------------------------------------------- */

$succes = 0;
$echecs = 0;

function titre(string $texte): void
{
    echo "\n", $texte, "\n", str_repeat('-', strlen($texte)), "\n";
}

function ok(string $texte): void
{
    global $succes;
    $succes++;
    echo '  [ok]     ', $texte, "\n";
}

function ko(string $texte): void
{
    global $echecs;
    $echecs++;
    echo '  [ECHEC]  ', $texte, "\n";
}

function info(string $texte): void
{
    echo '           ', $texte, "\n";
}

echo "\nVérification de la fondation communauté de SBD.re\n";
echo "=================================================\n";

/* -------------------------------------------------------------------------
 * 1. L'environnement PHP
 * ---------------------------------------------------------------------- */

titre('1. Environnement PHP');

info('Version : ' . PHP_VERSION);

// PDO est l'interface par laquelle l'API parlera à MySQL. Son intérêt tient aux
// requêtes préparées : la valeur saisie par un visiteur ne se mélange jamais au
// texte de la requête, ce qui ferme la porte aux injections SQL par construction
// plutôt que par vigilance.
if (extension_loaded('pdo_mysql')) {
    ok('L\'extension pdo_mysql est chargée.');
} else {
    ko('L\'extension pdo_mysql est ABSENTE. Rien ne peut fonctionner sans elle.');
    info('Modules présents : ' . implode(', ', get_loaded_extensions()));
    exit(1);
}

/* -------------------------------------------------------------------------
 * 2. La configuration
 * ---------------------------------------------------------------------- */

titre('2. Configuration');

$chemin = __DIR__ . '/config.php';

if (!is_file($chemin)) {
    ko('config.php introuvable.');
    info('Attendu ici : ' . $chemin);
    info('Copier db/config.exemple.php, le renommer, et y mettre les vraies valeurs.');
    exit(1);
}

/** @var array<string, mixed> $config */
$config = require $chemin;

foreach (['hote', 'base', 'utilisateur', 'mot_de_passe'] as $clef) {
    $valeur = $config['bdd'][$clef] ?? '';
    if ($valeur === '' || str_starts_with((string) $valeur, 'a-completer')) {
        ko("La valeur « bdd.$clef » n'est pas renseignée.");
        exit(1);
    }
}

ok('config.php est lisible et renseigné.');
info('Base visée : ' . $config['bdd']['base'] . ' sur ' . $config['bdd']['hote']);

/* -------------------------------------------------------------------------
 * 3. La connexion, avec le sql_mode imposé
 *
 * MYSQL_ATTR_INIT_COMMAND exécute la commande à CHAQUE ouverture de connexion,
 * y compris après une reconnexion automatique. C'est ce qui garantit qu'aucune
 * requête ne s'exécute jamais en mode permissif.
 * ---------------------------------------------------------------------- */

titre('3. Connexion à la base');

$modeVoulu = $config['bdd']['sql_mode'] ?? '';

if ($modeVoulu === '') {
    ko('Aucun sql_mode dans la configuration. Reprendre db/config.exemple.php.');
    exit(1);
}

$dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=%s',
    $config['bdd']['hote'],
    $config['bdd']['base'],
    $config['bdd']['jeu'] ?? 'utf8mb4',
);

try {
    $bdd = new PDO($dsn, $config['bdd']['utilisateur'], $config['bdd']['mot_de_passe'], [
        // Sans ceci, PDO signale les erreurs par un code de retour que l'on
        // oublie de lire une fois sur deux. En exceptions, une erreur ne peut
        // pas passer inaperçue.
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,

        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

        // De VRAIES requêtes préparées, envoyées au serveur, au lieu de
        // l'émulation qui recolle la requête côté PHP avant de l'expédier.
        PDO::ATTR_EMULATE_PREPARES   => false,

        PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION sql_mode = '$modeVoulu'",
    ]);
    ok('Connexion établie.');
} catch (PDOException $e) {
    ko('Connexion impossible : ' . $e->getMessage());
    info('Vérifier l\'hôte, la base, l\'identifiant et le mot de passe.');
    info('Rappel : cette base n\'est joignable que DEPUIS l\'hébergement OVH.');
    exit(1);
}

info('Version du serveur : ' . $bdd->query('SELECT VERSION()')->fetchColumn());

// On ne se contente pas d'avoir DEMANDÉ le mode strict : on relit ce que la
// session applique réellement. Une commande d'initialisation qui échoue est
// silencieuse.
$modeReel = (string) $bdd->query('SELECT @@SESSION.sql_mode')->fetchColumn();

foreach (['STRICT_ALL_TABLES', 'ONLY_FULL_GROUP_BY', 'NO_ZERO_DATE'] as $attendu) {
    if (str_contains($modeReel, $attendu)) {
        ok("Le mode $attendu est actif sur la session.");
    } else {
        ko("Le mode $attendu N'EST PAS actif. sql_mode réel : $modeReel");
    }
}

/* -------------------------------------------------------------------------
 * 4. Les tables
 * ---------------------------------------------------------------------- */

titre('4. Tables du schéma');

$presentes = $bdd->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

foreach (['membre', 'performance', 'jeton', 'tentative_connexion'] as $table) {
    if (in_array($table, $presentes, true)) {
        ok("La table $table existe.");
    } else {
        ko("La table $table est ABSENTE. Recharger db/schema.sql.");
    }
}

/* -------------------------------------------------------------------------
 * 5. Les essais qui DOIVENT échouer
 *
 * Toute cette section vit dans une transaction annulée à la fin. Rien ne reste.
 * ---------------------------------------------------------------------- */

// Inutile d'aller plus loin si le schéma est incomplet : les insertions
// suivantes s'effondreraient sur une erreur fatale, dont le message n'aiderait
// personne. On s'arrête sur un diagnostic lisible.
if ($echecs > 0) {
    titre('Bilan');
    echo "  Réussis : $succes\n  Échecs  : $echecs\n\n";
    echo "  Le schéma est incomplet ou la session n'est pas en mode strict.\n";
    echo "  Corriger les points [ECHEC] avant d'aller plus loin.\n\n";
    exit(1);
}

titre('5. Essais qui doivent être refusés');

$bdd->beginTransaction();

try {
    // Un membre de travail, pour que les clés étrangères aient de quoi pointer.
    $bdd->prepare(
        'INSERT INTO membre (courriel, pseudo, slug, sexe)
         VALUES (:courriel, :pseudo, :slug, :sexe)'
    )->execute([
        'courriel' => 'essai-' . bin2hex(random_bytes(4)) . '@exemple.re',
        'pseudo'   => 'essai-' . bin2hex(random_bytes(4)),
        'slug'     => 'essai-' . bin2hex(random_bytes(4)),
        'sexe'     => 'M',
    ]);
    $membreId = (int) $bdd->lastInsertId();
    ok('Insertion d\'un membre valide : acceptée, comme prévu.');

    /**
     * Tente une insertion de performance et dit si elle a été refusée.
     */
    $essai = function (string $intitule, array $champs) use ($bdd, $membreId): void {
        $defaut = [
            'membre_id'        => $membreId,
            'mouvement'        => 'squat',
            'equipement'       => 'raw',
            'charge_kg'        => 180.5,
            'tranche_poids'    => '80-90',
            'tranche_age'      => '24-39',
            'date_performance' => '2026-01-15',
            'lien_video'       => 'https://exemple.re/video',
        ];
        $valeurs = array_merge($defaut, $champs);

        try {
            $bdd->prepare(
                'INSERT INTO performance
                   (membre_id, mouvement, equipement, charge_kg, tranche_poids,
                    tranche_age, date_performance, lien_video)
                 VALUES
                   (:membre_id, :mouvement, :equipement, :charge_kg, :tranche_poids,
                    :tranche_age, :date_performance, :lien_video)'
            )->execute($valeurs);

            // Aucune exception : la valeur est passée. C'est un échec du test.
            $id = (int) $bdd->lastInsertId();
            $stocke = $bdd->prepare('SELECT charge_kg FROM performance WHERE id = ?');
            $stocke->execute([$id]);
            ko($intitule . ' : ACCEPTÉE. Valeur réellement stockée : '
               . $stocke->fetchColumn());
        } catch (PDOException $e) {
            ok($intitule . ' : refusée. (' . $e->getCode() . ')');
        }
    };

    // Le cas qui motive tout ce fichier. En mode permissif, MySQL ramènerait
    // 1500 à 999,99 — la borne du DECIMAL(5,2) — et la contrainte CHECK
    // trouverait cette valeur rabotée parfaitement conforme.
    $essai('Charge de 1500 kg (faute de frappe)', ['charge_kg' => 1500]);

    // La contrainte CHECK du schéma, elle, se charge du négatif.
    $essai('Charge négative de -50 kg', ['charge_kg' => -50]);

    // Sans mode strict, la chaîne serait tronquée à la largeur de la colonne.
    $essai('Tranche de poids trop longue', ['tranche_poids' => str_repeat('9', 40)]);

    // Sans NO_ZERO_DATE, cette date serait acceptée comme valide.
    $essai('Date 0000-00-00', ['date_performance' => '0000-00-00']);

    // Valeur absente de l'ENUM : refusée en strict, remplacée par une chaîne
    // vide en permissif.
    $essai('Mouvement inconnu « curl »', ['mouvement' => 'curl']);

    // Clé étrangère : un membre qui n'existe pas.
    $essai('Membre inexistant (n° 999999999)', ['membre_id' => 999999999]);

    // Contrôle de bon fonctionnement : une performance valide doit passer.
    // Sans ce contrôle, un schéma qui refuse TOUT paraîtrait irréprochable.
    try {
        $bdd->prepare(
            'INSERT INTO performance
               (membre_id, mouvement, equipement, charge_kg, tranche_poids,
                tranche_age, date_performance, lien_video)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$membreId, 'bench', 'raw', 122.5, '80-90', '24-39',
                    '2026-03-02', 'https://exemple.re/video']);
        ok('Insertion d\'une performance valide : acceptée, comme prévu.');
    } catch (PDOException $e) {
        ko('Une performance VALIDE a été refusée : ' . $e->getMessage());
    }

    // L'effacement en cascade, dont dépend le droit à l'effacement du RGPD.
    $bdd->prepare('DELETE FROM membre WHERE id = ?')->execute([$membreId]);
    $restantes = $bdd->prepare('SELECT COUNT(*) FROM performance WHERE membre_id = ?');
    $restantes->execute([$membreId]);

    if ((int) $restantes->fetchColumn() === 0) {
        ok('Effacement du membre : ses performances partent en cascade.');
    } else {
        ko('Des performances SURVIVENT à l\'effacement de leur membre.');
    }
} catch (Throwable $e) {
    // Une erreur qu'aucun essai n'attendait. On la montre plutôt que de laisser
    // PHP afficher une trace d'appels dont on ne tire rien.
    ko('Essai interrompu par une erreur inattendue : ' . $e->getMessage());
} finally {
    // Annulation systématique, y compris en cas d'exception : la base ressort
    // exactement telle qu'elle est entrée.
    $bdd->rollBack();
    info('Transaction annulée : rien n\'a été conservé.');
}

/* -------------------------------------------------------------------------
 * Bilan
 * ---------------------------------------------------------------------- */

titre('Bilan');

echo "  Réussis : $succes\n";
echo "  Échecs  : $echecs\n\n";

if ($echecs > 0) {
    echo "  La fondation n'est pas saine. Ne rien construire par-dessus avant\n";
    echo "  d'avoir corrigé les points marqués [ECHEC].\n\n";
    exit(1);
}

echo "  Fondation vérifiée. On peut écrire l'autorisation et les points d'entrée.\n\n";
exit(0);

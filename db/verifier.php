<?php
/**
 * Vérification de la fondation des comptes, profils et chat.
 *
 * Ce script ne construit rien. Il répond par la mesure à cinq questions dont
 * tout le reste dépend, sur le serveur OVH lui-même, puisque la base n'est
 * joignable que de là.
 *
 *   1. PDO et son pilote MySQL sont-ils présents ?
 *   2. La connexion à la base passe-t-elle ?
 *   3. Le `sql_mode` imposé à la session est-il bien pris en compte ?
 *   4. Les sept tables existent-elles, dans leur version du 6 novembre 2026 ?
 *   5. Le schéma REFUSE-t-il réellement ce qu'il doit refuser ?
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
 * Ce script N'ÉCRIT RIEN de durable : ses essais se déroulent dans une
 * transaction systématiquement annulée. Seuls les compteurs AUTO_INCREMENT
 * avancent, MySQL ne les rembobinant pas : les identifiants suivants
 * sauteront quelques numéros, sans autre effet.
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
 * 4. Les tables, dans leur version du 6 novembre
 *
 * `CREATE TABLE IF NOT EXISTS` ne remplace jamais une table existante. Une
 * table de juillet garderait donc ses anciennes colonnes sans rien dire : on
 * relit chacune et on y cherche une colonne propre à la nouvelle version.
 * ---------------------------------------------------------------------- */

titre('4. Tables du schéma');

$attendues = [
    'membre'              => ['pseudo', 'pseudo_precedent', 'date_pseudo', 'instagram', 'date_instagram', 'instagram_visible', 'banni', 'ip_inscription'],
    'performance'         => ['categorie', 'admin_id', 'date_verif_admin'],
    'vote'                => ['performance_id', 'membre_id', 'ok'],
    'message'             => ['canal', 'pseudo', 'texte', 'masque_par'],
    'signalement'         => ['type', 'cible_id', 'traite'],
    'jeton'               => ['membre_id', 'type', 'jeton_hash'],
    'tentative_connexion' => ['courriel', 'ip', 'reussie'],
];

$lireColonnes = $bdd->prepare(
    'SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
);

foreach ($attendues as $table => $colonnes) {
    $lireColonnes->execute([$table]);
    $presentes = array_column($lireColonnes->fetchAll(), 'COLUMN_TYPE', 'COLUMN_NAME');

    if ($presentes === []) {
        ko("La table $table est ABSENTE. Charger db/schema.sql.");
        continue;
    }

    $manquantes = array_diff($colonnes, array_keys($presentes));

    // Le type « session » des jetons est la seule nouveauté de cette table.
    if ($table === 'jeton' && !str_contains($presentes['type'] ?? '', "'session'")) {
        $manquantes[] = "type 'session'";
    }

    if ($manquantes === []) {
        ok("La table $table est à jour.");
    } else {
        ko("La table $table est une ANCIENNE version, il manque : " . implode(', ', $manquantes));
        info('Supprimer les tables (commande en tête de db/schema.sql), puis le recharger.');
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

titre('5. Essais');

// Les requêtes de l'API, éprouvées telles qu'elles sont écrites : depuis le
// dépôt, ou depuis ~/sbd-re-prive comme db/dkim.php. Le fichier ne fait que
// déclarer, il ne se connecte à rien en se chargeant.
foreach ([
    dirname(__DIR__) . '/public/api/lib/performances.php',
    dirname(__DIR__) . '/sbd-re/api/lib/performances.php',
] as $bibliotheque) {
    if (is_file($bibliotheque)) {
        define('SBDRE_API', true);
        require $bibliotheque;
        require_once dirname($bibliotheque) . '/forum.php';
        require_once dirname($bibliotheque) . '/chat.php';
        require_once dirname($bibliotheque) . '/signalements.php';
        require_once dirname($bibliotheque) . '/recapitulatif.php';
        break;
    }
}
if (!defined('SBDRE\SQL_PERFORMANCES_VOTES')) {
    ko('lib/performances.php introuvable : les requêtes des votes ne seront pas éprouvées.');
}

/** Un essai qui doit passer. Rend l'identifiant créé, 0 en cas de refus. */
$accepte = function (string $intitule, string $sql, array $valeurs) use ($bdd): int {
    try {
        $bdd->prepare($sql)->execute($valeurs);
        ok($intitule . ' : accepté, comme prévu.');
        return (int) $bdd->lastInsertId();
    } catch (PDOException $e) {
        ko($intitule . ' : REFUSÉ alors qu\'il est valide. ' . $e->getMessage());
        return 0;
    }
};

/** Un essai qui doit être refusé. Une acceptation est un échec du test. */
$refuse = function (string $intitule, string $sql, array $valeurs) use ($bdd): void {
    try {
        $bdd->prepare($sql)->execute($valeurs);
        ko($intitule . ' : ACCEPTÉ alors qu\'il devait être refusé.');
    } catch (PDOException $e) {
        ok($intitule . ' : refusé. (' . $e->getCode() . ')');
    }
};

/** Lit un nombre, pour les contrôles après effacement. */
$compter = function (string $sql, array $valeurs) use ($bdd): int {
    $requete = $bdd->prepare($sql);
    $requete->execute($valeurs);
    return (int) $requete->fetchColumn();
};

$bdd->beginTransaction();

try {
    /* --- Membres ------------------------------------------------------ */

    info('Membres');

    $sqlMembre = 'INSERT INTO membre (courriel, mot_de_passe_hash, pseudo, ip_inscription)
                  VALUES (?, ?, ?, ?)';
    $hash = password_hash('essai', PASSWORD_DEFAULT);

    // Un suffixe aléatoire en minuscules et chiffres : deux passages du script
    // ne se gênent pas, et chaque refus vient bien de la règle visée, jamais
    // d'un doublon accidentel.
    $s = bin2hex(random_bytes(3));
    $ip = '203.0.113.1'; // plage réservée à la documentation, jamais routée

    $membreId = $accepte('Membre valide', $sqlMembre, ["essai-$s@exemple.re", $hash, "essai_$s", $ip]);
    $autreId  = $accepte('Second membre valide, pseudo à point', $sqlMembre, ["autre-$s@exemple.re", $hash, "autre.$s", $ip]);

    if ($membreId === 0 || $autreId === 0) {
        throw new RuntimeException('impossible de créer les membres de travail, la suite n\'aurait pas de sens.');
    }

    $refuse('Pseudo en majuscules', $sqlMembre, ["a-$s@exemple.re", $hash, "Jean$s", $ip]);
    $refuse('Pseudo fait de points seulement', $sqlMembre, ["b-$s@exemple.re", $hash, '...', $ip]);
    $refuse('Pseudo à tiret, réservé au gabarit', $sqlMembre, ["c-$s@exemple.re", $hash, "jean-$s", $ip]);
    $refuse('Pseudo de 31 caractères', $sqlMembre, ["d-$s@exemple.re", $hash, str_repeat('a', 31), $ip]);
    $refuse('Pseudo de 2 caractères', $sqlMembre, ["e-$s@exemple.re", $hash, 'ab', $ip]);
    $refuse('Pseudo à espace', $sqlMembre, ["f-$s@exemple.re", $hash, "jean $s", $ip]);
    $refuse('Pseudo suivi d\'un retour à la ligne', $sqlMembre, ["h-$s@exemple.re", $hash, "jean$s\n", $ip]);
    $refuse('Pseudo déjà pris', $sqlMembre, ["g-$s@exemple.re", $hash, "essai_$s", $ip]);
    $refuse('Courriel déjà pris', $sqlMembre, ["essai-$s@exemple.re", $hash, "neuf_$s", $ip]);
    $refuse('Présentation de 161 caractères',
        'UPDATE membre SET bio = ? WHERE id = ?', [str_repeat('a', 161), $membreId]);
    $refuse('Rôle inconnu « moderateur »',
        'UPDATE membre SET role = ? WHERE id = ?', ['moderateur', $membreId]);

    // Le nom Instagram, distinct du pseudo (migration du 7 novembre 2026).
    $sqlInstagram = 'UPDATE membre SET instagram = ? WHERE id = ?';
    try {
        $bdd->prepare($sqlInstagram)->execute(["la.force_$s", $membreId]);
        ok('Nom Instagram valide : accepté, comme prévu.');
    } catch (PDOException $e) {
        ko('Nom Instagram valide : REFUSÉ. ' . $e->getMessage());
    }
    $refuse('Nom Instagram en majuscules', $sqlInstagram, ["LaForce$s", $autreId]);
    $refuse('Nom Instagram à tiret', $sqlInstagram, ["la-force-$s", $autreId]);
    $refuse('Nom Instagram fait de points seulement', $sqlInstagram, ['..', $autreId]);
    $refuse('Nom Instagram déjà pris par un autre membre', $sqlInstagram, ["la.force_$s", $autreId]);

    /* --- Performances ------------------------------------------------- */

    info('Performances');

    $sqlPerf = 'INSERT INTO performance
                  (membre_id, mouvement, equipement, charge_kg, date_performance, lien_video)
                VALUES (:membre_id, :mouvement, :equipement, :charge_kg, :date_performance, :lien_video)';
    $perf = fn (array $champs): array => array_merge([
        'membre_id'        => $membreId,
        'mouvement'        => 'squat',
        'equipement'       => 'raw',
        'charge_kg'        => 180.5,
        'date_performance' => '2026-10-15',
        'lien_video'       => 'https://www.instagram.com/p/essai/',
    ], $champs);

    $perfId  = $accepte('Performance valide', $sqlPerf, $perf([]));
    $perf2Id = $accepte('Performance valide du second membre', $sqlPerf, $perf(['membre_id' => $autreId]));

    // Le cas qui motive tout ce fichier. En mode permissif, MySQL ramènerait
    // 1500 à 999,99, la borne du DECIMAL(5,2), et la contrainte CHECK
    // trouverait cette valeur rabotée parfaitement conforme.
    $refuse('Charge de 1500 kg (faute de frappe)', $sqlPerf, $perf(['charge_kg' => 1500]));
    $refuse('Charge négative de -50 kg', $sqlPerf, $perf(['charge_kg' => -50]));
    $refuse('Charge nulle', $sqlPerf, $perf(['charge_kg' => 0]));
    $refuse('Date 0000-00-00', $sqlPerf, $perf(['date_performance' => '0000-00-00']));
    $refuse('Mouvement inconnu « curl »', $sqlPerf, $perf(['mouvement' => 'curl']));
    $refuse('Membre inexistant (n° 999999999)', $sqlPerf, $perf(['membre_id' => 999999999]));
    $refuse('Lien « javascript: »', $sqlPerf, $perf(['lien_video' => 'javascript:alert(1)']));
    $refuse('Lien en http sans s', $sqlPerf, $perf(['lien_video' => 'http://www.instagram.com/p/essai/']));
    $refuse('Catégorie de 6 caractères',
        'UPDATE performance SET categorie = ? WHERE id = ?', ['120+++', $perfId]);

    /* --- Votes -------------------------------------------------------- */

    info('Votes');

    $sqlVote = 'INSERT INTO vote (performance_id, membre_id, ok) VALUES (?, ?, ?)';

    $accepte('Vote valide', $sqlVote, [$perfId, $autreId, 1]);
    $refuse('Second vote du même membre', $sqlVote, [$perfId, $autreId, 0]);
    $refuse('Vote valant 2', $sqlVote, [$perf2Id, $membreId, 2]);
    $refuse('Vote sur une performance inexistante', $sqlVote, [999999999, $autreId, 1]);

    // Changer d'avis passe par une mise à jour de la même ligne.
    try {
        $bdd->prepare('UPDATE vote SET ok = 0 WHERE performance_id = ? AND membre_id = ?')
            ->execute([$perfId, $autreId]);
        $avis = $compter('SELECT ok FROM vote WHERE performance_id = ? AND membre_id = ?', [$perfId, $autreId]);
        $avis === 0 ? ok('Changer d\'avis : accepté, une seule ligne.') : ko('Changer d\'avis n\'a rien changé.');
    } catch (PDOException $e) {
        ko('Changer d\'avis : REFUSÉ. ' . $e->getMessage());
    }

    if (defined('SBDRE\SQL_PERFORMANCES_VOTES')) {
        // Un troisième membre, qui finira banni. Il vote sur la barre du second :
        // le premier y vote déjà plus bas, dans l'effacement.
        $banniId = $accepte('Troisième membre, qui sera banni', $sqlMembre, ["banni-$s@exemple.re", $hash, "banni_$s", $ip]);

        // Le vote de l'API, deux fois de suite : la seconde remplace la première.
        $voter = fn (int $p, int $m, int $avis) => $bdd->prepare(\SBDRE\SQL_VOTER)->execute(['p' => $p, 'm' => $m, 'ok' => $avis]);
        try {
            $voter($perf2Id, $banniId, 0);
            $voter($perf2Id, $banniId, 1);
            $compter('SELECT COUNT(*) FROM vote WHERE performance_id = ? AND membre_id = ? AND ok = 1', [$perf2Id, $banniId]) === 1
                ? ok('Le vote de l\'API remplace l\'avis précédent, une seule ligne.')
                : ko('Le vote de l\'API n\'a pas remplacé l\'avis précédent.');
        } catch (PDOException $e) {
            ko('Le vote de l\'API est REFUSÉ par MySQL. ' . $e->getMessage());
        }

        // Banni, il vote pour la barre du premier : son avis ne compte pas.
        $bdd->prepare('UPDATE membre SET banni = 1 WHERE id = ?')->execute([$banniId]);
        $bdd->prepare($sqlVote)->execute([$perfId, $banniId, 1]);

        try {
            $requete = $bdd->prepare(sprintf(\SBDRE\SQL_PERFORMANCES_VOTES, 'p.id = :id', ''));
            $requete->execute(['id' => $perfId]);
            $lue = $requete->fetch(PDO::FETCH_ASSOC);
            // Sur $perfId : l'avis défavorable du second membre, celui du banni écarté.
            ($lue !== false && (int) $lue['votes'] === 1 && (int) $lue['favorables'] === 0)
                ? ok('Le compte des votes écarte le membre banni : 1 avis, 0 favorable.')
                : ko('Le compte des votes est faux : ' . json_encode($lue));
        } catch (PDOException $e) {
            ko('La requête des votes est REFUSÉE par MySQL. ' . $e->getMessage());
        }
    }

    /* --- Messages ----------------------------------------------------- */

    info('Messages');

    $sqlMessage = 'INSERT INTO message (canal, membre_id, pseudo, texte, ip) VALUES (?, ?, ?, ?, ?)';

    $messageId = $accepte('Message valide, smiley et emoji compris', $sqlMessage,
        ['general', $membreId, "essai_$s", 'Nouveau record au squat :) 💪', $ip]);
    $refuse('Message de 501 caractères', $sqlMessage,
        ['general', $membreId, "essai_$s", str_repeat('a', 501), $ip]);
    $refuse('Message vide', $sqlMessage, ['general', $membreId, "essai_$s", '', $ip]);

    $bdd->prepare('UPDATE message SET masque_par = ? WHERE id = ?')->execute([$membreId, $messageId]);

    /* --- Signalements ------------------------------------------------- */

    info('Signalements');

    $sqlSignal = 'INSERT INTO signalement (type, cible_id, auteur_id, motif) VALUES (?, ?, ?, ?)';

    $accepte('Signalement valide', $sqlSignal, ['message', $messageId, $autreId, 'Essai']);
    $refuse('Même signalement en double', $sqlSignal, ['message', $messageId, $autreId, 'Encore']);
    $refuse('Type de cible inconnu', $sqlSignal, ['salon', 1, $autreId, null]);

    /* --- Jetons ------------------------------------------------------- */

    info('Jetons');

    $sqlJeton = 'INSERT INTO jeton (membre_id, type, jeton_hash, date_expiration)
                 VALUES (?, ?, ?, NOW() + INTERVAL 30 DAY)';
    $empreinte = hash('sha256', random_bytes(32));

    $accepte('Jeton de session', $sqlJeton, [$membreId, 'session', $empreinte]);
    $refuse('Même empreinte une seconde fois', $sqlJeton, [$autreId, 'session', $empreinte]);
    $refuse('Type de jeton inconnu', $sqlJeton, [$membreId, 'inconnu', hash('sha256', random_bytes(32))]);

    /* --- Effacement d'un compte --------------------------------------- */

    info('Effacement du premier membre');

    // L'admin du badge, joué ici par le premier membre.
    $bdd->prepare('UPDATE performance SET admin_id = ?, date_verif_admin = NOW() WHERE id = ?')
        ->execute([$membreId, $perf2Id]);
    $bdd->prepare('INSERT INTO vote (performance_id, membre_id, ok) VALUES (?, ?, 1)')
        ->execute([$perf2Id, $membreId]);

    $bdd->prepare('DELETE FROM membre WHERE id = ?')->execute([$membreId]);

    // Le droit à l'effacement : ses performances, ses votes et ses sessions
    // partent avec lui.
    $compter('SELECT COUNT(*) FROM performance WHERE membre_id = ?', [$membreId]) === 0
        ? ok('Ses performances partent en cascade.')
        : ko('Des performances SURVIVENT à l\'effacement de leur membre.');

    $compter('SELECT COUNT(*) FROM vote WHERE membre_id = ? OR performance_id = ?', [$membreId, $perfId]) === 0
        ? ok('Ses votes, et ceux donnés à ses performances, partent aussi.')
        : ko('Des votes SURVIVENT à l\'effacement.');

    $compter('SELECT COUNT(*) FROM jeton WHERE membre_id = ?', [$membreId]) === 0
        ? ok('Ses jetons de session partent aussi.')
        : ko('Des jetons SURVIVENT à l\'effacement.');

    // Le journal d'un an : le message reste, détaché du compte, signé du
    // pseudo copié, et toujours masqué.
    $compter(
        'SELECT COUNT(*) FROM message
          WHERE id = ? AND membre_id IS NULL AND pseudo = ? AND masque_par = ?',
        [$messageId, "essai_$s", $membreId]
    ) === 1
        ? ok('Son message reste au journal, sans compte, pseudo et masquage gardés.')
        : ko('Le message a disparu ou a perdu son pseudo ou son masquage.');

    // Le badge admin se lit sur la date : il survit à l'admin.
    $compter(
        'SELECT COUNT(*) FROM performance
          WHERE id = ? AND admin_id IS NULL AND date_verif_admin IS NOT NULL',
        [$perf2Id]
    ) === 1
        ? ok('Le badge admin qu\'il a posé reste en place.')
        : ko('Le badge admin a disparu avec son admin.');

    $compter('SELECT COUNT(*) FROM signalement WHERE cible_id = ? AND auteur_id = ?', [$messageId, $autreId]) === 1
        ? ok('Le signalement du second membre reste à traiter.')
        : ko('Le signalement a disparu.');
} catch (Throwable $e) {
    // Une erreur qu'aucun essai n'attendait. On la montre plutôt que de laisser
    // PHP afficher une trace d'appels dont on ne tire rien.
    ko('Essai interrompu par une erreur inattendue : ' . $e->getMessage());
} finally {
    // Annulation systématique, y compris en cas d'exception : la base ressort
    // telle qu'elle est entrée.
    $bdd->rollBack();
    info('Transaction annulée : rien n\'a été conservé.');
}

/* -------------------------------------------------------------------------
 * 6. Forum, chat et notifications
 *
 * Les fonctions mêmes de l'API (lib/forum.php, lib/chat.php,
 * lib/notifications.php), sur sa connexion à elle, sql_mode compris, dans
 * une transaction annulée à la fin.
 * ---------------------------------------------------------------------- */

titre('6. Forum, chat et notifications');

if (!function_exists('SBDRE\creerSujet')) {
    ko('lib/forum.php introuvable : forum, chat et notifications ne seront pas éprouvés.');
} else {
    $base = \SBDRE\bdd();
    $base->beginTransaction();
    try {
        $s2 = bin2hex(random_bytes(3));
        $ip2 = '203.0.113.1';
        $nouveau = function (string $nom) use ($base, $s2, $ip2): array {
            $base->prepare('INSERT INTO membre (courriel, mot_de_passe_hash, pseudo, ip_inscription, courriel_verifie)
                            VALUES (?, ?, ?, ?, 1)')->execute(["$nom-$s2@exemple.re", 'x', "{$nom}_$s2", $ip2]);
            return ['id' => (int) $base->lastInsertId(), 'pseudo' => "{$nom}_$s2"];
        };
        $nombre = fn (string $sql, array $v = []): int => (int) (\SBDRE\ligne($sql, $v)['n'] ?? -1);

        $auteur = $nouveau('auteur');
        $lecteur = $nouveau('lecteur');
        $cite = $nouveau('cite');

        info('Forum');

        $forum = \SBDRE\ligne("SELECT id, adresse FROM forum WHERE adresse = 'squat-technique'");
        if ($forum === null) {
            throw new RuntimeException('le forum « squat-technique » manque : la migration du forum n\'est pas importée.');
        }
        $nombre('SELECT COUNT(*) AS n FROM forum') >= 19
            ? ok('Les 19 forums de départ sont en place.')
            : ko('Il manque des forums de départ.');

        $sujetId = \SBDRE\creerSujet($auteur, (int) $forum['id'], 'Essai de sujet', 'Premier message :)', false, $ip2);
        $nombre('SELECT COUNT(*) AS n FROM abonnement WHERE sujet_id = :s AND membre_id = :m', ['s' => $sujetId, 'm' => $auteur['id']]) === 1
            ? ok('Un sujet s\'ouvre, et son auteur le suit d\'office.')
            : ko('L\'auteur ne suit pas le sujet qu\'il a ouvert.');

        \SBDRE\suivreSujet($lecteur['id'], $sujetId, true);
        \SBDRE\suivreSujet($lecteur['id'], $sujetId, true);
        $nombre('SELECT COUNT(*) AS n FROM abonnement WHERE sujet_id = :s AND membre_id = :m', ['s' => $sujetId, 'm' => $lecteur['id']]) === 1
            ? ok('Suivre deux fois ne double rien.')
            : ko('Suivre deux fois a doublé l\'abonnement.');

        $sujet = \SBDRE\ligne('SELECT id, titre, reponses FROM sujet WHERE id = :s', ['s' => $sujetId]);
        $lien = \SBDRE\ajouterReponse($lecteur, $sujet, (string) $forum['adresse'],
            "Bravo @{$cite['pseudo']} et @{$auteur['pseudo']}", false, $ip2);
        $apres = \SBDRE\ligne('SELECT reponses, dernier_pseudo FROM sujet WHERE id = :s', ['s' => $sujetId]);
        ((int) $apres['reponses'] === 1 && $apres['dernier_pseudo'] === $lecteur['pseudo'] && str_contains($lien, '#message-'))
            ? ok('Une réponse met le sujet à jour et rend le lien du message.')
            : ko('La réponse n\'a pas mis le sujet à jour : ' . json_encode($apres));

        // L'auteur suit le sujet et il est cité : une seule notification.
        $nAuteur = $nombre('SELECT COUNT(*) AS n FROM notification WHERE membre_id = :m', ['m' => $auteur['id']]);
        $nCite = $nombre("SELECT COUNT(*) AS n FROM notification WHERE membre_id = :m AND nature = 'mention'", ['m' => $cite['id']]);
        $nLecteur = $nombre('SELECT COUNT(*) AS n FROM notification WHERE membre_id = :m', ['m' => $lecteur['id']]);
        ($nAuteur === 1 && $nCite === 1 && $nLecteur === 0)
            ? ok('Abonné et cité prévenus une fois chacun, l\'auteur de la réponse jamais.')
            : ko("Notifications fausses : auteur $nAuteur, cité $nCite, lecteur $nLecteur.");

        $nonLues = \SBDRE\compterNonLues($auteur['id']);
        \SBDRE\marquerLues($auteur['id']);
        ($nonLues === 1 && \SBDRE\compterNonLues($auteur['id']) === 0 && count(\SBDRE\listerNotifications($auteur['id'])) === 1)
            ? ok('Les notifications se comptent, se listent et se marquent lues.')
            : ko('Le compte ou la lecture des notifications est faux.');

        $accueil = \SBDRE\forumsDeLAccueil();
        $liste = \SBDRE\sujetsDuForum((int) $forum['id'], 1);
        $messages = \SBDRE\messagesDuSujet($sujetId, 1);
        (count($accueil) >= 19 && in_array($sujetId, array_map('intval', array_column($liste, 'id')), true) && count($messages) === 2)
            ? ok('Les trois lectures du forum passent en mode strict.')
            : ko('Une lecture du forum rend un résultat faux.');

        (\SBDRE\forumTropRapide($lecteur['id']) && \SBDRE\sujetsDuJour($auteur['id']) === 1)
            ? ok('Le freinage du forum et le compte des sujets du jour mordent.')
            : ko('Le freinage du forum ne mord pas.');

        // Modifier sa réponse : le texte change, la date le dit, et l'horloge
        // de MySQL s'accorde avec celle de PHP pour le délai de 24 heures.
        $reponse = array_values(array_filter(\SBDRE\messagesDuSujet($sujetId, 1), fn (array $m): bool => (int) $m['membre_id'] === $lecteur['id']))[0];
        \SBDRE\modifierMessage((int) $reponse['id'], 'Texte corrigé', false);
        $corrige = array_values(array_filter(\SBDRE\messagesDuSujet($sujetId, 1), fn (array $m): bool => (int) $m['id'] === (int) $reponse['id']))[0];
        $actif = $lecteur + ['courriel_verifie' => 1, 'banni' => 0];
        ($corrige['texte'] === 'Texte corrigé' && $corrige['modif_ts'] !== null && \SBDRE\peutModifierMessage($actif, $reponse, time()))
            ? ok('Un message se modifie dans le délai, et la date de modification se pose.')
            : ko('La modification d\'un message a échoué : ' . json_encode($corrige));

        $siens = \SBDRE\sujetsDuMembre($auteur['id']);
        (count($siens) === 1 && $siens[0]['titre'] === 'Essai de sujet' && $siens[0]['reponses'] === 1)
            ? ok('« Mes sujets » rend les sujets ouverts par le membre.')
            : ko('« Mes sujets » est faux : ' . json_encode($siens));

        \SBDRE\renommerSujet($sujetId, 'Essai de sujet renommé', false);
        (\SBDRE\ligne('SELECT titre FROM sujet WHERE id = :s', ['s' => $sujetId])['titre'] ?? '') === 'Essai de sujet renommé'
            ? ok('Le titre d\'un sujet se modifie.')
            : ko('Le titre du sujet n\'a pas changé.');

        // Sans accents ni majuscules, et _ cherché comme une lettre.
        $trouves = array_map('intval', array_column(\SBDRE\rechercherSujets('ESSAI DE SUJET RENOMME'), 'id'));
        $joker = array_map('intval', array_column(\SBDRE\rechercherSujets('Essai_de'), 'id'));
        (in_array($sujetId, $trouves, true) && !in_array($sujetId, $joker, true))
            ? ok('La recherche trouve un titre sans accents ni majuscules, et _ reste une lettre.')
            : ko('La recherche des titres est fausse.');

        in_array($sujetId, array_column(\SBDRE\sujetsSuivis($lecteur['id']), 'id'), true)
            ? ok('« Sujets suivis » rend les sujets que le membre suit.')
            : ko('« Sujets suivis » oublie un sujet suivi.');

        $autre = \SBDRE\ligne("SELECT id FROM forum WHERE id <> :f AND etat <> 'masque' ORDER BY id LIMIT 1", ['f' => $forum['id']]);
        \SBDRE\deplacerSujet($sujetId, (int) $autre['id']);
        $ici = (int) (\SBDRE\ligne('SELECT forum_id FROM sujet WHERE id = :s', ['s' => $sujetId])['forum_id'] ?? 0);
        \SBDRE\deplacerSujet($sujetId, (int) $forum['id']);
        $ici === (int) $autre['id']
            ? ok('Un sujet se déplace vers un autre forum.')
            : ko('Le sujet n\'a pas changé de forum.');

        $stats = \SBDRE\statistiquesForum();
        ((int) ($stats['sujets'] ?? 0) >= 1 && (int) ($stats['membres'] ?? 0) >= 3 && ($stats['dernier'] ?? null) !== null)
            ? ok('Les statistiques du pied du forum passent en mode strict.')
            : ko('Les statistiques du forum sont fausses : ' . json_encode($stats));

        $nombre("SELECT COUNT(*) AS n FROM forum WHERE adresse IN ('fil', 'gabarit', 'recherche')") === 0
            ? ok('Aucun forum ne porte une adresse réservée, la recherche comprise.')
            : ko('Un forum porte l\'adresse « fil », « gabarit » ou « recherche » : sa page serait masquée.');

        // Sur la même connexion : l'autre ne voit pas les membres de cette
        // transaction, et attendrait leur verrou au lieu de refuser.
        $refuseIci = function (string $intitule, string $sql, array $valeurs) use ($base): void {
            try {
                $base->prepare($sql)->execute($valeurs);
                ko($intitule . ' : ACCEPTÉ alors qu\'il devait être refusé.');
            } catch (PDOException $e) {
                ok($intitule . ' : refusé. (' . $e->getCode() . ')');
            }
        };
        $refuseIci('Sujet de 4 caractères', 'INSERT INTO sujet (forum_id, pseudo, titre, dernier_pseudo) VALUES (?, ?, ?, ?)',
            [$forum['id'], 'x', 'Abcd', 'x']);
        $refuseIci('Notification vers un autre site', "INSERT INTO notification (membre_id, nature, texte, lien) VALUES (?, 'badge', 'x', ?)",
            [$cite['id'], '//exemple.re/']);

        $base->prepare('DELETE FROM membre WHERE id = ?')->execute([$auteur['id']]);
        $garde = \SBDRE\ligne('SELECT membre_id, pseudo FROM sujet WHERE id = :s', ['s' => $sujetId]);
        ($garde !== null && $garde['membre_id'] === null && $garde['pseudo'] === $auteur['pseudo']
            && $nombre('SELECT COUNT(*) AS n FROM notification WHERE membre_id = :m', ['m' => $auteur['id']]) === 0)
            ? ok('Un compte effacé laisse ses sujets signés de son pseudo, ses notifications partent.')
            : ko('L\'effacement d\'un compte a mal traité le forum.');

        info('Chat');

        $id = \SBDRE\ecrireChat($lecteur, 'general', 'Salut :)', false, $ip2);
        $premiere = \SBDRE\lireChat('general', 0, false);
        ($premiere['messages'] === [] && $premiere['dernier'] >= $id)
            ? ok('Un visiteur ne reçoit d\'abord que le numéro du dernier message.')
            : ko('La première lecture d\'un visiteur rend des messages.');

        $base->prepare("INSERT INTO message (canal, membre_id, pseudo, texte, ip, date_envoi)
                        VALUES ('general', ?, ?, 'Vieux message', ?, NOW() - INTERVAL 10 MINUTE)")
             ->execute([$lecteur['id'], $lecteur['pseudo'], $ip2]);
        $vieux = (int) $base->lastInsertId();
        $vuVisiteur = array_column(\SBDRE\lireChat('general', $id - 1, false)['messages'], 'id');
        $vuMembre = array_column(\SBDRE\lireChat('general', $id - 1, true)['messages'], 'id');
        (in_array($id, $vuVisiteur, true) && !in_array($vieux, $vuVisiteur, true) && in_array($vieux, $vuMembre, true))
            ? ok('Un visiteur ne remonte pas au-delà de cinq minutes, un membre lit sept jours.')
            : ko('La lecture du chat rend trop ou trop peu : visiteur ' . json_encode($vuVisiteur) . ', membre ' . json_encode($vuMembre));

        $base->prepare('UPDATE message SET masque_par = ? WHERE id = ?')->execute([$lecteur['id'], $id]);
        !in_array($id, array_column(\SBDRE\lireChat('general', $id - 1, true)['messages'], 'id'), true)
            ? ok('Un message masqué ne se lit plus.')
            : ko('Un message masqué se lit encore.');

        (\SBDRE\chatTropRapide($lecteur['id']) && in_array($lecteur['pseudo'], \SBDRE\enLigne(), true))
            ? ok('Le freinage du chat mord, et qui écrit paraît en ligne.')
            : ko('Freinage ou présence en ligne en défaut.');

        info('Fil, forums des membres, modération, avatars');

        $fil = \SBDRE\filActualite();
        $sujetsDuFil = array_map('intval', array_column(array_filter($fil, fn (array $e): bool => $e['nature'] === 'sujet'), 'sujet_id'));
        in_array($sujetId, $sujetsDuFil, true)
            ? ok('Le fil passe en mode strict et montre le nouveau sujet.')
            : ko('Le fil ne montre pas le sujet ouvert.');

        $adresse1 = \SBDRE\creerForumMembre($lecteur, 'Forum d\'essai', 'Une description');
        $adresse2 = \SBDRE\creerForumMembre($cite, 'Forum d\'essai', '');
        ($adresse2 === $adresse1 . '-2' && \SBDRE\forumsCreesCeMois($lecteur['id']) === 1)
            ? ok('Deux forums du même nom reçoivent deux adresses, comptés par créateur.')
            : ko("Adresses des forums de membres : $adresse1, $adresse2.");

        $idForum2 = (int) \SBDRE\ligne('SELECT id FROM forum WHERE adresse = :a', ['a' => $adresse2])['id'];
        \SBDRE\renommerForum($idForum2, 'Forum renommé', 'Nouvelle description');
        $renomme = \SBDRE\ligne('SELECT adresse, titre, description FROM forum WHERE id = :f', ['f' => $idForum2]);
        ($renomme['titre'] === 'Forum renommé' && $renomme['description'] === 'Nouvelle description' && $renomme['adresse'] === $adresse2)
            ? ok('Un forum se renomme et garde son adresse.')
            : ko('Le renommage du forum est faux : ' . json_encode($renomme));

        $base->prepare('UPDATE forum SET date_creation = NOW() - INTERVAL 7 MONTH WHERE adresse = ?')->execute([$adresse1]);
        \SBDRE\archiverForumsInactifs();
        (\SBDRE\ligne('SELECT etat FROM forum WHERE adresse = :a', ['a' => $adresse1])['etat'] ?? '') === 'archive'
            ? ok('Un forum de membre sans message depuis six mois passe en archive.')
            : ko('Le forum inactif n\'est pas archivé.');

        $aSignaler = \SBDRE\ecrireChat($lecteur, 'squat', 'À signaler', false, $ip2);
        $premier = \SBDRE\signaler($cite['id'], 'message', $aSignaler, 'spam');
        $second = \SBDRE\signaler($cite['id'], 'message', $aSignaler, 'encore');
        ($premier && !$second && \SBDRE\cibleExiste('message', $aSignaler) && \SBDRE\signalementsDuJour($cite['id']) === 1)
            ? ok('Un signalement par membre et par cible, compté pour la journée.')
            : ko('Le signalement se double ou ne se compte pas.');

        $enFile = array_values(array_filter(\SBDRE\fileModeration(), fn (array $s): bool => $s['type'] === 'message' && $s['cible_id'] === $aSignaler));
        ($enFile !== [] && $enFile[0]['auteur'] === $lecteur['pseudo'] && $enFile[0]['apercu'] === 'À signaler')
            ? ok('La file de modération montre le contenu signalé et son auteur.')
            : ko('La file de modération est fausse : ' . json_encode($enFile));

        $aPrevenir = \SBDRE\masquerMessage('message', $aSignaler, $cite['id']);
        \SBDRE\classer('message', $aSignaler);
        $resteEnFile = array_filter(\SBDRE\fileModeration(), fn (array $s): bool => $s['type'] === 'message' && $s['cible_id'] === $aSignaler);
        ($aPrevenir === $lecteur['id'] && !\SBDRE\cibleExiste('message', $aSignaler) && $resteEnFile === [])
            ? ok('Masquer rend l\'auteur à prévenir, classer vide la file.')
            : ko('Masquer ou classer ne fait pas ce qu\'il annonce.');

        count(\SBDRE\forumsDesMembres()) >= 2
            ? ok('Les forums des membres se listent pour l\'admin.')
            : ko('Les forums des membres manquent à la liste de l\'admin.');

        $base->prepare("INSERT INTO jeton (membre_id, type, jeton_hash, date_expiration) VALUES (?, 'session', ?, NOW() + INTERVAL 1 DAY)")
             ->execute([$cite['id'], hash('sha256', random_bytes(16))]);
        (\SBDRE\bannir($cite['id']) && $nombre('SELECT COUNT(*) AS n FROM jeton WHERE membre_id = :m', ['m' => $cite['id']]) === 0)
            ? ok('Bannir ferme aussitôt les sessions du membre.')
            : ko('Le membre banni garde une session.');

        $base->prepare("UPDATE membre SET role = 'admin' WHERE id = ?")->execute([$lecteur['id']]);
        !\SBDRE\bannir($lecteur['id'])
            ? ok('Un admin ne se bannit pas.')
            : ko('Un admin a été banni.');

        if (function_exists('imagewebp')) {
            $carre = imagecreatetruecolor(400, 400);
            ob_start();
            imagepng($carre);
            $webp = (string) \SBDRE\preparerAvatar((string) ob_get_clean());
            \SBDRE\enregistrerAvatar($lecteur['id'], $webp);
            \SBDRE\enregistrerAvatar($lecteur['id'], $webp);
            $relu = \SBDRE\lireAvatar($lecteur['pseudo']);
            ($webp !== '' && $relu !== null && $relu['image'] === $webp)
                ? ok('Un avatar s\'enregistre, se remplace et se relit à l\'octet près.')
                : ko('L\'avatar relu diffère de l\'avatar enregistré.');
        } else {
            ko('GD ou WebP manque sur ce serveur : les avatars ne fonctionneront pas.');
        }

        info('Récapitulatif par courriel');

        // Demandé il y a quinze jours, une notification depuis : il est dû.
        $base->prepare('UPDATE membre SET recapitulatif = 1, date_recapitulatif = NOW() - INTERVAL 15 DAY WHERE id = ?')
             ->execute([$lecteur['id']]);
        \SBDRE\notifier($lecteur['id'], 'reponse', 'Essai de récapitulatif', '/forum/');
        $du = array_values(array_filter(\SBDRE\recapitulatifsDus(1000), fn (array $m): bool => (int) $m['id'] === $lecteur['id']));
        $precedent = $du[0]['date_recapitulatif'] ?? null;
        ($du !== [] && \SBDRE\notificationsDuRecapitulatif($lecteur['id'], $precedent)['total'] === 1)
            ? ok('Un récapitulatif demandé, vieux de quinze jours, avec du nouveau, est dû.')
            : ko('Le récapitulatif dû ne se trouve pas.');

        $reserve = \SBDRE\reserverRecapitulatif($lecteur['id'], $precedent);
        $double = \SBDRE\reserverRecapitulatif($lecteur['id'], $precedent);
        $encoreDu = array_filter(\SBDRE\recapitulatifsDus(1000), fn (array $m): bool => (int) $m['id'] === $lecteur['id']);
        ($reserve && !$double && $encoreDu === [])
            ? ok('Un récapitulatif se réserve une seule fois, puis n\'est plus dû.')
            : ko('La réservation du récapitulatif laisserait partir deux courriels.');

        // Le type de jeton vient de migration-2026-11-08-recapitulatif.sql.
        $jeton = \SBDRE\creerJeton($lecteur['id'], 'recapitulatif', \SBDRE\JETON_RECAPITULATIF_MINUTES);
        (\SBDRE\consommerJeton($jeton, 'recapitulatif') === $lecteur['id'] && \SBDRE\consommerJeton($jeton, 'recapitulatif') === null)
            ? ok('Le lien « couper » sert une fois.')
            : ko('Le jeton du récapitulatif ne fonctionne pas.');

        // La promesse de /confidentialite/ : l'adresse IP s'efface au bout d'un an.
        $base->prepare('UPDATE message_forum SET date_creation = NOW() - INTERVAL 13 MONTH WHERE sujet_id = ?')->execute([$sujetId]);
        \SBDRE\oublierAdressesIp();
        $nombre("SELECT COUNT(*) AS n FROM message_forum WHERE sujet_id = :s AND ip <> ''", ['s' => $sujetId]) === 0
            ? ok('L\'adresse IP d\'un message de plus d\'un an s\'efface.')
            : ko('Une adresse IP de plus d\'un an reste en base.');
    } catch (Throwable $e) {
        ko('Essai interrompu par une erreur inattendue : ' . $e->getMessage());
    } finally {
        $base->rollBack();
        info('Transaction annulée : rien n\'a été conservé.');
    }
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

echo "  Fondation vérifiée. Phase suivante : le socle PHP (docs/comptes.md).\n\n";
exit(0);

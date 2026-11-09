<?php
/**
 * Vérification des règles d'autorisation.
 *
 * Ce script n'a besoin NI de base de données NI de session : il éprouve les
 * décisions pures de `public/api/lib/autorisation.php` et de `session.php` sur
 * une table de vérité. C'est possible parce que ces fonctions prennent des
 * tableaux en argument au lieu d'aller chercher l'état courant : du code de
 * sécurité qu'on ne peut pas éprouver est du code qu'on ne relit jamais.
 *
 * Il couvre, avant qu'un seul point d'entrée existe, les refus de la section 9
 * de docs/comptes.md qui ne demandent pas de base : voter sur sa barre, voter
 * trop tôt, poser le badge admin sans l'être, retirer la barre d'un autre, se
 * nommer admin, écrire depuis un autre site, publier sans adresse vérifiée,
 * le badge à 19 votes ou à 11 sur 20.
 *
 * -----------------------------------------------------------------------------
 * USAGE
 * -----------------------------------------------------------------------------
 *   php db/verifier-autorisation.php
 *
 * Sans argument, il suppose l'arborescence du dépôt. Sinon, lui passer le
 * chemin d'autorisation.php. Aucune connexion n'a lieu : les bibliothèques ne
 * font que DÉFINIR des fonctions, et seules les décisions pures sont appelées.
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
require_once dirname($chemin) . '/comptes.php';
require_once dirname($chemin) . '/performances.php';
require_once dirname($chemin) . '/forum.php';
require_once dirname($chemin) . '/chat.php';
require_once dirname($chemin) . '/recapitulatif.php';
require_once dirname($chemin) . '/revendications.php';
require_once dirname($chemin) . '/annuaire.php';

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
        $attendu ? 'oui' : 'NON',
        $obtenu ? 'oui' : 'NON',
    );
}

/* -------------------------------------------------------------------------
 * Personnages
 *
 * Les dates sont calculées depuis une horloge fixe : le résultat ne dépend pas
 * du jour où le script tourne.
 * ---------------------------------------------------------------------- */

$maintenant = strtotime('2026-11-06 12:00:00');
$ilYa = fn (int $jours, int $secondes = 0): string
    => date('Y-m-d H:i:s', $maintenant - $jours * 86400 - $secondes);

$personne = fn (int $id, array $champs = []): array => array_merge([
    'id'               => $id,
    'role'             => 'membre',
    'courriel_verifie' => 1,
    'banni'            => 0,
    'date_inscription' => $ilYa(60),
], $champs);

$anonyme     = null;
$alice       = $personne(1);
$bob         = $personne(2);
$nonVerifie  = $personne(3, ['courriel_verifie' => 0]);
$banni       = $personne(4, ['banni' => 1]);
$admin       = $personne(5, ['role' => 'admin']);
$adminBanni  = $personne(6, ['role' => 'admin', 'banni' => 1]);
$recent      = $personne(7, ['date_inscription' => $ilYa(29)]);
$pile30      = $personne(8, ['date_inscription' => $ilYa(30)]);
$presque30   = $personne(9, ['date_inscription' => $ilYa(30, -1)]);
$sansDate    = $personne(10, ['date_inscription' => '']);
$sansBanni   = ['id' => 11, 'role' => 'membre', 'courriel_verifie' => 1];

$barreAlice  = ['id' => 100, 'membre_id' => 1];
$barreBanni  = ['id' => 101, 'membre_id' => 4];

echo "\nVérification des règles d'autorisation\n";
echo "======================================\n";

/* -------------------------------------------------------------------------
 * Qui est qui
 * ---------------------------------------------------------------------- */

titre('Membre actif');

verifie('Un anonyme n\'est pas actif',                          \SBDRE\estActif($anonyme),    false);
verifie('Un membre vérifié est actif',                          \SBDRE\estActif($alice),      true);
verifie('Une adresse non vérifiée n\'est pas active',           \SBDRE\estActif($nonVerifie), false);
verifie('Un banni n\'est pas actif',                            \SBDRE\estActif($banni),      false);
verifie('Sans champ « banni », le refus l\'emporte',            \SBDRE\estActif($sansBanni),  false);
verifie('Un identifiant nul n\'ouvre aucun droit',
    \SBDRE\estActif(['id' => 0, 'courriel_verifie' => 1, 'banni' => 0]), false);

titre('Administrateur');

verifie('Un membre n\'est pas admin',                           \SBDRE\estAdmin($alice),      false);
verifie('Un admin est admin',                                   \SBDRE\estAdmin($admin),      true);
verifie('Un admin banni n\'est plus admin',                     \SBDRE\estAdmin($adminBanni), false);
verifie('L\'ancien rôle « moderateur » n\'ouvre rien',
    \SBDRE\estAdmin($personne(12, ['role' => 'moderateur'])), false);

titre('Propriété');

verifie('Alice est l\'auteur de sa barre',                      \SBDRE\estAuteur($barreAlice, $alice),   true);
verifie('Bob n\'est pas l\'auteur de celle d\'Alice',           \SBDRE\estAuteur($barreAlice, $bob),     false);
verifie('Un anonyme n\'est l\'auteur de rien',                  \SBDRE\estAuteur($barreAlice, $anonyme), false);
verifie('Un identifiant en chaîne vaut le même en entier',
    \SBDRE\estAuteur(['membre_id' => '1'], ['id' => 1]), true);
verifie('Deux identifiants nuls ne font pas un auteur',
    \SBDRE\estAuteur(['membre_id' => 0], ['id' => 0]), false);

/* -------------------------------------------------------------------------
 * Les gestes
 * ---------------------------------------------------------------------- */

titre('Publier : profil, performance, chat, signalement');

verifie('Un anonyme ne publie pas',                             \SBDRE\peutPublier($anonyme),    false);
verifie('Une adresse non vérifiée ne publie pas',               \SBDRE\peutPublier($nonVerifie), false);
verifie('Un banni ne publie pas',                               \SBDRE\peutPublier($banni),      false);
verifie('Un membre actif publie',                               \SBDRE\peutPublier($alice),      true);

titre('Retirer une performance');

verifie('Alice retire la sienne',                               \SBDRE\peutRetirerPerformance($barreAlice, $alice),   true);
verifie('Bob NE retire PAS celle d\'Alice',                     \SBDRE\peutRetirerPerformance($barreAlice, $bob),     false);
verifie('L\'admin retire celle d\'Alice',                       \SBDRE\peutRetirerPerformance($barreAlice, $admin),   true);
verifie('Un anonyme ne retire rien',                            \SBDRE\peutRetirerPerformance($barreAlice, $anonyme), false);
verifie('Un banni ne retire plus la sienne',                    \SBDRE\peutRetirerPerformance($barreBanni, $banni),   false);

titre('Voter');

verifie('Bob vote sur la barre d\'Alice',                       \SBDRE\peutVoter($barreAlice, $bob, $maintenant),        true);
verifie('Alice NE vote PAS sur la sienne',                      \SBDRE\peutVoter($barreAlice, $alice, $maintenant),      false);
verifie('Un compte de 29 jours NE vote PAS',                    \SBDRE\peutVoter($barreAlice, $recent, $maintenant),     false);
verifie('Un compte de 30 jours pile vote',                      \SBDRE\peutVoter($barreAlice, $pile30, $maintenant),     true);
verifie('Une seconde avant les 30 jours, NON',                  \SBDRE\peutVoter($barreAlice, $presque30, $maintenant),  false);
verifie('Une date d\'inscription illisible, NON',               \SBDRE\peutVoter($barreAlice, $sansDate, $maintenant),   false);
verifie('Une adresse non vérifiée NE vote PAS',                 \SBDRE\peutVoter($barreAlice, $nonVerifie, $maintenant), false);
verifie('Un banni NE vote PAS',                                 \SBDRE\peutVoter($barreAlice, $banni, $maintenant),      false);
verifie('Un anonyme NE vote PAS',                               \SBDRE\peutVoter($barreAlice, $anonyme, $maintenant),    false);

titre('Modérer : badge admin, masquer, bannir');

verifie('Un membre ne modère pas',                              \SBDRE\peutModerer($alice),      false);
verifie('Un admin modère',                                      \SBDRE\peutModerer($admin),      true);
verifie('Un admin banni ne modère plus',                        \SBDRE\peutModerer($adminBanni), false);
verifie('Un anonyme ne modère pas',                             \SBDRE\peutModerer($anonyme),    false);

titre('Badge communauté : 20 votes, 60 % favorables');

verifie('19 votes favorables sur 19 : pas de badge',            \SBDRE\badgeCommunaute(19, 19), false);
verifie('11 favorables sur 20 : pas de badge',                  \SBDRE\badgeCommunaute(20, 11), false);
verifie('12 favorables sur 20 : badge',                         \SBDRE\badgeCommunaute(20, 12), true);
verifie('15 sur 25, 60 % tout juste : badge',                   \SBDRE\badgeCommunaute(25, 15), true);
verifie('14 sur 25 : pas de badge',                             \SBDRE\badgeCommunaute(25, 14), false);
verifie('Aucun vote : pas de badge',                            \SBDRE\badgeCommunaute(0, 0),   false);
verifie('Plus de favorables que de votes : refusé',             \SBDRE\badgeCommunaute(20, 40), false);

/* -------------------------------------------------------------------------
 * Session
 * ---------------------------------------------------------------------- */

titre('Origine des requêtes');

verifie('Une lecture passe sans origine',                       \SBDRE\origineAutorisee('GET', ''),                       true);
verifie('Une écriture de sbd.re passe',                         \SBDRE\origineAutorisee('POST', 'https://sbd.re'),        true);
verifie('Une écriture de www.sbd.re passe',                     \SBDRE\origineAutorisee('DELETE', 'https://www.sbd.re'),  true);
verifie('Une écriture SANS origine est refusée',                \SBDRE\origineAutorisee('POST', ''),                      false);
verifie('Une écriture d\'un autre site est refusée',            \SBDRE\origineAutorisee('POST', 'https://exemple.re'),    false);
verifie('« sbd.re.exemple.com » n\'est pas sbd.re',             \SBDRE\origineAutorisee('PATCH', 'https://sbd.re.exemple.com'), false);
verifie('sbd.re sans https est refusé',                         \SBDRE\origineAutorisee('POST', 'http://sbd.re'),         false);
verifie('La méthode en minuscules ne contourne rien',           \SBDRE\origineAutorisee('post', 'https://exemple.re'),    false);

titre('Forme du cookie de session');

verifie('Un jeton émis ici est bien formé',                     \SBDRE\jetonBienForme(bin2hex(random_bytes(32))), true);
verifie('Un jeton trop court est refusé',                       \SBDRE\jetonBienForme(str_repeat('a', 63)),       false);
verifie('Un jeton à caractère non hexadécimal est refusé',      \SBDRE\jetonBienForme(str_repeat('a', 63) . 'z'), false);
verifie('Un jeton suivi d\'un retour à la ligne est refusé',    \SBDRE\jetonBienForme(str_repeat('a', 64) . "\n"), false);
verifie('Un cookie vide est refusé',                            \SBDRE\jetonBienForme(''),                        false);

/* -------------------------------------------------------------------------
 * Champs acceptés depuis une requête
 * ---------------------------------------------------------------------- */

titre('Champs acceptés depuis une requête');

$profilEnvoye = \SBDRE\filtrerChamps([
    'bio'              => 'Squat le lundi',
    'role'             => 'admin',      // se nommer admin
    'courriel_verifie' => 1,            // se valider seul
    'banni'            => 0,            // se débannir
    'pseudo'           => 'autre',      // passe, puis la règle d'un an
    'pseudo_precedent' => 'vole',       // garder l'ancien pseudo d'un autre
    'date_pseudo'      => null,         // effacer son délai
    'recapitulatif'    => true,         // demander le récapitulatif
    'date_recapitulatif' => null,       // en avancer l'envoi
    'id'               => 999,
], \SBDRE\CHAMPS_PROFIL);

verifie('La présentation est acceptée',             array_key_exists('bio', $profilEnvoye),              true);
verifie('Se nommer admin est ignoré',               array_key_exists('role', $profilEnvoye),             false);
verifie('Valider soi-même son adresse est ignoré',  array_key_exists('courriel_verifie', $profilEnvoye), false);
verifie('Se débannir est ignoré',                   array_key_exists('banni', $profilEnvoye),            false);
verifie('Le pseudo passe, pour la règle d\'un an',  array_key_exists('pseudo', $profilEnvoye),           true);
verifie('Le récapitulatif se demande',              array_key_exists('recapitulatif', $profilEnvoye),    true);
verifie('Sa date d\'envoi est ignorée',             array_key_exists('date_recapitulatif', $profilEnvoye), false);
verifie('Écrire son ancien pseudo est ignoré',      array_key_exists('pseudo_precedent', $profilEnvoye), false);
verifie('Effacer son délai de pseudo est ignoré',   array_key_exists('date_pseudo', $profilEnvoye),      false);
verifie('Changer d\'identifiant est ignoré',        array_key_exists('id', $profilEnvoye),               false);

$perfEnvoyee = \SBDRE\filtrerChamps([
    'charge_kg'        => 200,
    'membre_id'        => 2,           // publier au nom d'un autre
    'admin_id'         => 5,           // se poser le badge admin
    'date_verif_admin' => '2026-11-06',
], \SBDRE\CHAMPS_PERFORMANCE);

verifie('La charge est acceptée',                   array_key_exists('charge_kg', $perfEnvoyee),        true);
verifie('Publier au nom d\'un autre est ignoré',    array_key_exists('membre_id', $perfEnvoyee),        false);
verifie('Se poser le badge admin est ignoré',       array_key_exists('admin_id', $perfEnvoyee),         false);
verifie('Dater soi-même le badge est ignoré',       array_key_exists('date_verif_admin', $perfEnvoyee), false);

/* -------------------------------------------------------------------------
 * Comptes : formes acceptées
 * ---------------------------------------------------------------------- */

titre('Pseudo');

verifie('« jean_93 » est accepté',                       \SBDRE\pseudoValide('jean_93'),            true);
verifie('« la.force.974 » est accepté',                  \SBDRE\pseudoValide('la.force.974'),       true);
verifie('« _squat » est accepté, comme sur Instagram',   \SBDRE\pseudoValide('_squat'),             true);
verifie('Majuscules refusées',                           \SBDRE\pseudoValide('Jean'),               false);
verifie('Accent refusé',                                 \SBDRE\pseudoValide('rené'),               false);
verifie('Tiret refusé, réservé au gabarit',              \SBDRE\pseudoValide('-gabarit'),           false);
verifie('Points seuls refusés',                          \SBDRE\pseudoValide('...'),                false);
verifie('Deux caractères refusés',                       \SBDRE\pseudoValide('ab'),                 false);
verifie('31 caractères refusés',                         \SBDRE\pseudoValide(str_repeat('a', 31)),  false);
verifie('Retour à la ligne final refusé',                \SBDRE\pseudoValide("jean\n"),             false);
verifie('« admin » est réservé',                         \SBDRE\pseudoValide('admin'),              false);
verifie('« sbd.re » est réservé',                        \SBDRE\pseudoValide('sbd.re'),             false);

titre('Mot de passe');

verifie('9 caractères refusés',            \SBDRE\erreurMotDePasse('123456789') !== '',             true);
verifie('10 caractères acceptés',          \SBDRE\erreurMotDePasse('1234567890') === '',            true);
verifie('Espaces comptés, pas retirés',    \SBDRE\erreurMotDePasse('  deux mots  ') === '',         true);
verifie('Dix lettres accentuées comptent dix', \SBDRE\erreurMotDePasse('éééééééééé') === '',        true);
verifie('72 octets acceptés',              \SBDRE\erreurMotDePasse(str_repeat('a', 72)) === '',     true);
verifie('73 octets refusés, bcrypt ignorerait la suite', \SBDRE\erreurMotDePasse(str_repeat('a', 73)) !== '', true);

titre('Délai de remplissage, contre les robots');

verifie('Envoyé en 2 secondes : robot',             \SBDRE\delaiHumain($maintenant - 2, $maintenant),    false);
verifie('Envoyé en 3 secondes : accepté',           \SBDRE\delaiHumain($maintenant - 3, $maintenant),    true);
verifie('Envoyé en 2 heures pile : accepté',        \SBDRE\delaiHumain($maintenant - 7200, $maintenant), true);
verifie('Page restée ouverte plus de 2 heures',     \SBDRE\delaiHumain($maintenant - 7201, $maintenant), false);
verifie('Sans horodatage : refusé',                 \SBDRE\delaiHumain(0, $maintenant),                  false);
verifie('Horodatage dans le futur : refusé',        \SBDRE\delaiHumain($maintenant + 60, $maintenant),   false);

titre('Adresse électronique');

verifie('Mise en minuscules',              \SBDRE\normaliserCourriel(' Jean@Exemple.RE ') === 'jean@exemple.re', true);
verifie('Adresse sans arobase refusée',    \SBDRE\normaliserCourriel('jean.exemple.re') === '',      true);
verifie('Retour à la ligne refusé',        \SBDRE\normaliserCourriel("jean@exemple.re\nBcc: x@y.z") === '', true);

titre('Nom Instagram, distinct du pseudo');

verifie('« @Jean.Force » devient « jean.force »',      \SBDRE\normaliserInstagram(' @Jean.Force ') === 'jean.force', true);
verifie('Une adresse instagram.com donne le nom',     \SBDRE\normaliserInstagram('https://www.instagram.com/jean_93/?hl=fr') === 'jean_93', true);
verifie('« j » seul est un nom valide',               \SBDRE\instagramValide('j'),                  true);
verifie('Tiret refusé',                               \SBDRE\instagramValide('jean-force'),         false);
verifie('Points seuls refusés',                       \SBDRE\instagramValide('..'),                 false);
verifie('31 caractères refusés',                      \SBDRE\instagramValide(str_repeat('a', 31)),  false);
verifie('Retour à la ligne final refusé',             \SBDRE\instagramValide("jean\n"),             false);
$insta = \SBDRE\DELAI_INSTAGRAM_MOIS;
verifie('Premier nom : accepté tout de suite',        \SBDRE\prochainChangement(null, $insta, $maintenant) === 0, true);
verifie('Changé il y a 5 mois : refusé',
    \SBDRE\prochainChangement(date('Y-m-d H:i:s', strtotime('-5 months', $maintenant)), $insta, $maintenant) > 0, true);
verifie('Changé il y a 6 mois pile : accepté',
    \SBDRE\prochainChangement(date('Y-m-d H:i:s', strtotime('-6 months', $maintenant)), $insta, $maintenant) === 0, true);
verifie('Changé le 6 novembre : prochain le 6 mai',
    \SBDRE\dateFr(\SBDRE\prochainChangement('2026-11-06 12:00:00', $insta, $maintenant)) === '6 mai 2027', true);

titre('Pseudo, une fois par an');

$an = \SBDRE\DELAI_PSEUDO_MOIS;
verifie('Premier changement : libre, pour une faute de frappe', \SBDRE\prochainChangement(null, $an, $maintenant) === 0, true);
verifie('Changé il y a 11 mois : refusé',
    \SBDRE\prochainChangement(date('Y-m-d H:i:s', strtotime('-11 months', $maintenant)), $an, $maintenant) > 0, true);
verifie('Changé il y a un an pile : accepté',
    \SBDRE\prochainChangement(date('Y-m-d H:i:s', strtotime('-12 months', $maintenant)), $an, $maintenant) === 0, true);
verifie('Changé le 6 novembre 2026 : prochain le 6 novembre 2027',
    \SBDRE\dateFr(\SBDRE\prochainChangement('2026-11-06 12:00:00', $an, $maintenant)) === '6 novembre 2027', true);
verifie('Le premier du mois s\'écrit « 1er »',        \SBDRE\dateFr(strtotime('2027-05-01 10:00:00')) === '1er mai 2027', true);
verifie('Sans le jour : « novembre 2026 »',           \SBDRE\dateFr($maintenant, false) === 'novembre 2026', true);

titre('Performance : lien de la vidéo');

foreach ([
    ['https://www.instagram.com/p/abc/', true, 'Instagram'],
    ['https://youtu.be/abc', true, 'YouTube court'],
    ['https://www.youtube.com/watch?v=abc', true, 'YouTube'],
    ['https://www.tiktok.com/@jean/video/1', true, 'TikTok'],
    ['http://www.instagram.com/p/abc/', false, 'http sans s'],
    ['https://vimeo.com/123', false, 'un autre hébergeur'],
    ['https://www.instagram.com/', false, 'la page d\'accueil seule'],
    ['https://www.instagram.com@exemple.re/p/abc/', false, 'un faux hôte après un @'],
    ['https://exemple.re/www.instagram.com/p/abc/', false, 'l\'hébergeur dans le chemin'],
    ['https://www.instagram.com/p/abc/"><script>', false, 'un guillemet et une balise'],
    ['javascript:alert(1)', false, 'javascript:'],
] as [$lien, $attendu, $quoi]) {
    verifie(ucfirst($quoi) . ($attendu ? ' : accepté' : ' : refusé'), \SBDRE\lienVideoValide($lien), $attendu);
}

titre('Performance : charge, date, catégorie');

verifie('« 182,5 » vaut 182,5 kg',             \SBDRE\chargeValide('182,5') === 182.5, true);
verifie('600 kg acceptés',                     \SBDRE\chargeValide(600) === 600.0, true);
verifie('600,01 kg refusés',                   \SBDRE\chargeValide('600.01') === null, true);
verifie('0 kg refusé',                         \SBDRE\chargeValide('0') === null, true);
verifie('Charge négative refusée',             \SBDRE\chargeValide('-5') === null, true);
verifie('Trois décimales refusées',            \SBDRE\chargeValide('182.555') === null, true);
verifie('« 1e3 » refusé',                      \SBDRE\chargeValide('1e3') === null, true);
verifie('Aujourd\'hui accepté',                \SBDRE\datePerformanceValide(date('Y-m-d', $maintenant), $maintenant), true);
verifie('Demain refusé',                       \SBDRE\datePerformanceValide(date('Y-m-d', $maintenant + 86400), $maintenant), false);
verifie('Un 30 février refusé',                \SBDRE\datePerformanceValide('2026-02-30', $maintenant), false);
verifie('Avant 2000 refusé',                   \SBDRE\datePerformanceValide('1999-12-31', $maintenant), false);
verifie('Catégorie vide acceptée',             \SBDRE\categorieValide(''), true);
verifie('« 120+ » accepté',                    \SBDRE\categorieValide('120+'), true);
verifie('« 93kg » refusé',                     \SBDRE\categorieValide('93kg'), false);
verifie('« 250 » refusé',                      \SBDRE\categorieValide('250'), false);
verifie('« 182,5 kg » s\'écrit à la française', \SBDRE\kgFr(182.5) === "182,5\u{00A0}kg", true);

titre('Records du profil');

$barre = fn (string $mv, string $eq, string $kg, string $date): array =>
    ['mouvement' => $mv, 'equipement' => $eq, 'charge_kg' => $kg, 'date_performance' => $date];
$records = \SBDRE\recordsDe([
    $barre('squat', 'raw', '200.00', '2026-11-01'),
    $barre('squat', 'raw', '200.00', '2026-10-01'),
    $barre('squat', 'raw', '190.00', '2026-12-01'),
    $barre('bench', 'raw', '142.50', '2026-10-15'),
    $barre('deadlift', 'raw', '250.00', '2026-09-20'),
    $barre('squat', 'wraps', '215.00', '2026-08-01'),
]);
verifie('Total raw de 592,5 kg',                 $records['raw']['total'] === 592.5, true);
verifie('À charge égale, la plus ancienne garde le record',
    $records['raw']['barres']['squat']['date_performance'] === '2026-10-01', true);
verifie('Une barre plus légère ne bat pas le record', $records['raw']['barres']['squat']['charge_kg'] === '200.00', true);
verifie('Pas de total avec une seule barre',     $records['wraps']['total'] === null, true);
verifie('Raw avant bandes, dans l\'ordre du site', array_keys($records) === ['raw', 'wraps'], true);
$verifiee = \SBDRE\recordsDe([
    $barre('squat', 'raw', '200.00', '2026-10-01'),
    $barre('squat', 'raw', '200.00', '2026-11-01') + ['date_verif_admin' => '2026-11-02 10:00:00'],
]);
verifie('À charge égale, la barre vérifiée l\'emporte',
    $verifiee['raw']['barres']['squat']['date_performance'] === '2026-11-01', true);
$communaute = \SBDRE\recordsDe([
    $barre('squat', 'raw', '200.00', '2026-10-01') + ['votes' => '20', 'favorables' => '11'],
    $barre('squat', 'raw', '200.00', '2026-11-01') + ['votes' => '20', 'favorables' => '12'],
]);
verifie('À charge égale, le badge communauté l\'emporte aussi',
    $communaute['raw']['barres']['squat']['date_performance'] === '2026-11-01', true);

titre('Badge d\'une performance');

// Les nombres arrivent de MySQL en texte : badgeDe() les lit comme tels.
verifie('Vérifiée par l\'admin, sans aucun vote',  \SBDRE\badgeDe(['date_verif_admin' => '2026-11-07 10:00:00', 'votes' => '0', 'favorables' => '0']) === 'admin', true);
verifie('L\'admin l\'emporte sur un vote contraire', \SBDRE\badgeDe(['date_verif_admin' => '2026-11-07 10:00:00', 'votes' => '20', 'favorables' => '0']) === 'admin', true);
verifie('12 favorables sur 20 : communauté',        \SBDRE\badgeDe(['date_verif_admin' => null, 'votes' => '20', 'favorables' => '12']) === 'communaute', true);
verifie('11 favorables sur 20 : non vérifiée',      \SBDRE\badgeDe(['date_verif_admin' => null, 'votes' => '20', 'favorables' => '11']) === '', true);
verifie('Sans colonne de votes : non vérifiée',     \SBDRE\badgeDe(['date_verif_admin' => null]) === '', true);

titre('Forum : le texte d\'un message');

$smileys = [['code' => ':)', 'fichier' => 'icon_e_smile', 'alt' => 'sourire'], ['code' => '8)', 'fichier' => 'icon_cool', 'alt' => 'cool']];
$rendu = fn (string $t): string => \SBDRE\rendreTexte($t, $smileys);

verifie('Un <script> reste du texte',            !str_contains($rendu('<script>alert(1)</script>'), '<script'), true);
verifie('[url]javascript: ne fait pas de lien',   str_contains($rendu('[url]javascript:alert(1)[/url]'), '<a '), false);
verifie('[url]https fait un lien ugc nofollow',   str_contains($rendu('[url]https://sbd.re/[/url]'), 'rel="ugc nofollow noopener"'), true);
verifie('Un guillemet ne sort pas de l\'adresse', str_contains($rendu('[url]https://x.re" onmouseover="a[/url]'), 'onmouseover="'), false);
verifie(':) seul devient une image',              str_contains($rendu('Salut :)'), 'icon_e_smile.svg'), true);
verifie('« 5x8) » reste du texte',                str_contains($rendu('5x8)'), '<img'), false);
verifie('[quote=pseudo] fait une citation',       str_contains($rendu('[quote=jean_93]oui[/quote]'), '<blockquote'), true);
verifie('[quote=<b>] reste du texte',             str_contains($rendu('[quote=<b>]x[/quote]'), '<blockquote'), false);
verifie('[b] non fermé reste du texte',           str_contains($rendu('[b]gras'), '<strong>'), false);
verifie('Retour à la ligne en <br>',              str_contains($rendu("a\nb"), '<br>'), true);

titre('Forum : formes');

verifie('Titre de 4 caractères refusé',           \SBDRE\titreSujetValide('Abcd'), false);
verifie('Titre de 5 caractères accepté',          \SBDRE\titreSujetValide('Abcde'), true);
verifie('Titre de 121 caractères refusé',         \SBDRE\titreSujetValide(str_repeat('a', 121)), false);
verifie('Titre à retour à la ligne refusé',       \SBDRE\titreSujetValide("Mon\ntitre"), false);
verifie('Message vide refusé',                    \SBDRE\texteForumValide(''), false);
verifie('Message de 5 001 caractères refusé',     \SBDRE\texteForumValide(str_repeat('é', 5001)), false);
verifie('Message sur deux lignes accepté',        \SBDRE\texteForumValide("ligne\nligne"), true);
verifie('Caractère de contrôle refusé',           \SBDRE\texteForumValide("bip\x07"), false);
verifie('Adresse « squat-technique » valide',     \SBDRE\adresseForumValide('squat-technique'), true);
verifie('Adresse « -gabarit » refusée',           \SBDRE\adresseForumValide('-gabarit'), false);
verifie('Adresse en majuscules refusée',          \SBDRE\adresseForumValide('Squat'), false);
verifie('@pseudo cité, courriel ignoré',          \SBDRE\mentionsDe('Salut @jean_93, écris à a@b.re') === ['jean_93'], true);
verifie('Cinq mentions au plus',                  count(\SBDRE\mentionsDe('@aaa @bbb @ccc @ddd @eee @fff')) === 5, true);
verifie('Message 20 en page 1, 21 en page 2',     \SBDRE\pageDuMessage(19) === 1 && \SBDRE\pageDuMessage(20) === 2, true);
verifie('Lien de la page 2 d\'un sujet',          \SBDRE\lienSujet('squat-technique', 14, 2) === '/forum/squat-technique/14/?page=2', true);

titre('Modération : les deux listes');

verifie('« cycle » passe, mot du métier',         \SBDRE\analyserTexte('Je cherche un cycle de force') === 'ok', true);
verifie('« stéroïdes » sous avertissement',       \SBDRE\analyserTexte('Il prend des stéroïdes') === 'sensible', true);
verifie('« EPO » sous avertissement',             \SBDRE\analyserTexte('Contrôle EPO ?') === 'sensible', true);
verifie('« hormone de croissance » aussi',        \SBDRE\analyserTexte('Hormone de croissance') === 'sensible', true);
verifie('« épaule » passe',                       \SBDRE\analyserTexte('Mal à l\'épaule') === 'ok', true);
verifie('Une insulte visant quelqu\'un bloque',   \SBDRE\analyserTexte('T\'es un enculé') === 'bloque', true);
verifie('Une grossièreté seule passe',            \SBDRE\analyserTexte('Putain la barre') === 'ok', true);
verifie('« dépôt » passe, « dope » non visé',     \SBDRE\analyserTexte('Un dépôt de plaques') === 'ok', true);

titre('Chat : formes');

verifie('Message court accepté',                  \SBDRE\texteChatValide('Salut :)'), true);
verifie('501 caractères refusés',                 \SBDRE\texteChatValide(str_repeat('a', 501)), false);
verifie('Retour à la ligne refusé',               \SBDRE\texteChatValide("a\nb"), false);
verifie('Deux salons, Général et Général 2',      array_keys(\SBDRE\SALONS) === ['general', 'general-2'], true);

titre('Forums des membres');

$ancien = ['id' => 5, 'role' => 'membre', 'courriel_verifie' => 1, 'banni' => 0, 'date_inscription' => '2026-09-01 10:00:00'];
$recent = ['id' => 6, 'role' => 'membre', 'courriel_verifie' => 1, 'banni' => 0, 'date_inscription' => '2026-11-01 10:00:00'];
$adminF = ['id' => 1, 'role' => 'admin', 'courriel_verifie' => 1, 'banni' => 0, 'date_inscription' => '2026-11-06 10:00:00'];
$le7 = strtotime('2026-11-07 12:00:00');
verifie('Membre de plus de 30 jours : crée un forum',  \SBDRE\peutCreerForum($ancien, $le7), true);
verifie('Membre de 6 jours : pas encore',             \SBDRE\peutCreerForum($recent, $le7), false);
verifie('Visiteur : non',                             \SBDRE\peutCreerForum(null, $le7), false);
verifie('Le créateur gère son forum',                 \SBDRE\peutGererForum($ancien, ['createur_id' => 5]), true);
verifie('Un autre membre non',                        \SBDRE\peutGererForum($recent, ['createur_id' => 5]), false);
verifie('L\'admin gère tous les forums',              \SBDRE\peutGererForum($adminF, ['createur_id' => 5]), true);
verifie('Forum de l\'équipe : l\'admin seul',          \SBDRE\peutGererForum($ancien, ['createur_id' => null]), false);
verifie('Adresse tirée du nom, sans accent',          \SBDRE\adresseDepuisTitre('Préparation au Championnat !') === 'preparation-au-championnat', true);
verifie('« Fil » ne prend pas l\'adresse du fil',     \SBDRE\adresseDepuisTitre('Fil') === 'forum-fil', true);
verifie('Un nom sans lettre donne « forum »',         \SBDRE\adresseDepuisTitre('!!!') === 'forum', true);
verifie('Toute adresse tirée est valide',             \SBDRE\adresseForumValide(\SBDRE\adresseDepuisTitre('Salle Saint-Pierre — Le Tampon')), true);

titre('Modifier un message, renommer un forum');

$sien = ['membre_id' => 5, 'masque_par' => null, 'date_ts' => $le7 - 3600];
verifie('Son message, une heure après : se modifie',  \SBDRE\peutModifierMessage($ancien, $sien, $le7), true);
verifie('25 heures après : non',                      \SBDRE\peutModifierMessage($ancien, ['date_ts' => $le7 - 25 * 3600] + $sien, $le7), false);
verifie('Le message d\'un autre : non',               \SBDRE\peutModifierMessage($recent, $sien, $le7), false);
verifie('Masqué par la modération : non',             \SBDRE\peutModifierMessage($ancien, ['masque_par' => 1] + $sien, $le7), false);
verifie('D\'un compte effacé : personne',              \SBDRE\peutModifierMessage($ancien, ['membre_id' => null] + $sien, $le7), false);
verifie('Banni : non',                                \SBDRE\peutModifierMessage(['banni' => 1] + $ancien, $sien, $le7), false);
verifie('Nom de forum correct accepté',               \SBDRE\erreurForum('Salle du Tampon', 'Les lundis') === '', true);
verifie('Nom de deux lettres refusé',                 \SBDRE\erreurForum('Ab', '') !== '', true);
verifie('Description de 201 caractères refusée',      \SBDRE\erreurForum('Salle du Tampon', str_repeat('a', 201)) !== '', true);
verifie('Le dopage refusé dans un nom de forum',      \SBDRE\erreurForum('Cures de stéroïdes', '') !== '', true);

$message = ['id' => 44, 'membre_id' => 5, 'pseudo' => 'jean_93', 'pseudo_actuel' => 'jean_93', 'banni' => 0,
    'inscription_ts' => $le7, 'texte' => 'Mon "texte"', 'sensible' => 0, 'masque_par' => null, 'date_ts' => time() - 60,
    'modif_ts' => null, 'avatar_v' => null];
$sujetOuvert = ['id' => 3, 'membre_id' => 5, 'titre' => 'Ma prépa', 'verrouille' => 0, 'epingle' => 0, 'reponses' => 0];
$pageSujet = fn (array $m, array $s): string => \SBDRE\rendreSujet(['adresse' => 'squat-technique', 'etat' => 'ouvert', 'createur_id' => null], $s, [$m], 1, 1, $ancien, false, []);
verifie('Son message récent porte « Modifier »',      str_contains($pageSujet($message, $sujetOuvert), 'data-modifier'), true);
verifie('Sujet fermé : plus de « Modifier »',         str_contains($pageSujet($message, ['verrouille' => 1] + $sujetOuvert), 'data-modifier'), false);
verifie('Un message modifié le dit',                  str_contains($pageSujet(['modif_ts' => time()] + $message, $sujetOuvert), 'modifié le'), true);

$forumMembre = ['adresse' => 'salle-du-tampon', 'etat' => 'ouvert', 'createur_id' => 5, 'titre' => 'Salle "du" Tampon', 'description' => ''];
verifie('Le créateur voit « Renommer le forum »',     str_contains(\SBDRE\rendreForum($forumMembre, [], 1, 1, $ancien), 'data-forum-renommer'), true);
verifie('Un autre membre ne le voit pas',             str_contains(\SBDRE\rendreForum($forumMembre, [], 1, 1, $recent), 'data-forum-renommer'), false);
verifie('Le nom prérempli reste échappé',             str_contains(\SBDRE\rendreForum($forumMembre, [], 1, 1, $ancien), 'value="Salle &quot;du&quot; Tampon"'), true);

titre('Le confort du forum');

$sujetRecent = ['membre_id' => 5, 'creation_ts' => time() - 3600] + $sujetOuvert;
verifie('Son sujet, une heure après : le titre se modifie', \SBDRE\peutModifierTitre($ancien, $sujetRecent, time()), true);
verifie('Le sujet d\'un autre : non',                      \SBDRE\peutModifierTitre($recent, $sujetRecent, time()), false);
verifie('Sans date d\'ouverture : non',                     \SBDRE\peutModifierTitre($ancien, $sujetOuvert, time()), false);
verifie('« Modifier le titre » paraît pour son auteur',     str_contains($pageSujet($message, $sujetRecent), 'data-modifier-titre'), true);
verifie('Le dernier message porte l\'ancre #dernier',        str_contains($pageSujet($message, $sujetOuvert), '<li id="dernier">'), true);
verifie('« Dernier message » mène à l\'ancre',              \SBDRE\lienDernier('squat-technique', 3, 25) === '/forum/squat-technique/3/?page=2#dernier', true);
verifie('Le formulaire de réponse a son aperçu',            str_contains($pageSujet($message, $sujetOuvert), 'data-apercu'), true);
verifie('Et ses boutons de mise en forme',                  str_contains($pageSujet($message, $sujetOuvert), 'data-balises'), true);
verifie('« Recherche » ne prend pas l\'adresse réservée',   \SBDRE\adresseDepuisTitre('Recherche') === 'forum-recherche', true);
verifie('Recherche de 2 caractères refusée',                \SBDRE\rechercheValide('ab'), false);
verifie('Recherche de 81 caractères refusée',               \SBDRE\rechercheValide(str_repeat('a', 81)), false);
verifie('Recherche à retour à la ligne refusée',            \SBDRE\rechercheValide("squat\nprofond"), false);
$rechercheXss = \SBDRE\rendreRecherche('"><script>', []);
verifie('La recherche saisie reste échappée',              !str_contains($rechercheXss, '<script>') && str_contains($rechercheXss, 'value="&quot;&gt;&lt;script&gt;"'), true);
verifie('Rien trouvé : une phrase et la sortie',           str_contains($rechercheXss, 'Aucun sujet trouvé') && str_contains($rechercheXss, 'href="/forum/"'), true);
$trouve = \SBDRE\rendreRecherche('prépa', [['id' => 3, 'titre' => 'Ma prépa', 'pseudo' => 'jean_93', 'reponses' => 2, 'dernier_pseudo' => 'marie', 'dernier_ts' => $le7, 'creation_ts' => $le7, 'adresse' => 'squat-technique', 'forum' => 'Technique']]);
verifie('Un résultat nomme son forum',                     str_contains($trouve, 'dans Technique') && str_contains($trouve, '/forum/squat-technique/3/'), true);
$deplacer = \SBDRE\formulaireDeplacer([
    ['adresse' => 'squat-technique', 'titre' => 'Technique', 'categorie' => 'Squat'],
    ['adresse' => 'hors-sujet', 'titre' => '<b>Hors</b>', 'categorie' => 'Divers'],
], 3, 'squat-technique');
verifie('Déplacer : le forum actuel est choisi',           str_contains($deplacer, '<option value="squat-technique" selected>'), true);
verifie('Déplacer : les noms restent échappés',            str_contains($deplacer, '&lt;b&gt;Hors'), true);
$pied = \SBDRE\rendrePiedForum(['jean_93', '<img>'], ['sujets' => 3, 'messages' => 12, 'membres' => 40, 'dernier' => 'paul_120']);
verifie('Qui est en ligne : deux membres, liés au profil', str_contains($pied, "2\u{00A0}membres en ligne") && str_contains($pied, '/membres/jean_93/'), true);
verifie('Un pseudo en ligne reste échappé',                !str_contains($pied, '<img>'), true);
verifie('Les statistiques et le dernier membre',           str_contains($pied, "12\u{00A0}messages dans 3\u{00A0}sujets") && str_contains($pied, '/membres/paul_120/'), true);
verifie('Personne en ligne : une phrase',                  str_contains(\SBDRE\rendrePiedForum([], []), 'paraissent ici'), true);
$ariane = \SBDRE\donneesAriane([['Forum', '/forum/'], ['Squat, Technique', '/forum/squat-technique/'], ['</script><b>', null]], '/forum/squat-technique/3/');
$lu = json_decode((string) preg_replace('~^<script type="application/ld\+json">|</script>$~', '', $ariane), true);
verifie('Le fil d\'Ariane en BreadcrumbList',               ($lu['@type'] ?? '') === 'BreadcrumbList' && count($lu['itemListElement']) === 3, true);
verifie('La dernière étape est la page elle-même',         ($lu['itemListElement'][2]['item'] ?? '') === 'https://sbd.re/forum/squat-technique/3/', true);
verifie('Un titre ne referme pas le bloc de données',      substr_count($ariane, '</script>') === 1, true);
verifie('Forum vide : pas de sujets récents',             \SBDRE\rendreSujetsRecents([]) === '', true);
verifie('Un sujet récent nomme son forum',                 str_contains(\SBDRE\rendreSujetsRecents([['id' => 3, 'titre' => 'Ma prépa', 'pseudo' => 'jean_93', 'verrouille' => 0, 'reponses' => 0, 'dernier_pseudo' => 'jean_93', 'dernier_ts' => $le7, 'creation_ts' => $le7, 'adresse' => 'squat-technique', 'forum' => 'Technique']]), 'dans Technique'), true);
verifie('Profil sans sujet : rien',                        \SBDRE\rendreSujetsMembre([]) === '', true);
$sesSujets = \SBDRE\rendreSujetsMembre([['lien' => '/forum/squat-technique/3/#dernier', 'titre' => '<i>Prépa</i>', 'forum' => 'Technique', 'reponses' => 1]]);
verifie('Ses sujets au profil, titre échappé',             str_contains($sesSujets, '&lt;i&gt;Prépa') && str_contains($sesSujets, "1\u{00A0}réponse"), true);

titre('Le forum au quotidien');

verifie('Une seule page : un seul numéro',                 \SBDRE\numerosDePage(1, 1) === [1], true);
verifie('Trois pages : toutes',                            \SBDRE\numerosDePage(1, 3) === [1, 2, 3], true);
verifie('Page 5 sur 10 : 1 … 3 à 7 … 10',                  \SBDRE\numerosDePage(5, 10) === [1, 0, 3, 4, 5, 6, 7, 0, 10], true);
verifie('Dernière page sur 10 : 1 … 8 9 10',               \SBDRE\numerosDePage(10, 10) === [1, 0, 8, 9, 10], true);
$pages = \SBDRE\pagination('/forum/squat-technique/', 2, 3);
verifie('La page en cours est dite au lecteur d\'écran',    str_contains($pages, '<span class="courante" aria-current="page">2</span>'), true);
verifie('La page 1 garde son adresse sans ?page',          str_contains($pages, 'href="/forum/squat-technique/" aria-label="Page 1"'), true);
verifie('Précédente et Suivante encadrent les numéros',    str_contains($pages, 'rel="prev"') && str_contains($pages, 'rel="next"'), true);
$ligne = \SBDRE\ligneSujet(['id' => 3, 'titre' => 'Prépa', 'pseudo' => 'jean_93', 'reponses' => 7, 'dernier_pseudo' => 'marie', 'dernier_ts' => $le7, 'creation_ts' => $le7], 'squat-technique');
verifie('Une ligne de sujet porte ses repères de lecture', str_contains($ligne, 'data-sujet-id="3" data-reponses="7" data-creation="' . $le7 . '" data-adresse="squat-technique"'), true);
$page1 = $pageSujet($message, $sujetOuvert);
verifie('Le sujet dit jusqu\'où la page mène',               str_contains($page1, 'data-sujet-id="3" data-vu="0"'), true);
verifie('Le numéro du message est une ancre',              str_contains($page1, 'id="n1"'), true);
verifie('L\'aide annonce Ctrl + Entrée et le brouillon',     str_contains($page1, 'Ctrl + Entrée publie'), true);

titre('Relier un membre à sa fiche');

verifie('Adresse de fiche « jean-dupont-2 » valide',       \SBDRE\slugAthleteValide('jean-dupont-2'), true);
verifie('Adresse à majuscule refusée',                    \SBDRE\slugAthleteValide('Jean-dupont'), false);
verifie('Adresse qui remonte d\'un dossier refusée',       \SBDRE\slugAthleteValide('../membre'), false);
verifie('Adresse à tiret final refusée',                  \SBDRE\slugAthleteValide('jean-'), false);
verifie('Message de 300 caractères accepté',              \SBDRE\messageRevendicationValide(str_repeat('é', 300)), true);
verifie('Message de 301 caractères refusé',               \SBDRE\messageRevendicationValide(str_repeat('é', 301)), false);
$profilAthlete = \SBDRE\rendreProfil(['id' => 7, 'pseudo' => 'paul_120', 'bio' => '', 'salle' => '', 'instagram' => '', 'instagram_visible' => 0,
    'date_inscription' => '2026-08-01 10:00:00', 'avatar_v' => null, 'athlete_slug' => 'paul-dupont', 'athlete_nom' => 'Paul <b>Dupont</b>'], '%%FICHE%%%%DONNEES%%', [], []);
verifie('Le profil relié mène à la fiche, nom échappé',   str_contains($profilAthlete, 'href="/athlete/paul-dupont/"') && str_contains($profilAthlete, 'Paul &lt;b&gt;Dupont'), true);
verifie('Et la dit aux moteurs, sameAs',                 str_contains($profilAthlete, 'https://sbd.re/athlete/paul-dupont/'), true);

titre('Le profil d\'un coup d\'œil');

$perfsProfil = [
    ['mouvement' => 'squat', 'equipement' => 'raw', 'charge_kg' => '215.00', 'categorie' => '93', 'date_performance' => '2026-11-02',
        'lien_video' => 'https://youtu.be/a', 'date_verif_admin' => null, 'votes' => '20', 'favorables' => '15'],
    ['mouvement' => 'bench', 'equipement' => 'raw', 'charge_kg' => '140.00', 'categorie' => '93', 'date_performance' => '2026-10-28',
        'lien_video' => 'https://youtu.be/b', 'date_verif_admin' => '2026-11-01 10:00:00', 'votes' => '0', 'favorables' => '0'],
    ['mouvement' => 'deadlift', 'equipement' => 'raw', 'charge_kg' => '260.00', 'categorie' => '93', 'date_performance' => '2026-10-20',
        'lien_video' => 'https://youtu.be/c', 'date_verif_admin' => null, 'votes' => '4', 'favorables' => '2'],
];
$coupDOeil = \SBDRE\rendreProfil(['id' => 7, 'pseudo' => 'paul_120', 'bio' => '', 'salle' => '', 'instagram' => 'paul.force', 'instagram_visible' => 1,
    'date_inscription' => '2026-08-01 10:00:00', 'avatar_v' => null], '%%FICHE%%%%CHIFFRES%%%%RECORDS%%', [], $perfsProfil);
verifie('Les faits portent leur icône du sprite',          str_contains($coupDOeil, '<use href="#icone-instagram"/>') && str_contains($coupDOeil, '<use href="#icone-competitions"/>'), true);
verifie('Et leur libellé pour les lecteurs d\'écran',       str_contains($coupDOeil, '<span class="sr-only">Instagram : </span>'), true);
verifie('Une tuile par mouvement, et le total',            substr_count($coupDOeil, '<li class="tuile') === 4 && str_contains($coupDOeil, '615'), true);
verifie('Le dessin du mouvement vient du sprite',          str_contains($coupDOeil, '<use href="#mvt-deadlift"/>'), true);
verifie('L\'abréviation à l\'œil, le nom entier à l\'oreille', str_contains($coupDOeil, '>SDT</span><span class="sr-only">Soulevé de terre</span>'), true);
verifie('Les chiffres : 3 barres publiées, 2 vérifiées',   str_contains($coupDOeil, 'barres publiées</dt><dd>3</dd>') && str_contains($coupDOeil, 'vérifiées</dt><dd>2</dd>'), true);
verifie('L\'historique se replie sous les tuiles',          str_contains($coupDOeil, '<summary>Toutes les performances, 3</summary>'), true);
verifie('Sans barre, ni chiffres ni tuiles',               str_contains(\SBDRE\rendreProfil(['id' => 8, 'pseudo' => 'nouveau', 'bio' => '', 'salle' => '', 'instagram' => '', 'instagram_visible' => 0,
    'date_inscription' => '2026-11-09 10:00:00', 'avatar_v' => null], '%%CHIFFRES%%%%RECORDS%%', [], []), 'tuile'), false);

titre('« J\'aime »');

$jean = ['id' => 1, 'pseudo' => 'jean_93', 'courriel_verifie' => 1, 'banni' => 0];
$dePaul = ['id' => 101, 'membre_id' => 7, 'aime' => '4'];
$boutonAime = \SBDRE\boutonAime($dePaul, $jean, true);
verifie('Sur le message d\'un autre, un bouton pressé',      str_contains($boutonAime, 'data-aimer data-message="101" aria-pressed="true"'), true);
verifie('Le bouton porte le compte',                      str_contains($boutonAime, '<span class="nombre-aime"> · 4</span>'), true);
verifie('Sur son propre message, le compte seul',         \SBDRE\boutonAime(['id' => 102, 'membre_id' => 1, 'aime' => '2'], $jean, false) === "<span class=\"aime\">2\u{00A0}membres aiment ce message</span>", true);
verifie('Un visiteur lit le compte, sans bouton',         str_contains(\SBDRE\boutonAime($dePaul, null, false), 'data-aimer'), false);
verifie('À zéro, un visiteur ne lit rien',                \SBDRE\boutonAime(['id' => 103, 'membre_id' => 7, 'aime' => '0'], null, false) === '', true);
verifie('Un membre banni ne voit pas le bouton',          str_contains(\SBDRE\boutonAime($dePaul, ['banni' => 1] + $jean, false), 'data-aimer'), false);
verifie('Un auteur effacé : le compte seul',              str_contains(\SBDRE\boutonAime(['id' => 104, 'membre_id' => null, 'aime' => '1'], $jean, false), 'data-aimer'), false);

titre('L\'annuaire des membres');

verifie('La recherche se ramène au pseudo',               \SBDRE\rechercheMembre('  Jean_93 ') === 'jean_93', true);
verifie('Une lettre seule ne cherche rien',               \SBDRE\rechercheMembre('j') === '', true);
verifie('Un balisage ne cherche rien',                    \SBDRE\rechercheMembre('<b>jean</b>') === '', true);

$gabaritAnnuaire = '%%TITRE%%|%%ROBOTS%%|%%EFFECTIF%%|%%Q%%|%%LISTE%%|%%PAGINATION%%|<link rel="canonical" href="https://sbd.re/membres/-annuaire/">';
$annuaire = [
    ['id' => 7, 'pseudo' => 'paul_120', 'salle' => 'salle-essai', 'athlete_slug' => 'adrien-hoareau', 'avatar_v' => null, 'barres' => '5', 'verifiees' => 3],
    ['id' => 3, 'pseudo' => 'marie.force', 'salle' => '', 'athlete_slug' => null, 'avatar_v' => '1700000000', 'barres' => '0', 'verifiees' => 0],
];
$sallesEssai = ['salle-essai' => ['nom' => 'Salle <Essai>', 'commune' => 'Saint-Paul']];
$page1 = \SBDRE\rendreAnnuaire($gabaritAnnuaire, $annuaire, 64, 1, '', $sallesEssai);
verifie('Une carte par membre, son pseudo en lien',       substr_count($page1, '<li class="carte-annuaire">') === 2 && str_contains($page1, 'href="/membres/paul_120/"'), true);
verifie('Ses barres, et combien sont vérifiées',          str_contains($page1, "5\u{00A0}barres, 3\u{00A0}vérifiées"), true);
verifie('Sans barre, la carte le dit',                    str_contains($page1, 'Aucune barre publiée'), true);
verifie('Le nom de la salle est échappé',                 str_contains($page1, 'Salle &lt;Essai&gt;, Saint-Paul'), true);
verifie('Sans avatar, l\'initiale',                         str_contains($page1, '<span class="avatar avatar-vide" aria-hidden="true">P</span>'), true);
verifie('Avec avatar, l\'image du membre',                  str_contains($page1, 'src="/avatars/marie.force.webp?v=1700000000"'), true);
verifie('La liste entière s\'indexe',                       str_contains($page1, "|index, follow|64\u{00A0}membres|"), true);
verifie('Trois pages pour 64 membres',                    str_contains($page1, 'href="/membres/?page=3"'), true);
verifie('La page 2 a sa propre adresse canonique',        str_contains(\SBDRE\rendreAnnuaire($gabaritAnnuaire, $annuaire, 64, 2, '', []), 'href="https://sbd.re/membres/?page=2"'), true);
$recherche = \SBDRE\rendreAnnuaire($gabaritAnnuaire, [], 0, 1, 'zz', []);
verifie('Une recherche reste hors index',                 str_contains($recherche, '|noindex|'), true);
verifie('Une recherche vide le dit, avec la sortie',      str_contains($recherche, 'Aucun membre ne porte « zz » dans son pseudo') && str_contains($recherche, 'href="/membres/"'), true);

titre('Le récapitulatif par courriel');

$corps = \SBDRE\corpsRecapitulatif('jean_93', [
    ['texte' => 'marie a répondu à « Ma prépa ».', 'lien' => '/forum/squat-technique/3/#message-44'],
    ['texte' => 'Un message masqué.', 'lien' => ''],
], 23, str_repeat('a', 64));
verifie('Le nombre est dit',                          str_contains($corps, '23 notifications vous attendent'), true);
verifie('Chaque lien est complet',                    str_contains($corps, 'https://sbd.re/forum/squat-technique/3/#message-44'), true);
verifie('Un lien vide ne fait pas de ligne',          str_contains($corps, "https://sbd.re\n"), false);
verifie('Le reste est compté',                        str_contains($corps, 'Et 21 autres.'), true);
verifie('Le lien « couper » porte le jeton après #',  str_contains($corps, '/compte/recapitulatif/#jeton=' . str_repeat('a', 64)), true);
verifie('Une seule notification, au singulier',       str_contains(\SBDRE\corpsRecapitulatif('x', [['texte' => 't', 'lien' => '/']], 1, 'j'), 'Une notification vous attend'), true);
verifie('Une barre vérifiée se distingue par la coche', str_contains(\SBDRE\corpsRecapitulatif('x', [['nature' => 'badge', 'texte' => 'Votre squat est vérifié.', 'lien' => '/compte/']], 1, 'j'),
    "\n✓ Barre vérifiée. Votre squat est vérifié.\n"), true);

titre('Le fil');

$fil = \SBDRE\rendreFil([
    ['nature' => 'sujet', 'sujet_id' => 3, 'message_id' => null, 'titre' => '<b>Titre</b>', 'pseudo' => 'jean_93', 'adresse' => 'squat-technique', 'forum' => 'Squat, Technique', 'position' => 0, 'mouvement' => null, 'charge_kg' => null, 'date_ts' => $le7],
    ['nature' => 'reponse', 'sujet_id' => 3, 'message_id' => 44, 'titre' => 'Titre', 'pseudo' => 'marie', 'adresse' => 'squat-technique', 'forum' => 'Squat, Technique', 'position' => 25, 'mouvement' => null, 'charge_kg' => null, 'date_ts' => $le7],
    ['nature' => 'badge_communaute', 'sujet_id' => null, 'message_id' => null, 'titre' => null, 'pseudo' => 'paul_120', 'adresse' => null, 'forum' => null, 'position' => 0, 'mouvement' => 'squat', 'charge_kg' => '200.00', 'date_ts' => $le7],
]);
verifie('Un titre de sujet reste échappé',            str_contains($fil, '&lt;b&gt;Titre'), true);
verifie('Une réponse mène à sa page et son ancre',    str_contains($fil, '/forum/squat-technique/3/?page=2#message-44'), true);
verifie('Une barre vérifiée mène au profil',          str_contains($fil, '/membres/paul_120/') && str_contains($fil, 'par la communauté'), true);
verifie('Fil vide : une phrase, pas une liste',        str_contains(\SBDRE\rendreFil([]), '<ol'), false);

titre('Avatars');

if (!function_exists('imagewebp')) {
    echo "  [--]     GD absent ici : lancer avec « php -d extension=gd », le serveur l'a.\n";
} else {
    $image = imagecreatetruecolor(800, 600);
    ob_start();
    imagejpeg($image);
    $jpeg = (string) ob_get_clean();
    $webp = \SBDRE\preparerAvatar($jpeg);
    $taille = $webp !== null ? getimagesizefromstring($webp) : false;
    verifie('JPEG 800 × 600 : WebP 300 × 300',        $taille !== false && $taille[0] === 300 && $taille[1] === 300 && $taille['mime'] === 'image/webp', true);
    ob_start();
    imagegif($image);
    verifie('GIF refusé',                             \SBDRE\preparerAvatar((string) ob_get_clean()) === null, true);
    $petite = imagecreatetruecolor(100, 100);
    ob_start();
    imagepng($petite);
    verifie('PNG de 100 px refusé, trop petit',       \SBDRE\preparerAvatar((string) ob_get_clean()) === null, true);
    verifie('Un script déguisé est refusé',           \SBDRE\preparerAvatar("\xFF\xD8\xFF<?php system('id'); ?>") === null, true);
    verifie('Rien envoyé : refusé',                   \SBDRE\preparerAvatar('') === null, true);
}

titre('Domaines qui refusent nos courriels');

verifie('icloud.com injoignable',          \SBDRE\adresseInjoignable(\SBDRE\normaliserCourriel('Jean@iCloud.com')), true);
verifie('me.com injoignable',              \SBDRE\adresseInjoignable('jean@me.com'),               true);
verifie('mac.com injoignable',             \SBDRE\adresseInjoignable('jean@mac.com'),              true);
verifie('gmail.com joignable',             \SBDRE\adresseInjoignable('jean@gmail.com'),            false);
verifie('icloud.com.exemple.re joignable', \SBDRE\adresseInjoignable('jean@icloud.com.exemple.re'), false);
verifie('home.com joignable, pas « me.com »', \SBDRE\adresseInjoignable('jean@home.com'),           false);

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

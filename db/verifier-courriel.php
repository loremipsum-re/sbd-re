<?php
/**
 * Vérification de l'envoi de courriels, en SMTP authentifié.
 *
 * À lancer AVANT d'écrire l'inscription, qui en dépend entièrement : sans
 * courriel de vérification qui arrive, personne ne crée de compte, et rien dans
 * les journaux ne le signale. C'est une panne silencieuse, découverte seulement
 * par les visiteurs qui abandonnent.
 *
 * -----------------------------------------------------------------------------
 * CE QU'IL PROUVE, ET CE QU'IL NE PROUVE PAS
 * -----------------------------------------------------------------------------
 * Il prouve que la boîte s'authentifie et que le serveur accepte le message.
 * Il ne prouve PAS qu'il arrive : entre les deux restent les filtres de Gmail,
 * d'Outlook et des autres. D'où la consigne finale, qui est humaine.
 *
 * -----------------------------------------------------------------------------
 * USAGE
 * -----------------------------------------------------------------------------
 *   php verifier-courriel.php ton.adresse@exemple.re
 *
 * À lancer depuis le dossier qui contient `config.php`. Le script trouve seul
 * les bibliothèques déployées avec le site ; on peut aussi lui passer le chemin
 * de courriel.php en second argument.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$succes = 0;
$echecs = 0;

function titre(string $t): void
{
    echo "\n", $t, "\n", str_repeat('-', strlen($t)), "\n";
}

function ok(string $t): void
{
    global $succes;
    $succes++;
    echo '  [ok]     ', $t, "\n";
}

function ko(string $t): void
{
    global $echecs;
    $echecs++;
    echo '  [ECHEC]  ', $t, "\n";
}

function info(string $t): void
{
    echo '           ', $t, "\n";
}

echo "\nVérification de l'envoi de courriels\n";
echo "====================================\n";

/* -------------------------------------------------------------------------
 * Le destinataire
 * ---------------------------------------------------------------------- */

$destinataire = $argv[1] ?? '';

if (!filter_var($destinataire, FILTER_VALIDATE_EMAIL)) {
    echo "\nUsage : php verifier-courriel.php ton.adresse@exemple.re\n";
    echo "Mettre une VRAIE adresse, que tu peux consulter tout de suite.\n\n";
    exit(1);
}

/* -------------------------------------------------------------------------
 * 1. Trouver la bibliothèque
 *
 * Elle est déployée avec le site. Depuis ~/sbd-re-prive, elle se trouve donc
 * dans ~/sbd-re/api/lib/. Les autres candidats couvrent un dépôt à la main et
 * l'arborescence de développement.
 * ---------------------------------------------------------------------- */

titre('1. Bibliothèque');

$candidats = array_values(array_filter([
    $argv[2] ?? null,
    dirname(__DIR__) . '/sbd-re/api/lib/courriel.php',
    __DIR__ . '/courriel.php',
    dirname(__DIR__) . '/public/api/lib/courriel.php',
]));

$lib = null;
foreach ($candidats as $c) {
    if (is_file($c)) {
        $lib = $c;
        break;
    }
}

if ($lib === null) {
    ko('courriel.php introuvable.');
    foreach ($candidats as $c) {
        info('cherché : ' . $c);
    }
    info('Passer son chemin complet en second argument.');
    exit(1);
}

ok('Trouvée : ' . $lib);

// Les bibliothèques refusent de se charger sans cette constante, qui prouve
// qu'elles sont incluses par du code du projet et non appelées par le web.
define('SBDRE_API', true);
require_once $lib;

/* -------------------------------------------------------------------------
 * 2. La configuration
 * ---------------------------------------------------------------------- */

titre('2. Configuration');

try {
    $smtp = \SBDRE\config_val('smtp');
    $exp  = \SBDRE\config_val('courriel');
} catch (\Throwable $e) {
    ko('Configuration illisible : ' . $e->getMessage());
    info('config.php est attendu dans ~/sbd-re-prive/.');
    exit(1);
}

$motDePasse = (string) ($smtp['mot_de_passe'] ?? '');

if (!is_array($smtp) || $motDePasse === '' || str_starts_with($motDePasse, 'a-completer')) {
    ko('La section « smtp » de config.php n\'est pas renseignée.');
    info('Reprendre le modèle db/config.exemple.php, section smtp.');
    exit(1);
}

ok(sprintf(
    'Serveur : %s port %s en %s',
    (string) ($smtp['hote'] ?? '?'),
    (string) ($smtp['port'] ?? '?'),
    (string) ($smtp['chiffrement'] ?? 'ssl'),
));
ok('Boîte : ' . (string) ($smtp['utilisateur'] ?? '?'));

$expediteur = (string) ($exp['expediteur'] ?? '');
$domaine    = substr(strrchr($expediteur, '@') ?: '', 1);

if (str_ends_with($domaine, 'sbd.re')) {
    ok('L\'expéditeur appartient bien au domaine du site.');
} else {
    ko('L\'expéditeur n\'est pas du domaine sbd.re : ' . $domaine);
    info('Le SPF du domaine ne pourra pas l\'authentifier.');
}

// Le SPF décide de la légitimité du message aux yeux du destinataire.
if (function_exists('dns_get_record')) {
    $spf = [];
    foreach (@dns_get_record($domaine, DNS_TXT) ?: [] as $t) {
        if (isset($t['txt']) && str_starts_with((string) $t['txt'], 'v=spf1')) {
            $spf[] = (string) $t['txt'];
        }
    }

    if ($spf !== []) {
        ok('Le domaine publie un SPF : ' . implode(' | ', $spf));
    } else {
        ko('Aucun SPF publié pour ' . $domaine . '.');
    }
}

/* -------------------------------------------------------------------------
 * 3. L'envoi
 * ---------------------------------------------------------------------- */

titre('3. Conversation avec le serveur');

$jeton = bin2hex(random_bytes(4));

// La trace est masquée avant affichage : l'authentification SMTP transite en
// base64, donc le mot de passe de la boîte y apparaîtrait pour qui sait
// décoder, ce qui est immédiat. Sans ce masquage, coller cette sortie dans une
// conversation ou un ticket le divulguerait.
$journal = static function (string $ligne, int $niveau): void {
    $propre = rtrim(\SBDRE\masquerIdentifiants($ligne));
    if ($propre !== '') {
        echo '           | ', $propre, "\n";
    }
};

$corps = "Message d'essai envoyé par db/verifier-courriel.php.\n\n"
       . "Si tu le lis, l'envoi fonctionne.\n\n"
       . "Jeton : $jeton\n"
       . 'Envoyé le ' . date('d/m/Y à H:i:s') . " (heure de La Réunion)\n\n"
       . "Reste la vraie question : ce message est-il arrivé dans la boîte de\n"
       . "réception, ou dans les indésirables ?\n";

[$envoye, $erreur] = \SBDRE\envoyerCourriel(
    $destinataire,
    'SBD.re, essai d\'envoi ' . $jeton,
    $corps,
    $journal,
);

titre('4. Résultat');

if ($envoye) {
    ok('Message accepté par le serveur pour ' . $destinataire . '.');
    info('Jeton à retrouver : ' . $jeton);
} else {
    ko('Envoi refusé.');
    info($erreur);
}

/* -------------------------------------------------------------------------
 * Bilan
 * ---------------------------------------------------------------------- */

titre('Bilan');

echo "  Réussis : $succes\n";
echo "  Échecs  : $echecs\n\n";

if ($echecs > 0) {
    echo "  À corriger avant de bâtir l'inscription dessus.\n\n";
    exit(1);
}

echo "  CE N'EST PAS FINI : va voir ta boîte, ET les indésirables.\n";
echo "  Cherche le jeton $jeton. Ce que tu trouveras décide de la suite :\n\n";
echo "    - en réception    : on avance ainsi ;\n";
echo "    - en indésirables : il faudra soigner la réputation d'envoi ;\n";
echo "    - nulle part      : accepté puis jeté, à creuser.\n\n";

exit(0);

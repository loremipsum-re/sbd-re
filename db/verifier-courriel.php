<?php
/**
 * Vérification de l'envoi de courriels depuis l'hébergement.
 *
 * À lancer AVANT d'écrire l'inscription, parce que tout le parcours en dépend :
 * sans courriel de vérification qui arrive, personne ne peut créer de compte, et
 * rien dans les journaux ne le signale. C'est une panne silencieuse, découverte
 * par les visiteurs qui abandonnent.
 *
 * -----------------------------------------------------------------------------
 * CE QUE CE SCRIPT PROUVE, ET CE QU'IL NE PROUVE PAS
 * -----------------------------------------------------------------------------
 * Il prouve que PHP a bien confié le message au serveur d'envoi.
 *
 * Il ne prouve PAS que le message arrive. `mail()` renvoyant `true` signifie
 * seulement « accepté pour envoi », jamais « délivré ». Entre les deux il y a
 * les filtres anti-spam de Gmail, d'Outlook et des autres, qui écartent
 * volontiers un message venu d'un hébergement mutualisé.
 *
 * D'où la consigne : après l'avoir lancé, REGARDER SA BOÎTE, et regarder aussi
 * le dossier des indésirables. C'est cette seconde vérification qui compte.
 *
 * -----------------------------------------------------------------------------
 * USAGE
 * -----------------------------------------------------------------------------
 *   php verifier-courriel.php ton.adresse@exemple.re
 *
 * L'expéditeur est lu dans config.php, section `courriel`.
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

if ($destinataire === '' || !filter_var($destinataire, FILTER_VALIDATE_EMAIL)) {
    echo "\nUsage : php verifier-courriel.php ton.adresse@exemple.re\n";
    echo "Mettre une adresse que tu peux consulter tout de suite.\n\n";
    exit(1);
}

/* -------------------------------------------------------------------------
 * 1. La fonction est-elle disponible ?
 *
 * Certains hébergements désactivent mail() par `disable_functions`. Autant le
 * savoir maintenant que devant une inscription qui ne part pas.
 * ---------------------------------------------------------------------- */

titre('1. Disponibilité');

if (!function_exists('mail')) {
    ko('La fonction mail() est désactivée sur cet hébergement.');
    info('Il faudra passer par SMTP authentifié, avec une boîte du domaine.');
    exit(1);
}

ok('La fonction mail() est disponible.');

$desactivees = (string) ini_get('disable_functions');
if ($desactivees !== '') {
    info('Fonctions désactivées ici : ' . $desactivees);
}

info('Programme d\'envoi déclaré : ' . (ini_get('sendmail_path') ?: 'valeur par défaut'));

/* -------------------------------------------------------------------------
 * 2. L'expéditeur
 * ---------------------------------------------------------------------- */

titre('2. Expéditeur');

$chemin = __DIR__ . '/config.php';

if (!is_file($chemin)) {
    ko('config.php introuvable à côté de ce script.');
    exit(1);
}

/** @var array<string, mixed> $config */
$config = require $chemin;

$expediteur = $config['courriel']['expediteur'] ?? '';
$nom        = $config['courriel']['nom_expediteur'] ?? 'SBD.re';

if ($expediteur === '' || !filter_var($expediteur, FILTER_VALIDATE_EMAIL)) {
    ko('Aucune adresse d\'expédition valable dans config.php, section courriel.');
    exit(1);
}

ok('Expéditeur : ' . $expediteur);

// Le domaine de l'expéditeur décide de la délivrabilité. Une adresse d'un autre
// domaine que le site part presque à coup sûr en indésirable, les
// enregistrements SPF et DKIM de sbd.re ne pouvant pas l'authentifier.
$domaine = substr(strrchr($expediteur, '@') ?: '', 1);

if (str_ends_with($domaine, 'sbd.re')) {
    ok('L\'expéditeur est bien du domaine du site.');
} else {
    ko('L\'expéditeur n\'est PAS du domaine sbd.re : ' . $domaine);
    info('Le message sera très probablement classé en indésirable.');
}

// Un enregistrement SPF publié pour le domaine améliore nettement les chances.
if (function_exists('dns_get_record')) {
    $spf = [];
    foreach (@dns_get_record($domaine, DNS_TXT) ?: [] as $t) {
        if (isset($t['txt']) && str_starts_with($t['txt'], 'v=spf1')) {
            $spf[] = $t['txt'];
        }
    }

    if ($spf !== []) {
        ok('Le domaine publie un enregistrement SPF.');
        info(implode(' | ', $spf));
    } else {
        ko('Aucun enregistrement SPF trouvé pour ' . $domaine . '.');
        info('Sans lui, les messages partent souvent en indésirable.');
    }
}

/* -------------------------------------------------------------------------
 * 3. L'envoi
 * ---------------------------------------------------------------------- */

titre('3. Envoi');

$jeton = bin2hex(random_bytes(4));
$sujet = 'SBD.re, essai d\'envoi ' . $jeton;

$corps = "Ceci est un message d'essai envoyé par db/verifier-courriel.php.\n\n"
       . "Si tu le lis, l'hébergement sait envoyer du courrier.\n\n"
       . "Jeton de cet envoi : $jeton\n"
       . "Envoyé le : " . date('d/m/Y à H:i:s') . " (heure de La Réunion)\n\n"
       . "Le point à vérifier maintenant : ce message est-il arrivé dans la\n"
       . "boîte de réception, ou dans les indésirables ? La réponse décide de\n"
       . "la suite. Un lien de vérification qui atterrit en indésirable bloque\n"
       . "les inscriptions sans que rien ne le signale.\n";

// En-têtes minimales mais complètes. Sans Content-Type explicite, les accents
// arrivent en caractères illisibles chez une partie des destinataires.
$entetes = [
    'From: ' . sprintf('=?UTF-8?B?%s?= <%s>', base64_encode($nom), $expediteur),
    'Reply-To: ' . $expediteur,
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
    'X-Mailer: PHP/' . PHP_VERSION,
];

// Le sujet doit être encodé lui aussi, sinon l'apostrophe et les accents
// ressortent en charabia dans la liste des messages.
$sujetEncode = '=?UTF-8?B?' . base64_encode($sujet) . '?=';

$envoye = mail($destinataire, $sujetEncode, $corps, implode("\r\n", $entetes));

if ($envoye) {
    ok('mail() a accepté le message pour ' . $destinataire . '.');
    info('Jeton à retrouver dans le message : ' . $jeton);
} else {
    ko('mail() a refusé le message.');
    info('L\'hébergement n\'a pas pris le relais. Voir les journaux OVH.');
}

/* -------------------------------------------------------------------------
 * Bilan
 * ---------------------------------------------------------------------- */

titre('Bilan');

echo "  Réussis : $succes\n";
echo "  Échecs  : $echecs\n\n";

if ($echecs > 0) {
    echo "  Des points sont à corriger avant de bâtir l'inscription dessus.\n\n";
    exit(1);
}

echo "  PHP a confié le message au serveur d'envoi.\n\n";
echo "  CE N'EST PAS FINI : va voir ta boîte, et le dossier des indésirables.\n";
echo "  Cherche le jeton $jeton. Ce que tu y trouveras décide de la suite :\n\n";
echo "    - dans la réception : mail() suffit, on avance ainsi ;\n";
echo "    - dans les indésirables : il faudra passer par SMTP authentifié,\n";
echo "      avec une vraie boîte du domaine ;\n";
echo "    - nulle part après dix minutes : mail() ne sort pas d'ici.\n\n";

exit(0);

<?php

declare(strict_types=1);

/**
 * Vérification de l'envoi de courriel, par la chaîne réelle du site.
 *
 * -----------------------------------------------------------------------------
 * POURQUOI CE SCRIPT EXISTE
 * -----------------------------------------------------------------------------
 * Un formulaire qui avale les messages en silence est pire qu'un formulaire
 * absent. Ce script appelle `envoyerCourriel()` de public/api/lib/courriel.php,
 * la fonction des formulaires et des comptes, et dit quelle voie elle a prise :
 *
 *   - SMTP authentifié sur no-reply@sbd.re, dès que config.php porte le mot de
 *     passe de la boîte, section `smtp`. C'est la voie signée DKIM, la seule
 *     qu'iCloud accepte (refus [HM08] mesuré le 6 novembre 2026 avec mail()).
 *   - mail() sinon, depuis les serveurs de l'hébergement, sans signature.
 *
 * -----------------------------------------------------------------------------
 * OÙ LE LANCER
 * -----------------------------------------------------------------------------
 * Chez OVH, en SSH avec le login principal, depuis ~/sbd-re-prive, à côté de
 * config.php. En ligne de commande, mail() est refusée par l'hébergement
 * (« not allowed to submit mail ») : seul le SMTP s'y éprouve.
 *
 * -----------------------------------------------------------------------------
 * CE QU'IL PROUVE, ET CE QU'IL NE PROUVE PAS
 * -----------------------------------------------------------------------------
 * Il prouve que le serveur a ACCEPTÉ le message. Il ne prouve PAS qu'il arrive :
 * entre les deux restent les filtres du destinataire. Regarder les indésirables
 * avant de conclure à une panne, et l'onglet Scripts e-mails d'OVH pour mail().
 *
 *   Usage : php verifier-envoi.php [adresse]
 */

namespace SBDRE;

const DESTINATAIRE_PAR_DEFAUT = 'nathan@loremipsum.re';

$bibliotheque = null;
foreach ([
    dirname(__DIR__) . '/public/api/lib/courriel.php', // depuis le dépôt
    dirname(__DIR__) . '/sbd-re/api/lib/courriel.php',  // depuis ~/sbd-re-prive
] as $chemin) {
    if (is_file($chemin)) {
        $bibliotheque = $chemin;
        break;
    }
}
if ($bibliotheque === null) {
    fwrite(STDERR, "lib/courriel.php introuvable, ni dans le dépôt ni dans ~/sbd-re/api/lib/.\n");
    exit(1);
}

define('SBDRE_API', true);
require $bibliotheque;

$destinataire = $argv[1] ?? DESTINATAIRE_PAR_DEFAUT;

echo "Vérification de l'envoi de courriel\n";
echo str_repeat('-', 60) . "\n";

if (!filter_var($destinataire, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Adresse invalide : {$destinataire}\n");
    exit(1);
}

$smtp = motDePasseSmtp() !== '';

echo "  destinataire : {$destinataire}\n";
echo "  expéditeur   : " . EXPEDITEUR . "\n";
echo "  voie         : " . ($smtp ? 'SMTP authentifié, ' . SMTP_HOTE . ':' . SMTP_PORT : 'mail(), sans signature') . "\n";
echo "  système      : " . PHP_OS_FAMILY . ' / PHP ' . PHP_VERSION . "\n\n";

if (!$smtp) {
    echo "ARRÊT : le SMTP n'est pas configuré.\n\n";
    echo "  Ajouter à config.php, dans ~/sbd-re-prive :\n";
    echo "      'smtp' => ['mot_de_passe' => '…'],\n";
    echo "  avec le mot de passe de la boîte " . EXPEDITEUR . ". En ligne de\n";
    echo "  commande, mail() est refusée par l'hébergement : rien à éprouver sans SMTP.\n";
    exit(2);
}

$corps = implode("\n", [
    "Message d'essai envoyé par db/verifier-envoi.php.",
    '',
    "Si vous lisez ceci dans la boîte de réception, le site envoie par SMTP",
    "authentifié, et les liens de confirmation des comptes arriveront aussi.",
    '',
    'Date    : ' . date('d/m/Y à H:i:s'),
    'Serveur : ' . (gethostname() ?: 'inconnu'),
]);

// La voie SMTP seule : envoyerCourriel() repasserait par mail() en cas
// d'échec, et le journal montrerait la raison du mauvais refus.
if (envoyerParSmtp($destinataire, '[sbd.re] Essai d\'envoi, accentué', $corps, '', motDePasseSmtp())) {
    echo "ACCEPTÉ : le serveur de messagerie d'OVH a pris le message en charge.\n\n";
    echo "  Vérifiez maintenant la boîte {$destinataire}, INDÉSIRABLES COMPRIS.\n";
    echo "  L'acceptation ne garantit pas la remise.\n";
    exit(0);
}

echo "REFUSÉ. La raison, telle que le serveur l'a donnée :\n\n";
$journal = __DIR__ . '/courriels-refuses.log';
$lignes = is_file($journal) ? file($journal, FILE_IGNORE_NEW_LINES) : false;
echo '  ' . ($lignes ? end($lignes) : '(journal introuvable, voir le journal d\'erreurs de PHP)') . "\n\n";
echo "  « Could not authenticate » : mauvais mot de passe, ou boîte " . EXPEDITEUR . "\n";
echo "  inexistante. À créer dans l'espace client OVH, rubrique E-mails.\n";
exit(1);

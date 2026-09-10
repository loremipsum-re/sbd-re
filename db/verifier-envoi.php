<?php

declare(strict_types=1);

/**
 * Vérification de l'envoi de courriel par le serveur, sans SMTP authentifié.
 *
 * -----------------------------------------------------------------------------
 * POURQUOI CE SCRIPT EXISTE
 * -----------------------------------------------------------------------------
 * Le formulaire de partenariat poste avec `mail()`, la fonction du serveur, et
 * non par SMTP authentifié : aucune boîte à créer, aucun mot de passe à
 * déposer. En contrepartie on ne sait pas si ça marche tant qu'on n'a pas
 * essayé, et un formulaire qui avale les demandes en silence est pire qu'un
 * formulaire absent.
 *
 * Ce script envoie exactement le même genre de message que le formulaire, avec
 * les mêmes en-têtes, pour éprouver la chaîne sans passer par la page.
 *
 * -----------------------------------------------------------------------------
 * IL NE PEUT PAS TOURNER SUR UNE MACHINE DE DÉVELOPPEMENT
 * -----------------------------------------------------------------------------
 * `mail()` a besoin d'un serveur de courrier local. Windows n'en a pas, et le
 * script le dit plutôt que d'échouer sans expliquer. Il est fait pour être
 * lancé en SSH sur l'hébergement OVH.
 *
 * -----------------------------------------------------------------------------
 * CE QU'IL PROUVE, ET CE QU'IL NE PROUVE PAS
 * -----------------------------------------------------------------------------
 * Il prouve que le serveur a ACCEPTÉ le message. Il ne prouve PAS qu'il arrive :
 * entre les deux restent les filtres du destinataire. Un premier envoi depuis un
 * domaine qui n'écrit jamais atterrit souvent en indésirables. Regarder ce
 * dossier avant de conclure à une panne.
 *
 *   Usage : php db/verifier-envoi.php [adresse]
 */

const DESTINATAIRE_PAR_DEFAUT = 'nathan@loremipsum.re';

// Repris à l'identique de public/api/contact.php : c'est la chaîne réelle qu'on
// veut éprouver, pas une approximation.
const EXPEDITEUR = 'no-reply@sbd.re';
const NOM_EXPEDITEUR = 'SBD.re';

$destinataire = $argv[1] ?? DESTINATAIRE_PAR_DEFAUT;

echo "Vérification de l'envoi de courriel\n";
echo str_repeat('-', 60) . "\n";

if (!filter_var($destinataire, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Adresse invalide : {$destinataire}\n");
    exit(1);
}

$sendmail = (string) ini_get('sendmail_path');
echo "  destinataire  : {$destinataire}\n";
echo "  expéditeur    : " . EXPEDITEUR . "\n";
echo "  sendmail_path : " . ($sendmail !== '' ? $sendmail : '(vide)') . "\n";
echo "  système       : " . PHP_OS_FAMILY . ' / PHP ' . PHP_VERSION . "\n\n";

if (PHP_OS_FAMILY === 'Windows' || $sendmail === '') {
    echo "ARRÊT : aucun serveur de courrier n'est configuré ici.\n\n";
    echo "  `mail()` délègue l'envoi à un binaire système, absent sur une\n";
    echo "  machine de développement. Ce n'est pas une panne : ce script est\n";
    echo "  fait pour tourner sur l'hébergement.\n\n";
    echo "  Sur OVH, en SSH, depuis la racine du site :\n";
    echo "      php db/verifier-envoi.php {$destinataire}\n";
    exit(2);
}

$corps = implode("\n", [
    "Message d'essai envoyé par db/verifier-envoi.php.",
    '',
    "Si vous lisez ceci, le serveur accepte et achemine les courriels, et le",
    "formulaire de la page Partenariats fonctionne de bout en bout.",
    '',
    'Date    : ' . date('d/m/Y à H:i:s'),
    'Serveur : ' . (gethostname() ?: 'inconnu'),
]);

$entetes = implode("\r\n", [
    'From: ' . NOM_EXPEDITEUR . ' <' . EXPEDITEUR . '>',
    'Content-Type: text/plain; charset=UTF-8',
    'MIME-Version: 1.0',
    'X-Mailer: sbd.re',
]);

$envoye = mail($destinataire, '[sbd.re] Essai d\'envoi', $corps, $entetes, '-f' . EXPEDITEUR);

if ($envoye) {
    echo "ACCEPTÉ : le serveur a pris le message en charge.\n\n";
    echo "  Vérifiez maintenant la boîte {$destinataire}, INDÉSIRABLES COMPRIS.\n";
    echo "  L'acceptation ne garantit pas la remise.\n";
    exit(0);
}

echo "REFUSÉ : mail() a rendu false.\n\n";
echo "  Causes habituelles sur un mutualisé : envoi désactivé par l'hébergeur,\n";
echo "  quota atteint, ou expéditeur refusé parce qu'il n'appartient pas à un\n";
echo "  domaine du compte. L'expéditeur employé ici est " . EXPEDITEUR . ".\n";
exit(1);

<?php
/**
 * Envoi de courriels, pour les formulaires comme pour les comptes.
 *
 * -----------------------------------------------------------------------------
 * mail(), SIGNÉ DKIM PAR LE SITE LUI-MÊME
 * -----------------------------------------------------------------------------
 * Mesuré le 6 novembre 2026 : iCloud refuse les messages de mail(),
 * « 554 5.7.1 [HM08] Message rejected due to local policy ». OVH ne signe
 * pas en DKIM ce qui part de l'hébergement, et Apple authentifie chaque
 * message entrant par SPF et DKIM.
 *
 * Le SMTP authentifié d'OVH, qui signe, a été essayé le même soir : depuis
 * l'hébergement, smtp.mail.ovh.net et ssl0.ovh.net refusent la connexion sur
 * 465 comme sur 587 (« Connection refused »), alors qu'ils répondent
 * d'ailleurs. Le réseau de l'hébergement les bloque.
 *
 * Le site signe donc lui-même : PHPMailer pose la signature DKIM avec une clé
 * privée rangée dans ~/sbd-re-prive/, à côté de config.php, et la clé
 * publique est publiée dans le DNS sous le sélecteur `sbdre`. La signature
 * porte le domaine sbd.re, celui de l'expéditeur : DMARC est aligné. Sans clé
 * sur le serveur, le message part quand même, non signé, comme avant.
 * db/dkim.php crée la clé et vérifie le DNS.
 *
 * PHPMailer 7.1.1, licence LGPL 2.1 : la signature DKIM et l'encodage des
 * en-têtes sont le genre de code qu'on n'écrit pas soi-même sans bonne raison.
 *
 * TROIS RÈGLES, éprouvées par contact.php :
 *   - l'expéditeur est une adresse du domaine ;
 *   - aucune donnée libre du visiteur n'entre dans un en-tête, seul Reply-To
 *     porte une adresse, validée ici même ;
 *   - le corps part en quoted-printable, replié à 76 caractères.
 */

declare(strict_types=1);

namespace SBDRE;

if (!defined('SBDRE_API')) {
    http_response_code(404);
    exit;
}

/** Expéditeur. Doit appartenir au domaine, pour SPF comme pour DKIM. */
const EXPEDITEUR = 'no-reply@sbd.re';
const NOM_EXPEDITEUR = 'SBD.re';

/** La signature DKIM : domaine signataire et sélecteur publié dans le DNS. */
const DKIM_DOMAINE = 'sbd.re';
const DKIM_SELECTEUR = 'sbdre';

/** La clé privée, hors de portée du web et du déploiement, comme config.php. */
function cheminCleDkim(): string
{
    return dirname(__DIR__, 3) . '/sbd-re-prive/dkim-prive.pem';
}

/**
 * Envoie un message en texte simple. Rend true si le serveur l'a accepté, ce
 * qui ne garantit pas qu'il arrive.
 *
 * $sujet vient toujours d'un texte écrit par le site, jamais du visiteur.
 * $repondreA est une adresse saisie par le visiteur, ou ''.
 */
function envoyerCourriel(string $destinataire, string $sujet, string $corps, string $repondreA = ''): bool
{
    // Un retour à la ligne dans une adresse ajouterait un en-tête : c'est
    // l'injection qui transforme un formulaire en relais à spam.
    foreach ([$destinataire, $repondreA] as $adresse) {
        if ($adresse !== '' && (preg_match('/[\r\n]/', $adresse) === 1
            || filter_var($adresse, FILTER_VALIDATE_EMAIL) === false)) {
            return false;
        }
    }

    $mail = preparerCourriel($destinataire, $sujet, $corps, $repondreA);

    try {
        $mail->send();

        return true;
    } catch (\Throwable $e) {
        noterRefus($destinataire, $mail->ErrorInfo !== '' ? $mail->ErrorInfo : $e->getMessage());

        return false;
    }
}

/**
 * Le message prêt à partir, signé si la clé est là. Séparé de l'envoi pour
 * que db/dkim.php puisse produire un message signé et en vérifier la
 * signature, sans rien envoyer.
 */
function preparerCourriel(string $destinataire, string $sujet, string $corps, string $repondreA = ''): \PHPMailer\PHPMailer\PHPMailer
{
    require_once __DIR__ . '/phpmailer/Exception.php';
    require_once __DIR__ . '/phpmailer/PHPMailer.php';

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isMail();
    $mail->CharSet  = 'UTF-8';
    $mail->Encoding = 'quoted-printable';
    $mail->XMailer  = 'sbd.re';

    // L'expéditeur d'enveloppe, le « -f » passé à mail() : les refus
    // reviennent à no-reply@sbd.re, et SPF juge ce domaine.
    $mail->setFrom(EXPEDITEUR, NOM_EXPEDITEUR);
    $mail->Sender = EXPEDITEUR;

    $mail->addAddress($destinataire);
    if ($repondreA !== '') {
        $mail->addReplyTo($repondreA);
    }

    $mail->isHTML(false);
    $mail->Subject = str_replace(["\r", "\n"], ' ', $sujet);
    $mail->Body    = $corps;

    if (is_readable(cheminCleDkim())) {
        $mail->DKIM_domain     = DKIM_DOMAINE;
        $mail->DKIM_selector   = DKIM_SELECTEUR;
        $mail->DKIM_private    = cheminCleDkim();
        $mail->DKIM_passphrase = '';
        $mail->DKIM_identity   = EXPEDITEUR;
    }

    return $mail;
}

/**
 * Note pourquoi un envoi a été refusé. Sans cette ligne, un refus ne laisse
 * aucune trace.
 *
 * Dans ~/sbd-re-prive/courriels-refuses.log, hors de portée du web et lisible
 * en SSH, sinon dans le journal d'erreurs de PHP. Le domaine du destinataire
 * suffit au diagnostic : l'adresse entière n'y entre pas.
 */
function noterRefus(string $destinataire, string $raison): void
{
    $ligne = sprintf(
        "%s  refus vers %s : %s\n",
        date('c'),
        substr((string) strrchr($destinataire, '@'), 1),
        str_replace(["\r", "\n"], ' ', $raison),
    );

    $dossier = dirname(__DIR__, 3) . '/sbd-re-prive';
    if (!is_dir($dossier) || !@error_log($ligne, 3, $dossier . '/courriels-refuses.log')) {
        error_log('SBD.re : ' . trim($ligne));
    }
}

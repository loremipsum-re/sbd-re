<?php
/**
 * Envoi de courriels, pour les formulaires comme pour les comptes.
 *
 * -----------------------------------------------------------------------------
 * SMTP AUTHENTIFIÉ D'ABORD, mail() EN REPLI
 * -----------------------------------------------------------------------------
 * Mesuré le 6 novembre 2026 : iCloud refuse le lien de confirmation envoyé par
 * mail(), « 554 5.7.1 [HM08] Message rejected due to local policy ». La
 * documentation d'OVH le dit : mail() part des serveurs de l'hébergement SANS
 * signature DKIM, et Apple authentifie chaque message entrant par SPF et DKIM.
 * Le formulaire de partenariat passait, parce que sa boîte de réception, chez
 * loremipsum.re, est moins sévère.
 *
 * Le SMTP authentifié sur la boîte no-reply@sbd.re (MX Plan, inclus dans
 * l'hébergement) fait partir le message des serveurs de messagerie d'OVH,
 * signé DKIM quand DKIM est activé pour le domaine. C'est la voie que la
 * documentation d'OVH recommande. Il s'active dès que `config.php` porte le
 * mot de passe de la boîte, section `smtp`. Sans lui, mail() reste en place :
 * les formulaires ne cassent pas pendant la bascule.
 *
 * PHPMailer revient pour cela, version 7.1.1, licence LGPL 2.1, retiré le
 * matin même : l'authentification et le chiffrement d'un échange SMTP sont
 * le genre de code qu'on n'écrit pas soi-même sans bonne raison.
 *
 * TROIS RÈGLES, éprouvées par contact.php :
 *   - l'expéditeur est une adresse du domaine, et en SMTP celle du compte ;
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

require_once __DIR__ . '/configuration.php';

/** Expéditeur, et identifiant de la boîte en SMTP. Doit appartenir au domaine. */
const EXPEDITEUR = 'no-reply@sbd.re';
const NOM_EXPEDITEUR = 'SBD.re';

/** Le serveur d'envoi des boîtes MX Plan, selon la documentation d'OVH. */
const SMTP_HOTE = 'smtp.mail.ovh.net';
const SMTP_PORT = 465;

/**
 * Le mot de passe de la boîte no-reply@sbd.re, ou '' si le SMTP n'est pas
 * configuré. Un config.php absent ne casse rien : les formulaires repassent
 * par mail(), comme avant.
 */
function motDePasseSmtp(): string
{
    try {
        $valeur = (string) config_val('smtp.mot_de_passe', '');
    } catch (\RuntimeException) {
        return '';
    }

    return str_starts_with($valeur, 'a-completer') ? '' : $valeur;
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
    $sujet = str_replace(["\r", "\n"], ' ', $sujet);

    $motDePasse = motDePasseSmtp();

    // Un SMTP qui échoue (mot de passe changé, boîte supprimée) laisse sa
    // raison au journal, puis le message tente mail() : le formulaire de
    // partenariat ne doit pas tomber pour une boîte mal réglée.
    return ($motDePasse !== '' && envoyerParSmtp($destinataire, $sujet, $corps, $repondreA, $motDePasse))
        || envoyerParMail($destinataire, $sujet, $corps, $repondreA);
}

/** Par la boîte no-reply@sbd.re, sur les serveurs de messagerie d'OVH. */
function envoyerParSmtp(string $destinataire, string $sujet, string $corps, string $repondreA, string $motDePasse): bool
{
    require_once __DIR__ . '/phpmailer/Exception.php';
    require_once __DIR__ . '/phpmailer/PHPMailer.php';
    require_once __DIR__ . '/phpmailer/SMTP.php';

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOTE;
        $mail->Port       = SMTP_PORT;
        $mail->SMTPAuth   = true;
        $mail->Username   = EXPEDITEUR;
        $mail->Password   = $motDePasse;
        // Chiffré de bout en bout : le mot de passe ne circule jamais en clair.
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->Timeout    = 15;
        $mail->CharSet    = 'UTF-8';
        $mail->Encoding   = 'quoted-printable';

        $mail->setFrom(EXPEDITEUR, NOM_EXPEDITEUR);
        $mail->addAddress($destinataire);
        if ($repondreA !== '') {
            $mail->addReplyTo($repondreA);
        }
        $mail->isHTML(false);
        $mail->Subject = $sujet;
        $mail->Body    = $corps;

        $mail->send();

        return true;
    } catch (\Throwable $e) {
        // ErrorInfo dit ce que le serveur a répondu, sans le mot de passe.
        noterRefus($destinataire, 'SMTP : ' . ($mail->ErrorInfo !== '' ? $mail->ErrorInfo : $e->getMessage()));

        return false;
    }
}

/** Par mail(), depuis les serveurs de l'hébergement, sans signature DKIM. */
function envoyerParMail(string $destinataire, string $sujet, string $corps, string $repondreA): bool
{
    $entetes = ['From: ' . NOM_EXPEDITEUR . ' <' . EXPEDITEUR . '>'];
    if ($repondreA !== '') {
        $entetes[] = 'Reply-To: ' . $repondreA;
    }
    array_push(
        $entetes,
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: quoted-printable',
        'MIME-Version: 1.0',
        'X-Mailer: sbd.re',
    );

    // Un en-tête ne contient que de l'ASCII : les accents du sujet passent en
    // forme codée =?UTF-8?B?…?=, que tout client décode.
    $sujet = mb_encode_mimeheader($sujet, 'UTF-8', 'B');

    // quoted_printable_encode ne reconnaît que CRLF comme fin de ligne : un \n
    // seul serait codé =0A, réservé aux données binaires. On unifie avant.
    $corps = quoted_printable_encode(preg_replace('/\r\n|\r|\n/', "\r\n", $corps) ?? $corps);

    $envoye = @mail($destinataire, $sujet, $corps, implode("\r\n", $entetes), '-f' . EXPEDITEUR);

    if (!$envoye) {
        noterRefus($destinataire, 'mail() : ' . (error_get_last()['message'] ?? 'sans détail'));
    }

    return $envoye;
}

/**
 * Note pourquoi un envoi a été refusé. Sans cette ligne, un refus ne laisse
 * aucune trace : `@` étouffe l'avertissement de mail().
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

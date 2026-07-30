<?php
/**
 * Envoi de courriels, en SMTP authentifié.
 *
 * -----------------------------------------------------------------------------
 * POURQUOI PAS mail()
 * -----------------------------------------------------------------------------
 * Mesuré le 30 juillet 2026 sur l'hébergement : `mail()` est refusée au niveau
 * du système, « User loremis is not allowed to submit mail ».
 *
 * Et c'est tant mieux, car ce n'était pas la bonne voie. L'enregistrement SPF
 * du domaine est « v=spf1 include:mx.ovh.com -all » : seuls les serveurs de
 * messagerie d'OVH sont autorisés à écrire au nom de sbd.re, et le `-all`
 * demande le rejet de tout le reste. Un message parti du sendmail local de
 * l'hébergement web serait désavoué par le domaine lui-même.
 *
 * En passant par SMTP authentifié sur une boîte du domaine, les messages sortent
 * des serveurs que le SPF autorise.
 *
 * -----------------------------------------------------------------------------
 * PHPMailer
 * -----------------------------------------------------------------------------
 * Première et seule dépendance tierce du projet, choisie par l'auteur. C'est la
 * bibliothèque que WordPress emploie lui-même, donc un terrain connu pour lui.
 *
 * Version 7.1.1, sous licence LGPL 2.1, déposée à la main dans phpmailer/ :
 * l'hébergement mutualisé n'a pas Composer. Seuls trois de ses sept fichiers
 * sont retenus, ceux qu'exige l'envoi SMTP.
 */

declare(strict_types=1);

namespace SBDRE;

if (!defined('SBDRE_API')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/configuration.php';
require_once __DIR__ . '/phpmailer/Exception.php';
require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';

/**
 * Envoie un message en texte simple.
 *
 * Texte simple et non HTML, à dessein : les deux seuls courriels du site sont
 * un lien de vérification et un lien de réinitialisation. Un message court, sans
 * image ni mise en forme, franchit mieux les filtres anti-spam qu'une carte
 * colorée, et se lit partout.
 *
 * @param  \Closure|null $journal Reçoit la conversation SMTP, pour le script de
 *                                vérification. Laisser null en production.
 * @return array{bool, string}    Succès, et message d'erreur le cas échéant.
 */
function envoyerCourriel(
    string $destinataire,
    string $sujet,
    string $corps,
    ?\Closure $journal = null,
): array {
    $smtp = config_val('smtp');
    $exp  = config_val('courriel');

    foreach (['hote', 'port', 'utilisateur', 'mot_de_passe'] as $clef) {
        if (($smtp[$clef] ?? '') === '' || str_starts_with((string) ($smtp[$clef] ?? ''), 'a-completer')) {
            return [false, "Configuration SMTP incomplète : smtp.$clef."];
        }
    }

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = (string) $smtp['hote'];
        $mail->Port       = (int) $smtp['port'];
        $mail->SMTPAuth   = true;
        $mail->Username   = (string) $smtp['utilisateur'];
        $mail->Password   = (string) $smtp['mot_de_passe'];

        // « ssl » pour le port 465, « tls » pour le 587. Dans les deux cas la
        // connexion est chiffrée : le mot de passe de la boîte ne circule
        // jamais en clair.
        $mail->SMTPSecure = (string) ($smtp['chiffrement'] ?? 'ssl');

        $mail->Timeout    = 20;

        // Sans cette ligne, les accents ressortent en charabia chez une partie
        // des destinataires.
        $mail->CharSet    = 'UTF-8';

        if ($journal !== null) {
            $mail->SMTPDebug   = 2;
            $mail->Debugoutput = $journal;
        }

        $mail->setFrom(
            (string) ($exp['expediteur'] ?? $smtp['utilisateur']),
            (string) ($exp['nom_expediteur'] ?? 'SBD.re'),
        );
        $mail->addAddress($destinataire);
        $mail->Subject = $sujet;
        $mail->Body    = $corps;

        $mail->send();

        return [true, ''];
    } catch (\Throwable $e) {
        // Le message d'origine peut contenir l'identifiant de la boîte. Il va
        // au journal du serveur, jamais dans une réponse HTTP.
        error_log('SBD.re : envoi de courriel impossible. ' . $e->getMessage());

        return [false, $e->getMessage()];
    }
}

/**
 * Masque les identifiants dans une trace SMTP.
 *
 * PHPMailer, en mode bavard, restitue la conversation entière avec le serveur.
 * Or l'authentification y transite en base64 : l'adresse et le MOT DE PASSE de
 * la boîte apparaissent en clair pour qui sait décoder, ce qui est immédiat.
 *
 * Cette fonction remplace ces lignes avant tout affichage. Sans elle, coller la
 * sortie d'un script de diagnostic dans une conversation ou un ticket
 * divulguerait le mot de passe.
 */
function masquerIdentifiants(string $ligne): string
{
    // Une ligne entièrement en base64 est une réponse d'authentification.
    if (preg_match('/^[A-Za-z0-9+\/]{8,}={0,2}$/', trim($ligne)) === 1) {
        return '[identifiant masqué]';
    }

    // Certaines implémentations envoient « AUTH PLAIN <base64> » d'un bloc.
    return (string) preg_replace(
        '/\b(AUTH\s+(?:PLAIN|LOGIN))\s+\S+/i',
        '$1 [masqué]',
        $ligne,
    );
}

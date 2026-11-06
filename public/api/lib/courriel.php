<?php
/**
 * Envoi de courriels par mail(), la fonction du serveur.
 *
 * Un seul endroit construit les en-têtes, pour les formulaires comme pour les
 * comptes. Prouvé en production depuis le 10 septembre 2026 avec le formulaire
 * de partenariat : aucune boîte à créer, aucun mot de passe à déposer.
 *
 * Le 30 juillet, mail() avait été refusée en LIGNE DE COMMANDE (« User loremis
 * is not allowed to submit mail »), d'où le SMTP et PHPMailer. Servie par le
 * web, elle passe. PHPMailer est retiré le 6 novembre 2026.
 *
 * TROIS RÈGLES, éprouvées par contact.php :
 *   - l'expéditeur est une adresse du domaine, pour le SPF ;
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

/** Expéditeur technique. Doit appartenir au domaine, pour le SPF. */
const EXPEDITEUR = 'no-reply@sbd.re';
const NOM_EXPEDITEUR = 'SBD.re';

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
    $sujet = mb_encode_mimeheader(str_replace(["\r", "\n"], ' ', $sujet), 'UTF-8', 'B');

    // quoted_printable_encode ne reconnaît que CRLF comme fin de ligne : un \n
    // seul serait codé =0A, réservé aux données binaires. On unifie avant.
    $corps = quoted_printable_encode(preg_replace('/\r\n|\r|\n/', "\r\n", $corps) ?? $corps);

    return @mail($destinataire, $sujet, $corps, implode("\r\n", $entetes), '-f' . EXPEDITEUR);
}

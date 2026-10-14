<?php

declare(strict_types=1);

/**
 * Boîte à suggestions — deuxième point d'entrée public du site.
 *
 * -----------------------------------------------------------------------------
 * CE QUI CHANGE PAR RAPPORT À contact.php
 * -----------------------------------------------------------------------------
 * UNE SUGGESTION EST ANONYME PAR DÉFAUT. Le message suffit : ni nom, ni adresse
 * exigés. L'adresse reste proposée, pour qui veut une réponse, et elle n'entre
 * dans Reply-To que si elle est valide. Demander une identité pour recevoir une
 * idée, c'est écarter la moitié de ceux qui en ont une.
 *
 * Aucune donnée de session, aucune empreinte, aucun journal : l'adresse IP ne
 * sert qu'au compteur, sous forme de hachage dans un nom de fichier temporaire,
 * et n'est jamais écrite dans le courriel.
 *
 * -----------------------------------------------------------------------------
 * CE QUI NE CHANGE PAS, ET NE DOIT PAS CHANGER
 * -----------------------------------------------------------------------------
 * LE DESTINATAIRE EST ÉCRIT EN DUR, jamais lu depuis la requête : c'est la seule
 * garantie sérieuse contre l'usage du serveur comme relais à spam.
 *
 * AUCUNE DONNÉE DU VISITEUR DANS LES EN-TÊTES. Le sujet vient d'une liste
 * fermée, l'expéditeur est constant, tout le texte libre finit dans le CORPS.
 * Seul Reply-To porte une donnée, validée puis débarrassée de ses retours à la
 * ligne.
 *
 * Les pièges à robots, le compteur verrouillé et l'encodage du corps sont repris
 * de contact.php, où chacun a été éprouvé en production. Le raisonnement complet
 * y est écrit, il n'est pas répété ici.
 */

namespace SBDRE;

// -----------------------------------------------------------------------------
// Réglages.
// -----------------------------------------------------------------------------

/** Boîte de réception. Écrite en dur, jamais lue depuis la requête. */
const DESTINATAIRE = 'hello@loremipsum.re';

/** Expéditeur technique. Doit appartenir au domaine, pour le SPF. */
const EXPEDITEUR = 'no-reply@sbd.re';
const NOM_EXPEDITEUR = 'SBD.re';

/** Natures proposées. Liste FERMÉE : rien d'autre n'entre dans l'en-tête. */
const NATURES = [
    'amelioration' => 'Idée d\'amélioration',
    'donnee'       => 'Donnée fausse ou manquante',
    'technique'    => 'Problème technique',
    'autre'        => 'Autre',
];

/** Limites : cinq envois par heure et par adresse IP. */
const ENVOIS_MAX = 5;
const FENETRE_SECONDES = 3600;

/** Délai minimal entre l'affichage du formulaire et son envoi. */
const DELAI_MINIMAL = 4;
const DELAI_MAXIMAL = 7200;

const LONGUEURS = [
    'courriel' => 190,
    'page'     => 200,
    'message'  => 4000,
];

// -----------------------------------------------------------------------------

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

/** Répond en JSON et s'arrête. Le message est TOUJOURS écrit par nous. */
function repondre(int $code, string $message, bool $ok = false): never
{
    http_response_code($code);
    echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    repondre(405, 'Méthode non autorisée.');
}

$origine = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origine !== '' && !in_array($origine, ['https://sbd.re', 'https://www.sbd.re'], true)) {
    repondre(403, 'Origine non autorisée.');
}

// -----------------------------------------------------------------------------
// Limitation par adresse IP, en fichier verrouillé. Voir contact.php.
// -----------------------------------------------------------------------------

$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'inconnue');
$compteur = @fopen(sys_get_temp_dir() . '/sbdre-suggestion-' . sha1($ip) . '.txt', 'c+');

if ($compteur !== false && !flock($compteur, LOCK_EX)) {
    fclose($compteur);
    $compteur = false;
}

$horodatages = [];
if ($compteur !== false) {
    $horodatages = array_values(array_filter(
        array_map('intval', explode(',', (string) stream_get_contents($compteur))),
        static fn (int $t): bool => $t > time() - FENETRE_SECONDES,
    ));
}

if (count($horodatages) >= ENVOIS_MAX) {
    repondre(429, 'Trop de suggestions envoyées. Réessayez dans une heure.');
}

// -----------------------------------------------------------------------------
// Les pièges à robots.
// -----------------------------------------------------------------------------

if (trim((string) ($_POST['site'] ?? '')) !== '') {
    // On répond comme si tout allait bien : signaler le piège l'apprendrait.
    repondre(200, 'Suggestion envoyée.', true);
}

$affiche = (int) ($_POST['depuis'] ?? 0);
$ecoule = time() - $affiche;
if ($affiche <= 0 || $ecoule < DELAI_MINIMAL || $ecoule > DELAI_MAXIMAL) {
    repondre(422, 'Formulaire expiré. Rechargez la page et réessayez.');
}

// -----------------------------------------------------------------------------
// Validation. Tout est refusé par défaut.
// -----------------------------------------------------------------------------

/** Nettoie une valeur d'une ligne : espaces, caractères de contrôle, longueur. */
function ligne(string $valeur, int $max): string
{
    $valeur = str_replace(["\r", "\n", "\0"], ' ', $valeur);
    $valeur = trim(preg_replace('/\s+/u', ' ', $valeur) ?? '');
    return mb_substr($valeur, 0, $max);
}

$courriel = ligne((string) ($_POST['courriel'] ?? ''), LONGUEURS['courriel']);
$page = ligne((string) ($_POST['page'] ?? ''), LONGUEURS['page']);
$cleNature = (string) ($_POST['nature'] ?? '');
$message = mb_substr(trim((string) ($_POST['message'] ?? '')), 0, LONGUEURS['message']);

if (!array_key_exists($cleNature, NATURES)) {
    repondre(422, 'Choisissez une nature dans la liste.');
}
if (mb_strlen($message) < 20) {
    repondre(422, 'Décrivez votre idée en quelques phrases.');
}

/*
 * L'ADRESSE EST FACULTATIVE, MAIS PAS APPROXIMATIVE. Laissée vide, la
 * suggestion part anonymement. Renseignée, elle doit être valide : une adresse
 * fausse donne l'illusion d'une réponse qui n'arrivera jamais.
 */
$courrielValide = $courriel !== '' && filter_var($courriel, FILTER_VALIDATE_EMAIL) !== false;
if ($courriel !== '' && !$courrielValide) {
    repondre(422, 'Cette adresse électronique semble incorrecte. Laissez le champ vide pour rester anonyme.');
}

// -----------------------------------------------------------------------------
// Envoi.
// -----------------------------------------------------------------------------

$corps = implode("\n", [
    'Suggestion reçue depuis sbd.re.',
    '',
    'Nature   : ' . NATURES[$cleNature],
    'Page     : ' . ($page !== '' ? $page : '(non précisée)'),
    'Réponse  : ' . ($courrielValide ? $courriel : '(anonyme, aucune réponse possible)'),
    'Date     : ' . date('d/m/Y à H:i'),
    '',
    '--- Suggestion ---',
    '',
    $message,
]);

$listeEntetes = [
    'From: ' . NOM_EXPEDITEUR . ' <' . EXPEDITEUR . '>',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: quoted-printable',
    'MIME-Version: 1.0',
    'X-Mailer: sbd.re',
];

/*
 * Reply-To n'est posé QUE si une adresse valide a été donnée. Sur une
 * suggestion anonyme, l'en-tête est simplement absent : y mettre l'expéditeur
 * technique ferait croire à une réponse possible.
 */
if ($courrielValide) {
    array_splice($listeEntetes, 1, 0, 'Reply-To: ' . $courriel);
}

$entetes = implode("\r\n", $listeEntetes);
$sujet = mb_encode_mimeheader('[sbd.re] ' . NATURES[$cleNature], 'UTF-8', 'B');
$corps = quoted_printable_encode(preg_replace('/\r\n|\r|\n/', "\r\n", $corps) ?? $corps);

/*
 * -----------------------------------------------------------------------------
 * CONSERVATION, AVANT L'ENVOI
 * -----------------------------------------------------------------------------
 * Une suggestion qui ne part pas est une suggestion perdue. `mail()` échoue
 * parfois sans rien dire d'exploitable, et la boîte de réception n'est pas un
 * registre : elle se trie, s'archive et s'efface. La demande est donc écrite
 * sur le serveur AVANT la tentative d'envoi, dans un fichier que le web ne sert
 * pas, protégé par son propre .htaccess.
 *
 * CE QUI N'Y ENTRE PAS, et c'est délibéré : aucune adresse IP, aucun en-tête de
 * requête, aucun identifiant de session. Le fichier contient ce que la personne
 * a écrit, rien de plus. Son adresse n'y figure que si elle l'a donnée.
 *
 * L'ÉCHEC D'ÉCRITURE NE BLOQUE RIEN. Un disque plein ou un droit manquant ne
 * doit pas fermer la seule porte de suggestion du site : on continue vers
 * l'envoi, qui reste le chemin principal.
 */
$journal = __DIR__ . '/journal/suggestions.jsonl';
@file_put_contents(
    $journal,
    json_encode([
        'date'     => date('c'),
        'nature'   => $cleNature,
        'page'     => $page !== '' ? $page : null,
        'courriel' => $courrielValide ? $courriel : null,
        'message'  => $message,
    ], JSON_UNESCAPED_UNICODE) . "\n",
    FILE_APPEND | LOCK_EX,
);

/* Le compteur monte AVANT l'envoi : voir le raisonnement dans contact.php. */
if ($compteur !== false) {
    $horodatages[] = time();
    ftruncate($compteur, 0);
    rewind($compteur);
    fwrite($compteur, implode(',', $horodatages));
    fflush($compteur);
    flock($compteur, LOCK_UN);
    fclose($compteur);
}

$envoye = @mail(DESTINATAIRE, $sujet, $corps, $entetes, '-f' . EXPEDITEUR);

if (!$envoye) {
    /*
     * La suggestion est déjà conservée sur le serveur : elle n'est pas perdue,
     * seule la notification a échoué. On le dit sans promettre une lecture
     * immédiate, et on laisse une adresse de repli.
     */
    repondre(
        500,
        "Votre suggestion est enregistrée, mais la notification a échoué. "
        . "Écrivez à hello@loremipsum.re si elle est urgente.",
    );
}

repondre(200, 'Suggestion envoyée. Merci du coup de main.', true);

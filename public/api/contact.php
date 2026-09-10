<?php

declare(strict_types=1);

/**
 * Formulaire de partenariat — PREMIER point d'entrée public du site.
 *
 * -----------------------------------------------------------------------------
 * CE QUI PROTÈGE CE FICHIER, ET POURQUOI
 * -----------------------------------------------------------------------------
 * Jusqu'ici public/api/ ne contenait que des bibliothèques, toutes en 403.
 * Ouvrir une porte change la nature du site : tout ce qui suit existe pour que
 * cette porte ne devienne pas un relais à spam ni une fuite.
 *
 * LE DESTINATAIRE EST ÉCRIT EN DUR. Jamais lu depuis la requête. C'est la seule
 * garantie sérieuse contre l'usage du serveur comme relais : même en trafiquant
 * chaque champ, on ne peut faire partir un message ailleurs que chez nous.
 *
 * AUCUNE DONNÉE DU VISITEUR DANS LES EN-TÊTES. Le sujet est construit à partir
 * d'une liste fermée, l'expéditeur est constant. Tout ce que la personne écrit
 * finit dans le CORPS, jamais dans un en-tête, ce qui ferme l'injection
 * d'en-têtes. La seule exception est Reply-To, et l'adresse y est validée puis
 * débarrassée de tout retour à la ligne.
 *
 * ENVOI PAR LE SERVEUR, sans SMTP authentifié : aucune boîte à créer, aucun mot
 * de passe à déposer. En contrepartie l'expéditeur est une adresse technique du
 * domaine, ce qui est nécessaire pour que le SPF d'OVH reconnaisse l'envoi.
 */

namespace SBDRE;

// -----------------------------------------------------------------------------
// Réglages. Le destinataire et l'expéditeur ne se négocient pas depuis dehors.
// -----------------------------------------------------------------------------

/**
 * Boîte de réception des demandes.
 *
 * Provisoirement chez loremipsum.re : aucune boîte n'existe encore sur sbd.re,
 * et une adresse qui ne reçoit pas est pire qu'un formulaire absent. À basculer
 * vers sbd.re le jour où la boîte y sera créée.
 *
 * Écrite en dur, jamais lue depuis la requête : c'est la seule garantie
 * sérieuse contre l'usage du serveur comme relais à spam.
 */
const DESTINATAIRE = 'hello@loremipsum.re';

/** Expéditeur technique. Doit appartenir au domaine, pour le SPF. */
const EXPEDITEUR = 'no-reply@sbd.re';
const NOM_EXPEDITEUR = 'SBD.re';

/** Sujets proposés. Une liste FERMÉE : rien d'autre n'entre dans l'en-tête. */
const SUJETS = [
    'partenariat'  => 'Proposition de partenariat',
    'produit'      => 'Compléments ou équipement',
    'evenement'    => 'Compétition ou événement',
    'autre'        => 'Autre demande',
];

/** Limites : trois envois par heure et par adresse IP. */
const ENVOIS_MAX = 3;
const FENETRE_SECONDES = 3600;

/** Délai minimal entre l'affichage du formulaire et son envoi. */
const DELAI_MINIMAL = 4;
const DELAI_MAXIMAL = 7200;

const LONGUEURS = [
    'nom'          => 100,
    'organisation' => 120,
    'courriel'     => 190,
    'message'      => 4000,
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

/*
 * Le formulaire vit sur sbd.re et nulle part ailleurs. Ce contrôle n'arrête pas
 * un script déterminé, qui forge ce qu'il veut, mais il écarte les envois
 * automatiques déclenchés depuis une autre page, qui sont le gros du bruit.
 */
$origine = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origine !== '' && !in_array($origine, ['https://sbd.re', 'https://www.sbd.re'], true)) {
    repondre(403, 'Origine non autorisée.');
}

// -----------------------------------------------------------------------------
// Limitation par adresse IP, en fichier.
//
// Dans le dossier temporaire du système et non sous public/ : un compteur
// accessible par le web se lit, et pire, se vide.
// -----------------------------------------------------------------------------

$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'inconnue');
$compteur = sys_get_temp_dir() . '/sbdre-contact-' . sha1($ip) . '.txt';

$horodatages = [];
if (is_readable($compteur)) {
    $horodatages = array_filter(
        array_map('intval', explode(',', (string) file_get_contents($compteur))),
        static fn (int $t): bool => $t > time() - FENETRE_SECONDES,
    );
}

if (count($horodatages) >= ENVOIS_MAX) {
    repondre(429, 'Trop de messages envoyés. Réessayez dans une heure.');
}

// -----------------------------------------------------------------------------
// Les pièges à robots.
// -----------------------------------------------------------------------------

/*
 * Champ appât, invisible pour un humain et rempli par un robot qui complète
 * tout ce qu'il trouve. Il s'appelle « site » parce que c'est le genre de nom
 * qui attire, et non « piege » qui se contourne d'un coup d'œil.
 */
if (trim((string) ($_POST['site'] ?? '')) !== '') {
    // On répond comme si tout allait bien : signaler le piège l'apprendrait.
    repondre(200, 'Message envoyé.', true);
}

/*
 * Un formulaire rempli en moins de quatre secondes n'a pas été lu. Au-delà de
 * deux heures, la page traînait dans un onglet et l'envoi est probablement
 * automatique.
 */
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

$nom = ligne((string) ($_POST['nom'] ?? ''), LONGUEURS['nom']);
$organisation = ligne((string) ($_POST['organisation'] ?? ''), LONGUEURS['organisation']);
$courriel = ligne((string) ($_POST['courriel'] ?? ''), LONGUEURS['courriel']);
$cleSujet = (string) ($_POST['sujet'] ?? '');
$message = mb_substr(trim((string) ($_POST['message'] ?? '')), 0, LONGUEURS['message']);

if ($nom === '' || mb_strlen($nom) < 2) {
    repondre(422, 'Indiquez votre nom.');
}
if (!filter_var($courriel, FILTER_VALIDATE_EMAIL)) {
    repondre(422, 'Cette adresse électronique semble incorrecte.');
}
if (!array_key_exists($cleSujet, SUJETS)) {
    repondre(422, 'Choisissez un objet dans la liste.');
}
if (mb_strlen($message) < 20) {
    repondre(422, 'Détaillez un peu votre demande, en quelques phrases.');
}

// -----------------------------------------------------------------------------
// Envoi.
// -----------------------------------------------------------------------------

$corps = implode("\n", [
    'Demande reçue depuis le formulaire de partenariat de sbd.re.',
    '',
    'Nom          : ' . $nom,
    'Organisation : ' . ($organisation !== '' ? $organisation : '(non renseignée)'),
    'Courriel     : ' . $courriel,
    'Objet        : ' . SUJETS[$cleSujet],
    'Date         : ' . date('d/m/Y à H:i'),
    '',
    '--- Message ---',
    '',
    $message,
    '',
    '---',
    "Répondre à ce courriel écrit directement à l'expéditeur.",
]);

/*
 * Reply-To est le SEUL en-tête qui contient une donnée du visiteur, et elle a
 * passé FILTER_VALIDATE_EMAIL puis le nettoyage de `ligne()`. Le nom, lui,
 * n'entre pas dans l'en-tête : un nom est du texte libre, et du texte libre
 * dans un en-tête est une injection en attente.
 */
$entetes = implode("\r\n", [
    'From: ' . NOM_EXPEDITEUR . ' <' . EXPEDITEUR . '>',
    'Reply-To: ' . $courriel,
    'Content-Type: text/plain; charset=UTF-8',
    'MIME-Version: 1.0',
    'X-Mailer: sbd.re',
]);

$sujet = '[sbd.re] ' . SUJETS[$cleSujet];

/*
 * Le compteur monte AVANT l'envoi, et non après.
 *
 * Le compter après paraissait logique — n'facturer que ce qui part — mais
 * laissait une porte ouverte : si l'envoi échoue, par panne du serveur de
 * courriel par exemple, rien n'est retenu et la même requête peut être rejouée
 * sans limite. On compte donc les demandes qui ont PASSÉ LA VALIDATION, ce qui
 * est la ressource à protéger. Les requêtes malformées, elles, sont rejetées
 * avant et ne consomment rien.
 */
$horodatages[] = time();
@file_put_contents($compteur, implode(',', $horodatages), LOCK_EX);

$envoye = @mail(DESTINATAIRE, $sujet, $corps, $entetes, '-f' . EXPEDITEUR);

if (!$envoye) {
    /*
     * On ne dit pas POURQUOI : le détail renseignerait autant un attaquant
     * qu'un visiteur. L'adresse de repli permet à la personne d'aboutir quand
     * même, ce qui est le seul but.
     */
    repondre(500, "L'envoi a échoué. Écrivez directement à hello@loremipsum.re.");
}

repondre(200, 'Message envoyé. Réponse sous quelques jours.', true);

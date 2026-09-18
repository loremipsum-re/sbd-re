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
//
// Le fichier reste VERROUILLÉ de la lecture jusqu'à l'écriture. Sans verrou,
// des envois simultanés lisaient tous le même état avant que l'un d'eux
// n'écrive : mesuré, une salve de dix envois en faisait passer cinq au lieu
// de trois. Le verrou les met en file, chacun lit l'état laissé par le
// précédent. Il se libère seul à la fin du script, quelle que soit la sortie.
//
// Si le fichier ne s'ouvre pas, le formulaire fonctionne sans limite plutôt
// que de refuser tout le monde : une panne du compteur ne doit pas fermer la
// seule porte de contact du site.
// -----------------------------------------------------------------------------

$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'inconnue');
$compteur = @fopen(sys_get_temp_dir() . '/sbdre-contact-' . sha1($ip) . '.txt', 'c+');

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
    'Content-Transfer-Encoding: quoted-printable',
    'MIME-Version: 1.0',
    'X-Mailer: sbd.re',
]);

/*
 * Un en-tête de courriel ne contient que de l'ASCII. Trois objets sur quatre
 * portent des accents, qui partaient en octets bruts : sujet illisible chez
 * certains destinataires, et point de spam en plus. mb_encode_mimeheader les
 * écrit sous la forme codée =?UTF-8?B?...?= que tout client décode.
 */
$sujet = mb_encode_mimeheader('[sbd.re] ' . SUJETS[$cleSujet], 'UTF-8', 'B');

/*
 * Le corps passe en quoted-printable, pour deux raisons mesurées.
 *
 * La norme plafonne une ligne à 998 octets. Un paragraphe tapé sans retour à
 * la ligne en faisait 1 619, que les serveurs de courriel coupent à leur façon.
 * Le quoted-printable replie à 76 caractères, et le client recolle les lignes.
 *
 * Les accents deviennent de l'ASCII (é devient =C3=A9), ce qui rend l'encodage
 * déclaré exact.
 *
 * PIÈGE : quoted_printable_encode ne reconnaît que CRLF comme fin de ligne. Un
 * \n seul est codé =0A, un codage que la norme réserve aux données binaires :
 * dans du texte, elle exige de vrais retours CRLF. Le corps est assemblé avec
 * \n et le textarea envoie du CRLF : on unifie avant.
 */
$corps = quoted_printable_encode(preg_replace('/\r\n|\r|\n/', "\r\n", $corps) ?? $corps);

/*
 * Le compteur monte AVANT l'envoi, et non après.
 *
 * Le compter après paraissait logique, pour ne compter que ce qui part, mais
 * laissait une porte ouverte : si l'envoi échoue, par panne du serveur de
 * courriel par exemple, rien n'est retenu et la même requête peut être rejouée
 * sans limite. On compte donc les demandes qui ont PASSÉ LA VALIDATION, ce qui
 * est la ressource à protéger. Les requêtes malformées, elles, sont rejetées
 * avant et ne consomment rien.
 *
 * Le verrou est relâché juste après l'écriture, avant mail() : l'envoi peut
 * prendre une seconde, et rien ne justifie de faire attendre les autres.
 */
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
     * On ne dit pas POURQUOI : le détail renseignerait autant un attaquant
     * qu'un visiteur. L'adresse de repli permet à la personne d'aboutir quand
     * même, ce qui est le seul but.
     */
    repondre(500, "L'envoi a échoué. Écrivez directement à hello@loremipsum.re.");
}

repondre(200, 'Message envoyé. Réponse sous quelques jours.', true);

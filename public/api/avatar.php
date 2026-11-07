<?php
/**
 * Les avatars (docs/forum.md, section 7).
 *
 *   GET    /avatars/<pseudo>.webp   l'image, réécrite ici par le .htaccess
 *   POST   champ `image`, en multipart : déposer ou remplacer le sien
 *   DELETE                          retirer le sien
 *
 * L'image envoyée passe par preparerAvatar() (lib/avatar.php) : recadrée,
 * 300 × 300, réencodée en WebP, sans ses métadonnées. Jamais gardée telle
 * quelle.
 */

declare(strict_types=1);

namespace SBDRE;

define('SBDRE_API', true);
require __DIR__ . '/lib/comptes.php';
require __DIR__ . '/lib/avatar.php';

$methode = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($methode === 'GET' || $methode === 'HEAD') {
    header('X-Content-Type-Options: nosniff');
    $pseudo = (string) ($_GET['pseudo'] ?? '');
    $avatar = pseudoValide($pseudo) ? lireAvatar($pseudo) : null;
    if ($avatar === null) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: image/webp');
    // L'adresse porte la version (?v=) : un avatar changé change d'adresse,
    // l'ancienne peut se garder longtemps.
    header('Cache-Control: public, max-age=' . (isset($_GET['v']) ? '31536000, immutable' : '3600'));
    header('Content-Length: ' . strlen((string) $avatar['image']));
    if ($methode === 'GET') {
        echo $avatar['image'];
    }
    exit;
}

ouvrirApi(['POST', 'DELETE']);
$membre = exigerActif();

if ($methode === 'DELETE') {
    supprimerAvatar((int) $membre['id']);
    repondreOk('Avatar retiré.');
}

$fichier = $_FILES['image'] ?? null;
if (!is_array($fichier) || ($fichier['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $fichier['tmp_name'])) {
    refuser(422, 'Image absente, ou de plus de 2 Mo.');
}
$webp = preparerAvatar((string) file_get_contents((string) $fichier['tmp_name']));
if ($webp === null) {
    refuser(422, 'Image refusée : JPEG, PNG ou WebP, de ' . AVATAR_COTE_MIN . ' à ' . AVATAR_COTE_MAX . ' pixels de côté, 2 Mo au plus.');
}
enregistrerAvatar((int) $membre['id'], $webp);

$version = ligne('SELECT UNIX_TIMESTAMP(date_maj) AS v FROM avatar WHERE membre_id = :m', ['m' => $membre['id']]);
repondreOk('Avatar enregistré.', ['adresse' => adresseAvatar((string) $membre['pseudo'], (int) ($version['v'] ?? time()))]);

<?php
/**
 * Les avatars (docs/forum.md, section 7) : la première image que le site
 * reçoit d'un visiteur.
 *
 * L'image envoyée n'est JAMAIS gardée telle quelle. GD la décode, la recadre
 * au centre en carré, la réduit à 300 × 300 et la réencode en WebP : ses
 * métadonnées (la position GPS d'une photo de téléphone, par exemple) ne
 * passent pas, et un fichier piégé qui se dirait image n'arrive pas en base.
 * Formats admis : JPEG, PNG, WebP. Le GIF est refusé, animé ou non.
 *
 * Gardée en base (table `avatar`) : le déploiement efface du serveur tout
 * fichier absent du site généré. api/avatar.php la sert.
 */

declare(strict_types=1);

namespace SBDRE;

if (!defined('SBDRE_API')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/bdd.php';

const AVATAR_COTE = 300;
const AVATAR_COTE_MIN = 150;
/** Au-delà, le décodage seul coûterait trop de mémoire sur un mutualisé. */
const AVATAR_COTE_MAX = 4000;
const AVATAR_OCTETS_MAX = 2_000_000;
const AVATAR_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

/**
 * L'image prête à entrer en base, en WebP 300 × 300, ou null si elle ne
 * convient pas. Les dimensions se lisent AVANT le décodage.
 */
function preparerAvatar(string $octets): ?string
{
    if ($octets === '' || strlen($octets) > AVATAR_OCTETS_MAX || !function_exists('imagewebp')) {
        return null;
    }
    $infos = @getimagesizefromstring($octets);
    if ($infos === false || !in_array($infos['mime'] ?? '', AVATAR_TYPES, true)) {
        return null;
    }
    [$largeur, $hauteur] = $infos;
    if (min($largeur, $hauteur) < AVATAR_COTE_MIN || max($largeur, $hauteur) > AVATAR_COTE_MAX) {
        return null;
    }
    $source = @imagecreatefromstring($octets);
    if ($source === false) {
        return null;
    }

    // Une photo de téléphone dit son sens dans ses métadonnées, que GD ignore :
    // sans ce redressement, un portrait arriverait couché.
    if ($infos['mime'] === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data('data://image/jpeg;base64,' . base64_encode($octets));
        $angle = [3 => 180, 6 => -90, 8 => 90][(int) ($exif['Orientation'] ?? 1)] ?? 0;
        if ($angle !== 0) {
            $source = imagerotate($source, $angle, 0) ?: $source;
            [$largeur, $hauteur] = [imagesx($source), imagesy($source)];
        }
    }

    $cote = min($largeur, $hauteur);
    $carre = imagecreatetruecolor(AVATAR_COTE, AVATAR_COTE);
    imagealphablending($carre, false);
    imagesavealpha($carre, true);
    imagecopyresampled(
        $carre, $source, 0, 0,
        intdiv($largeur - $cote, 2), intdiv($hauteur - $cote, 2),
        AVATAR_COTE, AVATAR_COTE, $cote, $cote,
    );

    ob_start();
    imagewebp($carre, null, 82);
    $webp = (string) ob_get_clean();

    return $webp !== '' && strlen($webp) <= 200000 ? $webp : null;
}

function enregistrerAvatar(int $membreId, string $webp): void
{
    // PARAM_LOB : des octets, pas du texte, quel que soit le jeu de caractères.
    $requete = bdd()->prepare(
        'INSERT INTO avatar (membre_id, image, date_maj) VALUES (:m, :i, NOW()) AS nouveau
         ON DUPLICATE KEY UPDATE image = nouveau.image, date_maj = nouveau.date_maj',
    );
    $requete->bindValue('m', $membreId, \PDO::PARAM_INT);
    $requete->bindValue('i', $webp, \PDO::PARAM_LOB);
    $requete->execute();
}

function supprimerAvatar(int $membreId): void
{
    executer('DELETE FROM avatar WHERE membre_id = :m', ['m' => $membreId]);
}

/** L'image d'un membre visible, et sa version pour le cache. */
function lireAvatar(string $pseudo): ?array
{
    return ligne(
        'SELECT a.image, UNIX_TIMESTAMP(a.date_maj) AS version
           FROM avatar a JOIN membre m ON m.id = a.membre_id
          WHERE m.pseudo = :p AND m.banni = 0 AND m.courriel_verifie = 1',
        ['p' => $pseudo],
    );
}

/** L'adresse de l'avatar, la version au bout : un avatar changé se recharge. */
function adresseAvatar(string $pseudo, int $version): string
{
    return '/avatars/' . $pseudo . '.webp?v=' . $version;
}

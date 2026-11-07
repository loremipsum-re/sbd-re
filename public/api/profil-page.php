<?php
/**
 * Un profil public, à /membres/<pseudo>/.
 *
 * Le .htaccess racine envoie l'adresse ici, `?pseudo=<pseudo>`. Ce fichier lit
 * le membre en base, le gabarit d'Astro sur le disque, et rend la page avec
 * lib/profil.php. Seul un compte confirmé et non banni a un profil : les
 * autres répondent la page 404 du site.
 *
 * L'adresse canonique est en minuscules, comme le pseudo : /membres/Jean/
 * renvoie vers /membres/jean/, pour qu'un profil n'ait qu'une adresse.
 */

declare(strict_types=1);

namespace SBDRE;

define('SBDRE_API', true);
require __DIR__ . '/lib/profil.php';

header('X-Content-Type-Options: nosniff');

/** La page 404 du site, avec le bon code. */
function introuvable(): never
{
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    readfile(dirname(__DIR__) . '/404.html');
    exit;
}

/** Une panne, dite sans détail : le détail va au journal. */
function indisponible(string $journal): never
{
    error_log('SBD.re : profil indisponible, ' . $journal);
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    header('Retry-After: 300');
    echo "Ce profil est momentanément indisponible. Réessayez dans quelques minutes.\n";
    exit;
}

$methode = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($methode, ['GET', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD');
    exit;
}

$demande = (string) ($_GET['pseudo'] ?? '');
$pseudo = mb_strtolower($demande);

if (!pseudoValide($pseudo)) {
    introuvable();
}
if ($pseudo !== $demande) {
    header('Location: /membres/' . $pseudo . '/', true, 301);
    exit;
}

try {
    $membre = ligne(
        'SELECT id, pseudo, bio, salle, instagram, instagram_visible, date_inscription
           FROM membre
          WHERE pseudo = :p AND courriel_verifie = 1 AND banni = 0',
        ['p' => $pseudo],
    );
    if ($membre === null) {
        // Un ancien pseudo renvoie vers le profil sous son nouveau nom : un
        // lien partagé avant le changement continue de mener quelque part.
        $renomme = ligne(
            'SELECT pseudo FROM membre WHERE pseudo_precedent = :p AND courriel_verifie = 1 AND banni = 0',
            ['p' => $pseudo],
        );
        if ($renomme !== null) {
            header('Location: /membres/' . $renomme['pseudo'] . '/', true, 301);
            exit;
        }
        introuvable();
    }
    $performances = performancesAvecVotes('p.membre_id = :id', ['id' => $membre['id']]);
} catch (\RuntimeException | \PDOException $e) {
    indisponible($e->getMessage());
}

$gabarit = @file_get_contents(dirname(__DIR__) . '/membres/-gabarit/index.html');
if ($gabarit === false) {
    indisponible('gabarit introuvable');
}

// Les salles viennent du site lui-même, générées avec lui. Absentes, le
// profil s'affiche sans sa salle plutôt que pas du tout.
$salles = json_decode((string) @file_get_contents(__DIR__ . '/salles.json'), true);

header('Content-Type: text/html; charset=utf-8');
// Comme toute page HTML du site : revérifiée à chaque visite, pour qu'un
// profil modifié se voie aussitôt.
header('Cache-Control: public, max-age=0, must-revalidate');

if ($methode === 'HEAD') {
    exit;
}

echo rendreProfil($membre, $gabarit, is_array($salles) ? $salles : [], $performances);

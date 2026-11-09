<?php
/**
 * L'annuaire des membres, à /membres/, `?page=` et `?q=` (lib/annuaire.php).
 *
 * Le .htaccess racine envoie /membres/ ici. Une page au-delà de la dernière
 * répond 404, comme sur le forum.
 */

declare(strict_types=1);

namespace SBDRE;

define('SBDRE_API', true);
require __DIR__ . '/lib/annuaire.php';

header('X-Content-Type-Options: nosniff');

$methode = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($methode, ['GET', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD');
    exit;
}

$demandee = trim((string) ($_GET['q'] ?? ''));
$q = rechercheMembre($demandee);
$illisible = $demandee !== '' && $q === '';
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

$introuvable = static function (): never {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    readfile(dirname(__DIR__) . '/404.html');
    exit;
};
// La recherche tient sur une page : `?q=` et `?page=` ne se combinent pas.
if ($page === false || ($q !== '' && $page > 1)) {
    $introuvable();
}

try {
    ['membres' => $membres, 'total' => $total] = $illisible ? ['membres' => [], 'total' => 0] : membresAnnuaire($page, $q);
} catch (\RuntimeException | \PDOException $e) {
    error_log('SBD.re : annuaire indisponible, ' . $e->getMessage());
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    header('Retry-After: 300');
    echo "L'annuaire est momentanément indisponible. Réessayez dans quelques minutes.\n";
    exit;
}
if ($page > 1 && $membres === []) {
    $introuvable();
}

$gabarit = @file_get_contents(dirname(__DIR__) . '/membres/-annuaire/index.html');
if ($gabarit === false) {
    error_log('SBD.re : annuaire indisponible, gabarit introuvable');
    http_response_code(503);
    exit;
}
$salles = json_decode((string) @file_get_contents(__DIR__ . '/salles.json'), true);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: public, max-age=0, must-revalidate');
if ($methode === 'HEAD') {
    exit;
}

echo rendreAnnuaire($gabarit, $membres, $total, $page, $q, is_array($salles) ? $salles : [], $illisible);

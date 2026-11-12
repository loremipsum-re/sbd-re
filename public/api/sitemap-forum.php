<?php
/**
 * Le plan du forum, /sitemap-forum.xml, réécrit ici par le .htaccess et
 * ajouté à l'index du site par `customSitemaps` (astro.config.mjs), comme le
 * plan des profils.
 *
 * L'accueil, chaque forum visible, et chaque sujet, daté de son dernier
 * message : un moteur de recherche revient d'abord là où la discussion vit.
 * Le fil n'y est pas : il ne fait que reprendre ces pages.
 */

declare(strict_types=1);

namespace SBDRE;

define('SBDRE_API', true);
require_once __DIR__ . '/lib/bdd.php';

header('X-Content-Type-Options: nosniff');

try {
    $forums = lignes(
        "SELECT f.adresse, DATE(COALESCE(MAX(s.date_dernier), f.date_creation)) AS maj
           FROM forum f LEFT JOIN sujet s ON s.forum_id = f.id
          WHERE f.etat <> 'masque'
          GROUP BY f.id
          ORDER BY f.ordre, f.id",
    );
    $sujets = lignes(
        "SELECT s.id, f.adresse, DATE(s.date_dernier) AS maj
           FROM sujet s JOIN forum f ON f.id = s.forum_id
          WHERE f.etat <> 'masque'
          ORDER BY s.date_dernier DESC
          LIMIT 45000",
    );
} catch (\RuntimeException | \PDOException $e) {
    error_log('SBD.re : plan du forum indisponible, ' . $e->getMessage());
    http_response_code(503);
    header('Retry-After: 3600');
    exit;
}

header('Content-Type: application/xml; charset=utf-8');

// Les adresses ont un format contrôlé, a-z 0-9 et tirets : rien à échapper.
echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', "\n";
echo "  <url><loc>https://sbd.re/forum/</loc></url>\n";
foreach ($forums as $f) {
    printf("  <url><loc>https://sbd.re/forum/%s/</loc><lastmod>%s</lastmod></url>\n", $f['adresse'], $f['maj']);
}
foreach ($sujets as $s) {
    printf("  <url><loc>https://sbd.re/forum/%s/%d/</loc><lastmod>%s</lastmod></url>\n", $s['adresse'], (int) $s['id'], $s['maj']);
}
echo '</urlset>', "\n";

<?php
/**
 * Le plan de site des profils, servi à /sitemap-membres.xml par le .htaccess,
 * et déclaré dans /sitemap-index.xml par `customSitemaps` d'astro.config.mjs.
 *
 * L'annuaire, /membres/, vient en tête, daté de la dernière inscription.
 *
 * N'y entrent que les profils qu'un moteur peut indexer : confirmés, non
 * bannis, et porteurs d'au moins une performance. Les autres portent
 * `noindex`, les lister contredirait la page. La date est celle de la
 * dernière performance, ce qui change réellement le profil.
 */

declare(strict_types=1);

namespace SBDRE;

define('SBDRE_API', true);
require __DIR__ . '/lib/bdd.php';

header('X-Content-Type-Options: nosniff');

try {
    $profils = lignes(
        'SELECT m.pseudo, MAX(p.date_creation) AS maj
           FROM membre m
           JOIN performance p ON p.membre_id = m.id
          WHERE m.courriel_verifie = 1 AND m.banni = 0
          GROUP BY m.id, m.pseudo
          ORDER BY m.pseudo',
    );
    $inscription = ligne('SELECT MAX(date_inscription) AS d FROM membre WHERE courriel_verifie = 1 AND banni = 0')['d'] ?? null;
} catch (\RuntimeException | \PDOException $e) {
    error_log('SBD.re : plan des profils indisponible, ' . $e->getMessage());
    http_response_code(503);
    header('Retry-After: 3600');
    exit;
}

header('Content-Type: application/xml; charset=utf-8');

echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', "\n";
printf(
    "  <url><loc>https://sbd.re/membres/</loc><lastmod>%s</lastmod></url>\n",
    date('Y-m-d', strtotime((string) $inscription) ?: time()),
);
foreach ($profils as $profil) {
    // Le pseudo a un format contrôlé, a-z 0-9 . _ : rien à échapper en XML.
    printf(
        "  <url><loc>https://sbd.re/membres/%s/</loc><lastmod>%s</lastmod></url>\n",
        $profil['pseudo'],
        date('Y-m-d', strtotime((string) $profil['maj']) ?: time()),
    );
}
echo '</urlset>', "\n";

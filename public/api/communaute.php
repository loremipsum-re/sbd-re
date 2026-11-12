<?php
/**
 * La vitrine de /communaute/ (lib/vitrine.php), ouverte à tous :
 *
 *   GET   les dernières barres publiées, et le top des membres par mouvement
 *
 * Rien que des données déjà publiques sur les profils : pseudo, mouvement,
 * charge, date, vérifiée ou non. Cinq minutes de cache dans le navigateur.
 */

declare(strict_types=1);

namespace SBDRE;

define('SBDRE_API', true);
require_once __DIR__ . '/lib/comptes.php';
require_once __DIR__ . '/lib/vitrine.php';

ouvrirApi(['GET']);

header('Cache-Control: public, max-age=300');
repondreOk('', vitrine());

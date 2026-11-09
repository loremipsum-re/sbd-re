<?php
/**
 * Les pseudos qui commencent par ce qu'un membre tape après « @ », pour les
 * suggestions du forum et du chat (src/lib/suggestions-pseudo.ts).
 *
 *   GET ?debut=<1 à 30 caractères>   huit pseudos au plus, membres actifs
 *
 * Réservé aux membres actifs : seul qui écrit en a l'usage. L'annuaire,
 * /membres/, reste la liste publique.
 */

declare(strict_types=1);

namespace SBDRE;

define('SBDRE_API', true);
require __DIR__ . '/lib/comptes.php';

ouvrirApi(['GET']);
exigerActif();

const SUGGESTIONS_MAX = 8;

$debut = mb_strtolower(trim((string) ($_GET['debut'] ?? '')));
if (preg_match('/^[a-z0-9._]{1,30}$/', $debut) !== 1) {
    repondreOk('', ['pseudos' => []]);
}

// Une minute dans le navigateur : retaper la même lettre ne réinterroge pas.
header('Cache-Control: private, max-age=60');
repondreOk('', ['pseudos' => array_column(lignes(
    'SELECT pseudo FROM membre WHERE courriel_verifie = 1 AND banni = 0 AND pseudo LIKE :d ORDER BY pseudo LIMIT ' . SUGGESTIONS_MAX,
    // « _ » est un joker de LIKE, et il est permis dans un pseudo.
    ['d' => addcslashes($debut, '%_\\') . '%'],
), 'pseudo')]);

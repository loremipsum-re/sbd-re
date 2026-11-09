<?php
/**
 * Les pseudos qui commencent par ce qu'un membre tape après « @ », pour les
 * suggestions du forum et du chat (src/lib/suggestions-pseudo.ts).
 *
 *   GET ?debut=<1 à 30 caractères>   huit pseudos au plus, membres actifs
 *
 * Réservé aux membres actifs : seul qui écrit en a l'usage. L'annuaire,
 * /membres/, reste la liste publique. La requête vit dans lib/comptes.php,
 * pseudosCommencantPar(), éprouvée par db/verifier.php.
 */

declare(strict_types=1);

namespace SBDRE;

define('SBDRE_API', true);
require __DIR__ . '/lib/comptes.php';

ouvrirApi(['GET']);
exigerActif();

// Une minute dans le navigateur : retaper la même lettre ne réinterroge pas.
header('Cache-Control: private, max-age=60');
repondreOk('', ['pseudos' => pseudosCommencantPar((string) ($_GET['debut'] ?? ''))]);

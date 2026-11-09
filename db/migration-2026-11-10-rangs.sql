-- =============================================================================
-- Migration du 9 novembre 2026 : les rangs, admin, modérateur, juge,
-- donateur, membre (docs/comptes.md, « Les rangs »).
--
-- Pour la base déjà chargée. Une base neuve n'en a pas besoin : schema.sql
-- porte déjà ceci. À importer UNE FOIS dans phpMyAdmin, onglet « Importer »,
-- AVANT de fusionner la PR : le code qui suit écrit ces rangs. Puis relancer
-- `php verifier.php` en SSH.
--
-- Les comptes gardent leur rang : « membre » et « admin » existent déjà.
-- =============================================================================

SET NAMES utf8mb4;

ALTER TABLE membre
  MODIFY role ENUM('membre','donateur','juge','moderateur','admin') NOT NULL DEFAULT 'membre';

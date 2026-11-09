-- =============================================================================
-- Migration du 8 novembre 2026 : le récapitulatif par courriel
-- (docs/forum.md, section 4).
--
-- Pour la base déjà chargée. Une base neuve n'en a pas besoin : schema.sql
-- porte déjà ceci. À importer UNE FOIS dans phpMyAdmin, onglet « Importer »,
-- AVANT de fusionner la PR : le code qui suit crée des jetons de ce type.
-- Puis relancer `php verifier.php` en SSH.
-- =============================================================================

SET NAMES utf8mb4;

-- Le lien « couper le récapitulatif » de chaque courriel porte un jeton,
-- comme la vérification d'adresse : il marche sans connexion, une fois, et
-- seulement pour couper.
ALTER TABLE jeton
  MODIFY type ENUM('verification','reinitialisation','session','recapitulatif') NOT NULL;

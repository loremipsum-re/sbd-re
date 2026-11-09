-- =============================================================================
-- Migration du 9 novembre 2026 : « J'aime » sur les messages du forum.
--
-- Pour la base déjà chargée. Une base neuve n'en a pas besoin : schema.sql
-- porte déjà ceci. À importer UNE FOIS dans phpMyAdmin, onglet « Importer »,
-- AVANT de fusionner la PR : le code qui suit lit cette table. Puis relancer
-- `php verifier.php` en SSH.
-- =============================================================================

SET NAMES utf8mb4;

-- --- La suite est recopiée telle quelle à la fin de schema.sql. ------------

-- -----------------------------------------------------------------------------
-- appreciation
--
-- Un membre aime un message du forum, une fois au plus (clé primaire), jamais
-- le sien (contrôlé par api/forum.php). Le compte écarte les membres bannis.
-- Un message ou un compte effacé emporte ses « J'aime ».
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS appreciation (
  membre_id       BIGINT UNSIGNED NOT NULL,
  message_id      BIGINT UNSIGNED NOT NULL,
  date_creation   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (membre_id, message_id),
  KEY ix_appreciation_message (message_id),

  CONSTRAINT fk_appreciation_membre FOREIGN KEY (membre_id) REFERENCES membre (id)
    ON DELETE CASCADE,
  CONSTRAINT fk_appreciation_message FOREIGN KEY (message_id) REFERENCES message_forum (id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- Migration du 7 novembre 2026, second lot : le fil, les forums des membres,
-- la modération, les avatars (docs/forum.md, étapes 6 à 9).
--
-- Pour la base déjà chargée. Une base neuve n'en a pas besoin : schema.sql
-- porte déjà tout ceci. À importer UNE FOIS dans phpMyAdmin, onglet
-- « Importer », AVANT de fusionner la PR : le code qui suit lit ces colonnes.
-- Puis relancer `php verifier.php` en SSH.
-- =============================================================================

SET NAMES utf8mb4;

-- Le jour où une barre reçoit le badge communauté : le fil le date ainsi.
-- Posé par votes.php à l'avis qui fait franchir le seuil.
ALTER TABLE performance
  ADD COLUMN date_verif_communaute DATETIME NULL DEFAULT NULL;

-- Un forum de membre se signale, comme un message ou un profil.
ALTER TABLE signalement
  MODIFY type ENUM('message','membre','performance','message_forum','forum') NOT NULL;

-- --- AVATARS --------------------------------------------------------------
-- La suite est recopiée telle quelle à la fin de schema.sql.

-- -----------------------------------------------------------------------------
-- avatar
--
-- EN BASE ET NON EN FICHIER : le déploiement efface du serveur tout fichier
-- absent du site généré (`mirror --delete`, .github/workflows/deploy.yml). Un
-- avatar déposé dans le dossier public partirait au déploiement suivant.
-- L'image est réencodée en WebP 300 × 300 par GD avant d'entrer ici, ce qui
-- efface ses métadonnées (la position GPS d'une photo, par exemple), et pèse
-- quelques dizaines de kilo-octets. api/avatar.php la sert.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS avatar (
  membre_id       BIGINT UNSIGNED NOT NULL,
  image           MEDIUMBLOB      NOT NULL,
  date_maj        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (membre_id),

  CONSTRAINT fk_avatar_membre FOREIGN KEY (membre_id) REFERENCES membre (id)
    ON DELETE CASCADE,
  CONSTRAINT ck_avatar_taille CHECK (LENGTH(image) BETWEEN 1 AND 200000)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- Migration du 10 novembre 2026 : relier un membre à sa fiche d'athlète.
--
-- Pour la base déjà chargée. Une base neuve n'en a pas besoin : schema.sql
-- porte déjà ceci. À importer UNE FOIS dans phpMyAdmin, onglet « Importer »,
-- AVANT de fusionner la PR : le code qui suit lit cette colonne et cette
-- table. Puis relancer `php verifier.php` en SSH.
-- =============================================================================

SET NAMES utf8mb4;

-- La fiche d'athlète officielle du membre, une fois le lien validé par
-- l'admin : l'adresse de /athlete/<slug>/. Une fiche ne se relie qu'à un
-- seul membre, un membre qu'à une seule fiche.
ALTER TABLE membre
  ADD COLUMN athlete_slug VARCHAR(120) NULL DEFAULT NULL,
  ADD UNIQUE KEY uq_membre_athlete (athlete_slug),
  ADD CONSTRAINT ck_membre_athlete CHECK (
    athlete_slug IS NULL OR REGEXP_LIKE(athlete_slug, '^[a-z0-9]+(-[a-z0-9]+)*\\z', 'c')
  );

-- --- La suite est recopiée telle quelle à la fin de schema.sql. ------------

-- -----------------------------------------------------------------------------
-- revendication
--
-- Un membre réclame sa fiche d'athlète, avec un message facultatif pour
-- l'admin, qui relie ou refuse (docs/comptes.md, « Relier un membre à sa
-- fiche »). Rien n'est relié sans cette décision : une fiche porte les
-- résultats officiels d'une personne réelle.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS revendication (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  membre_id       BIGINT UNSIGNED NOT NULL,
  athlete_slug    VARCHAR(120)    NOT NULL,
  message         VARCHAR(300)    NOT NULL DEFAULT '',
  etat            ENUM('attente','acceptee','refusee') NOT NULL DEFAULT 'attente',
  admin_id        BIGINT UNSIGNED NULL     DEFAULT NULL,
  date_creation   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  date_decision   DATETIME        NULL     DEFAULT NULL,

  PRIMARY KEY (id),
  KEY ix_revendication_etat (etat, date_creation),
  KEY ix_revendication_membre (membre_id, etat),
  KEY ix_revendication_slug (athlete_slug, etat),

  CONSTRAINT fk_revendication_membre FOREIGN KEY (membre_id) REFERENCES membre (id)
    ON DELETE CASCADE,
  CONSTRAINT ck_revendication_slug CHECK (
    REGEXP_LIKE(athlete_slug, '^[a-z0-9]+(-[a-z0-9]+)*\\z', 'c')
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

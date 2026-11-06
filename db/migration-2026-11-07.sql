-- =============================================================================
-- Migration du 7 novembre 2026 : nom Instagram à part, pseudo modifiable.
--
-- Pour une base déjà chargée avec db/schema.sql d'avant cette date. Une base
-- neuve n'en a pas besoin : schema.sql porte déjà ces colonnes.
--
-- À importer dans phpMyAdmin, onglet « Importer », sur la base visée. Les
-- comptes existants sont gardés. Puis relancer `php verifier.php` en SSH.
-- =============================================================================

-- Le pseudo, lui, se changera une fois par an (demande de l'auteur du même
-- jour) : ses deux colonnes arrivent dans la même migration, pour n'en
-- passer qu'une.

ALTER TABLE membre
  ADD COLUMN pseudo_precedent VARCHAR(30) NULL DEFAULT NULL AFTER pseudo,
  ADD COLUMN date_pseudo      DATETIME    NULL DEFAULT NULL AFTER pseudo_precedent,
  ADD COLUMN instagram        VARCHAR(30) NULL DEFAULT NULL AFTER date_pseudo,
  ADD COLUMN date_instagram   DATETIME    NULL DEFAULT NULL AFTER instagram,
  ADD UNIQUE KEY uq_membre_pseudo_precedent (pseudo_precedent),
  ADD UNIQUE KEY uq_membre_instagram (instagram),
  ADD CONSTRAINT ck_membre_instagram CHECK (
    instagram IS NULL
    OR (REGEXP_LIKE(instagram, '^[a-z0-9._]{1,30}\\z', 'c')
        AND REGEXP_LIKE(instagram, '[a-z0-9]', 'c'))
  );

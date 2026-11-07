-- =============================================================================
-- Migration du 7 novembre 2026 : la contrainte du pseudo finit par \z.
--
-- Pour une base chargée avec db/schema.sql d'avant le 6 novembre 2026. Sa
-- contrainte finit par `$`, qui accepte aussi un retour à la ligne final :
-- `php verifier.php` le signale par « Pseudo suivi d'un retour à la ligne :
-- ACCEPTÉ ». PHP refuse déjà ce pseudo (pseudoValide de lib/comptes.php) :
-- c'est le second filet, celui de la base, qu'on remet d'aplomb.
--
-- Une base neuve n'en a pas besoin : schema.sql porte déjà la bonne forme.
--
-- À importer dans phpMyAdmin, onglet « Importer », sur la base visée. Les
-- comptes existants sont gardés : MySQL contrôle chacun d'eux en posant la
-- nouvelle contrainte, et refuse la migration si un pseudo ne la respecte
-- pas. Puis relancer `php verifier.php` en SSH.
-- =============================================================================

ALTER TABLE membre DROP CHECK ck_membre_pseudo;

ALTER TABLE membre
  ADD CONSTRAINT ck_membre_pseudo CHECK (
    REGEXP_LIKE(pseudo, '^[a-z0-9._]{3,30}\\z', 'c')
    AND REGEXP_LIKE(pseudo, '[a-z0-9]', 'c')
  );

-- =============================================================================
-- Migration du 7 novembre 2026 : le forum, les notifications, le chat revu.
--
-- Pour la base déjà chargée. Une base neuve n'en a pas besoin : schema.sql
-- porte déjà tout ceci. À importer UNE FOIS dans phpMyAdmin, onglet
-- « Importer », sur la base visée. Les comptes, performances et votes sont
-- gardés. Puis relancer `php verifier.php` en SSH.
--
-- Plan : docs/forum.md, sections 3 à 5.
-- =============================================================================

SET NAMES utf8mb4;

-- La présence en ligne, et le récapitulatif par courriel (coupé par défaut).
ALTER TABLE membre
  ADD COLUMN derniere_activite  DATETIME   NULL     DEFAULT NULL,
  ADD COLUMN recapitulatif      TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN date_recapitulatif DATETIME   NULL     DEFAULT NULL,
  ADD KEY ix_membre_activite (derniere_activite);

-- Un message du chat qui aborde le dopage paraît sous un avertissement.
ALTER TABLE message
  ADD COLUMN sensible TINYINT(1) NOT NULL DEFAULT 0;

-- Un message du forum se signale comme un message du chat (phase 8).
ALTER TABLE signalement
  MODIFY type ENUM('message','membre','performance','message_forum') NOT NULL;

-- --- FORUM ---------------------------------------------------------------
-- La suite est recopiée telle quelle à la fin de schema.sql.

-- -----------------------------------------------------------------------------
-- forum_categorie, forum, sujet, message_forum
--
-- Trois niveaux, comme phpBB (docs/forum.md, section 2) : une catégorie
-- regroupe des forums, un forum des sujets, un sujet des messages. Les
-- catégories et forums de départ sont posés à la fin de ce fichier.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS forum_categorie (
  id              SMALLINT UNSIGNED NOT NULL,
  titre           VARCHAR(60)     NOT NULL,
  ordre           SMALLINT        NOT NULL DEFAULT 0,

  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS forum (
  id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  categorie_id    SMALLINT UNSIGNED NOT NULL,

  -- L'adresse de la page, /forum/<adresse>/ : unique sur tout le forum.
  adresse         VARCHAR(60)     NOT NULL,
  titre           VARCHAR(80)     NOT NULL,
  description     VARCHAR(200)    NOT NULL DEFAULT '',

  -- Vide pour un forum de l'équipe, le membre sinon (forums des membres).
  createur_id     BIGINT UNSIGNED NULL     DEFAULT NULL,
  etat            ENUM('ouvert','verrouille','archive','masque') NOT NULL DEFAULT 'ouvert',
  ordre           SMALLINT        NOT NULL DEFAULT 0,
  date_creation   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_forum_adresse (adresse),
  KEY ix_forum_categorie (categorie_id, ordre),

  CONSTRAINT fk_forum_categorie FOREIGN KEY (categorie_id) REFERENCES forum_categorie (id),
  CONSTRAINT fk_forum_createur FOREIGN KEY (createur_id) REFERENCES membre (id)
    ON DELETE SET NULL,
  CONSTRAINT ck_forum_adresse CHECK (
    REGEXP_LIKE(adresse, '^[a-z0-9]+(-[a-z0-9]+)*\\z', 'c')
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sujet (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  forum_id        INT UNSIGNED    NOT NULL,

  -- Le pseudo est copié, comme au chat : un compte effacé laisse ses sujets
  -- signés de son nom, détachés de lui.
  membre_id       BIGINT UNSIGNED NULL     DEFAULT NULL,
  pseudo          VARCHAR(30)     NOT NULL,
  titre           VARCHAR(120)    NOT NULL,
  epingle         TINYINT(1)      NOT NULL DEFAULT 0,
  verrouille      TINYINT(1)      NOT NULL DEFAULT 0,
  date_creation   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

  -- Stockés par exception (docs/forum.md, section 3) : la liste d'un forum
  -- les lit à chaque visite, les recompter coûterait une requête par sujet.
  date_dernier    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  dernier_pseudo  VARCHAR(30)     NOT NULL,
  reponses        INT UNSIGNED    NOT NULL DEFAULT 0,

  PRIMARY KEY (id),
  KEY ix_sujet_liste (forum_id, epingle, date_dernier),
  KEY ix_sujet_membre (membre_id, date_creation),

  CONSTRAINT fk_sujet_forum FOREIGN KEY (forum_id) REFERENCES forum (id)
    ON DELETE CASCADE,
  CONSTRAINT fk_sujet_membre FOREIGN KEY (membre_id) REFERENCES membre (id)
    ON DELETE SET NULL,
  CONSTRAINT ck_sujet_titre CHECK (CHAR_LENGTH(titre) >= 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS message_forum (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  sujet_id        BIGINT UNSIGNED NOT NULL,
  membre_id       BIGINT UNSIGNED NULL     DEFAULT NULL,
  pseudo          VARCHAR(30)     NOT NULL,

  -- Le texte tel que tapé, balises d'époque comprises : le rendu se fait à
  -- l'affichage, à partir du texte échappé (docs/forum.md, section 6 bis).
  texte           TEXT            NOT NULL,
  ip              VARCHAR(45)     NOT NULL,
  date_creation   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  date_modification DATETIME      NULL     DEFAULT NULL,
  masque_par      BIGINT UNSIGNED NULL     DEFAULT NULL,
  sensible        TINYINT(1)      NOT NULL DEFAULT 0,

  PRIMARY KEY (id),
  KEY ix_message_forum_sujet (sujet_id, id),
  KEY ix_message_forum_membre (membre_id, date_creation),

  CONSTRAINT fk_message_forum_sujet FOREIGN KEY (sujet_id) REFERENCES sujet (id)
    ON DELETE CASCADE,
  CONSTRAINT fk_message_forum_membre FOREIGN KEY (membre_id) REFERENCES membre (id)
    ON DELETE SET NULL,
  CONSTRAINT ck_message_forum_texte CHECK (CHAR_LENGTH(texte) BETWEEN 1 AND 5000)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- abonnement, notification
--
-- On suit d'office les sujets qu'on ouvre, et ceux où l'on clique « Suivre ».
-- Une réponse notifie chaque abonné sauf son auteur (docs/forum.md, section 4).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS abonnement (
  membre_id       BIGINT UNSIGNED NOT NULL,
  sujet_id        BIGINT UNSIGNED NOT NULL,
  date_abonnement DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (membre_id, sujet_id),
  KEY ix_abonnement_sujet (sujet_id),

  CONSTRAINT fk_abonnement_membre FOREIGN KEY (membre_id) REFERENCES membre (id)
    ON DELETE CASCADE,
  CONSTRAINT fk_abonnement_sujet FOREIGN KEY (sujet_id) REFERENCES sujet (id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  membre_id       BIGINT UNSIGNED NOT NULL,
  nature          ENUM('reponse','mention','badge','moderation') NOT NULL,
  texte           VARCHAR(200)    NOT NULL,

  -- Une adresse du site seulement : jamais un lien vers ailleurs.
  lien            VARCHAR(255)    NOT NULL,
  lue             TINYINT(1)      NOT NULL DEFAULT 0,
  date_creation   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY ix_notification_membre (membre_id, lue, id),

  CONSTRAINT fk_notification_membre FOREIGN KEY (membre_id) REFERENCES membre (id)
    ON DELETE CASCADE,
  CONSTRAINT ck_notification_lien CHECK (lien LIKE '/%' AND lien NOT LIKE '//%')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Les catégories et forums de départ (docs/forum.md, section 2). INSERT
-- IGNORE : un second import ne double rien. Un forum de plus s'ajoute en une
-- ligne ; la catégorie 9 attend les forums des membres.
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO forum_categorie (id, titre, ordre) VALUES
  (1, 'Squat', 10),
  (2, 'Développé couché', 20),
  (3, 'Soulevé de terre', 30),
  (4, 'Arraché', 40),
  (5, 'Épaulé-jeté', 50),
  (6, 'Général', 60),
  (7, 'Compétitions', 70),
  (8, 'Hors sujet', 80),
  (9, 'Forums des membres', 90);

INSERT IGNORE INTO forum (categorie_id, adresse, titre, description, ordre) VALUES
  (1, 'squat-technique', 'Technique', 'Placement, profondeur, vidéos à analyser.', 10),
  (1, 'squat-programmation', 'Programmation', 'Cycles, volume, intensité.', 20),
  (2, 'developpe-couche-technique', 'Technique', 'Arche, pause, trajet de la barre.', 10),
  (2, 'developpe-couche-programmation', 'Programmation', 'Cycles, volume, intensité.', 20),
  (3, 'souleve-de-terre-technique', 'Technique', 'Sumo ou conventionnel, départ, verrouillage.', 10),
  (3, 'souleve-de-terre-programmation', 'Programmation', 'Cycles, volume, intensité.', 20),
  (4, 'arrache-technique', 'Technique', 'Tirage, passage sous la barre, réception.', 10),
  (4, 'arrache-programmation', 'Programmation', 'Cycles, volume, intensité.', 20),
  (5, 'epaule-jete-technique', 'Technique', 'Épaulé, jeté, fente.', 10),
  (5, 'epaule-jete-programmation', 'Programmation', 'Cycles, volume, intensité.', 20),
  (6, 'presentations', 'Présentations', 'Votre salle, vos mouvements, vos objectifs.', 10),
  (6, 'equipement', 'Équipement', 'Chaussures, ceinture, genouillères, bandes.', 20),
  (6, 'sante-recuperation', 'Santé et récupération', 'Sommeil, blessures, nutrition. Rien ici ne remplace un avis médical.', 30),
  (6, 'salles', 'Salles de l''île', 'Où s''entraîner à La Réunion. La carte des salles est sur la page Salles.', 40),
  (6, 'le-site', 'Le site SBD.re', 'Questions, idées, erreurs repérées.', 50),
  (7, 'calendrier', 'Calendrier et inscriptions', 'Les prochaines compétitions, sur l''île et ailleurs.', 10),
  (7, 'resultats', 'Résultats', 'Les compétitions passées et leurs barres.', 20),
  (7, 'reglement', 'Règlement et arbitrage', 'Règles techniques, tenue, pesée.', 30),
  (8, 'discussions-libres', 'Discussions libres', 'Tout ce qui n''a pas sa place ailleurs.', 10);

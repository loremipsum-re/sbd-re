-- =============================================================================
-- SBD.re — schéma des comptes, profils, performances et chat
--
-- Plan de travail : docs/comptes.md, section 3. Réécrit le 6 novembre 2026 ;
-- la version du 30 juillet (Google, tranches, file de modération) est
-- remplacée sans migration, la base de production étant vide.
--
-- Cible : MySQL 8.0.16 ou plus récent, pour que les contraintes CHECK
-- s'appliquent. La base OVH tourne en 8.4.10, mesuré.
--
-- Chargement, depuis l'hébergement seulement (OVH ferme le tunnel SSH vers ses
-- bases mutualisées) : phpMyAdmin, onglet « Importer », ou `mysql` en SSH.
-- Puis `php verifier.php` en SSH, qui doit finir sur un bilan vert.
--
-- Le script est réexécutable : chaque table n'est créée que si elle manque.
-- Il ne MODIFIE donc pas une table existante. La base de test porte encore les
-- tables de juillet : les supprimer d'abord, enfants avant parents.
--
--   DROP TABLE IF EXISTS signalement, message, vote, jeton, performance,
--                        tentative_connexion, membre;
--
-- Jamais sur la base de production une fois ouverte.
--
-- CE QUE CE SCHÉMA NE FAIT PAS
-- ----------------------------
-- MySQL n'a pas de sécurité par ligne. La base fait confiance à qui présente
-- le mot de passe, et toute l'autorisation vit dans le code PHP
-- (public/api/lib/autorisation.php). Les contraintes CHECK ci-dessous sont un
-- filet : PHP valide chaque champ avant d'écrire, et c'est lui qui fait
-- autorité. Le filet ne tient qu'avec le `sql_mode` strict que chaque
-- connexion impose, sans quoi MySQL rabote une valeur avant de la contrôler.
-- =============================================================================

SET NAMES utf8mb4;

-- InnoDB pour les clés étrangères. utf8mb4 pour les accents et les emojis :
-- l'ancien « utf8 » de MySQL s'arrête à trois octets et tronque le reste.


-- -----------------------------------------------------------------------------
-- membre
--
-- Un compte, et son profil public à /membres/<pseudo>/.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS membre (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- 254 caractères, la longueur maximale d'une adresse. Stockée en
  -- minuscules par PHP : « Jean@x.re » et « jean@x.re » sont une seule
  -- personne.
  courriel        VARCHAR(254)    NOT NULL,

  -- password_hash() produit 60 octets avec bcrypt, mais l'algorithme par
  -- défaut de PHP évolue : la documentation recommande 255.
  mot_de_passe_hash VARCHAR(255)  NOT NULL,

  -- Le format d'un nom Instagram : lettres minuscules, chiffres, point,
  -- tiret bas, 30 caractères au plus. Le pseudo sert aussi d'adresse du
  -- profil, d'où trois règles de plus :
  --   * stocké en minuscules, pour qu'une adresse n'existe qu'une fois ;
  --   * au moins une lettre ou un chiffre, pour écarter « ... » ;
  --   * aucun tiret, ce qui réserve /membres/-gabarit/ au gabarit d'Astro.
  -- Le drapeau 'c' rend l'expression sensible à la casse : la collation
  -- _ci de la colonne laisserait sinon passer « Jean ». La fin s'écrit
  -- `\z`, doublé dans la chaîne SQL : `$` accepterait aussi un retour à la
  -- ligne final, « abc » suivi d'un saut de ligne.
  pseudo          VARCHAR(30)     NOT NULL,

  -- Le nom Instagram, distinct du pseudo, choix de l'auteur du 7 novembre
  -- 2026. Même format qu'un pseudo, sans longueur minimale. Unique : un
  -- compte Instagram ne s'affiche que sur un seul profil.
  instagram       VARCHAR(30)     NULL     DEFAULT NULL,

  -- Date du dernier nom posé. Un nouveau nom n'est accepté que six mois
  -- après : changer de nom à volonté permettrait d'emprunter celui d'un
  -- autre le temps d'une capture d'écran. Retirer le nom ne remet pas
  -- l'horloge à zéro, sans quoi le délai se contournerait en deux gestes.
  date_instagram  DATETIME        NULL     DEFAULT NULL,

  -- Affiche le lien vers instagram.com/<instagram> sur le profil. Se coche
  -- et se décoche à volonté, sans toucher au délai.
  instagram_visible TINYINT(1)    NOT NULL DEFAULT 0,

  bio             VARCHAR(160)    NULL     DEFAULT NULL,

  -- Identifiant d'une fiche de /salles/. Pas de clé étrangère : les salles
  -- vivent dans le dépôt, pas en base. PHP valide contre leur liste.
  salle           VARCHAR(80)     NULL     DEFAULT NULL,

  -- Donné à la main en base. Aucun point d'entrée ne permet de le changer.
  role            ENUM('membre','admin') NOT NULL DEFAULT 'membre',

  -- Sans adresse vérifiée : ni publier, ni voter, ni chat.
  courriel_verifie TINYINT(1)     NOT NULL DEFAULT 0,

  banni           TINYINT(1)      NOT NULL DEFAULT 0,

  -- 45 caractères couvrent une IPv6 sous sa forme la plus longue. Sert au
  -- freinage, 3 inscriptions par heure et par IP, et à la conservation d'un
  -- an que demande la LCEN (docs/comptes.md, section 8).
  ip_inscription  VARCHAR(45)     NOT NULL,

  date_inscription DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_membre_courriel (courriel),
  UNIQUE KEY uq_membre_pseudo   (pseudo),
  UNIQUE KEY uq_membre_instagram (instagram),
  KEY ix_membre_ip (ip_inscription, date_inscription),

  CONSTRAINT ck_membre_pseudo CHECK (
    REGEXP_LIKE(pseudo, '^[a-z0-9._]{3,30}\\z', 'c')
    AND REGEXP_LIKE(pseudo, '[a-z0-9]', 'c')
  ),
  CONSTRAINT ck_membre_instagram CHECK (
    instagram IS NULL
    OR (REGEXP_LIKE(instagram, '^[a-z0-9._]{1,30}\\z', 'c')
        AND REGEXP_LIKE(instagram, '[a-z0-9]', 'c'))
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- performance
--
-- Une barre, publiée dès l'ajout. Ne se modifie pas : on la retire et on la
-- refait, ce qui remet ses votes à zéro. Une modification qui garderait les
-- votes ferait valider 300 kg avec les avis donnés à 150 kg.
--
-- Aucun total ni badge stocké : le total se calcule avec les trois meilleures
-- barres à équipement égal, le badge communauté avec la table vote. Stockés,
-- ils périmeraient au premier vote.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS performance (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  membre_id       BIGINT UNSIGNED NOT NULL,

  -- Le vocabulaire de LIFTS et EquipmentGroup, src/lib/categories.ts.
  mouvement       ENUM('squat','bench','deadlift') NOT NULL,
  equipement      ENUM('raw','wraps','equipped')   NOT NULL,

  -- DECIMAL et non FLOAT : 182,5 kg doit rester 182,5 et non 182,49999.
  -- 999,99 au plus ; au-delà, le mode strict refuse au lieu de raboter.
  charge_kg       DECIMAL(5,2)    NOT NULL,

  -- Catégorie de poids du site officiel, facultative : « 93 », « 120+ ».
  categorie       VARCHAR(5)      NULL     DEFAULT NULL,

  date_performance DATE           NOT NULL,

  -- Obligatoire. PHP le limite à Instagram, YouTube et TikTok ; le filet
  -- exige au moins https, ce qui écarte un lien « javascript: ».
  lien_video      VARCHAR(500)    NOT NULL,

  -- Le badge admin se lit sur la DATE, pas sur admin_id : si l'admin efface
  -- un jour son compte, admin_id repasse à NULL et le badge reste.
  admin_id        BIGINT UNSIGNED NULL     DEFAULT NULL,
  date_verif_admin DATETIME       NULL     DEFAULT NULL,

  date_creation   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),

  -- Une performance est une donnée personnelle : elle part avec son compte.
  CONSTRAINT fk_perf_membre FOREIGN KEY (membre_id) REFERENCES membre (id)
    ON DELETE CASCADE,
  CONSTRAINT fk_perf_admin  FOREIGN KEY (admin_id)  REFERENCES membre (id)
    ON DELETE SET NULL,

  -- Les records d'un profil : meilleure charge par mouvement et équipement.
  KEY ix_perf_membre (membre_id, mouvement, equipement, charge_kg),

  -- Le fil « À vérifier », les plus récentes d'abord.
  KEY ix_perf_recentes (date_creation),

  CONSTRAINT ck_perf_charge CHECK (charge_kg > 0),
  CONSTRAINT ck_perf_video  CHECK (lien_video LIKE 'https://%')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- vote
--
-- Un avis par membre et par performance, qu'il peut changer : la clé primaire
-- sur le couple empêche de peser double. Le badge communauté demande 20 votes
-- de membres non bannis, dont 60 % favorables. Les règles de qui vote (30 jours
-- d'ancienneté, jamais sur sa propre barre) vivent dans PHP.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS vote (
  performance_id  BIGINT UNSIGNED NOT NULL,
  membre_id       BIGINT UNSIGNED NOT NULL,
  ok              TINYINT(1)      NOT NULL,
  date_vote       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                  ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (performance_id, membre_id),
  KEY ix_vote_membre (membre_id),

  -- Une performance retirée emporte ses votes, un compte effacé aussi.
  CONSTRAINT fk_vote_perf   FOREIGN KEY (performance_id) REFERENCES performance (id)
    ON DELETE CASCADE,
  CONSTRAINT fk_vote_membre FOREIGN KEY (membre_id)      REFERENCES membre (id)
    ON DELETE CASCADE,

  CONSTRAINT ck_vote_ok CHECK (ok IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- message
--
-- Le chat ET son journal, dans une seule table. « Visible 7 jours » est un
-- filtre de lecture. « Conservé 1 an » est une purge par lots de 100, lancée à
-- chaque écriture. Le texte est stocké tel que tapé, smileys compris en code
-- (« :) ») : le navigateur les dessine à l'affichage.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS message (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- La liste des salons vit dans chat.php, source unique. Pas d'ENUM : un
  -- salon de plus ne demande pas de toucher au schéma.
  canal           VARCHAR(20)     NOT NULL,

  -- NULL après effacement du compte : le message sort du chat (la lecture
  -- l'écarte) et reste au journal jusqu'à son année, signé du pseudo copié.
  membre_id       BIGINT UNSIGNED NULL     DEFAULT NULL,
  pseudo          VARCHAR(30)     NOT NULL,

  texte           VARCHAR(500)    NOT NULL,
  ip              VARCHAR(45)     NOT NULL,
  date_envoi      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

  -- L'admin qui l'a masqué. Sans clé étrangère, exprès : un SET NULL à
  -- l'effacement de cet admin ferait réapparaître le message.
  masque_par      BIGINT UNSIGNED NULL     DEFAULT NULL,

  PRIMARY KEY (id),

  CONSTRAINT fk_message_membre FOREIGN KEY (membre_id) REFERENCES membre (id)
    ON DELETE SET NULL,

  -- Lecture d'un salon sur 7 jours.
  KEY ix_message_canal (canal, date_envoi),
  -- Freinage : la date du dernier message d'un membre.
  KEY ix_message_membre (membre_id, date_envoi),
  -- Purge des lignes de plus d'un an.
  KEY ix_message_date (date_envoi),

  CONSTRAINT ck_message_texte CHECK (texte <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- signalement
--
-- La file de l'admin. cible_id désigne un message, un membre ou une
-- performance selon type : pas de clé étrangère possible vers trois tables.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS signalement (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  type            ENUM('message','membre','performance') NOT NULL,
  cible_id        BIGINT UNSIGNED NOT NULL,

  -- NULL si l'auteur efface son compte : le signalement reste à traiter.
  auteur_id       BIGINT UNSIGNED NULL     DEFAULT NULL,
  motif           VARCHAR(200)    NULL     DEFAULT NULL,
  date_signalement DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  traite          TINYINT(1)      NOT NULL DEFAULT 0,

  PRIMARY KEY (id),

  -- Un signalement par membre et par cible : un seul compte ne noie pas la
  -- file sous cent copies du même.
  UNIQUE KEY uq_signalement (type, cible_id, auteur_id),
  KEY ix_signalement_file (traite, date_signalement),

  CONSTRAINT fk_signalement_auteur FOREIGN KEY (auteur_id) REFERENCES membre (id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- jeton
--
-- Vérification d'adresse (24 h), réinitialisation de mot de passe, et
-- session (30 jours). Seule l'EMPREINTE est stockée : une injection SQL qui
-- lirait la table n'y trouverait rien d'utilisable. Bannir un membre efface
-- ses jetons de session, ce qui le déconnecte partout.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS jeton (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  membre_id       BIGINT UNSIGNED NOT NULL,
  type            ENUM('verification','reinitialisation','session') NOT NULL,

  -- SHA-256 du jeton, en hexadécimal.
  jeton_hash      CHAR(64)        NOT NULL,

  date_expiration DATETIME        NOT NULL,

  -- Un jeton de vérification ou de réinitialisation ne sert qu'une fois.
  -- Une session, elle, ne se « consomme » pas : elle expire ou s'efface.
  date_utilisation DATETIME       NULL     DEFAULT NULL,

  date_creation   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_jeton_hash (jeton_hash),
  KEY ix_jeton_membre (membre_id, type),
  KEY ix_jeton_expiration (date_expiration),
  CONSTRAINT fk_jeton_membre FOREIGN KEY (membre_id) REFERENCES membre (id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- tentative_connexion
--
-- Freine les essais de mots de passe en série. Les échecs récents se
-- comptent par adresse ET par IP : un attaquant change l'une en gardant
-- l'autre. Les lignes de plus de quelques jours n'ont plus de valeur : la
-- connexion les purge en passant.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tentative_connexion (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- L'adresse SAISIE, pas un membre : une tentative sur un compte inexistant
  -- compte aussi.
  courriel        VARCHAR(254)    NOT NULL,
  ip              VARCHAR(45)     NOT NULL,
  reussie         TINYINT(1)      NOT NULL DEFAULT 0,
  date_tentative  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY ix_tentative_courriel (courriel, date_tentative),
  KEY ix_tentative_ip (ip, date_tentative)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

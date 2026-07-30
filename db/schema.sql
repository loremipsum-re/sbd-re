-- =============================================================================
-- SBD.re — schéma de la partie COMMUNAUTÉ
--
-- Cible : MySQL 5.7+ ou MariaDB 10.3+. Le script reste volontairement
-- conservateur, mais il n'a plus besoin de l'être par prudence : il a été
-- CHARGÉ AVEC SUCCÈS le 30 juillet 2026 sur la base OVH du projet, servie par
-- MySQL 8 (l'absence de largeur d'affichage sur les entiers, `bigint unsigned`
-- au lieu de `bigint(20) unsigned`, situe le serveur en 8.0.19 ou plus récent).
--
-- Deux façons de le charger, l'une et l'autre depuis l'hébergement lui-même :
-- phpMyAdmin (onglet « Importer »), ou le client `mysql` en SSH. En revanche un
-- tunnel SSH depuis une machine de développement ne fonctionne pas, OVH ayant
-- fermé cette voie vers ses bases mutualisées.
--
-- Le script est réexécutable : chaque table est créée seulement si elle n'existe
-- pas déjà. Attention, cela signifie aussi qu'il ne MODIFIE pas une table
-- existante. Pour repartir d'un schéma propre en base de test, supprimer les
-- tables d'abord.
--
-- CE QUE CE SCHÉMA NE FAIT PAS
-- ----------------------------
-- MySQL ne connaît pas les politiques de sécurité par ligne (le « RLS » de
-- PostgreSQL, qui était prévu avec Supabase). Aucune ligne de ce fichier
-- n'empêche donc quiconque de lire ou modifier n'importe quoi : la base fait
-- confiance à qui présente le mot de passe. Toute l'autorisation vit dans le
-- code PHP, et c'est là que se joue la sécurité du classement.
--
-- Voir docs/communaute.md, section « Ce qui remplace les politiques RLS ».
--
-- Les contraintes CHECK présentes plus bas sont un filet, pas une validation.
-- Sur MySQL 5.7 elles auraient été analysées puis ignorées en silence ; ici
-- elles MORDENT, vérifié par `SHOW CREATE TABLE performance` qui les restitue
-- intactes. PHP valide de toute façon chaque champ avant l'écriture, et c'est
-- lui qui fait autorité : la base n'est que le dernier recours.
-- =============================================================================

SET NAMES utf8mb4;

-- InnoDB pour les clés étrangères, utf8mb4 pour accepter les accents et les
-- emoji d'un pseudo. L'ancien « utf8 » de MySQL ne code que trois octets et
-- tronque silencieusement le reste.


-- -----------------------------------------------------------------------------
-- membre
--
-- Un compte. Deux moyens de connexion cohabitent sur la même ligne :
--
--   * mot_de_passe_hash renseigné  → connexion par courriel et mot de passe
--   * google_sub renseigné         → connexion par Google
--
-- Les deux peuvent l'être en même temps, ce qui permet à quelqu'un inscrit par
-- courriel d'ajouter Google plus tard sans créer un second compte. Au moins un
-- des deux doit l'être, sinon le compte serait inaccessible : c'est PHP qui le
-- garantit à l'inscription.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS membre (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- 254 caractères est la longueur maximale d'une adresse de courriel.
  -- Toujours stockée en minuscules, sinon « Jean@x.re » et « jean@x.re »
  -- créeraient deux comptes pour une seule personne.
  courriel        VARCHAR(254)    NOT NULL,

  -- NULL pour un compte créé par Google, qui n'a pas de mot de passe ici.
  -- 255 caractères : password_hash() produit 60 octets avec bcrypt aujourd'hui,
  -- mais l'algorithme par défaut de PHP change au fil des versions et la
  -- documentation recommande cette largeur pour ne pas avoir à migrer.
  mot_de_passe_hash VARCHAR(255)  NULL     DEFAULT NULL,

  -- Identifiant stable du compte Google (le champ « sub » du jeton d'identité).
  -- On ne se sert PAS de l'adresse de courriel pour reconnaître un compte
  -- Google : elle peut changer, « sub » jamais.
  google_sub      VARCHAR(255)    NULL     DEFAULT NULL,

  -- Nom public, obligatoire, unique. Sert aussi à fabriquer l'adresse de la
  -- fiche.
  pseudo          VARCHAR(40)     NOT NULL,

  -- Forme du pseudo utilisée dans l'URL. Stockée et unique, plutôt que
  -- recalculée à la lecture : « Jean-Luc » et « Jean Luc » produisent le même
  -- slug, et deux fiches se marcheraient dessus. Le site officiel fait échouer
  -- son build sur ce genre de collision ; ici c'est l'index unique qui refuse.
  slug            VARCHAR(60)     NOT NULL,

  -- Facultatif. L'athlète choisit à l'inscription si le classement affiche son
  -- pseudo ou son nom réel : voir afficher_nom_reel.
  nom_reel        VARCHAR(80)     NULL     DEFAULT NULL,
  afficher_nom_reel TINYINT(1)    NOT NULL DEFAULT 0,

  -- Reprend les identifiants de SEXES dans src/lib/categories.ts.
  sexe            ENUM('M','F')   NOT NULL,

  -- Identifiant d'une tranche de COMMUNITY_HEIGHT_BRACKETS
  -- (src/lib/categories.ts) : « 170-180 », « 200+ ». La taille ne bougeant
  -- plus à l'âge adulte, elle vit sur le membre. Le poids, lui, change entre
  -- deux performances : il est porté par la performance.
  tranche_taille  VARCHAR(10)     NULL     DEFAULT NULL,

  -- « membre » ne peut agir que sur ses propres performances. « moderateur »
  -- accède à la file d'attente. « admin » peut en plus changer les rôles.
  -- Le rôle est donné à la main en base : aucun point d'entrée de l'API ne
  -- permet de se promouvoir, ce serait la faille la plus évidente.
  role            ENUM('membre','moderateur','admin') NOT NULL DEFAULT 'membre',

  -- Une adresse non vérifiée ne doit pas pouvoir soumettre : sans quoi
  -- n'importe qui inscrirait l'adresse d'un tiers.
  courriel_verifie TINYINT(1)     NOT NULL DEFAULT 0,

  date_inscription DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  date_maj        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                  ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_membre_courriel (courriel),
  UNIQUE KEY uq_membre_pseudo   (pseudo),
  UNIQUE KEY uq_membre_slug     (slug),

  -- Un index UNIQUE accepte plusieurs NULL sous MySQL : les comptes sans
  -- Google ne se gênent donc pas entre eux.
  UNIQUE KEY uq_membre_google   (google_sub)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- performance
--
-- Une barre déclarée. Trois décisions sont encodées ici.
--
-- 1. PAS DE TOTAL SAISISSABLE. Seuls les trois mouvements existent. Un « total »
--    déclaré en salle ne veut rien dire : rien ne garantit que les trois barres
--    ont été faites le même jour, dans le même état de fraîcheur. Le total
--    communauté est donc CALCULÉ à la lecture, en additionnant les trois
--    meilleures barres publiées d'un membre à équipement égal, et seulement si
--    les trois existent. Le stocker le laisserait périmer.
--
-- 2. LE POIDS EST SUR LA PERFORMANCE, PAS SUR LE MEMBRE. Quelqu'un qui prend
--    dix kilos en un an ne doit pas voir ses anciennes barres changer de
--    tranche rétroactivement. La tranche est figée au moment de la barre.
--
-- 3. PAS DE SCORE DOTS. Le Dots exige un poids de corps au kilo près. Les
--    tranches déclaratives ne le fournissent pas, et prendre le milieu de la
--    tranche fabriquerait un chiffre faux. Le classement communauté se lit donc
--    par tranche, sans score relatif. C'est le prix, assumé, du choix des
--    tranches.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS performance (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  membre_id       BIGINT UNSIGNED NOT NULL,

  -- Reprend les identifiants de LIFTS dans src/lib/categories.ts, moins
  -- « total ». Employer le même vocabulaire de bout en bout évite une table de
  -- correspondance entre la base et l'affichage.
  mouvement       ENUM('squat','bench','deadlift') NOT NULL,

  -- Reprend EquipmentGroup dans src/lib/categories.ts.
  equipement      ENUM('raw','wraps','equipped')   NOT NULL,

  -- DECIMAL et non FLOAT : une charge est une valeur exacte, et les flottants
  -- feraient apparaître 182.49999 dans un classement. Trois chiffres avant la
  -- virgule couvrent tout record humain, deux après suffisent aux plaques de
  -- 0,25 kg.
  charge_kg       DECIMAL(5,2)    NOT NULL,

  -- Identifiant d'une tranche de COMMUNITY_WEIGHT_BRACKETS : « 80-90 », « 120+ ».
  tranche_poids   VARCHAR(10)     NOT NULL,

  date_performance DATE           NOT NULL,

  -- Salle ou lieu, facultatif et purement descriptif. Ne sert jamais à situer
  -- géographiquement une performance : le site officiel a appris à ses dépens
  -- qu'un nom de lieu ne prouve rien.
  lieu            VARCHAR(120)    NULL     DEFAULT NULL,

  -- OBLIGATOIRE. Sans vidéo, la modération n'aurait rien à examiner et le
  -- classement ne vaudrait rien au premier chiffre gonflé. C'est ce champ qui
  -- rend le reste crédible.
  lien_video      VARCHAR(500)    NOT NULL,

  -- Cycle de vie : en_attente à la soumission, puis publiee ou refusee.
  -- Une performance modifiée par son auteur repasse en_attente, sinon on
  -- publierait 150 kg puis on le remplacerait par 300 kg sans repasser devant
  -- personne.
  etat            ENUM('en_attente','publiee','refusee') NOT NULL DEFAULT 'en_attente',

  -- Renseigné en cas de refus, et montré à l'auteur : un refus sans motif fait
  -- fuir quelqu'un qui aurait juste mal saisi sa date.
  motif_refus     TEXT            NULL     DEFAULT NULL,

  -- ON DELETE SET NULL : si un modérateur supprime son compte, les décisions
  -- qu'il a prises restent, et les performances publiées ne disparaissent pas
  -- avec lui.
  moderateur_id   BIGINT UNSIGNED NULL     DEFAULT NULL,
  date_moderation DATETIME        NULL     DEFAULT NULL,

  date_soumission DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  date_maj        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                  ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),

  -- ON DELETE CASCADE : une performance est une donnée personnelle. Quand un
  -- membre exerce son droit à l'effacement, ses barres partent avec son compte.
  -- Sans cette cascade, il resterait des performances orphelines nominatives.
  CONSTRAINT fk_perf_membre     FOREIGN KEY (membre_id)     REFERENCES membre (id)
    ON DELETE CASCADE,
  CONSTRAINT fk_perf_moderateur FOREIGN KEY (moderateur_id) REFERENCES membre (id)
    ON DELETE SET NULL,

  -- Le classement public lit toujours « les performances publiées d'un
  -- mouvement, d'un équipement et d'une tranche ». Cet index couvre exactement
  -- cette question, la charge décroissante évitant en plus un tri.
  KEY ix_perf_classement (etat, mouvement, equipement, tranche_poids, charge_kg),

  -- La file de modération, lue à chaque visite de la page de modération.
  KEY ix_perf_file (etat, date_soumission),

  -- Les performances d'un membre, pour sa fiche et son espace personnel.
  KEY ix_perf_membre (membre_id, etat),

  -- Filet, non appliqué sous MySQL 5.7 : PHP valide de toute façon.
  CONSTRAINT ck_perf_charge CHECK (charge_kg > 0 AND charge_kg <= 999.99)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- jeton
--
-- Jetons à usage unique pour la vérification d'adresse et la réinitialisation
-- de mot de passe.
--
-- Le jeton n'est PAS stocké : seule son empreinte l'est. Même raisonnement que
-- pour un mot de passe. Quelqu'un qui lirait cette table par une injection SQL
-- y trouverait des empreintes inutilisables, là où des jetons en clair lui
-- donneraient la main sur tous les comptes en attente de réinitialisation.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS jeton (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  membre_id       BIGINT UNSIGNED NOT NULL,
  type            ENUM('verification','reinitialisation') NOT NULL,

  -- Empreinte SHA-256 du jeton envoyé par courriel, en hexadécimal.
  jeton_hash      CHAR(64)        NOT NULL,

  -- Un jeton de réinitialisation doit expirer vite : une boîte de courriel
  -- consultée des mois plus tard ne doit pas rouvrir un compte.
  date_expiration DATETIME        NOT NULL,

  -- Renseigné à la première utilisation. Un jeton déjà employé est refusé,
  -- sans quoi le même lien rouvrirait le compte indéfiniment.
  date_utilisation DATETIME       NULL     DEFAULT NULL,

  date_creation   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_jeton_hash (jeton_hash),
  KEY ix_jeton_membre (membre_id, type),
  CONSTRAINT fk_jeton_membre FOREIGN KEY (membre_id) REFERENCES membre (id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- tentative_connexion
--
-- Journal des tentatives, pour freiner les essais de mots de passe en série.
--
-- Sans cette table, rien n'empêche de tester des milliers de mots de passe sur
-- une adresse connue : c'est l'attaque la plus banale du web, et un hachage
-- solide n'y change rien. On compte les échecs récents par adresse ET par IP,
-- car un attaquant change d'adresse visée tout en gardant son IP, et
-- inversement.
--
-- Cette table grossit à chaque connexion : prévoir une purge des lignes de plus
-- de quelques jours, elles n'ont aucune valeur passé ce délai.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tentative_connexion (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- L'adresse SAISIE, pas un membre : une tentative sur un compte inexistant
  -- doit compter elle aussi.
  courriel        VARCHAR(254)    NOT NULL,

  -- 45 caractères couvrent une adresse IPv6 sous sa forme la plus longue.
  ip              VARCHAR(45)     NOT NULL,

  reussie         TINYINT(1)      NOT NULL DEFAULT 0,
  date_tentative  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY ix_tentative_courriel (courriel, date_tentative),
  KEY ix_tentative_ip (ip, date_tentative)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

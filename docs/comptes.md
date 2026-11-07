# Comptes, profils et chat : le plan de travail

Plan arrêté le 6 novembre 2026, à partir de la demande de l'auteur. Il
**remplace les sections 3, 5, 6, 8, 9 et 10** de [communaute.md](communaute.md),
écrites le 30 juillet pour un classement modéré. Restent valables dans ce
document-là : la section 1 (pourquoi PHP et MySQL chez OVH), la section 2
(l'architecture), la section 4 (ce qui remplace les politiques RLS, à relire
avant toute ligne de PHP) et la section 11 (éprouver le schéma chez OVH).

La règle de conduite est la plus simple qui tienne : **rien n'est construit
qui ne serve pas à parler perfs, montrer son profil et ses records.**

---

## 1. Ce que l'on construit

- **Un compte par pseudo.** Courriel et mot de passe pour se connecter, un
  pseudo public. Le pseudo suit les règles d'Instagram, ce qui permet d'y
  mettre son tag. Un interrupteur affiche le lien vers le compte Instagram.
- **Un profil public et référencé** à `/membres/<pseudo>/` : une courte
  présentation, la salle, les records et toutes les performances.
- **Des performances avec vidéo.** Mouvement, équipement, charge, date, lien
  vers la vidéo. Publiées tout de suite, vérifiées ensuite.
- **Deux badges de vérification**, indépendants. « Vérifiée par la
  communauté » au-delà de 60 % d'avis favorables. « Vérifiée par SBD.re » en
  un clic d'administrateur.
- **Un chat en salons**, réservé aux membres connectés dont l'adresse est
  vérifiée. Texte et smileys d'époque, ceux des forums phpBB. Les messages
  restent visibles 7 jours et sont conservés 1 an.
- **La modération** : un filtre de mots, un bouton de signalement sur chaque
  message, profil et performance, et une page d'administration.

**Hors de la version 1**, à ne pas construire sans nouvelle demande :
classement communauté, connexion Google, photo de profil, réactions aux
messages, messages privés, modification d'une performance, notifications,
salons créés par les membres.

---

## 2. Ce qui change par rapport au 30 juillet

| Le 30 juillet | Le 6 novembre | Pourquoi |
|---|---|---|
| Âge minimum 15 ans, tranches d'âge | 18 ans et plus, une case à cocher à l'inscription | Demande de l'auteur. Plus de mineurs, plus de tranche à gérer. |
| Nom réel facultatif, pseudo, slug | Pseudo seul, qui sert aussi d'adresse | Un champ au lieu de quatre. |
| Courriel et Google | Courriel seul | Google demande un projet Cloud et un secret. À ajouter plus tard si besoin. |
| PHPMailer et SMTP | PHPMailer et `mail()`, signé DKIM par le site | iCloud refuse les messages non signés, « [HM08] », et le SMTP d'OVH est injoignable depuis l'hébergement. Clé dans `~/sbd-re-prive/`, `db/dkim.php`. |
| Modération avant publication | Publication immédiate, vérification après | Les badges remplacent la file d'attente. |
| Classement par mouvement et par sexe | Records sur le profil, aucun classement | Demande de l'auteur : « et c'est tout ». |
| Tranches de poids et de taille, sexe | Catégorie de poids facultative, ni taille ni sexe | Moins de données collectées, rien à classer. |
| Sessions PHP | Jeton de session en base, table `jeton` | Une session PHP dure 24 minutes par défaut et dépend du réglage du serveur. La table existe déjà. |
| Jeton anti-CSRF | En-tête `Origin` exigé, cookie `SameSite=Lax` | Même protection pour une API appelée en `fetch`, sans jeton à faire circuler. |
| Îlots Svelte | Pages Astro et JavaScript natif | Svelte a été retiré du projet. Le formulaire de suggestion montre la voie. |

---

## 3. Le modèle de données

Sept tables, écrites dans [db/schema.sql](../db/schema.sql), qui fait foi
sur ce résumé. La base de production est vide : le schéma se réécrit sans
migration.

**`membre`** : `id`, `courriel` (unique), `mot_de_passe_hash`, `pseudo`
(unique, 3 à 30 caractères, `a-z 0-9 . _`, au moins une lettre ou un
chiffre, stocké en minuscules, contrôlé par une contrainte `CHECK`),
`instagram_visible` (0 ou 1), `bio` (160 caractères, facultative), `salle`
(identifiant d'une fiche de `/salles/`, facultatif), `role`
(`membre` ou `admin`), `courriel_verifie`, `banni`, `ip_inscription`,
`date_inscription`.

**`performance`** : `id`, `membre_id`, `mouvement` (`squat`, `bench`,
`deadlift`), `equipement` (`raw`, `wraps`, `equipped`), `charge_kg`,
`categorie` (catégorie de poids du site officiel, facultative),
`date_performance`, `lien_video`, `admin_id` et `date_verif_admin` (vides
tant que l'admin n'a pas cliqué), `date_creation`.

**`vote`** : `performance_id`, `membre_id`, `ok` (0 ou 1), `date_vote`. Clé
primaire sur le couple : un avis par membre et par performance, qu'il peut
changer.

**`message`** : `id`, `canal`, `membre_id`, `pseudo` (copié au moment de
l'envoi, pour que le journal reste lisible après un effacement de compte),
`texte` (500 caractères), `ip`, `date_envoi`, `masque_par`.

**`signalement`** : `id`, `type` (`message`, `membre`, `performance`),
`cible_id`, `auteur_id`, `motif` (200 caractères), `date_signalement`,
`traite`. Un seul signalement par membre et par cible.

**`jeton`** : inchangée, avec un type de plus, `session`, à côté de
`verification` et `reinitialisation`.

**`tentative_connexion`** : inchangée.

Trois décisions encodées là, à connaître avant d'y toucher.

- **Aucun total ni badge n'est stocké.** Le total se calcule à la lecture
  avec les trois meilleures barres à équipement égal. Le badge communauté se
  calcule à partir des votes. Stockés, ils périmeraient au premier vote.
- **Le chat tient dans une seule table.** « Visible 7 jours » est un filtre
  de lecture. « Conservé 1 an » est une purge des lignes plus anciennes. Aucun
  transfert vers une table de journal.
- **Toutes les tables en `utf8mb4`**, ce qui stocke les emojis. Déjà le cas
  du schéma de juillet.

---

## 4. Les règles d'accès

Elles vivent dans `public/api/lib/autorisation.php` et nulle part ailleurs.
Le refus est le comportement par défaut, l'identité vient du jeton de session,
jamais du corps de la requête.

| Geste | Qui |
|---|---|
| Lire un profil, ses performances, ses badges | Tout le monde |
| Modifier son profil, ajouter ou retirer sa performance | Le membre lui-même, adresse vérifiée, non banni |
| Voter sur une performance | Membre vérifié, non banni, inscrit depuis 30 jours, jamais sur la sienne |
| Lire et écrire dans le chat | Membre vérifié, non banni |
| Signaler | Membre vérifié |
| Badge admin, masquer un message, retirer une performance, bannir | Admin |
| Devenir admin | Personne par l'API. À la main en base. |

**Le badge communauté** s'affiche quand une performance réunit au moins
**20 votes** et au moins **60 %** de votes favorables, soit 12 sur 20. Seuls
comptent les votes de membres non bannis : un réseau de faux comptes banni
perd ses voix. Le délai de **30 jours** avant de voter freine les comptes créés
en série pour se valider soi-même.

Conséquence voulue : pendant les 30 premiers jours après l'ouverture, personne
ne peut voter. Le badge admin couvre ce démarrage.

---

## 5. Les points d'entrée

Un fichier par ressource dans `public/api/`, sur le modèle de `contact.php` :
réponse JSON, message toujours écrit par le serveur, refus par défaut.

| Fichier | Méthode | Rôle |
|---|---|---|
| `inscription.php` | POST | Créer le compte, envoyer le lien de vérification |
| `verifier.php` | GET | Valider l'adresse depuis le lien reçu |
| `connexion.php` | POST, DELETE | Ouvrir ou fermer la session |
| `mot-de-passe.php` | POST | Envoyer un lien, puis changer le mot de passe |
| `moi.php` | GET, PATCH, DELETE | Lire et modifier son profil, effacer son compte |
| `performances.php` | GET, POST, DELETE | Lister, ajouter, retirer |
| `votes.php` | POST | Donner ou changer son avis |
| `chat.php` | GET, POST | Lire les messages récents d'un salon, écrire |
| `signalements.php` | POST | Signaler un message, un profil, une performance |
| `admin.php` | GET, POST | File des signalements, actions d'admin |
| `profil-page.php` | GET | Rendre la page HTML d'un profil |
| `sitemap-membres.php` | GET | Plan de site des profils |

Bibliothèques nouvelles dans `public/api/lib/` : `session.php` (jeton,
cookie, contrôle d'`Origin`), `moderation.php` (listes de mots, filtre).
Gardées comme les autres par leur `.htaccess` et par `SBDRE_API`.

---

## 6. Les parcours

### 6.1 Inscription

```
formulaire ──> inscription.php ──> mail() avec lien ──> verifier.php
 pseudo            compte créé,         valable 24 h        courriel_verifie = 1
 courriel          non confirmé,                                  │
 mot de passe      sans session                                   ▼
 case « 18 ans et plus, j'accepte la charte »              connexion, profil
```

**Rien n'est utilisable avant le clic**, choix de l'auteur du 6 novembre :
« je ne veux pas de poubelle ». Un compte non confirmé n'ouvre aucune
session. Au bon mot de passe, la connexion lui renvoie un lien, trois par
heure au plus. Il s'efface au bout de 24 heures, et chaque inscription fait ce
ménage en passant. Si le courriel ne part pas, le compte s'efface aussitôt.

**Anti-spam maison**, choix du même jour, sans service tiers : champ appât
sur les quatre formulaires de compte, délai minimal de 3 secondes à
l'inscription, 3 inscriptions par heure et par IP, freinage des connexions
et des liens.

**Un envoi refusé laisse une trace** : `noterRefus()` écrit la raison dans
`~/sbd-re-prive/courriels-refuses.log`, avec le seul domaine du
destinataire.

**Gmail reçoit le lien de confirmation**, vérifié par l'auteur le 7 novembre
2026.

**Les adresses iCloud sont refusées à l'inscription**, choix de l'auteur du
7 novembre 2026. Apple rejette nos courriels, « [HM08] », alors que DKIM,
DMARC et SPF passent tous (relevé dans un courriel reçu à loremipsum.re) : il
bloque les serveurs d'envoi partagés d'OVH. Un compte iCloud ne recevrait
jamais son lien. L'inscription le dit et propose une autre adresse
(`DOMAINES_INJOIGNABLES`, `lib/comptes.php`). Deux voies pour lever la
restriction, écartées pour l'instant : écrire à icloudadmin@apple.com, ou
passer les courriels de compte par un service d'envoi tiers (Brevo, Mailjet),
à nommer alors comme sous-traitant dans `/confidentialite/`.

### 6.2 Profil public

`/membres/<pseudo>/` est servie par PHP, parce que les profils changent entre
deux déploiements et que la base n'est joignable que depuis OVH. Pour garder
un seul dessin du site, **Astro génère un gabarit**, `/membres/-gabarit/`,
adresse qu'aucun pseudo ne peut prendre puisque le tiret y est refusé,
avec des repères à remplacer. `profil-page.php` lit ce fichier, y écrit les
valeurs échappées et renvoie la page.

- Une règle du `.htaccess` racine envoie `/membres/<pseudo>/` vers
  `profil-page.php`. Une erreur de syntaxe dans ce fichier coupe tout le site :
  à éprouver en ligne juste après le déploiement.
- **Référencement** : titre « pseudo, records de force athlétique | SBD.re »,
  description tirée des records, données structurées `ProfilePage`, plan de
  site `sitemap-membres.php` déclaré dans `robots.txt`. Un profil sans
  performance reçoit `noindex`, une page vide ne mérite pas d'être indexée.
- Le lien Instagram, écrit par PHP, échappe au middleware d'Astro : PHP pose
  lui-même `target`, `rel` et `aria-describedby`.

### 6.3 Performance et vérification

```
ajout ──> visible sur le profil, « non vérifiée »
            │
            ├── votes des membres ──> 20 votes et 60 % d'avis favorables
            │                          ──> badge « Vérifiée par la communauté »
            │
            └── clic de l'admin ─────> badge « Vérifiée par SBD.re »
```

- **Le lien vidéo est obligatoire** et limité à Instagram, YouTube et TikTok.
  Il s'affiche en lien sortant, jamais en lecteur intégré : un lecteur
  chargerait un script tiers, interdit sans consentement.
- **Une performance ne se modifie pas.** On la retire et on la refait, ce qui
  remet ses votes à zéro. Une modification qui garderait les votes ferait
  valider 300 kg avec les votes donnés à 150 kg.
- **Les records du profil** sont la meilleure barre par mouvement et par
  équipement, et le total quand les trois existent. Badge affiché à côté.

### 6.4 Chat

- **Cinq salons fixes**, écrits dans `chat.php` : `general`, `squat`,
  `developpe-couche`, `souleve-de-terre`, `competitions`. Un salon donne un
  thème, pas une règle : on y parle de ce qu'on veut, la charte s'applique
  partout.
- **Lecture par interrogation** toutes les 5 secondes quand l'onglet est
  visible, arrêtée quand il est masqué. Un mutualisé ne garde pas de connexion
  ouverte, donc pas de WebSocket. À revoir au-delà d'une cinquantaine de
  personnes connectées en même temps.
- **Écriture** : 500 caractères, texte seul, affiché par `textContent`. Les
  adresses ne deviennent pas des liens, ce qui coupe court au hameçonnage.
- **Smileys d'époque**, choix de l'auteur du 6 novembre : les codes des
  forums phpBB. Le membre tape `:)` ou clique sur la barre de 16 smileys sous
  le champ, qui insère le code. **La base garde le code tel quel**, le journal
  reste du texte. Le navigateur le remplace à l'affichage par une image,
  créée par `document.createElement('img')`, jamais par `innerHTML`. Un code
  ne se remplace que seul entre deux espaces ou en bout de message :
  « 5x8) » reste « 5x8) ». Les emojis du clavier passent aussi, `utf8mb4` les
  stocke.
- **Les images d'époque**, choix de l'auteur du 6 novembre : le dessin des
  smileys de phpBB, dans sa version vectorielle, `vector-remaster/svg/` du
  dépôt `rednoah/phpBB-smilies`. C'est celle que phpBB livre lui-même depuis
  la 3.3, créditée dans son `CREDITS.txt`. Licence Apache 2.0 : le texte de
  la licence voyage avec les fichiers, dans `public/smileys/LICENSE`.
  16 fichiers SVG, 23,5 Ko en tout, servis par sbd.re, aucun script tiers.
  Vérifié le 6 novembre sur quatre fichiers : ni script, ni ressource
  extérieure. À revérifier sur les seize à la copie.
- **Pourquoi pas les GIF d'origine** : 15 pixels, flous sur un téléphone, et
  sous GPL 2, la licence de phpBB. Le SVG garde le même dessin, net à toute
  taille. Il perd l'animation de trois d'entre eux, `:cry:`, `:oops:` et
  `:roll:`.
- **Affichage** : hauteur `1.25em`, alignée sur le texte, `alt` en français
  pour les lecteurs d'écran. Ces images sont du contenu, pas des icônes :
  elles restent hors de la règle du jeu unique Tabler, comme `Coupe.astro`.
  Le dégradé jaune et orange se teste sur les deux thèmes, le sombre d'abord.

| Code | Fichier phpBB | `alt` | Code | Fichier phpBB | `alt` |
|---|---|---|---|---|---|
| `:)` | `icon_e_smile` | sourire | `:lol:` | `icon_lol` | mort de rire |
| `:D` | `icon_e_biggrin` | grand sourire | `:x` | `icon_mad` | en colère |
| `;)` | `icon_e_wink` | clin d'œil | `:P` | `icon_razz` | tire la langue |
| `:(` | `icon_e_sad` | triste | `:oops:` | `icon_redface` | gêné |
| `:o` | `icon_e_surprised` | surpris | `:cry:` | `icon_cry` | pleure |
| `:shock:` | `icon_eek` | choqué | `:roll:` | `icon_rolleyes` | lève les yeux au ciel |
| `:?` | `icon_e_confused` | perplexe | `:mrgreen:` | `icon_mrgreen` | rire vert |
| `8)` | `icon_cool` | cool | `:idea:` | `icon_idea` | idée |

- **Freinage** : un message toutes les 3 secondes par membre, lu sur la date
  de son dernier message.
- **Conservation** : la lecture ne rend que les 7 derniers jours. Chaque
  écriture purge par lots de 100 les lignes de plus d'un an. Aucune tâche
  planifiée à configurer.

### 6.5 Modération

```
filtre de mots ──> message refusé avant d'entrer en base
signalement ─────> file de admin.php ──> masquer, retirer, bannir, classer
```

Un banni perd sa session et ne se reconnecte plus. Son profil sort du site et
du plan de site. Ses messages restent dans le journal d'un an.

---

## 7. Le ton des contenus

**Profils : tout public.** Pseudo et présentation passent un filtre strict :
grossièretés, termes sexuels, insultes, termes haineux.

**Chat : plus libre, dans une limite.** Les grossièretés passent. Le contenu
sexuel, le racisme et toute haine visant une origine, une religion, un sexe,
une orientation ou un handicap sont refusés. Le harcèlement et la publication
d'informations personnelles d'autrui aussi.

Le filtre ne voit pas tout. Il arrête l'évident, le signalement et l'admin
font le reste. Les listes de mots vivent dans `moderation.php` et se
complètent à l'usage. Les règles s'écrivent dans une page `/communaute/charte/`,
que l'inscription fait accepter.

---

## 8. Vie privée et obligations, à faire valider

Ces points relèvent du droit. Ce plan les recense sans les trancher.

- **`/confidentialite/` à compléter** avant l'ouverture : courriel, pseudo,
  présentation, performances et liens vidéo, votes, messages visibles 7 jours
  et conservés 1 an, adresse IP conservée 1 an.
- **La conservation d'un an.** La loi pour la confiance dans l'économie
  numérique et son décret du 20 octobre 2021 fixent ce qu'un hébergeur de
  contenus conserve pour identifier l'auteur d'un contenu, dont un an pour
  les données techniques comme l'adresse IP. D'où l'IP stockée avec chaque
  message et à l'inscription. Durées et liste exacte à confirmer.
- **Signaler doit être possible** pour tout visiteur, membre ou non : le bouton
  pour les membres, une adresse de contact dans les mentions légales pour les
  autres.
- **L'effacement de compte** retire profil, performances et votes. Les
  messages sortent du chat et restent dans le journal jusqu'à leur année,
  avec le pseudo copié.

---

## 9. Les tests destinés à échouer

Chacun de ces gestes **doit** être refusé avant l'ouverture.

| Tentative | Attendu |
|---|---|
| Voter sur sa propre performance | refus |
| Voter avec un compte de moins de 30 jours | refus |
| Badge communauté à 19 votes, ou à 11 favorables sur 20 | pas de badge |
| Voter deux fois pour peser double | un seul vote compté |
| Poser le badge admin avec un compte membre | refus |
| Se nommer admin par `PATCH moi.php` | refus |
| Retirer la performance d'un autre | refus |
| Agir au nom d'un autre en envoyant `membre_id` | refus, l'identité vient de la session |
| Écrire sans en-tête `Origin` de sbd.re | refus |
| Lire ou écrire le chat sans adresse vérifiée | refus |
| Lire un message de plus de 7 jours par l'API | rien ne revient |
| Envoyer un message avec un terme interdit | refus |
| Enchaîner dix messages en dix secondes | freinage |
| Choisir un pseudo pris, ou d'une autre casse | refus |
| Lien vidéo hors Instagram, YouTube, TikTok | refus |
| Se connecter avec un compte banni | refus |
| Réutiliser un lien de vérification ou de réinitialisation | refus |
| Se connecter sans avoir confirmé son adresse | pas de session, un nouveau lien |
| Retrouver un compte non confirmé de plus de 24 heures | effacé |
| Envoyer l'inscription moins de 3 secondes après l'affichage | refus |
| Injecter du HTML dans une présentation ou un message | affiché tel quel, jamais interprété |
| Injecter du SQL dans un champ | aucun effet |
| Demander `config.php` ou `lib/` en HTTP | refus |
| Effacer son compte puis ouvrir son profil | 404 |

---

## 10. Pas à pas

Chaque phase se vérifie avant la suivante. Une phase vaut une PR.

### Phase 0, l'auteur

- [x] Répondre aux questions de la section 11, fait le 6 novembre.
- [ ] Confirmer que la base de **production** existe, en plus de celle de test
      éprouvée le 30 juillet.
- [ ] Dans un courriel reçu du formulaire de contact, relever la ligne
      `Authentication-Results` : `spf=pass` confirme que `mail()` suffira aux
      courriels de vérification.
- [ ] Faire relire la section 8 par quelqu'un qui connaît le droit du
      numérique, ou accepter le risque.

### Phase 1, le schéma

- [x] Réécrire `db/schema.sql` selon la section 3, fait le 6 novembre.
- [x] Mettre `db/verifier.php` à jour : il repère une table restée en
      version de juillet et passe 43 essais : 28 refus attendus, 8 écritures
      valides, 7 contrôles après l'effacement d'un compte.
- [x] Sur la base de test : tables de juillet supprimées et schéma chargé
      dans phpMyAdmin, puis `php verifier.php` en SSH.
- [x] **Fini le 6 novembre 2026** : 56 réussites, 0 échec, PHP 8.5.12 en
      ligne de commande. Les 43 essais sont passés, dont la contrainte
      `REGEXP_LIKE` du pseudo, qui n'avait jamais tourné.

**Deux comptes chez OVH, à ne pas confondre.** Le login principal,
`loremis`, arrive dans le dossier de l'hébergement, voit `sbd-re-prive/`, et
son SSH tourne en PHP 8. Un second login, `lorem`, est enfermé dans
`sbd-re/`, le dossier public : il ne voit pas `sbd-re-prive/`, et son SSH
tourne en PHP 5.4, qui refuserait le script. Les vérificateurs se lancent
donc avec le login principal. Et le client `ftp` de Windows ne lance aucune
commande : il ne fait que déplacer des fichiers, en clair.

**Une correction après coup, le 6 novembre.** En phase 2, un test a montré
que `$`, en fin d'expression, accepte aussi un retour à la ligne final, en PHP
comme dans MySQL. La contrainte du pseudo laissait donc passer « abc » suivi
d'un saut de ligne. Elle finit désormais par `\z`, et `verifier.php` l'essaie :
44 essais, 57 réussites attendues. La base de production n'a jamais été
rechargée : le 7 novembre, `verifier.php` y trouvait encore l'ancienne
contrainte. `db/migration-2026-11-07-pseudo.sql` la remplace sans toucher aux
comptes.

### Phase 2, le socle PHP

- [x] `session.php` : 32 octets tirés au hasard, empreinte en base, cookie
      `__Host-sbdre` en `Secure`, `HttpOnly`, `SameSite=Lax`, 30 jours sans
      prolongation, origine exigée sur toute écriture.
- [x] `autorisation.php` réécrit selon la section 4, badge communauté
      compris, et `db/verifier-autorisation.php` : 67 règles, sans base.
- [x] `courriel.php` réduit à `envoyerCourriel()`, partagée par le
      partenariat, les suggestions et bientôt les comptes. PHPMailer et
      `verifier-courriel.php` retirés, `config.exemple.php` réduit à la base.
- [x] **Fini le 6 novembre 2026** : `php -l` passe sur chaque fichier, 67
      règles sur 67 en local. Les deux formulaires, lancés sur un serveur PHP
      local, passent par la fonction partagée avec les mêmes en-têtes
      qu'avant, relevés dans le journal de `mail()`.

**Le pseudo ne change pas en version 1.** Il est l'adresse du profil : le
changer casserait chaque lien partagé. `CHAMPS_PROFIL` ne l'accepte donc
pas. Une ligne à ajouter si l'auteur en décide autrement.

### Phase 3, les comptes

- [x] `inscription.php`, `verifier.php`, `connexion.php`, `mot-de-passe.php`,
      `moi.php`, sur `lib/comptes.php` (formes, jetons, freinages).
- [x] Pages Astro `/compte/inscription/`, `/compte/connexion/`, `/compte/`
      (mon profil), `/compte/mot-de-passe/`.
- [x] La charte `/communaute/charte/`, reprise de la section 7.
- [x] En local, le 6 novembre : 88 règles sur 88, et 24 appels aux points
      d'entrée sur un serveur PHP, chacun avec la réponse attendue jusqu'à la
      base (méthode, origine, appât, chaque refus de forme, jeton mal formé,
      cookie). Pages mesurées à 375 px : aucun débordement, contrôles à 44 px.
- [x] **Fini le 7 novembre 2026**, vérifié par l'auteur avec une adresse Gmail : création, courriel reçu, confirmation, connexion, profil, déconnexion, mot de passe oublié, effacement. **Fini quand** un compte se crée, se vérifie par le courriel reçu, se
      connecte, se modifie et s'efface, en production.

Choix faits en écrivant, à connaître :

- **Les pages de compte portent `prive`** (`BaseLayout`) : `noindex`, hors
  du plan de site, et Clarity n'y est jamais chargé, même après
  consentement. Elles restent hors du menu jusqu'à la phase 9.
- ~~L'inscription ouvre la session~~ : remplacé le 6 novembre par la
  confirmation d'abord, section 6.1.
- **Une adresse déjà inscrite se dit telle quelle.** La taire obligerait à
  écrire au titulaire à chaque essai, ce qui ferait du formulaire un moyen
  d'inonder sa boîte. La demande de mot de passe, elle, répond toujours la
  même phrase.
- **Le lien de mot de passe passe son jeton après le « # »**, qui ne part
  jamais au serveur ni dans ses journaux. La page l'efface de l'adresse.
- **Mot de passe : 10 caractères au moins, 72 octets au plus**, sans règle
  de composition. Au-delà de 72 octets, bcrypt ignore la suite.
- **Freinage de la connexion** : 5 échecs en 15 minutes par adresse, 20 par
  IP, plus large parce que les opérateurs mobiles de l'île font sortir
  beaucoup de clients par la même adresse.
- **Pseudos réservés** (`admin`, `sbd.re`, `moderateur`…), écrits dans
  `lib/comptes.php` : ils feraient passer un membre pour le site.
- **Le filtre de mots du pseudo et de la présentation attend la phase 7**,
  avec `moderation.php`. Sans ouverture avant la phase 9, aucun profil
  public n'existe d'ici là.

### Phase 4, les profils publics

- [x] Gabarit Astro `/membres/-gabarit/`, exclu du plan de site, huit repères
      `%%NOM%%` que PHP remplace en un seul passage (`strtr`).
- [x] `profil-page.php` sur `lib/profil.php`, dont `rendreProfil()` est pure ;
      règles du `.htaccess` ; `sitemap-membres.php` servi à
      `/sitemap-membres.xml` et déclaré dans l'index par `customSitemaps`,
      plutôt qu'une ligne de plus dans `robots.txt`.
- [x] Les salles viennent de `/api/salles.json`, généré par Astro depuis la
      collection de `/salles/`.
- [x] En local, le 7 novembre : deux profils inventés rendus avec le vrai
      gabarit, 16 contrôles verts (présentation piégée affichée en texte,
      JSON-LD lisible, adresse canonique, `noindex` sans performance,
      Instagram selon la case, salle disparue ignorée), mesurés à 375 et
      1 280 px dans les deux thèmes, liens de la fiche à 44 px.
- [x] **Fini le 7 novembre 2026.** Le `.htaccess` éprouvé en ligne dès le
      déploiement (404 du gabarit et de `/membres/`, 301 en minuscules, plan
      des profils servi), et le test des résultats enrichis de Google accepte
      un vrai profil, vérifié par l'auteur.

**Un changement de pseudo ou de nom Instagram se fait en trois temps**,
demande de l'auteur du 7 novembre : le champ est grisé (`readonly`, qui part
quand même avec le formulaire, à la différence de `disabled`), une case le
débloque, et l'enregistrement d'une nouvelle valeur ouvre une fenêtre de
confirmation, un `<dialog>` natif comme celui de « Proposer ». `moi.php`
refuse le changement sans la confirmation. Pendant le délai d'un an, la case
du pseudo est désactivée. Retirer son nom Instagram n'engage à rien et ne
demande pas de confirmation.

**Le nom Instagram est distinct du pseudo**, demande de l'auteur du
7 novembre : il se pose dans `/compte/`, unique, et se change une fois tous
les six mois. Le retirer reste libre, sans remettre l'horloge à zéro.
`db/migration-2026-11-07.sql` ajoute ses colonnes à une base déjà chargée.

### Phase 5, les performances

- [x] `performances.php` (lister, ajouter, retirer) sur `lib/performances.php`,
      dont les formes sont pures : lien Instagram, YouTube ou TikTok en
      https, charge de 600 kg au plus et deux décimales, date réelle depuis
      2000 et jamais future, catégorie facultative au format « 93 » ou
      « 120+ », dix ajouts par jour.
- [x] Records et liste sur le profil : par équipement, la meilleure barre de
      chaque mouvement, à charge égale la vérifiée puis la plus ancienne, et
      le total quand les trois existent. Le profil s'indexe dès sa première
      barre, et sa description dit ses records quand il n'a pas de
      présentation.
- [x] Dans `/compte/`, le formulaire et la liste, « Retirer » passant par la
      fenêtre de confirmation.
- [x] En local, le 7 novembre : 152 règles sur 152, 26 contrôles sur deux
      profils rendus avec le vrai gabarit, la page de compte éprouvée contre
      une API simulée (envoi, liste, retrait confirmé, cibles à 44 px).
- [ ] **Fini quand** une performance apparaît sur le profil et disparaît
      quand on la retire, en production.

**Laissé de côté** : l'haltérophilie n'a pas de performances, seulement son
forum (forum.md) ; les mouvements restent ceux de la force athlétique.

**L'espace du membre, revu le 7 novembre**, à la demande de l'auteur :

- **`/compte/` est l'accueil du membre** : ses performances d'abord, le
  formulaire replié dessous (ouvert d'office tant qu'il n'y en a aucune),
  puis « Ma position », puis « Mes sujets », ajoutés le 8 novembre.
- **Les réglages passent derrière un engrenage**, `/compte/reglages/` :
  profil, session, effacement. L'icône est l'`outline/settings` de Tabler,
  accompagnée du mot « Réglages ».
- **« Ma position »** dit où chaque record placerait le membre au
  classement officiel de La Réunion, dans sa catégorie et son équipement :
  le total quand ses trois barres partagent une catégorie, puis chaque
  mouvement. Les marques officielles viennent de `/api/positions.json`,
  généré avec le site sur le découpage de `/simulateur/` (le total du full
  power seul), des nombres sans noms. Le sexe se déduit de la catégorie,
  les catégories féminines et masculines ne se recoupant pas. Recompté par
  un script indépendant le 7 novembre : 26 catégories, aucun écart.
- **Vérifiée en vert (`--succes`), non vérifiée en ambre (`--alerte`)**, en
  pastille au filet de sa couleur, le mot toujours écrit : 5,9:1 en clair,
  9 à 10,4:1 en sombre. Jamais de rouge, que le noir du thème sombre
  rapprocherait du duo interdit.
- La fenêtre de confirmation devient un composant,
  `FenetreConfirmation.astro`, ouvert par `confirmer()` de
  `lib/api-compte.ts`.

### Phase 6, la vérification

- [x] Le badge se calcule à la lecture, jamais stocké : `badgeDe()` de
      `lib/performances.php` rend « admin » si l'admin a vérifié, sinon
      « communaute » à 20 avis dont 60 % favorables (`badgeCommunaute()`),
      sinon rien. Une seule requête, `SQL_PERFORMANCES_VOTES`, compte les
      avis pour le profil, l'espace membre et le fil, et écarte ceux des
      membres bannis.
- [x] `votes.php` : le fil (GET) et l'avis (POST, `SQL_VOTER`), qui se change
      tant que la barre attend. Vote ouvert 30 jours après l'inscription,
      jamais sur sa propre barre (`peutVoter()`).
- [x] Le fil « À vérifier » vit à `/compte/a-verifier/` et non sur
      `/communaute/`, comme prévu d'abord : voter demande une session, et
      `/communaute/` reste la page d'attente publique jusqu'à la phase 9.
      Les cinquante barres non vérifiées les plus récentes des autres membres.
- [x] Le clic admin dans `admin.php`, qui garde `admin_id`. Un compte non
      admin y reçoit 404. Devenir admin se fait en base :
      `UPDATE membre SET role = 'admin' WHERE pseudo = '…';`
- [x] À charge égale, une barre vérifiée par la communauté garde le record
      comme une barre vérifiée par l'admin.
- [x] En local, le 7 novembre : 158 règles sur 158, dont les trois cas du
      badge ; le fil éprouvé contre une API simulée (avis donné puis changé,
      badge admin, compte de moins de 30 jours aux boutons grisés, cibles à
      44 px, filet d'accent sous l'aplat en clair).
- [x] `php verifier.php` sur le serveur : 65 réussites attendues, dont le
      vote de l'API qui remplace l'avis précédent et le membre banni écarté
      du compte. Il charge `~/sbd-re/api/lib/performances.php`, que le
      déploiement dépose : le site doit donc être en ligne avant.
- [x] **Fini quand** le calcul passe ses trois cas en local, qu'un vote
      compte en production, et qu'un compte de moins de 30 jours est refusé.
      Validé par l'auteur le 7 novembre.
      Vingt comptes de test seraient de trop : la date d'inscription se
      recule à la main sur la base de test.

### Phase 7, le chat

**Reprise par l'étape 5 de [forum.md](forum.md)**, et construite le 7 novembre
avec elle : voir « Construit le 7 novembre » dans ce document. Les cases
ci-dessous restent le plan d'origine.

- [x] `chat.php`, les cinq salons, la purge.
- [x] Copier les 16 SVG et la licence dans `public/smileys/`, contrôler
      chaque fichier : ni `<script>`, ni `href` extérieur.
- [x] L'interface, sur `/communaute/chat/` jusqu'à l'ouverture : salons, messages, champ, barre de
      smileys. Le remplacement des codes garde un autotest : `:)` devient
      l'image `icon_e_smile`, « 5x8) » ne bouge pas, `<b>` reste du texte.
- [x] `lib/moderation.php` et ses deux listes.
- [ ] **Fini quand** deux navigateurs se parlent, qu'un terme interdit est
      refusé, et que le freinage mord.

### Phase 8, la modération

- [x] `signalements.php`, boutons sur message, profil et performance.
- [x] `admin.php` et sa page, `/compte/admin/`, masquée du menu et protégée
      par le rôle. Construits le 7 novembre avec l'étape 8 de forum.md.
- [ ] **Fini quand** un signalement arrive dans la file et qu'un bannissement
      coupe la session.

### Phase 9, l'ouverture

- [x] `/confidentialite/` et `/mentions-legales/` complétées, le 7 novembre.
      Chaque durée écrite est tenue par le code : l'adresse IP de
      l'inscription et des messages du forum s'efface au bout d'un an
      (`oublierAdressesIp`, `lib/bdd.php`, ajoutée pour l'occasion : elle
      restait sans limite). Les mentions légales disent comment signaler un
      contenu sans compte.
- [x] `/communaute/` refaite : l'entrée, le forum, le fil, les barres à
      vérifier, le compte, puis le chat (`components/Chat.astro`).
      `/communaute/chat/` renvoie à `/communaute/#chat`. « Bientôt » retiré,
      Forum entre au menu, les tranches `COMMUNITY_*_BRACKETS` et leur
      infographie sont retirées. Les seuils affichés se lisent dans
      `autorisation.php` (`src/lib/communaute.ts`).
- [x] Le forum s'indexe (`FORUM_OUVERT`), avec son plan, `/sitemap-forum.xml`.
- [x] Les tests de la section 9 : la plupart passent par
      `db/verifier-autorisation.php` et `db/verifier.php` (91 réussites).
      À la main, en production : `/api/lib/bdd.php` en HTTP, la connexion
      d'un compte banni, le profil d'un compte effacé. Validés par l'auteur
      le 8 novembre.
- [x] Version 1.10.0 : nouvelle rubrique, le deuxième chiffre monte.

---

## 11. Décisions du 6 novembre

Réponses de l'auteur, reportées dans les sections concernées.

1. **Votes minimum : 20**, à 60 % de favorables. Section 4.
2. **Délai avant de voter : 30 jours.** Section 4.
3. **Case « 18 ans et plus »** fondue avec l'acceptation de la charte : oui,
   pour poser la règle par écrit. Section 6.1.
4. **Les cinq salons restent**, sujets libres dans chacun. Section 6.4.
5. **Smileys d'époque**, les codes phpBB, affichés avec les images de phpBB
   en SVG, au lieu d'une barre d'emojis. Section 6.4.

---

## 12. Demandes du 7 novembre, à organiser

Notées telles que l'auteur les a formulées. Chacune se planifie à part,
avec ses questions, avant d'écrire une ligne.

- **Le pseudo se change une fois par an, sauf demande spéciale à l'admin.**
  Fait le 7 novembre, dans `/compte/`. Le premier changement est libre, pour
  rattraper une faute de frappe, puis `DELAI_PSEUDO_MOIS` s'applique.
  L'ancien pseudo reste réservé au membre (`pseudo_precedent`) et son
  adresse renvoie en 301 vers le profil renommé. Pour une demande spéciale,
  en attendant la page d'admin de la phase 8 :
  `UPDATE membre SET date_pseudo = NULL WHERE pseudo = '…'` dans phpMyAdmin.
- **Le forum, le fil, les notifications et les avatars** ont leur propre
  plan, [forum.md](forum.md), préparé le même jour : catégories proposées,
  modèle de données, ordre de construction et questions ouvertes. Le chat y est
  revu (personnes en ligne, lecture des visiteurs) et sa phase 7 y est
  reprise.

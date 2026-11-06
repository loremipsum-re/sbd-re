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
  vérifiée. Texte et emojis. Les messages restent visibles 7 jours et sont
  conservés 1 an.
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
| PHPMailer et SMTP | `mail()`, comme les formulaires | Prouvé en production depuis le 10 septembre. Une dépendance de moins. |
| Modération avant publication | Publication immédiate, vérification après | Les badges remplacent la file d'attente. |
| Classement par mouvement et par sexe | Records sur le profil, aucun classement | Demande de l'auteur : « et c'est tout ». |
| Tranches de poids et de taille, sexe | Catégorie de poids facultative, ni taille ni sexe | Moins de données collectées, rien à classer. |
| Sessions PHP | Jeton de session en base, table `jeton` | Une session PHP dure 24 minutes par défaut et dépend du réglage du serveur. La table existe déjà. |
| Jeton anti-CSRF | En-tête `Origin` exigé, cookie `SameSite=Lax` | Même protection pour une API appelée en `fetch`, sans jeton à faire circuler. |
| Îlots Svelte | Pages Astro et JavaScript natif | Svelte a été retiré du projet. Le formulaire de suggestion montre la voie. |

---

## 3. Le modèle de données

Six tables. La base de production est vide : le schéma se réécrit sans
migration.

**`membre`** : `id`, `courriel` (unique), `mot_de_passe_hash`, `pseudo`
(unique, 3 à 30 caractères, `a-z 0-9 . _`, stocké en minuscules),
`instagram_visible` (0 ou 1), `bio` (160 caractères, facultative), `salle`
(identifiant d'une fiche de `/salles/`, facultatif), `role`
(`membre` ou `admin`), `courriel_verifie`, `banni`, `ip_inscription`,
`date_inscription`.

**`performance`** : `id`, `membre_id`, `mouvement` (`squat`, `bench`,
`deadlift`), `equipement` (`raw`, `wraps`, `equipped`), `charge_kg`,
`categorie` (catégorie de poids du site officiel, facultative),
`date_performance`, `lien_video`, `admin_id` et `date_verif_admin` (vides
tant que l'admin n'a pas cliqué), `date_creation`.

**`vote`** : `performance_id`, `membre_id`, `ok` (0 ou 1), `date`. Clé
primaire sur le couple : un avis par membre et par performance, qu'il peut
changer.

**`message`** : `id`, `canal`, `membre_id`, `pseudo` (copié au moment de
l'envoi, pour que le journal reste lisible après un effacement de compte),
`texte` (500 caractères), `ip`, `date`, `masque_par`.

**`signalement`** : `id`, `type` (`message`, `membre`, `performance`),
`cible_id`, `auteur_id`, `motif` (200 caractères), `date`, `traite`.

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
| Voter sur une performance | Membre vérifié, non banni, inscrit depuis 7 jours, jamais sur la sienne |
| Lire et écrire dans le chat | Membre vérifié, non banni |
| Signaler | Membre vérifié |
| Badge admin, masquer un message, retirer une performance, bannir | Admin |
| Devenir admin | Personne par l'API. À la main en base. |

**Le badge communauté** s'affiche quand une performance réunit au moins
**3 votes** (à confirmer) et au moins **60 %** de votes favorables. Le délai de
7 jours avant de voter freine les comptes créés en série pour se valider soi-même.

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
 courriel          non vérifié                                    │
 mot de passe                                                     ▼
 case « 18 ans et plus, j'accepte la charte »              connexion, profil
```

Sans adresse vérifiée, le membre se connecte mais ne peut ni publier, ni
voter, ni entrer dans le chat. Freinage : 3 inscriptions par heure et par IP,
compté dans `membre.ip_inscription`.

### 6.2 Profil public

`/membres/<pseudo>/` est servie par PHP, parce que les profils changent entre
deux déploiements et que la base n'est joignable que depuis OVH. Pour garder
un seul dessin du site, **Astro génère un gabarit**, `/membres/_gabarit/`,
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
            ├── votes des membres ──> 3 votes et 60 % d'avis favorables
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
  `developpe-couche`, `souleve-de-terre`, `competitions`.
- **Lecture par interrogation** toutes les 5 secondes quand l'onglet est
  visible, arrêtée quand il est masqué. Un mutualisé ne garde pas de connexion
  ouverte, donc pas de WebSocket. À revoir au-delà d'une cinquantaine de
  personnes connectées en même temps.
- **Écriture** : 500 caractères, texte seul, affiché par `textContent`. Les
  adresses ne deviennent pas des liens, ce qui coupe court au hameçonnage. Une
  barre de 16 emojis sous le champ, le clavier du téléphone fait le reste.
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
| Voter avec un compte de moins de 7 jours | refus |
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
| Injecter du HTML dans une présentation ou un message | affiché tel quel, jamais interprété |
| Injecter du SQL dans un champ | aucun effet |
| Demander `config.php` ou `lib/` en HTTP | refus |
| Effacer son compte puis ouvrir son profil | 404 |

---

## 10. Pas à pas

Chaque phase se vérifie avant la suivante. Une phase vaut une PR.

### Phase 0, l'auteur

- [ ] Répondre aux questions de la section 11.
- [ ] Confirmer que la base de **production** existe, en plus de celle de test
      éprouvée le 30 juillet.
- [ ] Dans un courriel reçu du formulaire de contact, relever la ligne
      `Authentication-Results` : `spf=pass` confirme que `mail()` suffira aux
      courriels de vérification.
- [ ] Faire relire la section 8 par quelqu'un qui connaît le droit du
      numérique, ou accepter le risque.

### Phase 1, le schéma

- [ ] Réécrire `db/schema.sql` selon la section 3.
- [ ] Mettre `db/verifier.php` à jour, le passer en SSH sur la base de test.
- [ ] **Fini quand** le script donne un bilan vert chez OVH.

### Phase 2, le socle PHP

- [ ] `session.php` : jeton aléatoire, empreinte en base, cookie `Secure`,
      `HttpOnly`, `SameSite=Lax`, 30 jours, contrôle d'`Origin`.
- [ ] Réécrire `autorisation.php` selon la section 4, et
      `db/verifier-autorisation.php` qui la teste sans base.
- [ ] Remplacer `courriel.php` par un appel à `mail()` partagé avec les
      formulaires. Retirer PHPMailer.
- [ ] **Fini quand** `php -l` passe sur chaque fichier et que les règles sont
      toutes vertes en local.

### Phase 3, les comptes

- [ ] `inscription.php`, `verifier.php`, `connexion.php`, `mot-de-passe.php`,
      `moi.php`.
- [ ] Pages Astro `/compte/inscription/`, `/compte/connexion/`, `/compte/`
      (mon profil), `/compte/mot-de-passe/`.
- [ ] La charte `/communaute/charte/`.
- [ ] **Fini quand** un compte se crée, se vérifie par le courriel reçu, se
      connecte, se modifie et s'efface, en production.

### Phase 4, les profils publics

- [ ] Gabarit Astro `/membres/_gabarit/`, exclu du plan de site.
- [ ] `profil-page.php`, règle du `.htaccess`, `sitemap-membres.php`,
      ligne `Sitemap` dans `robots.txt`.
- [ ] **Fini quand** un profil s'affiche au dessin du site, en clair et en
      sombre, et que le validateur de données structurées de Google l'accepte.

### Phase 5, les performances

- [ ] `performances.php`, ajout et retrait depuis `/compte/`.
- [ ] Records et liste sur le profil.
- [ ] **Fini quand** une performance apparaît sur le profil et disparaît
      quand on la retire.

### Phase 6, la vérification

- [ ] `votes.php` et le calcul du badge communauté.
- [ ] Un fil « À vérifier » sur `/communaute/`, les plus récentes d'abord.
- [ ] Le clic admin dans `admin.php`.
- [ ] **Fini quand** trois comptes de test font passer une performance au
      badge communauté, et qu'un compte de moins de 7 jours est refusé.

### Phase 7, le chat

- [ ] `chat.php`, les cinq salons, la purge.
- [ ] L'interface sur `/communaute/` : salons, messages, champ, barre d'emojis.
- [ ] `moderation.php` et ses deux listes.
- [ ] **Fini quand** deux navigateurs se parlent, qu'un terme interdit est
      refusé, et que le freinage mord.

### Phase 8, la modération

- [ ] `signalements.php`, boutons sur message, profil et performance.
- [ ] `admin.php` et sa page, masquée du menu et protégée par le rôle.
- [ ] **Fini quand** un signalement arrive dans la file et qu'un bannissement
      coupe la session.

### Phase 9, l'ouverture

- [ ] `/confidentialite/` et `/mentions-legales/` complétées.
- [ ] `/communaute/` refaite : elle devient l'entrée, chat et fil à vérifier.
      Retirer l'étiquette « Bientôt » du menu, et les tranches
      `COMMUNITY_*_BRACKETS` devenues inutiles, avec l'infographie qui les lit.
- [ ] Les tests de la section 9, passés en entier.
- [ ] Version : nouvelle rubrique, donc le deuxième chiffre monte.

---

## 11. Questions ouvertes

Chaque réponse se reporte dans ce document avant de coder.

1. **Combien de votes au minimum** pour le badge communauté ? Proposé : 3.
   Avec 60 %, cela veut dire 2 avis favorables sur 3.
2. **Le délai de 7 jours avant de voter** convient-il ?
3. **Une case « 18 ans et plus »** à l'inscription, fondue avec l'acceptation
   de la charte, convient-elle ? Elle coûte une ligne et pose la règle par écrit.
4. **Les cinq salons** : `general`, `squat`, `developpe-couche`,
   `souleve-de-terre`, `competitions`. À garder, renommer, compléter ?
5. **Les 16 emojis** de la barre : à choisir, ou laisser la proposition.

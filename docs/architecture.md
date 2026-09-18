# Architecture d'ensemble

Évaluation du 18 septembre 2026, en version 1.1. Chaque chiffre de ce document
a été mesuré ce jour-là, sur le dépôt ou sur le site en ligne.

Ce document décrit **la forme** du système et ses points fragiles. Le
fonctionnement au quotidien est dans le [README](../README.md), l'histoire des
décisions dans [parcours.md](parcours.md), la future partie communauté dans
[communaute.md](communaute.md).

---

## 1. Ce que le système doit faire

**Fonctions**

- Publier classements, records, fiches d'athlètes et compétitions à partir
  d'OpenPowerlifting, remis à jour chaque mois sans intervention.
- Publier du contenu éditorial sans toucher au code : actualités, salles,
  compléments aux fiches, partenaires.
- Recevoir les demandes de partenariat.
- Demain, la partie communauté : comptes, performances de salle, modération.

**Exigences**

- **Coût** : l'hébergement OVH déjà payé, aucun service payant de plus.
- **Vie privée** : aucun script tiers sans consentement. Vérifié en production :
  `/classement/` ne fait aucune requête hors de sbd.re avant l'accord.
- **Mobile d'abord**, sur le réseau réunionnais, avec un serveur en métropole.
- **Entretien par une seule personne**, développeur WordPress, qui ne pratique
  ni Node ni git en ligne de commande.

**Contraintes de l'hébergement**

OVH mutualisé : Apache, PHP 8.4, MySQL 8.4 hors mode strict, aucun Node côté
serveur. La base n'est joignable que depuis l'hébergement.

---

## 2. Vue d'ensemble

```
 OpenPowerlifting                  Auteur                     Auteur
 dump zip, ~168 Mo                 /admin/ (Sveltia CMS)      PR fusionnée
      │ le 1er du mois               │ jeton GitHub             │
      ▼                              │                          │
 Actions « mise à jour »             │                          │
 régénère results.json               │                          │
      │ commit                       │ commit                   │ push
      ▼                              ▼                          ▼
 ┌──────────────────────── dépôt GitHub, branche main ───────────────────────┐
 └───────────────────────────────────┬───────────────────────────────────────┘
                                     │ push, ou déclenchement explicite
                                     ▼
                  Actions « déploiement » : npm ci, astro build
                                     │ SFTP, lftp mirror --delete
                                     ▼
 OVH mutualisé, /home/loremis/
   ├── sbd-re/         racine web : 492 pages, /api/contact.php, /api/lib/ en 403
   └── sbd-re-prive/   config.php, hors web et hors miroir
                                     │
                                     ▼  HTTPS, TLS 1.3, HTTP/2, gzip
 Visiteur ── pages statiques
          ── POST /api/contact.php ── mail() ──► hello@loremipsum.re
          ── Microsoft Clarity, seulement après consentement
```

Trois flux indépendants alimentent le même dépôt : les données, le contenu
éditorial, le code. Un seul chemin mène en production, le déploiement.

---

## 3. Les choix structurants et leur contrepartie

| Choix | Ce qu'il apporte | Ce qu'il coûte |
|---|---|---|
| **Site statique généré par Astro** | Rapide, rien à attaquer côté serveur pour le classement officiel, un hébergement simple suffit. | Chaque modification passe par un build et un déploiement, soit 2 à 3 minutes. Une liste longue produit une page lourde. |
| **Données figées dans le dépôt** | `results.json`, 564 Ko pour 1 221 résultats : versionné, build reproductible, aucune base. | Mise à jour mensuelle seulement. Le script dépend du format du dump. |
| **CMS git (Sveltia)** | Aucune base ni back-office à héberger. Chaque modification est versionnée et réversible. | Le jeton GitHub stocké dans le navigateur de l'auteur donne le droit d'écrire sur le site. |
| **API PHP sur la même origine** | Aucune clé dans le navigateur, un cookie de session suffit. | Toute l'autorisation vit dans le code PHP. MySQL ne se teste pas en local. |
| **Aucune bibliothèque côté client** | 3 fichiers JavaScript, 33 Ko, plus de courts scripts dans les pages : 3 Ko sur `/classement/`. | Tableaux interactifs et carte sont écrits et entretenus à la main. |
| **OVH plutôt qu'un hébergeur statique** | Déjà payé, PHP et MySQL inclus. | Aucun CDN. Déploiement par mot de passe. |

**La séparation la plus précieuse** : le classement officiel ne dépend ni de
PHP ni de MySQL. Aucune panne ni faille de la future API ne peut l'altérer.

**Le système se dégrade bien.** Si OpenPowerlifting disparaît ou change de
format, la mise à jour mensuelle échoue et le site garde ses dernières données.
Si OVH tombe, rien n'est perdu : tout le site se reconstruit depuis le dépôt.

---

## 4. Les points fragiles, du plus grave au plus léger

### 4.1 Le secret de déploiement ouvre tout l'hébergement

Le compte SFTP se connecte à `/home/loremis`, qui contient le site **et**
`sbd-re-prive/config.php`. Le mot de passe rangé dans les secrets GitHub donne
donc accès aux identifiants MySQL, et permet de déposer du PHP qui s'exécutera.

Le serveur n'est jamais authentifié. `StrictHostKeyChecking=accept-new`
n'accepte une clé inconnue qu'une fois, mais la machine GitHub est neuve à
chaque passage : toute clé est donc « nouvelle », à chaque fois. Même chose
pour `sftp:auto-confirm yes` côté lftp. Un serveur qui se ferait passer pour
OVH recevrait le mot de passe.

**Correctif** : épingler l'empreinte du serveur dans un secret, et passer en
`StrictHostKeyChecking=yes`. **Ensuite** : un compte FTP secondaire limité à
`sbd-re/`, que l'espace client OVH permet de créer (à vérifier sur l'offre).

### 4.2 Écrire sur GitHub revient à exécuter du code chez OVH

Astro recopie `public/` tel quel. Un fichier `.php` ajouté au dépôt s'exécute
en production au déploiement suivant. Le jeton du CMS a exactement ce pouvoir.

**Correctif** : un jeton à portée fine, limité à ce seul dépôt, au seul droit
« Contents », avec une date d'expiration.

### 4.3 Deux documents se contredisent sur l'envoi de courriel

[communaute.md](communaute.md), le 30 juillet : `mail()` est refusé par le
serveur, et le SPF du domaine (`-all`) rejetterait ces messages. D'où le choix
du SMTP et de PHPMailer.

[CLAUDE.md](../CLAUDE.md), le 10 septembre : `mail()` fonctionne depuis le
formulaire, le message arrive.

Hypothèse non prouvée : le blocage visait le terminal SSH, et le PHP appelé par
le site passe par un autre chemin. **Question ouverte** : le message reçu
a-t-il passé le contrôle SPF, ou est-il arrivé malgré un échec ? La réponse se
lit dans les en-têtes du courriel reçu, ligne `Authentication-Results`.

Tant que ce n'est pas tranché, la partie communauté n'a pas de chemin d'envoi
choisi. Or son inscription repose entièrement sur un courriel de vérification.

### 4.4 Aucune alerte

Rien ne signale une panne du site, un échec de la mise à jour mensuelle ou un
formulaire cassé. Les données du 1er septembre, bien commitées mais jamais
déployées, sont restées invisibles jusqu'au 10 septembre.

**Correctif** : une sonde de disponibilité externe, qui n'ajoute aucun script au
site et ne pose donc aucune question de consentement. Et une dernière étape au
déploiement, qui lit la version affichée sur https://sbd.re et la compare à
`package.json` : elle prouverait la chaîne complète à chaque passage.

### 4.5 Les pages longues

`/classement/` pèse 981 Ko de HTML, 49 Ko une fois compressé. Le transfert est
léger, mais le téléphone construit **10 077 éléments**, là où les outils de
Google alertent à partir de 1 400.

**48 % de ce HTML est du SVG** : les 312 badges « officiel » répètent chacun
leur tracé complet et leur masque. Un seul `<symbol>` par page, appelé par
`<use>`, diviserait la page par deux environ.

Ce badge porte aussi un identifiant tiré au hasard, qui change à chaque build.
Le déploiement renvoie donc ~440 pages inchangées à chaque passage, et toute
comparaison entre deux builds est bruitée. Le même correctif règle les deux.

### 4.6 Une dépendance inutile

Svelte et `@astrojs/svelte` sont installés et déclarés dans `astro.config.mjs`,
et aucun composant ne s'en sert. C'est du temps de build, et une dépendance de
plus à surveiller à chaque audit.

### 4.7 Une donnée éditoriale hors du CMS

`src/data/next-meet.json`, la prochaine compétition annoncée en accueil, ne
s'édite que dans le code. C'est aussi la brique qui manque au futur planning :
une collection « calendrier » dans le CMS alimenterait les deux.

### 4.8 En-têtes de sécurité incomplets

`X-Content-Type-Options`, `X-Frame-Options` et `Referrer-Policy` sont en place.
HSTS et CSP manquent. Ils se posent dans le `.htaccess` racine, où une erreur
coupe tout le site : décision en attente de l'auteur.

### 4.9 Des chiffres écrits en dur dans la documentation

Les documents parlent de 436 pages et de 266 athlètes classés. On en compte
aujourd'hui 492 et 312. Chaque mise à jour mensuelle les périme. Mieux vaut
écrire l'ordre de grandeur, ou la commande qui donne le chiffre.

---

## 5. Charge et croissance

**Le trafic n'est pas un sujet.** Des pages statiques compressées se servent
par milliers sans effort sur un mutualisé. Le serveur répond en un aller-retour
après la poignée de main.

**La distance, si.** Un aller-retour entre ce poste et le serveur prend environ
200 ms. Une première visite en coûte trois avant le premier octet : connexion,
chiffrement, requête. Soit 0,6 à 0,8 s, qu'aucun réglage côté OVH ne réduit.

**Les données croissent lentement.** La mise à jour de septembre a ajouté 86
résultats et 45 athlètes. Le build de 492 pages prend 9 à 10 secondes. Même
décuplé, il resterait sous les deux minutes.

**Le vrai point de croissance est `/classement/`**, qui affiche tous les
athlètes sur une seule page. Son poids suit leur nombre : 981 Ko pour 312.

---

## 6. Ce qu'il faudra revoir, et à quel signal

| Signal | Ce qu'on revoit |
|---|---|
| Plus de 500 athlètes classés, ou plus de 15 000 éléments sur `/classement/` | Pagination, ou pages séparées par sexe et équipement. |
| Ouverture de la partie communauté | Sauvegardes MySQL (vérifier celles d'OVH dans l'espace client), compteurs anti-abus en base plutôt qu'en fichiers temporaires, alertes sur les erreurs PHP. |
| Plus de quelques centaines de comptes actifs | Limites de processus PHP de l'offre mutualisée, file de modération. |
| Un second contributeur | Branche `main` protégée, relecture par PR. Le CMS devra alors passer par des branches. |
| Plaintes sur la lenteur | Un CDN doté d'un point de présence proche. Contrepartie : un intermédiaire voit passer les visiteurs, ce qui se déclare dans la page de confidentialité. |
| OpenPowerlifting change de format | Le script mensuel échoue sans rien casser. Adapter `scripts/lib/opl.mjs`. |

---

## 7. Recommandations, dans l'ordre

1. **Épingler l'empreinte du serveur** dans le déploiement. Petit changement,
   gros risque fermé.
2. **Lire les en-têtes du courriel reçu**, trancher entre `mail()` et SMTP,
   puis mettre communaute.md en accord.
3. **Ajouter une sonde de disponibilité**, et la vérification de version en fin
   de déploiement.
4. **Badge en `<symbol>`** et **retrait de Svelte** : page de classement deux
   fois plus légère, build déterministe, une dépendance en moins.
5. **Collection « calendrier » dans le CMS** : débloque le planning et sort la
   dernière donnée éditoriale du code.
6. **HSTS et CSP**, sur décision de l'auteur.

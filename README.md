# SBD.re — classements et records du powerlifting réunionnais

Site statique publiant les classements et records de force athlétique (squat,
développé couché, soulevé de terre) de La Réunion, à partir des seuls résultats
officiels de compétition.

- **Techno** : [Astro](https://astro.build) en sortie statique, TypeScript, CSS écrit à la main.
- **Données** : le jeu public d'[OpenPowerlifting](https://www.openpowerlifting.org), régénéré chaque mois.
- **Hébergement** : mutualisé OVH, alimenté par GitHub Actions en FTPS.

---

## Comment ça marche, en une image

```
   Dump OpenPowerlifting (168 Mo, 4 millions de lignes)
                    │
                    │  cron mensuel : 1er du mois, 8 h à La Réunion
                    ▼
        GitHub Actions ── update-data.mjs ──> src/data/results.json
                    │                                │
                    │                         commit SI changement
                    ▼                                │
        GitHub Actions ── astro build ──> dist/ ─────┘
                    │
                    │  FTPS
                    ▼
             OVH /www/  ──>  https://sbd.re
```

Point important : **OVH ne fait que servir des fichiers HTML**. Aucun code ne
s'exécute sur le serveur. Le mutualisé ne sait faire tourner que du PHP, jamais
du Node — c'est GitHub qui fait tout le travail, gratuitement.

---

## Démarrer en local

### Prérequis

- [Node.js](https://nodejs.org) 20 ou plus (le projet a été développé sur la 24).
- Git.

### Installation

```bash
npm install
```

### Lancer le site

```bash
npm run dev
```

Le site est alors sur <http://localhost:4321>. Toute modification de fichier se
répercute immédiatement dans le navigateur.

### Toutes les commandes

| Commande | Ce qu'elle fait |
|---|---|
| `npm run dev` | Serveur de développement, rechargement automatique |
| `npm run build` | Génère le site final dans `dist/` |
| `npm run preview` | Sert `dist/` comme le ferait OVH — à faire avant tout déploiement |
| `npm run check` | Vérifie les types TypeScript et les composants Astro |
| `npm run data:update` | Régénère `src/data/results.json` depuis OpenPowerlifting |
| `npm run data:explore` | Analyse le dump sans rien produire (outil de diagnostic) |
| `npm run fonts:update` | Retélécharge les polices et régénère `src/styles/fonts.css` |

---

## Les données

### D'où elles viennent

Un unique fichier CSV public, téléchargé depuis OpenPowerlifting : environ
168 Mo compressés, 815 Mo une fois décompressé, 4 millions de lignes. Le script
le lit **en flux**, ligne par ligne : la mémoire du processus reste autour de
100 Mo quelle que soit la taille du fichier. Le charger entièrement ferait
saturer la machine.

Le dump est mis en cache dans `.cache/` (ignoré par git) et n'est retéléchargé
qu'au bout de 20 jours, ou avec `--force` :

```bash
node scripts/update-data.mjs --force
```

### Comment une compétition est reconnue comme réunionnaise

**C'est le cœur du projet, et il n'y a pas de solution automatique.** Le jeu de
données ne contient aucune information de région : le champ `MeetState` est
vide sur la totalité des 45 697 résultats de la fédération française. Vérifié,
pas supposé — c'est exactement ce que `npm run data:explore` a servi à établir.

Deux règles, dans cet ordre :

1. **Le nom contient « Réunion »** (sans tenir compte des accents ni de la
   casse). Couvre la majorité des championnats départementaux et régionaux.
2. **La compétition figure dans `data/meets-include.json`**, liste tenue à la
   main. Indispensable : *Open de la Fournaise*, *Bench Cup 974* ou
   *Fanm Kapab Contest* sont incontestablement réunionnais sans jamais nommer
   l'île. Sans cette liste, le site perdrait 14 compétitions sur 61.

**La ville n'inclut jamais une compétition à elle seule.** Saint-Denis,
Saint-Louis, Saint-Paul et Saint-Pierre existent aussi en métropole. Une
compétition organisée dans une commune réunionnaise mais dont le nom ne le dit
pas est simplement *signalée* dans `data/meets-candidates.json`, pour que tu
tranches.

### Les trois fichiers de `data/`

| Fichier | Qui l'écrit | Rôle |
|---|---|---|
| `meets-include.json` | **toi** | Compétitions à inclure que la règle du nom ne trouve pas |
| `meets-exclude.json` | **toi** | Compétitions à écarter, quoi qu'il arrive. A toujours le dernier mot |
| `meets-candidates.json` | le script | Compétitions à examiner. **Ne pas modifier** : réécrit à chaque exécution |

### Routine mensuelle

Le workflow s'occupe de tout. Ta seule tâche, occasionnelle : ouvrir
`data/meets-candidates.json` après une mise à jour. S'il contient des
compétitions, décide pour chacune :

- **réunionnaise** → recopie l'entrée dans `meets-include.json` ;
- **métropolitaine** → recopie-la dans `meets-exclude.json` pour ne plus la revoir ;
- **doute** → laisse-la, elle n'est pas publiée.

Puis relance `npm run data:update` et envoie sur GitHub.

### Périmètre retenu

- **Classement au total** : full power uniquement (`SBD`). Sur une compétition
  d'un seul mouvement, le « total » publié par OpenPowerlifting n'est que cette
  barre-là — le retenir mettrait un développé couché de 130 kg en concurrence
  avec un vrai total de 700 kg.
- **Records par mouvement** : tous les formats (`SBD`, `B`, `S`, `D`). Une barre
  compte dès lors qu'elle a été soulevée devant des juges. Le développé couché
  seul représente 26 des 61 compétitions de l'île.
- **Record au total** : full power uniquement, pour la raison ci-dessus.
- **Écartés** : disqualifications (dont dopage) et forfaits.

Le périmètre se règle en une ligne : la constante `EVENTS_RETENUS`, en tête de
`scripts/update-data.mjs`.

### Annoncer la prochaine compétition

Édite `src/data/next-meet.json` et passe `"annonce": true`. Le bloc disparaît
tout seul une fois la date passée. Ce fichier n'est jamais touché par le script :
les compétitions à venir n'existent pas dans OpenPowerlifting, qui ne publie que
des résultats passés.

---

## Déployer sur OVH

À faire une seule fois. Les étapes 1 à 3 sont de ton ressort : elles touchent à
ton compte OVH et à tes identifiants.

### 1. Créer un dossier et un utilisateur FTP dédiés chez OVH

Le site vit dans **son propre dossier** (`sbd-re`), pas à la racine de
l'hébergement. Deux avantages : il reste isolé de tout autre site hébergé sur le
même compte, et l'utilisateur FTP peut être enfermé dedans.

1. Crée le dossier `sbd-re` à la racine de ton espace FTP.
2. Espace client OVH → **Hébergements** → ton hébergement → onglet
   **FTP - SSH** → **Ajouter un utilisateur**, avec pour racine ce dossier.

L'utilisateur est alors *chrooté* : quand le déploiement se connecte, la racine
qu'il voit **est** le dossier `sbd-re`. C'est pourquoi `deploy.yml` dépose dans
`./` et non dans `./www/`. Se tromper ici ne provoque aucune erreur — les
fichiers atterrissent simplement dans un sous-dossier fantôme et le site reste
introuvable.

N'utilise pas ton compte FTP principal : si ce mot de passe fuite un jour, tu ne
révoques que cet accès-là, sans rien casser d'autre.

Note trois informations : **serveur** (`ftp.cluster0XX.hosting.ovh.net`),
**identifiant**, **mot de passe**.

### 2. Enregistrer les secrets sur GitHub

Dépôt GitHub → **Settings** → **Secrets and variables** → **Actions** →
**New repository secret**. Trois secrets à créer :

| Nom | Valeur |
|---|---|
| `FTP_SERVER` | l'adresse du serveur FTP OVH |
| `FTP_USERNAME` | l'identifiant de l'utilisateur dédié |
| `FTP_PASSWORD` | son mot de passe |

Un secret GitHub n'est jamais réaffichable : garde-les aussi dans ton
gestionnaire de mots de passe.

### 3. Brancher le domaine et activer HTTPS

Le domaine `sbd.re` étant déjà chez OVH, tout se passe au même endroit.

1. Espace client → **Hébergements** → ton hébergement → onglet **Multisite** →
   **Ajouter un domaine**.
2. Domaine `sbd.re`, **dossier racine `sbd-re`** — le même que celui du
   déploiement, sinon le domaine servirait un dossier vide. Réponds **non** à
   « Le domaine est-il un alias ? ».
3. Ajoute également `www.sbd.re` sur le même dossier — le `.htaccess` le
   redirigera vers `sbd.re`.
4. Toujours dans **Multisite**, active le **certificat SSL** (Let's Encrypt,
   gratuit). Comptez jusqu'à une heure avant qu'il soit délivré.

La propagation DNS peut prendre jusqu'à 24 h. Tant qu'elle n'est pas terminée,
le site peut sembler injoignable — c'est normal, ce n'est pas un problème de
déploiement.

### 4. Premier déploiement

Avant de brancher le DNS, lance le déploiement à la main pour vérifier que les
fichiers arrivent bien : onglet **Actions** → **Construire et déployer sur OVH**
→ **Run workflow**. Puis vérifie par FTP que `/www/` contient bien `index.html`,
`.htaccess` et le dossier `_astro/`.

Ensuite, chaque envoi sur `main` déclenche un déploiement automatique.

---

## Structure du projet

```
├── .github/workflows/     Automatisation : mise à jour des données, déploiement
├── data/                  Listes de compétitions (include / exclude / candidates)
├── scripts/
│   ├── lib/opl.mjs        Téléchargement, cache, lecture en flux du dump
│   ├── explore-meets.mjs  Diagnostic : ce que contient vraiment le dump
│   └── update-data.mjs    Pipeline de production
├── public/                Copié tel quel dans dist/ : .htaccess, robots.txt
└── src/
    ├── data/              results.json (généré) et next-meet.json (à la main)
    ├── lib/               Logique métier : classements, records, catégories
    ├── components/        Composants d'affichage
    ├── layouts/           Gabarit commun
    ├── styles/tokens.css  TOUTES les couleurs et espacements du site
    └── pages/             Une page = un fichier
```

### Changer l'apparence

Tout est dans `src/styles/tokens.css`. Les couleurs y sont déclarées **une seule
fois** en haut du fichier, puis référencées partout ailleurs. Changer l'accent
orange du site, c'est modifier deux lignes (`--p-light-accent` et
`--p-dark-accent`), pas chercher dans quinze composants.

### Typographie

Deux polices Google, **hébergées par le site lui-même** dans `public/fonts/` :

- **Oswald** — titres et grands chiffres. Condensée, donc un total à quatre
  chiffres tient dans une colonne étroite.
- **Inter** — texte courant et chiffres de tableau.

Elles ne sont **pas** chargées depuis `fonts.googleapis.com`. Ce lien
transmettrait l'adresse IP de chaque visiteur à Google sans son consentement, ce
que le tribunal de Munich a jugé contraire au RGPD en 2022. Les deux polices
étant sous licence SIL Open Font, les redistribuer soi-même est autorisé — et
plus rapide, puisqu'on économise une connexion vers un domaine tiers.

Elles sont récupérées en **version variable** : un seul fichier par famille
couvre toutes les graisses de 400 à 700. Résultat, 170 Ko sur le disque et
environ 68 Ko réellement transférés à un visiteur francophone, contre 600 Ko
avec des fichiers figés.

**Le piège à connaître avant de toucher aux polices.** Oswald n'a pas de chiffres
à largeur fixe : la propriété CSS `tabular-nums` y est purement ignorée. Mesuré
sur le site : en Oswald 700, « 111 » occupe 18,5 px là où « 888 » en occupe 26,4.
Dans une colonne de 266 lignes, les totaux se décaleraient visiblement d'une
ligne à l'autre. D'où deux classes CSS distinctes :

| Classe | Police | Quand l'utiliser |
|---|---|---|
| `.num` | Oswald | Chiffre **isolé** et grand : statistique d'accueil, record mis en avant |
| `.num-tab` | Inter | Chiffre **dans une colonne** de tableau |

Pour changer de police, modifie l'URL en tête de `scripts/fetch-fonts.mjs` puis
lance `npm run fonts:update`. Vérifie ensuite que la nouvelle police de titrage
gère bien `tabular-nums` avant de l'employer dans un tableau.

---

## Choix techniques, et pourquoi

**Pas de Tailwind.** Six pages et un système visuel qui tient en quinze
variables CSS. Le thème clair/sombre se fait en deux lignes avec des variables,
là où Tailwind impose sa mécanique `dark:` sur chaque classe.

**Presque pas de JavaScript.** Le classement envoie ses 266 lignes directement
dans le HTML, et les filtres tiennent en une trentaine de lignes de JavaScript
natif. Conséquences : les athlètes sont indexables par les moteurs de recherche,
et aucun framework n'est téléchargé.

**Les graphiques sont du SVG calculé au build.** Une courbe de progression, ce
sont des coordonnées — pas une raison d'embarquer une librairie de plusieurs
dizaines de kilo-octets.

**Le thème est appliqué avant le premier rendu**, par un court script inline dans
`<head>`. Un fichier externe serait chargé trop tard et la page clignoterait en
blanc avant de basculer en sombre.

**Le script de données est idempotent.** Deux exécutions sur le même dump
produisent un fichier rigoureusement identique, à l'octet près. C'est ce qui
permet au workflow mensuel de ne commiter que s'il y a réellement du nouveau.

---

## Limites connues

- **Pas de lien direct vers la fiche d'une compétition** sur OpenPowerlifting :
  leur jeu de données téléchargeable ne contient pas d'identifiant de meet.
  Fabriquer une URL reviendrait à inventer un lien mort. Les fiches *athlètes*,
  elles, sont bien liées.
- **La date d'aujourd'hui est celle du build.** Sur un site reconstruit une fois
  par mois, une compétition passée peut rester annoncée quelques semaines.
- **Les corrections de données passent par OpenPowerlifting.** Une performance
  fausse ou manquante doit leur être signalée : elle sera reprise ici à la mise
  à jour suivante.

---

## À venir

Le **classement communauté** (performances déclarées en salle et compétitions
non homologuées), avec comptes Google et e-mail via Supabase, modération avant
publication, et catégorisation par tranches de poids et de taille. Il vivra
strictement séparé du classement officiel, qui restera en lecture seule.

---

## Crédits

Données issues du projet [OpenPowerlifting](https://www.openpowerlifting.org),
librement téléchargeables sur [data.openpowerlifting.org](https://data.openpowerlifting.org).

Site indépendant, non affilié à la FFForce ni à SBD Apparel.

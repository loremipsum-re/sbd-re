# Instructions pour Claude Code

Ce fichier est lu automatiquement au démarrage d'une session. Il existe pour
qu'un nouveau contexte reprenne le projet sans repartir de zéro.

## Le projet

**sbd.re** : site statique de classements et records de force athlétique à
La Réunion, en ligne sur <https://sbd.re>, dépôt
<https://github.com/loremipsum-re/sbd-re>.

Astro en sortie statique, TypeScript, CSS écrit à la main. Données publiques
d'OpenPowerlifting régénérées chaque mois. Hébergement mutualisé OVH alimenté
par GitHub Actions.

**À lire avant toute modification structurante :**

- [README.md](README.md) : comment le projet fonctionne, comment le lancer, comment déployer.
- [docs/parcours.md](docs/parcours.md) : **pourquoi** il est ce qu'il est. Décisions, découvertes et pièges rencontrés. C'est le document qui fait gagner le plus de temps.
- [docs/communaute.md](docs/communaute.md) : la conception de la partie communauté, à lire avant d'y toucher. Architecture, modèle de données, et surtout le modèle de sécurité qui remplace les politiques RLS.

## Attentes de travail

L'auteur est développeur WordPress et Elementor. sbd.re est son premier projet
en développement assisté par IA. Il connaît le web et le CSS, mais pas
l'écosystème Node, ni git en ligne de commande, ni les plateformes
d'intégration continue.

- Expliquer les choix techniques **au fil de l'eau**, de façon pédagogique.
- Commits **atomiques**, messages en **français**, en minuscules, préfixés par le domaine : `données :`, `classement :`, `déploiement :`, `docs :`.
- **Poser une question** dès qu'une décision est ambiguë, plutôt que de trancher à sa place.
- Annoncer et justifier tout écart par rapport à ce qui était prévu.
- **Vérifier par la mesure avant d'affirmer.** Idempotence prouvée par empreinte, contrastes calculés, comportements testés dans le navigateur.
- Toute la rédaction du site est en **français**.

## Règles de contenu du site

- **Pas de tirets longs** dans la prose. Le tiret reste autorisé comme marqueur de valeur absente dans un tableau.
- **Ne jamais désigner La Réunion par « 974 »**, sauf lorsque c'est le nom propre d'une compétition (*Bench Cup 974*).
- **Tournures affirmatives.** Éviter d'empiler les négations et les comparaisons.
- **Interdit : le combo noir et rouge**, qui est l'identité de la marque SBD Apparel. L'accent du site est orange.
- Mentions obligatoires en pied de page : source OpenPowerlifting, et non-affiliation à la FFForce comme à SBD Apparel.

## Vie privée

**Aucun script tiers ne se charge sans consentement.** Microsoft Clarity est
posé derrière une bannière, et la fonction `window.sbdChargerMesure()` n'est
appelée qu'après acceptation. Avant de brancher quoi que ce soit d'externe,
vérifier dans le navigateur qu'aucune requête ne part avant l'accord.

Deux pages encadrent cela : `/confidentialite/` et `/mentions-legales/`. La
première documente aussi la publication des noms et performances des athlètes,
qui est un traitement de données personnelles à part entière, avec droit
d'opposition.

Ne jamais inventer de donnée légale : SIRET, adresse et forme juridique portent
la mention « à compléter » tant que l'auteur ne les a pas fournis.

## Structure des pages

| Adresse | Contenu |
|---|---|
| `/` | Accueil : top 10 aux Dots, compétitions récentes |
| `/classement/` | 266 athlètes en cartes, filtrable et triable |
| `/records/` | Grille par sexe et équipement, catégories en lignes |
| `/categories/` | Les 17 catégories, puis `/categories/hommes/93/` |
| `/competitions/` | Liste, puis `/competitions/<nom>/<date>/` |
| `/exterieur/` | Résultats obtenus hors de La Réunion |
| `/athlete/<slug>/` | 312 fiches |
| `/communaute/` | Page d'attente du classement non-officiel |
| `/confidentialite/`, `/mentions-legales/` | Pages légales |

Les URL de compétition sont bâties par `meetPath(date, nom)` et regroupent les
éditions sous le nom de l'événement. Les anciennes adresses `date-nom`
redirigent, via une table générée dans `astro.config.mjs`.

## Contrat des tableaux interactifs

`InteractiveTable.astro` pilote recherche, filtres et tri de **toutes** les
listes. Tout passe par des attributs `data-`, il n'y a aucun framework.

```html
<table data-tableau>
  <thead class="sr-only">
    <th data-tri data-cle="total" data-type="nombre">Total</th>
  </thead>
  <tbody>
    <tr data-recherche="Nom" data-total="782.5" data-sexe="M">
```

Points à ne pas casser :

- **`data-cle` fait lire la valeur sur la LIGNE**, pas dans la cellule. C'est
  indispensable en affichage par cartes, où plusieurs valeurs cohabitent dans
  une cellule et où l'index de colonne ne veut plus rien dire.
- **La recherche porte sur `data-recherche` seul**, jamais sur toute la ligne.
  Chercher partout ferait remonter « 93 » aussi bien pour une catégorie qu'un
  total ou une année.
- **Le tri réécrit `.rang-num`, jamais la cellule `.rang`**, qui contient aussi
  la coupe du podium.
- **Ne jamais utiliser le même nom d'attribut pour un tri et un filtre.** D'où
  `data-tri-categorie` pour le tri numérique et `data-categorie` pour le filtre,
  qui porte « 120+ ».
- `barreDeTri` remplace les en-têtes cliquables quand la liste s'affiche en
  cartes. Les deux mécanismes partagent la même logique.
- Les filtres se lisent et s'écrivent dans l'adresse, ce qui rend un état
  partageable et permet aux badges d'une fiche de pointer vers un classement
  déjà filtré.

## Partage sur les réseaux sociaux

La plomberie OpenGraph existe dans `BaseLayout.astro` mais reste inactive : la
constante `IMAGE_PARTAGE_DEFAUT` vaut `null`, et aucune balise `og:image` n'est
émise tant qu'aucune image n'existe. Une balise pointant vers un fichier absent
serait mise en cache durablement par les réseaux sociaux.

**Dès que le logo arrive** : déposer une image de 1200 × 630 en PNG ou JPEG
dans `public/`, renseigner la constante, et les 436 pages en héritent.

## Invariants à ne pas casser

**Le total n'a de sens qu'en full power.** Sur une compétition d'un seul
mouvement, OpenPowerlifting renseigne quand même `totalKg`, avec la valeur de
l'unique barre. Tout classement au total part de `fullPowerResults`, jamais de
`results`.

**Le meilleur total et le meilleur Dots sont deux choses distinctes**, chacune
avec sa date et sa compétition. En descendant de catégorie, un athlète obtient
un meilleur Dots avec un total inférieur.

**La ville n'inclut jamais une compétition à elle seule.** Saint-Denis,
Saint-Louis et Saint-Paul existent aussi en métropole. Elle sert uniquement à
signaler des candidats à vérifier à la main.

**Les résultats hors de La Réunion n'entrent ni au classement ni aux records.**
Ils figurent sur `/exterieur/` et sur les fiches d'athlètes.

**Oswald n'a pas de chiffres à largeur fixe.** Utiliser `.num` pour un chiffre
isolé et grand, `.num-tab` pour un chiffre dans une colonne.

**Tout contrôle fait au moins 44 px**, jeton `--cible-min`, règle globale dans
`tokens.css`. Un nouveau bouton en hérite sans rien demander. Employer
`min-height` et jamais `height` : le texte doit rester libre de grandir. Les
trois exclusions sont écrites dans `tokens.css`, ne pas en ajouter sans mesurer.

**Un seul jeu d'icônes : Tabler, variante `outline`**, licence MIT, tracé dans
`Icone.astro`. Trait de 2 sur grille de 24. Ne jamais mélanger deux packs, c'est
la première cause d'icônes dépareillées. Pour en ajouter une, reprendre le tracé
depuis `tabler/tabler-icons`, `icons/outline/`, et conserver la mention de
copyright en tête du composant, que la licence MIT impose.

**Les icônes du menu sont décoratives**, `aria-hidden="true"`, et accompagnent
toujours un libellé texte. Une icône seule redeviendrait la seule information et
donc un problème d'accès. Le contour sert à naviguer, le plein signale un état
dans la donnée : d'où le trophée en contour dans le menu et la coupe pleine de
`Coupe.astro` dans les tableaux.

**Ne jamais tronquer du texte porteur de sens.** Pas de `text-overflow:
ellipsis` sur un nom d'athlète, de compétition ou de commune : ce sont les
informations que les cartes existent pour donner. Le texte passe à la ligne.
`white-space: nowrap` reste bon pour une valeur courte et insécable, une date,
un poids, un effectif.

**Un élément de grille ou de flex vaut `min-width: auto`.** Il peut donc
dépasser sa colonne pour loger son contenu, et un enfant en `nowrap` suffit à
déclencher le débordement. Poser `min-width: 0` sur l'élément, et écrire les
grilles en `minmax(min(15rem, 100%), 1fr)` plutôt qu'en `minmax(15rem, 1fr)`.
C'est ce qui faisait sortir l'effectif d'athlètes de l'écran sur l'accueil.

**Le débordement de page n'est pas le bon indicateur.** Un conteneur en
`overflow: hidden` coupe sans que `scrollWidth` de la page ne bouge d'un pixel.
Mesurer par élément : `scrollWidth > clientWidth`, et la largeur de la boîte
comparée à celle de `.wrap`.

**`--accent` est un accent de TEXTE, `--accent-fill` est l'aplat.** Poser
`--accent` en fond sous `--on-accent` donne 2,78:1 en thème clair. L'erreur a
déjà été commise une fois, sur le lien d'évitement. Le thème sombre ne la
révèle pas, les deux jetons y valant la même couleur : toujours vérifier en
clair.

**Astro supprime l'espace avant une balise inline.** Écrire `{' '}` explicitement.
Un script de détection existe, voir docs/parcours.md.

**Le MySQL d'OVH n'est pas en mode strict.** Mesuré : MySQL 8.4.10, `sql_mode`
réduit au seul `NO_ENGINE_SUBSTITUTION`. Une charge saisie à 1500 kg entrerait
donc à 999,99 kg, une contrainte `CHECK` étant évaluée **après** ce rabotage.
**Chaque connexion PHP impose son `sql_mode`** dès l'ouverture, sans exception.
Voir docs/communaute.md, section 11.

## Commandes

```bash
npm run dev           # serveur de développement
npm run build         # génération dans dist/
npm run check         # types TypeScript et composants Astro
npm run data:update   # régénère les données depuis OpenPowerlifting
npm run data:explore  # diagnostic du dump, ne produit rien
npm run fonts:update  # retélécharge les polices auto-hébergées
```

Pour la partie communauté, **PHP 8.4 est installé en local** et disponible dans
le PATH utilisateur. Aucun PHP ne doit être livré sans être passé par le
contrôle de syntaxe : deux allers-retours ont été perdus le 30 juillet 2026
faute de l'avoir fait.

```bash
php -l <fichier>                    # contrôle de syntaxe
php db/verifier-autorisation.php    # 39 règles d'autorisation, sans base
```

MySQL, lui, n'est **pas** installé en local. Tout ce qui touche à la base
s'éprouve sur le serveur OVH, par `php db/verifier.php` en SSH.

Sous Windows, si `node` ou `php` est introuvable, recharger le PATH :

```powershell
$env:Path = [System.Environment]::GetEnvironmentVariable("Path","Machine") + ";" + [System.Environment]::GetEnvironmentVariable("Path","User")
```

## Reste à faire

**En attente de l'auteur**

- Le logo et le système de design, pour activer les OpenGraph.
- Google Search Console : la propriété est en cours de validation.

**Trois arbitrages ouverts**, sur lesquels l'auteur ne s'est pas prononcé :

1. **Médailles de compétition.** Elles sont aujourd'hui décernées par catégorie
   de poids, ce qui donne 41 médailles sur 54 résultats pour une compétition
   type. Sportivement exact, visuellement chargé. L'alternative serait les trois
   premiers toutes catégories confondues.
2. **Buy Me a Coffee.** Le bouton est fait maison, sans script tiers. L'auteur
   avait fourni le script officiel, écarté pour la même raison que Google Fonts
   en CDN.
3. **Nom de l'onglet Extérieur.** L'auteur proposait « International » ou
   « France ».

**Audit ergonomique iOS : fait le 2 août 2026**

Les skills <https://github.com/quin566/ios-ui-design-skills> sont installés dans
`.claude/skills/`. L'audit a été mené par la mesure dans le navigateur et les
corrections sont livrées. Le détail, chiffres avant et après compris, est dans
[docs/parcours.md](docs/parcours.md), section 7.

**L'esthétique iOS reste explicitement écartée.** Le système de marque
*Modernist* installé le 30 juillet, aplats francs, bordures de 2 px, angles
nets, aucune ombre, reste intact. Douceur, profondeur, ombres multicouches et
matériaux translucides lui sont opposés et ne doivent pas être importés.

Ce qui a été corrigé :

- **Cibles tactiles.** Aucun contrôle du site n'atteignait 44 px. La règle vit
  maintenant dans `tokens.css`, jeton `--cible-min`, avec trois exclusions
  documentées sur place.
- **Rayons imbriqués.** Un seul écart réel, les liens du menu déroulant.
- **États vides.** `EtatVide.astro` remplace les trois impasses par un titre,
  une explication, et l'action de sortie quand elle existe.
- **Contraste.** Le lien d'évitement était à 2,78:1 en thème clair. Il passe à
  10,66:1. C'était le seul échec du site.

Deux points du périmètre initial n'appelaient pas de correction, la mesure ayant
démenti le diagnostic :

- **La grille d'espacement était déjà bonne.** Sept jetons `--space-*` sur huit
  sont des multiples de 8, le huitième est un multiple de 4. La grille est
  désormais documentée telle qu'elle est, en 4 points avec le pas de 8 préféré.
- **`/comparer/` avait déjà son état vide**, complet, avec action de sortie.

Reste ouvert : les jetons `--succes`, `--alerte` et `--danger` sont posés et
mesurés mais sans usage, en attente des formulaires de la partie communauté.
Les niveaux de texte restent à deux, faute d'un besoin réel pour un troisième.

Hors périmètre : SF Pro, sous licence Apple et inutilisable sur le web, le
retour haptique sans équivalent, et les conventions d'application que sont les
barres d'onglets, les feuilles modales et les piles de navigation.

**Chantier principal**

La **partie communauté**, phases 4 à 7 du plan initial : classement
non-officiel, comptes Google et e-mail, lien vidéo obligatoire, soumissions
modérées, tranches de poids et de taille.

**Conception arrêtée le 30 juillet 2026**, à lire dans
[docs/communaute.md](docs/communaute.md). Supabase est écarté au profit d'une
**API PHP devant une base MySQL** sur l'hébergement OVH déjà payé. Le schéma est
prêt dans [db/schema.sql](db/schema.sql) ; aucun code PHP n'existe encore.

Deux conséquences à ne pas perdre de vue. MySQL n'a pas d'équivalent des
politiques RLS : **toute l'autorisation vit dans le code PHP**, et c'est
désormais le seul endroit du projet où une erreur laisserait un inconnu modifier
le classement. Et les bases OVH mutualisées ne sont joignables que depuis
l'hébergement, ce qui impose un MySQL local pour développer.

Elle repose sur une hypothèse jamais vérifiée : que des athlètes veuillent
déclarer leurs performances de salle. Un sondage auprès de quelques
powerlifters avant de construire éviterait peut-être des semaines de travail
dans la mauvaise direction.

**Pistes plus légères**, si la communauté attend : page par année recensant la
saison, comparaison entre deux athlètes, statistiques par commune organisatrice.
Les statistiques par club sont impossibles, le jeu de données d'OpenPowerlifting
ne contenant aucun champ de club.

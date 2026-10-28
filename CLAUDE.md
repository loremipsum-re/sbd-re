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
- [docs/architecture.md](docs/architecture.md) : la forme d'ensemble du système, ses points fragiles classés, et les signaux qui imposeront de la revoir.

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
- **Chaque mise à jour livrée fait monter la version**, demande de l'auteur du
  18 septembre 2026. Une rubrique ou une fonction visible nouvelle monte le
  deuxième chiffre (1.1 vers 1.2). Des corrections ou des dépendances seules
  montent le troisième (1.1 vers 1.1.1). Commande :
  `npm version 1.2.0 --no-git-tag-version`, qui met à jour `package.json` et
  `package-lock.json` sans créer d'étiquette git. Le pied de page affiche v1.2
  tant que le troisième chiffre vaut zéro.

## Règles de contenu du site

- **Pas de tirets longs** dans la prose. Le tiret reste autorisé comme marqueur de valeur absente dans un tableau.
- **Ne jamais désigner La Réunion par « 974 »**, sauf lorsque c'est le nom propre d'une compétition (*Bench Cup 974*).
- **Tournures affirmatives.** Éviter d'empiler les négations et les comparaisons.
- **Interdit : le combo noir et rouge**, qui est l'identité de la marque SBD Apparel. L'accent du site est le **jaune** du drapeau réunionnais : `#d1d100` en sombre, `#676700` pour un texte en clair, `#d6d600` en aplat. Valeurs et contrastes dans `tokens.css`.
- Mentions obligatoires en pied de page : source OpenPowerlifting, et non-affiliation à la FFForce comme à SBD Apparel.

## Vie privée

**Aucun script tiers ne se charge sans consentement.** Microsoft Clarity est
posé derrière une bannière, et la fonction `window.sbdChargerMesure()` n'est
appelée qu'après acceptation. Avant de brancher quoi que ce soit d'externe,
vérifier dans le navigateur qu'aucune requête ne part avant l'accord.

Tout choix passe par `enregistrerChoix()` de `src/lib/consentement.ts`, jamais
par une écriture directe de la clé `sbd-mesure`. La fonction émet
`sbd:consentement`, qui garde la bannière et `/confidentialite/` d'accord entre
elles.

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
| `/classement/` | Les athlètes classés, en cartes, filtrable et triable |
| `/records/` | Grille par sexe et équipement, catégories en lignes |
| `/categories/` | Les 17 catégories, puis `/categories/hommes/93/` |
| `/competitions/` | Liste, puis `/competitions/<nom>/<date>/` |
| `/exterieur/` | Résultats obtenus hors de La Réunion |
| `/athlete/<slug>/` | Une fiche par athlète |
| `/communaute/` | Page d'attente du classement communauté : son état en une phrase, trois actions, le fonctionnement prévu |
| `/suggestions/` | Boîte à suggestions anonyme, servie par `public/api/suggestion.php` |
| `/mises-a-jour/` | Journal des changements : chiffres des données calculés, entrées du site écrites dans `src/lib/journal.ts` |
| `/accessibilite/` | Audit d'accessibilité mené par la mesure, méthode, résultats et limites |
| `/confidentialite/`, `/mentions-legales/` | Pages légales |

**Aucun effectif n'est écrit ici**, et c'est volontaire : les données sont
régénérées chaque mois, si bien qu'un nombre cité dans un document est périmé à
la première mise à jour. Trois chiffres l'étaient déjà. `npm run build` annonce
le nombre de pages, et la page de classement affiche son effectif.

Les URL de compétition sont bâties par `meetPath(date, nom)` et regroupent les
éditions sous le nom de l'événement. Les anciennes adresses `date-nom`
redirigent, via une table générée dans `astro.config.mjs`.

**La navigation se déclare dans `src/lib/navigation.ts`, source unique**,
choix de l'auteur du 27 octobre 2026. Dès 48rem, la barre porte Classement,
Records et Catégories (champ `barre`), puis le bouton Menu. Le panneau range
les rubriques en trois familles, Classements, Outils et Le site, sur deux
colonnes dès 22,5rem. `/exterieur/` s'y appelle « Hors de l'île ». Communauté
porte l'étiquette « Bientôt » (champ `etiquette`). Mises à jour et pages
légales vivent au pied de page seulement (`piedSeulement`), qui suit le même
plan. Les identifiants de `FAMILLES` nomment des zones de grille dans
`BaseLayout.astro` et `SiteFooter.astro` : une famille ajoutée demande sa zone
dans les trois mises en page. Le menu se referme quand le focus le quitte, et
`aria-current` vaut `page` sur une rubrique, `true` sur une page intérieure.

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
- **Une seule barre de filtres pour tout le site**, `src/styles/filtres.css`,
  partagée par `InteractiveTable`, la carte des salles, `/comparer/` et
  `/simulateur/`. Dans l'ordre : la recherche sur toute la largeur, le bouton
  « Filtres » au bout ; le panneau, filtres sur une grille `auto-fit` puis le
  tri ; la rangée d'état, compteur « N sur M » à gauche, `.bouton-reinitialiser`
  à droite. Tout changement s'y remesure sur les onze pages à barre.
- **`replier`** range filtres et tri derrière le bouton « Filtres », qui compte
  les filtres actifs : `'toujours'` sur `/classement/`, `'telephone'` (sous
  40rem) sur les compétitions et `/exterieur/`. `/categories/` n'a pas de barre,
  ses pastilles sont une navigation. **`feminin`** accorde l'état vide :
  « Aucune catégorie ». L'événement **`sbd:filtres`** prévient les commandes
  propres à une page, comme les bascules de `/records/`.
- **Échap** ferme le panneau depuis un menu ou un bouton. Dans une recherche
  saisie, il garde son sens natif et vide le champ, un second Échap ferme le
  panneau : déplacer le focus pendant la touche laissait Chromium vider le
  champ sans émettre `input`.
- **Après une remise à zéro, le focus ne vise jamais un champ**, qui ferait
  surgir le clavier : le bouton « Filtres », sinon le compteur.
- **Un état choisi porte l'aplat `--accent-fill` sous un filet `--accent`**,
  jamais l'aplat seul. En clair, l'aplat ne se détache de son voisin qu'à
  1,39:1, sous les 3:1 du critère 1.4.11 ; le filet tient 5,34:1.
- **`.liste .intitule` n'est masqué que dans `td[data-libelle]`.** Écrit plus
  large, il a masqué SQ, DC et SDT de tous les résultats de compétition.

## Infographies

Vingt infographies, écrites en HTML et CSS dans `src/components/infographies/`,
autour d'un cadre commun, `Infographie.astro`. Une par page qui demande une
explication : seules les mentions légales, la page 404 et la liste des
actualités n'en ont pas.

- **Dans un article** : le champ `infographie` du frontmatter la pose entre le
  chapeau et le texte. Le catalogue `lib/infographies.ts` liste celles qu'un
  article peut choisir, et nourrit le schéma, le menu du CMS et l'aiguillage
  `InfographieArticle.astro`. L'infographie `competition` lit le champ
  `competition` de l'article, et la génération échoue s'il manque.
- **Sur une page de données** : dans une pastille repliée, `Repli.astro`, qui
  partage la classe `.repli` du glossaire. Plusieurs pastilles se rangent dans
  `.rangee-replis`. Une infographie propre à une page (consentement,
  partenariats, fiche d'athlète…) est appelée directement par la page, hors
  catalogue.
- **Les chiffres se lisent dans les données**, jamais recopiés. Les calculs
  lourds vivent dans `src/lib/infographie-<nom>.ts`. Trois exemples sont
  construits, et leur légende le dit : les neuf essais, le comparateur, le
  calcul des Dots. L'infographie des suggestions lit ses limites dans
  `public/api/suggestion.php` lui-même : modifier ce fichier peut arrêter la
  génération, et c'est voulu.
- `InfographieCategories` accepte `sexe` et `classe` pour mettre en avant la
  catégorie de la page.
- **Les classes globales `.note` et `.bloc`** de `tokens.css` s'appliquent aussi
  dans une infographie. Une classe locale du même nom en hérite.

Pour en ajouter une : un composant, et pour un article une ligne au catalogue
et une entrée dans l'aiguillage. Vérifier comme les autres : débordement par
élément, contraste calculé, captures à 375 et 1 280 px dans les deux thèmes,
coût vertical mesuré contre la version précédente, chiffres recomptés par un
script indépendant.

## Partage sur les réseaux sociaux

La plomberie OpenGraph existe dans `BaseLayout.astro` mais reste inactive : la
constante `IMAGE_PARTAGE_DEFAUT` vaut `null`, et aucune balise `og:image` n'est
émise tant qu'aucune image n'existe. Une balise pointant vers un fichier absent
serait mise en cache durablement par les réseaux sociaux.

**Dès que le logo arrive** : déposer une image de 1200 × 630 en PNG ou JPEG
dans `public/`, renseigner la constante, et toutes les pages en héritent.

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

**Une seule police, Archivo, depuis juillet 2026.** Elle a des chiffres
tabulaires, contrairement à Oswald qu'elle remplace. `.num` et `.num-tab` gardent
des rôles typographiques : `.num` pour un chiffre isolé et grand, `.num-tab`
pour un chiffre dans une colonne.

**Tout contrôle fait au moins 44 px**, jeton `--cible-min`, règle globale dans
`tokens.css`. Un nouveau bouton en hérite sans rien demander. Employer
`min-height` et jamais `height` : le texte doit rester libre de grandir. Les
quatre exclusions sont écrites dans `tokens.css`, ne pas en ajouter sans
mesurer. La quatrième est le terme souligné du lexique, une cible dans une
phrase que le critère 2.5.8 des WCAG 2.2 exempte.

**Aucun champ sous 16 px**, sinon Safari zoome sur iPhone dès qu'on le touche.
Le jeton `--police-champ` vaut `max(16px, var(--step-0))` et une règle globale
de `tokens.css` l'applique. Un composant qui redéclare `font` écrit
`font-size: var(--police-champ)` juste après. Jamais `maximum-scale` ni
`user-scalable=no`, qui empêcheraient aussi le zoom volontaire.

**Ordre d'empilement** : bannière de consentement et infobulle du glossaire 20,
lien d'évitement et panneau du menu 10, voile du menu 9, en-tête 6, bulles du
lexique 5, boutons flottants 4. Les
deux boutons flottants, retour en haut et « Proposer », sont réglés ensemble
dans `BaseLayout.astro` : quand ils empiètent sur la colonne du contenu, ils se
retirent à la descente et reviennent à la remontée.

**L'en-tête est collante, deux conséquences sur les ancres.** Une ancre de
retour en haut ne doit jamais viser un élément collant : sa boîte étant déjà en
haut de la fenêtre, le navigateur ne défile pas et le lien paraît mort. D'où le
`<span id="top">` en tête de document. Et `scroll-padding-top` doit rester posé
sur `:root`, sans quoi toute ancre dépose la cible sous l'en-tête.

**Un seul jeu d'icônes : Tabler, variante `outline`**, licence MIT, tracé dans
`Icone.astro`. Trait de 2 sur grille de 24. Ne jamais mélanger deux packs, c'est
la première cause d'icônes dépareillées. Pour en ajouter une, reprendre le tracé
depuis `tabler/tabler-icons`, `icons/outline/`, et conserver la mention de
copyright en tête du composant, que la licence MIT impose.

**Les partenaires sont DIRECTS, jamais une régie.** Une image et un lien
hébergés sur le site : aucun script tiers, aucun pistage, aucun consentement à
demander. Une régie type AdSense tomberait derrière la bannière, serait
invisible pour qui refuse, et contredirait la posture du site. Trois règles,
appliquées dans `lib/sponsors.ts` et non laissées à la vigilance des gabarits :
**une seule place de contenu par page**, le bandeau du pied de page étant
compté à part, **jamais dans une donnée** (ni entre deux lignes de classement,
ni dans un tableau de résultats), **jamais au-dessus du contenu**. Deux
obligations en plus : `rel="sponsored nofollow"` sur le lien, qu'exige Google,
et un intitulé « Partenaire » visible, qu'exige la loi française sur
l'identification de la publicité. La fiche d'athlète n'a ni place ni bandeau :
y afficher une marque prêterait à l'athlète une caution qu'il n'a pas donnée.

**Les formats des partenaires sont fixes**, choix de l'auteur du 27 octobre
2026, et se lisent dans `lib/formats-partenaires.ts` : bannière fournie en
1 200 × 300 et affichée sur 600 px au plus (articles, compétitions), carte logo
fournie en 400 × 200 et affichée en 96 × 48 avec une accroche de 80 caractères
(catégories, records, classement, salles), logo du pied de page en 80 × 40.
Rien ne s'affiche, pas même un cadre vide, sans partenaire actif. Une place
posée deux fois ou sur une fiche arrête la génération. Une image SVG porteuse
de script ou de ressource extérieure est refusée par `content.config.ts`.

**Ne jamais écrire que le site est « sans publicité ».** La promesse qui tient
est l'absence de RÉGIE et de script tiers. Le pied de page dit donc « sans
régie publicitaire », ce qui reste vrai avec des partenaires directs.

**Tout lien sortant porte une flèche ↗**, posée automatiquement par une règle
de `tokens.css` sur `a[href^="http"]`. Ne pas l'ajouter à la main dans le
balisage : un nouveau lien externe l'obtient sans rien demander. Les adresses
`mailto:` en sont exclues, un courriel n'étant pas un site externe.

**Tout lien sortant s'ouvre dans un nouvel onglet et s'annonce**, demande de
l'auteur du 24 octobre 2026. `src/middleware.ts` réécrit le HTML de chaque page
à la génération : `target="_blank"`, `rel` complété de `noopener`, et
`aria-describedby` vers la phrase unique « Nouvel onglet, quitte sbd.re »,
posée masquée dans `BaseLayout.astro`. Ne rien écrire de tout cela à la main.
Seul un lien fabriqué en JavaScript échappe au traitement : lui appeler
`marquerLienSortant()` de `lib/liens-sortants.ts`, comme la carte des salles.

**Une exception au pack unique : `Coupe.astro` et `Medaille.astro`.** Ce sont
des tracés maison et PLEINS, antérieurs à Tabler, et ils le restent. Deux
raisons. Ils marquent un état dans la donnée, pas une navigation, et le plein
est ce qui porte cette distinction. Et Tabler n'a pas de médaille pleine,
vérifié : `filled/trophy` et `filled/crown` existent, `filled/medal` et
`filled/medal-2` non. Migrer donnerait un podium moitié Tabler, moitié maison,
donc pire que deux tracés maison cohérents entre eux.

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

**Le thème SOMBRE est le défaut, et le réglage du système n'est plus consulté.**
Les rôles sombres vivent sur `:root`, le clair uniquement dans
`:root[data-theme='light']`. Deux conséquences à ne pas casser. Sans
JavaScript, aucun attribut n'est posé sur `<html>` : c'est `:root` qui
s'applique, donc le défaut doit y vivre. Et le repli du bouton de bascule vaut
`'dark'`, pas `'light'`.

**`--accent` est un accent de TEXTE, `--accent-fill` est l'aplat.** Poser
`--accent` en fond sous `--on-accent` donne 2,78:1 en thème clair. L'erreur a
déjà été commise une fois, sur le lien d'évitement. Le thème sombre ne la
révèle pas, les deux jetons y valant la même couleur : toujours vérifier en
clair.

**Astro supprime l'espace avant une balise inline.** Écrire `{' '}` explicitement.
Un script de détection existe, voir docs/parcours.md.

**Le lexique souligné a une source unique, `src/lib/glossaire.ts`**, choix de
l'auteur du 27 octobre 2026 : `definition` sert au glossaire repliable,
`phrase` à la bulle, `formes` aux articles. Seul le PREMIER emploi d'un terme
est souligné, par page et par article. Dans une page, poser
`<Terme cle="…">mot</Terme>` en prose seulement, jamais dans un titre, un lien,
un tableau, une étiquette ou un bouton. Les articles sont soulignés seuls par
le greffon `lib/lexique-markdown.ts` ; une empreinte du
glossaire calculée dans `astro.config.mjs` vide leur cache quand il change. Le
comportement vit dans `lib/bulles-lexique.ts`. Balisage à ne pas casser :
`span.lexique` insécable, qui contient la bulle (`span.lexique-bulle`,
`role="tooltip"`, `hidden`) PUIS le mot (`span.lexique-mot`, `role="button"`,
`tabindex="0"`). Jamais un `<button>` ni un `inline-block`, qui ouvrent une
coupure de ligne après eux. Une définition masquée reste dans `textContent` :
tout script qui lit le texte d'un paragraphe écarte les `.lexique-bulle`. Un
mot à deux sens peut recevoir la mauvaise définition : relire chaque nouvel
article généré, les tours connus étant écartés dans `formes`.

**La carte de l'île vient de l'IGN**, Admin Express COG 2018, par
`scripts/generer-carte-ile.mjs`, qui écrit `src/data/ile-reunion.json`. La
projection vit dans le générateur, et `projeter` de `src/lib/carte.ts` la
recopie pour placer les salles. Toute carte de l'île affiche la clé `source` et
la Licence Ouverte ; l'attribution OpenStreetMap reste pour les positions des
salles. Le placement des noms de commune est mesuré sur Archivo : à remesurer
si la police change.

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
npm run clarity:relever # relève la veille dans Clarity, à lancer chaque jour
node scripts/generer-carte-ile.mjs # redessine la carte de l'île depuis l'IGN
```

Le générateur de carte se lance seulement pour changer de source ou
d'édition, jamais chaque mois. `--source <fichier>` lit une copie locale,
`--mesurer` compare les tolérances sans rien écrire. Il redit aussi si chaque
salle tombe dans sa commune.

**Clarity ne s'exporte pas, il se relève.** Son API ne rend que les 1 à 3
derniers jours, agrégés, à 10 appels par jour. `scripts/relever-clarity.mjs`
écrit la veille dans `clarity/releves.jsonl`, dossier ignoré par git où vit
aussi le jeton. Un mois de données demande un mois de relevés. Les cartes de
chaleur et l'élément d'un clic mort ne passent pas par l'API : captures d'écran.


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
prêt dans [db/schema.sql](db/schema.sql).

**Le premier point d'entrée PHP public existe depuis le 10 septembre 2026** :
`public/api/contact.php`, derrière la page `/partenariats/`. Il sert de modèle
aux suivants. Destinataire écrit en dur, aucune donnée du visiteur dans les
en-têtes, champ appât, délai minimal, et trois envois par heure et par adresse
IP comptés avant l'envoi. Les bibliothèques de `public/api/lib/` restent en 403,
gardées par leur propre `.htaccess` et par `if (!defined('SBDRE_API'))`.

**L'ENVOI DE COURRIEL EST PROUVÉ EN PRODUCTION**, et cela débloque la partie
communauté, qui repose entièrement sur un courriel de vérification. Le serveur
poste avec `mail()`, sans SMTP authentifié, donc sans boîte à créer ni mot de
passe à déposer. L'expéditeur est `no-reply@sbd.re`, une adresse du domaine, ce
qu'exige le SPF. Testé le 10 septembre 2026 vers un domaine extérieur : le
message arrive.

`db/verifier-envoi.php` rejoue cet envoi. Il refuse de tourner sur une machine
de développement, `mail()` demandant un serveur de courrier local, et le dit au
lieu d'échouer en silence.

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

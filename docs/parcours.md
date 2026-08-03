# Parcours du projet — décisions, découvertes et pièges

Ce document retrace **pourquoi** le projet est ce qu'il est. Le [README](../README.md)
explique comment il fonctionne ; celui-ci explique comment on y est arrivé, et ce
qui a été appris en chemin.

À lire avant de modifier quoi que ce soit de structurant : plusieurs choix qui
paraissent arbitraires sont en réalité contraints par des faits vérifiés.

---

## 1. Les quatre écarts au brief initial

Le cahier des charges de départ a évolué. Chaque écart a été décidé
explicitement, jamais subi.

| Prévu au départ | Retenu | Pourquoi |
|---|---|---|
| Hébergement GitHub Pages | **OVH mutualisé** | Choix d'hébergeur. GitHub reste utilisé, mais uniquement comme automate. |
| Thème sombre uniquement | **Clair + sombre** | Demande explicite, avec fonds atténués plutôt que noir et blanc purs. |
| Ni comptes ni backend | **Comptes et API PHP prévus** (communauté) | Demande explicite. Limité à la partie non-officielle ; l'officiel reste statique et en lecture seule. |
| Full power (SBD) uniquement | **Tous les formats** | Les chiffres ont montré que la restriction coûtait un tiers des données. |

**Ce qui n'a pas bougé** : aucun compte ni soumission ne peut atteindre le
classement officiel, pas de CMS, pas de scraping de la FFForce, pas de
publicité, et l'interdiction du combo noir + rouge (identité de SBD Apparel).

**Mesure d'audience, ajoutée en juillet 2026.** Microsoft Clarity fournit des
cartes de chaleur et des relectures de session, mais **derrière un consentement
explicite**. Il dépose des cookies et transmet les données à Microsoft : il ne
relève donc pas de l'exemption que la CNIL accorde à la simple mesure
d'audience. Tant que rien n'est accepté, aucune requête ne part vers
clarity.ms. Le refus est aussi accessible que l'acceptation, et révocable à
tout moment depuis `/confidentialite/`.

---

## 2. Ce que les données ont révélé

Toutes ces informations viennent de `scripts/explore-meets.mjs`, écrit et
exécuté **avant** la moindre ligne de filtrage. C'est la décision de méthode la
plus rentable du projet.

### La région n'existe pas dans les données

Le champ `MeetState` est **vide sur la totalité des 45 697 résultats** de la
fédération française. Aucun moyen automatique d'identifier La Réunion. Tout le
mécanisme d'inclusion manuelle découle de ce constat.

### La règle du nom seul rate un quart des compétitions

Filtrer sur « le nom contient Réunion » trouve 47 compétitions sur 61. Les 14
manquantes ne sont pas des cas limites : *Open de la Fournaise* (le volcan),
*Bench Cup 974*, *Fanm Kapab Contest* (créole), *Coupe de l'Étang-Salé*. D'où
`data/meets-include.json`, tenu à la main.

### Le risque des villes homonymes est théorique ici, mais réel ailleurs

Les seules communes réunionnaises présentes dans les données FFForce sont
L'Étang-Salé, Saint-Paul, Sainte-Marie, Saint-Joseph, La Plaine-des-Palmistes,
Saint-Leu et Le Tampon. **Saint-Denis et Saint-Louis n'y figurent pas** — les
deux pièges les plus évidents. Aucun faux positif métropolitain aujourd'hui,
mais rien ne le garantit demain : `meets-exclude.json` existe pour ça, et la
ville ne déclenche **jamais** une inclusion automatique.

### Le développé couché seul n'est pas anecdotique

26 des 61 compétitions réunionnaises sont des compétitions de bench seul. Les
écarter — ce que prévoyait le brief — aurait rendu **10 des 26 records de
développé couché faux**, dont ceux de Gabriel Begue (205 kg) et Thierry Robert
(200 kg). Vérifié après coup, pas supposé.

À l'inverse, l'ajout des compétitions de squat seul et de soulevé de terre seul
(46 résultats) n'a déplacé **aucun** record. Elles complètent les historiques
d'athlètes sans changer le palmarès.

### Le piège principal du jeu de données

Sur une compétition d'un seul mouvement, OpenPowerlifting **renseigne quand même
`totalKg`** — avec la valeur de l'unique barre — et calcule un score Dots dessus.
Sans filtrage explicite, 343 « totaux » de développé couché viendraient se
classer à côté de vrais totaux full power.

D'où la règle absolue du code : **tout classement au total part de
`fullPowerResults`, jamais de `results`.** Voir l'avertissement en tête de
`src/lib/rankings.ts`.

### Les homonymes

OpenPowerlifting distingue les athlètes de même nom par un suffixe : `Lucas
Martin #3`, `Matéo Lopez #1`. Le numéro doit survivre dans l'URL, sinon deux
athlètes fusionnent et un record peut être attribué à la mauvaise personne. Le
build échoue volontairement en cas de collision de slug.

---

## 3. Les pièges techniques rencontrés

Ceux qui coûtent du temps et qu'on ne devine pas.

### Astro supprime l'espace avant une balise inline

`texte\n<a>lien</a>` produit `texteLien`, sans espace. Quatre occurrences
étaient passées inaperçues. Le correctif est `{' '}` explicite. Un script de
détection existe : il compare le HTML généré pour repérer les mots collés à une
balise. **À relancer après toute modification de texte contenant des liens.**

### Oswald ignore `tabular-nums`

La police de titrage n'a pas de chiffres à largeur fixe. Mesuré : en Oswald 700,
« 111 » occupe 18,5 px là où « 888 » en occupe 26,4. Dans une colonne de 266
lignes, les totaux se décalent visiblement.

D'où deux classes CSS distinctes — `.num` (Oswald, chiffre isolé et grand) et
`.num-tab` (Inter, chiffre dans une colonne). **Avant d'employer une police de
titrage dans un tableau, vérifier qu'elle gère `tabular-nums`.**

### Google Fonts en lien direct pose un problème RGPD

Charger une police depuis `fonts.gstatic.com` transmet l'adresse IP de chaque
visiteur à Google. Les polices sont auto-hébergées, en version **variable** :
un fichier par famille couvre toutes les graisses. 170 Ko sur le disque au lieu
de 600 Ko, et environ 68 Ko réellement transférés.

### Le contraste ne se joue pas là où on le croit

Lighthouse signalait l'accent orange comme trop clair. Le fond de page donnait
pourtant 4,16:1 — proche du seuil. Le vrai coupable était **l'onglet de
navigation actif**, qui pose du texte accentué sur un fond teinté du même accent
à 12 % : 3,56:1.

Corollaire : le fond de page seul est trompeusement permissif. Les cinq
contrastes à vérifier sont documentés dans `src/styles/tokens.css`.

Autre enseignement : **changer de teinte ne règle rien**. Le contraste dépend de
la luminosité. Aucune valeur de rouge ne satisfait les deux thèmes à la fois —
`#dc2626` passe en clair et échoue en sombre, `#b91c1c` fait l'inverse.

### Les fins de ligne Windows cassent les workflows

Un bloc `run:` enregistré avec des retours chariot CRLF échoue sous Linux.
`.gitattributes` force le LF dans le dépôt.

---

## 4. Le déploiement OVH : quatre échecs et ce qu'ils ont appris

Le point le plus laborieux du projet. Chaque échec a livré une information.

| Échec | Cause | Enseignement |
|---|---|---|
| `500 This security scheme is not implemented` | **Le mutualisé OVH n'implémente pas le FTPS** | Le FTP chiffré n'est pas disponible ; il faut passer par SSH. |
| rsync échoue après une connexion SSH réussie | **rsync doit être installé des deux côtés** | Il lance son binaire sur le serveur distant, absent d'un mutualisé. `lftp` parle SFTP nativement. |
| lftp se connecte à un hôte nommé `$SSH_HOST` | **lftp n'interprète pas les variables du shell** | Les échapper les transmet littéralement. C'est à bash de les développer. |
| Hôte SSH | **OVH expose `ssh.clusterXXX`, distinct de `ftp.clusterXXX`** | Deux noms d'hôtes différents pour deux services. |

**Les actions toutes faites du Marketplace ne conviennent pas** : celles pour OVH
clonent le dépôt git *sur le serveur*. Ici le dépôt contient le code source, pas
le site — `dist/` est généré au build et exclu de git.

**Deux exclusions vitales dans la purge** : `.well-known/` porte la validation du
certificat Let's Encrypt, `.ovhconfig` la configuration d'exécution OVH. Les
supprimer casserait le renouvellement HTTPS quelques semaines plus tard — une
panne dont on ne comprendrait pas l'origine.

---

## 5. La méthode qui a fonctionné

Ce qui est transposable à d'autres projets.

**Observer avant de filtrer.** Un script de reconnaissance jetable, exécuté avant
d'écrire la moindre règle, a évité de construire tout le site sur l'hypothèse
fausse que la région était renseignée.

**Vérifier par la mesure, pas à l'œil.** L'idempotence du pipeline est prouvée
par une empreinte SHA-256 identique entre deux exécutions. Le contraste est
calculé, pas estimé. Les mots collés sont détectés par un script. La largeur des
chiffres a été mesurée dans le navigateur.

**Écrire des tests destinés à échouer.** Pour la future partie communauté, les
vérifications prévues consistent à *tenter* de valider sa propre performance ou
de modifier celle d'un autre. Vérifier qu'une sécurité bloque vaut mieux que
vérifier que le site marche.

**Déployer avant de brancher le domaine.** On a confirmé l'arrivée des fichiers
au bon endroit avant d'y diriger `sbd.re` — jamais de site à moitié installé
exposé au public.

**N'activer une opération destructrice qu'après confirmation.** La purge des
fichiers obsolètes (`--delete`) n'a été activée qu'après un premier déploiement
réussi. Combinée à une erreur de chemin, elle aurait vidé l'hébergement.

---

## 6. Ce qui a été ajouté après la mise en ligne

### Les résultats hors de La Réunion

Les athlètes de l'île se déplacent : 436 résultats concernent 73 des 312
athlètes, dans 28 pays, depuis 1993. Ils vivent sur `/exterieur/` et sur les
fiches, **sans jamais entrer au classement ni aux records**, qui récompensent
des barres soulevées sur un plateau réunionnais.

Techniquement, cela impose **deux passes** sur le dump : la liste des athlètes
réunionnais n'est connue qu'après avoir lu tout le fichier.

**Un faux appariement a été trouvé** : des tournois lycéens texans attribués à
un « Kobe Washington » présent à La Réunion en 2023. Deux personnes différentes
qu'OpenPowerlifting n'avait pas distinguées. Le rattachement se faisant par le
nom exact, on hérite de leurs erreurs d'identification.
`data/exterieur-exclude.json` sert à corriger ces cas ; **d'autres existent
probablement**.

### Les pages par catégorie

Dix-sept catégories, chacune avec son classement, ses records et surtout
**l'histoire de son record au total** : qui l'a détenu, de combien il l'a battu,
combien de temps sa marque a tenu. En −93 kg hommes, neuf marques se sont
succédé depuis 2017.

Cette chronologie existait dans les données depuis le début sans être affichée
nulle part. Ces pages répondent aussi à des recherches réelles du type « record
squat 93 kg réunion », là où le site n'avait qu'une seule page de records.

### Affichage en cartes : un arbitrage entre deux demandes

L'auteur a demandé des **en-têtes de colonnes cliquables** pour trier, puis un
**affichage en lignes** de quatre niveaux par athlète. Les deux s'excluent :
sans colonnes visibles, il n'y a plus d'en-tête à cliquer.

Choix retenu : les cartes partout, et une **barre « Trier par »** construite à
partir des mêmes en-têtes, restés présents pour les lecteurs d'écran. Les deux
mécanismes partagent la même logique de tri.

Conséquence importante : les valeurs de tri ont migré des cellules vers la
**ligne**, en attributs `data-`. Plusieurs valeurs cohabitant dans une même
cellule, l'index de colonne ne voulait plus rien dire. Voir le contrat détaillé
dans `CLAUDE.md`.

### Le meilleur total et le meilleur Dots sont deux choses distinctes

Le score Dots affiché était celui du meilleur total, ce qui n'est pas la même
chose que le meilleur Dots. En descendant de catégorie, un athlète obtient un
meilleur Dots avec un total inférieur. Les deux sont désormais calculés
séparément, chacun avec sa date et sa compétition.

Le défaut a été révélé par une demande de l'auteur d'afficher « la date du
Dots », qui supposait implicitement que ce score a sa propre date.

### La mesure d'audience

Microsoft Clarity a été ajouté **derrière un consentement explicite**. Il dépose
des cookies et transmet les données à Microsoft : il ne relève donc pas de
l'exemption que la CNIL accorde à la simple mesure d'audience.

Le script n'est jamais posé directement. Seule la fonction
`window.sbdChargerMesure()` est définie, et elle n'est appelée qu'après
acceptation. Vérifié dans le navigateur : aucune requête vers `clarity.ms` avant
accord, aucune après refus.

Le texte de la bannière reste court et ne nomme pas Microsoft, à la demande de
l'auteur. Ce schéma est valide **à condition que le lien vers
`/confidentialite/` reste visible** : sans lui, le consentement ne serait plus
éclairé.

---

## 7. L'audit ergonomique du 2 août 2026

L'auteur voulait appliquer au site les conventions d'ergonomie d'Apple, à partir
des skills <https://github.com/quin566/ios-ui-design-skills>, installés dans
`.claude/skills/`. Le périmètre était tranché d'avance : **l'usage, pas le
goût**. Le système de marque *Modernist* reste intact, et l'esthétique iOS,
ombres multicouches et matériaux translucides, restait écartée.

L'audit s'est fait par la mesure dans le navigateur, jamais à la relecture.
C'est ce qui l'a rendu utile, parce qu'il a démenti trois choses que le projet
tenait pour acquises.

### Ce que la mesure a démenti

**Le site n'avait aucune cible tactile conforme.** Pas « quelques-unes trop
petites » : à 375 px de large, aucun contrôle n'atteignait les 44 px, du bouton
de tri à 31 px aux liens de pied de page à 15 px. C'est le genre de constat
qu'aucune relecture de code ne produit, parce que le CSS ne dit nulle part
combien de pixels un bouton fait au bout du compte.

**La grille d'espacement était déjà bonne.** `CLAUDE.md` affirmait que les
jetons `--space-*` ne suivaient aucune grille. Sept sur huit sont des multiples
de 8, et le huitième est un multiple de 4. Il n'y avait rien à corriger, juste à
documenter. Une demi-journée de refonte évitée par une addition.

**Les états vides n'étaient pas absents.** `/comparer/` en avait déjà un,
complet, avec son action de sortie. Trois autres existaient mais étaient des
impasses : un constat, aucune issue. Le vrai défaut n'était pas l'absence, il
était la forme.

### Le piège : un jeton d'accent employé comme un aplat

Le seul défaut de contraste du site vivait dans le lien « Aller au contenu »,
le tout premier élément qu'atteint une navigation au clavier. Il posait
`--accent` en fond sous `--on-accent` : en thème clair, du basalte sur le jaune
sombre, soit **2,78:1**.

`tokens.css` documentait pourtant la règle depuis le départ. `--accent` est un
accent de TEXTE, `--accent-fill` est l'aplat, et la paire basalte sur aplat vaut
10,66:1. La distinction était écrite, commentée, expliquée, et employée à
l'envers vingt lignes plus loin.

Deux raisons à sa survie. En thème sombre les deux jetons valent la même
couleur, donc le défaut y était invisible. Et le lien est hors écran tant qu'il
n'a pas le focus, donc invisible tout court à qui navigue à la souris.

**La leçon vaut au-delà de ce cas.** Un audit de contraste qui ne teste que le
thème par défaut, et que les éléments visibles au repos, passe à côté de ce
genre de trou. Il faut parcourir les éléments dans les deux thèmes et les forcer
dans leurs états, focus compris.

### Ce qui a été corrigé

| | Avant | Après |
|---|---|---|
| Boutons de tri | 31 px | 44 px |
| Sélecteurs de filtre | 35 px | 44 px |
| Champ de recherche | 39 px | 44 px |
| Bouton de thème | 40 × 40 px | 44 × 44 px |
| Menu | 41 px | 44 px |
| Onglets de navigation | 39 px | 44 px |
| Liens de pied de page | 15 px | 44 px |
| Lien d'évitement, thème clair | 2,78:1 | 10,66:1 |

La règle des 44 px vit dans `tokens.css`, une seule fois. Les quatre pages de
listes répétaient chacune la même règle `select` sans qu'aucune ne fixe de
hauteur : corriger à la racine valait mieux que corriger quatre fois.

Trois exclusions sont assumées et documentées dans le code. Les cases à cocher
et boutons radio, que le navigateur dessine lui-même. Les boutons d'en-tête de
tableau, qui n'existent qu'au pointeur puisque l'en-tête est masquée en cartes.
Et les liens en ligne dans les cartes, noms d'athlètes et dates, qui sont des
liens de prose dans une carte de 149 px et non des commandes.

### Le second piège : une troncature qui élargit la carte

Signalé par l'auteur après coup, sur une capture d'écran de téléphone : les
cartes de compétition de l'accueil étaient coupées, et l'effectif d'athlètes
avait disparu.

La cause tient en une déclaration. Le nom de la compétition portait
`white-space: nowrap` avec des points de suspension. On perd la fin du nom,
c'est visible et c'était le but. Mais en `nowrap`, la largeur **minimale** de
l'élément devient celle du texte entier. Le `li` qui le contient est un élément
de grille, donc en `min-width: auto`, et il refuse de descendre sous cette
largeur. La carte mesurait 433 px dans une colonne de 343, et sa partie droite
sortait de l'écran.

Une troncature censée faire tenir le contenu le faisait donc déborder.

**Et la mesure de débordement employée jusque-là ne le voyait pas.** Le test
`document.documentElement.scrollWidth > innerWidth` retournait `false`, parce
qu'un conteneur en `overflow: hidden` coupe sans que la page ne s'élargisse.
Ça ne déborde pas, ça disparaît. Il faut mesurer par élément, `scrollWidth`
contre `clientWidth`, et comparer la largeur de chaque boîte à celle de
`.wrap`.

Le balayage ainsi corrigé a trouvé quatre choses : les cartes de l'accueil, les
noms de détenteurs de records sur les deux pages de catégories, le tableau de
comparaison qui cachait 216 px sans le dire, et **une régression introduite le
jour même** dans le pied de page, où un rembourrage compensé par une marge
négative élargissait la liste de 12 px au-delà de sa colonne. Elle était passée
parce que la vérification portait sur la puce de survol, pas sur la boîte.

### Ce qui reste ouvert

Les états d'erreur n'ont pas de cas d'emploi aujourd'hui : le site est statique
et rien n'y échoue. Les jetons `--succes`, `--alerte` et `--danger` sont posés,
mesurés, et attendent les formulaires de la partie communauté.

Les niveaux de texte restent à deux, `--text` et `--muted`, là où la
convention en propose quatre. Rien n'en demande un troisième aujourd'hui, et un
jeton sans usage est un jeton qui dérive.

## 8. État au 30 juillet 2026

| | |
|---|---|
| En ligne | <https://sbd.re> |
| Dépôt | <https://github.com/loremipsum-re/sbd-re> |
| Données | 61 compétitions, 312 athlètes, 1 135 résultats à La Réunion, plus 436 à l'extérieur |
| Pages | 436 |
| Pipeline | 4 M de lignes en ~10 s par passe, deux passes, mémoire stable à ~115 Mo |
| Poids réseau | Page la plus lourde : 271 Ko bruts, **21,4 Ko** après gzip |
| Lighthouse | 100 partout, mesuré par l'auteur après la refonte |
| Référencement | Plan de site de 433 adresses, datées page par page. Search Console en cours |
| Automatisation | Données le 1er du mois à 8 h (heure de La Réunion), déploiement à chaque envoi |

### Ce qui reste

**En attente de l'auteur** : le logo, pour activer les OpenGraph.

**Trois arbitrages ouverts** : attribution des médailles par catégorie ou sur
le podium général, script Buy Me a Coffee officiel ou bouton maison, nom de
l'onglet Extérieur.

**Chantier principal** : la partie communauté, classement non-officiel, comptes
Google et e-mail, soumissions modérées, tranches de poids et de taille.

Sa conception a été arrêtée le 30 juillet 2026 et vit dans son propre document,
[communaute.md](communaute.md). Le point à retenir ici : **Supabase est écarté**
au profit d'une API PHP devant une base MySQL, sur l'hébergement OVH déjà payé.
La décision est celle de l'auteur, pour rester chez un seul fournisseur dans un
langage qu'il maîtrise.

Elle déplace le risque sans le réduire. MySQL ne connaît pas les politiques
d'accès RLS de PostgreSQL : là où la base aurait refusé elle-même une écriture
interdite même en cas de bug du front-end, **c'est désormais le code PHP qui
doit vérifier chaque droit**. Le « seul endroit du projet où une erreur
permettrait à un inconnu de trafiquer le classement » n'a pas disparu, il a
changé de fichier.

Deux faits vérifiés en chemin, qui contraignent tout le reste : une base de
données ne se parle jamais depuis un navigateur, ce qui rend une couche serveur
obligatoire dès lors que les données quittent le HTML statique ; et les bases
incluses dans un mutualisé OVH ne sont joignables **que depuis l'hébergement**,
le port 3306 n'étant pas exposé. Développer exige donc un MySQL local, et aucune
migration de schéma ne peut être automatisée.

Elle repose aussi sur une hypothèse jamais vérifiée, à savoir que des athlètes
veuillent déclarer leurs performances de salle. Un premier retour d'un
powerlifter a été positif sur la partie officielle ; rien ne dit encore que la
partie communauté trouvera son public.

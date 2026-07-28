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
| Ni comptes ni backend | **Comptes prévus** (communauté) | Demande explicite. Limité à la partie non-officielle ; l'officiel reste en lecture seule. |
| Full power (SBD) uniquement | **Tous les formats** | Les chiffres ont montré que la restriction coûtait un tiers des données. |

**Ce qui n'a pas bougé** : aucun compte ni soumission ne peut atteindre le
classement officiel, pas de CMS, pas de scraping de la FFForce, pas d'analytics
invasif, et l'interdiction du combo noir + rouge (identité de SBD Apparel).

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

## 6. État au 28 juillet 2026

| | |
|---|---|
| En ligne | <https://sbd.re> |
| Dépôt | <https://github.com/loremipsum-re/sbd-re> |
| Données | 61 compétitions, 312 athlètes, 1 135 résultats, 2017 → 2026 |
| Pages | 319, dont 312 fiches athlètes |
| Pipeline | 4 M de lignes en ~10 s, mémoire stable à ~115 Mo |
| Poids réseau | Page la plus lourde : 271 Ko bruts, **21,4 Ko** après gzip |
| Lighthouse | Performance, bonnes pratiques et SEO à 100 ; accessibilité corrigée |
| Automatisation | Données le 1er du mois à 8 h (heure de La Réunion), déploiement à chaque envoi |

### Ce qui reste

La **partie communauté** — classement non-officiel, comptes Google et e-mail via
Supabase, soumissions modérées, tranches de poids et de taille. Elle nécessite un
projet Supabase en région Europe et un projet Google Cloud pour l'OAuth.

L'étape la plus délicate y sera l'écriture des **politiques d'accès RLS** : c'est
le seul endroit du projet où une erreur permettrait à un inconnu de trafiquer le
classement.

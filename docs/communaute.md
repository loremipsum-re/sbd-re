# La partie communauté — conception

Ce document est au classement communauté ce que [parcours.md](parcours.md) est au
site officiel : il explique **pourquoi** l'architecture est celle-là, et quels
faits la contraignent.

Rien n'est encore construit. Le schéma de base existe
([db/schema.sql](../db/schema.sql)), le code PHP non.

---

## 1. Ce qui a changé par rapport au plan initial

Le plan prévoyait **Supabase** : Postgres hébergé, avec authentification
fournie et sécurité déclarée dans la base par des politiques RLS. Retenu
finalement : **PHP et MySQL sur l'hébergement OVH déjà payé.**

La décision est celle de l'auteur, pour rester chez un seul fournisseur, sans
tiers dans la boucle, dans un langage qu'il maîtrise. Voici ce qu'elle implique,
parce que ce n'est pas un simple changement de fournisseur de base.

### Une base de données ne se parle pas depuis un navigateur

C'est le fait central, et il vaut pour n'importe quelle base. Interroger MySQL
depuis le JavaScript d'une page supposerait d'y placer l'identifiant et le mot
de passe de la base, que n'importe quel visiteur lirait dans le code source pour
ensuite vider ou réécrire le classement.

Supabase n'était donc pas « une base ailleurs » : c'était une base **plus une
API HTTP devant elle, plus un système de règles de sécurité**. Ce sont ces trois
briques ensemble qui permettaient à un site statique de fonctionner sans
serveur. MySQL chez OVH ne fournit que la première. **Les deux autres sont à
écrire**, et c'est là tout le travail.

### Les bases OVH mutualisées ne sont joignables que depuis l'hébergement

Vérifié auprès de la documentation et du support OVH : le port 3306 n'est pas
exposé à l'extérieur, et une base incluse dans un mutualisé s'atteint uniquement
depuis cet hébergement. OVH renvoie vers un produit séparé et payant,
*Web Cloud Databases*, pour l'accès distant.

Conséquences pratiques :

- **Aucun outil de la machine de développement ne peut viser la base.** Ni un
  client graphique, ni un tunnel SSH : OVH a fermé cette dernière voie vers ses
  bases mutualisées. Ce qui tourne sur l'hébergement, en revanche, y accède sans
  difficulté.
- **GitHub Actions ne peut pas migrer la base.** Aucune automatisation de
  schéma : chaque évolution se charge à la main. À l'échelle du projet, ce n'est
  pas un problème, mais il faut le savoir avant de compter dessus.

### Le serveur OVH sert lui-même de banc d'essai

Le corollaire est utile : puisque seul l'hébergement atteint la base, c'est
**depuis l'hébergement** qu'on l'éprouve. L'accès SSH existe déjà, le
déploiement s'en sert, et OVH documente l'emploi du client `mysql` dans cet
environnement. Deux voies, aucune n'exposant quoi que ce soit sur le web :

| Voie | Pour quoi faire |
|---|---|
| **phpMyAdmin** | Charger le schéma, relever les versions, inspecter les tables. Limité en taille de fichier, sans conséquence ici. |
| **SSH et client `mysql`** | Tout le reste : exécuter, mesurer, réinitialiser. C'est la voie de travail. |

Deux règles à ne pas enfreindre.

**Une base de test distincte de la base de production.** L'offre Pro en fournit
dix : il n'y a aucune raison d'éprouver un schéma, et encore moins des tentatives
d'écriture interdite, sur les données réelles. Les tests destinés à échouer de la
section 8 supposent une base qu'on peut vider sans réfléchir.

**Aucun script de diagnostic servi en HTTP.** Un fichier PHP déposé dans
l'arborescence web répond à quiconque le demande, et un script qui se connecte à
la base ou charge un schéma est exactement ce qu'on ne veut pas laisser
accessible. Tout ce qui doit être exécuté passe par SSH, où rien n'est publié.
Si un tel script devenait nécessaire, il se dépose à la main hors de `dist/`,
s'utilise, et se supprime aussitôt.

**Ce que le serveur ne remplace pas.** Éprouver un schéma et relever des
versions, oui. Écrire l'authentification, non : chaque correction demanderait un
envoi de fichier, sur l'hôte qui sert le site public. Un PHP et un MySQL locaux
resteront le bon outil pour cette étape, mais ils ne bloquent plus rien
maintenant.

### Un invariant du projet tombe

Le README affirmait « OVH ne fait que servir des fichiers HTML. Aucun code ne
s'exécute sur le serveur ». Ce n'est plus vrai. Les 436 pages officielles
restent du HTML statique généré par Astro, mais des fichiers PHP vivront à côté
d'elles.

Cet écart est assumé et documenté ici. Le classement **officiel** reste, lui,
strictement en lecture seule et sans base de données : aucune panne de MySQL, ni
aucune faille de l'API, ne peut l'altérer. C'est la séparation la plus précieuse
du projet et elle est préservée.

---

## 2. L'architecture

```
   Navigateur
      │
      │  1. HTML statique, généré par Astro (436 pages officielles + pages communauté)
      │
      │  2. fetch('/api/...') en même origine, avec un cookie de session
      ▼
   Apache sur OVH
      ├── fichiers .html   servis tels quels
      └── /api/*.php  ─────> MySQL (même hébergement, non exposé)
```

Ce que cette forme apporte :

- **Aucune clé dans le navigateur.** L'API étant sur la même origine que le
  site, le navigateur n'a besoin d'aucun identifiant, d'aucune clé publique,
  d'aucun jeton d'API. Un cookie de session suffit. C'est plus simple, et
  strictement plus sûr, que le montage Supabase où une clé publique partait
  dans chaque page.
- **Le build ne change pas.** Astro continue de produire `dist/`, déployé par
  `lftp` comme aujourd'hui. Les fichiers PHP se déposent dans `public/`, qu'Astro
  recopie tel quel : ils suivent le même chemin de déploiement, sans nouvelle
  automatisation.
- **Le secret client Google reste côté serveur**, ce qu'un site purement statique
  ne permettait pas.

### Trois points de vigilance sur le déploiement

**Le fichier de configuration doit survivre à la purge.** Le déploiement lance
`lftp` en mode miroir avec `--delete`, qui supprime chez OVH tout ce qui n'est pas
dans `dist/`. Le fichier portant le mot de passe MySQL, déposé une fois à la
main, serait donc effacé au premier envoi. Il faut l'exclure explicitement, comme
le sont déjà `.well-known/` et `.ovhconfig`. Cette exclusion oubliée, le site
tombe en erreur 500 après un déploiement, et la cause n'a rien d'évident.

**Le fichier de configuration ne doit jamais être servi en HTTP.** Placé sous la
racine web, `config.php` répondrait à une requête directe. Deux protections à
cumuler : le placer hors de l'arborescence servie, et le refuser dans
`.htaccess`.

**`.htaccess` demande deux ajouts** : autoriser PHP à répondre sous `/api/`, et
refuser tout accès direct aux fichiers de configuration. Rappel du fichier
actuel : une erreur de syntaxe y rend le site entièrement inaccessible.

---

## 3. Le modèle de données

Quatre tables, détaillées et commentées dans
[db/schema.sql](../db/schema.sql) : `membre`, `performance`, `jeton`,
`tentative_connexion`.

Les décisions qu'il encode, et qui méritent d'être connues avant d'y toucher.

### Le vocabulaire est celui du site officiel

`mouvement` reprend les identifiants de `LIFTS`, `equipement` ceux de
`EquipmentGroup`, les tranches ceux de `COMMUNITY_WEIGHT_BRACKETS` et
`COMMUNITY_HEIGHT_BRACKETS`, tous définis dans
[src/lib/categories.ts](../src/lib/categories.ts). Aucune table de
correspondance entre la base et l'affichage : un seul vocabulaire de bout en
bout.

### Le poids est porté par la performance, la taille par le membre

Une taille ne bouge plus à l'âge adulte. Un poids de corps, si. Quelqu'un qui
prend dix kilos ne doit pas voir ses anciennes barres changer de tranche
rétroactivement : la tranche est figée au moment de la barre.

### Aucun total n'est saisissable

Seuls les trois mouvements existent en base. Un « total » déclaré en salle ne
veut rien dire : rien ne garantit que les trois barres ont été faites le même
jour. Le total communauté est **calculé à la lecture**, en additionnant les trois
meilleures barres publiées d'un membre à équipement égal, et seulement si les
trois existent.

C'est le même raisonnement que l'invariant du site officiel, où un total n'a de
sens qu'en full power. Le stocker dans une colonne le laisserait périmer au
premier nouveau record.

### Il n'y aura pas de score Dots

Le Dots exige un poids de corps au kilo près. Les tranches déclaratives ne le
fournissent pas, et prendre le milieu de la tranche fabriquerait un chiffre
faux, ce que le projet s'interdit partout ailleurs.

**Conséquence à mesurer** : le classement communauté se lit par tranche, sans
score relatif, donc sans « meilleur athlète toutes catégories » comme l'accueil
en propose côté officiel. C'est le prix du choix des tranches, qui reste le bon
choix pour la participation et pour le RGPD. À revoir seulement si l'auteur
préfère demander un poids exact.

### Le nom public est au choix de l'athlète

`pseudo` est obligatoire et unique, `nom_reel` facultatif, et
`afficher_nom_reel` décide lequel s'affiche. Un athlète peut donc apparaître
sous son nom réel comme le classement officiel, ou rester sous pseudo.

`slug` est stocké et unique, plutôt que recalculé : « Jean-Luc » et « Jean Luc »
produisent le même slug, et deux fiches se marcheraient dessus. Le site officiel
fait échouer son build sur ce genre de collision ; ici c'est l'index unique qui
refuse.

### Le lien vidéo est obligatoire

Sans vidéo, la modération n'aurait rien à examiner. C'est ce champ qui rend le
reste crédible, et il est `NOT NULL` en base autant que vérifié en PHP.

---

## 4. Ce qui remplace les politiques RLS

**C'est la section la plus importante du document.**

`parcours.md` désignait les politiques RLS comme « le seul endroit du projet où
une erreur permettrait à un inconnu de trafiquer le classement ». Ce risque ne
disparaît pas avec MySQL : **il déménage dans le code PHP.**

La différence est réelle et il faut la regarder en face. Avec des politiques RLS,
la base refuse elle-même une écriture interdite, même si le front-end a un bug,
même si quelqu'un forge une requête à la main. Avec MySQL, la base exécute tout
ce qu'on lui demande. **Chaque point d'entrée doit vérifier les droits
lui-même**, et un seul oubli est une brèche ouverte.

Trois règles pour obtenir en PHP la même discipline :

**Un seul endroit décide.** Un fichier `autorisation.php` expose des fonctions
`exigerMembre()`, `exigerProprietaire($performance)`, `exigerModerateur()`.
Aucun point d'entrée n'écrit sa propre vérification : il appelle l'une de ces
fonctions. Une règle à revoir se revoit alors en un seul endroit.

**Le refus est le comportement par défaut.** Un point d'entrée commence par
refuser et n'autorise qu'ensuite. Écrit dans l'autre sens, un `if` oublié laisse
passer au lieu de bloquer, et la faille est invisible à la lecture.

**Rien de sensible ne vient du client.** L'identifiant du membre se lit dans la
session, jamais dans le corps de la requête. Un champ `membre_id` envoyé par le
navigateur permettrait de soumettre au nom de quelqu'un d'autre. De même,
`etat` et `role` ne sont jamais modifiables par leur propriétaire : c'est
exactement le geste qui permettrait de publier sa propre performance sans
modération.

S'y ajoute l'hygiène habituelle, dont l'absence coûte cher :

- **Requêtes préparées partout**, aucune concaténation de chaîne dans du SQL.
- **`password_hash()` et `password_verify()`**, jamais un hachage maison.
- **Cookie de session** en `Secure`, `HttpOnly`, `SameSite=Lax`.
- **Jeton anti-CSRF** sur toute requête qui écrit.
- **Freinage des tentatives de connexion**, par adresse et par IP.
- **Jetons stockés sous forme d'empreinte**, jamais en clair.

---

## 5. Les points d'entrée prévus

| Méthode et adresse | Qui | Rôle |
|---|---|---|
| `POST /api/inscription` | tous | Créer un compte par courriel |
| `POST /api/connexion` | tous | Ouvrir une session, freinée en cas d'échecs |
| `POST /api/deconnexion` | membre | Fermer la session |
| `GET /api/google/depart` | tous | Rediriger vers Google |
| `GET /api/google/retour` | tous | Recevoir Google, créer ou retrouver le compte |
| `POST /api/mot-de-passe/oubli` | tous | Envoyer un lien de réinitialisation |
| `POST /api/mot-de-passe/reinitialiser` | porteur du jeton | Changer le mot de passe |
| `GET /api/profil` | membre | Lire son profil |
| `PATCH /api/profil` | membre | Modifier pseudo, nom, tranche de taille |
| `DELETE /api/profil` | membre | Effacer son compte et ses performances |
| `GET /api/performances` | tous | Lire le classement, **publiées seulement** |
| `POST /api/performances` | membre vérifié | Soumettre une barre |
| `PATCH /api/performances/{id}` | propriétaire | Corriger, ce qui repasse en attente |
| `DELETE /api/performances/{id}` | propriétaire | Retirer sa barre |
| `GET /api/moderation` | modérateur | Lire la file d'attente |
| `POST /api/moderation/{id}` | modérateur | Publier ou refuser avec motif |

Aucun point d'entrée ne permet de changer son propre rôle. La promotion d'un
modérateur se fait à la main en base.

---

## 6. Le parcours d'une performance

```
   soumission ──> en_attente ──> publiee ──> apparaît au classement
                      │              │
                      │              └── modifiée par l'auteur ──> en_attente
                      │
                      └──> refusee (avec motif, visible par l'auteur)
```

Deux points qui se devinent mal :

- **Une performance publiée puis modifiée repasse en attente.** Sinon on
  publierait 150 kg avant de le remplacer par 300 kg sans repasser devant
  personne.
- **Un refus porte toujours un motif**, montré à l'auteur. Un refus muet fait
  fuir quelqu'un qui avait simplement mal saisi sa date.

L'historique des décisions n'est **pas** journalisé : la performance porte son
état courant, son modérateur et sa date. C'est un choix délibéré, tant que
l'auteur modère seul. Le jour où plusieurs personnes modèrent, une table de
journal deviendra nécessaire, et elle est plus facile à ajouter avant que la
base ne contienne des milliers de lignes.

---

## 7. Vie privée

La partie communauté collecte bien plus que le site officiel, qui ne fait que
republier des données déjà publiques. Ici l'on collecte une adresse de courriel,
un mot de passe, éventuellement un nom réel, une tranche de poids, une tranche
de taille et un lien vers une vidéo où la personne se voit.

Ce qui en découle :

- **Les tranches sont un choix de conception, pas un détail d'affichage.**
  Demander « entre 80 et 90 kg » plutôt qu'un poids exact réduit la donnée
  collectée au strict nécessaire, ce que le RGPD appelle la minimisation.
- **Le lien vidéo est une donnée personnelle** au même titre qu'un nom : on y
  voit et on y entend la personne. Il ne doit jamais apparaître pour une
  performance non publiée autrement qu'à son auteur et aux modérateurs.
- **L'effacement doit fonctionner réellement.** `DELETE /api/profil` supprime le
  compte, et la cascade en base emporte les performances. À vérifier par un
  essai, pas sur parole.
- **`/confidentialite/` devra être complétée** avant toute ouverture : finalité,
  base légale, durée de conservation, et le fait que la modération implique de
  visionner la vidéo. La page traite aujourd'hui uniquement des données
  publiques d'OpenPowerlifting et de la mesure d'audience.
- **Les courriels de vérification et de réinitialisation** partent d'une adresse
  du domaine. La délivrabilité depuis un mutualisé est un sujet en soi : à
  tester tôt, un lien de réinitialisation qui atterrit en indésirable bloque
  purement et simplement les inscriptions.

---

## 8. Les tests destinés à échouer

`parcours.md` posait la méthode : « Vérifier qu'une sécurité bloque vaut mieux
que vérifier que le site marche. » Voici la liste à passer avant toute
ouverture au public. Chacun de ces gestes **doit** être refusé.

| Tentative | Attendu |
|---|---|
| Publier sa propre performance en envoyant `etat=publiee` | refus |
| Modifier la performance d'un autre membre | refus |
| Supprimer la performance d'un autre membre | refus |
| Lire la file de modération avec un compte simple | refus |
| Se promouvoir modérateur par `PATCH /api/profil` | refus |
| Soumettre au nom d'un autre en envoyant `membre_id` | refus, l'identité vient de la session |
| Soumettre sans lien vidéo | refus |
| Soumettre sans avoir vérifié son adresse | refus |
| Lire une performance en attente qui n'est pas la sienne | refus |
| Écrire n'importe quoi sans jeton anti-CSRF | refus |
| Enchaîner cinquante tentatives de connexion | freinage |
| Injecter du SQL dans un champ de recherche | aucun effet |
| Réutiliser un lien de réinitialisation déjà employé | refus |
| Employer un lien de réinitialisation expiré | refus |
| Demander `config.php` directement en HTTP | refus |
| Effacer son compte puis chercher ses performances | plus aucune trace |

---

## 9. Ce qui dépend de l'auteur

Rien ne peut avancer côté code sans ces informations.

1. **Créer DEUX bases MySQL** dans l'espace client OVH, une de test et une de
   production, et transmettre pour chacune serveur, nom, identifiant et mot de
   passe. À ne jamais écrire dans le dépôt. L'offre Pro en fournit dix.
2. **Relever deux versions**, en SSH ou dans phpMyAdmin : celle de **MySQL ou
   MariaDB**, et celle de **PHP**. Elles décident de ce qui est utilisable,
   notamment si les contraintes `CHECK` du schéma sont réellement appliquées ou
   silencieusement ignorées. Les commandes sont en section 11.
3. **Lancer `db/verifier.php`** sur le serveur et rapporter son bilan. C'est la
   seule façon de savoir si la fondation tient : rien de ce code n'a jamais été
   exécuté, aucun PHP n'étant installé sur la machine de développement.
4. **Créer un projet Google Cloud** et un identifiant OAuth, pour la connexion
   Google. Peut attendre : la connexion par courriel suffit à tout construire.
5. **Trancher la question du sondage.** Les deux documents de reprise signalent
   que ce chantier repose sur une hypothèse jamais vérifiée, à savoir que des
   athlètes veuillent déclarer leurs performances de salle. Quelques questions
   posées à des powerlifters de l'île coûtent une soirée ; le chantier coûte des
   semaines.

---

## 10. Ce qui reste à écrire

Dans cet ordre, chaque étape étant vérifiable avant la suivante.

1. **La configuration et la connexion à la base**, avec le fichier de config hors
   racine web, les ajouts au `.htaccess`, et **l'imposition du `sql_mode` à
   chaque connexion** : le serveur d'OVH n'est pas en mode strict, voir la
   section 11.
2. **`autorisation.php`**, avant tout point d'entrée. Écrire les règles d'abord
   évite de les recoller après coup, ce qui est exactement ainsi qu'on oublie un
   cas.
3. **L'inscription, la vérification d'adresse et la connexion** par courriel.
4. **La soumission et la lecture** des performances.
5. **La modération.**
6. **Les pages Astro et les îlots Svelte** : formulaire, espace personnel,
   classement, file de modération.
7. **La connexion Google.**
8. **La liste des tests de la section 8**, passée en entier.

Le classement communauté n'apparaît dans la navigation qu'une fois l'étape 8
franchie. D'ici là, `/communaute/` reste la page d'attente actuelle.

---

## 11. Éprouver le schéma sur le serveur OVH

À faire une fois, en SSH, avec les identifiants de la **base de test**. L'accès
SSH est celui que le déploiement utilise déjà, sur `ssh.clusterXXX.hosting.ovh.net`
au port 22.

**Sur le mot de passe.** La documentation d'OVH montre la commande avec
`--password=motdepasse` écrit en clair. À éviter : les arguments d'un processus
sont lisibles sur la machine, et le mot de passe reste dans l'historique du shell.
Écrire `-p` seul fait demander le mot de passe à la saisie, sans qu'il traîne
nulle part.

### Relever les versions

```
php -v
mysql --host=SERVEUR --user=UTILISATEUR -p -e "SELECT VERSION();"
```

Selon l'hébergement, `php` peut viser une version différente de celle du site.
Les versions installées se trouvent sous `/usr/local/`, par exemple
`/usr/local/php8.3/bin/php -v`.

### Vérifier ce dont l'API aura besoin

```
php -m
```

`pdo_mysql` doit figurer dans la liste. C'est par lui que l'API parlera à MySQL,
avec des requêtes préparées.

### Charger le schéma

Le fichier voyage par SFTP, comme le reste, puis se charge depuis le dossier où
il a été déposé.

```
mysql --host=SERVEUR --user=UTILISATEUR -p BASE_DE_TEST < schema.sql
```

Aucune sortie signifie que tout est passé. Sinon MySQL nomme la ligne et la
raison, et c'est cette réponse-là qu'il faut rapporter telle quelle.

### Ce que la mesure a donné, le 30 juillet 2026

Le schéma a été **chargé avec succès** dans la base OVH depuis phpMyAdmin. Trois
faits acquis, qui n'ont plus à être supposés.

**Le serveur est MySQL 8, pas MariaDB ni MySQL 5.7.** Le détail qui le trahit :
les entiers se relisent `bigint unsigned`, sans largeur d'affichage. Les versions
antérieures écrivaient `bigint(20) unsigned`, et MariaDB l'écrit toujours. Cette
largeur a disparu avec MySQL 8.0.19.

**Les contraintes CHECK mordent.** `SHOW CREATE TABLE performance` les restitue
intactes :

```
CONSTRAINT `ck_perf_charge` CHECK (((`charge_kg` > 0) and (`charge_kg` <= 999.99)))
```

MySQL 5.7 les aurait avalées sans un mot, et le filet aurait manqué sans que rien
ne le signale. Une charge négative est donc refusée par la base elle-même. Cela
ne change rien à la règle de fond : **PHP valide de toute façon**, la base n'étant
que le dernier recours.

**Les clés étrangères ont pris.** `fk_perf_membre` pointe vers `membre(id)`, et
MySQL ne l'aurait pas acceptée si la table `membre` manquait. La cascade
d'effacement, dont dépend le droit à l'effacement du RGPD, est donc bien en place.

### La trouvaille : le serveur n'est pas en mode strict

`SELECT VERSION(), @@sql_mode` donne :

```
8.4.10-10
NO_ENGINE_SUBSTITUTION
```

MySQL 8 active par défaut le **mode strict** et **`ONLY_FULL_GROUP_BY`**. OVH les
a retirés, et le réglage global est hors d'atteinte sur un mutualisé.

**Sans mode strict, MySQL ne refuse pas une valeur invalide : il la rabote et
émet un avertissement que personne ne lit.** La documentation de MySQL est
explicite, une valeur hors bornes est ramenée à la borne la plus proche puis
enregistrée. Trois dégâts concrets pour ce projet :

| Saisie | Sans mode strict | Avec mode strict |
|---|---|---|
| Une charge de 1500 kg, faute de frappe | enregistrée à **999,99 kg** | refusée |
| Un pseudo de 60 caractères | tronqué à 40 | refusé |
| Une date `0000-00-00` | acceptée | refusée |

Le premier cas mérite d'être regardé de près, car il **défait la contrainte
`CHECK`** dont on se félicitait deux paragraphes plus haut. `charge_kg` étant un
`DECIMAL(5,2)`, 1500 est d'abord rabaissé à 999,99 ; le `CHECK` évalue ensuite
cette valeur rabotée, la trouve conforme, et laisse passer. Un record du monde
entre au classement sans qu'aucune alarme ne sonne. Le filet posé en base ne
protège que si la valeur lui arrive intacte.

Et l'absence d'`ONLY_FULL_GROUP_BY` est un piège d'un autre genre : une requête
de classement demandant le meilleur soulevé d'un membre peut renvoyer **la date
d'une autre de ses performances**, sans erreur ni avertissement. C'est mot pour
mot le défaut qui avait faussé la date du score Dots côté officiel
([parcours.md](parcours.md), « le meilleur total et le meilleur Dots sont deux
choses distinctes »). Le mode strict aurait fait refuser la requête ; sans lui,
MySQL répond faux avec aplomb.

**La règle qui en découle, sans exception.** Chaque connexion PHP impose son
`sql_mode` pour la durée de sa session, dès l'ouverture :

```
STRICT_ALL_TABLES, ONLY_FULL_GROUP_BY, NO_ZERO_IN_DATE, NO_ZERO_DATE,
ERROR_FOR_DIVISION_BY_ZERO, NO_ENGINE_SUBSTITUTION
```

C'est la première chose que fera le code de connexion, avant toute requête. La
valeur est inscrite dans [db/config.exemple.php](../db/config.exemple.php) avec
la consigne de ne pas l'alléger.

`STRICT_ALL_TABLES` plutôt que `STRICT_TRANS_TABLES` : toutes les tables du
schéma étant en InnoDB, donc transactionnelles, les deux se comportent pareil
ici, et la première ne laisse aucune échappatoire si une table non
transactionnelle apparaissait un jour.

Pour refaire cette vérification après une modification du schéma, la requête
suffit, en SQL comme dans l'onglet du même nom de phpMyAdmin :

```
SHOW CREATE TABLE performance;
```

### Le script de vérification

[db/verifier.php](../db/verifier.php) éprouve toute la fondation en une commande,
sur le serveur. Il vérifie la présence de `pdo_mysql`, la connexion, la prise en
compte effective du `sql_mode`, l'existence des quatre tables, puis il **tente
sept écritures dont six doivent être refusées** : charge de 1500 kg, charge
négative, tranche trop longue, date nulle, mouvement hors de l'ENUM, membre
inexistant, et enfin une performance valide qui, elle, doit passer.

Ce dernier contrôle n'est pas décoratif : sans lui, un schéma qui refuserait
absolument tout paraîtrait irréprochable.

Deux propriétés à connaître. Le script **refuse de s'exécuter par HTTP** : servi
par le web, il répondrait 404, ce qui double la protection du dépôt hors racine
web. Et toutes ses écritures vivent dans une **transaction systématiquement
annulée**, y compris en cas d'erreur : la base ressort telle qu'elle est entrée.

Marche à suivre :

1. Copier `db/config.exemple.php` en `config.php`, y mettre les vraies valeurs.
2. Déposer `config.php` et `verifier.php` dans un même dossier, hors racine web.
3. En SSH, depuis ce dossier : `php verifier.php`
4. Supprimer `verifier.php`, qui n'a plus d'usage.

Il n'est pas versionné dans `dist/` et ne part donc jamais au déploiement.

**Résultat de la première exécution, le 30 juillet 2026 : 19 réussites, 0 échec.**
PHP 8.4.22 en ligne de commande, MySQL 8.4.10-10 sur l'hôte `loremisclaude.mysql.db`.
Les six écritures qui devaient être refusées l'ont été, avec les codes attendus :

| Code SQLSTATE | Essai | Ce qu'il prouve |
|---|---|---|
| `22003` | charge de 1500 kg | Le mode strict refuse au lieu de raboter à 999,99 |
| `HY000` | charge de −50 kg | La contrainte `CHECK` du schéma s'applique |
| `22001` | tranche trop longue | Plus de troncature silencieuse |
| `22007` | date `0000-00-00` | `NO_ZERO_DATE` est actif |
| `01000` | mouvement hors ENUM | La valeur n'est pas remplacée par une chaîne vide |
| `23000` | membre inexistant | Les clés étrangères tiennent |

L'effacement en cascade a été vérifié dans la foulée : les performances d'un
membre supprimé disparaissent avec lui.

### Les règles d'autorisation, vérifiées le 30 juillet 2026

[db/verifier-autorisation.php](../db/verifier-autorisation.php) éprouve les
décisions pures d'`autorisation.php` sur une table de vérité, sans base de
données ni session. **39 réussites, 0 échec.** Six des tentatives listées en
section 8 sont donc déjà couvertes, avant qu'un seul point d'entrée existe :
publier sa propre performance, modifier celle d'un autre, lire la file de
modération sans y avoir droit, se promouvoir modérateur, soumettre au nom d'un
tiers, lire une performance en attente qui n'est pas la sienne.

### L'emplacement du fichier de secrets

Le compte n'est **pas cloisonné** : l'invite SSH montre `/home/loremis/sbd-re-prive`,
donc le dossier de connexion est `/home/loremis`, et la racine du site est
`/home/loremis/sbd-re`. Le fichier de secrets vit à côté du site, jamais dedans.

Deux conséquences heureuses : il est **hors de portée du web**, aucune adresse ne
peut l'atteindre ; et il est **hors du miroir du déploiement**, qui ne vise que
`./sbd-re`, donc la purge `--delete` ne le touchera jamais. Aucune exclusion à
ajouter au workflow.

Le nom exact compte : `sbd-re-prive`, avec le tiret. `configuration.php` le
cherche à cet endroit précis, et un tiret de travers produirait une panne
visible en production seulement.

### Repartir de zéro

Le schéma ne modifie pas une table existante, il la crée si elle manque. Pour
recharger une version corrigée, supprimer d'abord, dans cet ordre, les tables
qui pointent vers les autres :

```
mysql --host=SERVEUR --user=UTILISATEUR -p BASE_DE_TEST -e "DROP TABLE IF EXISTS tentative_connexion, jeton, performance, membre;"
```

À ne jamais lancer sur la base de production.

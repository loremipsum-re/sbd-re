# Le forum, le fil et les notifications

Plan de travail préparé le 7 novembre 2026, à la demande de l'auteur. Les
étapes 1 à 5 de la section 8 sont construites le même jour, en essai : voir
« Construit le 7 novembre », à la fin de cette section. Il s'appuie sur
[comptes.md](comptes.md), dont il reprend les comptes, les profils, la
modération et les smileys d'époque.

---

## 1. Ce que l'auteur a demandé

- **Un vrai forum, « comme on le faisait dans l'ancien temps »** : des
  catégories, des forums, des sujets, des réponses, à la manière de phpBB.
- **Un premier forum dédié à l'haltéro**, puis **des forums publics créés
  par les membres** sur des sujets précis.
- **Un système de notifications.**
- **Un fil d'actualité** sur le forum.
- **Le chat reste**, il sert à voir qui est en ligne. Les membres connectés
  lisent l'historique, les visiteurs non connectés ne voient que les
  nouveaux messages qui arrivent.
- **Des avatars personnalisés en 300 × 300**, fournis par chaque membre,
  plus tard.

---

## 2. Les catégories

Trois niveaux, comme phpBB : une **catégorie** regroupe des **forums**, un
forum contient des **sujets**, un sujet des **messages**.

**Une catégorie par exercice, une générale, une pour la compétition, une
hors sujet**, choix de l'auteur du 7 novembre 2026. « Haltéro » veut dire
haltérophilie : l'arraché et l'épaulé-jeté sont deux exercices, donc deux
catégories. La dernière accueille les forums que les membres créent.

| Catégorie | Forums |
|---|---|
| **Squat** | Technique · Programmation |
| **Développé couché** | Technique · Programmation |
| **Soulevé de terre** | Technique · Programmation |
| **Arraché** | Technique · Programmation |
| **Épaulé-jeté** | Technique · Programmation |
| **Général** | Présentations · Équipement · Santé et récupération · Salles de l'île (relié à `/salles/`) · Le site SBD.re |
| **Compétitions** | Calendrier et inscriptions · Résultats · Règlement et arbitrage |
| **Hors sujet** | Discussions libres |
| **Forums des membres** | Créés par les membres, section 6 |

Quelques choix derrière ce tableau :

- **Peu de forums au départ.** Un forum vide donne un forum mort : mieux
  vaut en ouvrir un de plus quand un sujet déborde. Un forum se crée en une
  ligne en base, rien n'est figé.
- **« Santé et récupération »** porte un avertissement en tête : rien n'y
  remplace un avis médical.
- **Le dopage n'a pas de forum à lui, mais il se discute** partout où il a sa
  place, sous avertissement automatique : voir la section 5 bis.

---

## 3. Le modèle de données, en esquisse

Six tables de plus, sur le modèle de [comptes.md](comptes.md), section 3.

- **`forum_categorie`** : titre, ordre.
- **`forum`** : catégorie, adresse, titre, description, créateur (vide pour un
  forum de l'équipe), état (ouvert, verrouillé, archivé), ordre.
- **`sujet`** : forum, auteur, titre, épinglé, verrouillé, date du dernier
  message, nombre de réponses. Ces deux derniers champs sont stockés, par
  exception : la liste d'un forum les lit à chaque visite, et les recompter
  coûterait une requête par sujet.
- **`message_forum`** : sujet, auteur, pseudo copié (comme le chat, pour un
  compte effacé), texte, dates de création et de modification, masqué par,
  et `sensible`, posé quand un mot de la liste du dopage y figure (section
  5 bis). La table `message` du chat reçoit la même colonne.
- **`abonnement`** : un membre suit un sujet. On suit d'office les sujets
  qu'on ouvre, et on s'en désabonne d'un bouton (choix de l'auteur du
  7 novembre). Les autres se suivent par un bouton « Suivre ce sujet ».
- **`notification`** : membre, nature, lien, lue ou non, date.

La présence en ligne n'a pas besoin de table : une colonne
`derniere_activite` sur `membre`, mise à jour par le chat.

Le fil, lui non plus : c'est une requête sur les sujets et les messages
récents, réunis et triés par date.

---

## 4. Les notifications

**Sur le site d'abord.** Une cloche dans l'en-tête, avec le nombre de
notifications non lues, et une page `/compte/notifications/`. Quatre
natures pour commencer :

- une réponse dans un sujet suivi ;
- une mention `@pseudo` dans un message ;
- le badge communauté ou admin posé sur une de ses performances ;
- un message masqué ou une performance retirée par la modération.

**Un récapitulatif par courriel, toutes les deux semaines, pour qui le
demande**, choix de l'auteur du 7 novembre 2026. Jamais un courriel par
réponse, qui ferait exploser le quota d'envoi de l'hébergement. Le
récapitulatif reprend les notifications non lues depuis le précédent, et ne
part pas s'il n'y a rien. Quatre points :

- **Coupé par défaut.** Le membre l'active d'un bouton dans `/compte/`, et le
  coupe par un lien dans chaque courriel ou par le même bouton. Deux
  colonnes de plus sur `membre` : `recapitulatif` (0 ou 1) et la date du
  dernier envoi.
- **Les sujets qu'on ouvre notifient d'office**, et chacun se coupe d'un
  bouton « Ne plus suivre » (section 3, `abonnement`).
- **Il demande une tâche planifiée**, la première du projet : l'onglet
  « Tâches planifiées (Cron) » de l'hébergement OVH lance un script PHP
  chaque semaine, qui n'écrit qu'aux membres dont le dernier récapitulatif
  date de quatorze jours ou plus. Rien ne peut l'attendre d'une visite.
- **Les adresses iCloud n'en reçoivent pas**, comme les autres courriels du
  site (comptes.md, section 6.1).

**Un point technique à connaître.** Le cookie de session est `HttpOnly` :
le JavaScript de la page ne peut pas savoir si le visiteur est connecté, et
interrogerait le serveur à chaque page pour tout le monde. La connexion
posera donc un second cookie, lisible et sans secret, qui dit seulement
« connecté ». La cloche n'interroge le serveur qu'en sa présence, une fois
par minute.

---

## 5. Le chat, revu

Ce qui change par rapport à [comptes.md](comptes.md), section 6.4 :

- **Qui est en ligne.** La liste des membres actifs dans les deux dernières
  minutes, d'après `derniere_activite`, **visible de tous**, visiteurs
  compris (choix de l'auteur du 7 novembre). Les pseudos sont déjà publics
  par les profils.
- **Les visiteurs voient les nouveaux messages arriver**, sans historique.
  Leur première lecture ne rend que le numéro du dernier message ; les
  suivantes, ce qui est arrivé depuis. Le serveur refuse tout message de
  plus de cinq minutes à un visiteur : demander « tout depuis le numéro 0 »
  ne rend pas l'historique.
- **Les membres connectés et confirmés** lisent les sept derniers jours et
  écrivent, comme prévu.
- **L'interrogation des visiteurs est plus espacée**, 15 secondes au lieu
  de 5 : un mutualisé tient mal des dizaines de visiteurs qui interrogent
  toutes les cinq secondes.

Le chat devient donc **lisible par tout le monde**. **Son ton reste un peu
plus libre que celui des profils, sans plus**, réponse de l'auteur du
7 novembre. Concrètement, la charte telle qu'elle est : une grossièreté
passe, jamais dirigée contre une personne ; le contenu sexuel, la haine, le
harcèlement et les informations personnelles d'autrui sont refusés. Le
filtre de mots du chat reste donc plus large que celui des profils, mais
garde les insultes visant quelqu'un.

---

## 5 bis. Le dopage : on en parle, jamais on n'y pousse

Décision de l'auteur du 7 novembre 2026. Le sujet n'est pas interdit : un
athlète doit pouvoir poser une question, raconter un contrôle, discuter
d'une affaire. Mais un message qui l'aborde porte un **avertissement
visible**, et la charte interdit d'y **inciter** qui que ce soit.

**Le repérage est automatique.** `moderation.php` tient deux listes : les
mots qui **bloquent** un message (contenu sexuel, haine), et les mots
**sensibles**, qui le laissent passer en lui posant `sensible = 1`. La liste
du dopage vise des noms précis de produits et de pratiques, jamais des mots
courants du métier : « stéroïde », « anabolisant », « testostérone »,
« trenbolone », « nandrolone », « oxandrolone », « dianabol », « stanozolol »,
« winstrol », « clenbutérol », « EPO », « SARM », « hormone de croissance »,
« dopage ». « Cycle » ou « cure » en sont exclus, qui servent autant à la
programmation qu'au dopage. La liste se complète à l'usage.

**À l'écriture**, l'auteur voit avant d'envoyer : « Votre message aborde le
dopage. Il paraîtra avec un avertissement. N'incitez jamais personne. »

**À la lecture**, un bandeau précède le message, dans le forum comme dans le
chat :

> Ce message aborde le dopage. Les produits dopants mettent en danger la
> santé de qui les prend, et leur usage hors prescription est interdit en
> compétition. Rien ici n'est un conseil.

**Ce qui reste interdit**, écrit dans la charte et tenu par la modération :
pousser quelqu'un à se doper, donner des doses ou des protocoles, indiquer
où s'en procurer, vendre ou acheter. Un tel message se signale et l'admin le
masque. Le bandeau ne remplace pas la modération : il prévient le lecteur,
il n'excuse pas l'auteur.

---

## 6. Les forums des membres

Un membre crée un forum public sur un sujet précis : une salle, un
programme, une compétition à préparer. Ce qu'il faut fixer :

- **Qui peut en créer** : membre confirmé depuis 30 jours, comme pour voter,
  et un forum par mois au plus.
- **Publication immédiate**, choix de l'auteur du 7 novembre : aucun forum
  n'attend de validation.
- **Un espace de modération prêt pour autant**, demande du même jour : la
  page d'admin liste les forums des membres, les plus récents d'abord, avec
  leurs signalements, et permet de renommer, verrouiller, archiver ou
  masquer un forum en un clic. Le nom d'un forum passe le filtre strict des
  profils, puisqu'il s'affiche à tous.
- **Le créateur** peut épingler et verrouiller dans son forum ; masquer un
  message et bannir restent à l'admin.
- **Ménage** : un forum sans message depuis six mois passe en archive,
  lisible, fermé à l'écriture.

---

## 6 bis. La mise en forme des messages

**Les balises d'époque**, choix de l'auteur du 7 novembre : `[b]gras[/b]`,
`[i]italique[/i]`, `[quote=pseudo]…[/quote]` pour citer, `[url]…[/url]` pour
un lien, plus les smileys d'époque de comptes.md, section 6.4. **Les emojis
du téléphone passent aussi**, la base est en `utf8mb4`.

La base garde le texte tel que tapé, balises comprises. Le rendu se fait à
l'affichage, en PHP, à partir du texte **déjà échappé** : une balise inconnue
reste du texte, et aucun HTML écrit par un membre ne peut s'exécuter. Un
lien `[url]` n'accepte que `http` et `https`, s'ouvre dans un nouvel onglet,
s'annonce comme les autres liens sortants, et porte `rel="ugc nofollow"`,
qui dit à Google qu'un membre l'a écrit.

---

## 6 ter. Le fil d'actualité

L'auteur n'a pas tranché. **Proposition par défaut**, à revoir à l'usage :
les nouveaux sujets, les réponses, et les performances qui reçoivent un
badge, mêlés par date. Les nouveaux membres n'y entrent pas : un pseudo qui
arrive n'apprend rien au lecteur, et ouvrirait la porte aux comptes créés
pour se montrer.

---

## 7. Les avatars, plus tard

Une image carrée de 300 × 300, envoyée par le membre. Le serveur la
réencode en WebP avec GD, ce qui efface les métadonnées (la position GPS
d'une photo de téléphone, par exemple), et refuse tout autre format. Profil
tout public : un avatar se signale comme le reste. C'est la première fois
que le site recevrait une image d'un visiteur, d'où un chantier à part.

---

## 8. Ordre de construction proposé

Après les phases 5 (performances) et 6 (vérification) de comptes.md, qui
donnent au profil son contenu :

1. [x] **Schéma** du forum, comme la phase 1 des comptes.
2. [x] **Lecture** : catégories, forums, sujets paginés, rendus par PHP depuis
   des gabarits d'Astro, comme les profils, et indexés par Google.
3. [x] **Écriture** : ouvrir un sujet, répondre, citer, smileys d'époque.
4. [x] **Notifications** : la cloche, la page, les abonnements.
5. [x] **Chat revu** : en ligne, lecture des visiteurs. Remplace la phase 7 des
   comptes.
6. **Le fil.**
7. **Forums des membres.**
8. **Modération** : signalements et page d'admin, partagés avec les
   comptes, phase 8.
9. **Avatars**, en dernier.

**Cadence demandée par l'auteur le 7 novembre** : cinq étapes d'un trait,
puis une seule série de tests en production.

### Construit le 7 novembre, étapes 1 à 5

- **Schéma** : `db/migration-2026-11-07-forum.sql` pour la base en place,
  recopié à la fin de `schema.sql`. Six tables, trois colonnes sur `membre`
  (présence, récapitulatif), `sensible` sur `message`, et les 19 forums de
  départ de la section 2.
- **Lecture** : `/forum/`, `/forum/<adresse>/`, `/forum/<adresse>/<sujet>/`,
  paginés par `?page=N`, rendus par `api/forum-page.php` depuis
  `/forum/-gabarit/`. **En `noindex` jusqu'à l'ouverture** (`FORUM_OUVERT`
  de `lib/forum.php`) : un sujet d'essai n'entre pas dans Google. Un sujet
  porte un JSON-LD `DiscussionForumPosting`.
- **Écriture** : `api/forum.php`, actions `sujet`, `reponse`, `suivre`.
  Balises `[b]`, `[i]`, `[quote=pseudo]`, `[url]` rendues sur le texte
  échappé ; smileys d'époque, seize SVG contrôlés un à un dans
  `public/smileys/`. Cinq sujets par jour, un message toutes les quinze
  secondes, 5 000 caractères.
- **Dopage** : `lib/moderation.php`, deux listes. Un mot sensible renvoie
  d'abord un 409 avec l'avertissement, le second envoi confirme : l'auteur
  est prévenu **avant** la publication sans que la liste quitte le serveur.
- **Notifications** : `api/notifications.php`, `/compte/notifications/`, la
  cloche de l'en-tête et le cookie lisible `sbdre-connecte`. Réponse dans
  un sujet suivi, mention `@pseudo`, badge admin ou communauté.
- **Chat** : `api/chat.php` et `/communaute/chat/`, page `prive` jusqu'à
  l'ouverture, où il rejoindra `/communaute/`.
- **Tests** : `php db/verifier-autorisation.php` (195 règles),
  `node scripts/verifier-smileys.mjs` (7 cas), et la section 6 de
  `php db/verifier.php`, qui rejoue forum, chat et notifications sur MySQL.

**Écarts annoncés** :

- **La cloche est une icône avec un nombre**, sans le mot : « Notifications »
  ne tient pas à 375 px à côté du logo et du menu. Le mot est dit aux
  lecteurs d'écran. C'est la seule icône du site sans libellé visible.
- **Laissés pour plus tard** : le récapitulatif par courriel (ses colonnes
  existent, il attend sa tâche planifiée), la modification d'un message, et
  la notification de modération (phase 8).

---

## 9. Questions

**Tranchées le 7 novembre 2026**, et reportées plus haut :

- **« Haltéro » = haltérophilie.** Section 2.
- **Le chat garde un peu plus de liberté que les profils, sans plus.**
  Section 5.
- **Le dopage se discute, sous avertissement automatique, sans jamais
  inciter.** Section 5 bis. La charte `/communaute/charte/` recevra la règle
  avec la phase d'écriture du forum, quand le sujet pourra apparaître.
- **Une catégorie par exercice, une générale, une compétition, une hors
  sujet.** Section 2.
- **Balises d'époque et emojis du téléphone.** Section 6 bis.
- **Forums des membres publiés aussitôt, avec un espace de modération
  prêt.** Section 6.
- **Notifications sur le site, et un récapitulatif par courriel toutes les
  deux semaines, coupé par défaut et activé par le membre.** Les sujets
  qu'on ouvre notifient d'office, et se coupent. Section 4.
- **Les personnes en ligne, visibles de tous.** Section 5.
- **Le fil** : l'auteur ne sait pas encore. Proposition par défaut en
  section 6 ter.

Aucune question ne reste ouverte. Le fil se revoit à l'usage.

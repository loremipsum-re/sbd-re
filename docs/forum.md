# Le forum, le fil et les notifications

Plan de travail préparé le 7 novembre 2026, à la demande de l'auteur. Rien
n'est construit : ce document propose les catégories et l'organisation, et
liste les questions à trancher avant d'écrire une ligne. Il s'appuie sur
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

## 2. Les catégories proposées

Trois niveaux, comme phpBB : une **catégorie** regroupe des **forums**, un
forum contient des **sujets**, un sujet des **messages**. Les forums de
l'équipe sont fixes ; ceux des membres vivent dans leur propre catégorie.

| Catégorie | Forums |
|---|---|
| **Force athlétique** | Squat · Développé couché · Soulevé de terre · Programmation et entraînement · Compétitions (calendrier, pesée, arbitrage, résultats) · Équipement (raw, wraps, équipé, ceinture, genouillères, chaussures) |
| **Haltérophilie** | Arraché · Épaulé-jeté · Technique et mobilité · Compétitions d'haltéro |
| **La Réunion** | Salles de l'île (relié à `/salles/`) · Partenaires d'entraînement et séances communes · Matériel d'occasion |
| **Santé et récupération** | Nutrition · Blessures et récupération |
| **La communauté** | Présentations des nouveaux membres · Forums des membres · Le site SBD.re (idées, défauts, annonces) |

Quelques choix derrière ce tableau :

- **Les trois mouvements ont chacun leur forum**, comme les salons du chat :
  c'est le vocabulaire du site, et la question la plus fréquente porte sur
  un mouvement précis.
- **« Matériel d'occasion »** et non « petites annonces » : un espace
  d'échange de barres et de disques, pas une place de marché. Les
  compléments alimentaires et tout produit à avaler en sont exclus.
- **« Blessures et récupération »** porte un avertissement en tête : rien n'y
  remplace un avis médical.
- **« Haltéro » veut dire haltérophilie**, réponse de l'auteur du 7 novembre :
  la catégorie reste telle que le tableau la décrit.
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
- **`abonnement`** : un membre suit un sujet. On suit d'office un sujet qu'on
  ouvre ou auquel on répond.
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

**Le courriel attend.** Un courriel par réponse ferait vite exploser le
quota d'envoi de l'hébergement, et iCloud refuse aujourd'hui nos courriels
(comptes.md, section 6.1). Un récapitulatif quotidien, facultatif, viendra
s'il est demandé.

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
  minutes, d'après `derniere_activite`.
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
- **Validation** : publication immédiate, ou après un regard de l'admin
  (question 4).
- **Modération** : le créateur peut épingler et verrouiller dans son forum ;
  masquer et bannir restent à l'admin.
- **Ménage** : un forum sans message depuis six mois passe en archive,
  lisible, fermé à l'écriture.

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

1. **Schéma** du forum, comme la phase 1 des comptes.
2. **Lecture** : catégories, forums, sujets paginés, rendus par PHP depuis
   des gabarits d'Astro, comme les profils, et indexés par Google.
3. **Écriture** : ouvrir un sujet, répondre, citer, smileys d'époque.
4. **Notifications** : la cloche, la page, les abonnements.
5. **Chat revu** : en ligne, lecture des visiteurs. Remplace la phase 7 des
   comptes.
6. **Le fil.**
7. **Forums des membres.**
8. **Modération** : signalements et page d'admin, partagés avec les
   comptes, phase 8.
9. **Avatars**, en dernier.

---

## 9. Questions

**Tranchées le 7 novembre 2026**, et reportées plus haut :

- **« Haltéro » = haltérophilie.** Section 2.
- **Le chat garde un peu plus de liberté que les profils, sans plus.**
  Section 5.
- **Le dopage se discute, sous avertissement automatique, sans jamais
  inciter.** Section 5 bis. La charte `/communaute/charte/` recevra la règle
  avec la phase d'écriture du forum, quand le sujet pourra apparaître.

**Encore ouvertes :**

1. **Les catégories** de la section 2 : à garder, renommer, compléter ?
2. **La mise en forme des messages** : texte et smileys seulement, ou les
   balises d'époque `[b]`, `[i]`, `[quote]`, `[url]` ?
3. **Un forum de membre** paraît-il tout de suite, ou après validation de
   l'admin ?
4. **Les notifications** : sur le site seulement, ou aussi un courriel
   récapitulatif quotidien, facultatif ?
5. **La liste des personnes en ligne** : visible de tous, ou des membres
   seulement ?
6. **Le fil** : nouveaux sujets et réponses seulement, ou aussi les
   performances vérifiées et les nouveaux membres ?

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
- **Le dopage n'a pas de forum**, et la charte en interdit la promotion comme
  la vente (question 8).

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
  compte effacé), texte, dates de création et de modification, masqué par.
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

Le chat devient donc **lisible par tout le monde**. Le ton « plus libre »
prévu pour des membres entre eux est à revoir (question 6).

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

## 9. Questions à trancher

1. **« Haltéro »** veut-il dire l'haltérophilie (arraché, épaulé-jeté), comme
   dans le tableau, ou la musculation en général ?
2. **Les catégories** de la section 2 : à garder, renommer, compléter ?
3. **La mise en forme des messages** : texte et smileys seulement, ou les
   balises d'époque `[b]`, `[i]`, `[quote]`, `[url]` ?
4. **Un forum de membre** paraît-il tout de suite, ou après validation de
   l'admin ?
5. **Les notifications** : sur le site seulement, ou aussi un courriel
   récapitulatif quotidien, facultatif ?
6. **Le chat lisible par tous** : garde-t-il un ton plus libre que les
   profils ? Et la liste des personnes en ligne est-elle visible de tous, ou
   des membres seulement ?
7. **Le fil** : nouveaux sujets et réponses seulement, ou aussi les
   performances vérifiées et les nouveaux membres ?
8. **Le dopage** : la charte interdit-elle d'en parler, ou seulement d'en
   faire la promotion et le commerce ?

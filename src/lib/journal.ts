/**
 * Le journal des mises à jour du site, affiché sur /mises-a-jour/.
 *
 * POURQUOI CE FICHIER EXISTE. Le site publie les noms et les performances de
 * centaines d'athlètes. Qui veut savoir ce qui a changé, et quand, doit pouvoir
 * le lire sans fouiller un dépôt de code. C'est l'objet de la page.
 *
 * IL S'ÉDITE À LA MAIN, et c'est volontaire. L'historique des commits dit ce
 * qu'un développeur a fait ; cette page dit ce qu'un visiteur a vu changer. Les
 * deux ne se recouvrent pas : vingt commits peuvent ne rien changer à l'écran,
 * et une ligne de CSS peut tout changer.
 *
 * COMMENT AJOUTER UNE ENTRÉE. Une ligne en tête du tableau, la plus récente en
 * premier, avec sa date au format AAAA-MM-JJ. Écrire pour un lecteur qui ne
 * connaît ni le code ni le vocabulaire du métier : ce qu'il peut faire
 * maintenant, ou ce qui s'affiche autrement.
 *
 * LES DONNÉES NE FIGURENT PAS ICI. Elles se régénèrent toute seules le 1er de
 * chaque mois, et la page affiche leur étendue réelle, calculée au moment de la
 * génération. Un journal tenu à la main sur ce point mentirait un jour ou
 * l'autre.
 */

/**
 * La nature d'un changement, qui décide de la pastille affichée.
 *
 * `fonction` : quelque chose de nouveau à faire sur le site.
 * `visuel`   : la même information, présentée autrement.
 * `correction` : un défaut réparé.
 */
export type NatureEntree = 'fonction' | 'visuel' | 'correction';

export interface EntreeJournal {
  /** Date de mise en ligne, au format AAAA-MM-JJ. */
  date: string;
  nature: NatureEntree;
  /** Numéro de version affiché au pied de page, quand il a bougé ce jour-là. */
  version?: string;
  titre: string;
  /** Deux ou trois phrases, en français courant, sans vocabulaire technique. */
  texte: string;
}

export const LIBELLES_NATURE: Record<NatureEntree, string> = {
  fonction: 'Nouveauté',
  visuel: 'Présentation',
  correction: 'Correction',
};

export const JOURNAL: readonly EntreeJournal[] = [
  {
    date: '2026-11-10',
    nature: 'correction',
    version: '1.19.5',
    titre: 'Sur un petit téléphone, les boutons du forum gardent leur ligne',
    texte:
      "À 320 px de large, « J'aime · 4 » se pliait sur deux lignes dans son bouton. Les boutons d'un message passent désormais à la ligne, et le nombre de « J'aime » se lit au-dessus d'eux sur téléphone. Avec un texte espacé par le lecteur, le forum et l'espace membre tiennent dans l'écran.",
  },
  {
    date: '2026-11-10',
    nature: 'correction',
    version: '1.19.4',
    titre: 'Le site relu de bout en bout : lisibilité, lecture, alignements',
    texte:
      "Cinquante-deux pages mesurées, à six largeurs d'écran et dans les deux thèmes. Les titres démarrent à la même hauteur partout, les pages de texte se calent à gauche comme les autres, et les barres d'une fiche d'athlète s'alignent sous leur intitulé. Sur un téléphone de 360 px, le bouton du menu retrouve sa marge. Chaque tableau porte un nom pour les lecteurs d'écran, et un nombre reste sur la ligne de son unité.",
  },
  {
    date: '2026-11-10',
    nature: 'correction',
    titre: "L'accueil et la page À propos présentent la communauté ouverte",
    texte:
      "Les deux pages annonçaient encore un classement de la communauté « bientôt disponible ». Elles décrivent maintenant ce qui existe : le forum, le chat, et les barres de salle vérifiées sur vidéo.",
  },
  {
    date: '2026-11-10',
    nature: 'correction',
    version: '1.19.3',
    titre: 'Le rang des classements retrouve son dessin',
    texte:
      "Depuis la mise à jour du 9 novembre, un contour jaune entourait le numéro de rang dans les classements, et le numéro des étapes de quatre infographies. Il venait du badge des modérateurs, qui portait le même nom dans la feuille de style. Le badge a changé de nom, et les rangs retrouvent leur dessin.",
  },
  {
    date: '2026-11-10',
    nature: 'correction',
    version: '1.19.2',
    titre: 'Des noms plus faciles à toucher',
    texte:
      "Sur téléphone, les noms d'athlètes et de compétitions, les catégories et les records des listes offrent une cible de 24 px au moins, contre 17 à 23 auparavant. Les liens de retour en tête de page, comme « Toutes les compétitions », en offrent 44.",
  },
  {
    date: '2026-11-09',
    nature: 'correction',
    version: '1.19.1',
    titre: 'L\'accessibilité de la communauté, remesurée',
    texte:
      "Les pages de la communauté ont été passées au crible : contrastes, noms des commandes, clavier, taille des cibles, affichage à 320 px. Le contour des champs de saisie se voit mieux, l'en-tête tient sur les plus petits écrans, et la fenêtre de confirmation s'ouvre sur « Annuler ». Le détail est sur la page Accessibilité.",
  },
  {
    date: '2026-11-09',
    nature: 'fonction',
    version: '1.19.0',
    titre: 'Le top des membres sur la page Communauté',
    texte:
      "La page Communauté montre les dernières actualités, les trois barres vérifiées les plus lourdes de chaque mouvement et les dernières barres publiées. Une barre se publie en touchant des boutons. L'admin et les modérateurs portent un badge au forum et au chat, et « Connexion » se trouve dans l'en-tête.",
  },
  {
    date: '2026-11-09',
    nature: 'fonction',
    version: '1.18.0',
    titre: 'L\'espace membre en tableau de bord',
    texte:
      "Votre espace montre d'un coup d'œil vos barres publiées, vérifiées et en attente, et vos notifications non lues, avec un raccourci vers chaque geste. Les réglages ont leur sommaire. Dans les notifications, une barre vérifiée se distingue en vert, avec sa coche.",
  },
  {
    date: '2026-11-09',
    nature: 'fonction',
    version: '1.17.0',
    titre: 'L\'annuaire des membres, et « J\'aime » au forum',
    texte:
      "L'annuaire liste chaque membre de la communauté avec sa salle et ses barres publiées et vérifiées. Une recherche y trouve un pseudo. Au forum, un « J'aime » salue un message. Taper « @ » propose les pseudos, au forum comme au chat. Le chat se resserre sur deux salons, Général et Général 2.",
  },
  {
    date: '2026-11-09',
    nature: 'visuel',
    version: '1.16.1',
    titre: 'Le profil d\'un coup d\'œil',
    texte:
      "Sur ordinateur, le profil d'un membre se lit sur deux colonnes : sa carte à gauche, ses records à droite, sans défiler. Les records tiennent en tuiles, une par mouvement et le total, et les informations du membre portent chacune une icône.",
  },
  {
    date: '2026-11-09',
    nature: 'fonction',
    version: '1.16.0',
    titre: 'Relier son profil à sa fiche d\'athlète',
    texte:
      "Un membre qui a concouru à La Réunion réclame sa fiche d'athlète, en bas de la fiche. Une fois la demande validée par l'admin, son profil mène à ses résultats officiels, et la fiche à son profil dans la communauté.",
  },
  {
    date: '2026-11-09',
    nature: 'fonction',
    version: '1.15.1',
    titre: 'Trois articles de plus dans les actualités',
    texte:
      "Le premier explique SBD, les trois mouvements de la force athlétique. Le deuxième donne le calendrier 2027 de l'île, mois par mois, avec la dernière édition de chaque compétition. Le troisième présente le forum, le chat et la vérification des barres de salle.",
  },
  {
    date: '2026-11-09',
    nature: 'fonction',
    version: '1.15.0',
    titre: 'Votre compte dans l\'en-tête',
    texte:
      "Connecté, votre avatar ouvre en haut de page l'accès à votre espace, votre profil, vos réglages et votre avatar. Le bouton « Chat » s'orne d'un point quand d'autres ont écrit, « Proposer » paraît sur chaque page, et une carte annonce les nouveautés à chaque version.",
  },
  {
    date: '2026-11-09',
    nature: 'fonction',
    version: '1.14.0',
    titre: 'Le forum au quotidien',
    texte:
      "Le forum garde vos brouillons sur l'appareil, publie à Ctrl + Entrée et cite le passage sélectionné. Les listes marquent les sujets nouveaux depuis votre visite et mènent à la première réponse non lue, et les pages se numérotent. Une mention dans le chat prévient le membre cité.",
  },
  {
    date: '2026-11-09',
    nature: 'fonction',
    version: '1.13.0',
    titre: 'Le chat sur toutes les pages',
    texte:
      "Le chat s'ouvre désormais du bouton « Chat », en bas à droite de chaque page, et reste ouvert d'une page à l'autre. L'accueil du forum commence par les sujets récents, et le fil d'Ariane des sujets est aligné.",
  },
  {
    date: '2026-11-09',
    nature: 'fonction',
    version: '1.12.0',
    titre: 'Le confort du forum',
    texte:
      "Le forum gagne une recherche dans les titres, un aperçu avant publication et des boutons de mise en forme. « Dernier message » mène au message lui-même, l'accueil dit qui est en ligne, et l'espace membre liste les sujets suivis. Les profils montrent les sujets ouverts au forum.",
  },
  {
    date: '2026-11-08',
    nature: 'fonction',
    version: '1.11.0',
    titre: 'Modifier un message, et le récapitulatif par courriel',
    texte:
      "Un message du forum se corrige dans les 24 heures, et le dit. Le créateur d'un forum le renomme, sans en changer l'adresse. L'espace membre liste ses sujets, et les réglages proposent un récapitulatif des notifications par courriel, toutes les deux semaines, coupé tant qu'on ne le demande pas.",
  },
  {
    date: '2026-11-07',
    nature: 'fonction',
    version: '1.10.0',
    titre: 'La communauté ouvre',
    texte:
      "La page Communauté devient l'entrée : le forum, le fil de l'activité, les barres à vérifier et le chat. Le forum entre au menu et dans les moteurs de recherche, et la page de confidentialité dit ce que deviennent les données d'un compte.",
  },
  {
    date: '2026-11-07',
    nature: 'fonction',
    version: '1.9.17',
    titre: 'Le fil, les forums des membres et les avatars, en essai',
    texte:
      "Le forum se lit comme un phpBB, une catégorie par barre et les colonnes nommées. Les membres en essai y créent leurs forums, suivent le fil de l'activité, signalent ce qui enfreint la charte et choisissent un avatar.",
  },
  {
    date: '2026-11-07',
    nature: 'fonction',
    version: '1.9.16',
    titre: 'Le forum, le chat et les notifications, en essai',
    texte:
      "Les membres en essai ouvrent des sujets dans un forum à l'ancienne, une catégorie par mouvement, avec citations et smileys d'époque. Le chat s'ouvre en cinq salons, et une cloche prévient des réponses, des mentions et des badges.",
  },
  {
    date: '2026-11-07',
    nature: 'fonction',
    version: '1.9.15',
    titre: 'La vérification des performances, en essai',
    texte:
      "Les membres en essai jugent les barres des autres sur leur vidéo. Une barre reçoit le badge « Vérifiée par la communauté » à 20 avis, dont 60 % favorables, et le vote s'ouvre 30 jours après l'inscription.",
  },
  {
    date: '2026-11-07',
    nature: 'fonction',
    version: '1.9.14',
    titre: 'Un espace membre centré sur les performances',
    texte:
      "L'espace d'un membre en essai s'ouvre sur ses performances et lui dit où ses records le placeraient au classement officiel. Les réglages passent derrière un engrenage, et une performance vérifiée s'affiche en vert, une non vérifiée en ambre.",
  },
  {
    date: '2026-11-07',
    nature: 'fonction',
    version: '1.9.13',
    titre: 'Les performances des membres, en essai',
    texte:
      "Un membre en essai publie ses barres avec leur vidéo. Son profil affiche ses records par équipement, son total quand il a les trois mouvements, et toutes ses performances.",
  },
  {
    date: '2026-11-07',
    nature: 'visuel',
    version: '1.9.12',
    titre: 'Pseudo et Instagram verrouillés par défaut',
    texte:
      "Le pseudo et le nom Instagram s'affichent grisés. Une case les débloque, et une fenêtre demande confirmation au moment d'enregistrer un nouveau nom.",
  },
  {
    date: '2026-11-07',
    nature: 'correction',
    version: '1.9.11',
    titre: 'Un changement de pseudo se confirme',
    texte:
      "Changer de pseudo ou de nom Instagram demande de cocher une case de confirmation, puisque le nouveau nom reste en place un an ou six mois.",
  },
  {
    date: '2026-11-07',
    nature: 'fonction',
    version: '1.9.10',
    titre: 'Un pseudo modifiable une fois par an',
    texte:
      "Un membre peut changer de pseudo une fois par an. L'ancienne adresse de son profil mène vers la nouvelle, et personne d'autre ne peut reprendre l'ancien pseudo.",
  },
  {
    date: '2026-11-07',
    nature: 'fonction',
    version: '1.9.9',
    titre: 'Les profils de membres, en essai',
    texte:
      "Chaque membre en essai a son profil public, avec sa présentation, sa salle et, s'il le souhaite, son compte Instagram. Ce nom se change une fois tous les six mois.",
  },
  {
    date: '2026-11-07',
    nature: 'correction',
    version: '1.9.8',
    titre: 'Les adresses iCloud, en attente',
    texte:
      "Les adresses iCloud ne reçoivent pas encore les courriels de sbd.re. La création d'un compte le signale et propose d'utiliser une autre adresse.",
  },
  {
    date: '2026-11-07',
    nature: 'correction',
    version: '1.9.7',
    titre: 'Des courriels signés au nom de sbd.re',
    texte:
      "Les messages du site partent signés au nom de sbd.re. Les boîtes exigeantes, comme celles d'iCloud, les refusaient jusqu'ici.",
  },
  {
    date: '2026-11-06',
    nature: 'correction',
    version: '1.9.5',
    titre: "Un compte membre s'active par courriel",
    texte:
      "Un compte en essai devient utilisable au clic sur le lien reçu par courriel. Sans ce clic, il s'efface au bout de 24 heures. Les formulaires de compte repèrent aussi les envois automatiques.",
  },
  {
    date: '2026-11-06',
    nature: 'fonction',
    version: '1.9.4',
    titre: 'Les comptes membres, en essai',
    texte:
      "Les pages pour créer un compte, se connecter et retrouver son mot de passe sont en ligne, en essai et hors du menu. Elles ouvriront avec la communauté.",
  },
  {
    date: '2026-11-06',
    nature: 'correction',
    version: '1.9.3',
    titre: 'Un seul chemin pour les courriels du site',
    texte:
      "Les formulaires de partenariat et de suggestion envoient leurs messages par un même chemin, celui qui servira aussi aux futurs comptes. Une bibliothèque devenue inutile quitte le serveur.",
  },
  {
    date: '2026-11-05',
    nature: 'correction',
    version: '1.9.2',
    titre: 'Une mise en ligne qui vérifie le serveur',
    texte:
      "Le site se dépose uniquement sur le serveur d'OVH reconnu par sa clé de sécurité. Une autre clé arrête la mise en ligne avant tout envoi.",
  },
  {
    date: '2026-11-05',
    nature: 'fonction',
    version: '1.9.1',
    titre: 'Deux articles de plus dans les actualités',
    texte:
      "Le premier présente les formats de compétition de l'île, du full power au développé couché seul, et ce qu'ils changent pour le total et les records. Le second explique comment faire corriger un résultat faux ou manquant.",
  },
  {
    date: '2026-11-05',
    nature: 'visuel',
    version: '1.9.1',
    titre: 'Des pages plus légères',
    texte:
      "La page des résultats hors de l'île pèse 26 % de moins, le classement 20 % de moins. Le tracé du badge officiel s'écrit une fois par page, et le script du menu part allégé.",
  },
  {
    date: '2026-11-05',
    nature: 'correction',
    version: '1.9.1',
    titre: 'Tri et suggestions fiabilisés',
    texte:
      "Un tri croissant range en fin de liste les athlètes sans valeur dans la colonne triée. Les suggestions reçues restent conservées sur le serveur après chaque mise en ligne du site.",
  },
  {
    date: '2026-10-28',
    nature: 'visuel',
    version: '1.9',
    titre: 'Un menu plus court, rangé en trois familles',
    texte:
      "Sur grand écran, la barre garde le classement, les records et les catégories. Le menu range toutes les rubriques en trois familles : classements, outils et le site. Sur téléphone, il tient sur un écran sans défiler, et le pied de page suit le même plan.",
  },
  {
    date: '2026-10-28',
    nature: 'fonction',
    version: '1.9',
    titre: 'Le classement s’ouvre sur la liste',
    texte:
      "Une phrase d'introduction, puis la liste. Les filtres et le tri passent derrière un bouton qui dit combien de filtres sont actifs, et un tri choisi s'affiche sous le compteur. Toutes les listes partagent la même barre, et toucher un champ ne fait plus zoomer l'iPhone.",
  },
  {
    date: '2026-10-28',
    nature: 'fonction',
    version: '1.9',
    titre: 'Les termes techniques s’expliquent en un geste',
    texte:
      "Dots, total, raw, full power : la première apparition de chaque terme est soulignée en pointillé, dans les pages comme dans les articles. Sa définition s'affiche au survol, au clavier ou au toucher.",
  },
  {
    date: '2026-10-28',
    nature: 'fonction',
    version: '1.9',
    titre: 'Les records racontés dans le temps',
    texte:
      "Chaque catégorie montre la courbe de son record au total, marche par marche. L'accueil présente les records battus dans l'année, la page des records l'année de chacun, et chaque compétition les records battus ce jour-là. Sur l'accueil, le top 10 met en avant les points Dots qui le classent.",
  },
  {
    date: '2026-10-28',
    nature: 'fonction',
    version: '1.9',
    titre: 'Me situer montre où tombe votre total',
    texte:
      "La page dessine les totaux de tous les athlètes de votre catégorie et de votre équipement, avec le vôtre et le record. Elle donne aussi les kilos qui vous séparent du record. Le calcul reste dans votre navigateur.",
  },
  {
    date: '2026-10-28',
    nature: 'visuel',
    version: '1.9',
    titre: 'Des textes plus courts et plus nets',
    texte:
      "Une douzaine de pages sont réécrites, une information par phrase. La ponctuation ne tombe plus seule en début de ligne, les fiches n'affichent plus de zéro pour un mouvement absent, et le premier du mois s'écrit « 1er ».",
  },
  {
    date: '2026-10-28',
    nature: 'visuel',
    version: '1.9',
    titre: 'Les catégories les plus fournies ressortent',
    texte:
      "Chaque pastille de la page des catégories donne son nombre d'athlètes, et les trois catégories les plus fournies de chaque sexe passent en jaune.",
  },
  {
    date: '2026-10-28',
    nature: 'visuel',
    version: '1.9',
    titre: 'Une carte de l’île plus précise',
    texte:
      "La carte des salles suit maintenant le tracé de l'IGN : une côte deux fois plus détaillée et les limites des 24 communes. Les boutons de zoom passent sous la carte sur téléphone, et l'image des communes organisatrices les situe sur la même carte.",
  },
  {
    date: '2026-10-28',
    nature: 'visuel',
    version: '1.9',
    titre: 'La page Communauté dit où en est le classement',
    texte:
      "La page dit d'emblée que ce classement est en préparation. Trois boutons mènent au classement officiel, à la page Me situer et à la boîte à suggestions, et le fonctionnement prévu tient en six lignes.",
  },
  {
    date: '2026-10-28',
    nature: 'fonction',
    version: '1.9',
    titre: 'Trois formats pour les partenaires',
    texte:
      "La page Partenariats décrit trois formats fixes et les pages où ils peuvent paraître, toujours après le contenu. Tant qu'aucun partenaire n'est actif, rien ne s'affiche.",
  },
  {
    date: '2026-10-28',
    nature: 'correction',
    version: '1.9',
    titre: 'Les résultats de compétition retrouvent leurs intitulés',
    texte:
      "Les charges des résultats de compétition portent de nouveau les mots squat, développé couché et soulevé de terre. Plusieurs éléments choisis, comme les bascules des records, se distinguent mieux en thème clair.",
  },
  {
    date: '2026-10-25',
    nature: 'fonction',
    version: '1.8',
    titre: 'Une infographie sur chaque page qui demande une explication',
    texte:
      "Chaque compétition, chaque fiche d'athlète et chaque catégorie a désormais son image, comme les records, les résultats hors de l'île, les formats de compétition et les pages qui expliquent le site. Sur les pages de données, l'image se déplie depuis une pastille et la liste reste en tête. Les quinze articles ont chacun la leur, et leurs chiffres se mettent à jour avec les données.",
  },
  {
    date: '2026-10-25',
    nature: 'correction',
    version: '1.8',
    titre: 'Quatre articles alignés sur les données',
    texte:
      "L'article sur les titres internationaux compte trois titres mondiaux, dont celui d'Alain Cuvelier en octobre 2025. Le compte rendu du Championnat Régional précise que trois records de catégorie datent de ce jour-là. La page À propos calcule ses chiffres au lieu de les écrire.",
  },
  {
    date: '2026-10-25',
    nature: 'fonction',
    version: '1.7',
    titre: 'Cinq infographies pour comprendre la discipline',
    texte:
      "Les points Dots, le total en neuf essais, l'équipement, les catégories de poids et les divisions d'âge ont chacun leur image. Elles ouvrent les articles qui en parlent, et se déplient depuis le classement, les records et le simulateur. Leurs chiffres se mettent à jour avec les données chaque mois.",
  },
  {
    date: '2026-10-25',
    nature: 'visuel',
    version: '1.7',
    titre: 'Les liens vers d’autres sites ouvrent un nouvel onglet',
    texte:
      "La page de sbd.re reste ouverte derrière. La flèche ↗ signale ces liens à l'œil, et les lecteurs d'écran annoncent « Nouvel onglet, quitte sbd.re ».",
  },
  {
    date: '2026-10-25',
    nature: 'correction',
    version: '1.6.2',
    titre: 'Des espacements rétablis',
    texte:
      "Plusieurs blocs se touchaient : les champs du formulaire de suggestion, les groupes hommes et femmes de la page des catégories, et les encadrés de la page d'accessibilité, dont le texte collait au bord. Chacun retrouve l'espace prévu, le même que sur le reste du site.",
  },
  {
    date: '2026-10-24',
    nature: 'correction',
    version: '1.6.1',
    titre: 'Le choix de mesure d’audience, à jour partout',
    texte:
      "Sur la page Confidentialité, la ligne qui rappelle votre choix de mesure d'audience se met à jour dès que vous répondez dans la bannière. Un choix fait sur la page ferme la bannière à son tour : un refus arrête donc la mesure sur-le-champ, quel que soit le bouton utilisé.",
  },
  {
    date: '2026-10-24',
    nature: 'fonction',
    version: '1.6',
    titre: 'Trouver sa catégorie en un geste',
    texte:
      "La page des catégories s'ouvre sur dix-sept pastilles, hommes puis femmes, chacune menant à sa page d'un seul geste. Sur les records, le sexe et l'équipement se choisissent par des boutons qui montrent leurs trois options, au lieu de menus déroulants à ouvrir. Une femme qui cherche son record en 63 kg y arrive en deux gestes, contre quatre à six auparavant.",
  },
  {
    date: '2026-10-24',
    nature: 'fonction',
    version: '1.5.2',
    titre: 'Six articles de plus dans les actualités',
    texte:
      "Le Championnat Régional du 26 septembre a son compte rendu. Quatre articles de fond ouvrent les données autrement : les titres internationaux des Réunionnais, les divisions d'âge, ce qu'on soulève à sa première compétition, et les communes qui organisent. Un dernier compare la force athlétique au Grand Raid, deux sports de la même île.",
  },
  {
    date: '2026-10-14',
    nature: 'fonction',
    version: '1.5',
    titre: 'Proposer une amélioration, depuis n’importe quelle page',
    texte:
      "Un bouton discret suit la lecture en bas de l’écran et ouvre une petite fenêtre pour signaler une donnée fausse, une page qui résiste ou une idée. La page d’où part la demande est reprise toute seule. Le message suffit : l’adresse électronique reste facultative, et laissée vide, la suggestion est anonyme. Une page entière, « Proposer une amélioration », propose le même formulaire.",
  },
  {
    date: '2026-10-14',
    nature: 'fonction',
    titre: 'Deux pages qui disent ce que vaut le site',
    texte:
      "Cette page de mises à jour ouvre le journal des changements visibles. Une page Accessibilité rend compte d’un audit mené par la mesure sur huit pages : ce qui a été vérifié, ce qui a été corrigé au passage, et ce qui n’a pas été testé.",
  },
  {
    date: '2026-10-14',
    nature: 'visuel',
    titre: 'Catégories et records plus faciles à fouiller',
    texte:
      "La page des catégories se cherche et se filtre par sexe, et sa chronologie ne garde que les chiffres qui disent quelque chose. Les records se filtrent par sexe, équipement et catégorie, et chaque catégorie mène à sa page. Le thème sombre devient celui par défaut, sa bascule passe dans le menu, et le pied de page sépare ce qu’on vient chercher de ce qui parle du site.",
  },
  {
    date: '2026-09-22',
    nature: 'visuel',
    version: '1.4',
    titre: "La fiche d'athlète est refaite",
    texte:
      "La courbe de progression se lit enfin sur un téléphone, et chaque point s'ouvre d'une touche pour donner le détail de la compétition. L'historique range ses chiffres en colonnes au lieu de les empiler. Une flèche verte ou rouge indique ce qui a été gagné ou perdu depuis la compétition précédente, et les trois mouvements ont désormais leur icône.",
  },
  {
    date: '2026-09-21',
    nature: 'fonction',
    version: '1.3',
    titre: 'Savoir où l’on se situerait',
    texte:
      "Une page « Me situer » accepte un sexe, un équipement, un poids de corps et trois barres, puis annonce le rang que ce total obtiendrait au classement, avec les trois athlètes au-dessus et les trois en dessous. Le classement officiel montre en parallèle les places gagnées ou perdues sur douze mois.",
  },
  {
    date: '2026-09-20',
    nature: 'visuel',
    titre: 'Des listes qui se lisent sur un téléphone',
    texte:
      "Le classement, les compétitions et les records partagent une présentation commune : des cartes aérées sur petit écran, un tableau sur grand écran, une seule barre de recherche et de filtres. Un glossaire repliable explique au passage les termes du powerlifting.",
  },
  {
    date: '2026-09-18',
    nature: 'correction',
    version: '1.1',
    titre: 'Formulaire de contact fiabilisé',
    texte:
      "Le formulaire de la page Partenariats refuse maintenant les envois en rafale et ne laisse plus passer de message vide. Le numéro de version apparaît au pied de page, pour savoir d'un coup d'œil ce qui est en ligne.",
  },
  {
    date: '2026-09-10',
    nature: 'fonction',
    titre: 'Thème sombre, carte des salles et page Partenariats',
    texte:
      "Le site passe en thème sombre par défaut, avec un bouton pour revenir au clair. Une carte situe les salles de l'île commune par commune, et une page Partenariats ouvre un formulaire de contact direct.",
  },
];

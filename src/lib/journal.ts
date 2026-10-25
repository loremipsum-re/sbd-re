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
    date: '2026-10-24',
    nature: 'fonction',
    version: '1.8',
    titre: 'Une infographie sur chaque page qui demande une explication',
    texte:
      "Chaque compétition, chaque fiche d'athlète et chaque catégorie a désormais son image, comme les records, les résultats hors de l'île, les formats de compétition et les pages qui expliquent le site. Sur les pages de données, l'image se déplie depuis une pastille et la liste reste en tête. Les quinze articles ont chacun la leur, et leurs chiffres se mettent à jour avec les données.",
  },
  {
    date: '2026-10-24',
    nature: 'correction',
    version: '1.8',
    titre: 'Quatre articles alignés sur les données',
    texte:
      "L'article sur les titres internationaux compte trois titres mondiaux, dont celui d'Alain Cuvelier en octobre 2025. Le compte rendu du Championnat Régional précise que trois records de catégorie datent de ce jour-là. La page À propos calcule ses chiffres au lieu de les écrire.",
  },
  {
    date: '2026-10-24',
    nature: 'fonction',
    version: '1.7',
    titre: 'Cinq infographies pour comprendre la discipline',
    texte:
      "Les points Dots, le total en neuf essais, l'équipement, les catégories de poids et les divisions d'âge ont chacun leur image. Elles ouvrent les articles qui en parlent, et se déplient depuis le classement, les records et le simulateur. Leurs chiffres se mettent à jour avec les données chaque mois.",
  },
  {
    date: '2026-10-24',
    nature: 'visuel',
    version: '1.7',
    titre: 'Les liens vers d’autres sites ouvrent un nouvel onglet',
    texte:
      "La page de sbd.re reste ouverte derrière. La flèche ↗ signale ces liens à l'œil, et les lecteurs d'écran annoncent « Nouvel onglet, quitte sbd.re ».",
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

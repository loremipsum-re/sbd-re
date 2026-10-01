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

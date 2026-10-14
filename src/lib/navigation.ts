/**
 * Les rubriques du site, déclarées une seule fois.
 *
 * L'en-tête et le pied de page listaient chacun les mêmes huit entrées, à la
 * main, avec leurs adresses. Deux listes qui disent la même chose finissent
 * toujours par se contredire : une rubrique renommée d'un côté, une adresse
 * corrigée de l'autre. L'arrivée des icônes rendait le risque concret, en
 * ajoutant une troisième information à tenir synchronisée.
 *
 * Ce fichier est désormais le seul endroit où l'on ajoute ou retire une
 * rubrique du site.
 */

/**
 * Noms d'icônes disponibles. Le type vit ICI et non dans `Icone.astro` : un
 * fichier `.ts` ne peut pas importer proprement un type déclaré dans un
 * composant Astro, alors que l'inverse fonctionne. Le composant vient donc
 * chercher ce type, et non le contraire.
 */
export type NomIcone =
  | 'classement'
  | 'communaute'
  | 'comparer'
  | 'simulateur'
  | 'records'
  | 'categories'
  | 'competitions'
  | 'exterieur'
  | 'actualites'
  | 'partenariats'
  | 'salles'
  | 'suggestions'
  | 'journal'
  | 'apropos';

export interface Rubrique {
  href: string;
  /** Libellé court, pour la barre de navigation et le menu. */
  label: string;
  /**
   * Libellé développé, employé au pied de page seulement, où la colonne offre
   * la place d'être explicite. Absent quand le libellé court suffit.
   */
  labelLong?: string;
  /**
   * Une phrase qui dit ce qu'on trouve dans la rubrique, affichée dans le
   * grand menu. Tirée de la description de la page elle-même, pour que les
   * deux ne divergent pas.
   */
  resume: string;
  icone: NomIcone;
  /**
   * Les deux familles du pied de page.
   *
   * `classements` : ce qui montre des athlètes, des marques et des
   * compétitions, c'est-à-dire la donnée elle-même.
   * `projet` : ce qui parle du site, de ses sources et de ses coulisses.
   *
   * Un pied de page qui alignait quatorze entrées sous un seul titre « Le
   * site » ne disait rien de leur nature : deux colonnes distinguent ce qu'on
   * vient chercher de ce qu'on consulte une fois.
   */
  famille: 'classements' | 'projet';
  /**
   * Les rubriques principales occupent la barre de navigation sur grand écran.
   * Les autres vivent dans le menu du téléphone et au pied de page.
   *
   * Trois entrées, pas huit : une navigation qui montre tout ne met rien en
   * avant, et huit rubriques alignées débordaient de l'écran sur téléphone.
   */
  principale?: true;
}

export const RUBRIQUES: readonly Rubrique[] = [
  {
    href: '/classement/',
    label: 'Classement officiel',
    resume: 'Le meilleur total de chaque athlète, en compétition',
    icone: 'classement',
    famille: 'classements',
    principale: true,
  },
  {
    href: '/communaute/',
    label: 'Communauté',
    resume: 'Le classement ouvert aux performances de salle',
    icone: 'communaute',
    famille: 'classements',
    principale: true,
  },
  {
    href: '/comparer/',
    label: 'Comparer',
    labelLong: 'Comparer des athlètes',
    resume: "Jusqu'à quatre athlètes, barre par barre",
    icone: 'comparer',
    famille: 'classements',
    principale: true,
  },
  {
    href: '/simulateur/',
    label: 'Me situer',
    labelLong: 'Me situer dans le classement',
    resume: 'Où se placerait votre total dans le classement',
    icone: 'simulateur',
    famille: 'classements',
  },
  {
    href: '/records/',
    label: 'Records',
    resume: "Les meilleures marques de l'île, par mouvement",
    icone: 'records',
    famille: 'classements',
  },
  {
    href: '/categories/',
    label: 'Catégories',
    resume: 'Les catégories de poids, leurs records et leur histoire',
    icone: 'categories',
    famille: 'classements',
  },
  {
    href: '/competitions/',
    label: 'Compétitions',
    resume: 'Toutes les compétitions organisées depuis 2017',
    icone: 'competitions',
    famille: 'classements',
  },
  {
    href: '/exterieur/',
    label: 'Extérieur',
    resume: 'Les résultats obtenus hors de La Réunion',
    icone: 'exterieur',
    famille: 'classements',
  },
  {
    href: '/actualites/',
    label: 'Actualités',
    resume: 'Les compétitions racontées, résultats et records',
    icone: 'actualites',
    famille: 'projet',
  },
  {
    href: '/salles/',
    label: 'Salles',
    resume: "Les salles de l'île sur une carte, par commune",
    icone: 'salles',
    famille: 'projet',
  },
  {
    href: '/partenariats/',
    label: 'Partenariats',
    resume: 'Proposer un partenariat au site',
    icone: 'partenariats',
    famille: 'projet',
  },
  {
    href: '/suggestions/',
    label: 'Proposer',
    labelLong: 'Proposer une amélioration',
    resume: 'Signaler une erreur ou souffler une idée, sans compte',
    icone: 'suggestions',
    famille: 'projet',
  },
  {
    href: '/mises-a-jour/',
    label: 'Mises à jour',
    resume: 'Ce qui change sur le site, et quand',
    icone: 'journal',
    famille: 'projet',
  },
  {
    href: '/a-propos/',
    label: 'À propos',
    resume: 'Sources, méthode et fonctionnement du site',
    icone: 'apropos',
    famille: 'projet',
  },
];

export const rubriquesPrincipales = RUBRIQUES.filter((r) => r.principale);
export const rubriquesClassements = RUBRIQUES.filter((r) => r.famille === 'classements');
export const rubriquesProjet = RUBRIQUES.filter((r) => r.famille === 'projet');
export const rubriquesSecondaires = RUBRIQUES.filter((r) => !r.principale);

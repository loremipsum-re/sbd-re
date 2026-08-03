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
  | 'records'
  | 'categories'
  | 'competitions'
  | 'exterieur'
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
  icone: NomIcone;
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
    icone: 'classement',
    principale: true,
  },
  { href: '/communaute/', label: 'Communauté', icone: 'communaute', principale: true },
  {
    href: '/comparer/',
    label: 'Comparer',
    labelLong: 'Comparer des athlètes',
    icone: 'comparer',
    principale: true,
  },
  { href: '/records/', label: 'Records', icone: 'records' },
  { href: '/categories/', label: 'Catégories', icone: 'categories' },
  { href: '/competitions/', label: 'Compétitions', icone: 'competitions' },
  { href: '/exterieur/', label: 'Extérieur', icone: 'exterieur' },
  { href: '/a-propos/', label: 'À propos', icone: 'apropos' },
];

export const rubriquesPrincipales = RUBRIQUES.filter((r) => r.principale);
export const rubriquesSecondaires = RUBRIQUES.filter((r) => !r.principale);

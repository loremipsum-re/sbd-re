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
  | 'apropos'
  /* Deux icônes d'état, pour les infographies : un essai validé, un essai
     manqué. Toujours accompagnées du mot. */
  | 'valide'
  | 'manque';

/**
 * LES TROIS FAMILLES DU SITE, dans l'ordre où le menu et le pied de page les
 * montrent. Structure choisie par l'auteur le 27 octobre 2026, qui voulait un
 * menu plus court et plus clair.
 *
 * `classements` : ce qui montre la donnée, athlètes, marques et compétitions.
 * `outils` : ce qui calcule à partir d'elle, pour un total ou des athlètes
 * que le visiteur choisit.
 * `site` : ce qui parle du site, de ses sources et de ses coulisses.
 *
 * Le titre de chaque famille remplace la phrase de résumé que portait chaque
 * rubrique du menu. Un intitulé court se comprend dès qu'on sait dans quelle
 * famille il range sa page : « Comparer », sous « Outils », n'a pas besoin
 * d'une phrase de plus.
 *
 * Le menu et le pied de page lisent cette même liste. Un titre changé ici
 * change aux deux endroits.
 *
 * LES IDENTIFIANTS NOMMENT AUSSI DES ZONES DE GRILLE, dans BaseLayout : au
 * téléphone, Classements et Outils s'empilent à gauche et Le site occupe la
 * droite. Une famille ajoutée ou renommée demande d'y écrire sa zone.
 */
export const FAMILLES = [
  { id: 'classements', titre: 'Classements' },
  { id: 'outils', titre: 'Outils' },
  { id: 'site', titre: 'Le site' },
] as const;

export type Famille = (typeof FAMILLES)[number]['id'];

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
  famille: Famille;
  /**
   * Les trois rubriques de la barre d'en-tête, sur grand écran : Classement,
   * Records, Catégories. Une navigation qui montre tout ne met rien en avant.
   *
   * Sur téléphone, la barre ne garde que le logo et le bouton Menu. Mesuré à
   * 375 px, les trois libellés seuls font 233 px et le logo 112 px, pour
   * 343 px de largeur utile : la place manque avant même d'y loger le bouton
   * et des cibles de 44 px. Avec eux, la barre demanderait 646 px. Les trois
   * rubriques restent en tête du menu, famille Classements.
   */
  barre?: true;
  /**
   * Hors du menu, au pied de page seulement. Le menu montre ce qu'on vient
   * chercher. Les pages qu'on consulte une fois restent à un geste, en bas de
   * chaque page.
   */
  piedSeulement?: true;
  /**
   * Un mot posé à côté du libellé, en texte : « Bientôt » pour une rubrique
   * qui n'est pas encore ouverte. Une couleur seule ne dirait rien à qui ne
   * la distingue pas, ni à un lecteur d'écran.
   */
  etiquette?: string;
}

export const RUBRIQUES: readonly Rubrique[] = [
  {
    href: '/classement/',
    label: 'Classement',
    /* « officiel » le distingue, au pied de page, du classement de la
       communauté. Dans la barre, le mot seul suffit. */
    labelLong: 'Classement officiel',
    icone: 'classement',
    famille: 'classements',
    barre: true,
  },
  {
    href: '/records/',
    label: 'Records',
    icone: 'records',
    famille: 'classements',
    barre: true,
  },
  {
    href: '/categories/',
    label: 'Catégories',
    icone: 'categories',
    famille: 'classements',
    barre: true,
  },
  {
    href: '/competitions/',
    label: 'Compétitions',
    icone: 'competitions',
    famille: 'classements',
  },
  {
    href: '/exterieur/',
    /* Libellé choisi par l'auteur le 27 octobre 2026. L'infographie des
       données le reprend : elle suit tout changement de nom. */
    label: "Hors de l'île",
    icone: 'exterieur',
    famille: 'classements',
  },
  {
    href: '/simulateur/',
    label: 'Me situer',
    labelLong: 'Me situer dans le classement',
    icone: 'simulateur',
    famille: 'outils',
  },
  {
    href: '/comparer/',
    label: 'Comparer',
    labelLong: 'Comparer des athlètes',
    icone: 'comparer',
    famille: 'outils',
  },
  {
    href: '/actualites/',
    label: 'Actualités',
    icone: 'actualites',
    famille: 'site',
  },
  {
    href: '/salles/',
    label: 'Salles',
    icone: 'salles',
    famille: 'site',
  },
  {
    href: '/partenariats/',
    label: 'Partenariats',
    icone: 'partenariats',
    famille: 'site',
  },
  {
    href: '/suggestions/',
    label: 'Proposer une idée',
    labelLong: 'Proposer une amélioration',
    icone: 'suggestions',
    famille: 'site',
  },
  {
    href: '/mises-a-jour/',
    label: 'Mises à jour',
    icone: 'journal',
    famille: 'site',
    piedSeulement: true,
  },
  {
    href: '/a-propos/',
    label: 'À propos',
    icone: 'apropos',
    famille: 'site',
  },
  {
    href: '/communaute/',
    label: 'Communauté',
    icone: 'communaute',
    famille: 'site',
    etiquette: 'Bientôt',
  },
];

/** Les trois rubriques de la barre d'en-tête. */
export const rubriquesBarre = RUBRIQUES.filter((r) => r.barre);

/** Les familles et leurs rubriques, telles que le menu les montre. */
export const groupesMenu = FAMILLES.map((f) => ({
  ...f,
  rubriques: RUBRIQUES.filter((r) => r.famille === f.id && !r.piedSeulement),
}));

/** Les mêmes familles au pied de page, rubriques hors menu comprises. */
export const groupesPied = FAMILLES.map((f) => ({
  ...f,
  rubriques: RUBRIQUES.filter((r) => r.famille === f.id),
}));

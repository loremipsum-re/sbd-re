/**
 * Catalogue des infographies, demande de l'auteur du 24 octobre 2026.
 *
 * Une infographie explique en une image ce qu'un article ou une page suppose
 * connu : le calcul des points Dots, le total, l'équipement, les catégories,
 * les divisions d'âge.
 *
 * LES CHIFFRES SONT CALCULÉS, JAMAIS RECOPIÉS. Chaque composant de
 * src/components/infographies/ lit les données à la génération, comme le
 * reste du site. Une infographie dessinée une fois avec les effectifs du jour
 * serait fausse dès la mise à jour mensuelle. Seul l'exemple du total en neuf
 * essais est construit, et sa légende le dit.
 *
 * CE FICHIER EST LA SOURCE UNIQUE DE LA LISTE. Le schéma des articles, le
 * menu du CMS et l'aiguillage d'InfographieArticle.astro la lisent ici :
 * ajouter une infographie, c'est ajouter une ligne, puis son composant.
 */

export interface FicheInfographie {
  /** Libellé du menu du CMS. */
  libelle: string;
  /** Article qui la développe, par son adresse dans /actualites/. */
  article: string;
}

export const INFOGRAPHIES = {
  dots: { libelle: 'Les points Dots', article: 'points-dots' },
  total: { libelle: 'Le total en neuf essais', article: 'premiere-competition' },
  equipement: { libelle: 'Raw, bandes et équipé', article: 'raw-et-equipe' },
  categories: { libelle: 'Les catégories de poids', article: 'categories-de-poids' },
  divisions: { libelle: "Les divisions d'âge", article: 'divisions-age' },
} as const satisfies Record<string, FicheInfographie>;

export type IdInfographie = keyof typeof INFOGRAPHIES;

/** Les identifiants, sous la forme qu'attend `z.enum`. */
export const IDS_INFOGRAPHIES = Object.keys(INFOGRAPHIES) as [
  IdInfographie,
  ...IdInfographie[],
];

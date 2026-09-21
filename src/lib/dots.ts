/**
 * Score Dots, calculé plutôt que lu.
 *
 * POURQUOI CE FICHIER EXISTE. Partout ailleurs sur le site, le Dots vient
 * d'OpenPowerlifting, qui le publie avec chaque résultat. Le simulateur, lui,
 * doit le calculer : la personne qui s'y compare n'a jamais concouru, sa
 * performance n'existe dans aucun jeu de données.
 *
 * LA FORMULE. Dots ramène un total au poids de corps par un polynôme du
 * quatrième degré, propre à chaque sexe :
 *
 *     Dots = total × 500 / (a·p⁴ + b·p³ + c·p² + d·p + e)
 *
 * Les coefficients sont ceux de la définition officielle, reprise par
 * OpenPowerlifting.
 *
 * VÉRIFIÉE, PAS RECOPIÉE DE CONFIANCE. Comparée aux 832 résultats du jeu de
 * données qui portent à la fois un poids de corps et un Dots publié : écart
 * moyen de 0,0025 point, maximum 0,005. C'est l'arrondi à deux décimales de
 * la valeur publiée, donc la formule est la bonne.
 */

const COEFFICIENTS = {
  M: [-0.000001093, 0.0007391293, -0.1918759221, 24.0900756, -307.75076],
  F: [-0.0000010706, 0.0005158568, -0.1126655495, 13.6175032, -57.96288],
} as const;

/**
 * Bornes de poids de corps de la définition officielle. En dehors, le
 * polynôme cesse d'être fiable : la formule fige donc la valeur aux bornes
 * plutôt que de rendre un score fantaisiste.
 */
const BORNES = {
  M: [40, 210],
  F: [40, 150],
} as const;

export function dots(totalKg: number, poidsKg: number, sexe: 'M' | 'F'): number {
  if (!(totalKg > 0) || !(poidsKg > 0)) return 0;

  const [a, b, c, d, e] = COEFFICIENTS[sexe];
  const [min, max] = BORNES[sexe];
  const p = Math.min(Math.max(poidsKg, min), max);

  const denominateur = a * p ** 4 + b * p ** 3 + c * p ** 2 + d * p + e;
  return (totalKg * 500) / denominateur;
}

/**
 * La carte de La Réunion : la projection que partagent les salles et les
 * tracés.
 *
 * Les tracés viennent de src/data/ile-reunion.json, que produit
 * scripts/generer-carte-ile.mjs à partir des contours de l'IGN. Ce fichier-ci
 * ne fait que les relire : toute règle de projection vit dans le générateur,
 * et la fonction `projeter` la recopie à l'identique.
 */
import ile from '../data/ile-reunion.json';

/**
 * La projection du générateur : équirectangulaire, bornes lues dans le
 * fichier. Les salles et les tracés suivent ainsi la même règle, au chiffre
 * près, sans quoi les points flotteraient à côté de l'île.
 */
export function projeter(lat: number, lon: number): { x: number; y: number } {
  const { x0, x1, y0, y1, kx } = ile.bornes;
  return {
    x: ((lon * kx - x0) / (x1 - x0)) * ile.largeur,
    y: ((-lat - y0) / (y1 - y0)) * ile.hauteur,
  };
}

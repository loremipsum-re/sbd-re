/**
 * Tracés des icônes des trois mouvements : squat, développé couché, soulevé
 * de terre.
 *
 * TRACÉ MAISON, ET C'EST UNE EXCEPTION ASSUMÉE. La règle du site est un seul
 * jeu d'icônes, Tabler variante `outline`, tracé dans `Icone.astro`. Tabler
 * n'a simplement pas ces trois-là. Vérifié le 22 septembre 2026 en
 * interrogeant `tabler/tabler-icons` : `barbell`, `weight`, `dumbbell`,
 * `stretching`, `yoga` et `treadmill` répondent 200 ; `squat`, `bench-press`,
 * `deadlift`, `kettlebell`, `gym`, `fitness` et `powerlifting` renvoient 404.
 *
 * C'est le raisonnement de `Coupe.astro` et de `Medaille.astro`, dessinés à la
 * main pour la même raison. La géométrie de Tabler est respectée à la lettre :
 * grille de 24, trait de 2, extrémités et jonctions rondes, aucun aplat. Les
 * icônes se posent donc à côté de celles du pack sans détonner.
 *
 * LE MATÉRIEL PLUTÔT QUE LE CORPS. Trois tours de silhouettes ont été dessinés
 * puis écartés. Une posture demande beaucoup de traits et se perd vite en
 * réduisant : à 16 px, un athlète en squat et un athlète debout sont la même
 * tache. Une cage, un banc et une barre au sol n'ont aucune posture à
 * interpréter, donc rien à perdre en petit.
 *
 * LA MÊME BARRE AUX TROIS ENDROITS, en deux traits, l'axe et les disques.
 * C'est elle qui fait la famille : un seul objet reconnaissable, trois
 * situations. Les disques débordent des montants, comme sur une vraie cage.
 *
 * SA HAUTEUR SUIT LE MOUVEMENT : en haut de la cage, au milieu au-dessus du
 * banc, au sol. Posées à la même hauteur, la cage et le banc ne se
 * distinguaient plus que par la forme du meuble, ce qui est peu à 16 px.
 *
 * LE SOULEVÉ DE TERRE N'A PAS DE LIGNE DE SOL. À côté des deux autres, qui
 * touchent le bas de leur boîte, la position basse de la barre suffit à dire
 * le sol. Sa barre est remontée de deux unités et demie après ce retrait :
 * sans sol pour la tenir, toute l'encre tombait dans le tiers inférieur et le
 * glyphe pendait sous la ligne de texte. Centres optiques mesurés dans le
 * navigateur : 12,0 pour la cage, 13,8 pour le banc, 14,0 pour la barre.
 */
import type { LiftKey } from './types';

export const TRACES_MOUVEMENTS: Partial<Record<LiftKey, readonly string[]>> = {
  // La cage : la barre en haut, deux montants, deux pieds.
  squat: ['M3 6.5h18', 'M5 4v5M19 4v5', 'M7 6.5v12.5M17 6.5v12.5', 'M5 20h4M15 20h4'],
  // Le banc : les mêmes montants, plus courts, et l'assise qui les traverse.
  bench: ['M3 10h18', 'M5 7.5v5M19 7.5v5', 'M7 10v9M17 10v9', 'M5 15h14', 'M5 20h4M15 20h4'],
  // La barre seule, posée bas.
  deadlift: ['M3 14h18', 'M5 11.5v5M19 11.5v5'],
};

/** Identifiant du symbole dans le sprite, pour `<use href>`. */
export const idSymbole = (mouvement: LiftKey) => `mvt-${mouvement}`;

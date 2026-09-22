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

/*
 * LES TRACÉS OCCUPENT LA BOÎTE DE 2 À 22, comme ceux de Tabler.
 *
 * Première version dessinée de 3 à 21 : le glyphe n'occupait que 75 % de sa
 * boîte, donc 25 % de chaque icône était du vide. À 14 px, cela revenait à
 * dessiner dans 10,5 px. Un dixième de plus en dimension se voit beaucoup à
 * ces tailles-là.
 */
export const TRACES_MOUVEMENTS: Partial<Record<LiftKey, readonly string[]>> = {
  // La cage : la barre en haut, deux montants, deux pieds.
  squat: ['M2 6h20', 'M4 3v6M20 3v6', 'M6.5 6v13.5M17.5 6v13.5', 'M4 21h5M15 21h5'],
  // Le banc : les mêmes montants, plus courts, et l'assise qui les traverse.
  bench: [
    'M2 10h20',
    'M4 7v6M20 7v6',
    'M6.5 10v10M17.5 10v10',
    'M4 15.5h16',
    'M4 21h5M15 21h5',
  ],
  // La barre seule, posée bas.
  deadlift: ['M2 14h20', 'M4 11v6M20 11v6'],
};

/** Identifiant du symbole dans le sprite, pour `<use href>`. */
export const idSymbole = (mouvement: LiftKey) => `mvt-${mouvement}`;

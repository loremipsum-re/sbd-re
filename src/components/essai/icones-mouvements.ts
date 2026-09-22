/**
 * Familles d'icônes pour les trois mouvements, à comparer en local.
 *
 * POURQUOI IL FAUT LES DESSINER. Tabler n'a pas d'icône de squat, de développé
 * couché ni de soulevé de terre. Vérifié le 22 septembre 2026 en interrogeant
 * le dépôt `tabler/tabler-icons` : `barbell`, `weight`, `dumbbell`,
 * `stretching`, `yoga` et `treadmill` répondent, `squat`, `bench-press`,
 * `deadlift` et `kettlebell` renvoient 404.
 *
 * LA GRAMMAIRE RESTE CELLE DE TABLER. Grille de 24, trait de 2, extrémités
 * rondes, aucun aplat. Un tracé maison qui respecte la géométrie du pack ne
 * jure pas avec lui ; c'est déjà le raisonnement de Coupe.astro et de
 * Medaille.astro, dessinés à la main faute d'équivalent.
 *
 * DE PROFIL, ET NON DE FACE. C'est le tournant de la deuxième version. Vus de
 * face, les trois mouvements donnaient trois barres horizontales empilées
 * différemment : même orientation, même masse, donc trois dessins cousins qu'il
 * fallait comparer pour les distinguer. De profil, ils n'ont plus la même
 * ORIENTATION GÉNÉRALE : le squat est vertical, le développé couché est
 * horizontal, le soulevé de terre est oblique. C'est cette différence-là qui
 * survit à la réduction, bien avant le détail d'une posture.
 *
 * Et de profil, la barre devient un DISQUE, vu sur la tranche. Un cercle est
 * la forme la plus robuste du répertoire : il se reconnaît encore à 16 px,
 * quand un empilement de trois traits se referme sur lui-même.
 */

export interface Famille {
  id: string;
  nom: string;
  argument: string;
  traces: Record<'squat' | 'bench' | 'deadlift', readonly string[]>;
}

/** Cercle à la manière de Tabler, deux demi-arcs plutôt qu'un `<circle>`. */
function disque(cx: number, cy: number, r: number): string {
  return `M${cx - r} ${cy}a${r} ${r} 0 1 0 ${2 * r} 0a${r} ${r} 0 1 0 ${-2 * r} 0`;
}

/**
 * Tabler Icons, jeu « outline », icône `barbell`.
 * Copyright (c) 2020-2026 Paweł Kuna, licence MIT.
 */
const BARBELL_TABLER = [
  'M2 12h1',
  'M6 8h-2a1 1 0 0 0 -1 1v6a1 1 0 0 0 1 1h2',
  'M6 7v10a1 1 0 0 0 1 1h1a1 1 0 0 0 1 -1v-10a1 1 0 0 0 -1 -1h-1a1 1 0 0 0 -1 1',
  'M9 12h6',
  'M15 7v10a1 1 0 0 0 1 1h1a1 1 0 0 0 1 -1v-10a1 1 0 0 0 -1 -1h-1a1 1 0 0 0 -1 1',
  'M18 8h2a1 1 0 0 1 1 1v6a1 1 0 0 1 -1 1h-2',
  'M22 12h-1',
] as const;

export const FAMILLES: readonly Famille[] = [
  {
    id: 'a',
    nom: 'A. Le disque et son repère, de profil',
    argument:
      "Le disque vu sur la tranche, à la hauteur du mouvement, et le strict minimum pour dire laquelle : les cuisses pliées, le banc, le sol. Aucun corps. C'est la famille qui descend le plus bas en taille, un cercle restant un cercle à 16 px.",
    traces: {
      /*
       * La barre TRAVERSE le disque. Sans elle, un cercle de rayon 4 posé en
       * haut de la boîte se lit comme une tête, et le glyphe devient un
       * bonhomme au lieu d'une fonte.
       */
      // Disque haut, buste, cuisses pliées, sol. Masse de 3 à 20.
      squat: [disque(12, 7, 4), 'M6 7h12', 'M12 11v4', 'M7 19l5 -4l5 4', 'M4 20h16'],
      // Disque haut, banc dessiné d'un seul trait en U renversé.
      bench: [disque(12, 7, 4), 'M6 7h12', 'M6 20v-5h12v5'],
      // Disque posé au sol, et les bras qui descendent le chercher.
      deadlift: [disque(12, 15, 4), 'M6 15h12', 'M12 4v7', 'M4 20h16'],
    },
  },
  {
    id: 'b',
    nom: 'B. La silhouette de profil',
    argument:
      "Un corps vu de côté, dans la position du mouvement. C'est ce que dessinent les applications de salle, et de profil la posture se lit enfin : le squat se plie vers l'arrière, le couché s'allonge, le soulevé de terre se casse en deux.",
    traces: {
      // Buste vertical, barre sur les épaules, jambe repliée vers l'arrière.
      squat: [
        disque(13, 4, 1.5),
        'M8 7.5h8',
        'M13 6v5',
        'M13 11l-4 3',
        'M9 14v5',
        'M4 20h16',
      ],
      // Corps allongé, bras tendu, barre au-dessus, banc dessous.
      bench: [disque(5, 14.5, 1.5), 'M6.5 15h7', 'M11 15v-4', 'M7 10h9', 'M5 20v-3h12v3'],
      // Dos cassé vers l'avant, bras tendu, disque au sol.
      deadlift: [
        disque(8, 5, 1.5),
        'M9 6.5l5 3.5',
        'M14 10v8',
        'M10 8.5v6',
        disque(10, 16.5, 2.5),
        'M4 20h16',
      ],
    },
  },
  {
    id: 'c',
    nom: 'C. La barre de Tabler, partagée',
    argument:
      "Aucun tracé maison : la même icône `barbell` du pack pour les trois, l'intitulé faisant seul la différence. Ne coûte aucune exception à la règle du jeu unique, mais ne distingue rien non plus.",
    traces: {
      squat: BARBELL_TABLER,
      bench: BARBELL_TABLER,
      deadlift: BARBELL_TABLER,
    },
  },
  {
    id: 'd',
    nom: 'D. De face, la version précédente',
    argument:
      "Gardée pour la comparaison. La barre vue de face, à trois hauteurs. Les trois glyphes partagent la même orientation horizontale, et c'est précisément ce qui les rend cousins : il faut les mettre côte à côte pour les séparer.",
    traces: {
      squat: ['M4 6h16', 'M8 4v4', 'M16 4v4', 'M7 19l5 -7l5 7'],
      bench: ['M4 7h16', 'M8 5v4', 'M16 5v4', 'M6 19v-5h12v5'],
      deadlift: ['M9 5v9', 'M15 5v9', 'M4 16h16', 'M8 13v6', 'M16 13v6', 'M3 20h18'],
    },
  },
];

export const MOUVEMENTS = [
  { cle: 'squat', court: 'Squat', long: 'Squat', valeur: '302,5' },
  { cle: 'bench', court: 'DC', long: 'Développé couché', valeur: '205' },
  { cle: 'deadlift', court: 'SDT', long: 'Soulevé de terre', valeur: '300' },
] as const;

/** Les tailles où l'icône doit tenir, de la plus contrainte à la plus confortable. */
export const TAILLES = [16, 20, 28, 40] as const;

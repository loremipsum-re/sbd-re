/**
 * Trois familles d'icônes pour les trois mouvements, à comparer en local.
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
 * LA VRAIE DIFFICULTÉ EST LA TAILLE. Les trois mouvements se distinguent par
 * une posture, et une silhouette humaine de 16 px est une tache. D'où la
 * famille A, qui abandonne le corps et ne garde que ce qui reste lisible en
 * tout petit : la HAUTEUR DE LA BARRE, haute sur les épaules, à mi-hauteur
 * au-dessus d'un banc, posée au sol.
 */

export interface Famille {
  id: string;
  nom: string;
  argument: string;
  traces: Record<'squat' | 'bench' | 'deadlift', readonly string[]>;
}

export const FAMILLES: readonly Famille[] = [
  {
    id: 'a',
    nom: 'A. La barre et son repère',
    argument:
      "La barre seule, placée à la hauteur du mouvement, avec le sol ou le banc pour repère. Rien d'autre. C'est la famille qui résiste le mieux à la réduction : une position se lit encore à 16 px là où un corps se brouille.",
    traces: {
      /*
       * Barre haute sur les épaules, cuisses écartées sous elle. La masse du
       * dessin couvre 4 à 19, soit un centre optique à 11,5 ; les trois
       * glyphes de la famille partagent ce centre, sans quoi ils danseraient
       * d'un intitulé à l'autre.
       */
      squat: ['M4 6h16', 'M8 4v4', 'M16 4v4', 'M7 19l5 -7l5 7'],
      // Barre au-dessus d'un banc, dessiné d'un seul trait en U renversé.
      bench: ['M4 7h16', 'M8 5v4', 'M16 5v4', 'M6 19v-5h12v5'],
      // Barre au sol, et deux bras qui descendent la chercher.
      deadlift: ['M9 5v9', 'M15 5v9', 'M4 16h16', 'M8 13v6', 'M16 13v6', 'M3 20h18'],
    },
  },
  {
    id: 'b',
    nom: 'B. La silhouette',
    argument:
      "Un corps dans la position du mouvement, comme le font les applications de salle. C'est plus parlant en grand, et c'est ce qui se brouille le plus vite en petit : à juger à 16 px, pas à 40.",
    traces: {
      /*
       * Genoux PLIÉS et non jambes écartées : la première version donnait une
       * personne debout, ce qui est exactement le mouvement que le squat n'est
       * pas. Le genou tombe à 13, le pied à 19.
       */
      squat: [
        'M10.5 4a1.5 1.5 0 1 0 3 0a1.5 1.5 0 1 0 -3 0',
        'M5 8h14',
        'M12 8v3',
        'M12 11l-3.5 2v6',
        'M12 11l3.5 2v6',
      ],
      // Corps allongé, bras tendus vers le haut, barre au-dessus d'eux.
      bench: [
        'M3 19h18',
        'M4.5 14a1.5 1.5 0 1 0 3 0a1.5 1.5 0 1 0 -3 0',
        'M7 15.5h7l3 3',
        'M10 14v-4',
        'M14 14v-4',
        'M6 10h12',
      ],
      deadlift: [
        'M7.5 4a1.5 1.5 0 1 0 3 0a1.5 1.5 0 1 0 -3 0',
        'M9 7l5 4',
        'M14 11v4',
        'M9 7l-2 6',
        'M9 18h10',
        'M11 15v6',
        'M17 15v6',
      ],
    },
  },
  {
    id: 'c',
    nom: 'C. La barre de Tabler, partagée',
    argument:
      "Aucun tracé maison : la même icône `barbell` du pack pour les trois, l'intitulé faisant seul la différence. Ne coûte aucune exception à la règle du jeu unique, mais ne distingue rien non plus.",
    traces: {
      squat: BARBELL_TABLER(),
      bench: BARBELL_TABLER(),
      deadlift: BARBELL_TABLER(),
    },
  },
];

/**
 * Tabler Icons, jeu « outline », icône `barbell`.
 * Copyright (c) 2020-2026 Paweł Kuna, licence MIT.
 */
function BARBELL_TABLER(): readonly string[] {
  return [
    'M2 12h1',
    'M6 8h-2a1 1 0 0 0 -1 1v6a1 1 0 0 0 1 1h2',
    'M6 7v10a1 1 0 0 0 1 1h1a1 1 0 0 0 1 -1v-10a1 1 0 0 0 -1 -1h-1a1 1 0 0 0 -1 1',
    'M9 12h6',
    'M15 7v10a1 1 0 0 0 1 1h1a1 1 0 0 0 1 -1v-10a1 1 0 0 0 -1 -1h-1a1 1 0 0 0 -1 1',
    'M18 8h2a1 1 0 0 1 1 1v6a1 1 0 0 1 -1 1h-2',
    'M22 12h-1',
  ];
}

export const MOUVEMENTS = [
  { cle: 'squat', court: 'Squat', long: 'Squat', valeur: '302,5' },
  { cle: 'bench', court: 'DC', long: 'Développé couché', valeur: '205' },
  { cle: 'deadlift', court: 'SDT', long: 'Soulevé de terre', valeur: '300' },
] as const;

/** Les tailles où l'icône doit tenir, de la plus contrainte à la plus confortable. */
export const TAILLES = [16, 20, 28, 40] as const;

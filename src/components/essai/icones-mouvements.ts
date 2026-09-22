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
 *
 * PUIS LE MATÉRIEL PLUTÔT QUE LE CORPS. Demande de l'auteur du 22 septembre
 * 2026, après trois tours de silhouettes : montrer la cage, le banc et le sol,
 * de face, avec la même barre posée à trois hauteurs. Un meuble n'a pas de
 * posture à interpréter, donc rien à perdre en réduisant, et les trois
 * situations se distinguent par ce qui porte la barre plutôt que par l'angle
 * d'un genou.
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
    nom: 'A. Le matériel, vu de face',
    argument:
      "Pas de corps du tout : la cage avec la barre en haut, le banc avec la barre au-dessus, la barre au sol. La même barre aux trois endroits, et c'est le meuble qui nomme le mouvement. Aucun détail à perdre en réduisant, puisqu'il n'y en a aucun.",
    traces: {
      /*
       * LA MÊME BARRE AUX TROIS ENDROITS, posée à trois hauteurs, avec trois
       * meubles différents dessous. C'est ce qui fait la famille : un seul
       * objet reconnaissable, trois situations.
       *
       * La barre tient en deux traits, l'axe et les deux disques. Les disques
       * débordent des montants, comme sur une vraie cage.
       */
      /*
       * LA HAUTEUR DE LA BARRE SUIT LE MOUVEMENT : en haut de la cage, au
       * milieu au-dessus du banc, au sol. Posées à la même hauteur, la cage et
       * le banc ne se distinguaient plus que par la forme du meuble, ce qui est
       * peu à 16 px.
       */
      // La cage : deux montants, deux pieds, la barre posée en haut.
      squat: ['M3 6.5h18', 'M5 4v5M19 4v5', 'M7 6.5v12.5M17 6.5v12.5', 'M5 20h4M15 20h4'],
      // Le banc : une assise et deux pieds, la barre au-dessus.
      bench: ['M3 11h18', 'M5 8.5v5M19 8.5v5', 'M5 16h14', 'M8 16v4M16 16v4'],
      // La barre au sol, les disques posés sur la ligne.
      deadlift: ['M3 16.5h18', 'M5 14v5M19 14v5', 'M3 20h18'],
    },
  },
  {
    id: 'b',
    nom: 'B. La silhouette de profil',
    argument:
      "Un corps vu de côté, dans la position du mouvement. C'est ce que dessinent les applications de salle, et de profil la posture se lit enfin : le squat se plie vers l'arrière, le couché s'allonge, le soulevé de terre se casse en deux.",
    traces: {
      /*
       * LE DISQUE EST LE FIL DE LA FAMILLE. Les trois glyphes portent le même
       * disque de rayon 2,5, à la hauteur où la barre se trouve dans le
       * mouvement : sur le dos, au-dessus de la poitrine, au sol. Deuxième
       * version du squat et du couché, la première ayant été refusée.
       *
       * SQUAT, troisième version. Deux corrections. La cuisse est À
       * L'HORIZONTALE et le tibia à la verticale : c'est l'angle droit du
       * genou qui fait lire un squat, et les versions précédentes gardaient
       * une jambe presque tendue. Et la barre RELIE le disque au corps : posé
       * seul derrière l'épaule, le disque flottait comme un objet sans rapport
       * avec la silhouette.
       */
      squat: [
        disque(11.5, 4, 1.5),
        'M9 7.5h5',
        disque(16, 7.5, 2),
        'M11 5.5l2.5 6.5',
        'M13.5 12l-6 1',
        'M7.5 13v6',
        'M3 20h18',
      ],
      /*
       * COUCHÉ. Troisième version. La barre devient un DISQUE au-dessus de la
       * poitrine : trois horizontales empilées, le corps, la barre et le banc,
       * se lisaient comme une table. Et les JAMBES DESCENDENT AU SOL, pliées
       * au genou, ce qui donne au glyphe l'angle qui lui manquait : sans elles,
       * un corps de profil n'est qu'un trait horizontal de plus.
       */
      bench: [
        'M6 10.5h8',
        'M6.5 8.5v4M13.5 8.5v4',
        'M9 15v-2.5',
        disque(5, 15.5, 1.5),
        'M6.5 16h6',
        'M12.5 16l2 1.5v2.5',
        // Le banc pose ses pieds au sol : une ligne de sol en plus doublerait
        // l'horizontale la plus basse et fermerait le dessin en petit.
        'M4 20v-3h9v3',
      ],
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
];

export const MOUVEMENTS = [
  { cle: 'squat', court: 'Squat', long: 'Squat', valeur: '302,5' },
  { cle: 'bench', court: 'DC', long: 'Développé couché', valeur: '205' },
  { cle: 'deadlift', court: 'SDT', long: 'Soulevé de terre', valeur: '300' },
] as const;

/** Les tailles où l'icône doit tenir, de la plus contrainte à la plus confortable. */
export const TAILLES = [16, 20, 28, 40] as const;

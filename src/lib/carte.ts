/**
 * La carte de La Réunion : projection, lecture des tracés, et pose des noms de
 * commune sur la carte des salles.
 *
 * Les tracés viennent de src/data/ile-reunion.json, que produit
 * scripts/generer-carte-ile.mjs à partir des contours de l'IGN. Ce fichier-ci
 * ne fait que les relire : toute règle de projection vit dans le générateur,
 * et la fonction `projeter` la recopie à l'identique.
 */
import ile from '../data/ile-reunion.json';

export type Point = [number, number];

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

/**
 * Relit un tracé du générateur en anneaux de points. Le format est celui que
 * le générateur écrit, et lui seul : un départ « M x y », des déplacements
 * relatifs après « l », un « z » qui referme.
 */
export function lireTrace(trace: string): Point[][] {
  return trace
    .split('M')
    .filter(Boolean)
    .map((morceau) => {
      const nombres = (morceau.match(/-?(?:\d+\.?\d*|\.\d+)/g) ?? []).map(Number);
      let x = nombres[0];
      let y = nombres[1];
      const points: Point[] = [[x, y]];
      for (let i = 2; i + 1 < nombres.length; i += 2) {
        x += nombres[i];
        y += nombres[i + 1];
        points.push([x, y]);
      }
      return points;
    });
}

/** Le point est-il dans l'anneau ? Lancer de rayon. */
export function dansAnneau([x, y]: Point, anneau: Point[]): boolean {
  let dedans = false;
  for (let i = 0, j = anneau.length - 1; i < anneau.length; j = i++) {
    const [xi, yi] = anneau[i];
    const [xj, yj] = anneau[j];
    if (yi > y !== yj > y && x < ((xj - xi) * (y - yi)) / (yj - yi) + xi) dedans = !dedans;
  }
  return dedans;
}

const anneauxDesCommunes = ile.communes.map((c) => ({ nom: c.nom, anneaux: lireTrace(c.chemin) }));

/**
 * La commune qui contient ce point de la carte, ou `null` en mer. Elle lit le
 * tracé léger des communes, juste à 200 m près : de quoi dire de quel côté
 * d'une limite tombe un nom, sans trancher le cas d'une salle. Le générateur
 * fait ce contrôle-là sur le tracé fin.
 */
export function communeEn(p: Point): string | null {
  return anneauxDesCommunes.find((c) => c.anneaux.some((a) => dansAnneau(p, a)))?.nom ?? null;
}

// ---------------------------------------------------------------------------
// Les noms de commune sur la carte des salles
// ---------------------------------------------------------------------------

/** Taille des noms de commune, en unités de carte, à pleine étendue. */
export const TAILLE_NOM_COMMUNE = 30;

/*
 * Mesures faites dans le navigateur, police Archivo en graisse 700 avec
 * l'interlettrage de la carte. La largeur d'un caractère va de 0,51 à 0,62
 * fois la taille selon le nom : 0,64 la couvre toujours. La boîte de ligne
 * monte à 0,90 fois la taille au-dessus de la ligne de base et descend à 0,21.
 */
const CHASSE = 0.64;
const HAUT = 0.9;
const BAS = 0.21;
/** Rayon d'un point, son contour compris, à pleine étendue. */
const RAYON_POINT = 13;
/** L'air entre un nom et ce qui l'entoure. */
const AIR = 6;

interface Boite {
  g: number;
  d: number;
  h: number;
  b: number;
}

export interface Etiquette {
  nom: string;
  /** Milieu du nom, ligne de base : ce qu'attend un `<text>` centré. */
  x: number;
  y: number;
}

interface Candidat extends Etiquette {
  boite: Boite;
  cout: number;
}

const chevauche = (a: Boite, b: Boite) =>
  a.g < b.d + AIR && b.g < a.d + AIR && a.h < b.b + AIR && b.h < a.b + AIR;

/** Distance d'un point au bord le plus proche d'une boîte, nulle dedans. */
function ecart(boite: Boite, [x, y]: Point): number {
  return Math.hypot(Math.max(boite.g - x, 0, x - boite.d), Math.max(boite.h - y, 0, y - boite.b));
}

/** Le cercle d'un point touche-t-il la boîte ? */
const couvre = (boite: Boite, p: Point) => ecart(boite, p) < RAYON_POINT;

/**
 * Pose le nom de chaque commune à côté de ses salles, sans qu'aucun nom n'en
 * chevauche un autre ni ne couvre un point.
 *
 * Le nom se posait au barycentre des salles, donc SUR les points qu'il
 * désigne, et deux communes voisines se recouvraient : Saint-Denis et
 * Sainte-Marie, La Possession et Le Port. Mesuré à 375 et 1 280 px.
 *
 * Chaque nom essaie trente-deux places autour du groupe de ses salles :
 * au-dessus, au-dessous, à droite, à gauche et en biais, au contact du groupe
 * puis un cran plus loin, et au-dessus comme au-dessous en glissant de côté.
 * Une place est exclue hors du cadre ou sous une zone réservée, celle des
 * boutons de zoom. Sinon elle coûte, du plus grave au plus léger :
 *  - chaque point qu'elle couvre ;
 *  - un nom plus proche d'une salle d'une autre commune que des siennes, qui
 *    se lirait comme le nom de l'autre ;
 *  - chaque salle d'une autre commune au ras du nom, à moins de 30 unités ;
 *  - chaque tiers du nom posé dans une autre commune que la sienne, la mer
 *    restant permise, comme sur toute carte de côte ;
 *  - sa distance au groupe, et un léger ordre de préférence entre les places.
 * La recherche essaie ensuite les combinaisons, en écartant toute branche qui
 * ne peut plus battre la meilleure trouvée : dix noms restent instantanés.
 */
export function placerEtiquettes(
  groupes: { nom: string; points: Point[] }[],
  tousLesPoints: Point[],
  cadre: Boite,
  reserves: Boite[] = [],
): Etiquette[] {
  const T = TAILLE_NOM_COMMUNE;
  const hauteurBoite = (HAUT + BAS) * T;

  /** Distance de la boîte au point le plus proche d'un groupe. */
  const auPlusPres = (pts: Point[], boite: Boite) => Math.min(...pts.map((p) => ecart(boite, p)));

  const candidatsParGroupe = groupes.map(({ nom, points }) => {
    const w = CHASSE * T * nom.length;
    const xs = points.map((p) => p[0]);
    const ys = points.map((p) => p[1]);
    const g = Math.min(...xs) - RAYON_POINT - AIR;
    const d = Math.max(...xs) + RAYON_POINT + AIR;
    const h = Math.min(...ys) - RAYON_POINT - AIR;
    const b = Math.max(...ys) + RAYON_POINT + AIR;
    const cx = xs.reduce((t, v) => t + v, 0) / xs.length;
    const cy = ys.reduce((t, v) => t + v, 0) / ys.length;
    const autres = groupes.filter((autre) => autre.nom !== nom);

    const candidats: Candidat[] = [];
    // Directions : nord, sud, est, ouest, puis les quatre biais. Au nord et
    // au sud, le nom glisse aussi d'un ou deux sixièmes de sa largeur de part
    // et d'autre, pour se dégager d'un groupe voisin sur la même côte.
    const directions: [number, number, number][] = [
      [0, -1, 0],
      [0, 1, 0],
      [1, 0, 0],
      [-1, 0, 0],
      [1, -1, 0],
      [-1, -1, 0],
      [1, 1, 0],
      [-1, 1, 0],
      ...[-2, -1, 1, 2].flatMap((k): [number, number, number][] => [
        [0, -1, k],
        [0, 1, k],
      ]),
    ];
    directions.forEach(([dx, dy, glisse], rang) => {
      for (const cran of [0, 1]) {
        const x =
          dx === 0
            ? cx + (glisse * w) / 6
            : dx > 0
              ? d + w / 2 + cran * 2 * AIR
              : g - w / 2 - cran * 2 * AIR;
        const milieuBoite =
          dy === 0
            ? cy
            : dy < 0
              ? h - hauteurBoite / 2 - cran * hauteurBoite
              : b + hauteurBoite / 2 + cran * hauteurBoite;
        const boite = {
          g: x - w / 2,
          d: x + w / 2,
          h: milieuBoite - hauteurBoite / 2,
          b: milieuBoite + hauteurBoite / 2,
        };
        const hors =
          boite.g < cadre.g || boite.d > cadre.d || boite.h < cadre.h || boite.b > cadre.b;
        if (hors || reserves.some((r) => chevauche(boite, r))) continue;

        const pointsCouverts = tousLesPoints.filter((p) => couvre(boite, p)).length;
        const ambigu = autres.some(
          (autre) => auPlusPres(autre.points, boite) < auPlusPres(points, boite),
        );
        // Les salles d'une autre commune au ras du nom, qu'il semblerait désigner.
        const abords = { g: boite.g - 30, d: boite.d + 30, h: boite.h - 30, b: boite.b + 30 };
        const voisines = autres
          .flatMap((autre) => autre.points)
          .filter(([px, py]) => px > abords.g && px < abords.d && py > abords.h && py < abords.b).length;
        const ailleurs = [boite.g + w / 6, x, boite.d - w / 6].filter((sx) => {
          const c = communeEn([sx, milieuBoite]);
          return c !== null && c !== nom;
        }).length;
        const cout =
          pointsCouverts * 1000 +
          (ambigu ? 500 : 0) +
          voisines * 150 +
          ailleurs * 150 +
          Math.hypot(x - cx, milieuBoite - cy) +
          rang * 5 +
          cran * 20;
        // Ligne de base : le milieu de la boîte, redescendu du demi-écart
        // entre la hauteur au-dessus de la ligne et la profondeur dessous.
        candidats.push({ nom, x, y: milieuBoite + ((HAUT - BAS) * T) / 2, boite, cout });
      }
    });
    // Aucune place dans le cadre : le nom revient sur ses salles, comme avant.
    if (candidats.length === 0) {
      const y = cy + ((HAUT - BAS) * T) / 2;
      const boite = { g: cx - w / 2, d: cx + w / 2, h: cy - hauteurBoite / 2, b: cy + hauteurBoite / 2 };
      candidats.push({ nom, x: cx, y, boite, cout: 1e4 });
    }
    return candidats.sort((a, b) => a.cout - b.cout);
  });

  // Le meilleur coût possible de chaque groupe, pour borner la recherche.
  const plancher = candidatsParGroupe.map((c) => c[0].cout);
  const resteMinimal = plancher.map((_, i) => plancher.slice(i).reduce((t, v) => t + v, 0));

  let meilleur: { cout: number; choix: Candidat[] } = { cout: Infinity, choix: [] };
  const choix: Candidat[] = [];
  const PENALITE_CHEVAUCHEMENT = 1e5;

  function explorer(i: number, cout: number) {
    if (cout + (resteMinimal[i] ?? 0) >= meilleur.cout) return;
    if (i === candidatsParGroupe.length) {
      meilleur = { cout, choix: [...choix] };
      return;
    }
    for (const c of candidatsParGroupe[i]) {
      const conflits = choix.filter((autre) => chevauche(c.boite, autre.boite)).length;
      choix.push(c);
      explorer(i + 1, cout + c.cout + conflits * PENALITE_CHEVAUCHEMENT);
      choix.pop();
    }
  }
  explorer(0, 0);

  return meilleur.choix.map(({ nom, x, y }) => ({
    nom,
    x: Math.round(x * 10) / 10,
    y: Math.round(y * 10) / 10,
  }));
}

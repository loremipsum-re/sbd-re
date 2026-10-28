/**
 * GÉNÈRE src/data/ile-reunion.json : la côte de La Réunion, les limites de ses
 * vingt-quatre communes, et la projection que les salles partagent avec elles.
 *
 *   node scripts/generer-carte-ile.mjs                     télécharge la source et écrit
 *   node scripts/generer-carte-ile.mjs --source <fichier>  lit une copie locale de la source
 *   node scripts/generer-carte-ile.mjs --mesurer           compare les tolérances, n'écrit rien
 *
 * Aucune bibliothèque de cartographie : Node seul, plus la liste officielle des
 * communes de scripts/lib/opl.mjs.
 *
 * -----------------------------------------------------------------------------
 * LA SOURCE
 * -----------------------------------------------------------------------------
 * IGN, Admin Express COG, édition 2018, mis au format GeoJSON par le projet
 * france-geojson. Ce projet a déjà allégé le tracé de l'IGN, coordonnées à cinq
 * décimales et simplification à 25 %, en gardant la topologie : deux communes
 * voisines y partagent exactement les mêmes sommets. Vérifié sur le fichier :
 * aucune paire de sommets distincts à moins de 3 m l'un de l'autre.
 *
 * Licence Ouverte d'Etalab. Elle demande de citer le producteur et la date de
 * mise à jour : la clé `source` du fichier écrit porte cette mention, et chaque
 * carte du site l'affiche.
 *
 * Les noms du fichier perdent leur article (« Tampon », « Port »). Ils sont
 * ramenés à l'orthographe officielle par la liste de scripts/lib/opl.mjs, celle
 * qui normalise déjà le champ commune des compétitions.
 *
 * -----------------------------------------------------------------------------
 * LA PROJECTION
 * -----------------------------------------------------------------------------
 * Équirectangulaire : x = longitude × cos(latitude moyenne), y = −latitude, puis
 * mise à l'échelle sur 1 000 unités de large. Sur une île de 70 km, l'écart avec
 * une projection conforme ne se voit pas. Les bornes sont écrites dans le
 * fichier, et `projeter` (src/lib/carte.ts) les relit pour placer les salles :
 * la côte et les salles suivent la même règle, au chiffre près.
 *
 * -----------------------------------------------------------------------------
 * LA SIMPLIFICATION PAR ARCS
 * -----------------------------------------------------------------------------
 * Simplifier chaque commune pour elle-même décalerait les deux tracés d'une
 * limite partagée, et ouvrirait des trous entre voisines. Le script découpe
 * donc les contours en ARCS, d'un noeud à l'autre, simplifie chaque arc une
 * seule fois, puis recompose chaque commune avec ces arcs. Un noeud est un
 * sommet où le voisinage change : trois communes s'y rejoignent, ou deux
 * communes et la mer.
 *
 * Avant d'écrire, le script le prouve : chaque arc intérieur sert à deux
 * communes, en sens contraires, chaque arc de côte à une seule, aucun segment
 * n'en croise un autre, et la somme des surfaces des communes égale celle de
 * l'île.
 *
 * Douglas-Peucker, la tolérance comptée en unités de carte (environ 64 m
 * l'unité). Deux niveaux, fixés par la mesure de `--mesurer` :
 *  - FIN, pour la carte des salles, que l'on zoome jusqu'à ×14 : la côte et
 *    les limites, dans les clés `chemin` et `limites` ;
 *  - LÉGER, pour la carte fixe des communes, large de 22rem au plus : un
 *    tracé fermé par commune, dans la clé `communes`.
 *
 * IDEMPOTENT : même source, même fichier, octet pour octet. Aucune date du jour.
 */

import { readdirSync, readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { gzipSync } from 'node:zlib';
import { ROOT, REUNION_TOWNS, communeOfficielle, normalizeTown } from './lib/opl.mjs';

const URL_SOURCE =
  'https://raw.githubusercontent.com/gregoiredavid/france-geojson/master/departements/974-la-reunion/communes-974-la-reunion.geojson';

/** Producteur et édition, la mention qu'exige la Licence Ouverte. */
const SOURCE = 'IGN, Admin Express COG, édition 2018';

const SORTIE = path.join(ROOT, 'src', 'data', 'ile-reunion.json');
const DOSSIER_SALLES = path.join(ROOT, 'src', 'content', 'salles');

const LARGEUR = 1000;

/**
 * Tolérances, en unités de carte. `--mesurer` refait la mesure qui les a
 * fixées, en octobre 2026, sur la source de l'IGN.
 *
 * FINE à 0. Jusqu'à 0,15 unité, la simplification ne retirait aucun des 769
 * points de côte et 8 des 2 053 points de limite : la source est déjà allégée.
 * Seul l'arrondi au dixième d'unité reste, 4 m d'écart au plus.
 *
 * LÉGÈRE à 3. Écart de 193 m au plus, soit un pixel sur une carte de 352 px,
 * la plus grande que trace l'infographie. Les 24 tracés pèsent 7 Ko, contre
 * 34 Ko sans simplification et 10 Ko à la tolérance 2.
 */
const TOLERANCE_FINE = 0;
const TOLERANCE_LEGERE = 3;

// ---------------------------------------------------------------------------
// Arguments
// ---------------------------------------------------------------------------

const args = process.argv.slice(2);
const mesurer = args.includes('--mesurer');
const iSource = args.indexOf('--source');
const fichierSource = iSource >= 0 ? args[iSource + 1] : null;
if (iSource >= 0 && !fichierSource) {
  throw new Error('--source attend un chemin de fichier.');
}

// ---------------------------------------------------------------------------
// Lecture et contrôle de la source
// ---------------------------------------------------------------------------

async function chargerSource() {
  if (fichierSource) {
    console.log(`Source lue sur le disque : ${fichierSource}`);
    return JSON.parse(readFileSync(fichierSource, 'utf8'));
  }
  console.log(`Téléchargement de ${URL_SOURCE}`);
  const reponse = await fetch(URL_SOURCE);
  if (!reponse.ok) {
    throw new Error(`Téléchargement impossible : HTTP ${reponse.status} ${reponse.statusText}`);
  }
  return reponse.json();
}

/**
 * « Tampon » → « Le Tampon ». On essaie chaque article devant le nom de la
 * source, et on garde la seule forme que connaît la liste officielle.
 */
function nomOfficiel(nomSource) {
  const formes = ['', 'Le ', 'La ', 'Les ', "L'"]
    .map((article) => article + nomSource)
    .filter((forme) => REUNION_TOWNS.has(normalizeTown(forme)));
  if (formes.length !== 1) {
    throw new Error(
      `Commune « ${nomSource} » : ${formes.length} correspondance(s) dans la liste officielle, une seule attendue.`,
    );
  }
  return communeOfficielle(formes[0]);
}

/** Surface signée d'un anneau, formule du lacet. */
function surface(anneau) {
  let s = 0;
  for (let i = 0, j = anneau.length - 1; i < anneau.length; j = i++) {
    s += (anneau[j][0] - anneau[i][0]) * (anneau[j][1] + anneau[i][1]);
  }
  return s / 2;
}

/**
 * Les anneaux d'une commune, sans le sommet qui referme chacun, tous orientés
 * dans le même sens. Les arcs de côte s'enchaînent alors bout à bout.
 */
function anneauxDe(feature) {
  const g = feature.geometry;
  const polygones = g.type === 'Polygon' ? [g.coordinates] : g.type === 'MultiPolygon' ? g.coordinates : null;
  if (!polygones) throw new Error(`Géométrie inattendue : ${g.type}`);
  const anneaux = [];
  for (const polygone of polygones) {
    if (polygone.length > 1) {
      throw new Error(`${feature.properties.nom} : un trou dans une commune, cas non prévu.`);
    }
    const [anneau] = polygone;
    const ferme = anneau[0][0] === anneau.at(-1)[0] && anneau[0][1] === anneau.at(-1)[1];
    const pts = ferme ? anneau.slice(0, -1) : anneau.slice();
    if (surface(pts) < 0) pts.reverse();
    anneaux.push(pts);
  }
  return anneaux;
}

// ---------------------------------------------------------------------------
// Topologie : noeuds et arcs
// ---------------------------------------------------------------------------

const cle = (p) => `${p[0]},${p[1]}`;

/**
 * Un sommet est un noeud quand ses deux voisins ne sont pas les mêmes d'une
 * commune à l'autre. Sur une limite partagée, chaque sommet a les mêmes deux
 * voisins dans les deux communes. Là où la limite quitte la côte ou rejoint
 * une troisième commune, les voisins changent.
 */
function trouverNoeuds(communes) {
  const voisins = new Map();
  for (const c of communes) {
    for (const anneau of c.anneaux) {
      const n = anneau.length;
      anneau.forEach((p, i) => {
        const paire = [cle(anneau[(i - 1 + n) % n]), cle(anneau[(i + 1) % n])].sort().join('|');
        const k = cle(p);
        if (!voisins.has(k)) voisins.set(k, new Set());
        voisins.get(k).add(paire);
      });
    }
  }
  return new Set([...voisins].filter(([, paires]) => paires.size > 1).map(([k]) => k));
}

/**
 * Découpe chaque anneau en arcs, de noeud à noeud. Un arc partagé par deux
 * communes n'est rangé qu'une fois : la seconde le référence à l'envers.
 */
function construireArcs(communes, noeuds) {
  const arcs = [];
  const index = new Map();

  function referencer(points) {
    const avant = points.map(cle).join(';');
    if (index.has(avant)) return { arc: index.get(avant), inverse: false };
    const arriere = points.map(cle).reverse().join(';');
    if (index.has(arriere)) return { arc: index.get(arriere), inverse: true };
    index.set(avant, arcs.length);
    arcs.push(points);
    return { arc: arcs.length - 1, inverse: false };
  }

  for (const c of communes) {
    c.references = c.anneaux.map((anneau) => {
      const n = anneau.length;
      const coupes = anneau.map((p, i) => (noeuds.has(cle(p)) ? i : -1)).filter((i) => i >= 0);

      if (coupes.length === 0) {
        // Un anneau sans noeud, l'îlot de Petite-Île : un seul arc fermé. On
        // le fait partir de son plus petit sommet, pour qu'une voisine qui le
        // partagerait le retrouve.
        let debut = 0;
        for (let i = 1; i < n; i++) if (cle(anneau[i]) < cle(anneau[debut])) debut = i;
        const tour = [...anneau.slice(debut), ...anneau.slice(0, debut), anneau[debut]];
        return [referencer(tour)];
      }

      const refs = [];
      for (let k = 0; k < coupes.length; k++) {
        const a = coupes[k];
        const b = coupes[(k + 1) % coupes.length];
        const morceau = [];
        for (let i = a; ; i = (i + 1) % n) {
          morceau.push(anneau[i]);
          if (i === b && morceau.length > 1) break;
        }
        refs.push(referencer(morceau));
      }
      return refs;
    });
  }
  return arcs;
}

// ---------------------------------------------------------------------------
// Projection, simplification, écriture des tracés
// ---------------------------------------------------------------------------

function bornesDe(communes) {
  let lonMin = Infinity;
  let lonMax = -Infinity;
  let latMin = Infinity;
  let latMax = -Infinity;
  for (const c of communes) {
    for (const anneau of c.anneaux) {
      for (const [lon, lat] of anneau) {
        lonMin = Math.min(lonMin, lon);
        lonMax = Math.max(lonMax, lon);
        latMin = Math.min(latMin, lat);
        latMax = Math.max(latMax, lat);
      }
    }
  }
  const kx = Math.cos((((latMin + latMax) / 2) * Math.PI) / 180);
  return { x0: lonMin * kx, x1: lonMax * kx, y0: -latMax, y1: -latMin, kx };
}

/** Distance d'un point à un segment. */
function distanceSegment(p, a, b) {
  const dx = b[0] - a[0];
  const dy = b[1] - a[1];
  const l2 = dx * dx + dy * dy;
  let t = l2 === 0 ? 0 : ((p[0] - a[0]) * dx + (p[1] - a[1]) * dy) / l2;
  t = Math.max(0, Math.min(1, t));
  return Math.hypot(p[0] - a[0] - t * dx, p[1] - a[1] - t * dy);
}

/** Douglas-Peucker, extrémités gardées. */
function douglasPeucker(points, tolerance) {
  const n = points.length;
  if (n <= 2) return points.slice();
  const garde = new Uint8Array(n);
  garde[0] = 1;
  garde[n - 1] = 1;
  const pile = [[0, n - 1]];
  while (pile.length) {
    const [a, b] = pile.pop();
    let dMax = 0;
    let iMax = -1;
    for (let i = a + 1; i < b; i++) {
      const d = distanceSegment(points[i], points[a], points[b]);
      if (d > dMax) {
        dMax = d;
        iMax = i;
      }
    }
    if (iMax >= 0 && dMax > tolerance) {
      garde[iMax] = 1;
      pile.push([a, iMax], [iMax, b]);
    }
  }
  return points.filter((_, i) => garde[i]);
}

/**
 * Simplifie un arc. Un arc fermé est coupé en deux au sommet le plus éloigné
 * de son départ, sans quoi Douglas-Peucker mesurerait tout contre un segment
 * de longueur nulle. Un anneau qui tomberait sous quatre sommets garde sa forme.
 */
function simplifierArc(points, tolerance) {
  const ferme = cle(points[0]) === cle(points.at(-1));
  if (!ferme) return douglasPeucker(points, tolerance);
  let loin = 1;
  for (let i = 2; i < points.length - 1; i++) {
    if (Math.hypot(points[i][0] - points[0][0], points[i][1] - points[0][1]) >
      Math.hypot(points[loin][0] - points[0][0], points[loin][1] - points[0][1])) loin = i;
  }
  const resultat = [
    ...douglasPeucker(points.slice(0, loin + 1), tolerance).slice(0, -1),
    ...douglasPeucker(points.slice(loin), tolerance),
  ];
  return resultat.length >= 4 ? resultat : points.slice();
}

/**
 * Coordonnées en DIXIÈMES d'unité, entiers. Un sommet partagé s'arrondit à
 * l'identique dans les deux communes, et les déplacements relatifs se
 * calculent sans erreur cumulée.
 */
const enDixiemes = (p) => [Math.round(p[0] * 10), Math.round(p[1] * 10)];

/** 12 → « 1.2 », 5 → « .5 », -5 → « -.5 », 120 → « 12 ». */
function ecrire(dixiemes) {
  const signe = dixiemes < 0 ? '-' : '';
  const a = Math.abs(dixiemes);
  const entier = Math.floor(a / 10);
  const decimale = a % 10;
  if (decimale === 0) return `${signe}${entier}`;
  return `${signe}${entier === 0 ? '' : entier}.${decimale}`;
}

/**
 * Une suite de nombres au plus court, comme la grammaire des tracés SVG le
 * permet : « 1.5.3 » se lit 1,5 puis 0,3, et le signe moins sépare à lui seul.
 */
function joindre(nombres) {
  let s = '';
  let precedent = '';
  for (const n of nombres) {
    const colle = n.startsWith('-') || (n.startsWith('.') && precedent.includes('.'));
    s += (s === '' || colle ? '' : ' ') + n;
    precedent = n;
  }
  return s;
}

/** Retire les sommets répétés que l'arrondi a pu créer. */
function sansDoublons(points) {
  return points.filter((p, i) => i === 0 || p[0] !== points[i - 1][0] || p[1] !== points[i - 1][1]);
}

/** Tracé d'une ligne ouverte : départ absolu, puis déplacements relatifs. */
function tracerLigne(points) {
  const [depart, ...suite] = points;
  const nombres = [];
  let precedent = depart;
  for (const p of suite) {
    nombres.push(ecrire(p[0] - precedent[0]), ecrire(p[1] - precedent[1]));
    precedent = p;
  }
  return `M${joindre([ecrire(depart[0]), ecrire(depart[1])])}l${joindre(nombres)}`;
}

/** Tracé d'un anneau, refermé par z. */
function tracerAnneau(points) {
  const fin = points.at(-1);
  const ouvert = fin[0] === points[0][0] && fin[1] === points[0][1] ? points.slice(0, -1) : points;
  return `${tracerLigne(ouvert)}z`;
}

/** Les sommets d'un anneau, recomposé à partir de ses arcs. */
function recomposer(references, arcsSimplifies) {
  const points = [];
  for (const { arc, inverse } of references) {
    const morceau = inverse ? arcsSimplifies[arc].slice().reverse() : arcsSimplifies[arc];
    points.push(...(points.length ? morceau.slice(1) : morceau));
  }
  return sansDoublons(points);
}

/**
 * Enchaîne les arcs de côte en anneaux, d'un noeud au suivant. Chaque anneau
 * de commune étant orienté dans le même sens, la fin d'un arc de côte est le
 * début du suivant.
 */
function anneauxDeCote(arcsCote, arcsSimplifies) {
  const parDebut = new Map(arcsCote.map((a) => [cle(arcsSimplifies[a][0]), a]));
  const restants = new Set(arcsCote);
  const anneaux = [];
  while (restants.size) {
    const premier = Math.min(...restants);
    const anneau = [];
    let a = premier;
    do {
      restants.delete(a);
      const morceau = arcsSimplifies[a];
      anneau.push(...(anneau.length ? morceau.slice(1) : morceau));
      a = parDebut.get(cle(morceau.at(-1)));
      if (a === undefined) throw new Error('Côte interrompue : un arc ne trouve pas son suivant.');
    } while (a !== premier);
    anneaux.push(sansDoublons(anneau));
  }
  return anneaux;
}

// ---------------------------------------------------------------------------
// Contrôles
// ---------------------------------------------------------------------------

const memePoint = (p, q) => p[0] === q[0] && p[1] === q[1];

/**
 * Deux segments se coupent-ils ailleurs qu'en une extrémité commune ? Deux
 * segments identiques comptent comme un croisement : deux arcs distincts qui
 * se confondent écraseraient la surface comprise entre eux.
 */
function seCoupent(a, b, c, d) {
  const orient = (p, q, r) => Math.sign((q[0] - p[0]) * (r[1] - p[1]) - (q[1] - p[1]) * (r[0] - p[0]));
  if ((memePoint(a, c) && memePoint(b, d)) || (memePoint(a, d) && memePoint(b, c))) return true;
  const o1 = orient(a, b, c);
  const o2 = orient(a, b, d);
  const o3 = orient(c, d, a);
  const o4 = orient(c, d, b);
  const partage = [a, b].some((p) => [c, d].some((q) => memePoint(p, q)));
  if (partage) {
    // Deux segments qui se touchent en un bout ne doivent pas se recouvrir.
    return o1 === 0 && o2 === 0 && o3 === 0 && o4 === 0 && recouvrement(a, b, c, d);
  }
  return o1 !== o2 && o3 !== o4;
}

/** Deux segments alignés qui se touchent par un bout se recouvrent-ils ? */
function recouvrement(a, b, c, d) {
  const axe = Math.abs(b[0] - a[0]) >= Math.abs(b[1] - a[1]) ? 0 : 1;
  const [m1, n1] = [Math.min(a[axe], b[axe]), Math.max(a[axe], b[axe])];
  const [m2, n2] = [Math.min(c[axe], d[axe]), Math.max(c[axe], d[axe])];
  return Math.min(n1, n2) - Math.max(m1, m2) > 0;
}

/** Aucun segment des arcs ne croise un autre segment. */
function verifierCroisements(arcs) {
  const segments = [];
  arcs.forEach((pts, a) => {
    for (let i = 0; i < pts.length - 1; i++) segments.push({ a, i, p: pts[i], q: pts[i + 1] });
  });
  const pas = 200; // dixièmes d'unité : une grille de 20 unités
  const grille = new Map();
  segments.forEach((s, k) => {
    const x0 = Math.floor(Math.min(s.p[0], s.q[0]) / pas);
    const x1 = Math.floor(Math.max(s.p[0], s.q[0]) / pas);
    const y0 = Math.floor(Math.min(s.p[1], s.q[1]) / pas);
    const y1 = Math.floor(Math.max(s.p[1], s.q[1]) / pas);
    for (let x = x0; x <= x1; x++) {
      for (let y = y0; y <= y1; y++) {
        const g = `${x}:${y}`;
        if (!grille.has(g)) grille.set(g, []);
        grille.get(g).push(k);
      }
    }
  });
  const vus = new Set();
  const croisements = [];
  for (const cellule of grille.values()) {
    for (let u = 0; u < cellule.length; u++) {
      for (let v = u + 1; v < cellule.length; v++) {
        const [k, l] = [cellule[u], cellule[v]].sort((m, n) => m - n);
        const paire = `${k}-${l}`;
        if (vus.has(paire)) continue;
        vus.add(paire);
        const s = segments[k];
        const t = segments[l];
        if (s.a === t.a && Math.abs(s.i - t.i) === 1) continue;
        if (seCoupent(s.p, s.q, t.p, t.q)) croisements.push(`arc ${s.a} et arc ${t.a}`);
      }
    }
  }
  return croisements;
}

/** Le point est-il dans l'anneau ? Lancer de rayon. */
function dansAnneau([x, y], anneau) {
  let dedans = false;
  for (let i = 0, j = anneau.length - 1; i < anneau.length; j = i++) {
    const [xi, yi] = anneau[i];
    const [xj, yj] = anneau[j];
    if (yi > y !== yj > y && x < ((xj - xi) * (y - yi)) / (yj - yi) + xi) dedans = !dedans;
  }
  return dedans;
}

// ---------------------------------------------------------------------------
// Pôle d'inaccessibilité : le point intérieur le plus éloigné des limites
// ---------------------------------------------------------------------------

/**
 * Le centre d'une commune, là où l'infographie des communes pose son nombre
 * de compétitions. Le barycentre tombe hors d'une commune en croissant, ce
 * point-là jamais. Recherche par cellules de plus en plus fines, à une
 * demi-unité près.
 */
function poleInaccessibilite(anneau, precision = 0.5) {
  const distance = (x, y) => {
    let d = Infinity;
    for (let i = 0, j = anneau.length - 1; i < anneau.length; j = i++) {
      d = Math.min(d, distanceSegment([x, y], anneau[j], anneau[i]));
    }
    return dansAnneau([x, y], anneau) ? d : -d;
  };
  const cellule = (x, y, h) => {
    const d = distance(x, y);
    return { x, y, h, d, max: d + h * Math.SQRT2 };
  };
  const xs = anneau.map((p) => p[0]);
  const ys = anneau.map((p) => p[1]);
  const [minX, maxX, minY, maxY] = [Math.min(...xs), Math.max(...xs), Math.min(...ys), Math.max(...ys)];
  const taille = Math.min(maxX - minX, maxY - minY);
  const file = [];
  for (let x = minX; x < maxX; x += taille) {
    for (let y = minY; y < maxY; y += taille) file.push(cellule(x + taille / 2, y + taille / 2, taille / 2));
  }
  let meilleure = cellule((minX + maxX) / 2, (minY + maxY) / 2, 0);
  while (file.length) {
    file.sort((a, b) => a.max - b.max);
    const c = file.pop();
    if (c.d > meilleure.d) meilleure = c;
    if (c.max - meilleure.d <= precision) continue;
    const h = c.h / 2;
    file.push(cellule(c.x - h, c.y - h, h), cellule(c.x + h, c.y - h, h));
    file.push(cellule(c.x - h, c.y + h, h), cellule(c.x + h, c.y + h, h));
  }
  return [meilleure.x, meilleure.y];
}

// ---------------------------------------------------------------------------
// Salles : chacune dans l'île, et dans la commune qu'elle déclare
// ---------------------------------------------------------------------------

/** Lit les champs simples d'un en-tête YAML, sans bibliothèque. */
function lireSalle(fichier) {
  const texte = readFileSync(fichier, 'utf8');
  const entete = texte.match(/^---\r?\n([\s\S]*?)\r?\n---/)?.[1] ?? '';
  const champ = (nom) => entete.match(new RegExp(`^${nom}:\\s*(.+)$`, 'm'))?.[1].trim().replace(/^"(.*)"$/, '$1');
  return {
    fichier: path.basename(fichier),
    nom: champ('nom'),
    commune: champ('commune'),
    lat: Number(champ('lat')),
    lon: Number(champ('lon')),
    actif: champ('actif') !== 'false',
  };
}

// ---------------------------------------------------------------------------
// Programme
// ---------------------------------------------------------------------------

const geojson = await chargerSource();
if (geojson.type !== 'FeatureCollection' || geojson.features.length !== 24) {
  throw new Error(`24 communes attendues, ${geojson.features?.length ?? 0} trouvées.`);
}

const communes = geojson.features
  .map((f) => {
    if (!/^974\d\d$/.test(f.properties.code)) throw new Error(`Code inattendu : ${f.properties.code}`);
    return { nom: nomOfficiel(f.properties.nom), code: f.properties.code, anneaux: anneauxDe(f) };
  })
  .sort((a, b) => a.code.localeCompare(b.code));

const sommetsSource = communes.reduce((t, c) => t + c.anneaux.reduce((u, a) => u + a.length, 0), 0);
const noeuds = trouverNoeuds(communes);
const arcsSource = construireArcs(communes, noeuds);

// Usage de chaque arc : une commune pour la côte, deux pour une limite.
const usages = arcsSource.map(() => []);
for (const c of communes) {
  for (const refs of c.references) for (const r of refs) usages[r.arc].push({ code: c.code, inverse: r.inverse });
}
for (const [a, u] of usages.entries()) {
  const valide =
    u.length === 1 || (u.length === 2 && u[0].code !== u[1].code && u[0].inverse !== u[1].inverse);
  if (!valide) throw new Error(`Arc ${a} : usage invalide (${JSON.stringify(u)}).`);
}
const arcsCote = usages.flatMap((u, a) => (u.length === 1 ? [a] : []));
const arcsInterieurs = usages.flatMap((u, a) => (u.length === 2 ? [a] : []));

const bornes = bornesDe(communes);
const hauteur = Math.round(((LARGEUR * (bornes.y1 - bornes.y0)) / (bornes.x1 - bornes.x0)) * 10) / 10;

/** La règle de `projeter` dans src/lib/carte.ts, à l'identique, hauteur arrondie comprise. */
const projeter = ([lon, lat]) => [
  ((lon * bornes.kx - bornes.x0) / (bornes.x1 - bornes.x0)) * LARGEUR,
  ((-lat - bornes.y0) / (bornes.y1 - bornes.y0)) * hauteur,
];
const arcsProjetes = arcsSource.map((pts) => pts.map(projeter));
const metresParUnite = ((bornes.x1 - bornes.x0) / LARGEUR) * 111_320;

/** Tout ce qu'une tolérance produit : tracés, poids, écart, contrôles. */
function niveau(tolerance) {
  const arcs = arcsProjetes.map((pts) => sansDoublons(simplifierArc(pts, tolerance).map(enDixiemes)));

  // Écart réel : chaque sommet d'origine contre l'arc simplifié et arrondi.
  let ecart = 0;
  arcsProjetes.forEach((pts, a) => {
    const s = arcs[a].map(([x, y]) => [x / 10, y / 10]);
    for (const p of pts) {
      let d = Infinity;
      for (let i = 0; i < s.length - 1; i++) d = Math.min(d, distanceSegment(p, s[i], s[i + 1]));
      if (s.length === 1) d = Math.hypot(p[0] - s[0][0], p[1] - s[0][1]);
      ecart = Math.max(ecart, d);
    }
  });

  const cote = anneauxDeCote(arcsCote, arcs);
  const anneauxCommunes = communes.map((c) => c.references.map((refs) => recomposer(refs, arcs)));
  const chemin = cote.map(tracerAnneau).join('');
  const limites = arcsInterieurs.map((a) => tracerLigne(arcs[a])).join('');
  const chemins = anneauxCommunes.map((anneaux) => anneaux.map(tracerAnneau).join(''));

  const surfaceIle = cote.reduce((t, a) => t + surface(a), 0);
  const surfaceCommunes = anneauxCommunes.flat().reduce((t, a) => t + surface(a), 0);

  return {
    tolerance,
    arcs,
    cote,
    anneauxCommunes,
    chemin,
    limites,
    chemins,
    // Le sommet qui referme un anneau n'est pas écrit : z s'en charge.
    pointsCote: cote.reduce((t, a) => t + a.length - 1, 0),
    pointsLimites: arcsInterieurs.reduce((t, a) => t + arcs[a].length, 0),
    ecartMetres: ecart * metresParUnite,
    surfaceIle,
    surfaceCommunes,
    croisements: verifierCroisements(arcs),
  };
}

const octets = (s) => Buffer.byteLength(s);
const gzip = (s) => gzipSync(s, { level: 9 }).length;

console.log(
  `${communes.length} communes, ${sommetsSource} sommets, ${noeuds.size} noeuds, ` +
    `${arcsCote.length} arcs de côte, ${arcsInterieurs.length} arcs de limite.`,
);

if (mesurer) {
  console.log(
    '\ntolérance | écart max | côte (pts) | limites (pts) | côte + limites (o / gzip) | 24 communes (o / gzip) | croisements',
  );
  for (const t of [0, 0.05, 0.1, 0.15, 0.2, 0.3, 0.5, 0.75, 1, 1.5, 2, 3]) {
    const n = niveau(t);
    const fin = n.chemin + n.limites;
    const toutes = n.chemins.join('');
    console.log(
      [
        `${t}`.padStart(9),
        `${n.ecartMetres.toFixed(0)} m`.padStart(9),
        `${n.pointsCote}`.padStart(10),
        `${n.pointsLimites}`.padStart(13),
        `${octets(fin)} / ${gzip(fin)}`.padStart(25),
        `${octets(toutes)} / ${gzip(toutes)}`.padStart(22),
        `${n.croisements.length}`.padStart(11),
      ].join(' | '),
    );
  }
  process.exit(0);
}

const fin = niveau(TOLERANCE_FINE);
const leger = niveau(TOLERANCE_LEGERE);

for (const n of [fin, leger]) {
  if (n.croisements.length) {
    throw new Error(`Tolérance ${n.tolerance} : ${n.croisements.length} croisement(s), ${n.croisements[0]}.`);
  }
  const ecartSurface = Math.abs(n.surfaceIle - n.surfaceCommunes) / n.surfaceIle;
  if (ecartSurface > 1e-9) {
    throw new Error(`Tolérance ${n.tolerance} : les communes ne couvrent pas l'île (écart ${ecartSurface}).`);
  }
}

const communesEcrites = communes.map((c, i) => {
  // Le centre se calcule sur l'anneau principal, le plus grand.
  const anneaux = leger.anneauxCommunes[i].map((a) => a.map(([x, y]) => [x / 10, y / 10]));
  const principal = anneaux.reduce((m, a) => (Math.abs(surface(a)) > Math.abs(surface(m)) ? a : m));
  const [cx, cy] = poleInaccessibilite(principal);
  return {
    nom: c.nom,
    code: c.code,
    centre: [Math.round(cx * 10) / 10, Math.round(cy * 10) / 10],
    chemin: leger.chemins[i],
  };
});

const donnees = {
  source: SOURCE,
  largeur: LARGEUR,
  hauteur,
  bornes,
  points: fin.pointsCote,
  chemin: fin.chemin,
  limites: fin.limites,
  communes: communesEcrites,
};
const texte = `${JSON.stringify(donnees, null, 2)}\n`;
writeFileSync(SORTIE, texte);

console.log(`\nÉcrit : ${path.relative(ROOT, SORTIE)}, ${octets(texte)} o, ${gzip(texte)} o compressé.`);
console.log(
  `Carte des salles, tolérance ${fin.tolerance} : côte ${fin.pointsCote} points, limites ${fin.pointsLimites} points, ` +
    `écart maximal ${fin.ecartMetres.toFixed(0)} m, ${octets(fin.chemin + fin.limites)} o.`,
);
console.log(
  `Carte des communes, tolérance ${leger.tolerance} : écart maximal ${leger.ecartMetres.toFixed(0)} m, ` +
    `${octets(leger.chemins.join(''))} o pour les 24 tracés.`,
);
console.log('Contrôles : aucun croisement, chaque limite partagée par deux communes, surfaces égales.');

// Les salles, contre les contours d'origine et contre le tracé fin.
const salles = readdirSync(DOSSIER_SALLES)
  .filter((f) => f.endsWith('.md'))
  .sort()
  .map((f) => lireSalle(path.join(DOSSIER_SALLES, f)))
  .filter((s) => s.actif);

const communeDe = (lonLat, anneauxParCommune) =>
  communes.find((_, i) => anneauxParCommune[i].some((a) => dansAnneau(lonLat, a)))?.nom ?? null;
const anneauxSource = communes.map((c) => c.anneaux);
const anneauxFins = fin.anneauxCommunes.map((anneaux) => anneaux.map((a) => a.map(([x, y]) => [x / 10, y / 10])));

const ecarts = [];
let dansIle = 0;
for (const s of salles) {
  const surSource = communeDe([s.lon, s.lat], anneauxSource);
  const surTrace = communeDe(projeter([s.lon, s.lat]), anneauxFins);
  if (surSource && surTrace) dansIle++;
  if (surSource !== s.commune || surTrace !== s.commune) {
    ecarts.push(
      `  ${s.fichier} : déclarée à ${s.commune}, ` +
        `dans ${surSource ?? 'la mer'} selon l'IGN, dans ${surTrace ?? 'la mer'} sur le tracé.`,
    );
  }
}
console.log(`\nSalles actives : ${salles.length}, dont ${dansIle} dans l'île.`);
if (ecarts.length) {
  console.log(`À vérifier à la main, ${ecarts.length} salle(s) :`);
  for (const e of ecarts) console.log(e);
} else {
  console.log('Chaque salle tombe dans la commune qu\'elle déclare.');
}

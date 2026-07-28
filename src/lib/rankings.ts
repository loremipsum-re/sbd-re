/**
 * Classements : meilleur total par athlète, et podiums.
 *
 * LE PIÈGE DE CE JEU DE DONNÉES, à garder en tête en lisant ce fichier :
 * les compétitions de développé couché seul ont un `totalKg` renseigné, qui
 * vaut simplement la meilleure barre au bench, ainsi qu'un score Dots calculé
 * dessus. Un total de 130 kg au bench seul se retrouverait donc à côté d'un
 * total full power de 500 kg, dans la même colonne.
 *
 * Tout classement au total part donc de `fullPowerResults`, jamais de
 * `results`. C'est la seule protection contre un podium absurde.
 */

import { equipmentGroup, type EquipmentGroup } from './categories';
import { fullPowerResults } from './data';
import { athleteSlug } from './slug';
import type { Result, Sex } from './types';

export interface AthleteRanking {
  name: string;
  slug: string;
  sex: Sex;
  /** Meilleur total réalisé en full power. */
  totalKg: number;
  dots: number;
  bodyweightKg: number | null;
  weightClassKg: string;
  equipment: string;
  equipmentGroup: EquipmentGroup;
  /** Compétition où ce meilleur total a été réalisé. */
  date: string;
  meetName: string;
  /** Nombre total de compétitions full power disputées. */
  competitions: number;
}

/**
 * Un athlète, une ligne : sa meilleure performance au total.
 *
 * On retient la ligne du meilleur total plutôt que la plus récente — c'est un
 * classement de records, pas un instantané de forme.
 */
export function meilleurTotalParAthlete(source: Result[] = fullPowerResults): AthleteRanking[] {
  const parNom = new Map<string, { meilleur: Result; competitions: number }>();

  for (const r of source) {
    if (r.totalKg === null || r.totalKg <= 0) continue;

    const courant = parNom.get(r.name);
    if (!courant) {
      parNom.set(r.name, { meilleur: r, competitions: 1 });
      continue;
    }
    courant.competitions++;
    if (r.totalKg > (courant.meilleur.totalKg ?? 0)) courant.meilleur = r;
  }

  const classement: AthleteRanking[] = [];
  for (const { meilleur, competitions } of parNom.values()) {
    classement.push({
      name: meilleur.name,
      slug: athleteSlug(meilleur.name),
      sex: meilleur.sex,
      totalKg: meilleur.totalKg as number,
      dots: meilleur.dots ?? 0,
      bodyweightKg: meilleur.bodyweightKg,
      weightClassKg: meilleur.weightClassKg,
      equipment: meilleur.equipment,
      equipmentGroup: equipmentGroup(meilleur.equipment),
      date: meilleur.date,
      meetName: meilleur.meetName,
      competitions,
    });
  }

  // Tri par défaut : le total brut. Le tri par Dots est proposé côté client.
  return classement.sort((a, b) => b.totalKg - a.totalKg || a.name.localeCompare(b.name));
}

/**
 * Top toutes catégories confondues, classé aux points Dots.
 *
 * Dots rapporte la performance au poids de corps : c'est la seule façon
 * honnête de comparer un athlète de 59 kg à un athlète de 120 kg dans un même
 * classement. Le total brut, lui, favoriserait mécaniquement les plus lourds.
 */
export function topDots(n = 10, source?: Result[]): AthleteRanking[] {
  return [...meilleurTotalParAthlete(source)]
    .sort((a, b) => b.dots - a.dots || b.totalKg - a.totalKg)
    .slice(0, n);
}

export interface RangDots {
  /** Position au classement Dots, 1 étant le meilleur. */
  rang: number;
  /** Nombre d'athlètes classés. */
  total: number;
  /** Meilleurs X pour cent, arrondi vers le haut. 5 signifie « top 5 % ». */
  centile: number;
  dots: number;
}

/**
 * Position d'un athlète au classement général aux points Dots.
 *
 * Calculé une fois puis mémorisé : la fonction est appelée pour chacune des
 * 312 fiches, et retrier le classement à chaque appel multiplierait le temps
 * de build sans rien apporter.
 */
let cacheRangs: Map<string, RangDots> | null = null;

export function rangDots(name: string): RangDots | null {
  if (!cacheRangs) {
    const classement = [...meilleurTotalParAthlete()].sort(
      (a, b) => b.dots - a.dots || b.totalKg - a.totalKg,
    );
    cacheRangs = new Map();
    classement.forEach((e, i) => {
      cacheRangs!.set(e.name, {
        rang: i + 1,
        total: classement.length,
        centile: Math.max(1, Math.ceil(((i + 1) / classement.length) * 100)),
        dots: e.dots,
      });
    });
  }
  return cacheRangs.get(name) ?? null;
}

/** Historique complet d'un athlète, de la compétition la plus ancienne à la plus récente. */
export function historiqueAthlete(name: string, source: Result[]): Result[] {
  return source
    .filter((r) => r.name === name)
    .sort((a, b) => a.date.localeCompare(b.date) || a.event.localeCompare(b.event));
}

/**
 * Records de La Réunion, par mouvement × sexe × catégorie de poids × équipement.
 *
 * Deux règles gouvernent l'éligibilité d'une ligne, et elles ne sont pas les
 * mêmes selon le mouvement :
 *
 *  - Squat, soulevé de terre et total : uniquement en full power (SBD). Une
 *    compétition de développé couché seul ne contient aucune de ces barres, et
 *    son `totalKg` n'est pas un vrai total.
 *  - Développé couché : TOUTES les compétitions, full power comme bench seul.
 *    26 des 61 compétitions réunionnaises sont des compétitions de développé
 *    couché : les ignorer afficherait un record inférieur à ce qui s'est
 *    réellement fait sur l'île.
 */

import {
  compareWeightClass,
  equipmentGroup,
  parseWeightClass,
  type EquipmentGroup,
  type WeightClass,
} from './categories';
import { results } from './data';
import { athleteSlug } from './slug';
import type { LiftKey, Result, Sex } from './types';

export interface RecordEntry {
  lift: LiftKey;
  sex: Sex;
  weightClass: WeightClass;
  equipment: EquipmentGroup;
  valueKg: number;
  holder: string;
  slug: string;
  date: string;
  meetName: string;
  meetTown: string;
}

/** Charge retenue pour un mouvement donné, ou null si la ligne ne compte pas. */
function valeurPour(r: Result, lift: LiftKey): number | null {
  switch (lift) {
    case 'squat':
      return r.event === 'SBD' ? r.bestSquatKg : null;
    case 'deadlift':
      return r.event === 'SBD' ? r.bestDeadliftKg : null;
    case 'total':
      // Le total n'a de sens qu'en full power : voir l'avertissement en tête.
      return r.event === 'SBD' ? r.totalKg : null;
    case 'bench':
      // Seul mouvement où les compétitions de bench seul comptent.
      return r.bestBenchKg;
  }
}

/**
 * Meilleure performance par (sexe × catégorie × équipement) pour un mouvement.
 *
 * Les lignes sans catégorie de poids renseignée (24 dans les données actuelles)
 * sont écartées : un record n'a de sens que rattaché à une catégorie.
 */
export function recordsPour(lift: LiftKey, source: Result[] = results): RecordEntry[] {
  const meilleurs = new Map<string, RecordEntry>();

  for (const r of source) {
    const valeur = valeurPour(r, lift);
    if (valeur === null || valeur <= 0) continue;

    const weightClass = parseWeightClass(r.weightClassKg);
    if (!weightClass) continue;

    const equipement = equipmentGroup(r.equipment);
    const cle = `${r.sex}|${weightClass.id}|${equipement}`;

    const actuel = meilleurs.get(cle);
    if (actuel && actuel.valueKg >= valeur) continue;

    meilleurs.set(cle, {
      lift,
      sex: r.sex,
      weightClass,
      equipment: equipement,
      valueKg: valeur,
      holder: r.name,
      slug: athleteSlug(r.name),
      date: r.date,
      meetName: r.meetName,
      meetTown: r.meetTown,
    });
  }

  return [...meilleurs.values()].sort(
    (a, b) =>
      a.sex.localeCompare(b.sex) ||
      a.equipment.localeCompare(b.equipment) ||
      compareWeightClass(a.weightClass, b.weightClass),
  );
}

/** Records d'un mouvement, filtrés pour un sexe et un type d'équipement. */
export function recordsFiltres(
  lift: LiftKey,
  sex: Sex,
  equipment: EquipmentGroup,
  source: Result[] = results,
): RecordEntry[] {
  return recordsPour(lift, source).filter(
    (r) => r.sex === sex && r.equipment === equipment,
  );
}

/**
 * Meilleure performance absolue d'un mouvement, toutes catégories confondues.
 * Sert aux chiffres mis en avant sur la page d'accueil.
 */
export function recordAbsolu(
  lift: LiftKey,
  sex: Sex,
  source: Result[] = results,
): RecordEntry | null {
  const candidats = recordsPour(lift, source).filter((r) => r.sex === sex);
  if (candidats.length === 0) return null;
  return candidats.reduce((a, b) => (b.valueKg > a.valueKg ? b : a));
}

/** Types d'équipement réellement présents dans les données, pour ne pas afficher de sections vides. */
export function equipementsPresents(source: Result[] = results): EquipmentGroup[] {
  const vus = new Set<EquipmentGroup>();
  for (const r of source) vus.add(equipmentGroup(r.equipment));
  return ['raw', 'wraps', 'equipped'].filter((e) =>
    vus.has(e as EquipmentGroup),
  ) as EquipmentGroup[];
}

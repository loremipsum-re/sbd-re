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

/**
 * Charge retenue pour un mouvement donné, ou null si la ligne ne compte pas.
 *
 * Règle générale : une barre compte dès lors qu'elle a réellement été soulevée,
 * quel que soit le format de la compétition. Un squat réalisé lors d'une
 * compétition de squat seul reste un squat.
 *
 * L'exception est le TOTAL, qui n'existe qu'en full power. Sur les formats
 * partiels, OpenPowerlifting renseigne un « total » qui n'est que la somme des
 * mouvements disputés : le retenir mettrait un bench de 130 kg en concurrence
 * avec un vrai total de 700 kg.
 */
function valeurPour(r: Result, lift: LiftKey): number | null {
  if (lift === 'total') {
    return r.event === 'SBD' ? r.totalKg : null;
  }
  // Pour les mouvements individuels, la valeur est simplement absente (null)
  // quand la compétition ne comportait pas ce mouvement : aucun filtre sur
  // `event` n'est nécessaire, les données s'en chargent.
  if (lift === 'squat') return r.bestSquatKg;
  if (lift === 'bench') return r.bestBenchKg;
  return r.bestDeadliftKg;
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

/**
 * Ensemble des performances qui constituent actuellement un record.
 *
 * Sert à marquer une ligne d'un badge de vérification sur les fiches athlètes :
 * on veut distinguer « cette barre est le record de sa catégorie » de « cette
 * barre est un bon résultat ». La clé identifie une performance précise, à une
 * date et une compétition données.
 *
 * Calculé une seule fois puis mémorisé : la fonction est appelée pour chacune
 * des 312 fiches athlètes, et recalculer les records à chaque fois multiplierait
 * le temps de build sans raison.
 */
let cacheRecords: Set<string> | null = null;

export function clesRecords(source: Result[] = results): Set<string> {
  if (cacheRecords && source === results) return cacheRecords;

  const cles = new Set<string>();
  for (const lift of ['squat', 'bench', 'deadlift', 'total'] as LiftKey[]) {
    for (const rec of recordsPour(lift, source)) {
      cles.add(cleRecord(lift, rec.holder, rec.date, rec.meetName));
    }
  }

  if (source === results) cacheRecords = cles;
  return cles;
}

/** Construit la clé d'une performance, pour interroger l'ensemble ci-dessus. */
export function cleRecord(
  lift: LiftKey,
  nom: string,
  date: string,
  meetName: string,
): string {
  return `${lift}|${nom}|${date}|${meetName}`;
}

/** Types d'équipement réellement présents dans les données, pour ne pas afficher de sections vides. */
export function equipementsPresents(source: Result[] = results): EquipmentGroup[] {
  const vus = new Set<EquipmentGroup>();
  for (const r of source) vus.add(equipmentGroup(r.equipment));
  return ['raw', 'wraps', 'equipped'].filter((e) =>
    vus.has(e as EquipmentGroup),
  ) as EquipmentGroup[];
}

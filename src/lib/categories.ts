/**
 * Source unique de vérité pour toutes les catégories du site.
 *
 * Deux jeux cohabitent ici, et ils ne doivent JAMAIS se mélanger :
 *
 *  - OFFICIEL   : les catégories de poids de la fédération, telles qu'elles
 *                 figurent dans les résultats de compétition. Un record n'a de
 *                 sens que dans la catégorie où il a été réalisé.
 *  - COMMUNAUTÉ : des tranches larges, déclaratives, pour le classement
 *                 non-officiel où personne ne passe sur une balance homologuée.
 *
 * Sexe, équipement et mouvement, eux, sont communs aux deux.
 */

import type { LiftKey, Result, Sex } from './types';

/* -------------------------------------------------------------------------
 * Sexe
 * ---------------------------------------------------------------------- */

export const SEXES: { id: Sex; label: string; plural: string }[] = [
  { id: 'M', label: 'Homme', plural: 'Hommes' },
  { id: 'F', label: 'Femme', plural: 'Femmes' },
];

export function sexLabel(sex: string): string {
  return SEXES.find((s) => s.id === sex)?.label ?? sex;
}

/* -------------------------------------------------------------------------
 * Mouvements
 * ---------------------------------------------------------------------- */

export const LIFTS: { id: LiftKey; label: string; short: string }[] = [
  { id: 'squat', label: 'Squat', short: 'SQ' },
  { id: 'bench', label: 'Développé couché', short: 'DC' },
  { id: 'deadlift', label: 'Soulevé de terre', short: 'SDT' },
  { id: 'total', label: 'Total', short: 'TOT' },
];

/* -------------------------------------------------------------------------
 * Équipement
 * ---------------------------------------------------------------------- */

export type EquipmentGroup = 'raw' | 'wraps' | 'equipped';

export const EQUIPMENT_GROUPS: { id: EquipmentGroup; label: string; hint: string }[] = [
  { id: 'raw', label: 'Raw', hint: 'Sans équipement de force' },
  { id: 'wraps', label: 'Bandes', hint: 'Bandes de genoux autorisées' },
  { id: 'equipped', label: 'Équipé', hint: 'Combinaison de force' },
];

/**
 * Regroupe les libellés d'équipement d'OpenPowerlifting en trois familles.
 * Dans les données réunionnaises actuelles, seuls « Raw » et « Single-ply »
 * apparaissent — mais la fonction couvre les autres cas pour ne pas casser si
 * une compétition multi-ply arrive un jour.
 */
export function equipmentGroup(equipment: string): EquipmentGroup {
  if (equipment === 'Wraps') return 'wraps';
  if (equipment === 'Raw') return 'raw';
  return 'equipped'; // Single-ply, Multi-ply, Unlimited
}

export function equipmentLabel(group: EquipmentGroup): string {
  return EQUIPMENT_GROUPS.find((e) => e.id === group)?.label ?? group;
}

/* -------------------------------------------------------------------------
 * Catégories de poids OFFICIELLES
 *
 * Volontairement NON codées en dur. Les catégories ont changé au fil des
 * réformes de la fédération : on trouve dans les données à la fois des classes
 * actuelles (93, 120+) et anciennes (72, 76). Figer une liste ferait
 * silencieusement disparaître des résultats. On lit donc ce que les données
 * contiennent réellement, et on se contente de les ordonner.
 * ---------------------------------------------------------------------- */

export interface WeightClass {
  /** Valeur brute, telle qu'en base : « 93 », « 120+ ». */
  id: string;
  label: string;
  /** Borne numérique, pour le tri. */
  kg: number;
  /** true pour les catégories ouvertes vers le haut (« 120+ »). */
  open: boolean;
}

export function parseWeightClass(value: string): WeightClass | null {
  if (!value) return null;
  const open = value.endsWith('+');
  const kg = Number.parseFloat(open ? value.slice(0, -1) : value);
  if (!Number.isFinite(kg)) return null;
  return {
    id: value,
    label: open ? `+${kg} kg` : `−${kg} kg`,
    kg,
    open,
  };
}

/** Tri croissant, les catégories ouvertes passant après la borne de même poids. */
export function compareWeightClass(a: WeightClass, b: WeightClass): number {
  return a.kg - b.kg || Number(a.open) - Number(b.open);
}

/** Catégories réellement présentes pour un sexe donné, ordonnées. */
export function weightClassesFor(results: Result[], sex: Sex): WeightClass[] {
  const seen = new Map<string, WeightClass>();
  for (const r of results) {
    if (r.sex !== sex) continue;
    const wc = parseWeightClass(r.weightClassKg);
    if (wc) seen.set(wc.id, wc);
  }
  return [...seen.values()].sort(compareWeightClass);
}

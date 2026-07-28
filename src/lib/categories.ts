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

export function liftLabel(lift: LiftKey): string {
  return LIFTS.find((l) => l.id === lift)?.label ?? lift;
}

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

/* -------------------------------------------------------------------------
 * Tranches COMMUNAUTÉ (phase 7)
 *
 * Déclaratives et volontairement larges : personne ne se pèse officiellement
 * pour soumettre une performance de salle. Une tranche est aussi bien moins
 * intrusive qu'un poids au kilo près — ce qui lève un frein à la participation
 * et allège la justification côté RGPD.
 *
 * Ces bornes sont définies ICI et nulle part ailleurs : formulaire, filtres et
 * affichage les lisent tous depuis cette liste.
 * ---------------------------------------------------------------------- */

export interface Bracket {
  id: string;
  label: string;
  /** Borne basse incluse, borne haute exclue. `null` = pas de borne. */
  min: number | null;
  max: number | null;
}

export const COMMUNITY_WEIGHT_BRACKETS: Bracket[] = [
  { id: '-60', label: 'moins de 60 kg', min: null, max: 60 },
  { id: '60-70', label: '60 à 70 kg', min: 60, max: 70 },
  { id: '70-80', label: '70 à 80 kg', min: 70, max: 80 },
  { id: '80-90', label: '80 à 90 kg', min: 80, max: 90 },
  { id: '90-100', label: '90 à 100 kg', min: 90, max: 100 },
  { id: '100-110', label: '100 à 110 kg', min: 100, max: 110 },
  { id: '110-120', label: '110 à 120 kg', min: 110, max: 120 },
  { id: '120+', label: 'plus de 120 kg', min: 120, max: null },
];

export const COMMUNITY_HEIGHT_BRACKETS: Bracket[] = [
  { id: '-150', label: 'moins de 150 cm', min: null, max: 150 },
  { id: '150-160', label: '150 à 160 cm', min: 150, max: 160 },
  { id: '160-170', label: '160 à 170 cm', min: 160, max: 170 },
  { id: '170-180', label: '170 à 180 cm', min: 170, max: 180 },
  { id: '180-190', label: '180 à 190 cm', min: 180, max: 190 },
  { id: '190-200', label: '190 à 200 cm', min: 190, max: 200 },
  { id: '200+', label: 'plus de 200 cm', min: 200, max: null },
];

/** Retrouve la tranche correspondant à une valeur chiffrée. */
export function bracketFor(value: number, brackets: Bracket[]): Bracket | null {
  return (
    brackets.find(
      (b) => (b.min === null || value >= b.min) && (b.max === null || value < b.max),
    ) ?? null
  );
}

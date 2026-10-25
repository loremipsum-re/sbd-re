/**
 * Le portrait chiffré d'UNE compétition, que dessine InfographieCompetition.
 *
 * ISOLÉ ICI POUR DEUX CALCULS QUI PARCOURENT TOUTE L'ÎLE : la date du premier
 * résultat de chaque athlète, et les records toutes catégories confondues.
 * Ils servent à chaque page de compétition et à chaque compte rendu. Calculés
 * une fois, ils sont mémorisés, comme `clesRecords` dans lib/records.ts.
 *
 * Tout se lit dans les résultats, comme le reste du site. Aucun effectif n'est
 * recopié : la mise à jour mensuelle redessine chaque compétition.
 */

import {
  EQUIPMENT_GROUPS,
  compareWeightClass,
  equipmentGroup,
  parseWeightClass,
  type EquipmentGroup,
  type WeightClass,
} from './categories';
import { periode, results } from './data';
import { listeMeets, resultatsDuMeet, type Meet } from './meets';
import { cleRecord, clesRecords, recordAbsolu } from './records';
import { meetKey } from './slug';
import type { EventCode, LiftKey, Result, Sex } from './types';

/** Une colonne du graphique : une catégorie de poids et ses athlètes. */
export interface Colonne {
  /** `null` quand les résultats ne portent aucune catégorie de poids. */
  classe: WeightClass | null;
  resultats: number;
  /** Effectif par famille d'équipement, dans l'ordre d'EQUIPMENT_GROUPS. */
  parEquipement: { equipement: EquipmentGroup; resultats: number }[];
}

export interface RangeeSexe {
  sexe: Sex;
  resultats: number;
  colonnes: Colonne[];
}

/** Un format disputé, full power ou mouvement seul, et son plateau. */
export interface BlocFormat {
  event: EventCode;
  resultats: number;
  sexes: RangeeSexe[];
  /**
   * Vrai quand aucun résultat du format ne porte de catégorie de poids, comme
   * aux Éliminatoires Jean Villenave. Le graphique range alors les athlètes
   * par sexe, une colonne chacun.
   */
  sansCategorie: boolean;
}

export interface ProfilCompetition {
  meet: Meet;
  formats: BlocFormat[];
  /** Catégories de poids distinctes, sexe compris : le −63 kg femmes et le −63 kg hommes en font deux. */
  categories: number;
  /** Plus grand nombre de colonnes d'une rangée, pour aligner les rangées entre elles. */
  colonnesMax: number;
  /**
   * Vrai si un format qui a des catégories compte aussi des résultats sans
   * catégorie : ils forment une colonne marquée d'un tiret.
   */
  tiret: boolean;
  /** Athlètes distincts par famille d'équipement. */
  equipements: { equipement: EquipmentGroup; athletes: number }[];
  /** Vrai si un athlète dispute plusieurs formats : il compte alors une fois dans chacun. */
  plusieursFormatsParAthlete: boolean;
  /**
   * VRAI SI CHAQUE CARRÉ EST UN ATHLÈTE. Un même athlète peut disputer le full
   * power et le développé couché le même jour : il figure alors dans deux
   * formats, une fois dans chacun. Il compterait deux fois dans un même format
   * seulement s'il y concourait sous deux équipements. Le cas n'existe pas dans
   * les données, mais il est vérifié plutôt que supposé.
   */
  unAthleteParCarre: boolean;
  femmesSeulement: boolean;
  /**
   * Athlètes dont c'est le premier résultat RECENSÉ sur l'île. Les données
   * commencent en 2017 : un athlète qui concourait avant y apparaît comme un
   * débutant, d'où le mot « recensé » à l'affichage. `null` pour la
   * compétition qui ouvre les données : tout son plateau y débuterait par
   * construction, ce qui ne dirait rien.
   */
  debutants: number | null;
  /**
   * Marques de ce jour qui sont encore des records de leur catégorie : les
   * coupes du tableau. Ce sont des barres ou des totaux, d'où « marques ».
   */
  recordsCategorie: number;
  /** Parmi elles, les meilleures de l'île pour leur sexe, toutes catégories confondues. */
  recordsAbsolus: number;
  /**
   * Bornes des tranches d'âge relevées, « 13-15 » donnant 13 et « 65-69 »
   * donnant 69. La dernière tranche d'OpenPowerlifting, « 80-999 », n'a pas
   * de borne haute : `max` prend alors sa borne basse et `ouvert` vaut vrai.
   */
  ages: { min: number; max: number; ouvert: boolean } | null;
}

const LEVEES: LiftKey[] = ['squat', 'bench', 'deadlift', 'total'];
const ORDRE_EQUIPEMENT = EQUIPMENT_GROUPS.map((g) => g.id);
/** Borne haute de « 80-999 », la tranche ouverte d'OpenPowerlifting. */
const TRANCHE_SANS_BORNE = 999;

let premiers: Map<string, string> | null = null;

/** Date du premier résultat de chaque athlète sur l'île, par nom. */
function premierResultat(): Map<string, string> {
  if (premiers) return premiers;
  const dates = new Map<string, string>();
  for (const r of results) {
    const connue = dates.get(r.name);
    if (!connue || r.date < connue) dates.set(r.name, r.date);
  }
  premiers = dates;
  return dates;
}

let absolus: Map<string, number> | null = null;

/** Nombre de records toutes catégories confondues, par compétition. */
function recordsAbsolusParMeet(): Map<string, number> {
  if (absolus) return absolus;
  const comptes = new Map<string, number>();
  for (const levee of LEVEES) {
    for (const sexe of ['M', 'F'] as Sex[]) {
      const record = recordAbsolu(levee, sexe);
      if (!record) continue;
      const cle = meetKey(record.date, record.meetName);
      comptes.set(cle, (comptes.get(cle) ?? 0) + 1);
    }
  }
  absolus = comptes;
  return comptes;
}

/**
 * Coupes du tableau de résultats, recomptées avec la même règle que la page
 * de la compétition : une barre réussie qui constitue aujourd'hui un record,
 * le total seulement en full power.
 */
function coupes(r: Result): number {
  const records = clesRecords();
  const valeurs: Record<LiftKey, number | null> = {
    squat: r.bestSquatKg,
    bench: r.bestBenchKg,
    deadlift: r.bestDeadliftKg,
    total: r.event === 'SBD' ? r.totalKg : null,
  };
  return LEVEES.filter(
    (levee) => valeurs[levee] && records.has(cleRecord(levee, r.name, r.date, r.meetName)),
  ).length;
}

/** « 16-17 » donne [16, 17]. Une tranche vide ou illisible donne `null`. */
function bornes(tranche: string): [number, number] | null {
  const [bas, haut] = tranche.split('-').map(Number);
  return Number.isFinite(bas) && Number.isFinite(haut) ? [bas, haut] : null;
}

function rangee(sexe: Sex, lignes: Result[]): RangeeSexe {
  const parClasse = new Map<string, Result[]>();
  for (const r of lignes) {
    const cle = parseWeightClass(r.weightClassKg)?.id ?? '';
    parClasse.set(cle, [...(parClasse.get(cle) ?? []), r]);
  }

  const colonnes: Colonne[] = [...parClasse.entries()].map(([cle, membres]) => ({
    classe: parseWeightClass(cle),
    resultats: membres.length,
    parEquipement: ORDRE_EQUIPEMENT.map((equipement) => ({
      equipement,
      resultats: membres.filter((r) => equipmentGroup(r.equipment) === equipement).length,
    })).filter((e) => e.resultats > 0),
  }));

  // Les catégories du plus léger au plus lourd, celles qu'on ignore en dernier.
  colonnes.sort((a, b) =>
    a.classe && b.classe ? compareWeightClass(a.classe, b.classe) : a.classe ? -1 : 1,
  );

  return { sexe, resultats: lignes.length, colonnes };
}

/** Le portrait d'une compétition, désignée par son chemin « nom/date ». */
export function profilCompetition(chemin: string): ProfilCompetition {
  const meet = listeMeets().find((m) => m.chemin === chemin);
  if (!meet) {
    throw new Error(
      `Infographie de compétition : aucune compétition au chemin « ${chemin} ». ` +
        `Le chemin attendu ressemble à « open-de-la-fournaise/2026-01-18 ».`,
    );
  }

  const lignes = resultatsDuMeet(meet.key);

  const formats: BlocFormat[] = meet.events.map((event) => {
    const duFormat = lignes.filter((r) => r.event === event);
    const sexes = (['M', 'F'] as Sex[])
      .map((sexe) => rangee(sexe, duFormat.filter((r) => r.sex === sexe)))
      .filter((s) => s.resultats > 0);
    const sansCategorie = sexes.every((s) => s.colonnes.every((c) => c.classe === null));
    return { event, resultats: duFormat.length, sexes, sansCategorie };
  });

  const avecCategories = formats.filter((f) => !f.sansCategorie);
  const categories = new Set(
    formats.flatMap((f) =>
      f.sexes.flatMap((s) =>
        s.colonnes.filter((c) => c.classe).map((c) => `${s.sexe}|${c.classe!.id}`),
      ),
    ),
  ).size;

  const equipements = ORDRE_EQUIPEMENT.map((equipement) => ({
    equipement,
    athletes: new Set(
      lignes.filter((r) => equipmentGroup(r.equipment) === equipement).map((r) => r.name),
    ).size,
  })).filter((e) => e.athletes > 0);

  const unAthleteParCarre = formats.every(
    (f) => new Set(lignes.filter((r) => r.event === f.event).map((r) => r.name)).size === f.resultats,
  );

  const noms = new Set(lignes.map((r) => r.name));
  const debuts = premierResultat();
  const debutants =
    meet.date === periode().debut
      ? null
      : [...noms].filter((nom) => debuts.get(nom) === meet.date).length;

  const tranches = lignes.map((r) => bornes(r.ageClass)).filter((b) => b !== null);
  const derniere = tranches.reduce<[number, number] | null>(
    (plus, t) => (!plus || t[1] > plus[1] ? t : plus),
    null,
  );
  const ouvert = derniere !== null && derniere[1] >= TRANCHE_SANS_BORNE;
  const ages =
    derniere !== null
      ? {
          min: Math.min(...tranches.map(([bas]) => bas)),
          max: ouvert ? derniere[0] : derniere[1],
          ouvert,
        }
      : null;

  return {
    meet,
    formats,
    categories,
    colonnesMax: Math.max(
      1,
      ...avecCategories.flatMap((f) => f.sexes.map((s) => s.colonnes.length)),
    ),
    tiret: avecCategories.some((f) =>
      f.sexes.some((s) => s.colonnes.some((c) => c.classe === null)),
    ),
    equipements,
    plusieursFormatsParAthlete:
      formats.reduce((somme, f) => somme + f.resultats, 0) > meet.athletes,
    unAthleteParCarre,
    femmesSeulement: lignes.every((r) => r.sex === 'F'),
    debutants,
    recordsCategorie: lignes.reduce((somme, r) => somme + coupes(r), 0),
    recordsAbsolus: recordsAbsolusParMeet().get(meet.key) ?? 0,
    ages,
  };
}

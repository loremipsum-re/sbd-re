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

export interface EtapeRecord {
  date: string;
  meetName: string;
  meetTown: string;
  holder: string;
  slug: string;
  valueKg: number;
  /** Kilos gagnés sur le record précédent. Zéro pour la première marque. */
  gainKg: number;
  /** Nombre de jours pendant lesquels cette marque a tenu, ou null si elle tient encore. */
  jours: number | null;
}

/**
 * Histoire d'un record : qui l'a détenu, quand, et de combien il l'a amélioré.
 *
 * On parcourt les performances dans l'ordre chronologique en ne retenant que
 * celles qui dépassent la meilleure marque connue à cet instant. C'est bien
 * une succession de records, et non la liste des meilleures performances : une
 * barre supérieure réalisée plus tard efface les suivantes, jamais l'inverse.
 *
 * Nuance : à égalité stricte, la marque la plus ancienne reste le record. On ne
 * retient donc qu'une amélioration réelle.
 */
export function progressionRecord(
  lift: LiftKey,
  sex: Sex,
  weightClassId: string,
  equipement: EquipmentGroup,
  source: Result[] = results,
): EtapeRecord[] {
  const eligibles = source
    .filter(
      (r) =>
        r.sex === sex &&
        r.weightClassKg === weightClassId &&
        equipmentGroup(r.equipment) === equipement &&
        (valeurPour(r, lift) ?? 0) > 0,
    )
    .sort((a, b) => a.date.localeCompare(b.date));

  const etapes: EtapeRecord[] = [];
  let meilleure = 0;

  for (const r of eligibles) {
    const valeur = valeurPour(r, lift) as number;
    if (valeur <= meilleure) continue;

    etapes.push({
      date: r.date,
      meetName: r.meetName,
      meetTown: r.meetTown,
      holder: r.name,
      slug: athleteSlug(r.name),
      valueKg: valeur,
      gainKg: meilleure === 0 ? 0 : Math.round((valeur - meilleure) * 100) / 100,
      jours: null,
    });
    meilleure = valeur;
  }

  // Durée de règne de chaque marque, la dernière étant toujours en cours.
  const JOUR = 86_400_000;
  for (let i = 0; i < etapes.length - 1; i++) {
    const debut = new Date(`${etapes[i].date}T12:00:00Z`).getTime();
    const fin = new Date(`${etapes[i + 1].date}T12:00:00Z`).getTime();
    etapes[i].jours = Math.round((fin - debut) / JOUR);
  }

  return etapes;
}

/**
 * Un record en cours, avec ce qu'il a battu.
 *
 * DEUX SORTES DE RECORDS EN COURS, et le mot n'est pas le même. 27 des 101
 * records actuels sont des PREMIÈRES MARQUES, relevé le 28 octobre 2026 : la
 * catégorie n'avait aucune performance avant ce jour-là, souvent celle d'un
 * athlète seul en équipé. Ceux-là ont été établis, et non battus. Les autres
 * ont dépassé le record de leur catégorie, de `gainKg` kilos.
 */
export interface RecordEnCours extends RecordEntry {
  /**
   * Record de la catégorie la veille de cette marque : la meilleure valeur des
   * jours précédents, même mouvement, même sexe, même équipement. `null` pour
   * une première marque.
   */
  precedentKg: number | null;
  /** Kilos gagnés sur ce record précédent, `null` pour une première marque. */
  gainKg: number | null;
  /**
   * Record de l'île : la barre la plus lourde de son mouvement pour son sexe,
   * toutes catégories et tous équipements confondus. C'est le sens des
   * comptes rendus, et `recordAbsolu` le calcule déjà pour l'infographie
   * d'une compétition : les deux comptes restent d'accord.
   */
  recordIle: boolean;
}

const LEVEES: LiftKey[] = ['squat', 'bench', 'deadlift', 'total'];

let cacheEnCours: RecordEnCours[] | null = null;

/**
 * Tous les records en cours, des quatre levées, avec leur record précédent.
 *
 * LA VEILLE, ET NON LA COMPÉTITION PRÉCÉDENTE. Deux athlètes d'une même
 * catégorie peuvent dépasser l'ancien record le même jour : l'écart se mesure
 * au record qui tenait avant la compétition, pas à celui de l'adversaire du
 * jour. Deux compétitions disputées le même jour ne se départagent pas, faute
 * d'heure dans les données : la seconde ne compte pas comme antérieure.
 *
 * Calculé une seule fois, comme `clesRecords` : l'accueil, la page des records
 * et chaque page de compétition le lisent.
 */
export function recordsEnCours(): RecordEnCours[] {
  if (cacheEnCours) return cacheEnCours;

  const liste: RecordEnCours[] = [];
  for (const lift of LEVEES) {
    const absolus = (['M', 'F'] as Sex[]).map((sexe) => recordAbsolu(lift, sexe));
    for (const rec of recordsPour(lift)) {
      let precedent = 0;
      for (const r of results) {
        if (r.date >= rec.date) continue;
        if (r.sex !== rec.sex || r.weightClassKg !== rec.weightClass.id) continue;
        if (equipmentGroup(r.equipment) !== rec.equipment) continue;
        const valeur = valeurPour(r, lift);
        if (valeur !== null && valeur > precedent) precedent = valeur;
      }
      liste.push({
        ...rec,
        precedentKg: precedent > 0 ? precedent : null,
        gainKg: precedent > 0 ? Math.round((rec.valueKg - precedent) * 100) / 100 : null,
        recordIle: absolus.some(
          (a) =>
            a !== null &&
            a.sex === rec.sex &&
            a.weightClass.id === rec.weightClass.id &&
            a.equipment === rec.equipment,
        ),
      });
    }
  }

  cacheEnCours = liste;
  return liste;
}

/**
 * Records en cours BATTUS dans l'année : une marque antérieure existait dans
 * leur catégorie. Les premières marques en sont écartées, puisque rien n'a
 * été battu.
 */
export function recordsBattusEn(an: string): RecordEnCours[] {
  return recordsEnCours().filter((r) => r.date.startsWith(`${an}-`) && r.gainKg !== null);
}

/**
 * Records en cours établis lors d'une compétition : les coupes de son tableau
 * de résultats, premières marques comprises.
 */
export function recordsDeLaCompetition(date: string, meetName: string): RecordEnCours[] {
  return recordsEnCours().filter((r) => r.date === date && r.meetName === meetName);
}

let cacheParCle: Map<string, { date: string; meetName: string; valeur: number }[]> | null = null;

/** Toutes les marques de chaque catégorie, par mouvement, sexe et équipement. */
function marquesParCle(): Map<string, { date: string; meetName: string; valeur: number }[]> {
  if (cacheParCle) return cacheParCle;
  const index = new Map<string, { date: string; meetName: string; valeur: number }[]>();
  for (const lift of LEVEES) {
    for (const r of results) {
      const valeur = valeurPour(r, lift);
      if (valeur === null || valeur <= 0 || !parseWeightClass(r.weightClassKg)) continue;
      const cle = `${lift}|${r.sex}|${r.weightClassKg}|${equipmentGroup(r.equipment)}`;
      const marques = index.get(cle) ?? [];
      marques.push({ date: r.date, meetName: r.meetName, valeur });
      index.set(cle, marques);
    }
  }
  cacheParCle = index;
  return index;
}

/**
 * Nombre de records de catégorie BATTUS lors d'une compétition, qu'ils
 * tiennent encore ou non : la meilleure marque du jour dépasse le record de
 * la veille. Une catégorie compte une fois, même si deux athlètes y ont
 * dépassé l'ancien record le même jour.
 *
 * Le bandeau d'une compétition ne montre que les records qui tiennent
 * toujours. Ce compte lui permet de dire combien sont tombés depuis : au
 * Reunion Island Meet du 19 juillet 2025, 19 records sont battus, et 8
 * tiennent encore au 28 octobre 2026.
 */
export function nombreRecordsBattusLe(date: string, meetName: string): number {
  let battus = 0;
  for (const marques of marquesParCle().values()) {
    let veille = 0;
    let jour = 0;
    for (const m of marques) {
      if (m.date < date && m.valeur > veille) veille = m.valeur;
      if (m.date === date && m.meetName === meetName && m.valeur > jour) jour = m.valeur;
    }
    if (veille > 0 && jour > veille) battus++;
  }
  return battus;
}

/** Records d'un même athlète, d'une même compétition et d'une même catégorie. */
export interface RecordsAthlete {
  holder: string;
  slug: string;
  sex: Sex;
  weightClass: WeightClass;
  equipment: EquipmentGroup;
  date: string;
  meetName: string;
  meetTown: string;
  /** Dans l'ordre des mouvements : squat, développé couché, soulevé de terre, total. */
  records: RecordEnCours[];
}

/**
 * Regroupe des records par athlète, compétition et catégorie.
 *
 * LA CATÉGORIE ENTRE DANS LA CLÉ. Un athlète peut disputer le full power en
 * raw et le développé couché en équipé le même jour : ses records relèvent
 * alors de deux catégories, qui ont chacune leur ligne.
 *
 * Ordre : les records de l'île d'abord, puis le nombre de records, puis le
 * nom. En tête vient donc l'athlète qui détient le plus de records de l'île,
 * ceux que les comptes rendus mettent en avant.
 */
export function parAthlete(records: RecordEnCours[]): RecordsAthlete[] {
  const groupes = new Map<string, RecordsAthlete>();
  for (const r of records) {
    const cle = [r.slug, r.date, r.meetName, r.sex, r.weightClass.id, r.equipment].join('|');
    const groupe = groupes.get(cle) ?? {
      holder: r.holder,
      slug: r.slug,
      sex: r.sex,
      weightClass: r.weightClass,
      equipment: r.equipment,
      date: r.date,
      meetName: r.meetName,
      meetTown: r.meetTown,
      records: [],
    };
    groupe.records.push(r);
    groupes.set(cle, groupe);
  }
  const ile = (g: RecordsAthlete) => g.records.filter((r) => r.recordIle).length;
  return [...groupes.values()]
    .map((g) => ({
      ...g,
      records: [...g.records].sort((a, b) => LEVEES.indexOf(a.lift) - LEVEES.indexOf(b.lift)),
    }))
    .sort(
      (a, b) =>
        ile(b) - ile(a) ||
        b.records.length - a.records.length ||
        a.holder.localeCompare(b.holder, 'fr'),
    );
}

/** Types d'équipement réellement présents dans les données, pour ne pas afficher de sections vides. */
export function equipementsPresents(source: Result[] = results): EquipmentGroup[] {
  const vus = new Set<EquipmentGroup>();
  for (const r of source) vus.add(equipmentGroup(r.equipment));
  return ['raw', 'wraps', 'equipped'].filter((e) =>
    vus.has(e as EquipmentGroup),
  ) as EquipmentGroup[];
}

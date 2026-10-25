/**
 * Les chiffres de l'infographie des formats de compétition.
 *
 * TOUT EST LU DANS LES DONNÉES ET DANS LES RÈGLES DU SITE, rien n'est écrit en
 * dur. Les compétitions viennent de `listeMeets`, le classement de
 * `fullPowerResults`, les records de `recordsPour`. L'infographie suit donc
 * les pages qu'elle explique, d'une mise à jour à l'autre.
 *
 * UNE COMPÉTITION PEUT MÊLER PLUSIEURS FORMATS. Elle compte alors une fois
 * dans chacun, si bien que la somme des compétitions par format dépasse le
 * nombre de compétitions. Un résultat, lui, appartient à un seul format : un
 * athlète engagé en full power et au développé couché le même jour en compte
 * deux.
 */

import { LIFTS, equipmentGroup, parseWeightClass } from './categories';
import { fullPowerResults, results } from './data';
import { libelleEvent, listeMeets } from './meets';
import { recordsPour, type RecordEntry } from './records';
import type { EventCode, LiftKey, Result } from './types';

/** Même ordre que les étiquettes de `listeMeets` : le full power d'abord. */
const ORDRE: EventCode[] = ['SBD', 'B', 'S', 'D'];

/** Les mouvements disputés dans chaque format, selon lib/types.ts. */
const MOUVEMENTS: Record<EventCode, LiftKey[]> = {
  SBD: ['squat', 'bench', 'deadlift'],
  B: ['bench'],
  S: ['squat'],
  D: ['deadlift'],
};

/** Le format nommé dans une phrase, comme le fait scripts/update-data.mjs. */
const EN_PHRASE: Record<EventCode, string> = {
  SBD: 'full power',
  B: 'développé couché seul',
  S: 'squat seul',
  D: 'soulevé de terre seul',
};

export interface Format {
  code: EventCode;
  /** Le libellé des étiquettes de la liste des compétitions. */
  libelle: string;
  enPhrase: string;
  mouvements: LiftKey[];
  /** Compétitions où ce format a été disputé. */
  competitions: number;
  resultats: number;
  /** Résultats sans catégorie de poids, que les records écartent. */
  sansCategorie: number;
  /** Ce format alimente le classement, le total et les Dots. */
  auClassement: boolean;
}

export interface Melange {
  formats: EventCode[];
  competitions: number;
}

export interface RecordsDuMouvement {
  lift: LiftKey;
  libelle: string;
  total: number;
  /** Records détenus par format d'origine, sans les formats à zéro. */
  parFormat: { code: EventCode; enPhrase: string; records: number }[];
}

/**
 * La charge qu'un record retient pour une ligne. C'est la règle de
 * `valeurPour` dans lib/records.ts, qui ne l'exporte pas : le total en full
 * power seulement, chaque barre dans tous les formats.
 */
function valeur(r: Result, lift: LiftKey): number | null {
  if (lift === 'total') return r.event === 'SBD' ? r.totalKg : null;
  if (lift === 'squat') return r.bestSquatKg;
  if (lift === 'bench') return r.bestBenchKg;
  return r.bestDeadliftKg;
}

/**
 * Le format de la ligne qui a produit un record.
 *
 * LA PREMIÈRE LIGNE QUI CORRESPOND, dans l'ordre des données. C'est celle que
 * `recordsPour` a retenue, puisqu'il garde la première ligne qui atteint la
 * meilleure charge. Le cas compte : un athlète engagé dans deux formats le
 * même jour peut porter la même barre sur deux lignes.
 */
function formatDuRecord(rec: RecordEntry): EventCode | undefined {
  return results.find(
    (r) =>
      r.name === rec.holder &&
      r.date === rec.date &&
      r.meetName === rec.meetName &&
      r.sex === rec.sex &&
      r.weightClassKg === rec.weightClass.id &&
      equipmentGroup(r.equipment) === rec.equipment &&
      valeur(r, rec.lift) === rec.valueKg,
  )?.event;
}

export function statistiquesFormats() {
  const meets = listeMeets();
  const auClassement = new Set(fullPowerResults.map((r) => r.event));

  const formats: Format[] = ORDRE.map((code) => {
    const lignes = results.filter((r) => r.event === code);
    return {
      code,
      libelle: libelleEvent(code),
      enPhrase: EN_PHRASE[code],
      mouvements: MOUVEMENTS[code],
      competitions: meets.filter((m) => m.events.includes(code)).length,
      resultats: lignes.length,
      sansCategorie: lignes.filter((r) => !parseWeightClass(r.weightClassKg)).length,
      auClassement: auClassement.has(code),
    };
  });

  // Les combinaisons de formats, telles que les étiquettes de la liste les
  // affichent. Un seul format d'abord, puis les mélanges.
  const parCombinaison = new Map<string, Melange>();
  for (const m of meets) {
    const cle = m.events.join('+');
    const entree = parCombinaison.get(cle) ?? { formats: m.events, competitions: 0 };
    entree.competitions++;
    parCombinaison.set(cle, entree);
  }
  const melanges = [...parCombinaison.values()].sort(
    (a, b) => a.formats.length - b.formats.length || b.competitions - a.competitions,
  );

  const records: RecordsDuMouvement[] = LIFTS.map((lift) => {
    const liste = recordsPour(lift.id);
    const comptes = new Map<EventCode, number>();
    for (const rec of liste) {
      const code = formatDuRecord(rec);
      if (code) comptes.set(code, (comptes.get(code) ?? 0) + 1);
    }
    return {
      lift: lift.id,
      libelle: lift.label,
      total: liste.length,
      parFormat: ORDRE.filter((code) => (comptes.get(code) ?? 0) > 0).map((code) => ({
        code,
        enPhrase: EN_PHRASE[code],
        records: comptes.get(code) ?? 0,
      })),
    };
  });

  return {
    competitions: meets.length,
    resultats: results.length,
    formats,
    melanges,
    records,
  };
}

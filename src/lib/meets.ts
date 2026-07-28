/**
 * Liste des compétitions réunionnaises, reconstruite à partir des résultats.
 *
 * Pas de fichier de meets séparé : les compétitions se déduisent entièrement
 * des lignes de résultats. Une seule source de données, donc aucun risque que
 * les deux se contredisent.
 */

import { results } from './data';
import { meetKey, meetSlug } from './slug';
import type { EventCode, Result } from './types';

export interface Meet {
  key: string;
  /** Identifiant d'URL, de la forme « 2025-07-19-reunion-island-meet ». */
  slug: string;
  date: string;
  meetName: string;
  meetTown: string;
  /** Formats disputés lors de cette compétition. */
  events: EventCode[];
  athletes: number;
  resultats: number;
}

export function listeMeets(source: Result[] = results): Meet[] {
  const parMeet = new Map<string, { meet: Meet; noms: Set<string>; events: Set<EventCode> }>();

  for (const r of source) {
    const cle = meetKey(r.date, r.meetName);
    let entree = parMeet.get(cle);
    if (!entree) {
      entree = {
        meet: {
          key: cle,
          slug: meetSlug(r.date, r.meetName),
          date: r.date,
          meetName: r.meetName,
          meetTown: r.meetTown,
          events: [],
          athletes: 0,
          resultats: 0,
        },
        noms: new Set(),
        events: new Set(),
      };
      parMeet.set(cle, entree);
    }
    entree.meet.resultats++;
    entree.noms.add(r.name);
    entree.events.add(r.event);
  }

  const meets: Meet[] = [];
  for (const { meet, noms, events } of parMeet.values()) {
    meet.athletes = noms.size;
    // Ordre stable : le full power d'abord quand plusieurs formats coexistent.
    meet.events = (['SBD', 'B', 'S', 'D'] as EventCode[]).filter((e) => events.has(e));
    meets.push(meet);
  }

  // Du plus récent au plus ancien : c'est la dernière compétition qui intéresse.
  return meets.sort((a, b) => b.date.localeCompare(a.date));
}

/** Libellé lisible d'un format de compétition. */
export function libelleEvent(event: EventCode): string {
  const libelles: Record<EventCode, string> = {
    SBD: 'Full power',
    B: 'Développé couché',
    S: 'Squat',
    D: 'Soulevé de terre',
  };
  return libelles[event] ?? event;
}

/**
 * Résultats d'une compétition, classés comme sur le plateau : les places
 * numérotées d'abord, puis les athlètes invités, et à total égal le plus léger
 * devant, comme le veut la règle en force athlétique.
 */
export function resultatsDuMeet(cle: string, source: Result[] = results): Result[] {
  return source
    .filter((r) => meetKey(r.date, r.meetName) === cle)
    .sort((a, b) => {
      const pa = Number.parseInt(a.place, 10);
      const pb = Number.parseInt(b.place, 10);
      const va = Number.isFinite(pa) ? pa : 999;
      const vb = Number.isFinite(pb) ? pb : 999;
      return (
        va - vb ||
        (b.totalKg ?? 0) - (a.totalKg ?? 0) ||
        (a.bodyweightKg ?? 0) - (b.bodyweightKg ?? 0)
      );
    });
}

/** Compétitions groupées par année, pour un affichage en sections. */
export function meetsParAnnee(source: Result[] = results): { annee: string; meets: Meet[] }[] {
  const groupes = new Map<string, Meet[]>();
  for (const meet of listeMeets(source)) {
    const annee = meet.date.slice(0, 4);
    const liste = groupes.get(annee);
    if (liste) liste.push(meet);
    else groupes.set(annee, [meet]);
  }
  return [...groupes.entries()]
    .map(([annee, meets]) => ({ annee, meets }))
    .sort((a, b) => b.annee.localeCompare(a.annee));
}

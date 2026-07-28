/**
 * Liste des compétitions réunionnaises, reconstruite à partir des résultats.
 *
 * Pas de fichier de meets séparé : les compétitions se déduisent entièrement
 * des lignes de résultats. Une seule source de données, donc aucun risque que
 * les deux se contredisent.
 */

import { results } from './data';
import { meetKey } from './slug';
import type { EventCode, Result } from './types';

export interface Meet {
  key: string;
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
    // Ordre stable : le full power d'abord quand les deux formats coexistent.
    meet.events = (['SBD', 'B'] as EventCode[]).filter((e) => events.has(e));
    meets.push(meet);
  }

  // Du plus récent au plus ancien : c'est la dernière compétition qui intéresse.
  return meets.sort((a, b) => b.date.localeCompare(a.date));
}

/** Libellé lisible d'un format de compétition. */
export function libelleEvent(event: EventCode): string {
  return event === 'SBD' ? 'Full power' : 'Développé couché';
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

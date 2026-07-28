/**
 * Point d'entrée unique vers les données officielles.
 *
 * Tout le site importe depuis ce fichier, jamais directement depuis le JSON.
 * Ça donne un seul endroit où typer les données, et un seul endroit à corriger
 * si le format du fichier généré évolue.
 *
 * L'import est résolu au BUILD : le JSON ne part jamais au navigateur, seul le
 * HTML calculé à partir de lui est publié.
 */

import brut from '../data/results.json';
import type { Result } from './types';

export const results = brut as Result[];

/** Uniquement le full power : les seules lignes dont le total est comparable. */
export const fullPowerResults = results.filter((r) => r.event === 'SBD');

/** Période réellement couverte par les données. */
export function periode(): { debut: string; fin: string } {
  const dates = results.map((r) => r.date).sort();
  return { debut: dates[0] ?? '', fin: dates[dates.length - 1] ?? '' };
}

export function nombreAthletes(): number {
  return new Set(results.map((r) => r.name)).size;
}

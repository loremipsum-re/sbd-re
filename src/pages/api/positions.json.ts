/**
 * Où se placerait un membre au classement officiel de La Réunion.
 *
 * Demande de l'auteur du 7 novembre 2026 : l'accueil du membre lui dit où ses
 * barres le placeraient. Par sexe, catégorie et équipement, la meilleure
 * marque de chaque athlète, triée de la plus forte à la plus faible : le
 * total, du full power seul (CLAUDE.md, « le total n'a de sens qu'en full
 * power »), et chaque mouvement, de toutes les compétitions où il a été fait.
 *
 * Même découpage que /simulateur/ : un athlète compte dans chaque catégorie
 * et chaque équipement où il a concouru, avec sa meilleure marque DANS cette
 * catégorie. Des nombres seulement, aucun nom : la page n'en a pas besoin.
 *
 * Généré avec le site, comme /api/salles.json. Les résultats hors de l'île
 * n'y entrent pas : ils ne sont pas dans `results`.
 */
import { equipmentGroup } from '../../lib/categories';
import { fullPowerResults, results } from '../../lib/data';
import { meilleurTotalParAthlete } from '../../lib/rankings';
import type { Result } from '../../lib/types';

type Champ = 'bestSquatKg' | 'bestBenchKg' | 'bestDeadliftKg';

const cleDe = (r: Result) => `${r.sex}|${r.weightClassKg}|${equipmentGroup(r.equipment)}`;

function grouper(lignes: Result[]): Map<string, Result[]> {
  const groupes = new Map<string, Result[]>();
  for (const r of lignes) {
    if (!r.weightClassKg) continue;
    const groupe = groupes.get(cleDe(r)) ?? [];
    groupe.push(r);
    groupes.set(cleDe(r), groupe);
  }
  return groupes;
}

/** La meilleure marque de chaque athlète pour un mouvement, triée. */
function meilleuresMarques(lignes: Result[], champ: Champ): number[] {
  const parAthlete = new Map<string, number>();
  for (const r of lignes) {
    const kg = r[champ];
    if (kg === null || kg <= 0) continue;
    parAthlete.set(r.name, Math.max(parAthlete.get(r.name) ?? 0, kg));
  }
  return [...parAthlete.values()].sort((a, b) => b - a);
}

export function GET(): Response {
  const positions: Record<string, Record<string, number[]>> = {};

  for (const [cle, lignes] of grouper(results)) {
    positions[cle] = {
      squat: meilleuresMarques(lignes, 'bestSquatKg'),
      bench: meilleuresMarques(lignes, 'bestBenchKg'),
      deadlift: meilleuresMarques(lignes, 'bestDeadliftKg'),
      total: [],
    };
  }
  for (const [cle, lignes] of grouper(fullPowerResults)) {
    positions[cle].total = meilleurTotalParAthlete(lignes, false)
      .map((a) => a.totalKg)
      .sort((a, b) => b - a);
  }

  return new Response(JSON.stringify(positions), {
    headers: { 'Content-Type': 'application/json' },
  });
}

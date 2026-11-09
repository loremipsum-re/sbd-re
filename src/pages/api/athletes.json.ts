/**
 * Les fiches d'athlètes du site, adresse et nom : { "jean-dupont": "Jean Dupont" }.
 *
 * Généré avec le site, comme /api/salles.json. Le serveur y vérifie qu'une
 * fiche réclamée par un membre existe, et y lit le nom à afficher sur son
 * profil et dans la file de l'admin (public/api/lib/revendications.php). Les
 * noms sont déjà publics, chaque fiche les porte.
 *
 * Mêmes fiches que src/pages/athlete/[slug].astro : celles des résultats de
 * l'île, par athleteSlug().
 */
import { results } from '../../lib/data';
import { athleteSlug } from '../../lib/slug';

export function GET(): Response {
  const fiches: Record<string, string> = {};
  for (const r of results) fiches[athleteSlug(r.name)] = r.name;
  return new Response(JSON.stringify(fiches), { headers: { 'Content-Type': 'application/json; charset=utf-8' } });
}

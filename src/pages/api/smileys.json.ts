/**
 * Les smileys d'époque, pour le PHP qui rend le forum (lib/forum.php). Une
 * seule source, src/lib/smileys.ts, comme /api/salles.json pour les profils.
 */
import { SMILEYS } from '../../lib/smileys';

export function GET(): Response {
  return new Response(JSON.stringify(SMILEYS), { headers: { 'Content-Type': 'application/json' } });
}

/**
 * Les salles actives, pour le PHP qui rend les profils de membres.
 *
 * Un membre choisit sa salle dans /compte/ par son identifiant de fiche. Le
 * profil, rendu par public/api/profil-page.php, a besoin du nom et de la
 * commune : il les lit ici, généré avec le site depuis la même collection
 * que /salles/. Une seule source, et rien à recopier côté serveur.
 *
 * Données publiques, déjà affichées sur /salles/.
 */
import { getCollection } from 'astro:content';

export async function GET(): Promise<Response> {
  const salles = await getCollection('salles', (s) => s.data.actif);

  return new Response(
    JSON.stringify(
      Object.fromEntries(salles.map((s) => [s.id, { nom: s.data.nom, commune: s.data.commune }])),
    ),
    { headers: { 'Content-Type': 'application/json' } },
  );
}

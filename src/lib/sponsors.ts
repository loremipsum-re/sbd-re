/**
 * Choix des partenaires à afficher, et les règles qui l'encadrent.
 *
 * Le point important tient en une phrase : ce fichier ne rend jamais plus
 * d'UN partenaire pour un emplacement de contenu. La règle est appliquée ici,
 * une fois, plutôt que confiée à la vigilance de chaque gabarit.
 */
import { getCollection, type CollectionEntry } from 'astro:content';

export type Sponsor = CollectionEntry<'sponsors'>;
export type Emplacement = 'competition' | 'article' | 'pied';

let cache: Sponsor[] | null = null;

async function actifs(): Promise<Sponsor[]> {
  if (!cache) {
    cache = (await getCollection('sponsors')).filter((s) => s.data.actif);
  }
  return cache;
}

/**
 * Empreinte stable d'une chaîne, pour répartir les partenaires sans hasard.
 *
 * Le tirage doit être STABLE : un site statique se régénère chaque mois, et un
 * partenaire qui saute d'une page à l'autre à chaque build serait invérifiable
 * pour lui comme pour nous. La même page rend donc toujours le même partenaire,
 * tant que la liste ne change pas.
 */
function empreinte(clef: string): number {
  let h = 0;
  for (let i = 0; i < clef.length; i++) {
    h = (h * 31 + clef.charCodeAt(i)) | 0;
  }
  return Math.abs(h);
}

/**
 * LE partenaire d'une page, ou `undefined` s'il n'y en a aucun.
 *
 * Rend un seul élément, jamais une liste : c'est ce qui garantit la règle du
 * « un seul emplacement par page » quoi que fasse le gabarit appelant.
 *
 * @param emplacement Le type d'emplacement demandé.
 * @param clef        Identifiant de la page, qui fixe le tirage.
 */
export async function partenaireDeLaPage(
  emplacement: Exclude<Emplacement, 'pied'>,
  clef: string,
): Promise<Sponsor | undefined> {
  const eligibles = (await actifs())
    .filter((s) => s.data.emplacements.includes(emplacement))
    .sort((a, b) => a.id.localeCompare(b.id));

  if (eligibles.length === 0) return undefined;
  return eligibles[empreinte(clef) % eligibles.length];
}

/** Les partenaires du bandeau de pied de page, dans l'ordre choisi. */
export async function partenairesDuPied(): Promise<Sponsor[]> {
  return (await actifs())
    .filter((s) => s.data.emplacements.includes('pied'))
    .sort((a, b) => a.data.ordre - b.data.ordre || a.data.nom.localeCompare(b.data.nom, 'fr'));
}

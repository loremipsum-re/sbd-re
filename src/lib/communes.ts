/**
 * Les communes organisatrices : celles où s'est tenue au moins une
 * compétition présente dans les données.
 *
 * LA COMMUNE EST CELLE QU'ÉCRIT OPENPOWERLIFTING, champ `meetTown`. Elle sert
 * ici à regrouper des compétitions déjà reconnues comme réunionnaises, jamais
 * à en inclure une : Saint-Denis, Saint-Louis et Saint-Paul existent aussi en
 * métropole (CLAUDE.md, invariants). Une compétition sans commune n'entre
 * dans aucune page.
 */
import { results } from './data';
import { listeMeets, type Meet } from './meets';
import { meetKey, meetNameSlug } from './slug';

export interface Commune {
  nom: string;
  /** « L'Étang-Salé » donne « l-etang-sale ». */
  slug: string;
  /** Ses compétitions, de la plus récente à la plus ancienne. */
  competitions: Meet[];
  athletes: number;
  resultats: number;
  /** La première et la dernière compétition, au format AAAA-MM-JJ. */
  premiere: string;
  derniere: string;
  /** Les clés des compétitions, pour retrouver leurs résultats ou records. */
  cles: Set<string>;
}

/**
 * « À Saint-Joseph », mais « au Tampon » : l'article d'une commune se
 * contracte avec la préposition. Le Tampon figure dans les données.
 */
export function aLaCommune(commune: string): string {
  if (commune.startsWith('Le ')) return `au ${commune.slice(3)}`;
  if (commune.startsWith('Les ')) return `aux ${commune.slice(4)}`;
  return `à ${commune}`;
}

/** Les communes, de celle qui accueille le plus de compétitions à la moins fournie. */
export function listeCommunes(): Commune[] {
  const parNom = new Map<string, Meet[]>();
  for (const m of listeMeets()) {
    if (!m.meetTown) continue;
    const liste = parNom.get(m.meetTown) ?? [];
    liste.push(m);
    parNom.set(m.meetTown, liste);
  }

  const vus = new Map<string, string>();
  const communes = [...parNom].map(([nom, liste]) => {
    const slug = meetNameSlug(nom);
    // Deux graphies qui donneraient la même adresse fusionneraient deux pages.
    const deja = vus.get(slug);
    if (deja !== undefined) {
      throw new Error(`Deux communes pour l'adresse « ${slug} » : « ${deja} » et « ${nom} ».`);
    }
    vus.set(slug, nom);

    const cles = new Set(liste.map((m) => m.key));
    const siens = results.filter((r) => cles.has(meetKey(r.date, r.meetName)));
    const dates = liste.map((m) => m.date).sort();
    return {
      nom,
      slug,
      competitions: [...liste].sort((a, b) => b.date.localeCompare(a.date)),
      athletes: new Set(siens.map((r) => r.name)).size,
      resultats: siens.length,
      premiere: dates[0],
      derniere: dates[dates.length - 1],
      cles,
    };
  });

  return communes.sort(
    (a, b) => b.competitions.length - a.competitions.length || a.nom.localeCompare(b.nom, 'fr'),
  );
}

/**
 * Le liant entre les articles et le reste du site.
 *
 * Tout ce qui relie une actualité à une compétition ou à un athlète passe par
 * ici, et dans les DEUX sens : l'article pointe vers la compétition, et la
 * compétition retrouve les articles qui en parlent. Une seule déclaration dans
 * l'en-tête du Markdown suffit à fabriquer les deux liens.
 */
import { getCollection, type CollectionEntry } from 'astro:content';
import { listeMeets } from './meets';
import { athleteSlug } from './slug';

export type Article = CollectionEntry<'actualites'>;

/**
 * Les articles publiés, du plus récent au plus ancien.
 *
 * Les brouillons disparaissent en production et restent visibles en
 * développement : on écrit un article en le voyant, sans le publier.
 */
export async function articles(): Promise<Article[]> {
  const tous = await getCollection('actualites');
  return tous
    .filter((a) => import.meta.env.DEV || !a.data.brouillon)
    .sort((a, b) => b.data.date.getTime() - a.data.date.getTime());
}

/**
 * Vérifie que chaque `competition` déclarée existe vraiment.
 *
 * Une faute de frappe dans un chemin produirait un lien mort, et un lien mort
 * sur un site statique ne se voit qu'une fois en ligne. La génération s'arrête
 * donc ici, ce qui est le seul moment où l'erreur coûte encore peu.
 */
export function verifierCompetitions(liste: Article[]): void {
  const connus = new Set(listeMeets().map((m) => m.chemin));
  const fautives = liste
    .filter((a) => a.data.competition && !connus.has(a.data.competition))
    .map((a) => `  ${a.id} → « ${a.data.competition} »`);

  if (fautives.length > 0) {
    throw new Error(
      `Actualités : ${fautives.length} article(s) pointent vers une compétition ` +
        `inexistante.\n${fautives.join('\n')}\n` +
        `Le chemin attendu ressemble à « open-de-la-fournaise/2026-01-18 ».`,
    );
  }
}

/** La compétition dont parle un article, ou `undefined`. */
export function competitionDe(article: Article) {
  if (!article.data.competition) return undefined;
  return listeMeets().find((m) => m.chemin === article.data.competition);
}

/** Les articles qui parlent d'une compétition donnée. C'est le lien RETOUR. */
export async function articlesDeLaCompetition(chemin: string): Promise<Article[]> {
  return (await articles()).filter((a) => a.data.competition === chemin);
}

/** Les articles qui citent un athlète. */
export async function articlesDeLAthlete(nom: string): Promise<Article[]> {
  return (await articles()).filter((a) => a.data.athletes.includes(nom));
}

/**
 * Articles proches, pour la fin d'un article.
 *
 * Le rapprochement se fait d'abord par compétition, ce qui est le lien le plus
 * fort, puis par athlète cité. À défaut, les plus récents : mieux vaut proposer
 * une lecture que laisser une impasse.
 */
export async function articlesLies(article: Article, combien = 3): Promise<Article[]> {
  const tous = (await articles()).filter((a) => a.id !== article.id);
  const score = (a: Article) => {
    let n = 0;
    if (article.data.competition && a.data.competition === article.data.competition) n += 10;
    n += a.data.athletes.filter((x) => article.data.athletes.includes(x)).length;
    return n;
  };
  return tous
    .map((a) => ({ a, n: score(a) }))
    .sort((x, y) => y.n - x.n || y.a.data.date.getTime() - x.a.data.date.getTime())
    .slice(0, combien)
    .map((x) => x.a);
}

/** Adresse de la fiche d'un athlète cité. */
export function cheminAthlete(nom: string): string {
  return `/athlete/${athleteSlug(nom)}/`;
}

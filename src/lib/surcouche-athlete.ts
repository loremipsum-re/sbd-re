/**
 * Ce que le CMS ajoute sur une fiche d'athlète.
 *
 * La règle qui gouverne ce fichier : les PERFORMANCES ne se modifient pas.
 * Elles viennent d'OpenPowerlifting, elles sont réécrites chaque mois, et un
 * classement officiel qui se retouche à la main perd ce qui fait sa valeur.
 *
 * Tout ce qui vit ici est donc ADDITIF : une photo, une présentation, des
 * liens. Rien qui touche à un kilo soulevé.
 */
import { getCollection, render, type CollectionEntry } from 'astro:content';
import { athleteSlug } from './slug';

export type Surcouche = CollectionEntry<'athletes'>;

/**
 * Les surcouches, rangées par slug d'athlète.
 *
 * Lue une fois et mémorisée : la fiche d'athlète est générée 312 fois, et
 * relire la collection à chaque page coûterait pour rien.
 */
let cache: Map<string, Surcouche> | null = null;

export async function surcouches(): Promise<Map<string, Surcouche>> {
  if (cache) return cache;
  const toutes = await getCollection('athletes');
  cache = new Map(toutes.map((s) => [s.data.slug, s]));
  return cache;
}

/** La surcouche d'un athlète, par son nom. `undefined` s'il n'en a pas. */
export async function surcoucheDe(nom: string): Promise<Surcouche | undefined> {
  return (await surcouches()).get(athleteSlug(nom));
}

/**
 * Vérifie que chaque surcouche vise un athlète existant.
 *
 * Le CMS pose le slug depuis une liste tirée des données, donc le cas ne
 * devrait pas se produire. Il le devient après une mise à jour mensuelle qui
 * ferait disparaître un athlète : la surcouche resterait, orpheline et
 * invisible. Mieux vaut le dire à la génération que le découvrir six mois plus
 * tard.
 */
export async function verifierSurcouches(nomsConnus: Set<string>): Promise<void> {
  const slugsConnus = new Set([...nomsConnus].map((n) => athleteSlug(n)));
  const orphelines = [...(await surcouches()).keys()].filter((s) => !slugsConnus.has(s));

  if (orphelines.length > 0) {
    throw new Error(
      `Surcouches d'athlètes : ${orphelines.length} fiche(s) visent un athlète ` +
        `absent des données.\n  ${orphelines.join('\n  ')}\n` +
        `Soit le nom a changé chez OpenPowerlifting, soit l'athlète n'a plus de ` +
        `résultat. Corrige le champ « Athlète » dans le CMS, ou supprime la fiche.`,
    );
  }
}

/** Rend la présentation Markdown d'une surcouche, si elle en a une. */
export async function presentationDe(s: Surcouche) {
  const { Content } = await render(s);
  return Content;
}

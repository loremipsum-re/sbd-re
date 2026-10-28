/**
 * Choix des partenaires à afficher, et les règles qui l'encadrent.
 *
 * Le point important tient en une phrase : ce fichier ne rend jamais plus
 * d'UN partenaire dans le contenu d'une page. La règle est appliquée ici, une
 * fois, plutôt que confiée à la vigilance de chaque gabarit.
 *
 * LES QUATRE RÈGLES, et ce qui les tient.
 *   1. UN SEUL emplacement de contenu par page. `partenaireDeLaPage` rend un
 *      partenaire, jamais une liste. Et une page qui la demande deux fois
 *      arrête la génération : voir `pagesServies` plus bas.
 *   2. JAMAIS dans une donnée, ni entre deux lignes, ni dans un tableau.
 *   3. JAMAIS au-dessus du contenu.
 *   4. JAMAIS sur une fiche d'athlète, ni dans le contenu ni au pied de page :
 *      une marque y prêterait à l'athlète une caution qu'il n'a pas donnée.
 *      `partenaireDeLaPage` arrête la génération si une fiche la demande, et
 *      `partenairesDuPied` n'y rend aucun logo.
 * Les règles 2 et 3 tiennent à la table des places, lib/formats-partenaires.ts :
 * chaque place y est définie APRÈS le contenu de sa page, et le gabarit
 * l'appelle en une ligne, en fin de contenu.
 *
 * LE BANDEAU DU PIED DE PAGE EST À PART. Il ne compte pas dans la règle 1 :
 * il vient après tout le reste, sur toutes les pages sauf les fiches
 * d'athlète. Une page de compétition peut donc montrer une bannière après ses
 * résultats, puis le bandeau.
 */
import { getCollection, type CollectionEntry } from 'astro:content';
import { EMPLACEMENTS, type IdEmplacement } from './formats-partenaires';

export type Sponsor = CollectionEntry<'sponsors'>;

/** Les fiches d'athlète, écartées de toute place : voir la règle 4. */
function estFicheAthlete(chemin: string): boolean {
  return chemin.startsWith('/athlete/');
}

/** Ce que les deux fonctions lisent de la page : `Astro` suffit, il porte les deux. */
interface PageAppelante {
  request: Request;
  url: URL;
}

/**
 * Ce qu'une place de contenu affiche, prêt à l'emploi. Le format vient de la
 * place, et chaque pièce que ce format demande est là : le schéma de la
 * collection l'a vérifié.
 */
export type PartenaireAffiche =
  | { format: 'banniere'; nom: string; url: string; image: string }
  | { format: 'carte'; nom: string; url: string; logo: string; accroche: string };

/** Un logo du bandeau de pied de page. */
export interface LogoDuPied {
  nom: string;
  url: string;
  logo: string;
}

let cache: Promise<Sponsor[]> | null = null;

function actifs(): Promise<Sponsor[]> {
  cache ??= getCollection('sponsors').then((liste) => liste.filter((s) => s.data.actif));
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
 * Une pièce que le schéma garantit. Son absence trahirait un schéma contourné,
 * et la génération s'arrête plutôt que d'afficher une place à moitié vide.
 */
function exiger<T>(valeur: T | undefined, partenaire: Sponsor, piece: string): T {
  if (valeur === undefined) {
    throw new Error(
      `Partenaires : « ${partenaire.data.nom} » n'a pas de ${piece}, que sa place demande. ` +
        `Voir le schéma de la collection dans src/content.config.ts.`,
    );
  }
  return valeur;
}

/**
 * Les pages déjà servies pendant leur rendu, rangées par requête.
 *
 * C'est ce qui fait respecter la règle 1 quoi que fasse un gabarit. Chaque
 * page est rendue avec sa propre requête, à la génération comme au serveur de
 * développement : un second appel sur la même requête est donc une seconde
 * place sur la même page. La table ne retient rien au-delà du rendu.
 */
const pagesServies = new WeakMap<Request, IdEmplacement>();

/**
 * LE partenaire d'une place de contenu, ou `undefined` s'il n'y en a aucun.
 *
 * Rend un seul élément, jamais une liste. Le tirage dépend de l'adresse de la
 * page : la même page montre toujours le même partenaire.
 *
 * Les deux refus arrêtent la génération, collection vide comprise : une place
 * posée sur une fiche d'athlète ou en double se voit dès le premier essai, et
 * non le jour où un partenaire arrive.
 *
 * @param emplacement La place, telle que la nomme lib/formats-partenaires.ts.
 * @param page        La page qui l'appelle : `Astro` suffit.
 */
export async function partenaireDeLaPage(
  emplacement: IdEmplacement,
  page: PageAppelante,
): Promise<PartenaireAffiche | undefined> {
  const chemin = page.url.pathname;
  if (estFicheAthlete(chemin)) {
    throw new Error(
      `Partenaires : ${chemin} est une fiche d'athlète, qui n'a aucune place. ` +
        `Retirer <Partenaire emplacement="${emplacement}" /> de son gabarit.`,
    );
  }
  const deja = pagesServies.get(page.request);
  if (deja !== undefined) {
    throw new Error(
      `Partenaires : ${chemin} appelle deux places de contenu, « ${deja} » puis ` +
        `« ${emplacement} ». Une page en porte une seule.`,
    );
  }
  pagesServies.set(page.request, emplacement);

  const eligibles = (await actifs())
    .filter((s) => s.data.emplacements.includes(emplacement))
    .sort((a, b) => a.id.localeCompare(b.id));

  if (eligibles.length === 0) return undefined;
  const partenaire = eligibles[empreinte(chemin) % eligibles.length];
  const { nom, url } = partenaire.data;

  return EMPLACEMENTS[emplacement].format === 'banniere'
    ? { format: 'banniere', nom, url, image: exiger(partenaire.data.banniere, partenaire, 'bannière') }
    : {
        format: 'carte',
        nom,
        url,
        logo: exiger(partenaire.data.logo, partenaire, 'logo'),
        accroche: exiger(partenaire.data.accroche, partenaire, 'accroche'),
      };
}

/**
 * Les partenaires du bandeau de pied de page, dans l'ordre choisi.
 *
 * Aucun sur une fiche d'athlète : la règle 4 vaut aussi pour le bandeau. Le
 * pied de page étant commun à toutes les pages, c'est la page appelante qui
 * dit où l'on est.
 *
 * @param page La page en cours de rendu : `Astro` suffit.
 */
export async function partenairesDuPied(page: PageAppelante): Promise<LogoDuPied[]> {
  if (estFicheAthlete(page.url.pathname)) return [];
  return (await actifs())
    .filter((s) => s.data.emplacements.includes('pied'))
    .sort((a, b) => a.data.ordre - b.data.ordre || a.data.nom.localeCompare(b.data.nom, 'fr'))
    .map((s) => ({ nom: s.data.nom, url: s.data.url, logo: exiger(s.data.logo, s, 'logo') }));
}

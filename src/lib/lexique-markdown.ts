/**
 * Le lexique dans les articles : la première apparition de chaque terme est
 * soulignée, avec sa définition d'une phrase en bulle.
 *
 * C'EST UN GREFFON DE SÄTTERI, le moteur Markdown d'Astro 7, branché par
 * astro.config.mjs. Il tourne à la GÉNÉRATION, article par article, sur l'arbre
 * HTML produit par le Markdown (le « hast », l'équivalent d'un greffon rehype).
 * Le visiteur reçoit donc du HTML déjà souligné, et rien ne s'écrit à la main
 * dans les articles : un terme du glossaire employé demain dans un nouvel
 * article y sera souligné sans que personne y pense.
 *
 * LE BALISAGE EST CELUI DE Terme.astro, au caractère près, pour que la page et
 * l'article se ressemblent et se comportent pareil. Le style vit dans
 * tokens.css, le comportement dans bulles-lexique.ts.
 *
 * UNE SEULE FOIS PAR TERME ET PAR ARTICLE. Souligner chaque occurrence ferait
 * de la page une suite de pointillés : « record » revient soixante-six fois
 * dans la prose du site. Les termes déjà vus vivent dans `ctx.data`, le sac de
 * données que Sätteri remet à zéro pour chaque document.
 *
 * LE CHAPEAU D'ABORD, choix de l'auteur du 28 octobre 2026. Le chapeau est le
 * paragraphe sous le titre, tiré du champ `description` du frontmatter. À la
 * relecture du même jour, dix articles sur quinze y employaient un terme pour
 * la première fois. Un terme du chapeau y est souligné, une seule fois pour
 * tout l'article. Les autres le sont au premier emploi du corps, comme avant.
 *
 * LE CHAPEAU SE DÉCOUPE ICI, AVEC LE CORPS. La page de l'article rend le
 * chapeau, ce greffon rend le corps : les deux doivent s'accorder sur chaque
 * terme. Le greffon lit donc la description dans le frontmatter et la découpe
 * avant le corps, dans le même passage. Il remet ce découpage à la page par le
 * frontmatter, la voie qu'Astro prévoit pour ce qu'un greffon calcule. La page
 * pose un <Terme> là où le découpage le dit, sans rien décider. Astro garde ce
 * découpage en cache avec le HTML du corps, et les recalcule ensemble quand
 * l'article ou l'empreinte du lexique change : chapeau et corps viennent
 * toujours du même glossaire. Un second découpage, fait par la page, aurait
 * suivi le glossaire du jour pendant que le corps restait en cache : un terme
 * aurait pu se trouver souligné deux fois, ou nulle part.
 *
 * JAMAIS DANS UN TITRE, UN LIEN, DU CODE OU UN TABLEAU. Un bouton dans un lien
 * serait un contrôle dans un contrôle, ce que le HTML interdit. Dans un titre,
 * il couperait la lecture du plan de la page. Et un tableau d'article défile
 * horizontalement sur téléphone : la bulle y serait coupée net.
 *
 * UN MOT À DEUX SENS PEUT RECEVOIR LA MAUVAISE DÉFINITION. Le greffon lit des
 * mots, pas leur sens : « le total donne 63 compétitions » aurait reçu la
 * définition du total d'un athlète, d'où la phrase réécrite dans l'article
 * des communes. Les tours connus sont écartés dans les formes de
 * glossaire.ts, « les deux catégories » ou « record du monde ». Un nouvel
 * article se relit donc sur la page générée, chapeau et mots soulignés
 * compris.
 *
 * Seuls les articles sont traités : le greffon s'applique à tout Markdown du
 * site, et un terme souligné ailleurs pourrait doubler celui d'une page.
 */
import { GLOSSAIRE, idBulle, type CleTerme, type Terme } from './glossaire.ts';

/*
 * Les seuls éléments de l'arbre que ce greffon lit ou fabrique. Les types
 * complets viennent du paquet `satteri`, qu'Astro installe sans que le projet
 * en dépende directement : les reprendre ici évite d'y lier le projet.
 */
interface Texte {
  type: 'text';
  value: string;
}
interface Element {
  type: 'element';
  tagName: string;
  properties: Record<string, unknown>;
  children: Noeud[];
}
type Noeud = Texte | Element | { type: string };

interface Contexte {
  readonly fileURL: URL | undefined;
  readonly data: Record<string, unknown>;
  parent(noeud: Noeud): Noeud | undefined;
  replaceNode(noeud: Noeud, remplacement: Noeud[]): void;
}

/**
 * Un morceau de texte découpé : le texte tel quel, ou un terme à souligner,
 * avec le mot tel qu'il est écrit, accord et majuscule compris. Rien d'autre
 * que des chaînes : le découpage du chapeau passe par le cache d'Astro.
 */
export type Morceau = string | { cle: CleTerme; mot: string };

/** Ancêtres qui interdisent le soulignement. */
const EXCLUS = new Set([
  'a',
  'button',
  'h1',
  'h2',
  'h3',
  'h4',
  'h5',
  'h6',
  'code',
  'pre',
  'kbd',
  'samp',
  'table',
  'summary',
  'sup',
  'sub',
]);

/** Dossier des articles, seuls concernés. */
const ARTICLES = '/src/content/actualites/';

/** Clé du sac de données où vivent les termes déjà soulignés. */
const DEJA_VUS = 'lexiqueDejaVus';

/** Clé du frontmatter où le greffon dépose le chapeau découpé. */
const CHAPEAU = 'lexiqueChapeau';

/** Les termes qui ont des formes, seuls repérables dans un texte. */
const MARQUABLES = GLOSSAIRE.filter((t) => t.formes);
const PAR_CLE = new Map(MARQUABLES.map((t) => [t.cle, t]));

const texte = (value: string): Texte => ({ type: 'text', value });

/**
 * Découpe un texte autour des termes qu'il emploie pour la première fois dans
 * l'article. `vus` tient les termes déjà soulignés : ceux trouvés ici s'y
 * ajoutent, et le texte suivant ne les soulignera plus.
 *
 * Plusieurs termes peuvent partager un même texte : « 451,7 points Dots, pour
 * un total de 665 kg en catégorie 83 kg ». On prend à chaque tour le terme qui
 * apparaît le plus tôt, puis on reprend la suite du texte.
 */
export function decouper(source: string, vus: Set<CleTerme>): Morceau[] {
  const morceaux: Morceau[] = [];
  let reste = source;
  for (;;) {
    let premier: { terme: Terme; index: number; mot: string } | null = null;
    for (const t of MARQUABLES) {
      if (vus.has(t.cle)) continue;
      const trouve = t.formes!.exec(reste);
      if (trouve && (!premier || trouve.index < premier.index)) {
        premier = { terme: t, index: trouve.index, mot: trouve[0] };
      }
    }
    if (!premier) break;

    if (premier.index > 0) morceaux.push(reste.slice(0, premier.index));
    morceaux.push({ cle: premier.terme.cle, mot: premier.mot });
    vus.add(premier.terme.cle);
    reste = reste.slice(premier.index + premier.mot.length);
  }
  if (reste) morceaux.push(reste);
  return morceaux;
}

/** Le terme souligné, structure identique à celle de Terme.astro. */
function element(terme: Terme, mot: string): Element {
  const id = idBulle(terme.cle);
  return {
    type: 'element',
    tagName: 'span',
    properties: { className: ['lexique'], dataTerme: terme.cle },
    children: [
      {
        type: 'element',
        tagName: 'span',
        properties: {
          className: ['lexique-bulle'],
          id,
          role: 'tooltip',
          ariaHidden: 'true',
          hidden: true,
        },
        children: [texte(terme.phrase)],
      },
      {
        type: 'element',
        tagName: 'span',
        properties: { className: ['lexique-mot'], role: 'button', tabIndex: 0, ariaDescribedBy: id },
        children: [texte(mot)],
      },
    ],
  };
}

function dansUnExclu(noeud: Noeud, ctx: Contexte): boolean {
  for (let p = ctx.parent(noeud); p; p = ctx.parent(p)) {
    if (p.type !== 'element') continue;
    const el = p as Element;
    if (EXCLUS.has(el.tagName)) return true;
    // Jamais dans un terme déjà souligné, ni dans sa bulle : une définition
    // qui soulignerait ses propres mots ouvrirait une bulle dans une bulle.
    const classes = el.properties.className;
    if (Array.isArray(classes) && classes.some((c) => String(c).startsWith('lexique'))) return true;
  }
  return false;
}

const estUnArticle = (ctx: Pick<Contexte, 'fileURL'>) =>
  Boolean(ctx.fileURL?.pathname.includes(ARTICLES));

/**
 * Le greffon. `empreinte` résume le glossaire et ce fichier : astro.config.mjs
 * la calcule, et Astro la compte dans sa configuration. Sans elle, une
 * définition corrigée resterait ancienne dans les articles, qu'Astro garde en
 * cache tant que leur fichier Markdown ne change pas. Mesuré le 27 octobre
 * 2026, phrase des Dots modifiée : sans l'empreinte, aucun des huit articles
 * qui soulignent « Dots » n'était à jour, et avec elle les huit le sont.
 */
export function lexiqueMarkdown(empreinte: string) {
  return {
    name: 'sbd-lexique',
    empreinte,

    /*
     * Une fois par article, avant le corps : le chapeau. Ses termes entrent
     * dans `vus`, et le corps les laisse en texte simple. Son découpage part
     * vers la page par le frontmatter. Un article sans corps passe aussi par
     * ici : mesuré sur un article d'essai, jamais commité.
     */
    before(_racine: unknown, ctx: Pick<Contexte, 'fileURL' | 'data'>) {
      if (!estUnArticle(ctx)) return;
      const vus = new Set<CleTerme>();
      ctx.data[DEJA_VUS] = vus;

      const astro = ctx.data.astro as { frontmatter?: Record<string, unknown> } | undefined;
      const description = astro?.frontmatter?.description;
      if (astro?.frontmatter && typeof description === 'string') {
        astro.frontmatter[CHAPEAU] = decouper(description, vus);
      }
    },

    text(noeud: Texte, ctx: Contexte) {
      if (!estUnArticle(ctx)) return;

      const vus = (ctx.data[DEJA_VUS] ??= new Set<CleTerme>()) as Set<CleTerme>;
      if (vus.size === MARQUABLES.length || dansUnExclu(noeud, ctx)) return;

      const morceaux = decouper(noeud.value, vus);
      if (morceaux.every((m) => typeof m === 'string')) return;
      ctx.replaceNode(
        noeud,
        morceaux.map((m) => (typeof m === 'string' ? texte(m) : element(PAR_CLE.get(m.cle)!, m.mot))),
      );
    },
  };
}

/**
 * Le chapeau d'un article tel que le greffon l'a découpé, pour la page.
 *
 * LA GÉNÉRATION S'ARRÊTE si ce découpage manque, ou s'il ne redit pas la
 * description au caractère près. Il manquerait si le moteur Markdown changeait,
 * ou si le corps n'avait pas pu être rendu. Le chapeau perdrait alors son
 * lexique sans que personne le voie, et un terme retiré du corps au profit du
 * chapeau resterait sans soulignement. La description est aussi le texte des
 * moteurs de recherche et du flux RSS : le chapeau la redit telle quelle.
 * Mesuré le 28 octobre 2026, découpage retiré du frontmatter : la génération
 * s'arrête sur le premier article, qu'elle nomme.
 */
export function chapeauDecoupe(
  frontmatter: Record<string, unknown>,
  description: string,
  article: string,
): Morceau[] {
  const morceaux = frontmatter[CHAPEAU];
  const lisible =
    Array.isArray(morceaux) &&
    morceaux.every(
      (m) =>
        typeof m === 'string' ||
        (typeof m?.mot === 'string' && PAR_CLE.has(m.cle as CleTerme)),
    );
  if (!lisible) {
    throw new Error(
      `Lexique : le chapeau de l'article « ${article} » n'a pas été découpé par le greffon des articles. Voir src/lib/lexique-markdown.ts.`,
    );
  }
  const texteRendu = (morceaux as Morceau[]).map((m) => (typeof m === 'string' ? m : m.mot)).join('');
  if (texteRendu !== description) {
    throw new Error(
      `Lexique : le chapeau découpé de l'article « ${article} » ne redit pas sa description. Attendu « ${description} », trouvé « ${texteRendu} ».`,
    );
  }
  return morceaux as Morceau[];
}

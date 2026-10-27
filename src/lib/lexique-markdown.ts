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
 * article se relit donc sur la page générée, mots soulignés compris.
 *
 * Seuls les articles sont traités : le greffon s'applique à tout Markdown du
 * site, et un terme souligné ailleurs pourrait doubler celui d'une page.
 */
import { GLOSSAIRE, idBulle, type Terme } from './glossaire.ts';

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

const texte = (value: string): Texte => ({ type: 'text', value });

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

/**
 * Le greffon. `empreinte` résume le glossaire et ce fichier : astro.config.mjs
 * la calcule, et Astro la compte dans sa configuration. Sans elle, une
 * définition corrigée resterait ancienne dans les articles, qu'Astro garde en
 * cache tant que leur fichier Markdown ne change pas. Mesuré le 27 octobre
 * 2026, phrase des Dots modifiée : sans l'empreinte, aucun des huit articles
 * qui soulignent « Dots » n'était à jour, et avec elle les huit le sont.
 */
export function lexiqueMarkdown(empreinte: string) {
  const marquables = GLOSSAIRE.filter((t) => t.formes);

  return {
    name: 'sbd-lexique',
    empreinte,

    text(noeud: Texte, ctx: Contexte) {
      if (!ctx.fileURL?.pathname.includes(ARTICLES)) return;

      const vus = (ctx.data[DEJA_VUS] ??= new Set<string>()) as Set<string>;
      if (vus.size === marquables.length || dansUnExclu(noeud, ctx)) return;

      /*
       * Plusieurs termes peuvent partager un même nœud de texte : « 451,7
       * points Dots, pour un total de 665 kg en catégorie 83 kg ». On prend
       * à chaque tour le terme qui apparaît le plus tôt, puis on reprend la
       * suite du texte.
       */
      const morceaux: Noeud[] = [];
      let reste = noeud.value;
      for (;;) {
        let premier: { terme: Terme; index: number; mot: string } | null = null;
        for (const t of marquables) {
          if (vus.has(t.cle)) continue;
          const trouve = t.formes!.exec(reste);
          if (trouve && (!premier || trouve.index < premier.index)) {
            premier = { terme: t, index: trouve.index, mot: trouve[0] };
          }
        }
        if (!premier) break;

        if (premier.index > 0) morceaux.push(texte(reste.slice(0, premier.index)));
        morceaux.push(element(premier.terme, premier.mot));
        vus.add(premier.terme.cle);
        reste = reste.slice(premier.index + premier.mot.length);
      }

      if (morceaux.length === 0) return;
      if (reste) morceaux.push(texte(reste));
      ctx.replaceNode(noeud, morceaux);
    },
  };
}

/**
 * Espaces insécables de la ponctuation française.
 *
 * En français, « : ; ! ? » sont précédés d'une espace, et les guillemets « »
 * entourent leur contenu d'une espace. Écrite au clavier, cette espace est
 * ordinaire : le navigateur peut y couper la ligne, et un « : » se retrouve en
 * tête de ligne, ou un « « » seul en fin de ligne. Mesuré le 28 octobre 2026
 * sur les 514 pages (audit, défaut D12) : 1 688 deux-points, 517 points
 * d'interrogation, 2 points-virgules et 49 paires de guillemets portaient une
 * espace ordinaire. À 375 px, deux « : » ouvraient une ligne de
 * /confidentialite/. À 320 px, un « « » fermait une ligne de /partenariats/.
 *
 * LE TRAITEMENT EST AUTOMATIQUE, sur le HTML final de chaque page, comme
 * l'ouverture des liens sortants : un texte écrit dans un gabarit, un article
 * Markdown ou le CMS en profite sans que personne y pense. Voir middleware.ts.
 *
 * U+00A0, L'ESPACE INSÉCABLE ORDINAIRE, ET NON U+202F, L'ESPACE FINE.
 *
 *   - Même largeur que l'espace qu'elle remplace, 0,209 em dans Archivo,
 *     mesuré dans le navigateur. Le seul effet visible est la coupure de
 *     ligne interdite : aucune ligne du site ne se raccourcit, aucun bloc ne
 *     change de hauteur, sauf là où une ponctuation tombait en tête de ligne.
 *     L'espace fine, 0,1045 em, aurait resserré chaque deux-points du site.
 *   - C'est la règle de l'Imprimerie nationale pour le deux-points et les
 *     guillemets, qui forment l'essentiel des cas. Elle préfère une fine
 *     devant « ; ! ? », 519 cas sur 2 305.
 *   - Les insécables déjà posées à la main dans le site, devant 10
 *     deux-points et dans 13 paires de guillemets, sont des U+00A0.
 *
 * RIEN N'EST AJOUTÉ : seule une espace déjà écrite devient insécable. Un
 * deux-points sans espace devant reste tel quel, et c'est ce qui protège les
 * adresses (« https:// »), les heures (« 12:30 ») et les rapports de contraste
 * (« 4,5:1 ») sans avoir à les reconnaître. Une seconde passe ne change rien.
 *
 * SEULS LES NŒUDS DE TEXTE SONT TOUCHÉS. Les attributs, les commentaires,
 * `<script>`, `<style>` et `<textarea>` ressortent intacts, ainsi que tout ce
 * qui vit dans `<pre>`, `<code>`, `<kbd>` et `<samp>`, qui montrent du code
 * et non de la prose.
 */

export const ESPACE_INSECABLE = ' ';

/**
 * Un jeton de la page : un commentaire, un élément de texte brut ENTIER
 * (script, style, textarea, balises et contenu compris), une balise, une
 * déclaration, ou une suite de texte. Les attributs entre guillemets sont lus
 * d'un bloc : un `>` écrit dans une valeur ne coupe pas la balise. Le `<` isolé
 * de la dernière alternative est du texte.
 */
const JETON =
  /<!--[\s\S]*?-->|<(script|style|textarea)\b(?:[^>"']|"[^"]*"|'[^']*')*>[\s\S]*?<\/\1\s*>|<(\/?)([a-zA-Z][a-zA-Z0-9-]*)(?:[^>"']|"[^"]*"|'[^']*')*>|<![^>]*>|[^<]+|</gi;

/** Éléments dont le contenu est du code : aucune règle de prose ne s'y applique. */
const CODE = new Set(['pre', 'code', 'kbd', 'samp']);

/** Éléments de texte brut : leur contenu n'est jamais du texte affiché. */
const BRUT = new Set(['script', 'style', 'textarea']);

/**
 * Éléments EN LIGNE. Une espace qui suit leur fermeture, ou qui précède leur
 * ouverture, est rendue à l'écran au milieu d'une phrase : « <strong>Base
 * légale</strong> : » en est le cas courant. Une espace qui suit une balise de
 * bloc, elle, disparaît au rendu, et la rendre insécable la ferait apparaître.
 */
const EN_LIGNE = new Set([
  'a', 'abbr', 'b', 'bdi', 'bdo', 'cite', 'code', 'data', 'dfn', 'em', 'i', 'img',
  'kbd', 'mark', 'q', 's', 'samp', 'small', 'span', 'strong', 'sub', 'sup', 'svg',
  'time', 'u', 'var',
]);

/** Espace ordinaire au sens du HTML, sans l'insécable que `\s` comprend. */
const ESPACE = '[ \\t\\n\\r\\f]';

/** Espace avant la ponctuation haute ou le guillemet fermant, dans le nœud. */
const AVANT_PONCTUATION = new RegExp(`([^ \\t\\n\\r\\f])${ESPACE}+(?=[:;!?»])`, 'g');
/** La même, en tête de nœud : la phrase a commencé avant une balise en ligne. */
const AVANT_PONCTUATION_EN_TETE = new RegExp(`^${ESPACE}+(?=[:;!?»])`);
/** Espace après le guillemet ouvrant, suivie de texte dans le nœud. */
const APRES_GUILLEMET = new RegExp(`«${ESPACE}+(?=[^ \\t\\n\\r\\f])`, 'g');
/** La même, en fin de nœud : la citation continue dans une balise en ligne. */
const APRES_GUILLEMET_EN_FIN = new RegExp(`«${ESPACE}+$`);

/**
 * Rend insécables les espaces d'un nœud de texte.
 *
 * `suiteEnLigne` dit si ce qui précède le nœud est en ligne, `enLigneEnsuite`
 * si ce qui le suit l'est : une espace en bord de nœud n'est touchée que si
 * elle est rendue à l'écran.
 */
export function insecabiliser(
  texte: string,
  suiteEnLigne = false,
  enLigneEnsuite = false,
): string {
  let t = texte.replace(AVANT_PONCTUATION, `$1${ESPACE_INSECABLE}`);
  if (suiteEnLigne) t = t.replace(AVANT_PONCTUATION_EN_TETE, ESPACE_INSECABLE);
  t = t.replace(APRES_GUILLEMET, `«${ESPACE_INSECABLE}`);
  if (enLigneEnsuite) t = t.replace(APRES_GUILLEMET_EN_FIN, `«${ESPACE_INSECABLE}`);
  return t;
}

type Jeton =
  | { genre: 'texte'; brut: string }
  | { genre: 'balise'; brut: string; nom: string; fermante: boolean }
  | { genre: 'autre'; brut: string };

function decouper(html: string): Jeton[] | null {
  const jetons: Jeton[] = [];
  for (const m of html.matchAll(JETON)) {
    const brut = m[0];
    if (m[1]) {
      jetons.push({ genre: 'autre', brut });
    } else if (m[3]) {
      const nom = m[3].toLowerCase();
      // Une ouverture de script, de style ou de textarea lue comme une simple
      // balise n'a pas trouvé sa fermeture : la page est mal formée, et la
      // suite ne peut plus être lue sans risque.
      if (BRUT.has(nom) && !m[2]) return null;
      jetons.push({ genre: 'balise', brut, nom, fermante: m[2] === '/' });
    } else if (brut.startsWith('<!')) {
      jetons.push({ genre: 'autre', brut });
    } else {
      jetons.push({ genre: 'texte', brut });
    }
  }
  return jetons;
}

/** Le jeton voisin qui compte, commentaires sautés. */
function voisin(jetons: Jeton[], depart: number, pas: 1 | -1): Jeton | undefined {
  for (let i = depart + pas; i >= 0 && i < jetons.length; i += pas) {
    const j = jetons[i];
    if (j.genre === 'autre' && j.brut.startsWith('<!--')) continue;
    return j;
  }
  return undefined;
}

/** Applique `insecabiliser` à chaque nœud de texte d'une page HTML. */
export function poserEspacesInsecables(html: string): string {
  const jetons = decouper(html);
  if (!jetons) return html;

  let dansCode = 0;
  const sortie: string[] = [];
  jetons.forEach((jeton, i) => {
    if (jeton.genre === 'balise' && CODE.has(jeton.nom) && !jeton.brut.endsWith('/>')) {
      dansCode = Math.max(0, dansCode + (jeton.fermante ? -1 : 1));
    }
    if (jeton.genre !== 'texte' || dansCode > 0) {
      sortie.push(jeton.brut);
      return;
    }
    const avant = voisin(jetons, i, -1);
    const apres = voisin(jetons, i, 1);
    const suiteEnLigne =
      avant?.genre === 'texte' ||
      (avant?.genre === 'balise' && avant.fermante && EN_LIGNE.has(avant.nom)) ||
      (avant?.genre === 'balise' && avant.nom === 'img');
    const enLigneEnsuite =
      apres?.genre === 'texte' ||
      (apres?.genre === 'balise' && !apres.fermante && EN_LIGNE.has(apres.nom));
    sortie.push(insecabiliser(jeton.brut, suiteEnLigne, enLigneEnsuite));
  });
  return sortie.join('');
}

/**
 * Liens sortants : nouvel onglet, et annonce de la sortie.
 *
 * DEMANDE DE L'AUTEUR DU 24 OCTOBRE 2026. Un lien vers un autre site s'ouvre
 * dans un nouvel onglet, pour que la page de sbd.re reste ouverte derrière. Et
 * le visiteur l'apprend AVANT de cliquer : la flèche ↗ de tokens.css le dit à
 * l'œil, la phrase ci-dessous le dit aux lecteurs d'écran.
 *
 * UNE SEULE PHRASE POUR TOUT LE SITE, posée une fois dans BaseLayout.astro, et
 * reliée à chaque lien par `aria-describedby`. Un lecteur d'écran lit le nom du
 * lien, puis sa description : « CNIL, lien, nouvel onglet, quitte sbd.re ». Le
 * nom accessible du lien ne change pas, ce qui préserve ceux qui portent un
 * `aria-label`, et le texte n'est écrit qu'à un endroit.
 *
 * L'élément référencé porte `hidden`. Les lecteurs d'écran lisent quand même
 * une description masquée quand un `aria-describedby` la désigne : c'est écrit
 * dans la spécification du calcul des noms accessibles, et c'est le mécanisme
 * déjà employé par Infobulle.astro.
 */

export const ID_ANNONCE_SORTIE = 'annonce-lien-sortant';
export const TEXTE_ANNONCE_SORTIE = 'Nouvel onglet, quitte sbd.re';

/**
 * Vrai pour une adresse http ou https qui mène hors de sbd.re.
 *
 * Même périmètre que la flèche de tokens.css : `mailto:`, `tel:`, les ancres et
 * les chemins relatifs restent sur place. Un sous-domaine de sbd.re aussi.
 */
export function estSortant(href: string): boolean {
  if (!/^https?:\/\//i.test(href)) return false;
  try {
    const { hostname } = new URL(href);
    return hostname !== 'sbd.re' && !hostname.endsWith('.sbd.re');
  } catch {
    return false;
  }
}

/** Ajoute un mot à une liste d'attributs séparés par des espaces, sans doublon. */
function ajouterMot(liste: string, mot: string): string {
  const mots = liste.split(/\s+/).filter(Boolean);
  return mots.includes(mot) ? mots.join(' ') : [...mots, mot].join(' ');
}

/**
 * Une balise ouvrante `<a …>`, attributs entre guillemets compris : un `>` écrit
 * dans la valeur d'un attribut ne coupe donc pas la balise.
 *
 * L'alternative de tête capture les blocs `<script>` ENTIERS pour les rendre
 * intacts. Un script qui fabrique un lien dans une chaîne de caractères serait
 * sinon réécrit, et un guillemet ajouté au mauvais endroit casserait le script.
 */
const SCRIPT_OU_LIEN =
  /(<script\b[^>]*>[\s\S]*?<\/script>)|<a\b(?:[^>"']|"[^"]*"|'[^']*')*>/gi;

/** Lit la valeur d'un attribut dans une balise, ou `null` s'il est absent. */
function lireAttribut(balise: string, nom: string): string | null {
  const trouve = new RegExp(`\\s${nom}\\s*=\\s*"([^"]*)"`, 'i').exec(balise);
  return trouve ? trouve[1] : null;
}

/** Remplace la valeur d'un attribut, ou l'ajoute en fin de balise. */
function ecrireAttribut(balise: string, nom: string, valeur: string): string {
  const motif = new RegExp(`(\\s${nom}\\s*=\\s*)"[^"]*"`, 'i');
  if (motif.test(balise)) return balise.replace(motif, `$1"${valeur}"`);
  return balise.replace(/>$/, ` ${nom}="${valeur}">`);
}

/** Réécrit une balise `<a>` sortante. Toute autre balise ressort intacte. */
export function ouvrirLienSortant(balise: string): string {
  const href = lireAttribut(balise, 'href');
  if (!href || !estSortant(href)) return balise;

  let resultat = balise;
  // Un `target` déjà écrit est un choix explicite : il est respecté.
  if (lireAttribut(resultat, 'target') === null) {
    resultat = ecrireAttribut(resultat, 'target', '_blank');
  }
  // `noopener` interdit à la page ouverte de piloter celle-ci. Les navigateurs
  // récents le posent d'office avec `_blank`, les anciens non.
  resultat = ecrireAttribut(resultat, 'rel', ajouterMot(lireAttribut(resultat, 'rel') ?? '', 'noopener'));
  resultat = ecrireAttribut(
    resultat,
    'aria-describedby',
    ajouterMot(lireAttribut(resultat, 'aria-describedby') ?? '', ID_ANNONCE_SORTIE),
  );
  return resultat;
}

/** Applique `ouvrirLienSortant` à toute une page HTML. */
export function ouvrirLiensSortants(html: string): string {
  return html.replace(SCRIPT_OU_LIEN, (balise, script?: string) =>
    script ? script : ouvrirLienSortant(balise),
  );
}

/**
 * La même règle pour un lien fabriqué dans le navigateur, que le traitement de
 * la génération ne voit pas : celui de la carte des salles.
 */
export function marquerLienSortant(lien: HTMLAnchorElement): void {
  if (!estSortant(lien.href)) return;
  lien.target = '_blank';
  lien.rel = ajouterMot(lien.rel, 'noopener');
  lien.setAttribute(
    'aria-describedby',
    ajouterMot(lien.getAttribute('aria-describedby') ?? '', ID_ANNONCE_SORTIE),
  );
}

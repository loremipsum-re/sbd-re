/**
 * Le comportement des termes soulignés du lexique, côté navigateur.
 *
 * UN SEUL SCRIPT POUR TOUT LE SITE, injecté par astro.config.mjs sur chaque
 * page, une fois, quel que soit le nombre de termes : 2,5 ko, moins d'un ko
 * compressé, mis en cache d'une page à l'autre. Il ne fait rien sur une page
 * qui n'en a aucun. Il n'est posé ni par Terme.astro ni par la page
 * d'article : Astro rend le script d'un composant là où le composant apparaît
 * pour la première fois, et il aurait atterri au milieu d'une phrase.
 *
 * LE CSS FAIT L'ESSENTIEL, voir tokens.css. Il ouvre la bulle au survol d'une
 * souris et au focus du clavier, sans une ligne de script. Ce script ajoute les
 * quatre choses que le CSS ne sait pas faire :
 *
 *   1. GARDER LA BULLE LISIBLE. Elle part sous le mot, calée à sa gauche. Un
 *      mot en fin de ligne la ferait sortir de l'écran à droite, et un mot en
 *      bas d'écran la pousserait sous le pli, ou sur les boutons flottants
 *      qu'elle masquerait. Le script la décale vers la gauche, et la passe
 *      au-dessus du mot quand elle y tient tout entière.
 *   2. L'OUVRIR AU TOUCHER. Un téléphone n'a pas de survol, et un mot touché
 *      n'y prend pas le focus. Toucher le mot ouvre donc la bulle, le toucher
 *      encore ou toucher ailleurs la ferme. Un clic de souris l'épingle de la
 *      même façon.
 *   3. ENTRÉE ET ESPACE, qui épinglent et ferment comme le clic. Le mot est un
 *      `<span role="button">`, voir Terme.astro : le navigateur ne lui donne
 *      pas ces deux touches d'office, comme il le ferait pour un bouton.
 *   4. LA FERMER PAR ÉCHAP, sans déplacer le pointeur ni le focus, comme le
 *      demande le critère 1.4.13 des WCAG 2.2 pour tout contenu qui apparaît
 *      au survol ou au focus. Elle revient quand le pointeur ou le focus
 *      quitte le mot puis y revient, ou quand le pointeur, parti avant Échap,
 *      revient sur le mot.
 *
 * Trois états, en attributs sur le terme, lus par tokens.css :
 *   data-ouvert  épinglée par un toucher, un clic, Entrée ou Espace,
 *   data-ferme   refermée, et le reste tant que le pointeur ou le focus est là,
 *   data-mesure  affichée sans être vue, le temps d'en lire la taille.
 */

// Un module, et non un script global : ses noms restent les siens.
export {};

/** La gouttière du site, `--space-4` : la bulle ne s'en approche pas plus. */
const MARGE = 16;

const termeDe = (cible: EventTarget | null) =>
  cible instanceof Element ? cible.closest<HTMLElement>('.lexique') : null;

const bulleDe = (terme: HTMLElement) =>
  terme.querySelector<HTMLElement>(':scope > .lexique-bulle');

/** Vrai si `cible` est hors du terme : le pointeur ou le focus le quitte. */
const hors = (terme: HTMLElement, cible: EventTarget | null) =>
  !(cible instanceof Node && terme.contains(cible));

const visible = (terme: HTMLElement) => {
  const bulle = bulleDe(terme);
  return bulle !== null && getComputedStyle(bulle).display !== 'none';
};

/**
 * Vrai si un élément fixe occupe ce point de l'écran : un bouton flottant en
 * bas, l'en-tête collante en haut. La bulle se mesure masquée, donc
 * transparente au pointeur : c'est bien ce qu'il y a dessous qui répond.
 */
function couvert(x: number, y: number): boolean {
  for (let el = document.elementFromPoint(x, y); el; el = el.parentElement) {
    const position = getComputedStyle(el).position;
    if (position === 'fixed' || position === 'sticky') return true;
  }
  return false;
}

/**
 * Pose la bulle dans la fenêtre. Le CSS la place sous le mot, calée à sa
 * gauche : le script la décale du strict nécessaire. Tout se passe avant que
 * le navigateur ne dessine : rien ne saute à l'œil.
 *
 * LA BULLE NE DÉBORDE JAMAIS DE L'ÉCRAN, PAS MÊME LE TEMPS D'UNE MESURE. Sous
 * un mot en fin de ligne, à sa place naturelle ou à celle d'une mesure
 * précédente, elle dépasserait à droite. Sur un téléphone, ce débordement d'un
 * instant élargit la page, et près du bas de celle-ci le navigateur la fait
 * remonter. Mesuré le 28 octobre 2026 : 42 px de saut à 375 px, 70 px à
 * 768 px, et le doigt touchait alors la phrase au lieu du mot, si bien que la
 * bulle restait close. D'où deux lectures : la place du terme, bulle retirée,
 * puis la taille de la bulle, posée au bord gauche de l'écran. Sa place se
 * déduit ensuite de celle du terme, dont elle part.
 */
function placer(terme: HTMLElement) {
  const bulle = bulleDe(terme);
  if (!bulle) return;

  // Retirée avant toute lecture : chacune fait calculer la page.
  bulle.style.display = 'none';
  const racine = document.documentElement;
  const { clientWidth: largeur, clientHeight: hauteur } = racine;
  const mot = terme.getBoundingClientRect();
  bulle.style.removeProperty('display');

  terme.setAttribute('data-mesure', '');
  bulle.removeAttribute('data-dessus');
  bulle.style.left = `${MARGE - mot.left}px`;
  const cadre = bulle.getBoundingClientRect();

  // Sans décalage, la bulle part du bord gauche du terme : `left: 0`.
  let decalage = 0;
  if (mot.left + cadre.width > largeur - MARGE) decalage = largeur - MARGE - mot.left - cadre.width;
  if (mot.left + decalage < MARGE) decalage = MARGE - mot.left;
  if (decalage !== 0) bulle.style.left = `${Math.round(decalage)}px`;
  else bulle.style.removeProperty('left');

  /*
   * Au-dessus du mot si, dessous, la bulle sort de l'écran ou croise un
   * élément fixe, et seulement si elle tient tout entière au-dessus, sous
   * l'en-tête collante. La hauteur de celle-ci est celle que tokens.css
   * réserve aux ancres, `scroll-padding-top` : la lire là évite de dépendre
   * du gabarit du menu. Un bouton flottant peut mordre sur n'importe quel
   * coin : les quatre sont sondés, et le milieu du bas. Quand les deux places
   * le croisent, la bulle passe devant lui, voir tokens.css.
   */
  const gauche = mot.left + decalage + 4;
  const droite = mot.left + decalage + cadre.width - 4;
  const haut = cadre.top + 4;
  const bas = cadre.bottom - 4;
  const masquee =
    cadre.bottom > hauteur ||
    couvert(gauche, haut) ||
    couvert(droite, haut) ||
    couvert(gauche, bas) ||
    couvert(droite, bas) ||
    couvert((gauche + droite) / 2, bas);
  const plafond = Number.parseFloat(getComputedStyle(racine).scrollPaddingTop) || 0;
  if (masquee && mot.top - cadre.height >= plafond) {
    bulle.setAttribute('data-dessus', '');
  }

  terme.removeAttribute('data-mesure');
}

function fermer(terme: HTMLElement) {
  terme.removeAttribute('data-ouvert');
  terme.setAttribute('data-ferme', '');
}

function ouvrir(terme: HTMLElement) {
  for (const autre of document.querySelectorAll<HTMLElement>('.lexique[data-ouvert]')) {
    if (autre !== terme) autre.removeAttribute('data-ouvert');
  }
  terme.removeAttribute('data-ferme');
  terme.setAttribute('data-ouvert', '');
  placer(terme);
}

/** Le clic, Entrée ou Espace sur le mot : épingle la bulle, ou la ferme. */
function basculer(terme: HTMLElement) {
  if (terme.hasAttribute('data-ouvert')) fermer(terme);
  else ouvrir(terme);
}

if (document.querySelector('.lexique')) {
  /*
   * Le pointeur arrive sur le mot : la bulle se place avant de s'afficher.
   * Arriver de l'extérieur du terme est un nouveau survol, qui lève aussi la
   * fermeture par Échap. Sans cela, une bulle épinglée d'un clic puis fermée
   * par Échap, le pointeur déjà parti, restait close au retour suivant :
   * aucune sortie du pointeur n'était venue lever l'état. Mesuré, le premier
   * retour ne montrait rien et seul le deuxième rouvrait la bulle.
   */
  document.addEventListener('pointerover', (e) => {
    const terme = termeDe(e.target);
    if (!terme || !hors(terme, e.relatedTarget)) return;
    terme.removeAttribute('data-ferme');
    if (!terme.hasAttribute('data-ouvert')) placer(terme);
  });

  document.addEventListener('pointerout', (e) => {
    const terme = termeDe(e.target);
    if (terme && hors(terme, e.relatedTarget)) terme.removeAttribute('data-ferme');
  });

  document.addEventListener('focusin', (e) => {
    const terme = termeDe(e.target);
    if (terme) placer(terme);
  });

  document.addEventListener('focusout', (e) => {
    const terme = termeDe(e.target);
    if (terme && hors(terme, e.relatedTarget)) {
      terme.removeAttribute('data-ouvert');
      terme.removeAttribute('data-ferme');
    }
  });

  /*
   * Le clic vaut pour la souris et le doigt. Il s'écoute sur chaque mot, et
   * non sur le document : Safari sur iPhone ne déclenche le clic d'un élément
   * de texte que si cet élément l'écoute lui-même.
   */
  for (const mot of document.querySelectorAll<HTMLElement>('.lexique-mot')) {
    mot.addEventListener('click', () => {
      const terme = termeDe(mot);
      if (terme) basculer(terme);
    });
  }

  // Toucher ou cliquer ailleurs referme la bulle épinglée.
  document.addEventListener('pointerdown', (e) => {
    for (const terme of document.querySelectorAll<HTMLElement>('.lexique[data-ouvert]')) {
      if (hors(terme, e.target)) terme.removeAttribute('data-ouvert');
    }
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      for (const terme of document.querySelectorAll<HTMLElement>('.lexique')) {
        if (visible(terme)) fermer(terme);
      }
      return;
    }
    // Entrée et Espace sur le mot, comme sur un bouton. Espace ferait sinon
    // défiler la page, et une touche tenue enfoncée ne bascule qu'une fois.
    if (e.key !== 'Enter' && e.key !== ' ') return;
    const mot = e.target instanceof Element ? e.target.closest('.lexique-mot') : null;
    const terme = termeDe(mot);
    if (!terme) return;
    e.preventDefault();
    if (!e.repeat) basculer(terme);
  });

  // Un téléphone qu'on tourne change la largeur : la bulle ouverte se replace.
  addEventListener(
    'resize',
    () => {
      for (const terme of document.querySelectorAll<HTMLElement>('.lexique')) {
        if (visible(terme)) placer(terme);
      }
    },
    { passive: true },
  );
}

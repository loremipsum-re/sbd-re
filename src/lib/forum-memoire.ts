/**
 * Ce que le forum retient dans le navigateur, et nulle part ailleurs : les
 * brouillons, et jusqu'où chaque sujet a été lu, d'où « Nouveau » et
 * « 3 nouvelles réponses » dans les listes. Rien ne part au serveur, et
 * /confidentialite/ le dit. Stockage refusé (navigation privée stricte) :
 * le forum fonctionne pareil, sans mémoire.
 *
 * Les sujets lus se rangent sous des clés « s21 » et non « 21 » : JavaScript
 * range les clés numériques par valeur, et l'ordre d'insertion sert ici à
 * oublier les plus anciens au-delà de 500.
 */

const BROUILLON = 'sbd-brouillon:';
const VUS = 'sbd-forum-vus';
const VISITE = 'sbd-forum-visite';
const DEPUIS = 'sbd-forum-depuis';
const SEPT_JOURS = 7 * 24 * 3600 * 1000;
const VUS_MAX = 500;
/** Comme MESSAGES_PAR_PAGE de public/api/lib/forum.php. */
const MESSAGES_PAR_PAGE = 20;

const lire = (stock: () => Storage, cle: string): string | null => {
  try {
    return stock().getItem(cle);
  } catch {
    return null;
  }
};
const ecrire = (stock: () => Storage, cle: string, valeur: string | null): void => {
  try {
    if (valeur === null) stock().removeItem(cle);
    else stock().setItem(cle, valeur);
  } catch {
    /* sans mémoire */
  }
};
const local = () => localStorage;
const session = () => sessionStorage;

/* --- Les brouillons --------------------------------------------------- */

/** Les brouillons de plus de sept jours partent : un vieux texte surprendrait. */
export function purgerBrouillons(): void {
  try {
    for (const cle of Object.keys(localStorage)) {
      if (!cle.startsWith(BROUILLON)) continue;
      const t = Number((JSON.parse(localStorage.getItem(cle) ?? '{}') as { t?: number }).t);
      if (!(Date.now() - t < SEPT_JOURS)) localStorage.removeItem(cle);
    }
  } catch {
    /* sans mémoire */
  }
}

/**
 * Garde le titre et le texte d'un formulaire à chaque frappe, et les rend à
 * la visite suivante si les champs sont vides. `effacer` sert après la
 * publication.
 */
export function brancherBrouillon(form: HTMLFormElement, cle: string): { retrouve: boolean; effacer: () => void } {
  const nom = BROUILLON + cle;
  const champs = [...form.querySelectorAll<HTMLInputElement | HTMLTextAreaElement>('input[name="titre"], textarea[name="texte"]')];
  let retrouve = false;
  try {
    const garde = JSON.parse(lire(local, nom) ?? 'null') as Record<string, string | number> | null;
    if (garde && Date.now() - Number(garde.t) < SEPT_JOURS && champs.every((c) => c.value === '')) {
      for (const c of champs) c.value = String(garde[c.name] ?? '');
      retrouve = champs.some((c) => c.value !== '');
    }
  } catch {
    /* brouillon illisible : ignoré */
  }

  let minuterie = 0;
  form.addEventListener('input', () => {
    clearTimeout(minuterie);
    minuterie = window.setTimeout(() => {
      const vide = champs.every((c) => c.value.trim() === '');
      const garde: Record<string, string | number> = { t: Date.now() };
      for (const c of champs) garde[c.name] = c.value;
      ecrire(local, nom, vide ? null : JSON.stringify(garde));
    }, 400);
  });

  return {
    retrouve,
    effacer: () => {
      clearTimeout(minuterie);
      ecrire(local, nom, null);
    },
  };
}

/* --- Jusqu'où chaque sujet a été lu ------------------------------------- */

type Vus = Record<string, number>;

const lireVus = (): Vus => {
  try {
    const vus = JSON.parse(lire(local, VUS) ?? '{}') as unknown;
    return vus !== null && typeof vus === 'object' ? (vus as Vus) : {};
  } catch {
    return {};
  }
};

/** Retient la place du dernier message lu dans un sujet, la première valant 0. */
export function retenirLecture(sujetId: string, vu: number): void {
  const vus = lireVus();
  const cle = `s${sujetId}`;
  if ((vus[cle] ?? -1) >= vu) return;
  // Réinséré en dernier : l'ordre des clés dit l'ancienneté.
  delete vus[cle];
  vus[cle] = vu;
  const cles = Object.keys(vus);
  for (const vieille of cles.slice(0, Math.max(0, cles.length - VUS_MAX))) delete vus[vieille];
  ecrire(local, VUS, JSON.stringify(vus));
}

/** Comme lienSujet() de lib/forum.php, posé sur le numéro du message. */
export function lienPremierNonLu(adresse: string, sujetId: string, vu: number): string {
  const page = Math.floor((vu + 1) / MESSAGES_PAR_PAGE) + 1;
  return `/forum/${adresse}/${sujetId}/${page > 1 ? `?page=${page}` : ''}#n${vu + 2}`;
}

/**
 * Dans une liste de sujets : « 3 nouvelles réponses » vers la première non
 * lue, sur un sujet déjà ouvert ; « Nouveau » sur un sujet ouvert depuis la
 * visite précédente. La première visite ne marque rien.
 */
export function marquerNouveautes(lignes: HTMLElement[]): void {
  const vus = lireVus();
  // La visite précédente, fixée une fois par session : passer d'une liste à
  // l'autre n'efface pas les marques.
  let depuis = Number(lire(session, DEPUIS));
  if (!depuis) {
    depuis = Number(lire(local, VISITE)) || Date.now();
    ecrire(session, DEPUIS, String(depuis));
  }
  ecrire(local, VISITE, String(Date.now()));

  for (const li of lignes) {
    const id = li.dataset.sujetId ?? '';
    const titre = li.querySelector<HTMLElement>('.titre-sujet');
    if (!titre || li.querySelector('.nouveau')) continue;
    const vu = vus[`s${id}`];
    if (vu === undefined) {
      if (Number(li.dataset.creation) * 1000 > depuis) {
        titre.prepend(Object.assign(document.createElement('span'), { className: 'etiquette nouveau', textContent: 'Nouveau' }), ' ');
      }
      continue;
    }
    const nouvelles = Number(li.dataset.reponses) - vu;
    if (nouvelles > 0) {
      titre.append(
        ' ',
        Object.assign(document.createElement('a'), {
          className: 'nouveau',
          href: lienPremierNonLu(li.dataset.adresse ?? '', id, vu),
          // Texte fabriqué ici : l'insécable entre le nombre et le mot est écrite.
          textContent: nouvelles > 1 ? `${nouvelles} nouvelles réponses` : '1 nouvelle réponse',
        }),
      );
    }
  }
}

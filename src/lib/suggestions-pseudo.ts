/**
 * Les suggestions de pseudo après « @ », au forum et au chat.
 *
 * Un membre connecté tape « @je » : la liste des pseudos qui commencent par
 * « je » paraît sous le champ (public/api/pseudos.php, huit au plus). Les
 * flèches choisissent, Entrée insère « @jean_93 », Échap referme ; la souris
 * et le toucher choisissent aussi. Le champ garde le focus tout du long.
 *
 * ÉCOUTÉ SUR LE DOCUMENT : le formulaire « Modifier » du forum naît après le
 * chargement, et le chat flotte sur toutes les pages. Le clavier s'écoute en
 * capture, avant la fenêtre du chat, pour qu'Échap ferme la liste sans
 * fermer le chat, et qu'Entrée insère sans envoyer.
 *
 * Accessibilité : le champ reste un champ de texte, avec `aria-autocomplete`,
 * `aria-controls` et `aria-activedescendant` vers l'option choisie ; une
 * région polie annonce le nombre de pseudos et les touches.
 */
import { api } from './api-compte';

type Champ = HTMLTextAreaElement | HTMLInputElement;

const CHAMPS = 'textarea[name="texte"], #texte-chat';
/** La mention en cours, juste avant le curseur : le motif de mentionsDe(), lib/forum.php. */
const MOTIF = /(?:^|[^a-z0-9._@])@([a-z0-9._]{1,30})$/i;

let liste: HTMLUListElement | null = null;
let champ: Champ | null = null;
let pseudos: string[] = [];
let actif = 0;
let minuterie = 0;
let demande = 0;
let annonce: HTMLElement | null = null;

const estChamp = (cible: EventTarget | null): cible is Champ =>
  (cible instanceof HTMLTextAreaElement || cible instanceof HTMLInputElement) && cible.matches(CHAMPS);

/** Le début de pseudo tapé avant le curseur, et la position de son « @ ». */
function mentionEnCours(c: Champ): { debut: string; arobase: number } | null {
  const avant = c.value.slice(0, c.selectionStart ?? c.value.length);
  const m = MOTIF.exec(avant);
  return m ? { debut: m[1].toLowerCase(), arobase: avant.length - m[1].length - 1 } : null;
}

function annoncer(texte: string) {
  if (!annonce) {
    annonce = Object.assign(document.createElement('p'), { className: 'sr-only' });
    annonce.setAttribute('role', 'status');
    document.body.append(annonce);
  }
  annonce.textContent = texte;
}

function fermer() {
  liste?.remove();
  liste = null;
  champ?.removeAttribute('aria-activedescendant');
  champ?.removeAttribute('aria-controls');
  pseudos = [];
}

function marquer() {
  liste!.querySelectorAll('[role="option"]').forEach((option, i) => option.setAttribute('aria-selected', String(i === actif)));
  champ!.setAttribute('aria-activedescendant', `suggestion-pseudo-${actif}`);
}

function choisir(i: number) {
  const c = champ!;
  const mention = mentionEnCours(c);
  if (!mention) return fermer();
  const insere = `@${pseudos[i]} `;
  const fin = c.selectionStart ?? c.value.length;
  c.value = c.value.slice(0, mention.arobase) + insere + c.value.slice(fin);
  const curseur = mention.arobase + insere.length;
  c.setSelectionRange(curseur, curseur);
  fermer();
  c.focus();
  // Le brouillon du forum écoute `input` ; la mention finie ne relance rien.
  c.dispatchEvent(new Event('input', { bubbles: true }));
}

function montrer(c: Champ, trouves: string[]) {
  fermer();
  if (trouves.length === 0) return;
  champ = c;
  pseudos = trouves;
  actif = 0;
  liste = document.createElement('ul');
  liste.id = 'suggestions-pseudo';
  liste.className = 'suggestions-pseudo';
  liste.setAttribute('role', 'listbox');
  liste.setAttribute('aria-label', 'Pseudos');
  trouves.forEach((pseudo, i) => {
    const option = Object.assign(document.createElement('li'), { id: `suggestion-pseudo-${i}`, textContent: `@${pseudo}` });
    option.setAttribute('role', 'option');
    // `pointerdown` sans action par défaut : le champ garde le focus.
    option.addEventListener('pointerdown', (evenement) => {
      evenement.preventDefault();
      choisir(i);
    });
    liste!.append(option);
  });
  // Au chat, sous la ligne du champ et du bouton Envoyer.
  (c.closest('.ligne-envoi') ?? c).after(liste);
  c.setAttribute('aria-autocomplete', 'list');
  c.setAttribute('aria-controls', liste.id);
  marquer();
  annoncer(`${trouves.length} pseudo${trouves.length > 1 ? 's' : ''}. Flèches pour choisir, Entrée pour insérer.`);
}

let branche = false;

export function brancherSuggestionsPseudo() {
  if (branche) return;
  branche = true;

  document.addEventListener('input', (evenement) => {
    const c = evenement.target;
    if (!estChamp(c) || !document.cookie.split('; ').includes('sbdre-connecte=1')) return;
    clearTimeout(minuterie);
    const mention = mentionEnCours(c);
    if (!mention) return fermer();
    minuterie = window.setTimeout(async () => {
      const numero = ++demande;
      const r = await api(`/api/pseudos.php?debut=${encodeURIComponent(mention.debut)}`, 'GET');
      // Une réponse dépassée par une frappe plus récente, ou un champ quitté, n'affiche rien.
      if (numero !== demande || document.activeElement !== c) return;
      montrer(c, r.ok && Array.isArray(r.pseudos) ? (r.pseudos as string[]) : []);
    }, 200);
  });

  document.addEventListener(
    'keydown',
    (evenement) => {
      if (!liste || evenement.target !== champ) return;
      if (evenement.key === 'ArrowDown' || evenement.key === 'ArrowUp') {
        actif = (actif + (evenement.key === 'ArrowDown' ? 1 : -1) + pseudos.length) % pseudos.length;
        marquer();
      } else if (evenement.key === 'Enter') {
        choisir(actif);
      } else if (evenement.key === 'Escape') {
        fermer();
      } else {
        return;
      }
      evenement.preventDefault();
      evenement.stopPropagation();
    },
    true,
  );

  document.addEventListener('focusout', (evenement) => {
    if (evenement.target === champ) fermer();
  });
}

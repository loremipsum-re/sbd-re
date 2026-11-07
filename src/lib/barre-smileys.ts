/**
 * La barre de smileys sous un champ : un clic insère le code à la place du
 * curseur, entouré d'espaces, pour qu'il soit reconnu (src/lib/smileys.ts).
 * Partagée par le forum et le chat.
 */
import { SMILEYS, decouper } from './smileys';

/**
 * Pose, dans chaque `[data-smileys]`, un petit panneau replié « Smileys »,
 * à la manière des forums phpBB, relié au champ de son formulaire. Demande
 * de l'auteur du 7 novembre : les smileys ne s'affichent pas en gros.
 */
export function brancherBarresSmileys(racine: ParentNode = document): void {
  for (const zone of racine.querySelectorAll<HTMLElement>('[data-smileys]')) {
    const champ = zone.closest('form')?.querySelector<HTMLTextAreaElement | HTMLInputElement>('textarea, input[name="texte"]');
    if (!champ) continue;

    const panneau = Object.assign(document.createElement('details'), { className: 'panneau-smileys' });
    const titre = Object.assign(document.createElement('summary'), { textContent: 'Smileys' });
    const grille = Object.assign(document.createElement('div'), { className: 'grille-smileys' });
    grille.append(
      ...SMILEYS.map((s) => {
        const bouton = Object.assign(document.createElement('button'), { type: 'button', title: s.code });
        bouton.setAttribute('aria-label', `Insérer ${s.code}, ${s.alt}`);
        bouton.append(Object.assign(document.createElement('img'), { src: `/smileys/${s.fichier}.svg`, alt: '' }));
        bouton.addEventListener('click', () => inserer(champ, s.code));
        return bouton;
      }),
    );
    panneau.append(titre, grille);
    zone.replaceChildren(panneau);
  }
}

function inserer(champ: HTMLTextAreaElement | HTMLInputElement, code: string): void {
  const debut = champ.selectionStart ?? champ.value.length;
  const fin = champ.selectionEnd ?? debut;
  const avant = champ.value.slice(0, debut);
  const apres = champ.value.slice(fin);
  const ajout = `${avant === '' || /\s$/.test(avant) ? '' : ' '}${code} `;
  champ.value = avant + ajout + apres.replace(/^ /, '');
  const curseur = avant.length + ajout.length;
  champ.focus();
  champ.setSelectionRange(curseur, curseur);
}

/** Un message en nœuds : le texte par `textContent`, les smileys en images. Jamais d'innerHTML. */
export function messageEnNoeuds(message: string): Node[] {
  return decouper(message).map((m) =>
    'smiley' in m
      ? Object.assign(document.createElement('img'), {
          className: 'smiley',
          src: `/smileys/${m.smiley.fichier}.svg`,
          alt: m.smiley.alt,
        })
      : document.createTextNode(m.texte),
  );
}

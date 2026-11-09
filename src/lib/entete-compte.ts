/**
 * Le menu du compte, dans l'en-tête d'un membre connecté : demande de
 * l'auteur du 9 novembre 2026, qui ne savait pas où cliquer pour voir son
 * profil. Son avatar, ou l'icône d'une silhouette, ouvre un petit panneau :
 * mon espace, mon profil, mes notifications, les réglages, l'avatar, la
 * déconnexion, et pour un admin la modération et ses signalements en attente.
 *
 * Il paraît avec la cloche, sur le même cookie lisible (`sbdre-connecte`), et
 * se remplit de sa lecture (évènement `sbd:compte`, lib/cloche.ts) : aucune
 * requête de plus. Jusqu'à cette réponse, il porte les liens qui ne
 * dépendent pas du pseudo.
 */
import { api } from './api-compte';

const COOKIE = 'sbdre-connecte';

interface EtatCompte {
  deconnecte?: boolean;
  pseudo?: string;
  avatar?: string;
  admin?: boolean;
  moderation?: boolean;
  juge?: boolean;
  signalements?: number;
}

export function brancherMenuCompte(): void {
  const menu = document.querySelector<HTMLDetailsElement>('[data-menu-compte]');
  if (!menu || !document.cookie.split('; ').includes(`${COOKIE}=1`)) return;
  menu.hidden = false;

  const resume = menu.querySelector<HTMLElement>('summary')!;
  const avatar = menu.querySelector<HTMLImageElement>('[data-avatar-entete]')!;
  const silhouette = menu.querySelector<HTMLElement>('[data-silhouette]')!;
  const pseudo = menu.querySelector<HTMLElement>('[data-pseudo-entete]')!;
  const profil = menu.querySelector<HTMLAnchorElement>('[data-profil-entete]')!;
  // Les liens de l'équipe : chacun dit le droit qu'il demande (lib/autorisation.php).
  const gestesEquipe = [...menu.querySelectorAll<HTMLElement>('[data-droit-entete]')];
  const file = menu.querySelector<HTMLElement>('[data-file-entete]')!;

  document.addEventListener('sbd:compte', (evenement) => {
    const etat = (evenement as CustomEvent<EtatCompte>).detail;
    if (etat.deconnecte) {
      menu.hidden = true;
      return;
    }
    const nom = String(etat.pseudo ?? '');
    pseudo.textContent = nom;
    profil.href = `/membres/${nom}/`;
    profil.parentElement!.hidden = nom === '';
    resume.setAttribute('aria-label', nom ? `Mon compte, ${nom}` : 'Mon compte');
    avatar.hidden = !etat.avatar;
    silhouette.hidden = Boolean(etat.avatar);
    if (etat.avatar && avatar.getAttribute('src') !== etat.avatar) avatar.src = etat.avatar;
    for (const geste of gestesEquipe) geste.hidden = etat[geste.dataset.droitEntete as 'moderation' | 'juge'] !== true;
    const n = Number(etat.signalements) || 0;
    file.textContent = n === 0 ? '' : ` · ${n} en attente`;
  });

  // Se referme au clic dehors et à Échap, comme le grand menu.
  document.addEventListener('click', (evenement) => {
    if (menu.open && !menu.contains(evenement.target as Node)) menu.open = false;
  });
  menu.addEventListener('keydown', (evenement) => {
    if (evenement.key === 'Escape' && menu.open) {
      menu.open = false;
      resume.focus();
    }
  });

  menu.querySelector<HTMLButtonElement>('[data-deconnexion-entete]')!.addEventListener('click', async () => {
    await api('/api/connexion.php', 'DELETE');
    location.assign('/');
  });
}

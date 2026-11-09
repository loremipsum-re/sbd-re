/**
 * La cloche de l'en-tête : le nombre de notifications non lues
 * (docs/forum.md, section 4).
 *
 * Le cookie de session est `HttpOnly`, le script ne le voit pas. La connexion
 * pose donc un second cookie, `sbdre-connecte`, lisible et sans secret
 * (lib/session.php). Sans lui, la cloche reste masquée et rien ne part vers
 * le serveur : un visiteur ne coûte aucune requête. Avec lui, une lecture
 * par minute, onglet visible seulement.
 *
 * La même lecture sert le reste de l'en-tête : chaque réponse part dans
 * l'évènement `sbd:compte`, que lisent le menu du compte (pseudo, avatar,
 * gestes de l'admin, lib/entete-compte.ts) et le point du bouton « Chat »
 * (components/Chat.astro). Une seule requête par minute pour les trois.
 */

const COOKIE = 'sbdre-connecte';

export function brancherCloche(): void {
  const cloche = document.querySelector<HTMLAnchorElement>('[data-cloche]');
  if (!cloche || !document.cookie.split('; ').includes(`${COOKIE}=1`)) return;

  const compteur = cloche.querySelector<HTMLElement>('[data-compteur]')!;
  cloche.hidden = false;
  let minuterie = 0;

  const lire = async () => {
    if (document.hidden) return;
    try {
      const reponse = await fetch('/api/notifications.php?compte=1', { credentials: 'same-origin' });
      if (reponse.status === 401) {
        // Session finie ailleurs : le cookie lisible part avec elle.
        document.cookie = `${COOKIE}=; Max-Age=0; Path=/; Secure; SameSite=Lax`;
        document.documentElement.classList.remove('connecte');
        cloche.hidden = true;
        clearInterval(minuterie);
        document.dispatchEvent(new CustomEvent('sbd:compte', { detail: { deconnecte: true } }));
        return;
      }
      const donnees = (await reponse.json()) as Record<string, unknown>;
      document.dispatchEvent(new CustomEvent('sbd:compte', { detail: donnees }));
      const n = Number(donnees.non_lues) || 0;
      compteur.textContent = n > 99 ? '99+' : String(n);
      compteur.hidden = n === 0;
      cloche.setAttribute('aria-label', n === 0 ? 'Notifications' : `Notifications, ${n} non lue${n > 1 ? 's' : ''}`);
    } catch {
      // Réseau coupé : la lecture suivante, dans une minute, réessaiera.
    }
  };

  minuterie = window.setInterval(lire, 60_000);
  document.addEventListener('visibilitychange', () => void lire());
  void lire();
}

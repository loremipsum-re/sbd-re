/**
 * Les appels du navigateur à l'API des comptes, et l'affichage de leur réponse.
 *
 * Tout passe en JSON, avec le cookie de session (même origine). L'en-tête
 * Origin que le serveur exige est posé par le navigateur lui-même sur un POST,
 * un PATCH ou un DELETE : rien à ajouter ici.
 *
 * Une panne réseau ne lève jamais : elle devient une réponse refusée, avec un
 * message lisible. Un bouton resté grisé après une panne enfermerait le
 * visiteur.
 */

export interface ReponseApi {
  ok: boolean;
  message: string;
  /** Code HTTP, 0 sans réseau. */
  statut: number;
  [cle: string]: unknown;
}

export async function api(
  url: string,
  methode: 'GET' | 'POST' | 'PATCH' | 'DELETE' = 'POST',
  corps?: Record<string, unknown>,
): Promise<ReponseApi> {
  try {
    const reponse = await fetch(url, {
      method: methode,
      credentials: 'same-origin',
      headers: corps ? { 'Content-Type': 'application/json' } : undefined,
      body: corps ? JSON.stringify(corps) : undefined,
    });
    const donnees = (await reponse.json().catch(() => ({}))) as Partial<ReponseApi>;
    return {
      ...donnees,
      ok: donnees.ok === true,
      message: typeof donnees.message === 'string' ? donnees.message : 'Réponse illisible du serveur.',
      statut: reponse.status,
    };
  } catch {
    return {
      ok: false,
      message: 'Connexion impossible. Vérifiez votre réseau, puis réessayez.',
      statut: 0,
    };
  }
}

/** Affiche un message dans la zone d'état d'un formulaire, annoncée aux lecteurs d'écran. */
export function afficher(zone: HTMLElement, texte: string, ok: boolean): void {
  zone.textContent = texte;
  zone.dataset.ok = ok ? 'oui' : 'non';
  zone.hidden = texte === '';
}

/**
 * Branche un formulaire : à l'envoi, grise le bouton, appelle `envoyer`,
 * affiche la réponse, puis suit `apres` si elle est bonne.
 */
export function brancher(
  form: HTMLFormElement,
  envoyer: (donnees: FormData) => Promise<ReponseApi>,
  apres?: (reponse: ReponseApi) => void,
): void {
  const zone = form.querySelector<HTMLElement>('[data-etat]')!;
  const bouton = form.querySelector<HTMLButtonElement>('button[type="submit"]')!;

  form.addEventListener('submit', async (evenement) => {
    evenement.preventDefault();
    bouton.disabled = true;
    afficher(zone, 'Envoi en cours…', true);

    const reponse = await envoyer(new FormData(form));

    bouton.disabled = false;
    afficher(zone, reponse.message, reponse.ok);
    if (reponse.ok) apres?.(reponse);
  });
}

/** La valeur texte d'un champ, '' s'il manque. */
export function champ(donnees: FormData, nom: string): string {
  const valeur = donnees.get(nom);
  return typeof valeur === 'string' ? valeur : '';
}

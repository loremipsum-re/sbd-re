/**
 * Le choix de mesure d'audience, lu et écrit à un seul endroit.
 *
 * Deux interfaces le recueillent : la bannière, présente sur toutes les pages,
 * et le bloc de /confidentialite/. Tant que chacune écrivait le stockage de son
 * côté, aucune ne savait ce que l'autre venait de faire. Répondre par la
 * bannière laissait la page afficher « pas encore répondu », et accepter par la
 * page laissait la bannière ouverte : un « Refuser » cliqué ensuite enregistrait
 * le refus pendant que Clarity continuait de tourner.
 *
 * Chaque choix passe donc par `enregistrerChoix`, qui émet `sbd:consentement`
 * sur `document`. Les deux interfaces écoutent cet événement et se mettent à
 * jour, quelle que soit celle qui a reçu le clic.
 *
 * Module chargé par les scripts des composants. Le script en ligne de
 * BaseLayout, qui charge la mesure dès l'arrivée, lit la même clé sans pouvoir
 * importer ce module : la garder identique des deux côtés.
 */

export type Choix = 'oui' | 'non';

export const EVENEMENT_CONSENTEMENT = 'sbd:consentement';

const CLE = 'sbd-mesure';

/** Le choix mémorisé, ou `null` tant que le visiteur n'a pas répondu. */
export function lireChoix(): Choix | null {
  try {
    const valeur = localStorage.getItem(CLE);
    return valeur === 'oui' || valeur === 'non' ? valeur : null;
  } catch {
    // Navigation privée ou stockage refusé : on considère qu'aucun choix
    // n'a été exprimé, donc rien n'est chargé.
    return null;
  }
}

export function enregistrerChoix(choix: Choix): void {
  try {
    localStorage.setItem(CLE, choix);
  } catch {
    // Sans stockage, le choix ne vaut que pour la visite en cours.
  }

  if (choix === 'oui') {
    // Chargement immédiat : recharger la page pour activer la mesure serait
    // une friction inutile.
    window.sbdChargerMesure?.();
  } else if (window.__sbdMesureChargee) {
    /*
     * Un refus après coup doit produire un effet immédiat. Le script déjà
     * chargé ne peut pas être « déchargé » proprement : on recharge donc la
     * page, ce qui repart d'un contexte sans mesure.
     */
    location.reload();
    return;
  }

  // Le choix voyage dans l'événement : sans stockage, relire localStorage
  // rendrait `null` et la page afficherait encore « pas encore répondu ».
  document.dispatchEvent(new CustomEvent(EVENEMENT_CONSENTEMENT, { detail: choix }));
}

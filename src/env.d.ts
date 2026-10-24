/// <reference types="astro/client" />

declare global {
  interface Window {
    /**
     * Charge la mesure d'audience Microsoft Clarity.
     *
     * Définie dans BaseLayout, appelée uniquement après consentement explicite :
     * soit au chargement si le visiteur a accepté lors d'une visite précédente,
     * soit par `enregistrerChoix('oui')`, depuis la bannière ou /confidentialite/.
     */
    sbdChargerMesure?: () => void;
    /** Garde-fou contre un double chargement du script de mesure. */
    __sbdMesureChargee?: boolean;
  }

  interface DocumentEventMap {
    /** Émis par `enregistrerChoix`, dans lib/consentement.ts. */
    'sbd:consentement': CustomEvent<'oui' | 'non'>;
  }
}

export {};

/**
 * Les natures de notification (public/api/lib/notifications.php), chacune
 * avec son étiquette et son icône, pour la page des notifications et
 * l'espace du membre. Demande de l'auteur du 9 novembre 2026 : une barre
 * vérifiée se distingue des autres, en vert, avec la coche du profil.
 *
 * L'icône vient d'un sprite que la page pose (`#icone-<nom>`), l'étiquette
 * la double toujours : une icône seule redeviendrait la seule information.
 */
import type { NomIcone } from './navigation';

export const NATURES = {
  badge: { etiquette: 'Barre vérifiée', icone: 'valide' },
  reponse: { etiquette: 'Réponse', icone: 'forum' },
  mention: { etiquette: 'Mention', icone: 'mention' },
  moderation: { etiquette: 'Modération', icone: 'moderation' },
} as const satisfies Record<string, { etiquette: string; icone: NomIcone }>;

export type Nature = keyof typeof NATURES;

/** Les icônes à poser en sprite sur une page qui affiche des notifications. */
export const ICONES_NATURES: NomIcone[] = Object.values(NATURES).map((n) => n.icone);

/** Une nature inconnue, venue d'une version plus récente du serveur, se lit comme une réponse. */
export const natureDe = (nature: string): Nature => (nature in NATURES ? (nature as Nature) : 'reponse');

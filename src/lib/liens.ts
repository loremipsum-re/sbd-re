/**
 * Liens personnels des athlètes, saisis à la main.
 *
 * Volontairement séparé des données de compétition : celles-ci sont publiques
 * et régénérées automatiquement chaque mois, tandis que ces liens relèvent du
 * consentement individuel et ne doivent jamais être écrasés par un script.
 */

import brut from '../data/athletes-links.json';

export type Reseau = 'instagram' | 'facebook' | 'youtube' | 'tiktok' | 'strava' | 'site';

export interface LienAthlete {
  reseau: Reseau;
  label: string;
  url: string;
}

const LABELS: Record<Reseau, string> = {
  instagram: 'Instagram',
  facebook: 'Facebook',
  youtube: 'YouTube',
  tiktok: 'TikTok',
  strava: 'Strava',
  site: 'Site personnel',
};

const table = (brut as { athletes?: Record<string, Partial<Record<Reseau, string>>> })
  .athletes ?? {};

/** Liens déclarés pour un athlète, dans un ordre stable. Vide par défaut. */
export function liensAthlete(slug: string): LienAthlete[] {
  const entree = table[slug];
  if (!entree) return [];

  return (Object.keys(LABELS) as Reseau[])
    .filter((reseau) => typeof entree[reseau] === 'string' && entree[reseau]!.length > 0)
    .map((reseau) => ({ reseau, label: LABELS[reseau], url: entree[reseau]! }));
}

/**
 * Les seuils de la vérification par la communauté, lus à la génération dans
 * public/api/lib/autorisation.php, qui les applique. Une seule source : si le
 * serveur demande 25 avis, la page /communaute/ le dit sans qu'on y pense,
 * et la génération échoue si la constante change de forme.
 *
 * Même procédé que src/lib/infographie-suggestion.ts.
 */
import source from '../../public/api/lib/autorisation.php?raw';

/** `const NOM = 20;`, espaces d'alignement compris. */
function entier(nom: string): number {
  const trouve = source.match(new RegExp(`^const ${nom}\\s*=\\s*(\\d+);`, 'm'));
  if (!trouve) {
    throw new Error(`Communauté : la constante ${nom} est introuvable dans public/api/lib/autorisation.php.`);
  }
  return Number(trouve[1]);
}

export const VOTES_MIN = entier('VOTES_MIN');
export const FAVORABLES_POURCENT = entier('FAVORABLES_POURCENT');
export const ANCIENNETE_VOTE_JOURS = entier('ANCIENNETE_VOTE_JOURS');

/** Le top des membres de /communaute/, lu dans public/api/lib/vitrine.php. */
import vitrine from '../../public/api/lib/vitrine.php?raw';

const top = vitrine.match(/^const TOP_PAR_MOUVEMENT\s*=\s*(\d+);/m);
if (!top) {
  throw new Error('Communauté : la constante TOP_PAR_MOUVEMENT est introuvable dans public/api/lib/vitrine.php.');
}
export const TOP_PAR_MOUVEMENT = Number(top[1]);

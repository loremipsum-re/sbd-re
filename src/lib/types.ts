/**
 * Formes des données officielles, telles que produites par
 * scripts/update-data.mjs. Ce fichier est le contrat entre le script de données
 * et le site : si l'un change, l'autre doit suivre.
 */

export type Sex = 'M' | 'F';

/**
 * Format de la compétition.
 *  SBD = squat + développé couché + soulevé de terre (« full power »)
 *  B   = développé couché seul
 *  S   = squat seul
 *  D   = soulevé de terre seul
 *
 * Seul SBD produit un total comparable. Les trois autres formats servent
 * uniquement à établir le record du mouvement concerné.
 */
export type EventCode = 'SBD' | 'B' | 'S' | 'D';

export interface Result {
  /** Nom canonique OpenPowerlifting. Peut porter un suffixe « #2 » en cas d'homonymie. */
  name: string;
  sex: Sex;
  event: EventCode;
  equipment: string;
  division: string;
  ageClass: string;
  /** Date de début du meet, au format ISO (AAAA-MM-JJ). */
  date: string;
  meetName: string;
  meetTown: string;
  bodyweightKg: number | null;
  /** Catégorie de poids telle qu'annoncée par la fédération : « 93 », « 120+ »… */
  weightClassKg: string;
  bestSquatKg: number | null;
  bestBenchKg: number | null;
  bestDeadliftKg: number | null;
  /**
   * ATTENTION — renseigné même pour les compétitions de développé couché seul,
   * où il vaut simplement la meilleure barre au bench. Il n'est donc comparable
   * qu'entre lignes de même `event`. Tout classement au total doit filtrer sur
   * event === 'SBD'.
   */
  totalKg: number | null;
  /** Score relatif au poids de corps. Même réserve que totalKg. */
  dots: number | null;
  goodlift: number | null;
  /** Place, ou « G » pour un athlète invité. */
  place: string;
}

/** Un des quatre classements proposés par le site. */
export type LiftKey = 'squat' | 'bench' | 'deadlift' | 'total';

/** Métal d'une distinction : coupe de classement ou médaille de podium. */
export type Metal = 'or' | 'argent' | 'bronze';

/**
 * Résultat obtenu hors de La Réunion par un athlète du site.
 *
 * Ces lignes ne comptent NI pour le classement NI pour les records : ce sont
 * des records de l'île, décernés sur un plateau réunionnais. Elles figurent
 * uniquement sur la fiche de l'athlète et sur la page qui leur est consacrée,
 * pour que son parcours soit complet.
 */
export interface ResultExterieur extends Result {
  meetCountry: string;
  meetState: string;
  /** Fédération organisatrice : FFForce, EPF, IPF… */
  federation: string;
}

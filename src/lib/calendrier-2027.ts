/**
 * Les compétitions de La Réunion prévues en 2027, pour l'infographie du
 * calendrier (components/infographies/InfographieCalendrier.astro) et
 * l'article /actualites/competitions-2027/.
 *
 * LES DATES SONT RECOPIÉES, et c'est la seule exception à la règle des
 * infographies : une compétition à venir n'est dans aucune donnée. Leur source
 * est nommée, et se change ici seulement. Elles viennent de la version 5 du
 * calendrier national FFForce 2027, du 13 octobre 2026, telle que la reprend
 * powerlifting.fr, et peuvent encore bouger.
 *
 * LES ÉDITIONS PRÉCÉDENTES SE LISENT DANS LES DONNÉES : chaque compétition
 * nomme les noms qu'elle a portés chez OpenPowerlifting, et le site y trouve
 * sa dernière édition et son nombre d'athlètes.
 */
import { results } from './data';
import { meetPath } from './slug';

export const SOURCE_CALENDRIER_2027 = 'Calendrier national FFForce 2027, version 5 du 13 octobre 2026.';

export type FormatCompetition = 'full' | 'dc';

export interface CompetitionPrevue {
  debut: string;
  fin: string;
  nom: string;
  lieu: string;
  format: FormatCompetition;
  /** Les noms de ses éditions passées, tels qu'OpenPowerlifting les écrit. */
  anciensNoms: string[];
}

export const COMPETITIONS_2027: CompetitionPrevue[] = [
  { debut: '2027-01-16', fin: '2027-01-17', nom: 'Open de la Fournaise', lieu: "L'Étang-Salé", format: 'full', anciensNoms: ['Open de la Fournaise'] },
  { debut: '2027-03-27', fin: '2027-03-28', nom: 'Bench Cup', lieu: 'Sainte-Marie', format: 'dc', anciensNoms: ['Bench Cup 974'] },
  { debut: '2027-03-27', fin: '2027-03-28', nom: 'Fanm Kapab Contest', lieu: 'Sainte-Marie', format: 'full', anciensNoms: ['Fanm Kapab Contest'] },
  { debut: '2027-07-17', fin: '2027-07-18', nom: 'Reunion Island Meet', lieu: 'Saint-Joseph', format: 'full', anciensNoms: ['Reunion Island Meet'] },
  {
    debut: '2027-09-25',
    fin: '2027-09-26',
    nom: 'Championnat de La Réunion de force athlétique',
    lieu: 'Saint-Paul',
    format: 'full',
    anciensNoms: ['Championnat Régional FA/PL La Réunion', 'Championnat de La Réunion FA/PL', 'Championnat Régional FA La Réunion'],
  },
  {
    debut: '2027-10-16',
    fin: '2027-10-17',
    nom: 'Championnat de La Réunion de développé couché',
    lieu: 'Saint-Paul',
    format: 'dc',
    anciensNoms: ['Championnat de La Réunion DC/BP', 'Championnat Régional DC/BP La Réunion', 'Championnat Régional DC La Réunion'],
  },
];

export interface EditionPassee {
  date: string;
  athletes: number;
  chemin: string;
}

/** La plus récente édition d'une compétition dans les données, ou null. */
export function derniereEdition(c: CompetitionPrevue): EditionPassee | null {
  const lignes = results.filter((r) => c.anciensNoms.includes(r.meetName));
  if (lignes.length === 0) return null;
  const date = lignes.reduce((max, r) => (r.date > max ? r.date : max), '');
  const ce_jour = lignes.filter((r) => r.date === date);
  return {
    date,
    athletes: new Set(ce_jour.map((r) => r.name)).size,
    chemin: `/competitions/${meetPath(date, ce_jour[0].meetName)}/`,
  };
}

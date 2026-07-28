/**
 * Compétitions disputées hors de La Réunion par les athlètes du site.
 *
 * Ces résultats sont volontairement tenus à l'écart du classement et des
 * records : ceux-ci récompensent des barres soulevées sur un plateau
 * réunionnais. Les inclure dénaturerait leur sens.
 *
 * Ils comptent en revanche pleinement dans le parcours d'un athlète, et c'est
 * pourquoi ils apparaissent sur sa fiche et sur une page dédiée.
 *
 * LIMITE CONNUE : le rattachement se fait par le nom exact tel qu'écrit par
 * OpenPowerlifting. Le projet distingue les homonymes par un suffixe « #2 »,
 * mais il lui arrive de ne pas les avoir repérés. Les faux appariements
 * constatés sont corrigés dans data/exterieur-exclude.json.
 */

import brut from '../data/results-exterieur.json';
import type { ResultExterieur } from './types';

export const resultatsExterieur = brut as ResultExterieur[];

/** Parcours extérieur d'un athlète, du plus récent au plus ancien. */
export function exterieurDe(name: string): ResultExterieur[] {
  return resultatsExterieur
    .filter((r) => r.name === name)
    .sort((a, b) => b.date.localeCompare(a.date));
}

export interface StatsExterieur {
  resultats: number;
  athletes: number;
  pays: number;
  federations: number;
  debut: string;
  fin: string;
}

export function statsExterieur(): StatsExterieur {
  const dates = resultatsExterieur.map((r) => r.date).sort();
  return {
    resultats: resultatsExterieur.length,
    athletes: new Set(resultatsExterieur.map((r) => r.name)).size,
    pays: new Set(resultatsExterieur.map((r) => r.meetCountry).filter(Boolean)).size,
    federations: new Set(resultatsExterieur.map((r) => r.federation).filter(Boolean)).size,
    debut: dates[0] ?? '',
    fin: dates[dates.length - 1] ?? '',
  };
}

/** Valeurs distinctes d'un champ, ordonnées par fréquence décroissante. */
export function valeursFrequentes(champ: 'meetCountry' | 'federation'): string[] {
  const comptes = new Map<string, number>();
  for (const r of resultatsExterieur) {
    const v = r[champ];
    if (v) comptes.set(v, (comptes.get(v) ?? 0) + 1);
  }
  return [...comptes.entries()].sort((a, b) => b[1] - a[1]).map(([v]) => v);
}

/**
 * Traduction française des pays.
 *
 * OpenPowerlifting les nomme en anglais. La table couvre les 28 pays
 * effectivement présents dans les données, plus quelques voisins probables ;
 * tout libellé inconnu est affiché tel quel plutôt que masqué.
 */
const PAYS_FR: Record<string, string> = {
  Argentina: 'Argentine',
  Austria: 'Autriche',
  Belarus: 'Biélorussie',
  Belgium: 'Belgique',
  Bulgaria: 'Bulgarie',
  Canada: 'Canada',
  Czechia: 'Tchéquie',
  Denmark: 'Danemark',
  England: 'Angleterre',
  Estonia: 'Estonie',
  Finland: 'Finlande',
  France: 'France',
  Germany: 'Allemagne',
  Hungary: 'Hongrie',
  Iceland: 'Islande',
  India: 'Inde',
  Ireland: 'Irlande',
  Italy: 'Italie',
  Japan: 'Japon',
  Lithuania: 'Lituanie',
  Luxembourg: 'Luxembourg',
  Madagascar: 'Madagascar',
  Malta: 'Malte',
  Mauritius: 'Maurice',
  Netherlands: 'Pays-Bas',
  Norway: 'Norvège',
  Poland: 'Pologne',
  Portugal: 'Portugal',
  Romania: 'Roumanie',
  Russia: 'Russie',
  Scotland: 'Écosse',
  Slovakia: 'Slovaquie',
  Slovenia: 'Slovénie',
  'South Africa': 'Afrique du Sud',
  Spain: 'Espagne',
  Sweden: 'Suède',
  Switzerland: 'Suisse',
  UK: 'Royaume-Uni',
  Ukraine: 'Ukraine',
  'United Kingdom': 'Royaume-Uni',
  USA: 'États-Unis',
  Wales: 'Pays de Galles',
};

export function paysFr(pays: string): string {
  return PAYS_FR[pays] ?? pays;
}

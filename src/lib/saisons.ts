/**
 * Les saisons : une année civile de compétitions à La Réunion.
 *
 * TOUT EST COMPTÉ à la génération, comme le reste du site : les données
 * changent chaque mois, et un chiffre recopié serait faux à la mise à jour
 * suivante. Une saison existe dès qu'un résultat porte son année.
 */
import { fullPowerResults, results } from './data';
import { listeMeets, type Meet } from './meets';
import { recordsBattusEn } from './records';

export interface Saison {
  annee: string;
  /** Les compétitions de l'année, de la plus récente à la plus ancienne. */
  competitions: Meet[];
  athletes: number;
  resultats: number;
  /** Records battus cette année-là et qui tiennent toujours. */
  records: number;
}

const anneeDe = (date: string) => date.slice(0, 4);

/** Les saisons, de la plus récente à la plus ancienne. */
export function listeSaisons(): Saison[] {
  const meets = listeMeets();
  const annees = [...new Set(results.map((r) => anneeDe(r.date)))].sort().reverse();
  return annees.map((annee) => {
    const duAn = results.filter((r) => anneeDe(r.date) === annee);
    return {
      annee,
      competitions: meets
        .filter((m) => anneeDe(m.date) === annee)
        .sort((a, b) => b.date.localeCompare(a.date)),
      athletes: new Set(duAn.map((r) => r.name)).size,
      resultats: duAn.length,
      records: recordsBattusEn(annee).length,
    };
  });
}

/** Les résultats en full power d'une saison, pour son classement aux Dots. */
export function fullPowerDe(annee: string) {
  return fullPowerResults.filter((r) => anneeDe(r.date) === annee);
}

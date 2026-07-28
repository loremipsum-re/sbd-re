/**
 * Données des pages par catégorie de poids.
 *
 * Une catégorie est le couple d'un sexe et d'une classe de poids : « hommes
 * −93 kg ». C'est l'unité dans laquelle se dispute réellement une compétition,
 * et donc celle dans laquelle un record a un sens.
 */

import {
  compareWeightClass,
  parseWeightClass,
  type EquipmentGroup,
  type WeightClass,
} from './categories';
import { fullPowerResults, results } from './data';
import { meilleurTotalParAthlete, type AthleteRanking } from './rankings';
import type { Result, Sex } from './types';

export interface CategoriePage {
  sex: Sex;
  /** « hommes » ou « femmes », tel qu'il apparaît dans l'URL. */
  sexeSlug: string;
  sexeLabel: string;
  classe: WeightClass;
  /** Classe telle qu'elle apparaît dans l'URL : « 93 », « 120-plus ». */
  classeSlug: string;
  /** Chemin complet : « hommes/93 ». */
  chemin: string;
  athletes: number;
  resultats: number;
}

export const SEXE_SLUG: Record<Sex, string> = { M: 'hommes', F: 'femmes' };
export const SEXE_LABEL: Record<Sex, string> = { M: 'Hommes', F: 'Femmes' };

/**
 * « 120+ » devient « 120-plus ».
 *
 * Le signe plus a une signification particulière dans une adresse : il y code
 * un espace. Le laisser tel quel produirait une URL fragile, interprétée
 * différemment selon les navigateurs et les serveurs.
 */
export function classeSlug(id: string): string {
  return id.endsWith('+') ? `${id.slice(0, -1)}-plus` : id;
}

export function classeDepuisSlug(slug: string): string {
  return slug.endsWith('-plus') ? `${slug.slice(0, -5)}+` : slug;
}

/** Catégories réellement disputées, ordonnées par sexe puis par poids. */
export function listeCategories(source: Result[] = fullPowerResults): CategoriePage[] {
  const parCle = new Map<string, { sex: Sex; id: string; noms: Set<string>; lignes: number }>();

  for (const r of source) {
    if (!r.weightClassKg) continue;
    const cle = `${r.sex}|${r.weightClassKg}`;
    const entree = parCle.get(cle) ?? {
      sex: r.sex,
      id: r.weightClassKg,
      noms: new Set<string>(),
      lignes: 0,
    };
    entree.noms.add(r.name);
    entree.lignes++;
    parCle.set(cle, entree);
  }

  const pages: CategoriePage[] = [];
  for (const { sex, id, noms, lignes } of parCle.values()) {
    const classe = parseWeightClass(id);
    if (!classe) continue;
    pages.push({
      sex,
      sexeSlug: SEXE_SLUG[sex],
      sexeLabel: SEXE_LABEL[sex],
      classe,
      classeSlug: classeSlug(id),
      chemin: `${SEXE_SLUG[sex]}/${classeSlug(id)}`,
      athletes: noms.size,
      resultats: lignes,
    });
  }

  return pages.sort(
    (a, b) => a.sex.localeCompare(b.sex) || compareWeightClass(a.classe, b.classe),
  );
}

/** Classement d'une catégorie, du meilleur total au plus faible. */
export function athletesDeCategorie(sex: Sex, classeId: string): AthleteRanking[] {
  return meilleurTotalParAthlete(
    fullPowerResults.filter((r) => r.sex === sex && r.weightClassKg === classeId),
  );
}

/** Types d'équipement présents dans une catégorie, pour ne rien afficher de vide. */
export function equipementsDeCategorie(
  sex: Sex,
  classeId: string,
): EquipmentGroup[] {
  const vus = new Set<EquipmentGroup>();
  for (const r of results) {
    if (r.sex !== sex || r.weightClassKg !== classeId) continue;
    vus.add(
      r.equipment === 'Wraps' ? 'wraps' : r.equipment === 'Raw' ? 'raw' : 'equipped',
    );
  }
  return (['raw', 'wraps', 'equipped'] as EquipmentGroup[]).filter((e) => vus.has(e));
}

/**
 * Classements : meilleure performance par athlète, et rangs.
 *
 * LE PIÈGE DE CE JEU DE DONNÉES, à garder en tête en lisant ce fichier :
 * les compétitions d'un seul mouvement ont un `totalKg` renseigné, qui vaut
 * simplement la barre disputée, ainsi qu'un score Dots calculé dessus. Un
 * « total » de 130 kg au développé couché se retrouverait donc à côté d'un
 * total full power de 500 kg.
 *
 * Tout classement au total part donc de `fullPowerResults`, jamais de
 * `results`. C'est la seule protection contre un podium absurde.
 *
 * DEUXIÈME SUBTILITÉ : le meilleur total et le meilleur Dots d'un athlète ne
 * proviennent pas forcément de la même compétition. Le Dots rapporte la
 * performance au poids de corps ; en descendant de catégorie, on peut faire un
 * meilleur Dots avec un total plus faible. Les deux sont donc calculés
 * séparément, chacun avec sa date.
 */

import { equipmentGroup, type EquipmentGroup } from './categories';
import { resultatsExterieur } from './exterieur';
import { fullPowerResults } from './data';
import { athleteSlug } from './slug';
import type { Result, Sex } from './types';

export interface AthleteRanking {
  name: string;
  slug: string;
  sex: Sex;

  /** Meilleur total réalisé en full power. */
  totalKg: number;
  /** Compétition où ce meilleur total a été réalisé. */
  date: string;
  meetName: string;
  /** Commune de cette compétition. Elle qualifie le total, pas l'athlète. */
  meetTown: string;

  /** Meilleur score Dots, qui peut venir d'une AUTRE compétition. */
  dots: number;
  dotsDate: string;
  dotsMeetName: string;
  dotsMeetTown: string;

  bodyweightKg: number | null;
  weightClassKg: string;
  equipment: string;
  equipmentGroup: EquipmentGroup;
  /** Division fédérale : Seniors, Juniors, Masters 1… */
  division: string;
  /** Tranche d'âge telle que publiée : « 24-34 », « 45-49 »… */
  ageClass: string;

  /**
   * Places gagnées, en positif, ou perdues, en négatif, au classement GÉNÉRAL
   * sur les douze derniers mois.
   *
   * `null` dans deux cas, et c'est délibéré : l'athlète n'a pas concouru dans
   * la fenêtre, ou il vient d'entrer au classement. Mesuré : sur 312 classés,
   * 211 avaient « perdu » des places sans avoir soulevé une barre, simplement
   * parce que 84 nouveaux s'étaient intercalés. Afficher cela les ferait
   * passer pour des athlètes en perte de vitesse, ce qui serait faux.
   *
   * L'écart est calculé ici, et non dans le gabarit : sur une page de
   * catégorie, le rang affiché est celui de la catégorie, alors que le
   * mouvement, lui, porte sur le classement entier.
   */
  evolution: number | null;

  /** Entré au classement pendant les douze derniers mois. */
  nouveau: boolean;

  /** Compétitions full power disputées à La Réunion. */
  competitions: number;
  /** Compétitions disputées hors de l'île, tous formats confondus. */
  competitionsExterieur: number;
}

/**
 * Un athlète, une ligne.
 *
 * On retient la meilleure performance et non la plus récente : c'est un
 * classement de records, pas un instantané de forme.
 */
export function meilleurTotalParAthlete(
  source: Result[] = fullPowerResults,
  /** Faux pour le classement de référence, qui sert justement à la calculer. */
  avecEvolution = true,
): AthleteRanking[] {
  // Nombre de compétitions disputées hors de l'île, par athlète.
  const horsIle = new Map<string, Set<string>>();
  for (const r of resultatsExterieur) {
    const set = horsIle.get(r.name) ?? new Set<string>();
    set.add(`${r.date}|${r.meetName}`);
    horsIle.set(r.name, set);
  }

  const parNom = new Map<
    string,
    { meilleurTotal: Result; meilleurDots: Result; competitions: Set<string> }
  >();

  for (const r of source) {
    if (r.totalKg === null || r.totalKg <= 0) continue;

    const courant = parNom.get(r.name);
    if (!courant) {
      parNom.set(r.name, {
        meilleurTotal: r,
        meilleurDots: r,
        competitions: new Set([`${r.date}|${r.meetName}`]),
      });
      continue;
    }
    courant.competitions.add(`${r.date}|${r.meetName}`);
    if (r.totalKg > (courant.meilleurTotal.totalKg ?? 0)) courant.meilleurTotal = r;
    if ((r.dots ?? 0) > (courant.meilleurDots.dots ?? 0)) courant.meilleurDots = r;
  }

  const classement: AthleteRanking[] = [];
  for (const { meilleurTotal, meilleurDots, competitions } of parNom.values()) {
    classement.push({
      name: meilleurTotal.name,
      slug: athleteSlug(meilleurTotal.name),
      sex: meilleurTotal.sex,

      totalKg: meilleurTotal.totalKg as number,
      date: meilleurTotal.date,
      meetName: meilleurTotal.meetName,
      meetTown: meilleurTotal.meetTown,

      dots: meilleurDots.dots ?? 0,
      dotsDate: meilleurDots.date,
      dotsMeetName: meilleurDots.meetName,
      dotsMeetTown: meilleurDots.meetTown,

      bodyweightKg: meilleurTotal.bodyweightKg,
      weightClassKg: meilleurTotal.weightClassKg,
      equipment: meilleurTotal.equipment,
      equipmentGroup: equipmentGroup(meilleurTotal.equipment),
      division: meilleurTotal.division,
      ageClass: meilleurTotal.ageClass,

      evolution: null,
      nouveau: false,

      competitions: competitions.size,
      competitionsExterieur: horsIle.get(meilleurTotal.name)?.size ?? 0,
    });
  }

  // Tri par défaut : le total brut. Les autres tris sont proposés côté client.
  classement.sort((a, b) => b.totalKg - a.totalKg || a.name.localeCompare(b.name));

  if (avecEvolution) {
    const { rangs, actifs } = photoIlYaUnAn(source);
    classement.forEach((e, i) => {
      if (!actifs.has(e.name)) return;
      const avant = rangs.get(e.name);
      if (avant === undefined) {
        e.nouveau = true;
        return;
      }
      const ecart = avant - (i + 1);
      e.evolution = ecart === 0 ? null : ecart;
    });
  }

  return classement;
}

/**
 * Rang de chaque athlète tel qu'il était DOUZE MOIS PLUS TÔT.
 *
 * ANCRÉ SUR LA DERNIÈRE DATE DES DONNÉES, jamais sur l'horloge. Avec
 * `new Date()`, la fenêtre glisserait chaque jour : le HTML changerait sans
 * qu'aucune donnée n'ait bougé, et la génération cesserait d'être
 * reproductible. Ancrée sur les données, elle ne bouge qu'à la mise à jour
 * mensuelle, ce qui est précisément le rythme de l'information.
 *
 * Le classement retenant le MEILLEUR total de chaque athlète, un rang ne
 * baisse jamais par contre-performance : il baisse parce qu'un autre a
 * progressé ou qu'un nouveau s'est intercalé. C'est bien un mouvement relatif.
 */
function photoIlYaUnAn(source: Result[]): {
  rangs: Map<string, number>;
  actifs: Set<string>;
} {
  const derniere = source.reduce((max, r) => (r.date > max ? r.date : max), '');
  if (!derniere) return { rangs: new Map(), actifs: new Set() };

  const seuil = new Date(`${derniere}T12:00:00Z`);
  seuil.setUTCFullYear(seuil.getUTCFullYear() - 1);
  const limite = seuil.toISOString().slice(0, 10);

  // `false` : sans quoi le calcul de l'évolution s'appellerait lui-même.
  const avant = meilleurTotalParAthlete(
    source.filter((r) => r.date <= limite),
    false,
  );

  return {
    rangs: new Map(avant.map((e, i) => [e.name, i + 1])),
    actifs: new Set(source.filter((r) => r.date > limite).map((r) => r.name)),
  };
}

/**
 * Top toutes catégories confondues, classé aux points Dots.
 *
 * Dots rapporte la performance au poids de corps : c'est ce qui permet de
 * placer un athlète de 59 kg et un athlète de 120 kg sur la même échelle. Le
 * total brut, lui, favoriserait mécaniquement les plus lourds.
 */
export function topDots(n = 10, source?: Result[]): AthleteRanking[] {
  return [...meilleurTotalParAthlete(source)]
    .sort((a, b) => b.dots - a.dots || b.totalKg - a.totalKg)
    .slice(0, n);
}

export interface Rang {
  rang: number;
  total: number;
  /** Meilleurs X pour cent, arrondi vers le haut. 5 signifie « top 5 % ». */
  centile: number;
}

export interface RangsAthlete {
  /** Rang parmi tous les athlètes de l'île. */
  global: Rang;
  /** Rang parmi les athlètes du même sexe. */
  sexe: Rang;
  dots: number;
}

/**
 * Positions d'un athlète aux points Dots : au général, et dans son sexe.
 *
 * Les deux se lisent différemment. « 12e de La Réunion » situe dans la scène
 * entière ; « 3e chez les femmes » situe dans la compétition réelle, puisque
 * les catégories sont séparées le jour du plateau.
 *
 * Calculé une fois puis mémorisé : la fonction est appelée pour chacune des
 * 312 fiches, et retrier le classement à chaque appel multiplierait le temps
 * de build sans rien apporter.
 */
let cacheRangs: Map<string, RangsAthlete> | null = null;

export function rangsDots(name: string): RangsAthlete | null {
  if (!cacheRangs) {
    cacheRangs = new Map();
    const tous = [...meilleurTotalParAthlete()].sort(
      (a, b) => b.dots - a.dots || b.totalKg - a.totalKg,
    );

    const position = (liste: AthleteRanking[]): Map<string, Rang> => {
      const m = new Map<string, Rang>();
      liste.forEach((e, i) => {
        m.set(e.name, {
          rang: i + 1,
          total: liste.length,
          centile: Math.max(1, Math.ceil(((i + 1) / liste.length) * 100)),
        });
      });
      return m;
    };

    const globaux = position(tous);
    const parSexe = new Map<Sex, Map<string, Rang>>([
      ['M', position(tous.filter((e) => e.sex === 'M'))],
      ['F', position(tous.filter((e) => e.sex === 'F'))],
    ]);

    for (const e of tous) {
      const g = globaux.get(e.name);
      const s = parSexe.get(e.sex)?.get(e.name);
      if (g && s) cacheRangs.set(e.name, { global: g, sexe: s, dots: e.dots });
    }
  }
  return cacheRangs.get(name) ?? null;
}

/** Historique complet d'un athlète, de la compétition la plus ancienne à la plus récente. */
export function historiqueAthlete(name: string, source: Result[]): Result[] {
  return source
    .filter((r) => r.name === name)
    .sort((a, b) => a.date.localeCompare(b.date) || a.event.localeCompare(b.event));
}

/**
 * Glossaire des termes du powerlifting, pour qui arrive sur le site sans
 * connaître la discipline.
 *
 * UN SEUL ENDROIT POUR CES TEXTES. Ils sont affichés par les infobulles, et
 * pourront l'être demain par une page « comprendre les classements » ou par
 * les intitulés de filtres. Les écrire dans les gabarits garantirait qu'une
 * définition change à un endroit et pas à l'autre.
 *
 * Les libellés d'équipement ne sont pas recopiés : ils viennent de
 * EQUIPMENT_GROUPS, seule source des trois familles. Le champ `hint` qui y
 * dormait depuis l'origine, sans usage, sert enfin de résumé court.
 */

import { EQUIPMENT_GROUPS } from './categories';

export interface Terme {
  /** Identifiant stable, employé comme `id` HTML de l'infobulle. */
  id: string;
  /** Le mot tel qu'il apparaît dans les listes. */
  terme: string;
  /** Résumé d'une ligne, pour un attribut `title`. */
  resume: string;
  /** Explication complète, affichée par l'infobulle. */
  definition: string;
}

const EQUIPEMENTS: Record<string, { resume: string; definition: string }> = {
  raw: {
    resume: 'Sans équipement de force',
    definition:
      "Sans textile qui restitue de l'énergie. La ceinture, les genouillères souples et les bandes de poignets restent autorisées : elles soutiennent sans propulser.",
  },
  wraps: {
    resume: 'Bandes de genoux autorisées',
    definition:
      "Avec bandes de genoux. Serrées avant la barre, elles restituent de l'énergie en bas du squat et autorisent des charges plus lourdes qu'en raw.",
  },
  equipped: {
    resume: 'Combinaison de force',
    definition:
      "Avec combinaison de force, et chemise au développé couché. Ces textiles restituent une part de l'effort : les charges y sont nettement supérieures, et ne se comparent donc pas au raw.",
  },
};

/** Les trois familles d'équipement, tirées de leur source unique. */
const TERMES_EQUIPEMENT: Terme[] = EQUIPMENT_GROUPS.map((groupe) => ({
  id: `glossaire-${groupe.id}`,
  terme: groupe.label,
  resume: EQUIPEMENTS[groupe.id]?.resume ?? groupe.hint,
  definition: EQUIPEMENTS[groupe.id]?.definition ?? groupe.hint,
}));

export const GLOSSAIRE: Terme[] = [
  ...TERMES_EQUIPEMENT,
  {
    id: 'glossaire-total',
    terme: 'Total',
    resume: 'Somme des trois meilleures barres réussies',
    definition:
      "Somme des trois meilleures barres réussies au squat, au développé couché et au soulevé de terre, dans une même compétition. C'est la valeur qui classe les athlètes ici.",
  },
  {
    id: 'glossaire-dots',
    terme: 'Dots',
    resume: 'Score qui ramène le total au poids de corps',
    definition:
      "Score qui ramène un total au poids de corps, pour comparer des gabarits différents. 500 kg soulevés à 70 kg valent plus de points que les mêmes 500 kg à 120 kg.",
  },
  {
    id: 'glossaire-full-power',
    terme: 'Full power',
    resume: 'Les trois mouvements dans la même compétition',
    definition:
      "Compétition où les trois mouvements s'enchaînent. Le total n'a de sens que là : sur une compétition d'un seul mouvement, il ne vaut que cette unique barre.",
  },
  {
    id: 'glossaire-categorie',
    terme: 'Catégorie',
    resume: 'Tranche de poids de corps',
    definition:
      "Tranche de poids de corps dans laquelle l'athlète concourt. « 93 kg » signifie jusqu'à 93 kg, et « 120+ » au-delà de 120 kg.",
  },
  {
    id: 'glossaire-officiel',
    terme: 'Compétiteur officiel',
    resume: 'Performance réalisée en compétition homologuée',
    definition:
      "Performance réalisée en compétition homologuée, devant des juges, et publiée par OpenPowerlifting. C'est ce que signale la coche à côté d'un nom.",
  },
];

/** Retrouve un terme par son identifiant, sans le chercher à la main. */
export function terme(id: string): Terme | undefined {
  return GLOSSAIRE.find((t) => t.id === id);
}

/** Résumé d'une famille d'équipement, pour un attribut `title`. */
export function resumeEquipement(groupe: string): string {
  return EQUIPEMENTS[groupe]?.resume ?? '';
}

/**
 * Glossaire des termes du powerlifting, pour qui arrive sur le site sans
 * connaître la discipline.
 *
 * UN SEUL ENDROIT POUR CES TEXTES. Ils sont affichés par trois mécanismes :
 *
 *   le glossaire repliable des listes   Glossaire.astro et Infobulle.astro,
 *   le terme souligné d'une page        Terme.astro,
 *   le terme souligné d'un article      lexique-markdown.ts, à la génération,
 *                                       chapeau compris.
 *
 * Les écrire dans les gabarits garantirait qu'une définition change à un
 * endroit et pas à l'autre.
 *
 * DEUX LONGUEURS PAR TERME. `definition` est l'explication complète du
 * glossaire repliable, exemple compris. `phrase` est la définition d'une seule
 * phrase de la bulle d'un terme souligné, choix de l'auteur du 27 octobre
 * 2026 : la bulle s'ouvre au milieu d'une lecture et doit se lire d'un regard.
 * D'où une information par phrase, et deux virgules au plus. Chaque phrase
 * reprend ce que le site publie déjà, source citée en regard : aucune
 * n'apporte un fait que le site ne donne pas ailleurs.
 *
 * UNE PHRASE CHANGÉE ICI CHANGE AUSSI DANS LES ARTICLES, à la génération
 * suivante. Astro garde le HTML des articles d'une génération à l'autre, et
 * astro.config.mjs le lui fait recalculer dès que ce fichier change : voir
 * l'empreinte du lexique. Avec `npm run dev`, relancer le serveur.
 *
 * Les libellés d'équipement ne sont pas recopiés : ils viennent de
 * EQUIPMENT_GROUPS, seule source des trois familles. Le champ `hint` qui y
 * dormait depuis l'origine, sans usage, sert enfin de résumé court.
 *
 * L'EXTENSION `.ts` DES IMPORTS EST VOULUE, ici et dans lexique-markdown.ts.
 * astro.config.mjs charge ce fichier pour le greffon des articles, et Node le
 * lit alors directement, sans passer par Vite : il exige le nom de fichier
 * complet. Sans l'extension, Astro se rabattrait sur un chargement par Vite,
 * plus lent, qui fonctionne aussi.
 */

import { EQUIPMENT_GROUPS, type EquipmentGroup } from './categories.ts';

/** Clé d'un terme, celle qu'on écrit dans `<Terme cle="…">`. */
export type CleTerme =
  | EquipmentGroup
  | 'total'
  | 'dots'
  | 'full-power'
  | 'categorie'
  | 'officiel'
  | 'division'
  | 'tranche-age'
  | 'record'
  | 'barre'
  | 'homologue'
  | 'poids-corps';

export interface Terme {
  /** Clé stable. Le terme souligné en tire l'identifiant de sa bulle. */
  cle: CleTerme;
  /** Identifiant HTML de l'infobulle du glossaire repliable. */
  id: string;
  /** Le mot tel qu'il apparaît dans les listes. */
  terme: string;
  /** Résumé d'une ligne, pour un attribut `title`. */
  resume: string;
  /** Explication complète, affichée par le glossaire repliable. */
  definition: string;
  /** Définition d'une phrase, affichée par la bulle d'un terme souligné. */
  phrase: string;
  /**
   * Formes reconnues dans un article, chapeau puis corps, où la première
   * apparition est soulignée à la génération. Un terme sans formes n'y est
   * jamais souligné.
   */
  formes?: RegExp;
  /** Figure dans le glossaire repliable posé au-dessus des listes. */
  liste: boolean;
  /** Famille d'équipement, pour les trois termes qui en désignent une. */
  equipement?: EquipmentGroup;
}

/**
 * Un mot entier, accents compris. `\b` ignore les lettres accentuées : il
 * verrait une limite de mot au milieu de « équipé ». D'où ces bornes Unicode.
 */
const mot = (motif: string): RegExp =>
  new RegExp(`(?<![\\p{L}\\p{N}])(?:${motif})(?![\\p{L}\\p{N}])`, 'iu');

/** Une espace, insécable ou non, entre les mots d'un terme composé. */
const ESPACE = '[\\s\\u00a0]+';

/** L'apostrophe droite du texte source, ou la typographique du rendu. */
const APOSTROPHE = "['’]";

const EQUIPEMENTS: Record<
  string,
  { resume: string; definition: string; phrase: string; formes?: RegExp }
> = {
  raw: {
    resume: 'Sans équipement de force',
    definition:
      "Sans textile qui restitue de l'énergie. La ceinture, les genouillères souples et les bandes de poignets restent autorisées : elles soutiennent sans propulser.",
    // La première phrase de la définition ci-dessus.
    phrase: "Se dit d'une performance réalisée sans textile qui restitue de l'énergie.",
    formes: mot('raw'),
  },
  /*
   * Aucune forme pour les bandes. « Bandes de poignets », dans l'article
   * « Raw et équipé », relève du raw : le souligner y donnerait la mauvaise
   * définition. Aucun des 1 238 résultats de l'île n'est d'ailleurs en bandes,
   * relevé à l'audit du 27 octobre 2026.
   */
  wraps: {
    resume: 'Bandes de genoux autorisées',
    definition:
      "Avec bandes de genoux. Serrées avant la barre, elles restituent de l'énergie en bas du squat et autorisent des charges plus lourdes qu'en raw.",
    phrase:
      "Se dit d'une performance réalisée avec des bandes de genoux, qui restituent de l'énergie en bas du squat.",
  },
  equipped: {
    resume: 'Combinaison de force',
    definition:
      "Avec combinaison de force, et chemise au développé couché. Ces textiles restituent une part de l'effort : les charges y sont nettement supérieures, et ne se comparent donc pas au raw.",
    // Article « Raw et équipé » : « Un athlète équipé porte une combinaison
    // de force, et une chemise au développé couché. »
    phrase:
      "Se dit d'une performance réalisée en combinaison de force, et en chemise au développé couché.",
    formes: mot('équipée?s?'),
  },
};

/** Les trois familles d'équipement, tirées de leur source unique. */
const TERMES_EQUIPEMENT: Terme[] = EQUIPMENT_GROUPS.map((groupe) => ({
  cle: groupe.id,
  id: `glossaire-${groupe.id}`,
  terme: groupe.label,
  resume: EQUIPEMENTS[groupe.id]?.resume ?? groupe.hint,
  definition: EQUIPEMENTS[groupe.id]?.definition ?? groupe.hint,
  phrase: EQUIPEMENTS[groupe.id]?.phrase ?? groupe.hint,
  formes: EQUIPEMENTS[groupe.id]?.formes,
  liste: true,
  equipement: groupe.id,
}));

/**
 * Les termes hors glossaire des listes. Leur définition complète est leur
 * phrase : ils ne s'affichent que dans une bulle.
 */
const sansListe = (t: Omit<Terme, 'id' | 'definition' | 'liste'>): Terme => ({
  ...t,
  id: `glossaire-${t.cle}`,
  definition: t.phrase,
  liste: false,
});

export const GLOSSAIRE: Terme[] = [
  ...TERMES_EQUIPEMENT,
  {
    cle: 'total',
    id: 'glossaire-total',
    terme: 'Total',
    resume: 'Somme des trois meilleures barres réussies',
    definition:
      "Somme des trois meilleures barres réussies au squat, au développé couché et au soulevé de terre, dans une même compétition. C'est la valeur qui classe les athlètes ici.",
    // La première phrase de la définition ci-dessus.
    phrase:
      'Somme des trois meilleures barres réussies au squat, au développé couché et au soulevé de terre, dans une même compétition.',
    formes: mot('total|totaux'),
    liste: true,
  },
  {
    cle: 'dots',
    id: 'glossaire-dots',
    terme: 'Dots',
    resume: 'Score qui ramène le total au poids de corps',
    definition:
      'Score qui ramène un total au poids de corps, pour comparer des gabarits différents. 500 kg soulevés à 70 kg valent plus de points que les mêmes 500 kg à 120 kg.',
    // La première phrase de la définition ci-dessus.
    phrase: 'Score qui ramène un total au poids de corps, pour comparer des gabarits différents.',
    formes: mot('dots'),
    liste: true,
  },
  {
    cle: 'full-power',
    id: 'glossaire-full-power',
    terme: 'Full power',
    resume: 'Les trois mouvements dans la même compétition',
    definition:
      "Compétition où les trois mouvements s'enchaînent. Le total n'a de sens que là : sur une compétition d'un seul mouvement, il ne vaut que cette unique barre.",
    // Page À propos : « squat, développé couché et soulevé de terre lors de
    // la même compétition ».
    phrase:
      "Compétition où s'enchaînent le squat, le développé couché et le soulevé de terre.",
    formes: mot(`full${ESPACE}power`),
    liste: true,
  },
  {
    cle: 'categorie',
    id: 'glossaire-categorie',
    terme: 'Catégorie',
    resume: 'Tranche de poids de corps',
    definition:
      "Tranche de poids de corps dans laquelle l'athlète concourt. « 93 kg » signifie jusqu'à 93 kg, et « 120+ » au-delà de 120 kg.",
    // La première phrase de la définition ci-dessus.
    phrase: "Tranche de poids de corps dans laquelle l'athlète concourt.",
    /*
     * DEUX TOURS ÉCARTÉS, où le mot change de sens. « Les deux catégories se
     * classent séparément », dans l'article « Raw et équipé », oppose le raw
     * à l'équipé. Et une catégorie d'âge ou d'équipement n'est pas une
     * catégorie de poids. Souligné là, le mot recevrait la mauvaise
     * définition : l'apparition suivante prend sa place.
     */
    formes: mot(
      `(?<!deux${ESPACE})catégories?(?!${ESPACE}d${APOSTROPHE}(?:âge|équipement))`,
    ),
    liste: true,
  },
  {
    cle: 'officiel',
    id: 'glossaire-officiel',
    terme: 'Compétiteur officiel',
    resume: 'Performance réalisée en compétition homologuée',
    definition:
      "Performance réalisée en compétition homologuée, devant des juges, et publiée par OpenPowerlifting. C'est ce que signale la coche à côté d'un nom.",
    // Infographie de la fiche : « Compétiteur officiel. Ses performances
    // viennent de compétitions homologuées. »
    phrase: 'Athlète dont les performances viennent de compétitions homologuées.',
    liste: true,
  },
  // Article « Les divisions d'âge » : « Les divisions vont des Sub-Juniors aux
  // Masters 4. » Commentaire de classement.astro : « La DIVISION est fédérale
  // et dépend de l'année de naissance ».
  sansListe({
    cle: 'division',
    terme: 'Division',
    resume: "Groupe d'âge fixé par l'année de naissance",
    phrase: "Groupe d'âge fixé par l'année de naissance, des Sub-Juniors aux Masters 4.",
    formes: mot(`divisions?(?:${ESPACE}d${APOSTROPHE}âge)?`),
  }),
  // Même commentaire : « La TRANCHE D'ÂGE est l'âge réel au moment du
  // plateau. » Article « Les divisions d'âge » : « La tranche 24-34 ans ».
  sansListe({
    cle: 'tranche-age',
    terme: "Tranche d'âge",
    resume: 'Âge le jour de la compétition',
    phrase:
      "Tranche où se situe l'âge de l'athlète le jour de la compétition, par exemple 24-34 ans.",
    formes: mot(`tranches?${ESPACE}d${APOSTROPHE}âge`),
  }),
  // Page des records : « La meilleure barre réalisée en compétition à
  // La Réunion ». Page À propos : records par mouvement et record au total.
  sansListe({
    cle: 'record',
    terme: 'Record',
    resume: 'Meilleure performance en compétition à La Réunion',
    phrase:
      'Meilleure performance réalisée en compétition à La Réunion, sur un mouvement ou au total.',
    /*
     * Un record du monde, de France ou d'Europe, ou un record personnel, n'est
     * pas un record de l'île : ces tours sont écartés, pour la même raison
     * que les deux sens de « catégorie ».
     */
    formes: mot(
      `records?(?!${ESPACE}(?:du${ESPACE}monde|de${ESPACE}France|d${APOSTROPHE}Europe|personnels?))`,
    ),
  }),
  // Infographie du total : « Chaque mouvement se tente trois fois. La meilleure
  // barre validée compte. » Page 404 : « Barre non validée ». Une barre peut
  // donc échouer : « charge soulevée » contredisait les « barres réussies » de
  // la définition du total et les « neuf barres, trois essais » d'un article.
  sansListe({
    cle: 'barre',
    terme: 'Barre',
    resume: 'Charge tentée sur un mouvement',
    phrase: 'Charge tentée au squat, au développé couché ou au soulevé de terre.',
    formes: mot('barres?'),
  }),
  // Page À propos : « Le classement officiel présenté ici ne contient que des
  // résultats homologués ». Définition de « Compétiteur officiel » :
  // « compétition homologuée, devant des juges ».
  sansListe({
    cle: 'homologue',
    terme: 'Homologué',
    resume: 'Compétition officielle, devant des juges',
    phrase: "Se dit d'une compétition officielle et des résultats validés par ses juges.",
    formes: mot('homologu(?:é|ée|és|ées)'),
  }),
  // Titre des étiquettes du classement : « Poids de corps pesé le jour de la
  // compétition ». Article « Les catégories de poids » : « Le poids relevé ».
  sansListe({
    cle: 'poids-corps',
    terme: 'Poids de corps',
    resume: 'Poids de corps pesé le jour de la compétition',
    phrase: "Poids de l'athlète relevé à la pesée, le jour de la compétition.",
    formes: mot(`poids${ESPACE}de${ESPACE}corps`),
  }),
];

/** Retrouve un terme par son identifiant, sans le chercher à la main. */
export function terme(id: string): Terme | undefined {
  return GLOSSAIRE.find((t) => t.id === id);
}

/**
 * Retrouve un terme par sa clé. Une clé inconnue arrête la génération : un
 * terme souligné sans définition serait une promesse vide.
 */
export function lexique(cle: CleTerme): Terme {
  const trouve = GLOSSAIRE.find((t) => t.cle === cle);
  if (!trouve) throw new Error(`Terme absent du glossaire : « ${cle} ».`);
  return trouve;
}

/** Identifiant HTML de la bulle d'un terme souligné, unique dans sa page. */
export function idBulle(cle: CleTerme): string {
  return `lexique-${cle}`;
}

/** Résumé d'une famille d'équipement, pour un attribut `title`. */
export function resumeEquipement(groupe: string): string {
  return EQUIPEMENTS[groupe]?.resume ?? '';
}

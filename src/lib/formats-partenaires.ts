/**
 * Les formats des partenaires, et la place que chaque page leur réserve.
 *
 * CHOIX DE L'AUTEUR DU 27 OCTOBRE 2026. Des partenaires DIRECTS seulement,
 * une image et un lien hébergés sur sbd.re. Des FORMATS FIXES, chacun à une
 * place réservée. Et RIEN ne s'affiche tant qu'aucun partenaire n'est actif :
 * ni cadre vide, ni encart maison.
 *
 * CE FICHIER EST LA SOURCE UNIQUE. Le schéma de la collection, le menu du CMS,
 * le composant Partenaire.astro, la page /partenariats/ et son infographie
 * lisent ici les dimensions et les pages. Changer le format d'une page revient
 * à changer un mot dans EMPLACEMENTS, et tout le reste suit.
 *
 * Il n'importe rien d'Astro : content.config.ts le lit avant que les
 * collections existent.
 */

/**
 * Les deux images qu'un partenaire peut fournir, en pixels.
 *
 * LE RAPPORT EST LE CONTRAT, plus que la taille. C'est lui qui garantit qu'une
 * image remplit exactement la place réservée, sans bande vide ni recadrage. La
 * génération refuse donc une image qui s'en écarte de plus d'un pour cent, et
 * une image matricielle plus petite que ces dimensions. Un multiple, 2 400 ×
 * 600 par exemple, est accepté. Un SVG n'a pas de taille minimale.
 */
export const IMAGES = {
  banniere: { largeur: 1200, hauteur: 300, libelle: 'Bannière' },
  logo: { largeur: 400, hauteur: 200, libelle: 'Logo' },
} as const;

export type IdImage = keyof typeof IMAGES;

/** Les types de fichier acceptés, tels que les lit l'en-tête de l'image. */
export const EXTENSIONS_IMAGE = ['png', 'jpg', 'jpeg', 'webp', 'svg'] as const;

/** Longueur maximale de l'accroche d'une carte, en caractères. */
export const ACCROCHE_MAX = 80;

/** Le plus petit écran que le site mesure, un téléphone de 320 px de large. */
export const PLUS_PETIT_ECRAN = 320;

/**
 * La bannière sur ce plus petit écran : la colonne y vaut 288 px, les 320
 * moins les deux marges de 16 px de `.wrap`. C'est là que le texte d'une
 * bannière est le plus petit.
 */
export const BANNIERE_SUR_TELEPHONE = PLUS_PETIT_ECRAN - 2 * 16;

/**
 * Hauteur minimale du texte écrit DANS une bannière, en pixels d'image. Sur le
 * plus petit téléphone, il en reste 14 px à l'écran.
 */
export const TEXTE_BANNIERE_MIN = 60;

/**
 * Les trois formats, et leur taille d'affichage en pixels CSS.
 *
 * LA BANNIÈRE occupe toute la largeur de la colonne, 600 px au plus. Une image
 * de 1 200 px y reste nette sur un écran de densité 2, et ces 600 px tiennent
 * dans la mesure du texte courant, 679 px à 1 280 px de fenêtre. Sur
 * téléphone, elle prend la largeur de la colonne : 288 px à 320 px de large,
 * 343 px à 375 px.
 *
 * LA CARTE est la forme discrète, pour les pages de données : le logo en
 * 96 × 48 px, le nom et une phrase. Elle prend la même largeur maximale que la
 * bannière, pour qu'une page passe d'un format à l'autre sans que sa fin bouge
 * en largeur.
 *
 * LE LOGO DU PIED DE PAGE reprend l'image de la carte en plus petit, 80 × 40
 * px, dans une cible de 44 px de haut comme tout contrôle du site.
 */
export const FORMATS = {
  banniere: {
    libelle: 'Bannière',
    image: 'banniere',
    affichage: { largeur: 600, hauteur: 150 },
  },
  carte: {
    libelle: 'Carte',
    image: 'logo',
    affichage: { largeur: 96, hauteur: 48 },
  },
  logo: {
    libelle: 'Logo',
    image: 'logo',
    affichage: { largeur: 80, hauteur: 40 },
  },
} as const satisfies Record<string, { libelle: string; image: IdImage; affichage: { largeur: number; hauteur: number } }>;

export type IdFormat = keyof typeof FORMATS;
/** Les formats d'une place de contenu. Le logo vit au pied de page. */
export type FormatDeContenu = Exclude<IdFormat, 'logo'>;

interface FicheEmplacement {
  /** Le format de la place, fixe : c'est la page qui le choisit. */
  format: FormatDeContenu;
  /** Les pages concernées, pour le menu du CMS. */
  libelle: string;
  /** Où sur la page, pour le menu du CMS. */
  ou: string;
  /** Mot court pour l'infographie de /partenariats/. */
  court: string;
  /** Les pages, pour la phrase de /partenariats/ qui commence par « Sur ». */
  pages: string;
}

/**
 * LES PLACES DE CONTENU, UNE PAR PAGE AU PLUS.
 *
 * Chacune vient APRÈS le contenu de sa page, jamais au-dessus et jamais dans
 * une donnée : après le dernier tableau, la dernière liste ou la note qui les
 * suit. Le gabarit l'appelle en une ligne, en fin de contenu :
 *
 *   <Partenaire emplacement="classement" />
 *
 * LA BANNIÈRE clôt les pages qu'on lit jusqu'au bout : un article, les
 * résultats d'une compétition. LA CARTE clôt les pages de données qu'on
 * consulte et reconsulte : les catégories, les records, le classement, les
 * salles de sport.
 *
 * « Carte » désigne ici le format, une petite fiche à logo. La page des salles
 * montre une carte de l'île, au sens géographique : les textes destinés aux
 * annonceurs disent donc « page des salles », jamais « carte des salles ».
 *
 * LE BANDEAU DU PIED DE PAGE EST À PART. Il n'est pas une place de contenu et
 * ne compte pas dans la règle d'une place par page : il vient après tout le
 * reste, sur toutes les pages sauf les fiches d'athlète.
 *
 * La fiche d'athlète n'a aucune place, ni de contenu ni au pied de page, et
 * n'en aura pas : une marque y prêterait à l'athlète une caution qu'il n'a pas
 * donnée. lib/sponsors.ts fait respecter cette exclusion.
 */
export const EMPLACEMENTS = {
  article: {
    format: 'banniere',
    libelle: 'Articles',
    ou: "en fin d'article",
    court: 'articles',
    pages: 'les articles',
  },
  competition: {
    format: 'banniere',
    libelle: 'Pages de compétition',
    ou: 'après les résultats',
    court: 'compétitions',
    pages: 'les pages de compétition',
  },
  /*
   * Les deux places des catégories, la liste et chaque page, se disent d'un
   * seul mot aux annonceurs : `enumeration` fond les doublons, et la phrase
   * de /partenariats/ ne nomme pas deux fois les catégories.
   */
  categorie: {
    format: 'carte',
    libelle: 'Pages de catégorie',
    ou: 'après le classement de la catégorie',
    court: 'catégories',
    pages: 'les catégories',
  },
  categories: {
    format: 'carte',
    libelle: 'Liste des catégories',
    ou: 'après les tableaux',
    court: 'catégories',
    pages: 'les catégories',
  },
  records: {
    format: 'carte',
    libelle: 'Records',
    ou: 'après les grilles',
    court: 'records',
    pages: 'les records',
  },
  classement: {
    format: 'carte',
    libelle: 'Classement',
    ou: 'après la liste des athlètes',
    court: 'classement',
    pages: 'le classement',
  },
  salles: {
    format: 'carte',
    libelle: 'Page des salles de sport',
    ou: 'en fin de page',
    court: 'salles',
    pages: 'la page des salles de sport',
  },
} as const satisfies Record<string, FicheEmplacement>;

export type IdEmplacement = keyof typeof EMPLACEMENTS;

/** Toutes les places : celles du contenu, plus le bandeau du pied de page. */
export type IdEmplacementPartenaire = IdEmplacement | 'pied';

/** Les identifiants, sous la forme qu'attend `z.enum`. */
const IDS: IdEmplacementPartenaire[] = [...(Object.keys(EMPLACEMENTS) as IdEmplacement[]), 'pied'];
export const IDS_EMPLACEMENTS_PARTENAIRE = IDS as [
  IdEmplacementPartenaire,
  ...IdEmplacementPartenaire[],
];

/** Les places d'un format, dans l'ordre de la table. */
export function emplacementsDuFormat(format: FormatDeContenu): IdEmplacement[] {
  return (Object.keys(EMPLACEMENTS) as IdEmplacement[]).filter(
    (id) => EMPLACEMENTS[id].format === format,
  );
}

/** Le format d'une place, pied de page compris. */
export function formatDe(emplacement: IdEmplacementPartenaire): IdFormat {
  return emplacement === 'pied' ? 'logo' : EMPLACEMENTS[emplacement].format;
}

/**
 * Ce qu'un partenaire actif doit fournir pour ses places : la bannière pour
 * une place en bannière, le logo pour une carte ou le pied de page, et
 * l'accroche pour une carte.
 */
export function piecesExigees(
  emplacements: readonly IdEmplacementPartenaire[],
): Set<'banniere' | 'logo' | 'accroche'> {
  const pieces = new Set<'banniere' | 'logo' | 'accroche'>();
  for (const emplacement of emplacements) {
    const format = formatDe(emplacement);
    pieces.add(FORMATS[format].image);
    if (format === 'carte') pieces.add('accroche');
  }
  return pieces;
}

/** [a, b, c] → « a, b et c », sans doublon, dans l'ordre donné. */
export function enumeration(mots: readonly string[]): string {
  const uniques = [...new Set(mots)];
  return uniques.length > 1
    ? `${uniques.slice(0, -1).join(', ')} et ${uniques.at(-1)}`
    : (uniques[0] ?? '');
}

/** 1200 → « 1 200 », avec l'espace fine insécable du français. */
export function pixels(n: number): string {
  return n.toLocaleString('fr-FR');
}

/*
 * LES DIMENSIONS NE SE COUPENT JAMAIS. Le signe × est entouré d'espaces
 * insécables : avec des espaces ordinaires, « 1 200 × » finissait une ligne et
 * « 300 pixels » ouvrait la suivante, mesuré à 375 px sur /partenariats/.
 */
const INSECABLE = ' ';

/** « 1 200 × 300 », d'un seul tenant. */
export function dimensions(d: { largeur: number; hauteur: number }): string {
  return `${pixels(d.largeur)}${INSECABLE}×${INSECABLE}${pixels(d.hauteur)}`;
}

/** « 1 200 × 300 pixels » ou « 600 pixels », l'unité collée au nombre. */
export function enPixels(d: number | { largeur: number; hauteur: number }): string {
  return `${typeof d === 'number' ? pixels(d) : dimensions(d)}${INSECABLE}pixels`;
}

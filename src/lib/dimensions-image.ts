/**
 * Les dimensions d'une image, lues dans son en-tête.
 *
 * Elles servent à refuser, à la génération, une image de partenaire hors
 * format : voir le schéma de la collection dans content.config.ts. Une image
 * au mauvais rapport serait recadrée dans sa place réservée, et le partenaire
 * découvrirait en ligne une bannière amputée de son texte.
 *
 * AUCUNE DÉPENDANCE. Quatre formats suffisent, ceux que le CMS accepte pour un
 * partenaire : PNG, JPEG, WebP et SVG. Chacun range ses dimensions à une place
 * connue de son en-tête, et quelques octets suffisent à les lire.
 *
 * L'orientation EXIF d'un JPEG n'est pas lue. Elle concerne les photos prises
 * au téléphone, jamais une bannière ou un logo exportés pour le web.
 */
import { readFileSync } from 'node:fs';

export interface Dimensions {
  largeur: number;
  hauteur: number;
  /** Vrai pour un SVG : seul son rapport compte, il n'a pas de taille minimale. */
  vectorielle: boolean;
}

/** PNG : signature de huit octets, puis le bloc IHDR, largeur et hauteur en tête. */
function png(o: Buffer): Dimensions | null {
  if (o.length < 24 || o.readUInt32BE(0) !== 0x89504e47 || o.toString('ascii', 12, 16) !== 'IHDR') {
    return null;
  }
  return { largeur: o.readUInt32BE(16), hauteur: o.readUInt32BE(20), vectorielle: false };
}

/**
 * JPEG : une suite de segments, chacun ouvert par l'octet FF et un marqueur.
 * La taille vit dans le premier segment « SOF », de C0 à CF hors C4, C8 et CC,
 * qui désignent autre chose.
 */
function jpeg(o: Buffer): Dimensions | null {
  if (o.length < 4 || o[0] !== 0xff || o[1] !== 0xd8) return null;
  let i = 2;
  while (i + 9 < o.length) {
    if (o[i] !== 0xff) return null;
    const marqueur = o[i + 1];
    // Octets de bourrage, puis marqueurs sans longueur.
    if (marqueur === 0xff) {
      i += 1;
      continue;
    }
    if (marqueur === 0x01 || (marqueur >= 0xd0 && marqueur <= 0xd7)) {
      i += 2;
      continue;
    }
    // Fin d'image ou début des données compressées : la taille aurait dû
    // précéder.
    if (marqueur === 0xd9 || marqueur === 0xda) return null;
    const estSof =
      marqueur >= 0xc0 && marqueur <= 0xcf && marqueur !== 0xc4 && marqueur !== 0xc8 && marqueur !== 0xcc;
    if (estSof) {
      return { hauteur: o.readUInt16BE(i + 5), largeur: o.readUInt16BE(i + 7), vectorielle: false };
    }
    i += 2 + o.readUInt16BE(i + 2);
  }
  return null;
}

/** WebP : un conteneur RIFF, puis un bloc VP8 (avec perte), VP8L (sans) ou VP8X (étendu). */
function webp(o: Buffer): Dimensions | null {
  if (o.length < 30 || o.toString('ascii', 0, 4) !== 'RIFF' || o.toString('ascii', 8, 12) !== 'WEBP') {
    return null;
  }
  const bloc = o.toString('ascii', 12, 16);
  if (bloc === 'VP8 ') {
    return {
      largeur: o.readUInt16LE(26) & 0x3fff,
      hauteur: o.readUInt16LE(28) & 0x3fff,
      vectorielle: false,
    };
  }
  if (bloc === 'VP8L') {
    const bits = o.readUInt32LE(21);
    return { largeur: (bits & 0x3fff) + 1, hauteur: ((bits >>> 14) & 0x3fff) + 1, vectorielle: false };
  }
  if (bloc === 'VP8X') {
    return { largeur: o.readUIntLE(24, 3) + 1, hauteur: o.readUIntLE(27, 3) + 1, vectorielle: false };
  }
  return null;
}

/**
 * SVG : le `viewBox` d'abord, qui fixe le rapport, puis `width` et `height`
 * s'il manque. Une unité relative, comme 100 %, ne dit rien du rapport.
 */
function svg(o: Buffer): Dimensions | null {
  const texte = o.toString('utf8', 0, Math.min(o.length, 8192));
  const balise = /<svg\b[^>]*>/i.exec(texte)?.[0];
  if (!balise) return null;

  const attribut = (nom: string) =>
    new RegExp(`\\s${nom}\\s*=\\s*["']([^"']*)["']`, 'i').exec(balise)?.[1];

  const vue = attribut('viewBox')?.trim().split(/[\s,]+/).map(Number);
  if (vue && vue.length === 4 && vue[2] > 0 && vue[3] > 0) {
    return { largeur: vue[2], hauteur: vue[3], vectorielle: true };
  }

  const longueur = (valeur?: string) => {
    const trouve = valeur && /^\s*([\d.]+)\s*(px)?\s*$/i.exec(valeur);
    return trouve ? Number(trouve[1]) : NaN;
  };
  const largeur = longueur(attribut('width'));
  const hauteur = longueur(attribut('height'));
  return largeur > 0 && hauteur > 0 ? { largeur, hauteur, vectorielle: true } : null;
}

/** Les dimensions de l'image, ou `null` si son en-tête ne se lit pas. */
export function dimensionsImage(fichier: string): Dimensions | null {
  const octets = readFileSync(fichier);
  return png(octets) ?? jpeg(octets) ?? webp(octets) ?? svg(octets);
}

/**
 * Briques communes aux deux scripts de données (explore-meets et update-data).
 *
 * Le dump OpenPowerlifting pèse ~160 Mo compressés et contient environ 4 millions
 * de lignes. Deux conséquences qui gouvernent tout ce fichier :
 *
 *  1. On ne le charge JAMAIS entièrement en mémoire. Tout est lu en flux, ligne
 *     par ligne. Le processus doit garder une empreinte mémoire stable, quelle
 *     que soit la taille du fichier.
 *  2. On le met en cache sur le disque. Retélécharger 160 Mo à chaque essai
 *     rendrait le développement insupportable.
 */

import { createWriteStream, existsSync, mkdirSync, statSync } from 'node:fs';
import { createInterface } from 'node:readline';
import { Readable } from 'node:stream';
import { pipeline } from 'node:stream/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import unzipper from 'unzipper';

export const ROOT = path.resolve(fileURLToPath(new URL('../..', import.meta.url)));
export const CACHE_DIR = path.join(ROOT, '.cache');
export const ZIP_PATH = path.join(CACHE_DIR, 'openpowerlifting-latest.zip');

const DUMP_URL =
  'https://openpowerlifting.gitlab.io/opl-csv/files/openpowerlifting-latest.zip';

/** Au-delà de cet âge, le cache local est considéré comme périmé. */
const CACHE_MAX_AGE_MS = 1000 * 60 * 60 * 24 * 20; // 20 jours

/**
 * Met le dump à disposition dans `.cache/`, en le téléchargeant si nécessaire.
 * Retélécharge si le fichier est absent, périmé, ou si `force` est demandé.
 */
export async function ensureDump({ force = false } = {}) {
  mkdirSync(CACHE_DIR, { recursive: true });

  if (!force && existsSync(ZIP_PATH)) {
    const stats = statSync(ZIP_PATH);
    const ageMs = Date.now() - stats.mtimeMs;
    const ageDays = Math.floor(ageMs / 86_400_000);

    if (ageMs < CACHE_MAX_AGE_MS && stats.size > 0) {
      console.log(
        `Dump en cache réutilisé (${formatBytes(stats.size)}, ${ageDays} j). ` +
          `Utilise --force pour le retélécharger.`,
      );
      return ZIP_PATH;
    }
    console.log(`Dump en cache périmé (${ageDays} j) — retéléchargement.`);
  }

  console.log(`Téléchargement de ${DUMP_URL}`);
  const response = await fetch(DUMP_URL);
  if (!response.ok || !response.body) {
    throw new Error(
      `Téléchargement impossible : HTTP ${response.status} ${response.statusText}`,
    );
  }

  const expected = Number(response.headers.get('content-length')) || 0;
  let received = 0;
  let lastLogged = 0;

  // On écrit directement sur le disque au fil de l'eau : à aucun moment les
  // 160 Mo ne se retrouvent en mémoire.
  const source = Readable.fromWeb(response.body);
  source.on('data', (chunk) => {
    received += chunk.length;
    if (received - lastLogged > 20_000_000) {
      lastLogged = received;
      const pct = expected ? ` (${Math.round((received / expected) * 100)} %)` : '';
      process.stdout.write(`  ${formatBytes(received)}${pct}\r`);
    }
  });

  await pipeline(source, createWriteStream(ZIP_PATH));
  process.stdout.write('\n');
  console.log(`Téléchargé : ${formatBytes(statSync(ZIP_PATH).size)}`);

  return ZIP_PATH;
}

/**
 * Ouvre le CSV principal contenu dans le zip et prépare sa lecture en flux.
 *
 * Retourne :
 *  - `header` : les noms de colonnes, dans l'ordre du fichier ;
 *  - `index`  : un objet { NomDeColonne: position }, pour lire une ligne par
 *               NOM et jamais par position. Si OpenPowerlifting réordonne ou
 *               insère une colonne un jour, le reste du code continue de
 *               fonctionner sans modification ;
 *  - `rows`   : un générateur asynchrone de tableaux de chaînes.
 */
export async function openRows(zipPath = ZIP_PATH) {
  const archive = await unzipper.Open.file(zipPath);

  // L'archive contient un dossier daté, avec le CSV principal à l'intérieur.
  // On prend le plus gros .csv : c'est celui des résultats.
  const csvEntries = archive.files.filter((f) => f.path.toLowerCase().endsWith('.csv'));
  if (csvEntries.length === 0) {
    throw new Error("Aucun fichier .csv trouvé dans l'archive.");
  }
  const entry = csvEntries.sort((a, b) => b.uncompressedSize - a.uncompressedSize)[0];
  console.log(
    `Lecture de ${entry.path} (${formatBytes(entry.uncompressedSize)} décompressé)`,
  );

  const lines = createInterface({
    input: entry.stream(),
    crlfDelay: Infinity,
  });
  const iterator = lines[Symbol.asyncIterator]();

  const first = await iterator.next();
  if (first.done) throw new Error('Le CSV est vide.');

  const header = first.value.split(',');
  const index = Object.fromEntries(header.map((name, i) => [name, i]));

  async function* rows() {
    for await (const line of iterator) {
      if (line === '') continue;
      // Le format OpenPowerlifting interdit les guillemets et les virgules à
      // l'intérieur des champs. Un split simple est donc sûr — et nettement
      // plus rapide qu'une librairie CSV sur 4 millions de lignes.
      yield line.split(',');
    }
  }

  return { header, index, rows: rows() };
}

/**
 * Minuscules, sans accents, espaces normalisés.
 * Sert à comparer des libellés saisis par des humains, avec toutes leurs
 * variantes d'orthographe et de casse.
 */
export function normalize(value) {
  return (
    String(value ?? '')
      // NFD sépare « é » en deux caractères : un « e » et un accent isolé.
      .normalize('NFD')
      // On supprime ensuite ces accents. \p{Mn} désigne la catégorie Unicode
      // « marque non espaçante », c'est-à-dire exactement les signes
      // diacritiques que NFD vient de détacher.
      .replace(/\p{Mn}/gu, '')
      .toLowerCase()
      .trim()
  );
}

/**
 * Même chose, mais en gommant aussi tirets et apostrophes, pour que
 * « Saint-Denis », « Saint Denis » et « saint denis » se rejoignent.
 */
export function normalizeTown(value) {
  return normalize(value)
    .replace(/['’-]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

/**
 * Les 24 communes de La Réunion.
 *
 * ATTENTION — cette liste ne sert JAMAIS à inclure automatiquement un meet.
 * Saint-Denis, Saint-André, Saint-Louis, Saint-Paul, Saint-Pierre et Sainte-Marie
 * existent aussi en métropole : filtrer sur la ville ferait entrer des
 * compétitions qui n'ont rien à voir avec l'île.
 *
 * Elle sert uniquement à SIGNALER des meets à examiner à la main, qui
 * atterrissent dans data/meets-candidates.json. La décision reste humaine.
 */
export const REUNION_TOWNS = new Set(
  [
    'Les Avirons',
    'Bras-Panon',
    'Cilaos',
    'Entre-Deux',
    "L'Étang-Salé",
    'Petite-Île',
    'La Plaine-des-Palmistes',
    'Le Port',
    'La Possession',
    'Saint-André',
    'Saint-Benoît',
    'Saint-Denis',
    'Saint-Joseph',
    'Saint-Leu',
    'Saint-Louis',
    'Saint-Paul',
    'Saint-Philippe',
    'Saint-Pierre',
    'Sainte-Marie',
    'Sainte-Rose',
    'Sainte-Suzanne',
    'Salazie',
    'Le Tampon',
    'Les Trois-Bassins',
  ].map(normalizeTown),
);

export function formatBytes(bytes) {
  if (bytes >= 1_000_000_000) return `${(bytes / 1_000_000_000).toFixed(1)} Go`;
  if (bytes >= 1_000_000) return `${(bytes / 1_000_000).toFixed(1)} Mo`;
  if (bytes >= 1_000) return `${(bytes / 1_000).toFixed(1)} Ko`;
  return `${bytes} o`;
}

/** Mémoire réellement occupée par le processus — sert à prouver que le streaming tient. */
export function memoryUsageMb() {
  return Math.round(process.memoryUsage().rss / 1_048_576);
}

/**
 * PIPELINE DE PRODUCTION — génère src/data/results.json à partir du dump
 * OpenPowerlifting.
 *
 *   node scripts/update-data.mjs            (réutilise le dump en cache)
 *   node scripts/update-data.mjs --force    (force le retéléchargement)
 *
 * Le script est idempotent : lancé deux fois sur le même dump, il produit
 * exactement le même fichier, octet pour octet. C'est ce qui permet au workflow
 * mensuel de ne créer un commit que lorsque les données ont réellement changé.
 */

import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import {
  ROOT,
  ensureDump,
  memoryUsageMb,
  normalize,
  normalizeTown,
  openRows,
  REUNION_TOWNS,
} from './lib/opl.mjs';

const force = process.argv.includes('--force');

/**
 * Formats de compétition retenus.
 *
 *  SBD = full power (squat + développé couché + soulevé de terre)
 *  B   = développé couché seul
 *  S   = squat seul
 *  D   = soulevé de terre seul
 *
 * On garde tous les formats, parce qu'un record doit refléter ce qui a
 * réellement été soulevé sur l'île. Le développé couché seul représente à lui
 * seul 26 des 61 compétitions réunionnaises : l'écarter afficherait un record
 * de bench inférieur à la réalité.
 *
 * MAIS ces lignes n'ont pas de total comparable — sur une compétition de bench
 * seul, OpenPowerlifting renseigne un « total » qui vaut simplement la meilleure
 * barre. Tout classement au total doit donc filtrer sur event === 'SBD'
 * (voir src/lib/rankings.ts et src/lib/records.ts).
 */
const EVENTS_RETENUS = new Set(['SBD', 'B', 'S', 'D']);

/**
 * Résultats qui ne comptent pas.
 *  DQ = disqualifié, DD = disqualifié pour dopage, NS = absent.
 * Le brief ne mentionnait que DQ ; DD et NS sont ajoutés pour la même raison
 * de fond : une performance annulée n'a pas à figurer dans un record.
 */
const PLACES_ECARTEES = new Set(['DQ', 'DD', 'NS']);

const DATA_DIR = path.join(ROOT, 'data');
const OUT_DIR = path.join(ROOT, 'src', 'data');

const include = lireListe(path.join(DATA_DIR, 'meets-include.json'));
const exclude = lireListe(path.join(DATA_DIR, 'meets-exclude.json'));

// Un meet est identifié par sa date ET son nom : « Championnat Régional »
// revient chaque année, et dans chaque région de France.
const cleInclude = new Set(include.map((m) => `${m.date}|${m.meetName}`));
const cleExclude = new Set(exclude.map((m) => `${m.date}|${m.meetName}`));

await ensureDump({ force });
const { index, rows } = await openRows();

const col = (nom) => {
  if (!(nom in index)) {
    throw new Error(
      `Colonne « ${nom} » absente du dump. OpenPowerlifting a changé son format : ` +
        `relance scripts/explore-meets.mjs pour voir ce qui est disponible.`,
    );
  }
  return index[nom];
};

const iFederation = col('Federation');
const iEvent = col('Event');
const iPlace = col('Place');
const iDate = col('Date');
const iMeetName = col('MeetName');
const iMeetTown = col('MeetTown');
const iName = col('Name');
const iSex = col('Sex');
const iEquipment = col('Equipment');
const iDivision = col('Division');
const iAgeClass = col('AgeClass');
const iBodyweight = col('BodyweightKg');
const iWeightClass = col('WeightClassKg');
const iSquat = col('Best3SquatKg');
const iBench = col('Best3BenchKg');
const iDeadlift = col('Best3DeadliftKg');
const iTotal = col('TotalKg');
const iDots = col('Dots');
const iGoodlift = col('Goodlift');

const resultats = [];
/** @type {Map<string, any>} */
const candidats = new Map();
const meetsRetenus = new Set();
const parEvent = new Map();

let lignesLues = 0;
let lignesFFForce = 0;
let ecarteesPlace = 0;
// On retient QUELS formats sont écartés, pas seulement combien. Un compteur
// muet cache ce qu'on perd ; ce détail permet de revoir le périmètre en
// connaissance de cause.
const ecarteesParEvent = new Map();

console.log('\nLecture et filtrage…');
const debut = Date.now();

for await (const row of rows) {
  lignesLues++;
  if (lignesLues % 500_000 === 0) {
    process.stdout.write(
      `  ${(lignesLues / 1_000_000).toFixed(1)} M lignes — mémoire ${memoryUsageMb()} Mo\r`,
    );
  }

  if (row[iFederation] !== 'FFForce') continue;
  lignesFFForce++;

  const date = row[iDate] ?? '';
  const meetName = row[iMeetName] ?? '';
  const meetTown = row[iMeetTown] ?? '';
  const cle = `${date}|${meetName}`;

  // La liste d'exclusion a toujours le dernier mot.
  if (cleExclude.has(cle)) continue;

  const parLeNom = normalize(meetName).includes('reunion');
  const retenu = parLeNom || cleInclude.has(cle);

  if (!retenu) {
    // La ville n'inclut JAMAIS un meet toute seule : Saint-Denis, Saint-Louis
    // et Saint-Paul existent aussi en métropole. Elle sert uniquement à
    // signaler un meet à examiner à la main.
    if (meetTown && REUNION_TOWNS.has(normalizeTown(meetTown))) {
      const c = candidats.get(cle);
      if (c) c.lignes++;
      else candidats.set(cle, { date, meetName, meetTown, lignes: 1 });
    }
    continue;
  }

  const event = row[iEvent] ?? '';
  if (!EVENTS_RETENUS.has(event)) {
    ecarteesParEvent.set(event, (ecarteesParEvent.get(event) ?? 0) + 1);
    continue;
  }

  if (PLACES_ECARTEES.has(row[iPlace] ?? '')) {
    ecarteesPlace++;
    continue;
  }

  meetsRetenus.add(cle);
  parEvent.set(event, (parEvent.get(event) ?? 0) + 1);

  resultats.push({
    name: row[iName] ?? '',
    sex: row[iSex] ?? '',
    event,
    equipment: row[iEquipment] ?? '',
    division: row[iDivision] ?? '',
    ageClass: row[iAgeClass] ?? '',
    date,
    meetName,
    meetTown,
    bodyweightKg: nombre(row[iBodyweight]),
    weightClassKg: row[iWeightClass] ?? '',
    bestSquatKg: nombre(row[iSquat]),
    bestBenchKg: nombre(row[iBench]),
    bestDeadliftKg: nombre(row[iDeadlift]),
    totalKg: nombre(row[iTotal]),
    dots: nombre(row[iDots]),
    goodlift: nombre(row[iGoodlift]),
    place: row[iPlace] ?? '',
  });
}

process.stdout.write('\n');
const duree = ((Date.now() - debut) / 1000).toFixed(1);

// ---------------------------------------------------------------------------
// Deuxième passe : les compétitions disputées HORS de La Réunion.
//
// Les athlètes réunionnais se déplacent, en métropole comme à l'étranger, et
// ces résultats disparaissaient jusqu'ici du site. Ils comptent pourtant dans
// leur parcours.
//
// Deux passes sont nécessaires, et non une seule : on ne connaît la liste des
// athlètes réunionnais qu'APRÈS avoir parcouru tout le dump. Garder en mémoire
// les 4 millions de lignes en attendant serait évidemment exclu.
//
// L'identité repose sur le nom exact tel qu'OpenPowerlifting l'écrit. Ce n'est
// pas une approximation : le projet distingue lui-même les homonymes par un
// suffixe « #2 », donc deux lignes portant la même chaîne désignent bien la
// même personne.
// ---------------------------------------------------------------------------
const athletesReunion = new Set(resultats.map((r) => r.name));
const exterieur = [];
const federationsExterieur = new Map();
const paysExterieur = new Map();
let ecarteesHomonymie = 0;

/**
 * Faux appariements écartés à la main.
 *
 * L'identité repose sur le nom exact, et OpenPowerlifting ne repère pas
 * toujours les homonymes. Sans cette liste, les tournois lycéens texans d'un
 * « Kobe Washington » atterriraient sur la fiche d'un athlète réunionnais du
 * même nom.
 */
const exclusionsExterieur = JSON.parse(
  readFileSync(path.join(DATA_DIR, 'exterieur-exclude.json'), 'utf8'),
).athletes ?? [];

const estFauxAppariement = (name, federation, date) =>
  exclusionsExterieur.some(
    (e) =>
      e.name === name &&
      (!e.federations || e.federations.includes(federation)) &&
      (!e.dates || e.dates.includes(date)),
  );

console.log('\nDeuxième passe : résultats hors de La Réunion…');
const debut2 = Date.now();
let lignesLues2 = 0;

const { index: index2, rows: rows2 } = await openRows();
/** Résout une colonne par son nom, en échouant bruyamment si elle manque. */
const c2 = (nom) => {
  const i = index2[nom];
  if (i === undefined) throw new Error(`Colonne « ${nom} » absente du dump.`);
  return i;
};

for await (const row of rows2) {
  lignesLues2++;
  if (lignesLues2 % 500_000 === 0) {
    process.stdout.write(
      `  ${(lignesLues2 / 1_000_000).toFixed(1)} M lignes — mémoire ${memoryUsageMb()} Mo\r`,
    );
  }

  const name = row[c2('Name')] ?? '';
  if (!athletesReunion.has(name)) continue;

  const date = row[c2('Date')] ?? '';
  const meetName = row[c2('MeetName')] ?? '';
  // Les compétitions réunionnaises sont déjà dans le fichier principal.
  if (meetsRetenus.has(`${date}|${meetName}`)) continue;

  const event = row[c2('Event')] ?? '';
  if (!EVENTS_RETENUS.has(event)) continue;
  if (PLACES_ECARTEES.has(row[c2('Place')] ?? '')) continue;

  const federation = row[c2('Federation')] ?? '';
  const meetCountry = row[c2('MeetCountry')] ?? '';

  if (estFauxAppariement(name, federation, date)) {
    ecarteesHomonymie++;
    continue;
  }

  federationsExterieur.set(federation, (federationsExterieur.get(federation) ?? 0) + 1);
  paysExterieur.set(meetCountry, (paysExterieur.get(meetCountry) ?? 0) + 1);

  exterieur.push({
    name,
    sex: row[c2('Sex')] ?? '',
    event,
    equipment: row[c2('Equipment')] ?? '',
    division: row[c2('Division')] ?? '',
    ageClass: row[c2('AgeClass')] ?? '',
    date,
    meetName,
    meetTown: row[c2('MeetTown')] ?? '',
    meetCountry,
    meetState: row[c2('MeetState')] ?? '',
    federation,
    bodyweightKg: nombre(row[c2('BodyweightKg')]),
    weightClassKg: row[c2('WeightClassKg')] ?? '',
    bestSquatKg: nombre(row[c2('Best3SquatKg')]),
    bestBenchKg: nombre(row[c2('Best3BenchKg')]),
    bestDeadliftKg: nombre(row[c2('Best3DeadliftKg')]),
    totalKg: nombre(row[c2('TotalKg')]),
    dots: nombre(row[c2('Dots')]),
    goodlift: nombre(row[c2('Goodlift')]),
    place: row[c2('Place')] ?? '',
  });
}

process.stdout.write('\n');
const duree2 = ((Date.now() - debut2) / 1000).toFixed(1);

exterieur.sort(
  (a, b) =>
    a.date.localeCompare(b.date) ||
    a.meetName.localeCompare(b.meetName) ||
    a.name.localeCompare(b.name) ||
    a.event.localeCompare(b.event),
);

// Tri déterministe : c'est lui qui garantit l'idempotence. Sans ordre stable,
// deux exécutions identiques produiraient des fichiers différents et le
// workflow mensuel créerait un commit à chaque passage, pour rien.
resultats.sort(
  (a, b) =>
    a.date.localeCompare(b.date) ||
    a.meetName.localeCompare(b.meetName) ||
    a.name.localeCompare(b.name) ||
    a.event.localeCompare(b.event),
);

mkdirSync(OUT_DIR, { recursive: true });
writeFileSync(
  path.join(OUT_DIR, 'results.json'),
  JSON.stringify(resultats, null, 2) + '\n',
  'utf8',
);
writeFileSync(
  path.join(OUT_DIR, 'results-exterieur.json'),
  JSON.stringify(exterieur, null, 2) + '\n',
  'utf8',
);

const listeCandidats = [...candidats.values()].sort(
  (a, b) => a.date.localeCompare(b.date) || a.meetName.localeCompare(b.meetName),
);
writeFileSync(
  path.join(DATA_DIR, 'meets-candidates.json'),
  JSON.stringify(
    {
      _lisezmoi: [
        'GÉNÉRÉ AUTOMATIQUEMENT — ne pas modifier à la main, le fichier est réécrit',
        'à chaque exécution de scripts/update-data.mjs.',
        '',
        'Compétitions FFForce qui se tiennent dans une commune portant un nom',
        'réunionnais, mais que la règle du nom ne retient pas. Elles ne sont PAS',
        'incluses dans le site.',
        '',
        'À toi de trancher : si un meet de cette liste est bien réunionnais,',
        'recopie-le dans meets-include.json. Sinon, ignore-le — ou mets-le dans',
        'meets-exclude.json pour ne plus le revoir ici.',
      ],
      genereLe: new Date().toISOString().slice(0, 10),
      meets: listeCandidats,
    },
    null,
    2,
  ) + '\n',
  'utf8',
);

// ---------------------------------------------------------------------------
// Bilan
// ---------------------------------------------------------------------------
const athletes = new Set(resultats.map((r) => r.name));
const dates = resultats.map((r) => r.date).sort();

console.log(`\n${lignesLues.toLocaleString('fr-FR')} lignes lues en ${duree} s`);
console.log(`Pic mémoire : ${memoryUsageMb()} Mo (streaming — indépendant de la taille du dump)`);
console.log('');
console.log(`  Meets retenus ......... ${meetsRetenus.size}`);
console.log(`  Athlètes distincts .... ${athletes.size}`);
console.log(`  Résultats ............. ${resultats.length}`);
for (const [event, n] of [...parEvent].sort((a, b) => b[1] - a[1])) {
  console.log(`      dont ${event.padEnd(3)} — ${libelleEvent(event).padEnd(28)} ${n}`);
}
console.log(
  `  Période ............... ${dates[0] ?? '—'} → ${dates[dates.length - 1] ?? '—'}`,
);
console.log('');
const totalEcarteesEvent = [...ecarteesParEvent.values()].reduce((a, b) => a + b, 0);
console.log(`  Écartés (format non retenu) ... ${totalEcarteesEvent}`);
for (const [event, n] of [...ecarteesParEvent].sort((a, b) => b[1] - a[1])) {
  console.log(`      ${event || '(vide)'} : ${n} — ${libelleEvent(event)}`);
}
console.log(`  Écartés (DQ / DD / absent) .... ${ecarteesPlace}`);
console.log(`  Lignes FFForce examinées ...... ${lignesFFForce.toLocaleString('fr-FR')}`);
console.log('');

if (listeCandidats.length > 0) {
  console.log(
    `  ${listeCandidats.length} meet(s) à trier dans data/meets-candidates.json :`,
  );
  for (const c of listeCandidats) {
    console.log(`      ${c.date}  ${c.meetTown}, ${c.meetName}  (${c.lignes} lignes)`);
  }
} else {
  console.log('  Aucun meet en attente de tri.');
}

// ---------------------------------------------------------------------------
// Bilan de la deuxième passe
// ---------------------------------------------------------------------------
const athletesVoyageurs = new Set(exterieur.map((r) => r.name));
const datesExt = exterieur.map((r) => r.date).sort();

console.log('');
console.log(`Hors de La Réunion (lu en ${duree2} s)`);
console.log(`  Résultats ............. ${exterieur.length}`);
console.log(
  `  Athlètes concernés .... ${athletesVoyageurs.size} sur ${athletesReunion.size}`,
);
console.log(`  Écartés (homonymie) ... ${ecarteesHomonymie}`);
console.log(
  `  Période ............... ${datesExt[0] ?? '—'} → ${datesExt[datesExt.length - 1] ?? '—'}`,
);
for (const [pays, n] of [...paysExterieur].sort((a, b) => b[1] - a[1]).slice(0, 6)) {
  console.log(`      ${(pays || '(inconnu)').padEnd(16)} ${n}`);
}
console.log('  Fédérations :');
for (const [fed, n] of [...federationsExterieur].sort((a, b) => b[1] - a[1]).slice(0, 6)) {
  console.log(`      ${(fed || '(inconnue)').padEnd(16)} ${n}`);
}

console.log('\nÉcrit : src/data/results.json et src/data/results-exterieur.json');

// ---------------------------------------------------------------------------

function nombre(valeur) {
  if (valeur === undefined || valeur === '') return null;
  const n = Number(valeur);
  return Number.isFinite(n) ? n : null;
}

function libelleEvent(event) {
  return (
    {
      SBD: 'full power',
      B: 'développé couché seul',
      S: 'squat seul',
      D: 'soulevé de terre seul',
      BD: 'développé couché + soulevé de terre',
      SB: 'squat + développé couché',
      SD: 'squat + soulevé de terre',
    }[event] ?? 'format inconnu'
  );
}

function lireListe(fichier) {
  const contenu = JSON.parse(readFileSync(fichier, 'utf8'));
  // Les fichiers portent une clé « _lisezmoi » pour être compréhensibles quand
  // on les ouvre ; seule la clé « meets » nous intéresse ici.
  return Array.isArray(contenu) ? contenu : (contenu.meets ?? []);
}

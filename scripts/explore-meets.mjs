/**
 * RECONNAISSANCE — ce script ne produit aucune donnée pour le site.
 *
 * Objectif unique : regarder ce que le dump OpenPowerlifting contient réellement
 * pour la fédération française, afin de décider *sur pièces* comment identifier
 * les compétitions réunionnaises.
 *
 * On ne devine pas la règle de filtrage, on l'observe. C'est ce qui évite de
 * construire tout un site sur une hypothèse fausse.
 *
 *   node scripts/explore-meets.mjs            (réutilise le dump en cache)
 *   node scripts/explore-meets.mjs --force    (force le retéléchargement)
 */

import { writeFileSync } from 'node:fs';
import path from 'node:path';
import {
  CACHE_DIR,
  ensureDump,
  memoryUsageMb,
  normalize,
  normalizeTown,
  openRows,
  REUNION_TOWNS,
} from './lib/opl.mjs';

const force = process.argv.includes('--force');

// Colonnes dont dépend la suite du projet. On vérifie leur présence au lieu de
// la supposer : si OpenPowerlifting en renomme une, on veut un message clair
// maintenant, pas une colonne de chiffres vides trois étapes plus loin.
const EXPECTED_COLUMNS = [
  'Name', 'Sex', 'Event', 'Equipment', 'Division', 'AgeClass',
  'BodyweightKg', 'WeightClassKg', 'Best3SquatKg', 'Best3BenchKg',
  'Best3DeadliftKg', 'TotalKg', 'Place', 'Dots', 'Goodlift',
  'Federation', 'Date', 'MeetCountry', 'MeetState', 'MeetName',
];

await ensureDump({ force });
const { header, index, rows } = await openRows();

console.log(`\n${header.length} colonnes détectées.`);

const missing = EXPECTED_COLUMNS.filter((c) => !(c in index));
if (missing.length > 0) {
  console.warn(`ATTENTION — colonnes attendues absentes : ${missing.join(', ')}`);
} else {
  console.log('Toutes les colonnes attendues sont présentes.');
}

// La documentation publique ne mentionne pas MeetTown dans le CSV combiné, alors
// qu'il existe dans les données de meets. On vérifie plutôt que de parier.
const hasMeetTown = 'MeetTown' in index;
console.log(
  hasMeetTown
    ? 'Colonne MeetTown : présente.'
    : 'Colonne MeetTown : ABSENTE — la ville devra venir d\'ailleurs.',
);

const iFederation = index['Federation'];
const iEvent = index['Event'];
const iDate = index['Date'];
const iMeetName = index['MeetName'];
const iMeetState = index['MeetState'];
const iMeetCountry = index['MeetCountry'];
const iMeetTown = hasMeetTown ? index['MeetTown'] : -1;

/** @type {Map<string, any>} */
const meets = new Map();
const stateCounts = new Map();
const townCounts = new Map();

let totalRows = 0;
let ffforceRows = 0;

console.log('\nLecture en cours…');
const startedAt = Date.now();

for await (const row of rows) {
  totalRows++;

  if (totalRows % 500_000 === 0) {
    process.stdout.write(
      `  ${(totalRows / 1_000_000).toFixed(1)} M lignes — mémoire ${memoryUsageMb()} Mo\r`,
    );
  }

  if (row[iFederation] !== 'FFForce') continue;
  ffforceRows++;

  const meetName = row[iMeetName] ?? '';
  const meetState = row[iMeetState] ?? '';
  const meetTown = iMeetTown >= 0 ? (row[iMeetTown] ?? '') : '';
  const date = row[iDate] ?? '';

  stateCounts.set(meetState, (stateCounts.get(meetState) ?? 0) + 1);
  if (meetTown) townCounts.set(meetTown, (townCounts.get(meetTown) ?? 0) + 1);

  // Un meet est identifié par sa date ET son nom : « Championnat Régional »
  // revient chaque année et dans chaque région.
  const key = `${date}|${meetName}|${meetTown}|${meetState}`;
  let meet = meets.get(key);
  if (!meet) {
    meet = {
      date,
      meetName,
      meetTown,
      meetState,
      meetCountry: row[iMeetCountry] ?? '',
      rows: 0,
      sbdRows: 0,
    };
    meets.set(key, meet);
  }
  meet.rows++;
  if (row[iEvent] === 'SBD') meet.sbdRows++;
}

const elapsed = ((Date.now() - startedAt) / 1000).toFixed(1);
process.stdout.write('\n');

console.log(
  `\n${totalRows.toLocaleString('fr-FR')} lignes lues en ${elapsed} s ` +
    `— pic mémoire ${memoryUsageMb()} Mo`,
);
console.log(
  `${ffforceRows.toLocaleString('fr-FR')} lignes FFForce, ` +
    `réparties sur ${meets.size} meets.`,
);

// ---------------------------------------------------------------------------
// 1. Comment la région est-elle encodée ? C'est LA question de cette étape.
// ---------------------------------------------------------------------------
console.log('\n--- Valeurs de MeetState pour FFForce (lignes) ---');
const states = [...stateCounts.entries()].sort((a, b) => b[1] - a[1]);
if (states.length === 0) {
  console.log('  (aucune)');
} else {
  for (const [state, count] of states.slice(0, 40)) {
    console.log(`  ${(state || '(vide)').padEnd(24)} ${count.toLocaleString('fr-FR')}`);
  }
  if (states.length > 40) console.log(`  … et ${states.length - 40} autres valeurs`);
}

// ---------------------------------------------------------------------------
// 2. Meets dont le NOM évoque La Réunion — la règle du brief.
// ---------------------------------------------------------------------------
const byName = [...meets.values()].filter((m) => normalize(m.meetName).includes('reunion'));
console.log(`\n--- Meets dont le nom contient « reunion » : ${byName.length} ---`);
for (const m of byName.sort(sortByDate)) console.log(`  ${describe(m)}`);

// ---------------------------------------------------------------------------
// 3. Meets dont la VILLE est une commune réunionnaise.
//    Rappel : la ville ne suffira jamais à inclure un meet — Saint-Denis,
//    Saint-Louis ou Saint-Paul existent aussi en métropole. Cette liste sert
//    à repérer ce que la règle du nom laisse passer.
// ---------------------------------------------------------------------------
const byTown = [...meets.values()].filter(
  (m) => m.meetTown && REUNION_TOWNS.has(normalizeTown(m.meetTown)),
);
const byTownOnly = byTown.filter((m) => !normalize(m.meetName).includes('reunion'));
console.log(
  `\n--- Meets dans une commune réunionnaise : ${byTown.length} ` +
    `(dont ${byTownOnly.length} que le nom seul ne trouve PAS) ---`,
);
for (const m of byTownOnly.sort(sortByDate)) console.log(`  ${describe(m)}`);

// ---------------------------------------------------------------------------
// 4. Rapport complet sur le disque, pour inspection tranquille.
// ---------------------------------------------------------------------------
const reportPath = path.join(CACHE_DIR, 'ffforce-meets.json');
writeFileSync(
  reportPath,
  JSON.stringify(
    {
      genereLe: new Date().toISOString(),
      colonnes: header,
      colonnesManquantes: missing,
      aMeetTown: hasMeetTown,
      lignesTotales: totalRows,
      lignesFFForce: ffforceRows,
      valeursMeetState: Object.fromEntries(states),
      villes: Object.fromEntries([...townCounts.entries()].sort((a, b) => b[1] - a[1])),
      meets: [...meets.values()].sort(sortByDate),
    },
    null,
    2,
  ),
  'utf8',
);

console.log(`\nRapport complet écrit dans ${path.relative(process.cwd(), reportPath)}`);
console.log(
  'Prochaine étape : on lit ces résultats ensemble et on fige la règle de filtrage.',
);

function sortByDate(a, b) {
  return a.date.localeCompare(b.date);
}

function describe(m) {
  const town = m.meetTown ? `${m.meetTown}, ` : '';
  const state = m.meetState ? `[${m.meetState}] ` : '';
  return `${m.date}  ${state}${town}${m.meetName}  (${m.rows} lignes, ${m.sbdRows} SBD)`;
}

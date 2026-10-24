/**
 * Relevé quotidien de Microsoft Clarity, par son API d'export.
 *
 *   npm run clarity:relever
 *
 * POURQUOI UN RELEVÉ ET NON UN EXPORT. L'API de Clarity ne rend que les 1 à
 * 3 derniers jours, agrégés, et accepte 10 appels par projet et par jour.
 * Aucun appel ne donnera jamais trente jours d'un coup. La seule façon d'avoir
 * un mois, c'est de relever chaque jour et de garder les relevés.
 *
 * CE QUI EST RELEVÉ. La veille (numOfDays=1), ventilée par adresse puis par
 * appareil : sessions, profondeur de défilement, temps actif, clics morts,
 * clics de rage, retours rapides. Deux appels par exécution.
 *
 * OÙ ÇA VA. clarity/releves.jsonl, une ligne JSON par relevé, horodatée. Le
 * dossier clarity/ est ignoré par git : le jeton et les relevés restent sur la
 * machine de l'auteur. Un deuxième lancement le même jour est refusé, pour ne
 * pas consommer le quota ni doubler une ligne.
 *
 * LE JETON vit dans clarity/jeton.txt, créé à la main, jamais dans le code.
 */
import { appendFileSync, existsSync, readFileSync } from 'node:fs';

const DOSSIER = new URL('../clarity/', import.meta.url);
const FICHIER_JETON = new URL('jeton.txt', DOSSIER);
const FICHIER_RELEVES = new URL('releves.jsonl', DOSSIER);
const API = 'https://www.clarity.ms/export-data/api/v1/project-live-insights';

if (!existsSync(FICHIER_JETON)) {
  console.error('Aucun jeton : dépose la clé API de Clarity dans clarity/jeton.txt.');
  process.exit(1);
}
const jeton = readFileSync(FICHIER_JETON, 'utf8').trim();

/* La veille, au format AAAA-MM-JJ : le relevé d'aujourd'hui décrit hier. */
const veille = new Date(Date.now() - 86_400_000).toISOString().slice(0, 10);

if (existsSync(FICHIER_RELEVES)) {
  const dejaFait = readFileSync(FICHIER_RELEVES, 'utf8')
    .split('\n')
    .filter(Boolean)
    .some((ligne) => JSON.parse(ligne).jour === veille);
  if (dejaFait) {
    console.log(`Relevé du ${veille} déjà fait. Rien à faire.`);
    process.exit(0);
  }
}

async function relever(dimension) {
  const reponse = await fetch(`${API}?numOfDays=1&dimension1=${dimension}`, {
    headers: { Authorization: `Bearer ${jeton}` },
  });
  if (!reponse.ok) {
    throw new Error(`Clarity répond ${reponse.status} pour la dimension ${dimension}.`);
  }
  return reponse.json();
}

const [parAdresse, parAppareil] = await Promise.all([relever('URL'), relever('Device')]);

appendFileSync(
  FICHIER_RELEVES,
  JSON.stringify({ jour: veille, releveLe: new Date().toISOString(), parAdresse, parAppareil }) + '\n',
);

const trafic = parAppareil.find((m) => m.metricName === 'Traffic')?.information ?? [];
const sessions = trafic.reduce((n, l) => n + (l.totalSessionCount ?? 0), 0);
console.log(`Relevé du ${veille} écrit : ${sessions} session(s), ${trafic.length} type(s) d'appareil.`);

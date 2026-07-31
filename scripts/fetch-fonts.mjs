/**
 * Récupère les polices Google et les installe DANS le projet.
 *
 *   node scripts/fetch-fonts.mjs
 *
 * Écrit les fichiers .woff2 dans public/fonts/ et régénère src/styles/fonts.css.
 * À relancer uniquement si tu changes de police ou de graisses.
 *
 * POURQUOI NE PAS UTILISER LE <link> VERS fonts.googleapis.com
 * ------------------------------------------------------------
 * Ce lien ferait transmettre l'adresse IP de chaque visiteur à Google, sans son
 * consentement. Le tribunal de Munich l'a jugé contraire au RGPD en 2022, et la
 * CNIL suit la même ligne. Les deux polices utilisées ici sont sous licence SIL
 * Open Font, qui autorise explicitement la redistribution : les héberger
 * nous-mêmes est légal, conforme, et plus rapide (une connexion réseau de moins).
 *
 * POLICES VARIABLES
 * -----------------
 * La syntaxe « wght@400..700 » demande une PLAGE de graisses. Google renvoie
 * alors un fichier unique couvrant tout l'intervalle, au lieu d'un fichier par
 * graisse. Sur ce projet : 170 Ko au total au lieu de 600 Ko.
 */

import { existsSync, mkdirSync, readdirSync, rmSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(fileURLToPath(new URL('..', import.meta.url)));
const DOSSIER_POLICES = path.join(ROOT, 'public', 'fonts');
const FEUILLE = path.join(ROOT, 'src', 'styles', 'fonts.css');

/**
 * Sans agent utilisateur de navigateur récent, l'API de Google renvoie du TTF,
 * bien plus lourd que le woff2. Ce n'est pas un contournement : c'est la
 * négociation de format prévue par le service.
 */
const AGENT =
  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';

/**
 * UNE seule famille depuis juillet 2026 : Archivo, qui remplace Oswald et Inter.
 *
 * Deux axes sont demandés, et c'est le point intéressant :
 *   wght 400..800 — des graisses de texte au titrage le plus lourd ;
 *   wdth 62..125  — un axe de LARGEUR, du très resserré au large.
 *
 * Cet axe rend Oswald inutile. On avait choisi Oswald pour sa forme condensée,
 * qui fait tenir un total à quatre chiffres dans une colonne étroite, mais elle
 * ignore `tabular-nums` — d'où les deux classes .num et .num-tab, et tout le
 * piège documenté dans parcours.md. Archivo, elle, applique réellement
 * tabular-nums : mesuré dans le navigateur, « 111 » et « 888 » occupent
 * exactement 68,17 px en graisse 400. Un seul fichier couvre donc les deux
 * usages.
 */
const URL_CSS =
  'https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,400..800&display=swap';

/**
 * Le français tient dans latin + latin-ext. Les autres sous-ensembles
 * (cyrillique, grec, vietnamien) sont ignorés : ils pèseraient lourd pour rien.
 * Grâce à « unicode-range », le navigateur ne télécharge latin-ext que s'il
 * rencontre effectivement un caractère de cette plage.
 */
const SOUS_ENSEMBLES = new Set(['latin', 'latin-ext']);

if (existsSync(DOSSIER_POLICES)) {
  for (const f of readdirSync(DOSSIER_POLICES)) rmSync(path.join(DOSSIER_POLICES, f));
}
mkdirSync(DOSSIER_POLICES, { recursive: true });

console.log(`Récupération de ${URL_CSS}\n`);
const reponseCss = await fetch(URL_CSS, { headers: { 'User-Agent': AGENT } });
if (!reponseCss.ok) {
  throw new Error(`Google Fonts a répondu ${reponseCss.status} ${reponseCss.statusText}`);
}
const css = await reponseCss.text();

// Chaque bloc @font-face est précédé d'un commentaire nommant le sous-ensemble.
const blocs = css.split('/*').slice(1);
const faces = [];
let total = 0;

for (const bloc of blocs) {
  const sousEnsemble = bloc.slice(0, bloc.indexOf('*/')).trim();
  if (!SOUS_ENSEMBLES.has(sousEnsemble)) continue;

  const famille = /font-family:\s*'([^']+)'/.exec(bloc)?.[1];
  // Une police variable déclare une plage : « font-weight: 400 800 ».
  const graisse = /font-weight:\s*([^;]+);/.exec(bloc)?.[1]?.trim();
  const style = /font-style:\s*(\w+)/.exec(bloc)?.[1] ?? 'normal';
  const url = /src:\s*url\(([^)]+)\)/.exec(bloc)?.[1];
  const plage = /unicode-range:\s*([^;]+);/.exec(bloc)?.[1]?.trim();
  // L'axe de largeur, quand la police en a un : « font-stretch: 62% 125% ».
  // Sans cette déclaration, le navigateur refuserait toute valeur de
  // `font-stretch` autre que 100 % et la forme resserrée serait inaccessible.
  const largeur = /font-stretch:\s*([^;]+);/.exec(bloc)?.[1]?.trim();
  if (!famille || !graisse || !url) continue;

  const nom = `${famille.toLowerCase().replace(/\s+/g, '-')}-${sousEnsemble}.woff2`;
  const reponse = await fetch(url, { headers: { 'User-Agent': AGENT } });
  if (!reponse.ok) throw new Error(`Téléchargement de ${nom} : HTTP ${reponse.status}`);

  const octets = Buffer.from(await reponse.arrayBuffer());
  writeFileSync(path.join(DOSSIER_POLICES, nom), octets);
  total += octets.length;

  faces.push({ famille, graisse, style, nom, plage, largeur, taille: octets.length });
  console.log(
    `  ${nom.padEnd(28)} graisses ${graisse.padEnd(9)} ${(octets.length / 1024).toFixed(1)} Ko`,
  );
}

if (faces.length === 0) {
  throw new Error("Aucune police récupérée : le format de réponse de Google a peut-être changé.");
}

const entete = `/*
 * Polices auto-hébergées — GÉNÉRÉ PAR scripts/fetch-fonts.mjs.
 * Ne pas modifier à la main : le fichier est réécrit à chaque exécution.
 *
 * UNE famille depuis juillet 2026 : Archivo, en version VARIABLE sur deux axes.
 * Un seul fichier couvre toutes les graisses ET toutes les largeurs.
 *
 *   wght 400..800 — du texte courant au titrage le plus lourd.
 *   wdth 62..125  — l'axe de LARGEUR, piloté en CSS par « font-stretch ».
 *
 * Elle remplace Oswald et Inter à elle seule. Oswald avait été choisie pour sa
 * forme condensée, mais elle ignore « tabular-nums », ce qui avait imposé deux
 * classes CSS distinctes. Archivo applique réellement tabular-nums — mesuré
 * dans le navigateur, « 111 » et « 888 » font exactement 68,17 px en graisse
 * 400 — et sa forme resserrée s'obtient par font-stretch sur le même fichier.
 *
 * « font-display: swap » affiche le texte immédiatement dans une police système
 * puis bascule une fois la police chargée. Sans lui, le texte resterait
 * invisible pendant le téléchargement.
 */
`;

const regles = faces
  .map(
    (f) => `@font-face {
  font-family: '${f.famille}';
  font-style: ${f.style};
  font-weight: ${f.graisse};${f.largeur ? `\n  font-stretch: ${f.largeur};` : ''}
  font-display: swap;
  src: url('/fonts/${f.nom}') format('woff2-variations');
  unicode-range: ${f.plage};
}`,
  )
  .join('\n\n');

writeFileSync(FEUILLE, `${entete}\n${regles}\n`, 'utf8');

const poidsLatin =
  faces.filter((f) => f.nom.endsWith('-latin.woff2')).reduce((s, f) => s + f.taille, 0) / 1024;

console.log(`\n${faces.length} fichiers, ${(total / 1024).toFixed(1)} Ko sur le disque.`);
console.log(`Transfert réel pour un visiteur francophone : ~${poidsLatin.toFixed(0)} Ko.`);
console.log('Écrit : src/styles/fonts.css');

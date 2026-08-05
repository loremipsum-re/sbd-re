/**
 * Copie le CMS dans public/admin/ avant chaque génération.
 *
 * POURQUOI COPIER PLUTÔT QUE CHARGER DEPUIS UN CDN
 *
 * L'usage courant de Sveltia CMS est une balise <script> pointant vers unpkg.
 * Ce site refuse les requêtes vers des tiers : la règle vaut pour la page
 * d'administration comme pour le reste, d'autant qu'un CDN verrait passer
 * chaque ouverture du CMS. Le fichier est donc servi depuis sbd.re.
 *
 * POURQUOI COPIER PLUTÔT QUE VERSIONNER
 *
 * Le fichier pèse près d'un mégaoctet et change à chaque mise à jour du
 * paquet. Le versionner alourdirait le dépôt sans rien apporter : `npm ci`
 * l'installe déjà, à la version exacte du package-lock.json. La copie est donc
 * refaite à chaque build, en local comme dans GitHub Actions.
 */
import { copyFileSync, mkdirSync, existsSync, statSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const RACINE = path.resolve(fileURLToPath(new URL('..', import.meta.url)));
const SOURCE = path.join(RACINE, 'node_modules', '@sveltia', 'cms', 'dist', 'sveltia-cms.js');
const DOSSIER = path.join(RACINE, 'public', 'admin');
const CIBLE = path.join(DOSSIER, 'sveltia-cms.js');

if (!existsSync(SOURCE)) {
  console.error(
    `CMS introuvable : ${SOURCE}\n` +
      `Lance « npm install » avant de générer le site.`,
  );
  process.exit(1);
}

mkdirSync(DOSSIER, { recursive: true });
copyFileSync(SOURCE, CIBLE);

const ko = Math.round(statSync(CIBLE).size / 1024);
console.log(`Admin : sveltia-cms.js copié dans public/admin/ (${ko} Ko).`);

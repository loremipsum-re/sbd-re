// @ts-check
import { readFileSync, statSync } from 'node:fs';
import { defineConfig } from 'astro/config';
import sitemap from '@astrojs/sitemap';
import svelte from '@astrojs/svelte';

/** Reproduit meetNameSlug() et athleteSlug() de src/lib/slug.ts. */
const slugifier = (/** @type {string} */ valeur) =>
  valeur
    .normalize('NFD')
    .replace(/\p{Mn}/gu, '')
    .toLowerCase()
    .replace(/#/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');

/** @type {(f: string) => { date: string, name: string, meetName: string }[]} */
const lireDonnees = (f) =>
  JSON.parse(readFileSync(new URL(`./src/data/${f}`, import.meta.url), 'utf8'));

/**
 * Date de dernière modification de chaque page, pour le plan de site.
 *
 * Coller partout la date du build serait exact mais inutile : Google verrait
 * 413 pages modifiées le même jour et n'en tirerait aucune priorité. On donne
 * donc à chaque page la date de sa donnée la plus récente. Une fiche d'athlète
 * qui n'a plus concouru depuis 2019 porte la date de 2019, et le robot cesse
 * de la réexplorer inutilement.
 */
function datesDeModification() {
  /** @type {Map<string, string>} */
  const dates = new Map();
  const retenir = (/** @type {string} */ url, /** @type {string} */ date) => {
    const actuelle = dates.get(url);
    if (!actuelle || actuelle < date) dates.set(url, date);
  };

  for (const r of lireDonnees('results.json')) {
    const nom = slugifier(r.meetName);
    retenir(`/athlete/${slugifier(r.name)}/`, r.date);
    retenir(`/competitions/${nom}/${r.date}/`, r.date);
    retenir(`/competitions/${nom}/`, r.date);
  }

  // Les compétitions extérieures figurent sur les fiches d'athlètes : elles
  // comptent donc dans leur date de dernière modification.
  for (const r of lireDonnees('results-exterieur.json')) {
    retenir(`/athlete/${slugifier(r.name)}/`, r.date);
  }

  return dates;
}

const DATES = datesDeModification();

/**
 * Date de référence pour les pages qui ne dépendent pas d'un résultat précis
 * (accueil, à propos…) : celle de la dernière régénération des données.
 */
const DATE_DONNEES = statSync(new URL('./src/data/results.json', import.meta.url))
  .mtime.toISOString()
  .slice(0, 10);

/**
 * Redirections des anciennes adresses de compétition.
 *
 * Les pages de compétition sont passées de « /competitions/2025-07-19-nom/ » à
 * « /competitions/nom/2025-07-19/ », pour regrouper les éditions successives
 * d'un même événement sous un dossier commun.
 *
 * Ces adresses ayant été publiées et référencées dans le plan de site, les
 * laisser tomber en 404 perdrait le bénéfice de leur indexation. Astro génère
 * ici une page de redirection pour chacune.
 *
 * À supprimer quand les moteurs de recherche auront pris acte du changement,
 * disons dans un an.
 */
function ancienesAdressesCompetitions() {
  /** @type {{ date: string, meetName: string }[]} */
  const resultats = JSON.parse(
    readFileSync(new URL('./src/data/results.json', import.meta.url), 'utf8'),
  );

  /** @type {Record<string, string>} */
  const redirections = {};
  for (const r of resultats) {
    const nom = slugifier(r.meetName);
    redirections[`/competitions/${r.date}-${nom}`] = `/competitions/${nom}/${r.date}`;
  }
  return redirections;
}

const REDIRECTIONS = ancienesAdressesCompetitions();

// https://astro.build/config
export default defineConfig({
  // Sert à générer les URL absolues : balises canoniques, plan de site, partages.
  site: 'https://sbd.re',

  // Sortie 100 % statique : le build produit des fichiers HTML, rien d'autre.
  // C'est ce qui permet d'héberger sur un mutualisé OVH, incapable d'exécuter Node.
  output: 'static',

  build: {
    // Génère /classement/index.html plutôt que /classement.html.
    // Apache, le serveur d'OVH, sert alors /classement/ sans configuration.
    format: 'directory',
  },

  // On aligne Astro sur le comportement d'Apache, qui redirige /classement vers
  // /classement/. Sans ça, le serveur de développement et la production ne se
  // comportent pas pareil, et l'écart se découvre après la mise en ligne.
  trailingSlash: 'always',

  redirects: REDIRECTIONS,

  integrations: [
    sitemap({
      // Les pages de redirection ne doivent pas figurer au plan de site : elles
      // n'ont pas de contenu propre et diluent le référencement.
      filter: (page) => !/\/competitions\/\d{4}-\d{2}-\d{2}-/.test(page),

      serialize(item) {
        const chemin = new URL(item.url).pathname;
        item.lastmod = DATES.get(chemin) ?? DATE_DONNEES;
        return item;
      },
    }),

    // Svelte sert uniquement aux « îlots » : les rares zones réellement
    // interactives de la future partie communauté. Les pages officielles sont
    // du HTML pur, leurs tableaux étant pilotés en JavaScript natif.
    svelte(),
  ],
});

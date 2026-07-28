// @ts-check
import { readFileSync } from 'node:fs';
import { defineConfig } from 'astro/config';
import sitemap from '@astrojs/sitemap';
import svelte from '@astrojs/svelte';

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

  // Reproduit meetNameSlug() de src/lib/slug.ts. La duplication est assumée :
  // ce fichier de configuration est chargé avant toute compilation TypeScript.
  /** @param {string} valeur */
  const slugifier = (valeur) =>
    valeur
      .normalize('NFD')
      .replace(/\p{Mn}/gu, '')
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '');

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
    }),

    // Svelte sert uniquement aux « îlots » : les rares zones réellement
    // interactives de la future partie communauté. Les pages officielles sont
    // du HTML pur, leurs tableaux étant pilotés en JavaScript natif.
    svelte(),
  ],
});

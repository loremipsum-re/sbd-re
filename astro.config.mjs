// @ts-check
import { defineConfig } from 'astro/config';
import sitemap from '@astrojs/sitemap';
import svelte from '@astrojs/svelte';

// https://astro.build/config
export default defineConfig({
  // Sert à générer les URL absolues : balises canoniques, sitemap, partages sociaux.
  site: 'https://sbd.re',

  // Sortie 100 % statique : le build produit des fichiers HTML, rien d'autre.
  // C'est ce qui permet d'héberger sur un mutualisé OVH, qui ne sait pas exécuter de Node.
  output: 'static',

  build: {
    // Génère /classement/index.html plutôt que /classement.html.
    // Apache (le serveur d'OVH) sert alors /classement/ sans configuration particulière.
    format: 'directory',
  },

  // On aligne Astro sur le comportement d'Apache, qui redirige /classement vers
  // /classement/. Sans ça, le serveur de dev et la production ne se comportent pas
  // pareil, et on découvre l'écart au pire moment : après la mise en ligne.
  trailingSlash: 'always',

  integrations: [
    // Génère sitemap-index.xml : la liste des 300 et quelques pages du site,
    // à destination des moteurs de recherche. Sans lui, les fiches athlètes
    // ne seraient découvertes qu'au fil des liens, très lentement.
    sitemap(),

    // Svelte sert uniquement aux « îlots » : les rares zones réellement
    // interactives de la future partie communauté. Les pages officielles sont
    // du HTML pur — le classement filtre ses 266 lignes en JavaScript natif.
    svelte(),
  ],
});

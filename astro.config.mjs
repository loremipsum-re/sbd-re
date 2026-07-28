// @ts-check
import { defineConfig } from 'astro/config';
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

  // Svelte sert uniquement aux « îlots » : les rares zones réellement interactives
  // (filtres, connexion, formulaire de soumission). Le reste du site est du HTML
  // pur, sans JavaScript envoyé au navigateur.
  integrations: [svelte()],
});

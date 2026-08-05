import { defineCollection } from 'astro:content';
import { glob } from 'astro/loaders';
// `z` depuis zod directement : celui réexporté par `astro:content` est déprécié
// depuis Astro 7. Le paquet est déjà là, tiré par Astro lui-même.
import { z } from 'zod';

/**
 * Actualités : les articles du site.
 *
 * Écrire un article se réduit à déposer un fichier Markdown dans
 * src/content/actualites/. Le nom du fichier devient l'adresse de la page.
 *
 * Tout le reste se génère : la liste, le tri, le lien vers la compétition,
 * le lien retour depuis la page de cette compétition, les articles liés, le
 * flux RSS et l'entrée au plan de site. Rien à inscrire nulle part ailleurs.
 *
 * Le champ `competition` est la seule clé à connaître. Elle vaut le chemin de
 * l'édition tel qu'il apparaît dans l'adresse du site, par exemple
 * « open-de-la-fournaise/2026-01-18 ». C'est ce qui fabrique le lien dans les
 * DEUX sens.
 */
const actualites = defineCollection({
  loader: glob({ pattern: '**/*.md', base: './src/content/actualites' }),
  schema: z.object({
    titre: z.string(),
    /** Résumé affiché dans la liste et donné aux moteurs de recherche. */
    description: z.string(),
    date: z.coerce.date(),
    /**
     * Chemin de la compétition dont parle l'article, sans barre oblique
     * initiale ni finale. Exemple : « open-de-la-fournaise/2026-01-18 ».
     *
     * Une adresse inexistante est rejetée à la génération plutôt qu'affichée
     * en lien mort : voir la vérification dans lib/actualites.ts.
     */
    competition: z.string().optional(),
    /**
     * Athlètes cités, écrits exactement comme dans les données. Ils créent le
     * lien vers leur fiche et rapprochent les articles entre eux.
     */
    athletes: z.array(z.string()).default([]),
    /** Un brouillon reste invisible en production. */
    brouillon: z.boolean().default(false),
  }),
});

export const collections = { actualites };

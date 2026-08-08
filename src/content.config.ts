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

/**
 * Surcouches d'athlètes.
 *
 * Ce ne sont PAS les données des athlètes. Celles-ci viennent
 * d'OpenPowerlifting et sont réécrites intégralement à chaque exécution de
 * « npm run data:update » : une modification directe y serait effacée sans
 * avertissement.
 *
 * Ce fichier-ci s'ajoute PAR-DESSUS et survit à la régénération. On y met ce
 * que la donnée sportive ne contient pas : une photo, une présentation, des
 * liens. Les performances restent ce que les juges ont validé.
 *
 * Le nom du fichier est le slug de l'athlète, celui que produit athleteSlug().
 * Le CMS le pose lui-même, à partir d'une liste tirée des données.
 */
const athletes = defineCollection({
  loader: glob({ pattern: '**/*.md', base: './src/content/athletes' }),
  schema: z.object({
    slug: z.string(),
    photo: z.string().optional(),
    liens: z
      .array(z.object({ libelle: z.string(), url: z.string() }))
      .default([]),
  }),
});

/**
 * Partenaires.
 *
 * Des sponsors DIRECTS, jamais une régie. Une image et un lien hébergés ici,
 * donc aucune requête vers un tiers, aucun pistage, et aucun consentement à
 * demander. Une régie type AdSense tomberait derrière la bannière, serait
 * invisible pour qui refuse, et contredirait la posture du site.
 *
 * Trois règles gouvernent l'affichage, appliquées dans lib/sponsors.ts :
 *   1. UN SEUL emplacement par page, jamais deux.
 *   2. JAMAIS dans une donnée : ni entre deux lignes de classement, ni dans un
 *      tableau de résultats. Le classement est le produit.
 *   3. JAMAIS au-dessus du contenu : le visiteur obtient ce qu'il est venu
 *      chercher avant de voir un partenaire.
 */
const sponsors = defineCollection({
  loader: glob({ pattern: '**/*.md', base: './src/content/sponsors' }),
  schema: z.object({
    nom: z.string(),
    /** Chemin de l'image, servie depuis le site. */
    logo: z.string(),
    url: z.string(),
    /**
     * Où ce partenaire peut apparaître. Un partenaire sans emplacement ne
     * s'affiche nulle part, ce qui est la façon simple de le mettre en pause.
     */
    emplacements: z
      .array(z.enum(['competition', 'article', 'pied']))
      .default([]),
    actif: z.boolean().default(true),
    /** Petit nombre en premier, dans le bandeau du pied de page. */
    ordre: z.number().default(0),
  }),
});

/**
 * Salles de sport de La Réunion.
 *
 * Les 41 fiches d'origine viennent d'OpenStreetMap, extraites UNE FOIS le
 * 8 août 2026 puis versionnées ici. Elles ne se resynchronisent pas : une
 * requête à chaque build ferait dépendre la génération d'un service extérieur,
 * et surtout écraserait les corrections faites depuis le CMS.
 *
 * La donnée OSM est sous licence ODbL, qui impose l'attribution. Elle figure
 * sur la page /salles/, comme le crédit OpenPowerlifting figure au pied de page.
 *
 * `actif` à false écarte une fiche de la page. Huit l'étaient à l'import :
 * gymnases municipaux et lieux qui ne sont pas des salles de musculation. Le
 * tri final revient à l'auteur, qui connaît la scène locale.
 */
const salles = defineCollection({
  loader: glob({ pattern: '**/*.md', base: './src/content/salles' }),
  schema: z.object({
    nom: z.string(),
    commune: z.string(),
    lat: z.number(),
    lon: z.number(),
    rue: z.string().optional(),
    site: z.string().optional(),
    telephone: z.string().optional(),
    horaires: z.string().optional(),
    /** Référence de l'objet OSM d'origine, pour retrouver la source. */
    osm: z.string().optional(),
    actif: z.boolean().default(true),
  }),
});

export const collections = { actualites, athletes, sponsors, salles };

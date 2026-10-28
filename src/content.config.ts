import { existsSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { defineCollection } from 'astro:content';
import { glob } from 'astro/loaders';
// `z` depuis zod directement : celui réexporté par `astro:content` est déprécié
// depuis Astro 7. Le paquet est déjà là, tiré par Astro lui-même.
import { z } from 'zod';
import { dimensionsImage } from './lib/dimensions-image';
import {
  ACCROCHE_MAX,
  EXTENSIONS_IMAGE,
  IDS_EMPLACEMENTS_PARTENAIRE,
  IMAGES,
  dimensions,
  piecesExigees,
  pixels,
  type IdImage,
} from './lib/formats-partenaires';
import { IDS_INFOGRAPHIES } from './lib/infographies';

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
    /**
     * Infographie posée entre le chapeau et le texte. Les valeurs possibles
     * sont celles du catalogue, lib/infographies.ts : une valeur inconnue est
     * rejetée à la génération.
     */
    infographie: z.enum(IDS_INFOGRAPHIES).optional(),
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
 * Quatre règles gouvernent l'affichage, appliquées dans lib/sponsors.ts :
 *   1. UN SEUL emplacement de contenu par page, jamais deux. Le bandeau du
 *      pied de page est à part et ne compte pas dans cette règle.
 *   2. JAMAIS dans une donnée : ni entre deux lignes de classement, ni dans un
 *      tableau de résultats. Le classement est le produit.
 *   3. JAMAIS au-dessus du contenu : le visiteur obtient ce qu'il est venu
 *      chercher avant de voir un partenaire.
 *   4. JAMAIS sur une fiche d'athlète, bandeau compris : une marque y
 *      prêterait à l'athlète une caution qu'il n'a pas donnée.
 *
 * LES FORMATS SONT FIXES, et le schéma les fait respecter : voir
 * lib/formats-partenaires.ts. Chaque place impose son format, et un partenaire
 * actif doit fournir ce que ses places demandent. Une pièce manquante ou une
 * image hors format arrête la génération sur un message qui les nomme, au lieu
 * de laisser en ligne une place vide ou une bannière recadrée.
 */

/**
 * Le chemin d'une image servie par le site, tel que le CMS l'écrit :
 * « /images/logo.png ». Une adresse vers un autre site est refusée. L'image se
 * chargerait chez un tiers, qui verrait passer chaque visiteur, et c'est
 * précisément ce qu'un partenaire direct évite.
 */
const cheminImage = z
  .string()
  .regex(
    new RegExp(`^/(?!/)[^?#]+\\.(?:${EXTENSIONS_IMAGE.join('|')})$`, 'i'),
    'Une image de partenaire est servie par le site, en PNG, JPEG, WebP ou SVG. ' +
      'Exemple attendu : /images/logo.png.',
  );

/**
 * Un champ facultatif du CMS. Laissé vide, il arrive en chaîne vide, en
 * espaces ou en `null` selon l'outil qui a écrit le fichier : tout cela vaut
 * « absent ».
 */
const facultatif = <T extends z.ZodType>(schema: T) =>
  z.preprocess(
    (v) => (v === null || (typeof v === 'string' && v.trim() === '') ? undefined : v),
    schema.optional(),
  );

/**
 * Pourquoi une image ne convient pas à son format, ou `null` si elle convient.
 *
 * Le fichier est cherché dans public/, depuis le dossier où la commande est
 * lancée : la racine du projet, pour `npm run build` comme pour
 * `npm run check`. Le CMS, lui, ne lance pas ce contrôle : il tourne dans le
 * navigateur. Un partenaire hors format passe donc le CMS, puis arrête la
 * génération de GitHub Actions, et le site en ligne reste tel qu'il était.
 */
function ecartAuFormat(chemin: string, image: IdImage): string | null {
  const attendu = IMAGES[image];
  const fichier = join(process.cwd(), 'public', chemin);
  if (!existsSync(fichier)) return `${attendu.libelle} introuvable : public${chemin}.`;

  const lue = dimensionsImage(fichier);
  if (!lue) return `${attendu.libelle} illisible : public${chemin}.`;

  const rapport = lue.largeur / lue.hauteur;
  const rapportAttendu = attendu.largeur / attendu.hauteur;
  if (Math.abs(rapport - rapportAttendu) / rapportAttendu > 0.01) {
    return (
      `${attendu.libelle} de ${dimensions(lue)} px. ` +
      `Le format attendu est ${dimensions(attendu)} px, ou un multiple.`
    );
  }
  if (!lue.vectorielle && lue.largeur < attendu.largeur) {
    return (
      `${attendu.libelle} de ${dimensions(lue)} px. L'image doit mesurer ` +
      `${pixels(attendu.largeur)} px de large au moins pour rester nette.`
    );
  }
  if (/\.svg$/i.test(chemin)) {
    const actif = contenuActifSvg(readFileSync(fichier, 'utf8'));
    if (actif) {
      return (
        `${attendu.libelle} : le fichier SVG contient ${actif}. ` +
        `Le réexporter sans script ni ressource extérieure.`
      );
    }
  }
  return null;
}

/**
 * Ce qui rend un SVG actif, ou `null` s'il est inerte.
 *
 * Affiché par <img>, un SVG n'exécute rien et ne charge rien d'extérieur. Ouvert
 * à sa propre adresse, /images/logo.svg, il redevient un document : un script
 * y tournerait au nom de sbd.re, avec accès à tout ce que le site garde, et le
 * jour venu à la session des membres de la communauté. Un partenaire direct est
 * une image et un lien, jamais un script ni un pistage : la génération refuse
 * donc ces motifs, plutôt que de compter sur la vigilance de qui dépose le
 * fichier.
 *
 * Les motifs sont larges à dessein. Un logo exporté pour le web n'en contient
 * aucun, et un faux refus se corrige en réexportant le fichier. Les adresses
 * d'espace de noms, `xmlns="http://www.w3.org/2000/svg"`, ne sont pas des
 * ressources et passent.
 */
function contenuActifSvg(texte: string): string | null {
  const motifs: [RegExp, string][] = [
    [/<script[\s/>]/i, 'un élément <script>'],
    [/\son[a-z]+\s*=/i, "un attribut d'événement, comme onload"],
    [/javascript\s*:/i, 'une adresse javascript:'],
    [/<foreignObject[\s/>]/i, 'un élément <foreignObject>'],
    [/(?:href|src)\s*=\s*["']\s*(?:[a-z][a-z0-9+.-]*:)?\/\//i, 'une ressource extérieure'],
    [/url\(\s*["']?\s*(?:[a-z][a-z0-9+.-]*:)?\/\//i, 'une ressource extérieure'],
    [/@import/i, 'une feuille de style importée'],
  ];
  return motifs.find(([motif]) => motif.test(texte))?.[1] ?? null;
}

/** Ce que dit le message quand une pièce exigée par les places manque. */
const PIECE_MANQUANTE = {
  banniere: `Une place en bannière demande une image de ${dimensions(IMAGES.banniere)} px.`,
  logo: `Une carte ou le pied de page demandent un logo de ${dimensions(IMAGES.logo)} px.`,
  accroche: `Une carte demande une accroche de ${ACCROCHE_MAX} caractères au plus.`,
} as const;

const sponsors = defineCollection({
  loader: glob({ pattern: '**/*.md', base: './src/content/sponsors' }),
  schema: z
    .object({
      nom: z.string().trim().min(1, 'Le nom du partenaire est obligatoire.'),
      url: z
        .string()
        .regex(/^https?:\/\/[^\s/]+/, "L'adresse du partenaire commence par https://."),
      /**
       * Où ce partenaire peut apparaître. Un partenaire sans emplacement ne
       * s'affiche nulle part, ce qui est la façon simple de le mettre en pause.
       * Le format suit la place : voir lib/formats-partenaires.ts.
       */
      emplacements: z
        .array(
          z.enum(IDS_EMPLACEMENTS_PARTENAIRE, {
            error: `Emplacement inconnu. Les places existantes : ${IDS_EMPLACEMENTS_PARTENAIRE.join(', ')}.`,
          }),
        )
        .default([]),
      /** Image des places en bannière. */
      banniere: facultatif(cheminImage),
      /** Image de la carte et du pied de page. */
      logo: facultatif(cheminImage),
      /** La phrase de la carte, sous le nom. */
      accroche: facultatif(
        z
          .string()
          .trim()
          .max(ACCROCHE_MAX, `L'accroche tient en ${ACCROCHE_MAX} caractères au plus.`),
      ),
      actif: z.boolean().default(true),
      /** Petit nombre en premier, dans le bandeau du pied de page. */
      ordre: z.number().default(0),
    })
    .superRefine((partenaire, ctx) => {
      // Un partenaire en pause garde ses fichiers tels quels : rien n'est
      // affiché, rien n'est exigé.
      if (!partenaire.actif) return;

      for (const piece of piecesExigees(partenaire.emplacements)) {
        if (!partenaire[piece]) {
          ctx.addIssue({ code: 'custom', path: [piece], message: PIECE_MANQUANTE[piece] });
        }
      }

      // Le fichier n'est ouvert que si le chemin est valable. Zod poursuit
      // jusqu'ici même après un refus de `cheminImage`, et une adresse
      // extérieure donnait en plus un « introuvable » trompeur.
      for (const image of ['banniere', 'logo'] as const) {
        const chemin = partenaire[image];
        const lisible = chemin !== undefined && cheminImage.safeParse(chemin).success;
        const ecart = lisible ? ecartAuFormat(chemin, image) : null;
        if (ecart) ctx.addIssue({ code: 'custom', path: [image], message: ecart });
      }
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

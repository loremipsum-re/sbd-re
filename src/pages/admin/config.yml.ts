/**
 * Configuration du CMS, GÉNÉRÉE et non écrite à la main.
 *
 * La raison tient en un chiffre : 312. C'est le nombre d'athlètes, et il
 * change chaque mois. Écrire cette liste à la main dans un fichier statique
 * reviendrait à la tenir à jour à la main, exactement ce qu'on cherche à
 * éviter. Elle est donc lue depuis les données à chaque génération.
 *
 * Conséquence agréable : après la mise à jour mensuelle, les nouveaux athlètes
 * apparaissent d'eux-mêmes dans le menu déroulant du CMS.
 */
import type { APIRoute } from 'astro';
import { results } from '../../lib/data';
import { listeMeets } from '../../lib/meets';
import { athleteSlug } from '../../lib/slug';

const DEPOT = 'loremipsum-re/sbd-re';

/** Échappe une chaîne pour YAML, en la mettant entre guillemets doubles. */
const y = (v: string) => `"${v.replace(/\\/g, '\\\\').replace(/"/g, '\\"')}"`;

export const GET: APIRoute = async () => {
  const athletes = [...new Set(results.map((r) => r.name))].sort((a, b) =>
    a.localeCompare(b, 'fr'),
  );

  /*
   * La VALEUR est le slug, pas le nom. C'est lui qui nomme le fichier, et il
   * doit correspondre exactement à celui que produit athleteSlug() pour que
   * la surcouche retrouve sa fiche. Laisser le CMS fabriquer son propre slug
   * à partir du nom introduirait un second algorithme, donc un désaccord.
   */
  const optionsAthletes = athletes
    .map((nom) => `          - { label: ${y(nom)}, value: ${y(athleteSlug(nom))} }`)
    .join('\n');

  const optionsCompetitions = listeMeets()
    .sort((a, b) => b.date.localeCompare(a.date))
    .map(
      (m) =>
        `          - { label: ${y(`${m.meetName} — ${m.date}`)}, value: ${y(m.chemin)} }`,
    )
    .join('\n');

  const yaml = `# ---------------------------------------------------------------------------
# CONFIGURATION GÉNÉRÉE — ne pas modifier ce fichier à la main.
#
# Elle est produite à chaque build par src/pages/admin/config.yml.ts, qui y
# injecte la liste des athlètes et des compétitions depuis les données. Toute
# retouche directe serait écrasée à la génération suivante.
# ---------------------------------------------------------------------------

backend:
  name: github
  repo: ${DEPOT}
  branch: main

# Les images déposées depuis le CMS atterrissent ici et sont versionnées avec
# le reste. « public_folder » est l'adresse par laquelle le site les sert.
media_folder: public/images
public_folder: /images

# La langue de l'interface suit celle du site.
locale: fr

collections:
  # -------------------------------------------------------------------------
  - name: actualites
    label: Actualités
    label_singular: Article
    folder: src/content/actualites
    create: true
    delete: true
    extension: md
    format: frontmatter
    slug: "{{year}}-{{month}}-{{day}}-{{titre}}"
    summary: "{{titre}}"
    sortable_fields: [date, titre]
    fields:
      - { name: titre, label: Titre, widget: string }
      - name: description
        label: Résumé
        widget: text
        hint: Affiché dans la liste et donné aux moteurs de recherche.
      - { name: date, label: Date, widget: datetime, date_format: "DD/MM/YYYY", time_format: false, picker_utc: true }
      - name: competition
        label: Compétition traitée
        widget: select
        required: false
        hint: Crée le lien vers la compétition, ET le lien retour depuis sa page.
        options:
${optionsCompetitions || '          []'}
      - name: athletes
        label: Athlètes cités
        widget: select
        multiple: true
        required: false
        default: []
        hint: Chaque athlète cité obtient un lien vers sa fiche.
        options:
${optionsAthletes || '          []'}
      - { name: brouillon, label: Brouillon, widget: boolean, default: false, hint: "Un brouillon reste invisible en ligne." }
      - { name: body, label: Article, widget: markdown }

  # -------------------------------------------------------------------------
  # Surcouches d'athlètes.
  #
  # Ce ne sont PAS les données des athlètes : celles-ci viennent
  # d'OpenPowerlifting et sont réécrites intégralement chaque mois par
  # « npm run data:update ». Une modification directe serait effacée sans
  # avertissement.
  #
  # Ce que l'on dépose ici s'ajoute par-dessus et survit à la régénération :
  # une photo, une présentation, des liens. Les performances, elles, restent
  # ce que les juges ont validé.
  - name: athletes
    label: Athlètes
    label_singular: Athlète
    folder: src/content/athletes
    create: true
    delete: true
    extension: md
    format: frontmatter
    slug: "{{fields.slug}}"
    summary: "{{slug}}"
    fields:
      - name: slug
        label: Athlète
        widget: select
        hint: La liste suit les données, elle s'étoffe à chaque mise à jour mensuelle.
        options:
${optionsAthletes || '          []'}
      - { name: photo, label: Photo, widget: image, required: false, hint: "Demande l'accord de l'athlète avant de publier sa photo." }
      - name: liens
        label: Liens
        widget: list
        required: false
        default: []
        fields:
          - { name: libelle, label: Libellé, widget: string }
          - { name: url, label: Adresse, widget: string }
      - { name: body, label: Présentation, widget: markdown, required: false }
`;

  return new Response(yaml, {
    headers: { 'Content-Type': 'text/yaml; charset=utf-8' },
  });
};

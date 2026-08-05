/**
 * Flux RSS des actualités.
 *
 * Écrit à la main plutôt qu'avec @astrojs/rss : le format tient en trente
 * lignes, et le projet évite d'ajouter une dépendance pour ce qu'un gabarit de
 * chaîne fait aussi bien.
 *
 * Il se met à jour tout seul : un article déposé dans src/content/actualites/
 * y entre à la génération suivante, sans rien inscrire ici.
 */
import type { APIRoute } from 'astro';
import { articles } from '../../lib/actualites';

/** Échappe les cinq caractères que XML ne tolère pas dans du texte. */
const echapper = (v: string) =>
  v.replace(
    /[&<>"']/g,
    (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&apos;' })[c]!,
  );

export const GET: APIRoute = async ({ site }) => {
  const base = site ?? new URL('https://sbd.re');
  const liste = (await articles()).filter((a) => !a.data.brouillon);

  const items = liste
    .map((a) => {
      const url = new URL(`/actualites/${a.id}/`, base).href;
      return `    <item>
      <title>${echapper(a.data.titre)}</title>
      <link>${url}</link>
      <guid isPermaLink="true">${url}</guid>
      <description>${echapper(a.data.description)}</description>
      <pubDate>${a.data.date.toUTCString()}</pubDate>
    </item>`;
    })
    .join('\n');

  const xml = `<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
    <title>SBD.re — Actualités</title>
    <link>${new URL('/actualites/', base).href}</link>
    <atom:link href="${new URL('/actualites/rss.xml', base).href}" rel="self" type="application/rss+xml" />
    <description>Les compétitions de force athlétique à La Réunion, racontées.</description>
    <language>fr-FR</language>
${items}
  </channel>
</rss>
`;

  return new Response(xml, {
    headers: { 'Content-Type': 'application/rss+xml; charset=utf-8' },
  });
};

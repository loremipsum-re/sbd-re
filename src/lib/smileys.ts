/**
 * Les smileys d'époque, codes des forums phpBB, choix de l'auteur du
 * 6 novembre 2026 (docs/comptes.md, section 6.4).
 *
 * SOURCE UNIQUE : le chat lit ce fichier, et le forum, rendu par PHP, lit
 * sa copie générée à /api/smileys.json (src/pages/api/smileys.json.ts).
 *
 * La base garde le code tel que tapé. L'image remplace le code à
 * l'affichage, et SEULEMENT quand il est seul entre deux espaces ou en bout
 * de message : « 5x8) » reste « 5x8) ». Les dessins sont ceux de phpBB en
 * vectoriel, sous licence Apache 2.0, public/smileys/LICENSE.
 *
 * Pas d'import, pas d'enum : `node` lit ce fichier tel quel pour l'autotest
 * (scripts/verifier-smileys.mjs).
 */

export interface Smiley {
  code: string;
  fichier: string;
  alt: string;
}

export const SMILEYS: readonly Smiley[] = [
  { code: ':)', fichier: 'icon_e_smile', alt: 'sourire' },
  { code: ':D', fichier: 'icon_e_biggrin', alt: 'grand sourire' },
  { code: ';)', fichier: 'icon_e_wink', alt: "clin d'œil" },
  { code: ':(', fichier: 'icon_e_sad', alt: 'triste' },
  { code: ':o', fichier: 'icon_e_surprised', alt: 'surpris' },
  { code: ':shock:', fichier: 'icon_eek', alt: 'choqué' },
  { code: ':?', fichier: 'icon_e_confused', alt: 'perplexe' },
  { code: '8)', fichier: 'icon_cool', alt: 'cool' },
  { code: ':lol:', fichier: 'icon_lol', alt: 'mort de rire' },
  { code: ':x', fichier: 'icon_mad', alt: 'en colère' },
  { code: ':P', fichier: 'icon_razz', alt: 'tire la langue' },
  { code: ':oops:', fichier: 'icon_redface', alt: 'gêné' },
  { code: ':cry:', fichier: 'icon_cry', alt: 'pleure' },
  { code: ':roll:', fichier: 'icon_rolleyes', alt: 'lève les yeux au ciel' },
  { code: ':mrgreen:', fichier: 'icon_mrgreen', alt: 'rire vert' },
  { code: ':idea:', fichier: 'icon_idea', alt: 'idée' },
];

export type Morceau = { texte: string } | { smiley: Smiley };

/**
 * Découpe un message en morceaux de texte et en smileys. Les espaces restent
 * dans les morceaux de texte : recollés, les morceaux redonnent le message.
 */
export function decouper(message: string): Morceau[] {
  const morceaux: Morceau[] = [];
  for (const bout of message.split(/(\s+)/)) {
    if (bout === '') continue;
    const smiley = SMILEYS.find((s) => s.code === bout);
    const dernier = morceaux.at(-1);
    if (smiley) morceaux.push({ smiley });
    else if (dernier && 'texte' in dernier) dernier.texte += bout;
    else morceaux.push({ texte: bout });
  }
  return morceaux;
}

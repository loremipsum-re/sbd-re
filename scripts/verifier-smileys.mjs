/**
 * Autotest du remplacement des smileys d'époque (docs/comptes.md, phase 7).
 * Node lit src/lib/smileys.ts directement, ses types effacés.
 *
 *   node scripts/verifier-smileys.mjs
 */
import { decouper } from '../src/lib/smileys.ts';

const cas = [
  [':)', [{ smiley: ':)' }], 'un code seul devient une image'],
  ['Bravo :) !', [{ texte: 'Bravo ' }, { smiley: ':)' }, { texte: ' !' }], 'entre deux espaces'],
  ['5x8)', [{ texte: '5x8)' }], '« 5x8) » ne bouge pas'],
  ['8) en fin', [{ smiley: '8)' }, { texte: ' en fin' }], 'en tête de message'],
  ['<b>gras</b> :D', [{ texte: '<b>gras</b> ' }, { smiley: ':D' }], '<b> reste du texte'],
  ['(:)', [{ texte: '(:)' }], 'collé à une parenthèse, rien'],
  ['💪 :lol:', [{ texte: '💪 ' }, { smiley: ':lol:' }], 'un emoji passe tel quel'],
];

let echecs = 0;
for (const [message, attendu, intitule] of cas) {
  const obtenu = decouper(message).map((m) => ('smiley' in m ? { smiley: m.smiley.code } : m));
  const ok = JSON.stringify(obtenu) === JSON.stringify(attendu);
  if (!ok) echecs++;
  console.log(`  [${ok ? 'ok' : 'ÉCHEC'}] ${intitule}${ok ? '' : ` : ${JSON.stringify(obtenu)}`}`);
}
console.log(`\n  ${cas.length - echecs} sur ${cas.length}`);
process.exit(echecs > 0 ? 1 : 0);

/**
 * Traitement appliqué à chaque page, une fois produite.
 *
 * EN SORTIE STATIQUE, CE FICHIER TOURNE À LA GÉNÉRATION, page par page, et
 * plus jamais ensuite. Il ne s'exécute pas chez le visiteur et rien n'est
 * envoyé au serveur d'OVH en plus du HTML. Le serveur de développement le
 * rejoue à chaque affichage, si bien que `npm run dev` montre la même chose
 * que le site en ligne.
 *
 * UN SEUL TRAITEMENT À CE JOUR : les liens sortants, qui s'ouvrent dans un
 * nouvel onglet et s'annoncent aux lecteurs d'écran. Voir lib/liens-sortants.ts.
 *
 * Il s'applique ici, sur le HTML final, plutôt que lien par lien dans les
 * gabarits, pour la même raison que la flèche ↗ vit en CSS. Les liens écrits
 * dans un article Markdown, saisis dans le CMS ou ajoutés demain dans un
 * gabarit sont tous traités, sans que personne ait à y penser.
 */
import { defineMiddleware } from 'astro:middleware';
import { ouvrirLiensSortants } from './lib/liens-sortants';

export const onRequest = defineMiddleware(async (_contexte, suivant) => {
  const reponse = await suivant();

  // Le flux RSS et la configuration du CMS passent aussi par ici : seul le
  // HTML est concerné.
  if (!reponse.headers.get('content-type')?.includes('text/html')) return reponse;

  const html = ouvrirLiensSortants(await reponse.text());
  const entetes = new Headers(reponse.headers);
  // La longueur a changé : l'ancienne valeur tronquerait la page.
  entetes.delete('content-length');

  return new Response(html, {
    status: reponse.status,
    statusText: reponse.statusText,
    headers: entetes,
  });
});

/**
 * Traitement appliqué à chaque page, une fois produite.
 *
 * EN SORTIE STATIQUE, CE FICHIER TOURNE À LA GÉNÉRATION, page par page, et
 * plus jamais ensuite. Il ne s'exécute pas chez le visiteur et rien n'est
 * envoyé au serveur d'OVH en plus du HTML. Le serveur de développement le
 * rejoue à chaque affichage, si bien que `npm run dev` montre la même chose
 * que le site en ligne.
 *
 * DEUX TRAITEMENTS À CE JOUR, l'un après l'autre :
 *   - les liens sortants, qui s'ouvrent dans un nouvel onglet et s'annoncent
 *     aux lecteurs d'écran. Voir lib/liens-sortants.ts ;
 *   - les espaces insécables avant « : ; ! ? » et dans les guillemets « »,
 *     qui empêchent une ponctuation d'ouvrir ou de fermer seule une ligne.
 *     Voir lib/espaces-insecables.ts.
 *
 * Ils s'appliquent ici, sur le HTML final, plutôt que dans chaque gabarit,
 * pour la même raison que la flèche ↗ vit en CSS. Les liens et les phrases
 * écrits dans un article Markdown, saisis dans le CMS ou ajoutés demain dans
 * un gabarit sont tous traités, sans que personne ait à y penser.
 *
 * Le premier ne touche que des balises `<a>`, le second que du texte : leur
 * ordre ne change rien au résultat.
 */
import { defineMiddleware } from 'astro:middleware';
import { poserEspacesInsecables } from './lib/espaces-insecables';
import { ouvrirLiensSortants } from './lib/liens-sortants';

export const onRequest = defineMiddleware(async (_contexte, suivant) => {
  const reponse = await suivant();

  // Le flux RSS et la configuration du CMS passent aussi par ici : seul le
  // HTML est concerné.
  if (!reponse.headers.get('content-type')?.includes('text/html')) return reponse;

  const html = poserEspacesInsecables(ouvrirLiensSortants(await reponse.text()));
  const entetes = new Headers(reponse.headers);
  // La longueur a changé : l'ancienne valeur tronquerait la page.
  entetes.delete('content-length');

  return new Response(html, {
    status: reponse.status,
    statusText: reponse.statusText,
    headers: entetes,
  });
});

<?php
/**
 * Relier un membre à sa fiche d'athlète (lib/revendications.php).
 *
 *   GET  ?fiche=<slug>     ouvert à tous : le pseudo du membre relié, ou null.
 *                          La fiche, page statique, l'affiche.
 *   GET                    le membre : sa fiche reliée, sa demande en attente
 *   POST { slug, message } le membre réclame une fiche
 *   DELETE                 le membre retire son lien, ou sa demande
 *
 * L'admin relie ou refuse depuis /compte/admin/ (admin.php, actions
 * relier_fiche et refuser_fiche).
 */

declare(strict_types=1);

namespace SBDRE;

define('SBDRE_API', true);
require_once __DIR__ . '/lib/comptes.php';
require_once __DIR__ . '/lib/revendications.php';

$methode = ouvrirApi(['GET', 'POST', 'DELETE']);

if ($methode === 'GET' && isset($_GET['fiche'])) {
    $slug = (string) $_GET['fiche'];
    if (!slugAthleteValide($slug)) {
        refuser(404, 'Fiche inconnue.');
    }
    // Lu à chaque visite d'une fiche : cinq minutes de cache dans le navigateur.
    header('Cache-Control: public, max-age=300');
    repondreOk('', ['pseudo' => membreLie($slug)]);
}

$membre = exigerActif();
$membreId = (int) $membre['id'];

if ($methode === 'GET') {
    repondreOk('', etatFiche($membreId));
}

if ($methode === 'DELETE') {
    retirerFiche($membreId);
    repondreOk('Le lien avec votre fiche d\'athlète est retiré.');
}

$slug = texte('slug');
$message = texte('message');
if (!slugAthleteValide($slug)) {
    refuser(422, 'Fiche illisible.');
}
if (!messageRevendicationValide($message)) {
    refuser(422, 'Le message tient en ' . REVENDICATION_MESSAGE_MAX . ' caractères.');
}
$refus = refusRevendication($membreId, $slug);
if ($refus !== '') {
    refuser(409, $refus);
}
demanderFiche($membreId, $slug, $message);
repondreOk('Demande envoyée. L\'admin la relit, et vous recevez une notification à sa décision.');

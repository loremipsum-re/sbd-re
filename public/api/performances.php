<?php
/**
 * Ses propres performances.
 *
 *   GET     les lister, pour /compte/.
 *   POST    en ajouter une : mouvement, équipement, charge, date, vidéo,
 *           catégorie facultative. Publiée aussitôt, « non vérifiée ».
 *   DELETE  en retirer une, par son identifiant. Ses votes partent avec elle.
 *
 * Le profil public, lui, lit les performances directement en base
 * (profil-page.php) : rien ici ne sert un visiteur.
 */

declare(strict_types=1);

namespace SBDRE;

define('SBDRE_API', true);
require __DIR__ . '/lib/comptes.php';
require __DIR__ . '/lib/performances.php';

$methode = ouvrirApi(['GET', 'POST', 'DELETE']);

if ($methode === 'GET') {
    $membre = exigerMembre();
    $performances = lignes(
        'SELECT id, mouvement, equipement, charge_kg, categorie, date_performance, lien_video, date_verif_admin
           FROM performance WHERE membre_id = :id
          ORDER BY date_performance DESC, id DESC',
        ['id' => $membre['id']],
    );
    // Les records, calculés ici comme sur le profil : une seule règle, celle
    // de recordsDe(). L'accueil du membre s'en sert pour le situer.
    repondreOk('', ['performances' => $performances, 'records' => recordsDe($performances)]);
}

if ($methode === 'DELETE') {
    $membre = exigerMembre();
    $id = entree()['id'] ?? null;
    $performance = is_int($id) && $id > 0
        ? ligne('SELECT id, membre_id FROM performance WHERE id = :id', ['id' => $id])
        : null;

    if ($performance === null) {
        refuser(404, 'Cette performance n\'existe plus.');
    }
    if (!peutRetirerPerformance($performance, $membre)) {
        refuser(403, 'Cette performance ne vous appartient pas.');
    }

    executer('DELETE FROM performance WHERE id = :id', ['id' => $id]);
    repondreOk('Performance retirée.');
}

// POST : seuls les champs de la liste fermée sont lus, chacun validé.
$membre = exigerActif();
$recu = filtrerChamps(entree(), CHAMPS_PERFORMANCE);

$mouvement = is_string($recu['mouvement'] ?? null) ? $recu['mouvement'] : '';
$equipement = is_string($recu['equipement'] ?? null) ? $recu['equipement'] : '';
$charge = chargeValide($recu['charge_kg'] ?? null);
$date = is_string($recu['date_performance'] ?? null) ? $recu['date_performance'] : '';
$categorie = is_string($recu['categorie'] ?? null) ? trim($recu['categorie']) : '';
$video = is_string($recu['lien_video'] ?? null) ? trim($recu['lien_video']) : '';

if (!array_key_exists($mouvement, MOUVEMENTS)) {
    refuser(422, 'Choisissez un mouvement : squat, développé couché ou soulevé de terre.');
}
if (!array_key_exists($equipement, EQUIPEMENTS)) {
    refuser(422, 'Choisissez un équipement : raw, bandes ou équipé.');
}
if ($charge === null) {
    refuser(422, 'Indiquez la charge en kilos, jusqu\'à ' . CHARGE_MAX_KG . ' kg, deux décimales au plus.');
}
if (!datePerformanceValide($date, time())) {
    refuser(422, 'Indiquez une date réelle, entre 2000 et aujourd\'hui.');
}
if (!categorieValide($categorie)) {
    refuser(422, 'Catégorie de poids inconnue.');
}
if (!lienVideoValide($video)) {
    refuser(422, 'Le lien de la vidéo doit mener à Instagram, YouTube ou TikTok, en https.');
}

$recentes = ligne(
    'SELECT COUNT(*) AS n FROM performance
      WHERE membre_id = :id AND date_creation > NOW() - INTERVAL 1 DAY',
    ['id' => $membre['id']],
);
if ((int) ($recentes['n'] ?? 0) >= PERFORMANCES_PAR_JOUR) {
    refuser(429, PERFORMANCES_PAR_JOUR . ' performances ajoutées en un jour. Reprenez demain.');
}

executer(
    'INSERT INTO performance (membre_id, mouvement, equipement, charge_kg, categorie, date_performance, lien_video)
     VALUES (:membre, :mouvement, :equipement, :charge, :categorie, :date, :video)',
    [
        'membre'     => $membre['id'],
        'mouvement'  => $mouvement,
        'equipement' => $equipement,
        'charge'     => $charge,
        'categorie'  => $categorie !== '' ? $categorie : null,
        'date'       => $date,
        'video'      => $video,
    ],
);

repondreOk('Performance publiée sur votre profil, non vérifiée pour l\'instant.');

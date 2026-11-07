<?php
/**
 * Les notifications du membre (docs/forum.md, section 4).
 *
 *   GET  ?compte   le nombre de non lues, pour la cloche de l'en-tête
 *   GET            les 50 dernières, pour /compte/notifications/
 *   POST           tout marquer comme lu
 *
 * La cloche fait aussi partir les récapitulatifs par courriel qui sont dus,
 * deux au plus par passage : le site envoie, une tâche planifiée ne le peut
 * pas (lib/recapitulatif.php).
 */

declare(strict_types=1);

namespace SBDRE;

define('SBDRE_API', true);
require __DIR__ . '/lib/comptes.php';
require __DIR__ . '/lib/notifications.php';
require __DIR__ . '/lib/recapitulatif.php';

$methode = ouvrirApi(['GET', 'POST']);
$membreId = (int) exigerActif()['id'];

if ($methode === 'POST') {
    marquerLues($membreId);
    repondreOk('Notifications lues.');
}

if (isset($_GET['compte'])) {
    $nonLues = compterNonLues($membreId);
    // Une panne d'envoi ne fait jamais taire la cloche.
    try {
        envoyerRecapitulatifs();
    } catch (\Throwable $e) {
        error_log('SBD.re : récapitulatif, ' . $e->getMessage());
    }
    repondreOk('', ['non_lues' => $nonLues]);
}

repondreOk('', ['notifications' => listerNotifications($membreId), 'non_lues' => compterNonLues($membreId)]);

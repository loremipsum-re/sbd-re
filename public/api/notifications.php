<?php
/**
 * Les notifications du membre (docs/forum.md, section 4).
 *
 *   GET  ?compte   le nombre de non lues, pour la cloche de l'en-tête
 *   GET            les 50 dernières, pour /compte/notifications/
 *   POST           tout marquer comme lu
 */

declare(strict_types=1);

namespace SBDRE;

define('SBDRE_API', true);
require __DIR__ . '/lib/comptes.php';
require __DIR__ . '/lib/notifications.php';

$methode = ouvrirApi(['GET', 'POST']);
$membreId = (int) exigerActif()['id'];

if ($methode === 'POST') {
    marquerLues($membreId);
    repondreOk('Notifications lues.');
}

if (isset($_GET['compte'])) {
    repondreOk('', ['non_lues' => compterNonLues($membreId)]);
}

repondreOk('', ['notifications' => listerNotifications($membreId), 'non_lues' => compterNonLues($membreId)]);

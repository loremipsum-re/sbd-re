<?php
/**
 * Les gestes de l'admin. Pour l'instant un seul, phase 6 de docs/comptes.md :
 * poser ou retirer le badge « Vérifiée par SBD.re » d'un clic.
 *
 *   POST { performance_id, verifier: true|false }
 *
 * La file des signalements, le masquage et le bannissement arriveront ici
 * avec la phase 8. Un compte non admin reçoit 404 (exigerAdmin) : la page ne
 * confirme pas son existence à qui la cherche.
 *
 * Devenir admin se fait à la main, en base :
 *   UPDATE membre SET role = 'admin' WHERE pseudo = '…';
 */

declare(strict_types=1);

namespace SBDRE;

define('SBDRE_API', true);
require __DIR__ . '/lib/comptes.php';
require __DIR__ . '/lib/performances.php';
require __DIR__ . '/lib/notifications.php';

ouvrirApi(['POST']);
$admin = exigerAdmin();

$id = entree()['performance_id'] ?? null;
$verifier = entree()['verifier'] ?? null;
if (!is_int($id) || $id <= 0 || !is_bool($verifier)) {
    refuser(422, 'Demande illisible.');
}

// admin_id garde qui a vérifié : une vérification se retrace.
$touchees = $verifier
    ? executer(
        'UPDATE performance SET admin_id = :admin, date_verif_admin = NOW() WHERE id = :id',
        ['admin' => $admin['id'], 'id' => $id],
    )
    : executer(
        'UPDATE performance SET admin_id = NULL, date_verif_admin = NULL WHERE id = :id',
        ['id' => $id],
    );

if ($touchees === 0 && ligne('SELECT 1 FROM performance WHERE id = :id', ['id' => $id]) === null) {
    refuser(404, 'Cette performance n\'existe plus.');
}

if ($verifier && $touchees > 0) {
    $perf = ligne('SELECT membre_id, mouvement, charge_kg FROM performance WHERE id = :id', ['id' => $id]);
    notifier((int) $perf['membre_id'], 'badge', 'Votre performance au ' . mb_strtolower(MOUVEMENTS[$perf['mouvement']])
        . ', ' . kgFr((float) $perf['charge_kg']) . ', est vérifiée par SBD.re.', '/compte/');
}

repondreOk($verifier ? 'Badge « Vérifiée par SBD.re » posé.' : 'Badge retiré.');

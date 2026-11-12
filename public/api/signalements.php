<?php
/**
 * Signaler (docs/comptes.md, section 6.5).
 *
 *   POST { type, cible_id, motif? }
 *
 * type : message (chat), message_forum, membre (profil et avatar),
 * performance, forum. Membre confirmé seulement ; dix signalements par jour ;
 * un seul par membre et par cible. Le signalement part dans la file de
 * l'admin, /compte/admin/.
 */

declare(strict_types=1);

namespace SBDRE;

define('SBDRE_API', true);
require_once __DIR__ . '/lib/comptes.php';
require_once __DIR__ . '/lib/signalements.php';

ouvrirApi(['POST']);
$membre = exigerActif();

$type = texte('type');
$cible = entree()['cible_id'] ?? null;
if (!in_array($type, TYPES_SIGNALEMENT, true) || !is_int($cible) || $cible <= 0) {
    refuser(422, 'Signalement illisible.');
}
if ($type === 'membre' && $cible === (int) $membre['id']) {
    refuser(422, 'On ne se signale pas soi-même.');
}
if (!cibleExiste($type, $cible)) {
    refuser(404, 'Ce contenu n\'est plus visible.');
}
if (signalementsDuJour((int) $membre['id']) >= SIGNALEMENTS_PAR_JOUR) {
    refuser(429, SIGNALEMENTS_PAR_JOUR . ' signalements par jour au plus.');
}

signaler((int) $membre['id'], $type, $cible, texte('motif'));
repondreOk('Merci, le signalement est transmis à l\'admin.');

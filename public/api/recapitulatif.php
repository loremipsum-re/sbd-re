<?php
/**
 * Couper le récapitulatif depuis le lien d'un courriel, sans se connecter
 * (docs/forum.md, section 4). La page /compte/recapitulatif/ porte le bouton.
 *
 *   POST { jeton }   couper, une fois par lien
 *
 * POST et non GET : certaines messageries ouvrent les liens d'un courriel
 * pour les inspecter, et couperaient le récapitulatif à la place du membre.
 * Le jeton ne sert qu'à couper : rallumer passe par les réglages, connecté.
 */

declare(strict_types=1);

namespace SBDRE;

define('SBDRE_API', true);
require __DIR__ . '/lib/comptes.php';

ouvrirApi(['POST']);

$membreId = consommerJeton(texte('jeton'), 'recapitulatif');
if ($membreId === null) {
    refuser(410, 'Ce lien a déjà servi, ou il a expiré. Le récapitulatif se coupe aussi dans les réglages du compte.');
}

executer('UPDATE membre SET recapitulatif = 0 WHERE id = :id', ['id' => $membreId]);
repondreOk('Le récapitulatif est coupé. Il se rallume dans les réglages du compte.');

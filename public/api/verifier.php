<?php
/**
 * Confirmer son adresse, depuis le lien reçu par courriel.
 *
 *   GET ?jeton=…  consomme le jeton, confirme l'adresse, puis renvoie vers la
 *                 connexion avec le résultat.
 *
 * Redemander un lien se fait en se connectant : connexion.php en renvoie un
 * à qui donne le bon mot de passe d'un compte non confirmé. Exiger le mot de
 * passe empêche d'inonder la boîte de quelqu'un d'autre.
 *
 * Un antivirus de messagerie qui ouvre le lien avant le membre le consomme à
 * sa place. C'est sans dommage ici : ouvrir le lien prouve que le message est
 * arrivé, ce que la vérification cherche à savoir. Le lien de nouveau mot de
 * passe, lui, mène à un formulaire, pour cette raison exacte.
 */

declare(strict_types=1);

namespace SBDRE;

define('SBDRE_API', true);
require __DIR__ . '/lib/comptes.php';

ouvrirApi(['GET']);

$membreId = consommerJeton((string) ($_GET['jeton'] ?? ''), 'verification');

if ($membreId !== null) {
    executer('UPDATE membre SET courriel_verifie = 1 WHERE id = :id', ['id' => $membreId]);
}

header('Location: /compte/connexion/?adresse=' . ($membreId !== null ? 'confirmee' : 'lien-invalide'), true, 303);
exit;

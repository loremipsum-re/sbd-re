<?php
/**
 * Confirmer son adresse.
 *
 *   GET  ?jeton=…  le lien reçu par courriel. Consomme le jeton, confirme
 *                  l'adresse, puis renvoie vers /compte/ avec le résultat.
 *   POST           redemande un lien, pour le membre connecté.
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

$methode = ouvrirApi(['GET', 'POST']);

if ($methode === 'GET') {
    $membreId = consommerJeton((string) ($_GET['jeton'] ?? ''), 'verification');

    if ($membreId !== null) {
        executer('UPDATE membre SET courriel_verifie = 1 WHERE id = :id', ['id' => $membreId]);
    }

    header('Location: /compte/?adresse=' . ($membreId !== null ? 'confirmee' : 'lien-invalide'), true, 303);
    exit;
}

$membre = exigerMembre();

if ((int) $membre['courriel_verifie'] === 1) {
    repondreOk('Votre adresse est déjà confirmée.');
}
if (jetonsRecents((int) $membre['id'], 'verification') >= LIENS_PAR_HEURE) {
    refuser(429, 'Trois liens envoyés dans l\'heure. Regardez aussi les indésirables, puis réessayez plus tard.');
}

$courriel = ligne('SELECT courriel FROM membre WHERE id = :id', ['id' => $membre['id']]);

if (!envoyerLienVerification((int) $membre['id'], (string) $membre['pseudo'], (string) ($courriel['courriel'] ?? ''))) {
    refuser(500, 'Le courriel n\'est pas parti. Réessayez dans un moment.');
}

repondreOk('Lien envoyé. Il reste valable 24 heures.');

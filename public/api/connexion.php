<?php
/**
 * Ouvrir (POST) ou fermer (DELETE) la session.
 *
 * LE FREINAGE COMPTE LES ÉCHECS RÉCENTS par adresse et par IP, dans
 * `tentative_connexion`, avant même de lire le mot de passe : un hachage
 * solide ralentit chaque essai, il n'en limite pas le nombre.
 *
 * LA RÉPONSE NE DIT PAS LEQUEL DES DEUX EST FAUX. « Adresse ou mot de passe
 * incorrect », et le même temps de calcul dans les deux cas, grâce à
 * l'empreinte factice. Un compte banni ne se dit tel qu'au bon mot de passe.
 */

declare(strict_types=1);

namespace SBDRE;

define('SBDRE_API', true);
require __DIR__ . '/lib/comptes.php';

$methode = ouvrirApi(['POST', 'DELETE']);

if ($methode === 'DELETE') {
    fermerSession();
    repondreOk('Vous êtes déconnecté.');
}

$courriel = normaliserCourriel(texte('courriel'));
$motDePasse = brut('mot_de_passe');
$ip = ipVisiteur();

$echecs = ligne(
    'SELECT SUM(courriel = :c) AS par_courriel, SUM(ip = :ip) AS par_ip
       FROM tentative_connexion
      WHERE reussie = 0
        AND date_tentative > NOW() - INTERVAL ' . FENETRE_ECHECS_MINUTES . ' MINUTE
        AND (courriel = :c2 OR ip = :ip2)',
    ['c' => $courriel, 'ip' => $ip, 'c2' => $courriel, 'ip2' => $ip],
);
if ((int) ($echecs['par_courriel'] ?? 0) >= ECHECS_PAR_COURRIEL || (int) ($echecs['par_ip'] ?? 0) >= ECHECS_PAR_IP) {
    refuser(429, 'Trop d\'essais. Patientez un quart d\'heure, ou demandez un nouveau mot de passe.');
}

$membre = $courriel === '' ? null : ligne(
    'SELECT id, mot_de_passe_hash, banni FROM membre WHERE courriel = :c',
    ['c' => $courriel],
);

$bon = password_verify($motDePasse, $membre['mot_de_passe_hash'] ?? EMPREINTE_FACTICE) && $membre !== null;

executer(
    'INSERT INTO tentative_connexion (courriel, ip, reussie) VALUES (:c, :ip, :ok)',
    ['c' => substr($courriel !== '' ? $courriel : texte('courriel'), 0, 254), 'ip' => $ip, 'ok' => $bon ? 1 : 0],
);
// Purge en passant : une tentative de plus d'un jour ne sert plus à rien.
executer('DELETE FROM tentative_connexion WHERE date_tentative < NOW() - INTERVAL 1 DAY LIMIT 100');

if (!$bon) {
    refuser(401, 'Adresse ou mot de passe incorrect.');
}
if ((int) $membre['banni'] === 1) {
    refuser(403, 'Ce compte est suspendu.');
}

// L'algorithme par défaut de PHP évolue : l'empreinte suit à la connexion.
if (password_needs_rehash((string) $membre['mot_de_passe_hash'], PASSWORD_DEFAULT)) {
    executer(
        'UPDATE membre SET mot_de_passe_hash = :h WHERE id = :id',
        ['h' => password_hash($motDePasse, PASSWORD_DEFAULT), 'id' => $membre['id']],
    );
}

ouvrirSession((int) $membre['id']);
repondreOk('Vous êtes connecté.');

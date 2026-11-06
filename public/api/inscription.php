<?php
/**
 * Créer un compte : pseudo, adresse, mot de passe, case « 18 ans et plus ».
 *
 * Le compte naît NON VÉRIFIÉ et la session s'ouvre aussitôt : le membre
 * arrive sur /compte/, d'où il peut redemander le lien si le courriel tarde.
 * Tant que l'adresse n'est pas confirmée, il ne publie rien.
 *
 * L'adresse déjà inscrite est dite comme telle. Un site de force athlétique ne
 * cache pas grand-chose derrière, et la taire obligerait à écrire à son
 * propriétaire à chaque essai, ce qui ferait de ce formulaire une machine à
 * courriels pour qui voudrait l'en inonder.
 */

declare(strict_types=1);

namespace SBDRE;

define('SBDRE_API', true);
require __DIR__ . '/lib/comptes.php';

ouvrirApi(['POST']);

// Champ appât : rempli, c'est un robot. On répond comme si tout allait bien.
if (texte('site') !== '') {
    repondreOk('Compte créé. Un lien de confirmation part vers votre adresse.');
}

$pseudo = mb_strtolower(texte('pseudo'));
$courriel = normaliserCourriel(texte('courriel'));
$motDePasse = brut('mot_de_passe');

if ((entree()['charte'] ?? false) !== true) {
    refuser(422, 'Cochez la case : vous avez 18 ans ou plus et acceptez la charte.');
}
if (!pseudoValide($pseudo)) {
    refuser(422, 'Pseudo de 3 à 30 caractères : lettres sans accent, chiffres, point ou tiret bas, comme sur Instagram.');
}
if ($courriel === '') {
    refuser(422, 'Cette adresse électronique semble incorrecte.');
}
$erreur = erreurMotDePasse($motDePasse);
if ($erreur !== '') {
    refuser(422, $erreur);
}

$ip = ipVisiteur();
$recentes = ligne(
    'SELECT COUNT(*) AS n FROM membre
      WHERE ip_inscription = :ip AND date_inscription > NOW() - INTERVAL 1 HOUR',
    ['ip' => $ip],
);
if ((int) ($recentes['n'] ?? 0) >= INSCRIPTIONS_PAR_HEURE) {
    refuser(429, 'Trop de comptes créés depuis cette connexion. Réessayez dans une heure.');
}

if (ligne('SELECT 1 FROM membre WHERE pseudo = :p', ['p' => $pseudo]) !== null) {
    refuser(409, 'Ce pseudo est déjà pris.');
}
if (ligne('SELECT 1 FROM membre WHERE courriel = :c', ['c' => $courriel]) !== null) {
    refuser(409, 'Cette adresse a déjà un compte. Connectez-vous, ou demandez un nouveau mot de passe.');
}

try {
    executer(
        'INSERT INTO membre (courriel, mot_de_passe_hash, pseudo, ip_inscription)
         VALUES (:courriel, :hash, :pseudo, :ip)',
        [
            'courriel' => $courriel,
            'hash'     => password_hash($motDePasse, PASSWORD_DEFAULT),
            'pseudo'   => $pseudo,
            'ip'       => $ip,
        ],
    );
} catch (\PDOException $e) {
    // Deux inscriptions simultanées sur le même pseudo : la clé unique tranche.
    if ($e->getCode() === '23000') {
        refuser(409, 'Ce pseudo ou cette adresse vient d\'être pris.');
    }
    throw $e;
}

$membreId = (int) bdd()->lastInsertId();
ouvrirSession($membreId);

if (!envoyerLienVerification($membreId, $pseudo, $courriel)) {
    // Le compte existe : l'échec ne porte que sur le courriel, que /compte/
    // permet de redemander.
    repondreOk('Compte créé, mais le courriel de confirmation n\'est pas parti. Redemandez-le depuis votre compte.');
}

repondreOk('Compte créé. Un lien de confirmation part vers votre adresse, valable 24 heures.');

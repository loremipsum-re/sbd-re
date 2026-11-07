<?php
/**
 * Créer un compte : pseudo, adresse, mot de passe, case « 18 ans et plus ».
 *
 * RIEN N'EST UTILISABLE AVANT LA CONFIRMATION, choix de l'auteur du
 * 6 novembre 2026 : « je ne veux pas de poubelle ». Le compte naît non
 * confirmé, sans session. Seul le lien reçu par courriel l'active, et un
 * compte jamais confirmé s'efface au bout de 24 heures. Si le courriel ne
 * part pas, le compte s'efface aussitôt : on peut réessayer tout de suite.
 *
 * L'ANTI-SPAM EST MAISON, sans service tiers : champ appât, délai minimal
 * entre l'affichage et l'envoi, trois comptes par heure et par IP.
 *
 * L'adresse déjà inscrite est dite comme telle. La taire obligerait à écrire
 * à son propriétaire à chaque essai, ce qui ferait de ce formulaire une
 * machine à inonder sa boîte.
 */

declare(strict_types=1);

namespace SBDRE;

define('SBDRE_API', true);
require __DIR__ . '/lib/comptes.php';

ouvrirApi(['POST']);

// Champ appât : rempli, c'est un robot. On répond comme si tout allait bien.
if (texte('site') !== '') {
    repondreOk('Compte créé. Ouvrez le lien reçu par courriel pour l\'activer.');
}
if (!delaiHumain((int) (entree()['depuis'] ?? 0), time())) {
    refuser(422, 'Formulaire expiré ou envoyé trop vite. Rechargez la page et réessayez.');
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
if (adresseInjoignable($courriel)) {
    refuser(422, MESSAGE_INJOIGNABLE);
}
$erreur = erreurMotDePasse($motDePasse);
if ($erreur !== '') {
    refuser(422, $erreur);
}

// Le ménage d'abord : un pseudo ou une adresse laissés par un compte jamais
// confirmé redeviennent libres après 24 heures.
purgerNonConfirmes();

$ip = ipVisiteur();
$recentes = ligne(
    'SELECT COUNT(*) AS n FROM membre
      WHERE ip_inscription = :ip AND date_inscription > NOW() - INTERVAL 1 HOUR',
    ['ip' => $ip],
);
if ((int) ($recentes['n'] ?? 0) >= INSCRIPTIONS_PAR_HEURE) {
    refuser(429, 'Trop de comptes créés depuis cette connexion. Réessayez dans une heure.');
}

$existant = ligne('SELECT courriel_verifie FROM membre WHERE courriel = :c', ['c' => $courriel]);
if ($existant !== null) {
    refuser(409, (int) $existant['courriel_verifie'] === 1
        ? 'Cette adresse a déjà un compte. Connectez-vous, ou demandez un nouveau mot de passe.'
        : 'Cette adresse attend sa confirmation. Connectez-vous : un nouveau lien partira.');
}
// Un ancien pseudo reste réservé à son membre : personne ne le reprend.
if (ligne('SELECT 1 FROM membre WHERE pseudo = :p OR pseudo_precedent = :p2', ['p' => $pseudo, 'p2' => $pseudo]) !== null) {
    refuser(409, 'Ce pseudo est déjà pris.');
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

if (!envoyerLienVerification($membreId, $pseudo, $courriel)) {
    // Un compte que personne ne pourra confirmer est un compte poubelle.
    executer('DELETE FROM membre WHERE id = :id', ['id' => $membreId]);
    refuser(500, 'Le courriel de confirmation n\'est pas parti, et le compte n\'est pas créé. Réessayez dans un moment.');
}

repondreOk('Compte créé. Ouvrez le lien reçu par courriel dans les 24 heures pour l\'activer, indésirables compris.');

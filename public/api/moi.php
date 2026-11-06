<?php
/**
 * Son propre compte.
 *
 *   GET     lire son compte, adresse comprise : seul son titulaire la voit.
 *   PATCH   changer présentation, salle, lien Instagram.
 *   DELETE  effacer son compte, mot de passe exigé.
 *
 * Le mot de passe à l'effacement empêche qu'un ordinateur resté connecté,
 * dans une salle ou chez un ami, suffise à détruire le compte de quelqu'un.
 *
 * L'effacement part en cascade : sessions, performances, votes. Les messages
 * du chat sortent du chat et restent au journal d'un an, signés du pseudo
 * copié, comme le prévoit docs/comptes.md, section 8.
 */

declare(strict_types=1);

namespace SBDRE;

define('SBDRE_API', true);
require __DIR__ . '/lib/comptes.php';

$methode = ouvrirApi(['GET', 'PATCH', 'DELETE']);

if ($methode === 'GET') {
    $membre = exigerMembre();
    $compte = ligne(
        'SELECT pseudo, courriel, instagram, date_instagram, instagram_visible, bio, salle, date_inscription
           FROM membre WHERE id = :id',
        ['id' => $membre['id']],
    );
    // La date du prochain changement de nom Instagram, écrite pour l'œil.
    $prochain = prochainInstagram($compte['date_instagram'] ?? null, time());
    $compte['instagram_prochain'] = $prochain > 0 ? dateFr($prochain) : '';
    unset($compte['date_instagram']);

    repondreOk('', ['compte' => $compte]);
}

if ($methode === 'DELETE') {
    $membre = exigerMembre();
    $hash = ligne('SELECT mot_de_passe_hash FROM membre WHERE id = :id', ['id' => $membre['id']]);

    if (!password_verify(brut('mot_de_passe'), (string) ($hash['mot_de_passe_hash'] ?? EMPREINTE_FACTICE))) {
        refuser(403, 'Mot de passe incorrect. Le compte n\'est pas effacé.');
    }

    executer('DELETE FROM membre WHERE id = :id', ['id' => $membre['id']]);
    poserCookie('', time() - 3600);

    repondreOk('Votre compte est effacé.');
}

// PATCH : seuls les champs de la liste fermée sont lus, chacun validé.
$membre = exigerActif();
$recu = filtrerChamps(entree(), CHAMPS_PROFIL);
$valeurs = [];

if (array_key_exists('instagram_visible', $recu)) {
    $valeurs['instagram_visible'] = $recu['instagram_visible'] === true ? 1 : 0;
}

if (array_key_exists('bio', $recu)) {
    // Une ligne : les retours à la ligne et caractères de contrôle deviennent
    // des espaces, et la longueur se compte en caractères, pas en octets.
    $bio = is_string($recu['bio']) ? trim(preg_replace('/[\p{Cc}\s]+/u', ' ', $recu['bio']) ?? '') : '';
    if (mb_strlen($bio) > 160) {
        refuser(422, 'La présentation tient en 160 caractères.');
    }
    $valeurs['bio'] = $bio !== '' ? $bio : null;
}

if (array_key_exists('salle', $recu)) {
    // La forme seule se contrôle ici : l'identifiant d'une fiche de /salles/.
    // Une salle disparue du site s'ignore simplement à l'affichage.
    $salle = is_string($recu['salle']) ? $recu['salle'] : '';
    if ($salle !== '' && preg_match('/^[a-z0-9-]{1,80}\z/', $salle) !== 1) {
        refuser(422, 'Salle inconnue.');
    }
    $valeurs['salle'] = $salle !== '' ? $salle : null;
}

if (array_key_exists('instagram', $recu)) {
    $nom = is_string($recu['instagram']) ? normaliserInstagram($recu['instagram']) : '';
    if ($nom !== '' && !instagramValide($nom)) {
        refuser(422, 'Nom Instagram : lettres sans accent, chiffres, point ou tiret bas, 30 caractères au plus.');
    }

    $actuel = ligne('SELECT instagram, date_instagram FROM membre WHERE id = :id', ['id' => $membre['id']]);

    if ($nom !== (string) ($actuel['instagram'] ?? '')) {
        if ($nom === '') {
            // Retirer son nom reste possible à tout moment. L'horloge ne
            // bouge pas : sinon on contournerait le délai en deux gestes.
            $valeurs['instagram'] = null;
        } else {
            $prochain = prochainInstagram($actuel['date_instagram'] ?? null, time());
            if ($prochain > 0) {
                refuser(422, 'Le nom Instagram se change une fois tous les six mois. Prochain changement possible le ' . dateFr($prochain) . '.');
            }
            $valeurs['instagram'] = $nom;
            // Posée par le serveur, jamais lue dans la requête.
            $valeurs['date_instagram'] = date('Y-m-d H:i:s');
        }
    }
}

if ($valeurs === []) {
    repondreOk('Rien n\'a changé.');
}

// Les noms de colonnes viennent de CHAMPS_PROFIL et du serveur, jamais de la
// requête.
$affectations = implode(', ', array_map(static fn (string $c): string => "$c = :$c", array_keys($valeurs)));

try {
    executer("UPDATE membre SET $affectations WHERE id = :id", $valeurs + ['id' => $membre['id']]);
} catch (\PDOException $e) {
    if ($e->getCode() === '23000') {
        refuser(409, 'Ce nom Instagram est déjà relié à un autre profil. S\'il est le vôtre, écrivez à moderation@sbd.re.');
    }
    throw $e;
}

repondreOk('Profil enregistré.');

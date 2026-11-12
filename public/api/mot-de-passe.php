<?php
/**
 * Mot de passe oublié, en deux temps, tous deux en POST.
 *
 *   { courriel }               envoie un lien vers /compte/mot-de-passe/.
 *   { jeton, mot_de_passe }    pose le nouveau mot de passe.
 *
 * LA DEMANDE RÉPOND TOUJOURS LA MÊME PHRASE, que l'adresse ait un compte ou
 * non : ce formulaire ne sert pas à tester quelles adresses sont inscrites.
 *
 * LE LIEN MÈNE À UN FORMULAIRE, pas à une action. Un antivirus de messagerie
 * qui ouvre les liens d'avance consommerait le jeton, et le membre trouverait
 * un lien mort.
 *
 * Poser le nouveau mot de passe ferme TOUTES les sessions du compte, puis en
 * ouvre une pour ce navigateur. Celui qui aurait volé l'ancien est dehors. Le
 * lien prouvant l'accès à la boîte, l'adresse est confirmée au passage.
 */

declare(strict_types=1);

namespace SBDRE;

define('SBDRE_API', true);
require_once __DIR__ . '/lib/comptes.php';

ouvrirApi(['POST']);

$jeton = texte('jeton');

if ($jeton !== '') {
    $motDePasse = brut('mot_de_passe');
    // Le mot de passe se contrôle AVANT de consommer le jeton : un mot de
    // passe trop court ne doit pas griller le lien.
    $erreur = erreurMotDePasse($motDePasse);
    if ($erreur !== '') {
        refuser(422, $erreur);
    }

    $membreId = consommerJeton($jeton, 'reinitialisation');
    if ($membreId === null) {
        refuser(410, 'Ce lien a expiré ou a déjà servi. Demandez-en un nouveau.');
    }

    // Le sel change à chaque hachage : un compte non banni voit toujours sa
    // ligne modifiée. Zéro ligne veut dire banni entre la demande et le clic.
    $change = executer(
        'UPDATE membre SET mot_de_passe_hash = :h, courriel_verifie = 1 WHERE id = :id AND banni = 0',
        ['h' => password_hash($motDePasse, PASSWORD_DEFAULT), 'id' => $membreId],
    );
    if ($change !== 1) {
        refuser(403, 'Ce compte est suspendu.');
    }
    fermerToutesSessions($membreId);
    ouvrirSession($membreId);

    repondreOk('Mot de passe changé. Vous êtes connecté.');
}

$reponse = 'Si un compte existe pour cette adresse, un lien part. Il reste valable une heure.';

// Champ appât : un robot reçoit la réponse habituelle, et rien ne part.
if (texte('site') !== '') {
    repondreOk($reponse);
}
$courriel = normaliserCourriel(texte('courriel'));

if ($courriel === '') {
    refuser(422, 'Cette adresse électronique semble incorrecte.');
}

$membre = ligne('SELECT id, pseudo FROM membre WHERE courriel = :c AND banni = 0', ['c' => $courriel]);

if ($membre !== null && jetonsRecents((int) $membre['id'], 'reinitialisation') < LIENS_PAR_HEURE) {
    $lien = creerJeton((int) $membre['id'], 'reinitialisation', REINITIALISATION_MINUTES);

    // Un échec d'envoi se tait comme le reste : le dire révélerait que
    // l'adresse a un compte. Il va au journal du serveur.
    if (!envoyerCourriel($courriel, '[sbd.re] Nouveau mot de passe', implode("\n", [
        'Bonjour ' . $membre['pseudo'] . ',',
        '',
        'Pour choisir un nouveau mot de passe sur sbd.re, ouvrez ce lien dans l\'heure :',
        '',
        // Après le « # » : cette partie de l'adresse ne part jamais au
        // serveur, donc ne s'écrit pas dans ses journaux d'accès.
        'https://sbd.re/compte/mot-de-passe/#jeton=' . $lien,
        '',
        "Vous n'avez rien demandé ? Ignorez ce message : votre mot de passe ne change pas.",
    ]))) {
        error_log('SBD.re : lien de mot de passe non envoyé, membre ' . $membre['id']);
    }
}

repondreOk($reponse);

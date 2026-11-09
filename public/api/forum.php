<?php
/**
 * Les écritures du forum, en JSON. Une seule méthode, POST, et un champ
 * `action` :
 *
 *   sujet    { forum, titre, texte, confirme? }  ouvrir un sujet
 *   reponse  { sujet_id, texte, confirme? }      répondre
 *   suivre   { sujet_id, suivre }                suivre un sujet, ou plus
 *   modifier { message_id, texte, confirme? }    corriger son message, 24 heures
 *   creer_forum, renommer_forum, epingler, verrouiller : les forums des membres
 *
 * Membre confirmé et non banni seulement (exigerActif). Un mot bloquant
 * refuse le message ; un mot du dopage le fait passer sous avertissement,
 * après une confirmation (409, puis `confirme: true`).
 */

declare(strict_types=1);

namespace SBDRE;

define('SBDRE_API', true);
require __DIR__ . '/lib/comptes.php';
require __DIR__ . '/lib/forum.php';

ouvrirApi(['POST']);
$membre = exigerActif();
$membreId = (int) $membre['id'];

/** Refuse un texte bloqué, demande confirmation pour un texte sensible. Rend « sensible ». */
function controlerTexte(string ...$textes): bool
{
    $analyses = array_map(analyserTexte(...), $textes);
    if (in_array('bloque', $analyses, true)) {
        refuser(422, 'Ce message contient un terme refusé par la charte.');
    }
    $sensible = in_array('sensible', $analyses, true);
    if ($sensible && (entree()['confirme'] ?? false) !== true) {
        refuser(409, PREVENIR_DOPAGE);
    }

    return $sensible;
}

function dansUneTransaction(callable $ecrire): mixed
{
    bdd()->beginTransaction();
    try {
        $resultat = $ecrire();
        bdd()->commit();

        return $resultat;
    } catch (\Throwable $e) {
        bdd()->rollBack();
        throw $e;
    }
}

$texte = str_replace("\r\n", "\n", texte('texte'));
$sujetId = entree()['sujet_id'] ?? null;

switch (texte('action')) {
    case 'sujet':
        $forum = ligne('SELECT id, adresse, etat FROM forum WHERE adresse = :a', ['a' => texte('forum')]);
        if ($forum === null || $forum['etat'] === 'masque') {
            refuser(404, 'Ce forum n\'existe pas.');
        }
        if ($forum['etat'] !== 'ouvert') {
            refuser(403, 'Ce forum est fermé à l\'écriture.');
        }
        $titre = texte('titre');
        if (!titreSujetValide($titre)) {
            refuser(422, 'Le titre tient en ' . TITRE_SUJET_MIN . ' à ' . TITRE_SUJET_MAX . ' caractères.');
        }
        if (!texteForumValide($texte)) {
            refuser(422, 'Le message tient en 1 à ' . MESSAGE_FORUM_MAX . ' caractères.');
        }
        $sensible = controlerTexte($titre, $texte);
        if (forumTropRapide($membreId)) {
            refuser(429, 'Doucement : un message toutes les ' . FREIN_FORUM_SECONDES . ' secondes.');
        }
        if (sujetsDuJour($membreId) >= SUJETS_PAR_JOUR) {
            refuser(429, SUJETS_PAR_JOUR . ' sujets par jour au plus. Répondez dans un sujet existant, ou revenez demain.');
        }
        $id = dansUneTransaction(static fn (): int => creerSujet($membre, (int) $forum['id'], $titre, $texte, $sensible, ipVisiteur()));
        repondreOk('Sujet publié.', ['lien' => lienSujet((string) $forum['adresse'], $id)]);

    case 'reponse':
        if (!is_int($sujetId) || $sujetId <= 0) {
            refuser(422, 'Sujet illisible.');
        }
        $sujet = ligne(
            'SELECT s.id, s.titre, s.reponses, s.verrouille, f.adresse, f.etat
               FROM sujet s JOIN forum f ON f.id = s.forum_id WHERE s.id = :s',
            ['s' => $sujetId],
        );
        if ($sujet === null || $sujet['etat'] === 'masque') {
            refuser(404, 'Ce sujet n\'existe plus.');
        }
        if ((int) $sujet['verrouille'] === 1 || $sujet['etat'] !== 'ouvert') {
            refuser(403, 'Ce sujet est fermé aux réponses.');
        }
        if (!texteForumValide($texte)) {
            refuser(422, 'Le message tient en 1 à ' . MESSAGE_FORUM_MAX . ' caractères.');
        }
        $sensible = controlerTexte($texte);
        if (forumTropRapide($membreId)) {
            refuser(429, 'Doucement : un message toutes les ' . FREIN_FORUM_SECONDES . ' secondes.');
        }
        $lien = dansUneTransaction(static fn (): string => ajouterReponse($membre, $sujet, (string) $sujet['adresse'], $texte, $sensible, ipVisiteur()));
        repondreOk('Réponse publiée.', ['lien' => $lien]);

    case 'modifier':
        $messageId = entree()['message_id'] ?? null;
        if (!is_int($messageId) || $messageId <= 0) {
            refuser(422, 'Message illisible.');
        }
        $message = ligne(
            'SELECT mf.id, mf.membre_id, mf.masque_par, UNIX_TIMESTAMP(mf.date_creation) AS date_ts, s.verrouille, f.etat
               FROM message_forum mf JOIN sujet s ON s.id = mf.sujet_id JOIN forum f ON f.id = s.forum_id
              WHERE mf.id = :id',
            ['id' => $messageId],
        );
        if ($message === null || $message['etat'] === 'masque') {
            refuser(404, 'Ce message n\'existe plus.');
        }
        if ((int) $message['verrouille'] === 1 || $message['etat'] !== 'ouvert') {
            refuser(403, 'Ce sujet est fermé : ses messages ne se modifient plus.');
        }
        if (!peutModifierMessage($membre, $message, time())) {
            refuser(403, 'Un message se modifie par son auteur, dans les ' . MODIFICATION_HEURES . ' heures qui suivent sa publication.');
        }
        if (!texteForumValide($texte)) {
            refuser(422, 'Le message tient en 1 à ' . MESSAGE_FORUM_MAX . ' caractères.');
        }
        modifierMessage($messageId, $texte, controlerTexte($texte));
        repondreOk('Message modifié.');

    case 'suivre':
        $suivre = entree()['suivre'] ?? null;
        if (!is_int($sujetId) || $sujetId <= 0 || !is_bool($suivre)) {
            refuser(422, 'Demande illisible.');
        }
        if (ligne('SELECT 1 AS n FROM sujet WHERE id = :s', ['s' => $sujetId]) === null) {
            refuser(404, 'Ce sujet n\'existe plus.');
        }
        suivreSujet($membreId, $sujetId, $suivre);
        repondreOk($suivre ? 'Vous suivez ce sujet.' : 'Vous ne suivez plus ce sujet.');

    case 'creer_forum':
        // Les forums des membres (docs/forum.md, section 6).
        if (!peutCreerForum($membre, time())) {
            refuser(403, 'Un forum se crée ' . ANCIENNETE_FORUM_JOURS . ' jours après l\'inscription.');
        }
        $titre = texte('titre');
        $description = texte('description');
        $erreur = erreurForum($titre, $description);
        if ($erreur !== '') {
            refuser(422, $erreur);
        }
        if (forumsCreesCeMois($membreId) >= FORUMS_PAR_MOIS) {
            refuser(429, 'Un forum par mois au plus. Le prochain pourra se créer dans quelques jours.');
        }
        $adresse = creerForumMembre($membre, $titre, $description);
        repondreOk('Forum créé.', ['lien' => '/forum/' . $adresse . '/']);

    case 'renommer_forum':
        $forum = ligne('SELECT id, createur_id, etat FROM forum WHERE adresse = :a', ['a' => texte('forum')]);
        if ($forum === null || $forum['etat'] === 'masque') {
            refuser(404, 'Ce forum n\'existe pas.');
        }
        if (!peutGererForum($membre, $forum)) {
            refuser(403, 'Seuls le créateur du forum et l\'admin le peuvent.');
        }
        $titre = texte('titre');
        $description = texte('description');
        $erreur = erreurForum($titre, $description);
        if ($erreur !== '') {
            refuser(422, $erreur);
        }
        renommerForum((int) $forum['id'], $titre, $description);
        repondreOk('Forum renommé. Son adresse ne change pas.');

    case 'epingler':
    case 'verrouiller':
        $valeur = entree()['valeur'] ?? null;
        if (!is_int($sujetId) || $sujetId <= 0 || !is_bool($valeur)) {
            refuser(422, 'Demande illisible.');
        }
        $cible = ligne(
            'SELECT s.id, f.createur_id FROM sujet s JOIN forum f ON f.id = s.forum_id WHERE s.id = :s',
            ['s' => $sujetId],
        );
        if ($cible === null) {
            refuser(404, 'Ce sujet n\'existe plus.');
        }
        if (!peutGererForum($membre, $cible)) {
            refuser(403, 'Seuls le créateur du forum et l\'admin le peuvent.');
        }
        $epingler = texte('action') === 'epingler';
        marquerSujet($sujetId, $epingler ? 'epingle' : 'verrouille', $valeur);
        repondreOk(match (true) {
            $epingler && $valeur => 'Sujet épinglé.',
            $epingler => 'Sujet désépinglé.',
            $valeur => 'Sujet fermé.',
            default => 'Sujet rouvert.',
        });

    default:
        refuser(422, 'Action inconnue.');
}

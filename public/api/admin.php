<?php
/**
 * Les gestes de l'admin (docs/comptes.md, phase 8 ; docs/forum.md, étape 8).
 *
 *   GET   la file des signalements, les forums des membres, les comptes bannis,
 *         les fiches d'athlètes réclamées
 *   POST  { performance_id, verifier }    poser ou retirer le badge SBD.re
 *   POST  { action: 'masquer', type, id } masquer un message du chat ou du forum
 *         { action: 'retirer_performance', id }
 *         { action: 'bannir', membre_id }
 *         { action: 'debannir', membre_id }    rétablir un compte banni par erreur
 *         { action: 'relier_fiche', id }       relier un membre à la fiche qu'il réclame
 *         { action: 'refuser_fiche', id }
 *         { action: 'retirer_avatar', membre_id }
 *         { action: 'forum', forum_id, etat }  ouvert, verrouille, archive, masque
 *         { action: 'classer', type, id }      sans suite
 *
 * Masquer, retirer et bannir classent aussi les signalements de la cible,
 * et préviennent l'auteur. Un compte non admin reçoit 404 (exigerAdmin) : la
 * page ne confirme pas son existence à qui la cherche.
 *
 * Devenir admin se fait à la main, en base :
 *   UPDATE membre SET role = 'admin' WHERE pseudo = '…';
 */

declare(strict_types=1);

namespace SBDRE;

define('SBDRE_API', true);
require __DIR__ . '/lib/comptes.php';
require __DIR__ . '/lib/performances.php';
require __DIR__ . '/lib/notifications.php';
require __DIR__ . '/lib/signalements.php';
require __DIR__ . '/lib/avatar.php';
require __DIR__ . '/lib/revendications.php';

$methode = ouvrirApi(['GET', 'POST']);
$admin = exigerAdmin();

if ($methode === 'GET') {
    repondreOk('', [
        'file'           => fileModeration(),
        'forums'         => forumsDesMembres(),
        'bannis'         => membresBannis(),
        'revendications' => revendicationsEnAttente(),
    ]);
}

/** Un entier strictement positif lu dans la requête, ou le refus. */
function identifiant(string $cle): int
{
    $valeur = entree()[$cle] ?? null;
    if (!is_int($valeur) || $valeur <= 0) {
        refuser(422, 'Demande illisible.');
    }

    return $valeur;
}

const LIEN_CHARTE = '/communaute/charte/';

switch (texte('action')) {
    case '':
        // Le badge « Vérifiée par SBD.re », phase 6, appelé par /compte/a-verifier/.
        $id = identifiant('performance_id');
        $verifier = entree()['verifier'] ?? null;
        if (!is_bool($verifier)) {
            refuser(422, 'Demande illisible.');
        }
        // admin_id garde qui a vérifié : une vérification se retrace.
        $touchees = $verifier
            ? executer('UPDATE performance SET admin_id = :admin, date_verif_admin = NOW() WHERE id = :id', ['admin' => $admin['id'], 'id' => $id])
            : executer('UPDATE performance SET admin_id = NULL, date_verif_admin = NULL WHERE id = :id', ['id' => $id]);
        if ($touchees === 0 && ligne('SELECT 1 AS n FROM performance WHERE id = :id', ['id' => $id]) === null) {
            refuser(404, 'Cette performance n\'existe plus.');
        }
        if ($verifier && $touchees > 0) {
            $perf = ligne('SELECT membre_id, mouvement, charge_kg FROM performance WHERE id = :id', ['id' => $id]);
            notifier((int) $perf['membre_id'], 'badge', 'Votre performance au ' . mb_strtolower(MOUVEMENTS[$perf['mouvement']])
                . ', ' . kgFr((float) $perf['charge_kg']) . ', est vérifiée par SBD.re.', '/compte/');
        }
        repondreOk($verifier ? 'Badge « Vérifiée par SBD.re » posé.' : 'Badge retiré.');

    case 'masquer':
        $type = texte('type');
        if (!in_array($type, ['message', 'message_forum'], true)) {
            refuser(422, 'Demande illisible.');
        }
        $id = identifiant('id');
        $auteur = masquerMessage($type, $id, (int) $admin['id']);
        classer($type, $id);
        if ($auteur !== null) {
            notifier($auteur, 'moderation', 'Un de vos messages a été masqué par la modération.', LIEN_CHARTE);
        }
        repondreOk('Message masqué.');

    case 'retirer_performance':
        $id = identifiant('id');
        $auteur = retirerPerformance($id);
        classer('performance', $id);
        if ($auteur !== null) {
            notifier($auteur, 'moderation', 'Une de vos performances a été retirée par la modération.', LIEN_CHARTE);
        }
        repondreOk('Performance retirée.');

    case 'bannir':
        $id = identifiant('membre_id');
        if (!bannir($id)) {
            refuser(422, 'Ce compte ne peut pas être banni : il n\'existe pas, ou c\'est un admin.');
        }
        classer('membre', $id);
        repondreOk('Membre banni. Ses sessions sont fermées.');

    case 'debannir':
        $id = identifiant('membre_id');
        if (!debannir($id)) {
            refuser(422, 'Ce compte n\'est pas banni, ou il n\'existe plus.');
        }
        notifier($id, 'moderation', 'Votre compte est rétabli. La charte s\'applique comme avant.', LIEN_CHARTE);
        repondreOk('Compte rétabli. Le membre peut se reconnecter.');

    case 'relier_fiche':
        $relie = accepterRevendication(identifiant('id'), (int) $admin['id']);
        if ($relie === null) {
            refuser(409, 'Cette demande ne peut plus aboutir : la fiche est déjà reliée, ou le membre en a une.');
        }
        notifier($relie['membre_id'], 'moderation', 'Votre compte est relié à votre fiche d\'athlète.', '/athlete/' . $relie['slug'] . '/');
        repondreOk('Fiche reliée. Le profil et la fiche se renvoient l\'un à l\'autre.');

    case 'refuser_fiche':
        $membreId = refuserRevendication(identifiant('id'), (int) $admin['id']);
        if ($membreId === null) {
            refuser(404, 'Cette demande n\'attend plus.');
        }
        notifier($membreId, 'moderation', 'Votre demande de fiche d\'athlète est refusée. Écrivez à l\'admin pour en savoir plus.', '/compte/');
        repondreOk('Demande refusée. Le membre en est prévenu.');

    case 'retirer_avatar':
        $id = identifiant('membre_id');
        supprimerAvatar($id);
        notifier($id, 'moderation', 'Votre avatar a été retiré par la modération.', LIEN_CHARTE);
        repondreOk('Avatar retiré.');

    case 'forum':
        $id = identifiant('forum_id');
        $etat = texte('etat');
        if (!in_array($etat, ETATS_FORUM, true)) {
            refuser(422, 'État inconnu.');
        }
        changerEtatForum($id, $etat);
        if ($etat !== 'ouvert') {
            classer('forum', $id);
        }
        repondreOk('Forum passé à l\'état « ' . $etat . ' ».');

    case 'classer':
        $type = texte('type');
        if (!in_array($type, TYPES_SIGNALEMENT, true)) {
            refuser(422, 'Demande illisible.');
        }
        classer($type, identifiant('id'));
        repondreOk('Signalements classés sans suite.');

    default:
        refuser(422, 'Action inconnue.');
}

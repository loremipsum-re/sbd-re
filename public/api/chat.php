<?php
/**
 * Le chat (docs/forum.md, section 5).
 *
 *   GET   ?canal=general&depuis=N  les messages après N, qui est en ligne,
 *         et l'intervalle de la prochaine lecture. Ouvert à tous : un
 *         visiteur ne reçoit que ce qui arrive (lib/chat.php, lireChat).
 *   POST  { canal, texte, confirme? }  écrire, membre confirmé seulement.
 *         Un @pseudo cité reçoit une notification (notifierMentionsChat).
 */

declare(strict_types=1);

namespace SBDRE;

define('SBDRE_API', true);
require_once __DIR__ . '/lib/comptes.php';
require_once __DIR__ . '/lib/chat.php';
require_once __DIR__ . '/lib/moderation.php';
require_once __DIR__ . '/lib/forum.php';

$methode = ouvrirApi(['GET', 'POST']);

if ($methode === 'GET') {
    $canal = (string) ($_GET['canal'] ?? 'general');
    if (!isset(SALONS[$canal])) {
        refuser(404, 'Salon inconnu.');
    }
    $depuis = filter_var($_GET['depuis'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    $membre = membreCourant();
    if ($membre !== null) {
        marquerActivite((int) $membre['id']);
    }

    repondreOk('', lireChat($canal, $depuis === false ? 0 : $depuis, $membre !== null) + [
        'en_ligne'      => enLigne(),
        'connecte'      => $membre !== null,
        'pseudo'        => (string) ($membre['pseudo'] ?? ''),
        'intervalle'    => $membre !== null ? CHAT_INTERVALLE_MEMBRE : CHAT_INTERVALLE_VISITEUR,
        'avertissement' => AVERTISSEMENT_DOPAGE,
    ]);
}

$membre = exigerActif();
$canal = texte('canal');
if (!isset(SALONS[$canal])) {
    refuser(404, 'Salon inconnu.');
}
$texte = texte('texte');
if (!texteChatValide($texte)) {
    refuser(422, 'Un message tient en ' . CHAT_MAX . ' caractères, sur une ligne.');
}
$analyse = analyserTexte($texte);
if ($analyse === 'bloque') {
    refuser(422, 'Ce message contient un terme refusé par la charte.');
}
if ($analyse === 'sensible' && (entree()['confirme'] ?? false) !== true) {
    refuser(409, PREVENIR_DOPAGE);
}
if (chatTropRapide((int) $membre['id'])) {
    refuser(429, 'Doucement : un message toutes les ' . FREIN_CHAT_SECONDES . ' secondes.');
}

$id = ecrireChat($membre, $canal, $texte, $analyse === 'sensible', ipVisiteur());
notifierMentionsChat(mentionsDe($texte), (int) $membre['id'], $membre['pseudo'] . ' vous cite dans le chat, salon ' . SALONS[$canal] . '.', $canal);
repondreOk('', ['id' => $id]);

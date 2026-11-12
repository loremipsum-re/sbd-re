<?php
/**
 * La vérification par les membres.
 *
 *   GET   le fil « À vérifier » : les cinquante performances les plus
 *         récentes des autres membres, pas encore vérifiées, avec leurs avis
 *         et le sien. Dit aussi si le membre peut voter, et à partir de quand.
 *   POST  { performance_id, ok } : donner ou changer son avis.
 *
 * Les règles de qui vote vivent dans autorisation.php, peutVoter() : membre
 * actif, inscrit depuis 30 jours, jamais sur sa propre barre. Le badge se
 * calcule à la lecture, badgeDe() : rien n'est stocké qui périmerait.
 */

declare(strict_types=1);

namespace SBDRE;

define('SBDRE_API', true);
require_once __DIR__ . '/lib/comptes.php';
require_once __DIR__ . '/lib/performances.php';
require_once __DIR__ . '/lib/notifications.php';

$methode = ouvrirApi(['GET', 'POST']);
$membre = exigerActif();
$moi = (int) $membre['id'];

if ($methode === 'GET') {
    $fil = performancesAvecVotes(
        'p.membre_id <> :moi AND p.date_verif_admin IS NULL AND m.banni = 0 AND m.courriel_verifie = 1',
        ['moi' => $moi],
        'ORDER BY p.date_creation DESC, p.id DESC LIMIT 50',
    );
    // Celles qui ont déjà le badge communauté n'attendent plus personne.
    $fil = array_values(array_filter($fil, static fn (array $p): bool => badgeDe($p) === ''));

    $mesVotes = [];
    if ($fil !== []) {
        $ids = implode(',', array_map(static fn (array $p): int => (int) $p['id'], $fil));
        // Des entiers lus en base, jamais la requête : rien à lier.
        foreach (lignes("SELECT performance_id, ok FROM vote WHERE membre_id = :moi AND performance_id IN ($ids)", ['moi' => $moi]) as $v) {
            $mesVotes[(int) $v['performance_id']] = (int) $v['ok'];
        }
    }
    foreach ($fil as &$p) {
        $p['mon_vote'] = $mesVotes[(int) $p['id']] ?? null;
        unset($p['membre_id']);
    }
    unset($p);

    $inscription = strtotime((string) $membre['date_inscription']) ?: time();
    $possible = $inscription + ANCIENNETE_VOTE_JOURS * 86400;

    repondreOk('', [
        'fil'          => $fil,
        'peut_voter'   => $possible <= time(),
        'vote_possible_le' => $possible > time() ? dateFr($possible) : '',
        // Le badge SBD.re : le juge, et l'admin (lib/autorisation.php).
        'juge'         => estJuge($membre),
        'seuil'        => ['votes' => VOTES_MIN, 'pourcent' => FAVORABLES_POURCENT, 'jours' => ANCIENNETE_VOTE_JOURS],
    ]);
}

// POST : donner ou changer son avis.
$id = entree()['performance_id'] ?? null;
$ok = entree()['ok'] ?? null;
if (!is_int($id) || $id <= 0 || !is_bool($ok)) {
    refuser(422, 'Avis illisible.');
}

$performance = ligne('SELECT id, membre_id FROM performance WHERE id = :id', ['id' => $id]);
if ($performance === null) {
    refuser(404, 'Cette performance n\'existe plus.');
}
if (estAuteur($performance, $membre)) {
    refuser(403, 'On ne vote pas sur sa propre performance.');
}
if (!peutVoter($performance, $membre, time())) {
    refuser(403, 'Le vote s\'ouvre ' . ANCIENNETE_VOTE_JOURS . ' jours après l\'inscription.');
}

// Le badge avant et après l'avis : celui qui le fait tomber prévient l'auteur.
$badge = static fn (): string => badgeDe(performancesAvecVotes('p.id = :id', ['id' => $id])[0] ?? []);
$avant = $badge();
executer(SQL_VOTER, ['p' => $id, 'm' => $moi, 'ok' => $ok ? 1 : 0]);
if ($avant === '' && $badge() === 'communaute') {
    // La date du badge, pour le fil : posée une fois, à l'avis qui le fait tomber.
    executer('UPDATE performance SET date_verif_communaute = NOW() WHERE id = :id AND date_verif_communaute IS NULL', ['id' => $id]);
    $perf = ligne('SELECT membre_id, mouvement, charge_kg FROM performance WHERE id = :id', ['id' => $id]);
    notifier((int) $perf['membre_id'], 'badge', 'Votre performance au ' . mb_strtolower(MOUVEMENTS[$perf['mouvement']])
        . ', ' . kgFr((float) $perf['charge_kg']) . ', est vérifiée par la communauté.', '/compte/');
}

repondreOk($ok ? 'Avis enregistré : valide.' : 'Avis enregistré : ne valide pas.');

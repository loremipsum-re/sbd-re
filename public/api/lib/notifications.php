<?php
/**
 * Les notifications sur le site (docs/forum.md, section 4) : une réponse dans
 * un sujet suivi, une mention @pseudo, un badge posé sur une performance.
 * La nature « moderation » attend la phase 8.
 *
 * Aucun courriel par notification : le récapitulatif de quinzaine, coupé par
 * défaut, les reprend (lib/recapitulatif.php). Les fonctions écrivent sans ouvrir
 * de transaction : l'appelant l'ouvre, et db/verifier.php les éprouve dans
 * la sienne.
 */

declare(strict_types=1);

namespace SBDRE;

if (!defined('SBDRE_API')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/bdd.php';
require_once __DIR__ . '/avatar.php';
require_once __DIR__ . '/chat.php';

const NOTIFICATIONS_LISTE = 50;
const NOTIFICATIONS_GARDEES_JOURS = 90;

/** Le texte tient dans la colonne, 200 caractères. */
function texteNotification(string $texte): string
{
    return mb_substr($texte, 0, 200);
}

function notifier(int $membreId, string $nature, string $texte, string $lien): void
{
    executer(
        'INSERT INTO notification (membre_id, nature, texte, lien) VALUES (:m, :n, :t, :l)',
        ['m' => $membreId, 'n' => $nature, 't' => texteNotification($texte), 'l' => $lien],
    );
}

/** Chaque abonné du sujet, sauf l'auteur de la réponse. Rend le nombre prévenu. */
function notifierAbonnes(int $sujetId, int $auteurId, string $texte, string $lien): int
{
    return executer(
        "INSERT INTO notification (membre_id, nature, texte, lien)
         SELECT a.membre_id, 'reponse', :t, :l
           FROM abonnement a
           JOIN membre m ON m.id = a.membre_id
          WHERE a.sujet_id = :s AND a.membre_id <> :auteur AND m.banni = 0",
        ['t' => texteNotification($texte), 'l' => $lien, 's' => $sujetId, 'auteur' => $auteurId],
    );
}

/**
 * Les membres cités par @pseudo, hors auteur, et hors abonnés du sujet que la
 * réponse prévient déjà. Rend le nombre prévenu.
 *
 * @param list<string> $pseudos
 */
function notifierMentions(array $pseudos, int $sujetId, int $auteurId, string $texte, string $lien): int
{
    if ($pseudos === []) {
        return 0;
    }
    $params = ['t' => texteNotification($texte), 'l' => $lien, 'auteur' => $auteurId, 's' => $sujetId];
    $noms = [];
    foreach (array_values($pseudos) as $i => $pseudo) {
        $params["p$i"] = $pseudo;
        $noms[] = ":p$i";
    }

    return executer(
        "INSERT INTO notification (membre_id, nature, texte, lien)
         SELECT m.id, 'mention', :t, :l
           FROM membre m
          WHERE m.pseudo IN (" . implode(', ', $noms) . ")
            AND m.id <> :auteur AND m.banni = 0 AND m.courriel_verifie = 1
            AND NOT EXISTS (SELECT 1 FROM abonnement a WHERE a.sujet_id = :s AND a.membre_id = m.id)",
        $params,
    );
}

/**
 * Ce que l'en-tête montre d'un membre connecté, lu avec la cloche une fois
 * par minute : son pseudo et son avatar pour le menu du compte, le nombre de
 * signalements en attente pour un admin, et le numéro du dernier message du
 * chat écrit par un autre, pour le point du bouton « Chat ».
 *
 * @param array<string, mixed> $membre id, pseudo, role
 * @return array{pseudo: string, avatar: string, admin: bool, signalements: int, chat_dernier: int}
 */
function etatEntete(array $membre): array
{
    $id = (int) $membre['id'];
    $avatar = ligne('SELECT UNIX_TIMESTAMP(date_maj) AS v FROM avatar WHERE membre_id = :m', ['m' => $id]);
    $admin = estAdmin($membre);

    return [
        'pseudo'       => (string) $membre['pseudo'],
        'avatar'       => $avatar !== null ? adresseAvatar((string) $membre['pseudo'], (int) $avatar['v']) : '',
        'admin'        => $admin,
        // La file de l'admin : les cibles signalées, et les fiches réclamées.
        'signalements' => $admin
            ? (int) (ligne('SELECT COUNT(*) AS n FROM (SELECT 1 FROM signalement WHERE traite = 0 GROUP BY type, cible_id) AS file')['n'] ?? 0)
                + (int) (ligne("SELECT COUNT(*) AS n FROM revendication WHERE etat = 'attente'")['n'] ?? 0)
            : 0,
        // Les salons ouverts seulement : un message d'un salon retiré, que
        // personne ne lit plus, laisserait le point allumé sept jours.
        'chat_dernier' => (int) (ligne(
            "SELECT MAX(id) AS n FROM message
              WHERE masque_par IS NULL AND date_envoi > NOW() - INTERVAL 7 DAY
                AND canal IN ('" . implode("', '", array_keys(SALONS)) . "')
                AND (membre_id IS NULL OR membre_id <> :m)",
            ['m' => $id],
        )['n'] ?? 0),
    ];
}

function compterNonLues(int $membreId): int
{
    return (int) (ligne('SELECT COUNT(*) AS n FROM notification WHERE membre_id = :m AND lue = 0', ['m' => $membreId])['n'] ?? 0);
}

/** @return list<array<string, mixed>> les plus récentes d'abord */
function listerNotifications(int $membreId): array
{
    return array_map(
        static fn (array $n): array => [
            'id'     => (int) $n['id'],
            'nature' => $n['nature'],
            'texte'  => $n['texte'],
            'lien'   => $n['lien'],
            'lue'    => (int) $n['lue'] === 1,
            'date'   => (int) $n['date_ts'],
        ],
        lignes(
            'SELECT id, nature, texte, lien, lue, UNIX_TIMESTAMP(date_creation) AS date_ts
               FROM notification WHERE membre_id = :m ORDER BY id DESC LIMIT ' . NOTIFICATIONS_LISTE,
            ['m' => $membreId],
        ),
    );
}

/** Tout marquer comme lu, et effacer en passant les lues de plus de 90 jours. */
function marquerLues(int $membreId): void
{
    executer('UPDATE notification SET lue = 1 WHERE membre_id = :m AND lue = 0', ['m' => $membreId]);
    executer(
        'DELETE FROM notification WHERE membre_id = :m AND lue = 1
            AND date_creation < NOW() - INTERVAL ' . NOTIFICATIONS_GARDEES_JOURS . ' DAY LIMIT 100',
        ['m' => $membreId],
    );
}

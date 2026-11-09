<?php
/**
 * Le chat revu (docs/forum.md, section 5, qui reprend la phase 7 de
 * docs/comptes.md) : cinq salons, lisibles de tous.
 *
 *   Membre confirmé : lit les sept derniers jours, écrit, apparaît en ligne.
 *   Visiteur        : voit arriver les nouveaux messages, sans historique.
 *                     Sa première lecture ne rend que le numéro du dernier
 *                     message, les suivantes ce qui est arrivé depuis, et
 *                     jamais un message de plus de cinq minutes : demander
 *                     « tout depuis le numéro 1 » ne rend pas l'historique.
 *
 * Les fonctions écrivent sans transaction, pour que db/verifier.php les
 * éprouve dans la sienne.
 */

declare(strict_types=1);

namespace SBDRE;

if (!defined('SBDRE_API')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/bdd.php';
require_once __DIR__ . '/notifications.php';

/** Un salon donne un thème, pas une règle : la charte s'applique partout. */
const SALONS = [
    'general'          => 'Général',
    'squat'            => 'Squat',
    'developpe-couche' => 'Développé couché',
    'souleve-de-terre' => 'Soulevé de terre',
    'competitions'     => 'Compétitions',
];

const CHAT_MAX = 500;
const FREIN_CHAT_SECONDES = 3;
const CHAT_JOURS = 7;
const CHAT_VISITEUR_MINUTES = 5;
const EN_LIGNE_MINUTES = 2;
/** Secondes entre deux lectures : un mutualisé tient mal des visiteurs pressés. */
const CHAT_INTERVALLE_MEMBRE = 5;
const CHAT_INTERVALLE_VISITEUR = 15;

/** 1 à 500 caractères, sur une ligne, sans caractère de contrôle. */
function texteChatValide(string $texte): bool
{
    $n = mb_strlen($texte);

    return $n >= 1 && $n <= CHAT_MAX && preg_match('/[\x00-\x1F\x7F]/', $texte) !== 1;
}

/**
 * Les messages d'un salon après `$depuis`, et le numéro du dernier.
 *
 * @return array{messages: list<array<string, mixed>>, dernier: int}
 */
function lireChat(string $canal, int $depuis, bool $membre): array
{
    $champs = 'SELECT id, pseudo, texte, sensible, UNIX_TIMESTAMP(date_envoi) AS date_ts
                 FROM message WHERE canal = :c AND masque_par IS NULL';
    $params = ['c' => $canal];
    if ($depuis > 0) {
        $params['d'] = $depuis;
    }

    $lignes = match (true) {
        $membre && $depuis > 0 => lignes(
            "$champs AND id > :d AND date_envoi > NOW() - INTERVAL " . CHAT_JOURS . ' DAY ORDER BY id LIMIT 100',
            $params,
        ),
        $membre => array_reverse(lignes(
            "$champs AND date_envoi > NOW() - INTERVAL " . CHAT_JOURS . ' DAY ORDER BY id DESC LIMIT 100',
            $params,
        )),
        $depuis > 0 => lignes(
            "$champs AND id > :d AND date_envoi > NOW() - INTERVAL " . CHAT_VISITEUR_MINUTES . ' MINUTE ORDER BY id LIMIT 50',
            $params,
        ),
        default => [],
    };

    $dernier = $lignes !== []
        ? (int) end($lignes)['id']
        : ($depuis > 0 ? $depuis : (int) (ligne('SELECT MAX(id) AS n FROM message WHERE canal = :c', ['c' => $canal])['n'] ?? 0));

    return [
        'messages' => array_map(static fn (array $m): array => [
            'id'       => (int) $m['id'],
            'pseudo'   => $m['pseudo'],
            'texte'    => $m['texte'],
            'sensible' => (int) $m['sensible'] === 1,
            'date'     => (int) $m['date_ts'],
        ], $lignes),
        'dernier' => $dernier,
    ];
}

/** Les pseudos actifs dans les deux dernières minutes, visibles de tous. */
function enLigne(): array
{
    return array_column(lignes(
        'SELECT pseudo FROM membre
          WHERE derniere_activite > NOW() - INTERVAL ' . EN_LIGNE_MINUTES . ' MINUTE
            AND banni = 0 AND courriel_verifie = 1
          ORDER BY pseudo LIMIT 50',
    ), 'pseudo');
}

/** Une écriture par minute au plus : chaque lecture du chat passe ici. */
function marquerActivite(int $membreId): void
{
    executer(
        'UPDATE membre SET derniere_activite = NOW()
          WHERE id = :m AND (derniere_activite IS NULL OR derniere_activite < NOW() - INTERVAL 60 SECOND)',
        ['m' => $membreId],
    );
}

function chatTropRapide(int $membreId): bool
{
    return ligne(
        'SELECT 1 AS n FROM message WHERE membre_id = :m
            AND date_envoi > NOW() - INTERVAL ' . FREIN_CHAT_SECONDES . ' SECOND LIMIT 1',
        ['m' => $membreId],
    ) !== null;
}

/**
 * Les membres cités par @pseudo dans le chat, cinq au plus, hors auteur.
 * Une seule notification non lue par salon et par membre : être cité dix
 * fois de suite ne noie pas la cloche. Le lien ouvre le chat sur le salon.
 *
 * @param list<string> $pseudos
 */
function notifierMentionsChat(array $pseudos, int $auteurId, string $texte, string $canal): int
{
    if ($pseudos === [] || !isset(SALONS[$canal])) {
        return 0;
    }
    $params = ['t' => texteNotification($texte), 'l' => '/communaute/#chat=' . $canal, 'l2' => '/communaute/#chat=' . $canal, 'auteur' => $auteurId];
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
            AND NOT EXISTS (SELECT 1 FROM notification n WHERE n.membre_id = m.id AND n.lue = 0 AND n.lien = :l2)",
        $params,
    );
}

/**
 * Écrit un message, et purge en passant cent lignes de plus d'un an : la
 * conservation d'un an se tient sans tâche planifiée (comptes.md, 6.4).
 */
function ecrireChat(array $membre, string $canal, string $texte, bool $sensible, string $ip): int
{
    executer('DELETE FROM message WHERE date_envoi < NOW() - INTERVAL 1 YEAR LIMIT 100');
    oublierAdressesIp();
    executer(
        'INSERT INTO message (canal, membre_id, pseudo, texte, ip, sensible) VALUES (:c, :m, :p, :t, :ip, :s)',
        ['c' => $canal, 'm' => $membre['id'], 'p' => $membre['pseudo'], 't' => $texte, 'ip' => $ip, 's' => $sensible ? 1 : 0],
    );
    $id = (int) bdd()->lastInsertId();
    executer('UPDATE membre SET derniere_activite = NOW() WHERE id = :m', ['m' => $membre['id']]);

    return $id;
}

<?php
/**
 * Les signalements et les gestes de l'admin (docs/comptes.md, section 6.5 ;
 * docs/forum.md, étape 8).
 *
 *   signalement ──> file de l'admin ──> masquer, retirer, bannir, classer
 *
 * Un membre signale un message du chat ou du forum, un profil (avatar
 * compris), une performance, un forum de membre. La file regroupe les
 * signalements par cible, les plus signalées d'abord, avec de quoi juger sans
 * quitter la page. Écritures sans transaction, comme le reste.
 */

declare(strict_types=1);

namespace SBDRE;

if (!defined('SBDRE_API')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/bdd.php';
require_once __DIR__ . '/notifications.php';

const TYPES_SIGNALEMENT = ['message', 'message_forum', 'membre', 'performance', 'forum'];
const SIGNALEMENTS_PAR_JOUR = 10;
const MOTIF_MAX = 200;
const ETATS_FORUM = ['ouvert', 'verrouille', 'archive', 'masque'];

/** La cible existe-t-elle, et reste-t-elle visible ? On ne signale pas le vide. */
function cibleExiste(string $type, int $id): bool
{
    $sql = match ($type) {
        'message'       => 'SELECT 1 AS n FROM message WHERE id = :id AND masque_par IS NULL',
        'message_forum' => 'SELECT 1 AS n FROM message_forum WHERE id = :id AND masque_par IS NULL',
        'membre'        => 'SELECT 1 AS n FROM membre WHERE id = :id AND banni = 0 AND courriel_verifie = 1',
        'performance'   => 'SELECT 1 AS n FROM performance WHERE id = :id',
        'forum'         => "SELECT 1 AS n FROM forum WHERE id = :id AND etat <> 'masque'",
        default         => null,
    };

    return $sql !== null && ligne($sql, ['id' => $id]) !== null;
}

function signalementsDuJour(int $auteurId): int
{
    return (int) (ligne(
        'SELECT COUNT(*) AS n FROM signalement WHERE auteur_id = :a AND date_signalement > NOW() - INTERVAL 1 DAY',
        ['a' => $auteurId],
    )['n'] ?? 0);
}

/** Un signalement par membre et par cible : le second ne double rien. Rend vrai s'il est neuf. */
function signaler(int $auteurId, string $type, int $cibleId, string $motif): bool
{
    return executer(
        'INSERT INTO signalement (type, cible_id, auteur_id, motif) VALUES (:t, :c, :a, :m)
         ON DUPLICATE KEY UPDATE id = id',
        ['t' => $type, 'c' => $cibleId, 'a' => $auteurId, 'm' => $motif === '' ? null : mb_substr($motif, 0, MOTIF_MAX)],
    ) === 1;
}

/**
 * La file : une ligne par cible signalée et non traitée, avec un aperçu, son
 * auteur et un lien pour la voir en place.
 *
 * @return list<array<string, mixed>>
 */
function fileModeration(): array
{
    $file = [];
    foreach (lignes(
        "SELECT type, cible_id, COUNT(*) AS nombre, UNIX_TIMESTAMP(MIN(date_signalement)) AS premier_ts,
                GROUP_CONCAT(DISTINCT motif ORDER BY motif SEPARATOR ' · ') AS motifs
           FROM signalement WHERE traite = 0
          GROUP BY type, cible_id
          ORDER BY nombre DESC, premier_ts
          LIMIT 100",
    ) as $s) {
        $id = (int) $s['cible_id'];
        $cible = match ($s['type']) {
            'message' => ligne(
                "SELECT pseudo AS auteur, membre_id AS auteur_id, texte AS apercu,
                        CONCAT('/communaute/chat/') AS lien
                   FROM message WHERE id = :id",
                ['id' => $id],
            ),
            'message_forum' => ligne(
                "SELECT mf.pseudo AS auteur, mf.membre_id AS auteur_id, mf.texte AS apercu,
                        CONCAT('/forum/', f.adresse, '/', s.id, '/') AS lien
                   FROM message_forum mf JOIN sujet s ON s.id = mf.sujet_id JOIN forum f ON f.id = s.forum_id
                  WHERE mf.id = :id",
                ['id' => $id],
            ),
            'membre' => ligne(
                "SELECT pseudo AS auteur, id AS auteur_id, COALESCE(bio, '') AS apercu,
                        CONCAT('/membres/', pseudo, '/') AS lien
                   FROM membre WHERE id = :id",
                ['id' => $id],
            ),
            'performance' => ligne(
                "SELECT m.pseudo AS auteur, p.membre_id AS auteur_id,
                        CONCAT(p.mouvement, ', ', p.equipement, ', ', p.charge_kg, ' kg, ', p.lien_video) AS apercu,
                        CONCAT('/membres/', m.pseudo, '/') AS lien
                   FROM performance p JOIN membre m ON m.id = p.membre_id WHERE p.id = :id",
                ['id' => $id],
            ),
            'forum' => ligne(
                "SELECT COALESCE(m.pseudo, '') AS auteur, f.createur_id AS auteur_id,
                        CONCAT(f.titre, ' : ', f.description, ' (', f.etat, ')') AS apercu,
                        CONCAT('/forum/', f.adresse, '/') AS lien
                   FROM forum f LEFT JOIN membre m ON m.id = f.createur_id WHERE f.id = :id",
                ['id' => $id],
            ),
            default => null,
        };
        $file[] = [
            'type'      => $s['type'],
            'cible_id'  => $id,
            'nombre'    => (int) $s['nombre'],
            'premier'   => (int) $s['premier_ts'],
            'motifs'    => (string) ($s['motifs'] ?? ''),
            // Une cible effacée entre-temps garde sa ligne, à classer.
            'auteur'    => (string) ($cible['auteur'] ?? ''),
            'auteur_id' => isset($cible['auteur_id']) ? (int) $cible['auteur_id'] : null,
            'apercu'    => mb_substr((string) ($cible['apercu'] ?? 'Contenu effacé depuis le signalement.'), 0, 600),
            'lien'      => (string) ($cible['lien'] ?? ''),
        ];
    }

    return $file;
}

/** Les forums des membres, les plus récents d'abord, avec leurs signalements en attente. */
function forumsDesMembres(): array
{
    return lignes(
        "SELECT f.id, f.adresse, f.titre, f.etat, UNIX_TIMESTAMP(f.date_creation) AS creation_ts,
                COALESCE(m.pseudo, '') AS createur,
                (SELECT COUNT(*) FROM signalement s WHERE s.type = 'forum' AND s.cible_id = f.id AND s.traite = 0) AS signalements
           FROM forum f LEFT JOIN membre m ON m.id = f.createur_id
          WHERE f.createur_id IS NOT NULL OR f.categorie_id = 9
          ORDER BY f.date_creation DESC
          LIMIT 50",
    );
}

/** Tous les signalements d'une cible passent en traités. */
function classer(string $type, int $cibleId): void
{
    executer(
        'UPDATE signalement SET traite = 1 WHERE type = :t AND cible_id = :c AND traite = 0',
        ['t' => $type, 'c' => $cibleId],
    );
}

/** Masque un message du chat ou du forum. Rend son auteur, pour le prévenir. */
function masquerMessage(string $type, int $id, int $adminId): ?int
{
    $table = $type === 'message' ? 'message' : 'message_forum';
    $auteur = ligne("SELECT membre_id FROM $table WHERE id = :id", ['id' => $id]);
    executer("UPDATE $table SET masque_par = :a WHERE id = :id", ['a' => $adminId, 'id' => $id]);

    return isset($auteur['membre_id']) ? (int) $auteur['membre_id'] : null;
}

/** Retire une performance, ses votes avec elle. Rend son auteur. */
function retirerPerformance(int $id): ?int
{
    $auteur = ligne('SELECT membre_id FROM performance WHERE id = :id', ['id' => $id]);
    executer('DELETE FROM performance WHERE id = :id', ['id' => $id]);

    return isset($auteur['membre_id']) ? (int) $auteur['membre_id'] : null;
}

/**
 * Bannit un membre : ses sessions tombent aussitôt, son profil sort du site
 * et du plan de site, ses messages restent au journal d'un an. Un admin ne
 * se bannit pas, ni un autre admin. Rend vrai si quelqu'un est banni.
 */
function bannir(int $membreId): bool
{
    $touche = executer("UPDATE membre SET banni = 1 WHERE id = :m AND role <> 'admin'", ['m' => $membreId]);
    executer("DELETE FROM jeton WHERE membre_id = :m AND type = 'session'", ['m' => $membreId]);

    return $touche === 1;
}

function changerEtatForum(int $forumId, string $etat): bool
{
    return in_array($etat, ETATS_FORUM, true)
        && executer('UPDATE forum SET etat = :e WHERE id = :f', ['e' => $etat, 'f' => $forumId]) >= 0;
}

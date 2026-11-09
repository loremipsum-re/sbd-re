<?php
/**
 * Relier un membre à sa fiche d'athlète officielle, demande de l'auteur du
 * 9 novembre 2026.
 *
 *   1. Le membre réclame la fiche depuis /athlete/<slug>/, « C'est ma fiche »,
 *      avec un message facultatif pour l'admin.
 *   2. L'admin relie ou refuse, depuis /compte/admin/.
 *   3. Relié, le profil du membre mène à sa fiche, et la fiche à son profil.
 *
 * RIEN N'EST RELIÉ SANS L'ADMIN : une fiche porte les résultats officiels
 * d'une personne réelle. Une fiche ne se relie qu'à un membre, un membre qu'à
 * une fiche, et le lien se retire à tout moment par le membre.
 *
 * Les fiches existantes se lisent dans /api/athletes.json, généré avec le
 * site. Les fonctions écrivent sans transaction, pour que db/verifier.php les
 * éprouve dans la sienne.
 */

declare(strict_types=1);

namespace SBDRE;

if (!defined('SBDRE_API')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/bdd.php';

const REVENDICATION_MESSAGE_MAX = 300;
const REVENDICATIONS_PAR_JOUR = 3;

/** La forme d'une adresse de fiche, celle de athleteSlug() : « jean-dupont-2 ». */
function slugAthleteValide(string $slug): bool
{
    return strlen($slug) <= 120 && preg_match('/^[a-z0-9]+(-[a-z0-9]+)*\z/', $slug) === 1;
}

/** Les fiches du site, adresse vers nom, lues une fois par requête. */
function fichesAthletes(): array
{
    static $fiches = null;
    if ($fiches === null) {
        $lu = json_decode((string) @file_get_contents(dirname(__DIR__) . '/athletes.json'), true);
        $fiches = is_array($lu) ? $lu : [];
    }

    return $fiches;
}

/** Le nom d'un athlète, ou null si sa fiche n'existe pas. */
function nomAthlete(string $slug): ?string
{
    $nom = fichesAthletes()[$slug] ?? null;

    return is_string($nom) ? $nom : null;
}

function messageRevendicationValide(string $message): bool
{
    return mb_strlen($message) <= REVENDICATION_MESSAGE_MAX && preg_match('/[\x00-\x09\x0B-\x1F\x7F]/', $message) !== 1;
}

/** Le pseudo du membre relié à cette fiche, s'il est actif. */
function membreLie(string $slug): ?string
{
    $m = ligne('SELECT pseudo FROM membre WHERE athlete_slug = :s AND courriel_verifie = 1 AND banni = 0', ['s' => $slug]);

    return $m !== null ? (string) $m['pseudo'] : null;
}

/** La fiche reliée au membre, et sa demande en attente. */
function etatFiche(int $membreId): array
{
    $lien = ligne('SELECT athlete_slug FROM membre WHERE id = :m', ['m' => $membreId]);
    $attente = ligne(
        "SELECT athlete_slug, UNIX_TIMESTAMP(date_creation) AS date_ts FROM revendication
          WHERE membre_id = :m AND etat = 'attente' ORDER BY id DESC LIMIT 1",
        ['m' => $membreId],
    );
    $slug = $lien['athlete_slug'] ?? null;

    return [
        'lien'    => $slug !== null ? ['slug' => (string) $slug, 'nom' => nomAthlete((string) $slug) ?? (string) $slug] : null,
        'attente' => $attente !== null
            ? ['slug' => (string) $attente['athlete_slug'], 'nom' => nomAthlete((string) $attente['athlete_slug']) ?? (string) $attente['athlete_slug']]
            : null,
    ];
}

function revendicationsDuJour(int $membreId): int
{
    return (int) (ligne(
        'SELECT COUNT(*) AS n FROM revendication WHERE membre_id = :m AND date_creation > NOW() - INTERVAL 1 DAY',
        ['m' => $membreId],
    )['n'] ?? 0);
}

/**
 * Pourquoi la demande ne peut pas partir, ou '' si elle le peut. Les
 * contrôles qui demandent la base, l'appelant ayant vérifié la forme.
 */
function refusRevendication(int $membreId, string $slug): string
{
    $etat = etatFiche($membreId);

    return match (true) {
        nomAthlete($slug) === null => 'Cette fiche n\'existe pas sur le site.',
        $etat['lien'] !== null => 'Votre compte est déjà relié à une fiche. Retirez ce lien depuis votre espace avant d\'en demander un autre.',
        $etat['attente'] !== null => 'Une demande attend déjà l\'admin. Elle reçoit une réponse avant la suivante.',
        membreLie($slug) !== null => 'Cette fiche est déjà reliée à un autre membre. Écrivez à l\'admin si c\'est une erreur.',
        revendicationsDuJour($membreId) >= REVENDICATIONS_PAR_JOUR => REVENDICATIONS_PAR_JOUR . ' demandes par jour au plus. Revenez demain.',
        default => '',
    };
}

function demanderFiche(int $membreId, string $slug, string $message): int
{
    executer(
        'INSERT INTO revendication (membre_id, athlete_slug, message) VALUES (:m, :s, :t)',
        ['m' => $membreId, 's' => $slug, 't' => $message],
    );

    return (int) bdd()->lastInsertId();
}

/** Les demandes en attente, les plus anciennes d'abord, pour la page de l'admin. */
function revendicationsEnAttente(): array
{
    return array_map(
        static fn (array $r): array => [
            'id'      => (int) $r['id'],
            'pseudo'  => (string) $r['pseudo'],
            'slug'    => (string) $r['athlete_slug'],
            'nom'     => nomAthlete((string) $r['athlete_slug']) ?? (string) $r['athlete_slug'],
            'message' => (string) $r['message'],
            'date'    => (int) $r['date_ts'],
            'deja'    => $r['deja'] !== null ? (string) $r['deja'] : '',
        ],
        lignes(
            "SELECT r.id, r.athlete_slug, r.message, UNIX_TIMESTAMP(r.date_creation) AS date_ts, m.pseudo,
                    (SELECT m2.pseudo FROM membre m2 WHERE m2.athlete_slug = r.athlete_slug) AS deja
               FROM revendication r JOIN membre m ON m.id = r.membre_id
              WHERE r.etat = 'attente'
              ORDER BY r.date_creation, r.id
              LIMIT 50",
        ),
    );
}

/**
 * Relier : la fiche passe au membre si elle est libre et qu'il n'en a pas.
 * Rend le membre et la fiche, ou null si la demande n'est plus valable.
 */
function accepterRevendication(int $id, int $adminId): ?array
{
    $r = ligne("SELECT membre_id, athlete_slug FROM revendication WHERE id = :id AND etat = 'attente'", ['id' => $id]);
    if ($r === null) {
        return null;
    }
    $relie = executer(
        'UPDATE membre SET athlete_slug = :s
          WHERE id = :m AND athlete_slug IS NULL
            AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM membre WHERE athlete_slug = :s2) AS pris)',
        ['s' => $r['athlete_slug'], 'm' => $r['membre_id'], 's2' => $r['athlete_slug']],
    );
    if ($relie !== 1) {
        return null;
    }
    executer(
        "UPDATE revendication SET etat = 'acceptee', admin_id = :a, date_decision = NOW() WHERE id = :id",
        ['a' => $adminId, 'id' => $id],
    );

    return ['membre_id' => (int) $r['membre_id'], 'slug' => (string) $r['athlete_slug']];
}

/** Refuser. Rend le membre à prévenir, ou null si la demande n'attend plus. */
function refuserRevendication(int $id, int $adminId): ?int
{
    $r = ligne("SELECT membre_id FROM revendication WHERE id = :id AND etat = 'attente'", ['id' => $id]);
    if ($r === null) {
        return null;
    }
    executer(
        "UPDATE revendication SET etat = 'refusee', admin_id = :a, date_decision = NOW() WHERE id = :id",
        ['a' => $adminId, 'id' => $id],
    );

    return (int) $r['membre_id'];
}

/** Le membre retire le lien, ou sa demande en attente. */
function retirerFiche(int $membreId): void
{
    executer('UPDATE membre SET athlete_slug = NULL WHERE id = :m', ['m' => $membreId]);
    executer("DELETE FROM revendication WHERE membre_id = :m AND etat = 'attente'", ['m' => $membreId]);
}

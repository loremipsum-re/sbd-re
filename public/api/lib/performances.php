<?php
/**
 * Les performances déclarées par les membres : formes acceptées et records.
 *
 * Fonctions pures, éprouvées sans base par db/verifier-autorisation.php.
 * performances.php les emploie à l'écriture, lib/profil.php à l'affichage.
 * Le vocabulaire est celui du site, src/lib/categories.ts : Squat,
 * Développé couché, Soulevé de terre ; Raw, Bandes, Équipé.
 *
 * UNE PERFORMANCE NE SE MODIFIE PAS (docs/comptes.md, section 6.3) : on la
 * retire et on la refait. Une modification qui garderait les votes ferait
 * valider 300 kg avec les avis donnés à 150 kg.
 */

declare(strict_types=1);

namespace SBDRE;

if (!defined('SBDRE_API')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/autorisation.php';

/**
 * Les performances avec leurs votes, pour le profil, l'espace membre et le
 * fil « À vérifier ». `%1$s` reçoit la condition, `%2$s` la fin (tri,
 * limite), écrites par le code, jamais par la requête.
 *
 * Les votes d'un membre banni ne comptent pas (docs/comptes.md, section 4) :
 * la jointure sur `mv` les écarte, et COUNT(mv.id) ne compte que les autres.
 * Le texte est une constante pour que db/verifier.php l'éprouve tel quel
 * sur MySQL, en mode strict, dans sa transaction.
 */
const SQL_PERFORMANCES_VOTES = 'SELECT p.id, p.membre_id, p.mouvement, p.equipement, p.charge_kg, p.categorie,
        p.date_performance, p.lien_video, p.date_verif_admin, p.date_creation, m.pseudo,
        COUNT(mv.id) AS votes,
        COALESCE(SUM(CASE WHEN mv.id IS NOT NULL THEN v.ok END), 0) AS favorables
   FROM performance p
   JOIN membre m ON m.id = p.membre_id
   LEFT JOIN vote v ON v.performance_id = p.id
   LEFT JOIN membre mv ON mv.id = v.membre_id AND mv.banni = 0
  WHERE %1$s
  GROUP BY p.id, m.pseudo
  %2$s';

/**
 * Donner ou changer son avis : un avis par membre et par performance (clé
 * unique de `vote`), le second remplace le premier. La forme `AS nouveau`
 * remplace VALUES(), déprécié depuis MySQL 8.0.20.
 */
const SQL_VOTER = 'INSERT INTO vote (performance_id, membre_id, ok) VALUES (:p, :m, :ok) AS nouveau
     ON DUPLICATE KEY UPDATE ok = nouveau.ok';

/**
 * @param  array<string, mixed> $params
 * @return list<array<string, mixed>>
 */
function performancesAvecVotes(string $condition, array $params, string $fin = 'ORDER BY p.date_performance DESC, p.id DESC'): array
{
    return lignes(sprintf(SQL_PERFORMANCES_VOTES, $condition, $fin), $params);
}

/**
 * Le badge d'une performance : « admin » si l'admin l'a vérifiée,
 * « communaute » si 20 avis au moins, dont 60 % favorables, sinon ''.
 */
function badgeDe(array $performance): string
{
    if (($performance['date_verif_admin'] ?? null) !== null) {
        return 'admin';
    }

    return badgeCommunaute((int) ($performance['votes'] ?? 0), (int) ($performance['favorables'] ?? 0))
        ? 'communaute'
        : '';
}

const MOUVEMENTS = ['squat' => 'Squat', 'bench' => 'Développé couché', 'deadlift' => 'Soulevé de terre'];
const EQUIPEMENTS = ['raw' => 'Raw', 'wraps' => 'Bandes', 'equipped' => 'Équipé'];

/** Au-dessus des records du monde, toutes catégories : une faute de frappe. */
const CHARGE_MAX_KG = 600;

/** Dix ajouts par jour et par membre : de quoi saisir une séance, pas d'inonder. */
const PERFORMANCES_PAR_JOUR = 10;

/**
 * Les seuls hébergeurs de vidéo admis, et seulement en https. La vidéo
 * s'affiche en lien, jamais en lecteur intégré : un lecteur chargerait un
 * script tiers, interdit sans consentement.
 */
const HOTES_VIDEO = [
    'instagram.com', 'www.instagram.com',
    'youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be',
    'tiktok.com', 'www.tiktok.com', 'vm.tiktok.com', 'vt.tiktok.com',
];

function lienVideoValide(string $lien): bool
{
    // Ni espace, ni chevron, ni guillemet : l'adresse ressort dans un attribut.
    if ($lien === '' || strlen($lien) > 500 || preg_match('/[\s<>"\'\\\\]/', $lien) === 1) {
        return false;
    }
    $morceaux = parse_url($lien);

    return is_array($morceaux)
        && ($morceaux['scheme'] ?? '') === 'https'
        && in_array(strtolower((string) ($morceaux['host'] ?? '')), HOTES_VIDEO, true)
        && !isset($morceaux['user'])
        && !isset($morceaux['pass'])
        && !isset($morceaux['port'])
        && isset($morceaux['path']) && $morceaux['path'] !== '/';
}

/**
 * La charge en kilos, ou null si elle ne convient pas. Accepte un nombre ou
 * un texte, la virgule comme le point : « 182,5 ».
 */
function chargeValide(mixed $saisie): ?float
{
    if (is_string($saisie)) {
        $saisie = str_replace(',', '.', trim($saisie));
    }
    if ((!is_int($saisie) && !is_float($saisie) && !is_numeric($saisie))
        || preg_match('/^\d{1,3}(\.\d{1,2})?\z/', (string) $saisie) !== 1) {
        return null;
    }
    $kg = (float) $saisie;

    return $kg > 0 && $kg <= CHARGE_MAX_KG ? $kg : null;
}

/** Une date réelle au format AAAA-MM-JJ, depuis 2000, jamais dans le futur. */
function datePerformanceValide(string $date, int $maintenant): bool
{
    $lue = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);

    return $lue !== false
        && $lue->format('Y-m-d') === $date
        && $date >= '2000-01-01'
        && $date <= date('Y-m-d', $maintenant);
}

/**
 * Une catégorie de poids, facultative : « 93 », « 120+ ». La forme seule se
 * contrôle, comme le site lit les catégories dans les données sans les
 * figer (src/lib/categories.ts).
 */
function categorieValide(string $categorie): bool
{
    if ($categorie === '') {
        return true;
    }
    if (preg_match('/^(\d{2,3})\+?\z/', $categorie, $m) !== 1) {
        return false;
    }

    return (int) $m[1] >= 30 && (int) $m[1] <= 200;
}

/** « −93 kg », « +120 kg », comme parseWeightClass du site. */
function libelleCategorie(string $categorie): string
{
    return str_ends_with($categorie, '+')
        ? '+' . rtrim($categorie, '+') . "\u{00A0}kg"
        : '−' . $categorie . "\u{00A0}kg";
}

/** « 182,5 kg », « 200 kg » : virgule, et espace insécable avant l'unité. */
function kgFr(float $kg): string
{
    return rtrim(rtrim(number_format($kg, 2, ',', "\u{202F}"), '0'), ',') . "\u{00A0}kg";
}

/**
 * Les records d'un membre : par équipement, la meilleure barre de chaque
 * mouvement, et le total quand les trois existent. À charge égale, une barre
 * vérifiée l'emporte, puis la plus ancienne, comme en compétition.
 *
 * @param  list<array<string, mixed>> $performances
 * @return array<string, array{barres: array<string, array<string, mixed>>, total: ?float}>
 */
function recordsDe(array $performances): array
{
    $records = [];

    foreach ($performances as $p) {
        $eq = (string) $p['equipement'];
        $mv = (string) $p['mouvement'];
        $actuel = $records[$eq]['barres'][$mv] ?? null;

        // Clés de tri, de la plus forte à la plus faible : charge, vérifiée,
        // ancienneté. Comparer deux tableaux en PHP compare case par case.
        $cle = static fn (array $b): array => [
            (float) $b['charge_kg'],
            badgeDe($b) !== '' ? 1 : 0,
            -(int) str_replace('-', '', (string) $b['date_performance']),
        ];
        if ($actuel === null || $cle($p) > $cle($actuel)) {
            $records[$eq]['barres'][$mv] = $p;
        }
    }

    // Dans l'ordre du site : équipements puis mouvements.
    $ordonnes = [];
    foreach (array_keys(EQUIPEMENTS) as $eq) {
        if (!isset($records[$eq])) {
            continue;
        }
        $barres = array_filter(array_map(
            static fn (string $mv): ?array => $records[$eq]['barres'][$mv] ?? null,
            array_combine(array_keys(MOUVEMENTS), array_keys(MOUVEMENTS)),
        ));
        $ordonnes[$eq] = [
            'barres' => $barres,
            'total'  => count($barres) === 3
                ? array_sum(array_map(static fn (array $b): float => (float) $b['charge_kg'], $barres))
                : null,
        ];
    }

    return $ordonnes;
}

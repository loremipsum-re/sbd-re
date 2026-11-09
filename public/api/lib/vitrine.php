<?php
/**
 * La vitrine de /communaute/ : les dernières barres publiées, et le top des
 * membres. Demande de l'auteur du 10 novembre 2026.
 *
 * LE TOP : les trois barres vérifiées les plus lourdes de chaque mouvement,
 * en raw seulement, une par membre (sa meilleure). Une barre de salle n'est
 * pas une barre de compétition : la page le dit, et ce top reste à part du
 * classement officiel. Le calcul est pur, éprouvé sans base par
 * db/verifier-autorisation.php ; la lecture en base, par db/verifier.php.
 */

declare(strict_types=1);

namespace SBDRE;

if (!defined('SBDRE_API')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/performances.php';

const TOP_PAR_MOUVEMENT = 3;
const DERNIERES_BARRES  = 6;

/** Les comptes qui s'affichent : confirmés et non bannis. */
const CONDITION_VITRINE = 'm.banni = 0 AND m.courriel_verifie = 1';

/**
 * Le top par mouvement : les barres vérifiées, la meilleure de chaque
 * membre, les plus lourdes d'abord, à égalité la plus ancienne.
 *
 * @param list<array<string, mixed>> $performances avec leurs votes, en raw
 * @return array<string, list<array{pseudo: string, charge: float, date: string}>>
 */
function topMembres(array $performances, int $combien = TOP_PAR_MOUVEMENT): array
{
    $meilleures = array_fill_keys(array_keys(MOUVEMENTS), []);
    foreach ($performances as $p) {
        if (badgeDe($p) === '' || !isset($meilleures[$p['mouvement']])) {
            continue;
        }
        $pseudo = (string) $p['pseudo'];
        $actuelle = $meilleures[$p['mouvement']][$pseudo] ?? null;
        if ($actuelle === null || (float) $p['charge_kg'] > $actuelle['charge']
            || ((float) $p['charge_kg'] === $actuelle['charge'] && $p['date_performance'] < $actuelle['date'])) {
            $meilleures[$p['mouvement']][$pseudo] = ['pseudo' => $pseudo, 'charge' => (float) $p['charge_kg'], 'date' => (string) $p['date_performance']];
        }
    }

    return array_map(static function (array $parMembre) use ($combien): array {
        $liste = array_values($parMembre);
        usort($liste, static fn (array $a, array $b): int => [$b['charge'], $a['date']] <=> [$a['charge'], $b['date']]);

        return array_slice($liste, 0, $combien);
    }, $meilleures);
}

/** Une barre telle que la vitrine la montre, sans lien vidéo ni identifiant. */
function barreVitrine(array $p): array
{
    return [
        'pseudo'     => (string) $p['pseudo'],
        'mouvement'  => (string) $p['mouvement'],
        'equipement' => (string) $p['equipement'],
        'charge'     => (float) $p['charge_kg'],
        'date'       => (string) $p['date_performance'],
        'verifiee'   => badgeDe($p) !== '',
    ];
}

/** @return array{dernieres: list<array<string, mixed>>, top: array<string, list<array<string, mixed>>>} */
function vitrine(): array
{
    return [
        'dernieres' => array_map('SBDRE\barreVitrine', performancesAvecVotes(
            CONDITION_VITRINE,
            [],
            'ORDER BY p.date_creation DESC, p.id DESC LIMIT ' . DERNIERES_BARRES,
        )),
        'top' => topMembres(performancesAvecVotes(CONDITION_VITRINE . " AND p.equipement = 'raw'", [])),
    ];
}

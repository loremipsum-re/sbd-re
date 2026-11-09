<?php
/**
 * L'annuaire des membres, à /membres/ : chaque compte confirmé et non banni,
 * en cartes, ceux qui ont publié le plus de barres d'abord.
 *
 * api/annuaire.php lit la page demandée et la recherche, ce fichier interroge
 * la base et remplit le gabarit d'Astro, /membres/-annuaire/. Le rendu est
 * pur, éprouvé sans base par db/verifier-autorisation.php.
 */

declare(strict_types=1);

namespace SBDRE;

if (!defined('SBDRE_API')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/profil.php';
require_once __DIR__ . '/forum.php';

const MEMBRES_PAR_PAGE = 30;

/**
 * La recherche, ramenée à ce qu'un pseudo peut contenir. Rend '' pour une
 * recherche illisible : la page dit alors comment chercher.
 */
function rechercheMembre(string $q): string
{
    $q = mb_strtolower(trim($q));

    return preg_match('/^[a-z0-9._]{2,30}$/', $q) === 1 ? $q : '';
}

/**
 * Une page de l'annuaire et le total des membres trouvés.
 *
 * @return array{membres: list<array<string, mixed>>, total: int}
 */
function membresAnnuaire(int $page, string $q): array
{
    $condition = 'm.courriel_verifie = 1 AND m.banni = 0';
    $params = [];
    if ($q !== '') {
        // « _ » est un joker de LIKE, et il est permis dans un pseudo.
        $condition .= ' AND m.pseudo LIKE :q';
        $params['q'] = '%' . addcslashes($q, '%_\\') . '%';
    }
    $total = (int) (ligne("SELECT COUNT(*) AS n FROM membre m WHERE $condition", $params)['n'] ?? 0);
    $decalage = (max(1, $page) - 1) * MEMBRES_PAR_PAGE;
    $membres = lignes(
        "SELECT m.id, m.pseudo, m.salle, m.athlete_slug, UNIX_TIMESTAMP(a.date_maj) AS avatar_v,
                (SELECT COUNT(*) FROM performance p WHERE p.membre_id = m.id) AS barres
           FROM membre m LEFT JOIN avatar a ON a.membre_id = m.id
          WHERE $condition
          ORDER BY barres DESC, m.pseudo
          LIMIT " . MEMBRES_PAR_PAGE . ' OFFSET ' . $decalage,
        $params,
    );
    if ($membres === []) {
        return ['membres' => [], 'total' => $total];
    }

    // Les barres vérifiées se comptent comme sur le profil, avec badgeDe.
    $ids = [];
    foreach ($membres as $i => $m) {
        $ids["i$i"] = (int) $m['id'];
    }
    $verifiees = [];
    foreach (performancesAvecVotes('p.membre_id IN (:' . implode(', :', array_keys($ids)) . ')', $ids) as $p) {
        if (badgeDe($p) !== '') {
            $verifiees[(int) $p['membre_id']] = ($verifiees[(int) $p['membre_id']] ?? 0) + 1;
        }
    }
    $membres = array_map(static fn (array $m): array => $m + ['verifiees' => $verifiees[(int) $m['id']] ?? 0], $membres);

    return ['membres' => $membres, 'total' => $total];
}

/** La carte d'un membre : son avatar et son pseudo en lien, sa salle, sa fiche, ses barres. */
function carteAnnuaire(array $m, array $salles): string
{
    $pseudo = (string) $m['pseudo'];
    $avatar = ($m['avatar_v'] ?? null) !== null
        ? '<img class="avatar" src="' . e(adresseAvatar($pseudo, (int) $m['avatar_v'])) . '" alt="" width="64" height="64" loading="lazy">'
        : '<span class="avatar avatar-vide" aria-hidden="true">' . e(mb_strtoupper(mb_substr($pseudo, 0, 1))) . '</span>';

    $faits = [];
    $salle = $salles[(string) ($m['salle'] ?? '')] ?? null;
    if ($salle !== null) {
        $faits[] = fait('salles', 'Salle', e($salle['nom'] . ', ' . $salle['commune']));
    }
    if (($m['athlete_slug'] ?? null) !== null) {
        $faits[] = fait('records', 'Fiche d\'athlète', 'Athlète vérifié');
    }
    $barres = (int) $m['barres'];
    $faits[] = fait('valide', 'Barres', $barres === 0 ? 'Aucune barre publiée'
        : compte($barres, 'barre', 'barres') . ', ' . compte((int) $m['verifiees'], 'vérifiée', 'vérifiées'));

    return '<li class="carte-annuaire"><a class="identite-annuaire" href="/membres/' . e($pseudo) . '/">' . $avatar
        . '<span class="pseudo">' . e($pseudo) . '</span></a><ul class="faits">' . implode('', $faits) . '</ul></li>';
}

/**
 * Remplit le gabarit. La page 1 et la liste entière sont indexées, une
 * recherche ne l'est pas : elle n'apprend rien qu'un moteur ne trouve déjà.
 *
 * @param list<array<string, mixed>> $membres
 */
function rendreAnnuaire(string $gabarit, array $membres, int $total, int $page, string $q, array $salles, bool $rechercheIllisible = false): string
{
    $pages = max(1, (int) ceil($total / MEMBRES_PAR_PAGE));
    $canonique = '/membres/' . ($q === '' && $page > 1 ? '?page=' . $page : '');

    if ($rechercheIllisible) {
        $liste = '<p class="aide">Un pseudo tient en 2 à 30 caractères, lettres, chiffres, point et tiret bas.</p>';
    } elseif ($membres === []) {
        $liste = $q !== ''
            ? '<p class="ligne-vide">Aucun membre ne porte « ' . e($q) . ' » dans son pseudo. <a href="/membres/">Voir tous les membres</a></p>'
            : '<p class="ligne-vide">Aucun membre pour l\'instant. <a href="/compte/inscription/">Créer un compte</a></p>';
    } else {
        $liste = '<ul class="annuaire">' . implode('', array_map(static fn (array $m): string => carteAnnuaire($m, $salles), $membres)) . '</ul>';
    }

    // Une recherche tient sur une page : au-delà, la page invite à préciser.
    $effectif = $q === '' ? compte($total, 'membre', 'membres') : compte($total, 'membre trouvé', 'membres trouvés')
        . ($total > MEMBRES_PAR_PAGE ? ', les ' . MEMBRES_PAR_PAGE . ' premiers affichés' : '');
    $pagination = $q === '' ? pagination('/membres/', $page, $pages) : '';

    return strtr($gabarit, [
        '%%TITRE%%'      => e(($page > 1 ? 'Les membres, page ' . $page : 'Les membres de la communauté') . ' | SBD.re'),
        '%%ROBOTS%%'     => $q !== '' || $rechercheIllisible ? 'noindex' : 'index, follow',
        '%%EFFECTIF%%'   => e($effectif),
        '%%Q%%'          => e($q),
        '%%LISTE%%'      => $liste,
        '%%PAGINATION%%' => $pagination,
        '/membres/-annuaire/' => e($canonique),
    ]);
}

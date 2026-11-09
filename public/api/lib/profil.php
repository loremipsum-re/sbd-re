<?php
/**
 * Le rendu d'un profil public, à partir du gabarit d'Astro.
 *
 * `rendreProfil()` est pure : un membre, le gabarit, les salles, et la page
 * en sort. Elle s'éprouve donc sans base, avec un membre inventé, et la page
 * produite s'ouvre dans un navigateur comme les autres. profil-page.php ne
 * fait que lire la base et le disque, puis l'appeler.
 *
 * TOUT CE QUI VIENT DU MEMBRE EST ÉCHAPPÉ, une seule fois, ici. Le pseudo et
 * le nom Instagram ont un format contrôlé, mais la présentation est un texte
 * libre : sans échappement, un `<script>` écrit dans une présentation
 * s'exécuterait chez chaque visiteur du profil.
 */

declare(strict_types=1);

namespace SBDRE;

if (!defined('SBDRE_API')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/comptes.php';
require_once __DIR__ . '/performances.php';
require_once __DIR__ . '/avatar.php';

/** Le lien d'une vidéo : sortant, annoncé, et `ugc nofollow` puisqu'un membre l'a écrit. */
function lienVideo(string $adresse): string
{
    return '<a href="' . e($adresse) . '" target="_blank" rel="ugc nofollow noopener"'
        . ' aria-describedby="annonce-lien-sortant">Vidéo</a>';
}

/** Le libellé du badge, partagé avec l'espace membre par `badge` de performances.php. */
const LIBELLES_BADGE = [
    'admin'      => 'Vérifiée par SBD.re',
    'communaute' => 'Vérifiée par la communauté',
    ''           => 'Non vérifiée',
];

/**
 * Les mêmes, en court, pour les tuiles des records, où la place manque : la
 * coche dit « vérifiée », la légende sous « Records » le rappelle, et les
 * lecteurs d'écran entendent la phrase entière.
 */
const COCHE = '<svg class="icone-verif" aria-hidden="true" focusable="false"><use href="#icone-valide"/></svg>';
const LIBELLES_BADGE_COURTS = [
    'admin'      => COCHE . '<span class="sr-only">Vérifiée par </span>SBD.re',
    'communaute' => COCHE . '<span class="sr-only">Vérifiée par la </span>communauté',
    ''           => 'Non vérifiée',
];

/** Les abréviations des tableaux du site, à côté du dessin du mouvement. */
const ABREGES = ['squat' => 'SQ', 'bench' => 'DC', 'deadlift' => 'SDT'];

/** L'état de vérification d'une barre. */
function etatVerification(array $performance, bool $court = false): string
{
    // Deux couleurs, demande de l'auteur du 7 novembre 2026 : --succes pour
    // vérifiée, --alerte pour non vérifiée. Le mot reste écrit : la couleur
    // seule ne porterait pas le sens.
    $badge = badgeDe($performance);

    return '<span class="verif ' . ($badge !== '' ? 'verif-oui' : 'verif-non') . '">'
        . ($court ? LIBELLES_BADGE_COURTS : LIBELLES_BADGE)[$badge] . '</span>';
}

/**
 * Une ligne de la carte du membre : l'icône du sprite du gabarit, un libellé
 * dit aux lecteurs d'écran, la valeur. L'icône est décorative.
 */
function fait(string $icone, string $libelle, string $valeur): string
{
    return '<li><svg class="icone-fait" aria-hidden="true" focusable="false"><use href="#icone-' . $icone . '"/></svg>'
        . '<span><span class="sr-only">' . $libelle . ' : </span>' . $valeur . '</span></li>';
}

/**
 * Les records en tuiles, une par mouvement et le total, pour se lire d'un
 * coup d'œil ; puis la liste complète, repliée, plus récente d'abord.
 * Demande de l'auteur du 9 novembre 2026.
 *
 * @param list<array<string, mixed>> $performances
 */
function rendrePerformances(array $performances): string
{
    if ($performances === []) {
        return '<p class="chapeau">Aucune performance publiée pour l\'instant.</p>';
    }

    $html = '<p class="legende-records">' . COCHE . ' Une coche marque une barre vérifiée, par la communauté ou par SBD.re.</p>';
    foreach (recordsDe($performances) as $equipement => $record) {
        $tuiles = '';
        foreach ($record['barres'] as $mouvement => $barre) {
            // Le dessin du mouvement vient du sprite posé par le gabarit (SpriteMouvements).
            // Le dessin et l'abréviation, la vidéo sur la même ligne : la tuile
            // tient en trois lignes. Le nom entier reste dit aux lecteurs d'écran.
            $tuiles .= '<li class="tuile"><p class="mouvement"><span><svg class="icone-mouvement" aria-hidden="true" focusable="false">'
                . '<use href="#mvt-' . e((string) $mouvement) . '"/></svg><span aria-hidden="true">' . (ABREGES[$mouvement] ?? '') . '</span>'
                . '<span class="sr-only">' . e(MOUVEMENTS[$mouvement]) . '</span></span>'
                . lienVideo((string) $barre['lien_video']) . '</p>'
                . '<p class="charge">' . kgFr((float) $barre['charge_kg']) . '</p>'
                . '<p class="etat-barre">' . etatVerification($barre, true) . '</p></li>';
        }
        if ($record['total'] !== null) {
            $tuiles .= '<li class="tuile total"><p class="mouvement">Total</p><p class="charge">' . kgFr($record['total']) . '</p></li>';
        }
        $html .= '<h3>' . e(EQUIPEMENTS[$equipement]) . '</h3><ul class="tuiles">' . $tuiles . '</ul>';
    }

    $lignes = '';
    foreach ($performances as $p) {
        $details = [
            e(dateFr(strtotime((string) $p['date_performance']) ?: time())),
            e(MOUVEMENTS[$p['mouvement']] ?? (string) $p['mouvement']),
            e(EQUIPEMENTS[$p['equipement']] ?? (string) $p['equipement']),
            '<strong>' . kgFr((float) $p['charge_kg']) . '</strong>',
        ];
        if (($p['categorie'] ?? '') !== '') {
            $details[] = e(libelleCategorie((string) $p['categorie']));
        }
        $lignes .= '<li>' . implode(' · ', $details) . ' · ' . lienVideo((string) $p['lien_video'])
            . ' ' . etatVerification($p) . '</li>';
    }

    return $html . '<details class="repli historique"><summary>Toutes les performances, ' . count($performances) . '</summary>'
        . '<ul class="liste-perfs">' . $lignes . '</ul></details>';
}

/** Échappe pour le texte comme pour un attribut entre guillemets. */
function e(string $texte): string
{
    return htmlspecialchars($texte, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/**
 * La page d'un profil.
 *
 * @param array{pseudo: string, bio: ?string, salle: ?string, instagram: ?string,
 *              instagram_visible: int|string, date_inscription: string} $membre
 * @param array<string, array{nom: string, commune: string}> $salles
 * @param list<array<string, mixed>> $performances les barres du membre, plus récente d'abord
 * @param list<array<string, mixed>> $sujets       ses sujets au forum (sujetsDuMembre), dix au plus
 */
/** Ses sujets au forum, du plus récemment actif au plus ancien. Rien s'il n'en a ouvert aucun. */
function rendreSujetsMembre(array $sujets): string
{
    if ($sujets === []) {
        return '';
    }
    $lignes = array_map(
        static fn (array $s): string => '<li><a href="' . e((string) $s['lien']) . '">' . e((string) $s['titre']) . '</a>'
            . '<span class="meta">' . e((string) $s['forum']) . ', ' . match ((int) $s['reponses']) {
                0 => 'aucune réponse',
                1 => "1 réponse",
                default => (int) $s['reponses'] . " réponses",
            } . '</span></li>',
        $sujets,
    );

    return '<section class="sujets-membre" aria-labelledby="titre-sujets-membre">'
        . '<h2 id="titre-sujets-membre">Ses sujets au forum</h2><ul>' . implode('', $lignes) . '</ul></section>';
}

function rendreProfil(array $membre, string $gabarit, array $salles, array $performances, array $sujets = []): string
{
    $pseudo = (string) $membre['pseudo'];
    $bio = trim((string) ($membre['bio'] ?? ''));
    $inscription = strtotime((string) $membre['date_inscription']) ?: time();
    $adresse = 'https://sbd.re/membres/' . $pseudo . '/';

    $instagram = (int) $membre['instagram_visible'] === 1 && ($membre['instagram'] ?? '') !== ''
        ? 'https://www.instagram.com/' . $membre['instagram'] . '/'
        : '';
    $salle = $salles[(string) ($membre['salle'] ?? '')] ?? null;

    /* --- La carte : salle, Instagram, fiche, ancienneté, en icônes --- */

    $fiche = [];
    if ($salle !== null) {
        $fiche[] = fait('salles', 'Salle', '<a href="/salles/">' . e($salle['nom'] . ', ' . $salle['commune']) . '</a>');
    }
    if ($instagram !== '') {
        // Écrit par PHP, ce lien échappe au middleware d'Astro : il pose
        // lui-même nouvel onglet, `noopener` et l'annonce de sortie. `me`
        // dit aux moteurs que ce compte appartient à la personne du profil.
        $fiche[] = fait('instagram', 'Instagram', '<a href="' . e($instagram) . '" target="_blank" rel="me noopener"'
            . ' aria-describedby="annonce-lien-sortant">@' . e((string) $membre['instagram']) . '</a>');
    }
    // La fiche d'athlète officielle, reliée par l'admin (lib/revendications.php).
    if (($membre['athlete_nom'] ?? null) !== null) {
        $fiche[] = fait('records', 'Fiche d\'athlète', '<a href="/athlete/' . e((string) $membre['athlete_slug']) . '/">' . e((string) $membre['athlete_nom'])
            . '</a> <span class="verifie">athlète vérifié</span>');
    }
    $fiche[] = fait('competitions', 'Inscription', 'Membre depuis ' . e(dateFr($inscription, false)));

    $htmlFiche = '<ul class="faits">' . implode('', $fiche) . '</ul>';

    /* --- Les chiffres, d'un coup d'œil ------------------------------- */

    $verifiees = count(array_filter($performances, static fn (array $p): bool => badgeDe($p) !== ''));
    $chiffres = $performances === [] ? '' : '<dl class="chiffres-membre">'
        . '<div><dt>' . (count($performances) > 1 ? 'barres publiées' : 'barre publiée') . '</dt><dd>' . count($performances) . '</dd></div>'
        . '<div><dt>' . ($verifiees > 1 ? 'vérifiées' : 'vérifiée') . '</dt><dd>' . $verifiees . '</dd></div>'
        . '</dl>';

    /* --- Référencement ------------------------------------------------- */

    // Un profil sans performance n'a rien à offrir à un moteur de recherche :
    // il reste hors index jusqu'à sa première barre.
    $robots = $performances !== [] ? 'index, follow' : 'noindex';

    // Sans présentation, la description dit les records du premier
    // équipement : c'est ce qu'un moteur de recherche a de plus utile à montrer.
    $records = recordsDe($performances);
    $premier = $records === [] ? null : array_key_first($records);
    $description = match (true) {
        $bio !== '' => $bio,
        $premier !== null => $pseudo . ', records en ' . mb_strtolower(EQUIPEMENTS[$premier]) . ' : '
            . implode(', ', array_map(
                static fn (string $mv, array $b): string => MOUVEMENTS[$mv] . ' ' . kgFr((float) $b['charge_kg']),
                array_keys($records[$premier]['barres']),
                $records[$premier]['barres'],
            )) . '.',
        default => $pseudo . ', membre de la communauté de force athlétique de SBD.re, à La Réunion.',
    };

    $personne = ['@type' => 'Person', 'name' => $pseudo, 'url' => $adresse];
    if ($bio !== '') {
        $personne['description'] = $bio;
    }
    if ($instagram !== '') {
        $personne['sameAs'] = [$instagram];
    }
    if (($membre['athlete_nom'] ?? null) !== null) {
        $personne['sameAs'] = [...($personne['sameAs'] ?? []), 'https://sbd.re/athlete/' . $membre['athlete_slug'] . '/'];
    }
    // JSON_HEX_TAG écrit < et > en < et > : une présentation qui
    // contiendrait « </script> » ne peut pas refermer le bloc.
    $donnees = json_encode([
        '@context'    => 'https://schema.org',
        '@type'       => 'ProfilePage',
        'url'         => $adresse,
        'dateCreated' => date('c', $inscription),
        'mainEntity'  => $personne,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);

    // strtr remplace tout en un seul passage : un repère écrit dans une
    // présentation n'est jamais remplacé à son tour.
    return strtr($gabarit, [
        '%%PSEUDO%%'       => e($pseudo),
        '%%TITRE%%'        => e($pseudo . ', records de force athlétique | SBD.re'),
        '%%DESCRIPTION%%'  => e($description),
        '%%ROBOTS%%'       => $robots,
        '%%PRESENTATION%%' => $bio !== '' ? '<p class="chapeau">' . e($bio) . '</p>' : '',
        '%%FICHE%%'        => $htmlFiche,
        '%%CHIFFRES%%'     => $chiffres,
        '%%RECORDS%%'      => rendrePerformances($performances),
        '%%SUJETS%%'       => rendreSujetsMembre($sujets),
        '%%DONNEES%%'      => (string) $donnees,
        // L'avatar, décoratif : le pseudo, en titre juste dessous, dit qui c'est.
        '%%AVATAR%%'       => ($membre['avatar_v'] ?? null) !== null
            ? '<img class="avatar-profil" src="' . e(adresseAvatar($pseudo, (int) $membre['avatar_v'])) . '" alt="" width="96" height="96">'
            : '',
        '%%MEMBRE%%'       => (string) (int) ($membre['id'] ?? 0),
        '/membres/-gabarit/' => '/membres/' . e($pseudo) . '/',
    ]);
}

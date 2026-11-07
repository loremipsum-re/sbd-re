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

/** Le lien d'une vidéo : sortant, annoncé, et `ugc nofollow` puisqu'un membre l'a écrit. */
function lienVideo(string $adresse): string
{
    return '<a href="' . e($adresse) . '" target="_blank" rel="ugc nofollow noopener"'
        . ' aria-describedby="annonce-lien-sortant">Vidéo</a>';
}

/** L'état de vérification d'une barre. Le badge communauté viendra avec la phase 6. */
function etatVerification(array $performance): string
{
    return ($performance['date_verif_admin'] ?? null) !== null
        ? '<span class="verif verif-admin">Vérifiée par SBD.re</span>'
        : '<span class="verif">Non vérifiée</span>';
}

/**
 * Les records puis la liste complète, plus récente d'abord.
 *
 * @param list<array<string, mixed>> $performances
 */
function rendrePerformances(array $performances): string
{
    if ($performances === []) {
        return '<p class="chapeau">Aucune performance publiée pour l\'instant.</p>';
    }

    $html = '';
    foreach (recordsDe($performances) as $equipement => $record) {
        $cases = '';
        foreach ($record['barres'] as $mouvement => $barre) {
            $cases .= '<div><dt>' . e(MOUVEMENTS[$mouvement]) . '</dt><dd>'
                . '<span class="charge">' . kgFr((float) $barre['charge_kg']) . '</span> '
                . lienVideo((string) $barre['lien_video']) . ' ' . etatVerification($barre)
                . '</dd></div>';
        }
        if ($record['total'] !== null) {
            $cases .= '<div class="total"><dt>Total</dt><dd><span class="charge">'
                . kgFr($record['total']) . '</span></dd></div>';
        }
        $html .= '<h3>' . e(EQUIPEMENTS[$equipement]) . '</h3><dl class="records">' . $cases . '</dl>';
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

    return $html . '<h3>Toutes les performances</h3><ul class="liste-perfs">' . $lignes . '</ul>';
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
 */
function rendreProfil(array $membre, string $gabarit, array $salles, array $performances): string
{
    $pseudo = (string) $membre['pseudo'];
    $bio = trim((string) ($membre['bio'] ?? ''));
    $inscription = strtotime((string) $membre['date_inscription']) ?: time();
    $adresse = 'https://sbd.re/membres/' . $pseudo . '/';

    $instagram = (int) $membre['instagram_visible'] === 1 && ($membre['instagram'] ?? '') !== ''
        ? 'https://www.instagram.com/' . $membre['instagram'] . '/'
        : '';
    $salle = $salles[(string) ($membre['salle'] ?? '')] ?? null;

    /* --- La fiche : salle, Instagram, ancienneté --------------------- */

    $fiche = [];
    if ($salle !== null) {
        $fiche[] = ['Salle', '<a href="/salles/">' . e($salle['nom'] . ', ' . $salle['commune']) . '</a>'];
    }
    if ($instagram !== '') {
        // Écrit par PHP, ce lien échappe au middleware d'Astro : il pose
        // lui-même nouvel onglet, `noopener` et l'annonce de sortie. `me`
        // dit aux moteurs que ce compte appartient à la personne du profil.
        $fiche[] = ['Instagram', '<a href="' . e($instagram) . '" target="_blank" rel="me noopener"'
            . ' aria-describedby="annonce-lien-sortant">@' . e((string) $membre['instagram']) . '</a>'];
    }
    $fiche[] = ['Membre depuis', e(dateFr($inscription, false))];

    $htmlFiche = '<dl class="fiche-membre">' . implode('', array_map(
        static fn (array $ligne): string => '<div><dt>' . $ligne[0] . '</dt><dd>' . $ligne[1] . '</dd></div>',
        $fiche,
    )) . '</dl>';

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
        '%%RECORDS%%'      => rendrePerformances($performances),
        '%%DONNEES%%'      => (string) $donnees,
        '/membres/-gabarit/' => '/membres/' . e($pseudo) . '/',
    ]);
}

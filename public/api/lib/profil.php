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
 */
function rendreProfil(array $membre, string $gabarit, array $salles, int $performances): string
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

    /* --- Les records, phase 5 ------------------------------------------ */

    $htmlRecords = $performances === 0
        ? '<p class="chapeau">Aucune performance publiée pour l\'instant.</p>'
        : '';

    /* --- Référencement ------------------------------------------------- */

    // Un profil sans performance n'a rien à offrir à un moteur de recherche :
    // il reste hors index jusqu'à sa première barre.
    $robots = $performances > 0 ? 'index, follow' : 'noindex';
    $description = $bio !== ''
        ? $bio
        : $pseudo . ', membre de la communauté de force athlétique de SBD.re, à La Réunion.';

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
        '%%RECORDS%%'      => $htmlRecords,
        '%%DONNEES%%'      => (string) $donnees,
        '/membres/-gabarit/' => '/membres/' . e($pseudo) . '/',
    ]);
}

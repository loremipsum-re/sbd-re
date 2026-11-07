<?php
/**
 * Les pages du forum, rendues à la demande depuis le gabarit d'Astro
 * (/forum/-gabarit/), comme les profils. Le .htaccess y mène :
 *
 *   /forum/                      l'accueil, catégories et forums
 *   /forum/<adresse>/            un forum, ses sujets, ?page=N
 *   /forum/<adresse>/<sujet>/    un sujet, ses messages, ?page=N
 *
 * La page connaît le membre connecté, pour lui offrir le formulaire : elle
 * ne se garde donc que dans le cache de son navigateur (`private`).
 */

declare(strict_types=1);

namespace SBDRE;

define('SBDRE_API', true);
require __DIR__ . '/lib/forum.php';

header('X-Content-Type-Options: nosniff');

function introuvable(): never
{
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    readfile(dirname(__DIR__) . '/404.html');
    exit;
}

function indisponible(string $journal): never
{
    error_log('SBD.re : forum indisponible, ' . $journal);
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    header('Retry-After: 300');
    echo "Le forum est momentanément indisponible. Réessayez dans quelques minutes.\n";
    exit;
}

$methode = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($methode, ['GET', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD');
    exit;
}

$adresse = isset($_GET['forum']) ? (string) $_GET['forum'] : null;
$sujetDemande = isset($_GET['sujet']) ? (string) $_GET['sujet'] : null;
$pageDemandee = (string) ($_GET['page'] ?? '1');

if (($adresse !== null && !adresseForumValide($adresse))
    || ($sujetDemande !== null && preg_match('/^[1-9][0-9]{0,18}\z/', $sujetDemande) !== 1)
    || preg_match('/^[1-9][0-9]{0,5}\z/', $pageDemandee) !== 1) {
    introuvable();
}
$page = (int) $pageDemandee;

$gabarit = @file_get_contents(dirname(__DIR__) . '/forum/-gabarit/index.html');
if ($gabarit === false) {
    indisponible('gabarit introuvable');
}
$smileys = json_decode((string) @file_get_contents(__DIR__ . '/smileys.json'), true);
$smileys = is_array($smileys) ? $smileys : [];

try {
    $membre = membreCourant();

    if ($adresse === null) {
        /* --- L'accueil ---------------------------------------------------- */
        $categories = lignes('SELECT id, titre FROM forum_categorie ORDER BY ordre, id');
        $forums = forumsDeLAccueil();
        $chemin = '/forum/';
        $page = 1;
        $reperes = [
            'TITRE'        => 'Forum',
            'TITRE_ONGLET' => 'Forum de force athlétique et d\'haltérophilie | SBD.re',
            'DESCRIPTION'  => 'Le forum de la force athlétique et de l\'haltérophilie à La Réunion : technique, programmation, compétitions.',
            'ARIANE'       => '',
            'CHAPEAU'      => '<p class="chapeau">Technique, programmation, compétitions : une catégorie par mouvement, à la manière des forums d\'autrefois.</p>',
            'CONTENU'      => rendreIndexForum($categories, $forums),
            'DONNEES'      => '',
        ];
    } else {
        $forum = ligne(
            "SELECT f.id, f.adresse, f.titre, f.description, f.etat, c.titre AS categorie
               FROM forum f JOIN forum_categorie c ON c.id = f.categorie_id
              WHERE f.adresse = :a AND f.etat <> 'masque'",
            ['a' => $adresse],
        );
        if ($forum === null) {
            introuvable();
        }
        $nomForum = $forum['categorie'] . ', ' . $forum['titre'];

        if ($sujetDemande === null) {
            /* --- Un forum ------------------------------------------------- */
            $total = (int) (ligne('SELECT COUNT(*) AS n FROM sujet WHERE forum_id = :f', ['f' => $forum['id']])['n'] ?? 0);
            $pages = max(1, (int) ceil($total / SUJETS_PAR_PAGE));
            if ($page > $pages) {
                introuvable();
            }
            $sujets = sujetsDuForum((int) $forum['id'], $page);
            $chemin = '/forum/' . $forum['adresse'] . '/';
            $reperes = [
                'TITRE'        => e($nomForum),
                'TITRE_ONGLET' => e($nomForum . ($page > 1 ? ', page ' . $page : '') . ' | Forum SBD.re'),
                'DESCRIPTION'  => e($forum['description'] !== '' ? $forum['description'] : $nomForum),
                'ARIANE'       => ariane([['Forum', '/forum/'], [$nomForum, null]]),
                'CHAPEAU'      => $forum['description'] !== '' ? '<p class="chapeau">' . e($forum['description']) . '</p>' : '',
                'CONTENU'      => rendreForum($forum, $sujets, $page, $pages, $membre),
                'DONNEES'      => '',
            ];
        } else {
            /* --- Un sujet ------------------------------------------------- */
            $sujet = ligne(
                'SELECT id, titre, verrouille, reponses FROM sujet WHERE id = :s AND forum_id = :f',
                ['s' => $sujetDemande, 'f' => $forum['id']],
            );
            if ($sujet === null) {
                introuvable();
            }
            $pages = max(1, (int) ceil(((int) $sujet['reponses'] + 1) / MESSAGES_PAR_PAGE));
            if ($page > $pages) {
                introuvable();
            }
            $messages = messagesDuSujet((int) $sujet['id'], $page);
            $abonne = $membre !== null && ligne(
                'SELECT 1 AS n FROM abonnement WHERE membre_id = :m AND sujet_id = :s',
                ['m' => $membre['id'], 's' => $sujet['id']],
            ) !== null;

            $premier = $messages[0] ?? null;
            $extrait = $premier !== null && $premier['masque_par'] === null
                ? mb_substr(trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace('~\[/?[a-z]+(=[^\]]*)?\]~', '', (string) $premier['texte']))), 0, 155)
                : (string) $sujet['titre'];
            $chemin = lienSujet((string) $forum['adresse'], (int) $sujet['id'], $page);
            $reperes = [
                'TITRE'        => e((string) $sujet['titre']),
                'TITRE_ONGLET' => e($sujet['titre'] . ($page > 1 ? ', page ' . $page : '') . ' | ' . $nomForum . ' | Forum SBD.re'),
                'DESCRIPTION'  => e($extrait),
                'ARIANE'       => ariane([['Forum', '/forum/'], [$nomForum, '/forum/' . $forum['adresse'] . '/'], [(string) $sujet['titre'], null]]),
                'CHAPEAU'      => '',
                'CONTENU'      => rendreSujet($forum, $sujet, $messages, $page, $pages, $membre, $abonne, $smileys),
                'DONNEES'      => $page === 1 && $premier !== null ? donneesSujet($sujet, $premier, (string) $forum['adresse']) : '',
            ];
        }
    }
} catch (\RuntimeException | \PDOException $e) {
    indisponible($e->getMessage());
}

header('Content-Type: text/html; charset=utf-8');
// La page porte le formulaire du membre connecté : jamais dans un cache partagé.
header('Cache-Control: private, no-cache');
header('Vary: Cookie');

if ($methode === 'HEAD') {
    exit;
}

echo remplirGabaritForum($gabarit, $reperes, $chemin);

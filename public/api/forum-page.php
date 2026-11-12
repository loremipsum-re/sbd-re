<?php
/**
 * Les pages du forum, rendues à la demande depuis le gabarit d'Astro
 * (/forum/-gabarit/), comme les profils. Le .htaccess y mène :
 *
 *   /forum/                      l'accueil, catégories et forums
 *   /forum/<adresse>/            un forum, ses sujets, ?page=N
 *   /forum/<adresse>/<sujet>/    un sujet, ses messages, ?page=N
 *   /forum/fil/, /forum/recherche/?q=   deux adresses réservées
 *
 * La page connaît le membre connecté, pour lui offrir le formulaire : elle
 * ne se garde donc que dans le cache de son navigateur (`private`).
 */

declare(strict_types=1);

namespace SBDRE;

define('SBDRE_API', true);
require_once __DIR__ . '/lib/forum.php';
require_once __DIR__ . '/lib/chat.php';

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
    // Lire le forum compte comme une présence : « Qui est en ligne » le dit.
    if ($membre !== null) {
        marquerActivite((int) $membre['id']);
    }
    $indexer = true;

    if ($adresse === 'fil') {
        /* --- Le fil, adresse réservée (ADRESSES_RESERVEES) ----------------- */
        $chemin = '/forum/fil/';
        $page = 1;
        $etapes = [['Forum', '/forum/'], ['Le fil', null]];
        $reperes = [
            'TITRE'        => 'Le fil',
            'TITRE_ONGLET' => 'Le fil du forum | SBD.re',
            'DESCRIPTION'  => e('Les nouveaux sujets, les réponses et les performances vérifiées de la communauté SBD.re.'),
            'ARIANE'       => ariane($etapes),
            'CHAPEAU'      => '<p class="chapeau">Les nouveaux sujets, les réponses et les performances vérifiées, du plus récent au plus ancien.</p>',
            'CONTENU'      => rendreFil(filActualite()),
            'DONNEES'      => donneesAriane($etapes, $chemin),
        ];
    } elseif ($adresse === 'recherche') {
        /* --- La recherche, adresse réservée, hors index ------------------- */
        $q = trim((string) ($_GET['q'] ?? ''));
        $chemin = '/forum/recherche/';
        $page = 1;
        $indexer = false;
        $reperes = [
            'TITRE'        => 'Chercher un sujet',
            'TITRE_ONGLET' => e(($q !== '' ? $q . ', ' : '') . 'recherche dans le forum | SBD.re'),
            'DESCRIPTION'  => e('Chercher un sujet dans le forum de SBD.re.'),
            'ARIANE'       => ariane([['Forum', '/forum/'], ['Recherche', null]]),
            'CHAPEAU'      => '',
            'CONTENU'      => rendreRecherche($q, rechercheValide($q) ? rechercherSujets($q) : []),
            'DONNEES'      => '',
        ];
    } elseif ($adresse === null) {
        /* --- L'accueil ---------------------------------------------------- */
        archiverForumsInactifs();
        $categories = lignes('SELECT id, titre FROM forum_categorie ORDER BY ordre, id');
        $forums = forumsDeLAccueil();
        $chemin = '/forum/';
        $page = 1;
        $reperes = [
            'TITRE'        => 'Forum',
            'TITRE_ONGLET' => e('Forum de force athlétique et d\'haltérophilie | SBD.re'),
            'DESCRIPTION'  => e("Le forum de la force athlétique et de l'haltérophilie à La Réunion : technique, programmation, compétitions."),
            'ARIANE'       => '',
            'CHAPEAU'      => '<p class="chapeau">Technique, programmation, compétitions : une catégorie par mouvement, à la manière des forums d\'autrefois.</p>',
            'CONTENU'      => '<p class="actions-forum"><a class="bouton-nouveau" href="/forum/fil/">Le fil de l\'activité</a></p>'
                . formulaireRecherche()
                . rendreSujetsRecents(sujetsRecents())
                . rendreIndexForum($categories, $forums, $membre)
                . rendrePiedForum(enLigne(), statistiquesForum()),
            'DONNEES'      => '',
        ];
    } else {
        $forum = ligne(
            "SELECT f.id, f.adresse, f.titre, f.description, f.etat, f.createur_id, c.titre AS categorie
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
            $etapes = [['Forum', '/forum/'], [$nomForum, null]];
            $reperes = [
                'TITRE'        => e($nomForum),
                'TITRE_ONGLET' => e($nomForum . ($page > 1 ? ', page ' . $page : '') . ' | Forum SBD.re'),
                'DESCRIPTION'  => e($forum['description'] !== '' ? $forum['description'] : $nomForum),
                'ARIANE'       => ariane($etapes),
                'CHAPEAU'      => $forum['description'] !== '' ? '<p class="chapeau">' . e($forum['description']) . '</p>' : '',
                'CONTENU'      => rendreForum($forum, $sujets, $page, $pages, $membre),
                'DONNEES'      => donneesAriane($etapes, $chemin),
            ];
        } else {
            /* --- Un sujet ------------------------------------------------- */
            $sujet = ligne(
                'SELECT id, membre_id, titre, epingle, verrouille, reponses, UNIX_TIMESTAMP(date_creation) AS creation_ts
                   FROM sujet WHERE id = :s AND forum_id = :f',
                ['s' => $sujetDemande, 'f' => $forum['id']],
            );
            if ($sujet === null) {
                // Un sujet déplacé par l'admin : ses anciens liens mènent à sa nouvelle adresse.
                $ailleurs = ligne(
                    "SELECT f.adresse FROM sujet s JOIN forum f ON f.id = s.forum_id WHERE s.id = :s AND f.etat <> 'masque'",
                    ['s' => $sujetDemande],
                );
                if ($ailleurs !== null) {
                    header('Location: ' . lienSujet((string) $ailleurs['adresse'], (int) $sujetDemande, $page), true, 301);
                    exit;
                }
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

            // Les messages de la page que le membre aime, pour l'état de ses boutons.
            $aimes = $membre !== null ? messagesAimes((int) $membre['id'], array_map('intval', array_column($messages, 'id'))) : [];
            $premier = $messages[0] ?? null;
            $extrait = $premier !== null && $premier['masque_par'] === null
                ? mb_substr(trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace('~\[/?[a-z]+(=[^\]]*)?\]~', '', (string) $premier['texte']))), 0, 155)
                : (string) $sujet['titre'];
            $chemin = lienSujet((string) $forum['adresse'], (int) $sujet['id'], $page);
            $etapes = [['Forum', '/forum/'], [$nomForum, '/forum/' . $forum['adresse'] . '/'], [(string) $sujet['titre'], null]];
            // L'admin déplace un sujet posté au mauvais endroit.
            $deplacer = estAdmin($membre)
                ? formulaireDeplacer(lignes(
                    "SELECT f.adresse, f.titre, c.titre AS categorie FROM forum f JOIN forum_categorie c ON c.id = f.categorie_id
                      WHERE f.etat <> 'masque' ORDER BY c.ordre, c.id, f.ordre, f.id",
                ), (int) $sujet['id'], (string) $forum['adresse'])
                : '';
            $reperes = [
                'TITRE'        => e((string) $sujet['titre']),
                'TITRE_ONGLET' => e($sujet['titre'] . ($page > 1 ? ', page ' . $page : '') . ' | ' . $nomForum . ' | Forum SBD.re'),
                'DESCRIPTION'  => e($extrait),
                'ARIANE'       => ariane($etapes),
                'CHAPEAU'      => '',
                'CONTENU'      => rendreSujet($forum, $sujet, $messages, $page, $pages, $membre, $abonne, $smileys, $aimes) . $deplacer,
                'DONNEES'      => donneesAriane($etapes, $chemin)
                    . ($page === 1 && $premier !== null ? donneesSujet($sujet, $premier, (string) $forum['adresse']) : ''),
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

echo remplirGabaritForum($gabarit, $reperes, $chemin, $indexer);

<?php
/**
 * Le forum (docs/forum.md) : formes acceptées, rendu des pages, écritures.
 *
 * LE RENDU EST PUR : un tableau de lignes et le gabarit d'Astro entrent, la
 * page sort. Il s'éprouve sans base (db/verifier-autorisation.php), comme
 * le profil. LES ÉCRITURES n'ouvrent pas de transaction : l'appelant l'ouvre,
 * et db/verifier.php les rejoue dans la sienne, sur MySQL.
 *
 * TOUT CE QU'ÉCRIT UN MEMBRE EST ÉCHAPPÉ D'ABORD, puis les balises d'époque
 * sont reconnues dans le texte déjà échappé : une balise inconnue reste du
 * texte, et aucun HTML écrit par un membre ne peut s'exécuter.
 */

declare(strict_types=1);

namespace SBDRE;

if (!defined('SBDRE_API')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/profil.php';
require_once __DIR__ . '/moderation.php';
require_once __DIR__ . '/notifications.php';

/**
 * Indexation : coupée jusqu'à l'ouverture de la communauté (comptes.md,
 * phase 9). Le forum s'éprouve d'ici là sans qu'un sujet d'essai n'entre
 * dans Google.
 */
const FORUM_OUVERT = false;

const SUJETS_PAR_PAGE = 30;
const MESSAGES_PAR_PAGE = 20;
const MESSAGE_FORUM_MAX = 5000;
const TITRE_SUJET_MIN = 5;
const TITRE_SUJET_MAX = 120;
const FREIN_FORUM_SECONDES = 15;
const SUJETS_PAR_JOUR = 5;
const MENTIONS_MAX = 5;

const MOIS_FR = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet',
    'août', 'septembre', 'octobre', 'novembre', 'décembre'];

/* --- Formes ---------------------------------------------------------- */

function titreSujetValide(string $titre): bool
{
    $n = mb_strlen($titre);

    return $n >= TITRE_SUJET_MIN && $n <= TITRE_SUJET_MAX && preg_match('/[\x00-\x1F\x7F]/', $titre) !== 1;
}

/** Les retours à la ligne passent, les autres caractères de contrôle non. */
function texteForumValide(string $texte): bool
{
    $n = mb_strlen($texte);

    return $n >= 1 && $n <= MESSAGE_FORUM_MAX && preg_match('/[\x00-\x09\x0B-\x1F\x7F]/', $texte) !== 1;
}

function adresseForumValide(string $adresse): bool
{
    return preg_match('/^[a-z0-9]+(-[a-z0-9]+)*\z/', $adresse) === 1 && strlen($adresse) <= 60;
}

/** Les pseudos cités par @pseudo, cinq au plus, sans doublon. */
function mentionsDe(string $texte): array
{
    preg_match_all('/(?<![a-z0-9._@])@([a-z0-9._]{3,30})/', $texte, $m);

    return array_slice(array_values(array_unique(array_map(
        static fn (string $p): string => rtrim($p, '.'),
        $m[1],
    ))), 0, MENTIONS_MAX);
}

/* --- Adresses et dates ------------------------------------------------ */

function lienSujet(string $adresse, int $sujetId, int $page = 1): string
{
    return '/forum/' . $adresse . '/' . $sujetId . '/' . ($page > 1 ? '?page=' . $page : '');
}

/** La page d'un message, d'après sa place dans le sujet (0 pour le premier). */
function pageDuMessage(int $position): int
{
    return intdiv($position, MESSAGES_PAR_PAGE) + 1;
}

/** « 7 novembre 2026 à 14 h 05 », à l'heure de La Réunion. */
function dateHeureFr(int $moment): string
{
    $d = (new \DateTimeImmutable('@' . $moment))->setTimezone(new \DateTimeZone('Indian/Reunion'));
    $jour = $d->format('j');

    return ($jour === '1' ? '1er' : $jour) . ' ' . MOIS_FR[(int) $d->format('n') - 1] . ' ' . $d->format('Y')
        . ' à ' . $d->format('G') . ' h ' . $d->format('i');
}

function balisageDate(int $moment): string
{
    return '<time datetime="' . date('c', $moment) . '">' . dateHeureFr($moment) . '</time>';
}

/* --- Le texte d'un message -------------------------------------------- */

/**
 * Le texte d'un message en HTML : échappé, puis smileys, [b], [i],
 * [quote=pseudo], [url], retours à la ligne.
 *
 * @param list<array{code: string, fichier: string, alt: string}> $smileys
 */
function rendreTexte(string $texte, array $smileys): string
{
    $html = e(str_replace("\r\n", "\n", $texte));

    // Un code ne devient une image que seul entre deux espaces : « 5x8) »
    // reste du texte. Même règle que src/lib/smileys.ts, pour le chat.
    $parCode = [];
    foreach ($smileys as $s) {
        $parCode[e($s['code'])] = $s;
    }
    $bouts = preg_split('/(\s+)/u', $html, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
    foreach ($bouts as &$bout) {
        if (isset($parCode[$bout])) {
            $s = $parCode[$bout];
            $bout = '<img class="smiley" src="/smileys/' . e($s['fichier']) . '.svg" alt="' . e($s['alt']) . '">';
        }
    }
    unset($bout);
    $html = implode('', $bouts);

    // Trois passages : de quoi imbriquer gras, italique et citations.
    for ($i = 0; $i < 3; $i++) {
        $html = (string) preg_replace('~\[b\](.+?)\[/b\]~su', '<strong>$1</strong>', $html);
        $html = (string) preg_replace('~\[i\](.+?)\[/i\]~su', '<em>$1</em>', $html);
        // La citation la plus intérieure d'abord : son contenu n'en ouvre pas d'autre.
        $html = (string) preg_replace_callback(
            '~\[quote(?:=([a-z0-9._]{1,30}))?\]((?:(?!\[quote).)*?)\[/quote\]~su',
            static fn (array $m): string => '<blockquote class="citation">'
                . ($m[1] !== '' ? '<p class="cite">' . $m[1] . ' a écrit :</p>' : '')
                . '<div>' . trim($m[2]) . '</div></blockquote>',
            $html,
        );
    }

    // Un lien http ou https seulement, tel quel : le texte échappé ne contient
    // plus ni guillemet ni chevron qui sortirait de l'attribut.
    $html = (string) preg_replace_callback(
        '~\[url\](https?://[^\s<>"\[\]]+)\[/url\]~iu',
        static fn (array $m): string => '<a href="' . $m[1] . '" target="_blank" rel="ugc nofollow noopener"'
            . ' aria-describedby="annonce-lien-sortant">' . $m[1] . '</a>',
        $html,
    );

    // Le retour à la ligne qui entoure une citation est déjà fait par le bloc.
    $html = (string) preg_replace(['~\n*(<blockquote)~', '~(</blockquote>)\n~'], ['$1', '$1'], $html);

    return str_replace("\n", "<br>\n", $html);
}

/* --- Les pages ---------------------------------------------------------- */

/** « 3 sujets », « 1 réponse », « aucun message ». */
function compte(int $n, string $singulier, string $pluriel): string
{
    return $n . "\u{00A0}" . ($n > 1 ? $pluriel : $singulier);
}

function pagination(string $chemin, int $page, int $pages): string
{
    if ($pages <= 1) {
        return '';
    }
    $lien = static fn (int $p): string => e($chemin . ($p > 1 ? '?page=' . $p : ''));
    $html = '<nav class="pagination" aria-label="Pages">';
    if ($page > 1) {
        $html .= '<a href="' . $lien($page - 1) . '" rel="prev">Page précédente</a>';
    }
    $html .= '<span>Page ' . $page . ' sur ' . $pages . '</span>';
    if ($page < $pages) {
        $html .= '<a href="' . $lien($page + 1) . '" rel="next">Page suivante</a>';
    }

    return $html . '</nav>';
}

function barreSmileys(): string
{
    return '<div class="barre-smileys" data-smileys></div>';
}

function aideBalises(string $id): string
{
    return '<p class="aide" id="' . $id . '">[b]gras[/b], [i]italique[/i], [quote]citation[/quote], '
        . '[url]https://…[/url]. Les smileys d\'époque et les emojis passent.</p>';
}

/** L'invitation à se connecter, ou la raison de la fermeture, à la place d'un formulaire. */
function pasDeFormulaire(?array $membre, string $geste, bool $ferme, string $raison): string
{
    if ($ferme) {
        return '<p class="etat-ferme">' . $raison . '</p>';
    }

    return $membre === null
        ? '<p class="inviter"><a href="/compte/connexion/">Connectez-vous</a> pour ' . $geste . '.</p>'
        : '';
}

/**
 * Un nombre et son unité. L'unité se lit sur téléphone, où les nombres
 * s'alignent sur une ligne ; sur écran large, l'en-tête de colonne la dit,
 * et elle reste pour les lecteurs d'écran.
 */
function nombreColonne(int $n, string $singulier, string $pluriel): string
{
    return '<span class="nombre">' . $n . '</span><span class="libelle"> ' . ($n > 1 ? $pluriel : $singulier) . '</span>';
}

/**
 * La barre d'un tableau du forum, à la manière de phpBB : son titre, puis le
 * nom des colonnes. Les colonnes sont dites aux yeux seulement, chaque
 * cellule porte son propre libellé pour les lecteurs d'écran.
 *
 * @param list<string> $colonnes
 */
function barreTableau(string $titre, string $id, array $colonnes): string
{
    $html = '<div class="barre-tableau"><h2 id="' . $id . '">' . $titre . '</h2>';
    foreach ($colonnes as $colonne) {
        $html .= '<span class="colonne" aria-hidden="true">' . $colonne . '</span>';
    }

    return $html . '</div>';
}

/** La cellule « Dernier message » : le sujet sur l'accueil, la date ailleurs, menant à la dernière page. */
function celluleDernier(?string $titre, string $lien, string $pseudo, int $moment): string
{
    $html = '<div class="dernier"><span class="libelle">Dernier message</span>';
    if ($titre !== null) {
        return $html . '<a href="' . e($lien) . '">' . e($titre) . '</a>'
            . '<span class="meta">par ' . e($pseudo) . ', ' . balisageDate($moment) . '</span></div>';
    }

    return $html . '<span class="meta">par ' . e($pseudo) . '</span>'
        . '<a href="' . e($lien) . '">' . balisageDate($moment) . '</a></div>';
}

/**
 * L'accueil du forum : une barre par catégorie, une ligne par forum, comme
 * l'index d'un phpBB. Colonnes sur écran large, cartes sur téléphone.
 *
 * @param list<array<string, mixed>> $categories id, titre
 * @param list<array<string, mixed>> $forums     categorie_id, adresse, titre, description, sujets, messages,
 *                                               dernier_id, dernier_titre, dernier_pseudo, dernier_reponses, dernier_ts
 */
function rendreIndexForum(array $categories, array $forums): string
{
    $html = '';
    foreach ($categories as $c) {
        $id = 'categorie-' . (int) $c['id'];
        $dans = array_filter($forums, static fn (array $f): bool => (int) $f['categorie_id'] === (int) $c['id']);
        $html .= '<section class="tableau-forum" aria-labelledby="' . $id . '">'
            . barreTableau(e((string) $c['titre']), $id, $dans === [] ? [] : ['Sujets', 'Messages', 'Dernier message']);
        if ($dans === []) {
            $html .= '<p class="ligne-vide">Aucun forum pour l\'instant.</p></section>';
            continue;
        }
        $html .= '<ul class="lignes-forum">';
        foreach ($dans as $f) {
            $adresse = (string) $f['adresse'];
            $dernier = $f['dernier_id'] !== null
                ? celluleDernier(
                    (string) $f['dernier_titre'],
                    lienSujet($adresse, (int) $f['dernier_id'], pageDuMessage((int) $f['dernier_reponses'])),
                    (string) $f['dernier_pseudo'],
                    (int) $f['dernier_ts'],
                )
                : '<div class="dernier"><span class="meta">Aucun message</span></div>';
            $html .= '<li class="ligne-forum"><div class="intitule">'
                . '<h3><a href="/forum/' . e($adresse) . '/">' . e((string) $f['titre']) . '</a></h3>'
                . ($f['description'] !== '' ? '<p class="description">' . e((string) $f['description']) . '</p>' : '')
                . '</div>'
                . '<p class="compte">' . nombreColonne((int) $f['sujets'], 'sujet', 'sujets') . '</p>'
                . '<p class="compte">' . nombreColonne((int) $f['messages'], 'message', 'messages') . '</p>'
                . $dernier . '</li>';
        }
        $html .= '</ul></section>';
    }

    return $html;
}

/**
 * Un forum : le bouton « Nouveau sujet » en tête, comme sur phpBB et Reddit,
 * ses sujets en lignes, épinglés d'abord, puis le formulaire.
 *
 * @param array<string, mixed>       $forum  adresse, etat
 * @param list<array<string, mixed>> $sujets id, pseudo, titre, epingle, verrouille, reponses, dernier_pseudo,
 *                                           dernier_ts, creation_ts
 */
function rendreForum(array $forum, array $sujets, int $page, int $pages, ?array $membre): string
{
    $adresse = (string) $forum['adresse'];
    $ferme = $forum['etat'] !== 'ouvert';

    $html = $membre !== null && !$ferme
        ? '<p class="actions-forum"><a class="bouton-nouveau" href="#titre-ecrire">Nouveau sujet</a></p>'
        : '';
    $html .= '<section class="tableau-forum tableau-sujets" aria-labelledby="titre-sujets">'
        . barreTableau('Sujets', 'titre-sujets', ['Réponses', 'Dernier message']);

    if ($sujets === []) {
        $html .= '<p class="ligne-vide">Aucun sujet pour l\'instant. Ouvrez le premier.</p>';
    } else {
        $html .= '<ul class="lignes-forum">';
        foreach ($sujets as $s) {
            $id = (int) $s['id'];
            $epingle = (int) $s['epingle'] === 1;
            $etiquettes = ($epingle ? '<span class="etiquette">Épinglé</span> ' : '')
                . ((int) $s['verrouille'] === 1 ? '<span class="etiquette">Fermé</span> ' : '');
            $html .= '<li class="ligne-forum' . ($epingle ? ' epingle' : '') . '"><div class="intitule">'
                . '<p class="titre-sujet">' . $etiquettes . '<a href="' . e(lienSujet($adresse, $id)) . '">' . e((string) $s['titre']) . '</a></p>'
                . '<p class="meta">par ' . e((string) $s['pseudo']) . ', ' . balisageDate((int) $s['creation_ts']) . '</p></div>'
                . '<p class="compte">' . nombreColonne((int) $s['reponses'], 'réponse', 'réponses') . '</p>'
                . celluleDernier(null, lienSujet($adresse, $id, pageDuMessage((int) $s['reponses'])), (string) $s['dernier_pseudo'], (int) $s['dernier_ts'])
                . '</li>';
        }
        $html .= '</ul>';
    }
    $html .= '</section>' . pagination('/forum/' . $adresse . '/', $page, $pages);

    $html .= '<section class="ecrire" aria-labelledby="titre-ecrire"><h2 id="titre-ecrire">Ouvrir un sujet</h2>'
        . pasDeFormulaire($membre, 'ouvrir un sujet', $ferme, 'Ce forum est fermé à l\'écriture.');
    if ($membre !== null && !$ferme) {
        $html .= '<form class="form-compte" data-forum-sujet data-forum="' . e($adresse) . '" novalidate>'
            . '<div class="champ"><label for="titre-sujet">Titre</label>'
            . '<input id="titre-sujet" name="titre" required minlength="' . TITRE_SUJET_MIN . '" maxlength="' . TITRE_SUJET_MAX . '" /></div>'
            . '<div class="champ"><label for="texte-sujet">Message</label>'
            . '<textarea id="texte-sujet" name="texte" rows="8" required maxlength="' . MESSAGE_FORUM_MAX . '" aria-describedby="aide-sujet"></textarea>'
            . barreSmileys() . aideBalises('aide-sujet') . '</div>'
            . '<div class="actions"><button type="submit">Publier le sujet</button></div>'
            . '<p class="etat-compte" data-etat role="status" aria-live="polite" hidden></p></form>';
    }

    return $html . '</section>';
}

/**
 * Un sujet : chaque message avec son auteur en colonne, comme un phpBB, son
 * numéro en lien permanent, et « Auteur du sujet » sur les messages de qui
 * l'a ouvert, comme le OP de Reddit. Puis la réponse.
 *
 * @param array<string, mixed>       $forum    adresse, etat
 * @param array<string, mixed>       $sujet    id, membre_id, titre, verrouille, reponses
 * @param list<array<string, mixed>> $messages id, membre_id, pseudo, pseudo_actuel, banni, inscription_ts,
 *                                             texte, sensible, masque_par, date_ts
 * @param list<array{code: string, fichier: string, alt: string}> $smileys
 */
function rendreSujet(array $forum, array $sujet, array $messages, int $page, int $pages, ?array $membre, bool $abonne, array $smileys): string
{
    $sujetId = (int) $sujet['id'];
    $ferme = (int) $sujet['verrouille'] === 1 || $forum['etat'] !== 'ouvert';
    $peutEcrire = $membre !== null && !$ferme;
    $auteurSujet = $sujet['membre_id'] ?? null;

    $html = '<p class="meta-sujet">' . compte((int) $sujet['reponses'], 'réponse', 'réponses');
    if ($peutEcrire) {
        $html .= ' <a class="bouton-nouveau" href="#titre-ecrire">Répondre</a>';
    }
    if ($membre !== null) {
        $html .= ' <button type="button" class="bouton-suivre" data-suivre data-sujet="' . $sujetId . '"'
            . ' aria-pressed="' . ($abonne ? 'true' : 'false') . '">Suivre ce sujet</button>';
    }
    $html .= '</p><ol class="messages-forum">';

    foreach (array_values($messages) as $i => $m) {
        $numero = ($page - 1) * MESSAGES_PAR_PAGE + $i + 1;
        $ancre = 'message-' . (int) $m['id'];
        $pseudo = (string) ($m['pseudo_actuel'] ?? $m['pseudo']);
        // Un compte effacé ou banni n'a plus de profil : son nom reste, sans lien.
        $auteur = ($m['pseudo_actuel'] ?? null) !== null && (int) $m['banni'] === 0
            ? '<a href="/membres/' . e($pseudo) . '/">' . e($pseudo) . '</a>'
            : e($pseudo);
        $op = $auteurSujet !== null && (int) ($m['membre_id'] ?? 0) === (int) $auteurSujet;

        $html .= '<li><article class="message-forum" id="' . $ancre . '">'
            . '<div class="auteur-message"><strong class="auteur">' . $auteur . '</strong>'
            . ($op ? '<span class="etiquette etiquette-op">Auteur du sujet</span>' : '')
            . (($m['inscription_ts'] ?? null) !== null ? '<span class="meta">Membre depuis ' . e(dateFr((int) $m['inscription_ts'], false)) . '</span>' : '')
            . '</div><div class="corps-message">'
            . '<header><a class="numero" href="#' . $ancre . '" aria-label="Lien vers le message ' . $numero . '">#' . $numero . '</a> '
            . balisageDate((int) $m['date_ts']) . '</header>';

        if ($m['masque_par'] !== null) {
            $html .= '<p class="aide">Message masqué par la modération.</p></div></article></li>';
            continue;
        }
        if ((int) $m['sensible'] === 1) {
            $html .= '<p class="avertissement" role="note">' . AVERTISSEMENT_DOPAGE . '</p>';
        }
        $html .= '<div class="texte">' . rendreTexte((string) $m['texte'], $smileys) . '</div>';
        if ($peutEcrire) {
            $html .= '<footer><button type="button" class="bouton-citer" data-citer data-pseudo="' . e($pseudo)
                . '" data-texte="' . e((string) $m['texte']) . '">Citer</button></footer>';
        }
        $html .= '</div></article></li>';
    }
    $html .= '</ol>' . pagination(lienSujet((string) $forum['adresse'], $sujetId), $page, $pages);

    $html .= '<section class="ecrire" aria-labelledby="titre-ecrire"><h2 id="titre-ecrire">Répondre</h2>'
        . pasDeFormulaire($membre, 'répondre', $ferme, 'Ce sujet est fermé aux réponses.');
    if ($peutEcrire) {
        $html .= '<form class="form-compte" data-forum-reponse data-sujet="' . $sujetId . '" novalidate>'
            . '<div class="champ"><label for="texte-reponse">Votre réponse</label>'
            . '<textarea id="texte-reponse" name="texte" rows="6" required maxlength="' . MESSAGE_FORUM_MAX . '" aria-describedby="aide-reponse"></textarea>'
            . barreSmileys() . aideBalises('aide-reponse') . '</div>'
            . '<div class="actions"><button type="submit">Publier la réponse</button></div>'
            . '<p class="etat-compte" data-etat role="status" aria-live="polite" hidden></p></form>';
    }

    return $html . '</section>';
}

/** Les données structurées d'un sujet, DiscussionForumPosting, pour sa première page. */
function donneesSujet(array $sujet, array $premier, string $adresse): string
{
    $donnees = json_encode([
        '@context'      => 'https://schema.org',
        '@type'         => 'DiscussionForumPosting',
        'headline'      => (string) $sujet['titre'],
        'text'          => mb_substr((string) $premier['texte'], 0, 500),
        'url'           => 'https://sbd.re' . lienSujet($adresse, (int) $sujet['id']),
        'datePublished' => date('c', (int) $premier['date_ts']),
        'author'        => ['@type' => 'Person', 'name' => (string) ($premier['pseudo_actuel'] ?? $premier['pseudo'])],
        'commentCount'  => (int) $sujet['reponses'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);

    return '<script type="application/ld+json">' . $donnees . '</script>';
}

/**
 * Remplit le gabarit. strtr remplace tout en un seul passage : un repère
 * écrit dans un message n'est jamais remplacé à son tour.
 *
 * @param array<string, string> $reperes TITRE, TITRE_ONGLET, DESCRIPTION, ARIANE, CHAPEAU, CONTENU, DONNEES
 */
function remplirGabaritForum(string $gabarit, array $reperes, string $chemin): string
{
    $table = ['/forum/-gabarit/' => $chemin, '%%ROBOTS%%' => FORUM_OUVERT ? 'index, follow' : 'noindex'];
    foreach ($reperes as $nom => $valeur) {
        $table['%%' . $nom . '%%'] = $valeur;
    }

    return strtr($gabarit, $table);
}

/** Le fil d'Ariane : Forum › Catégorie › Forum. */
function ariane(array $etapes): string
{
    $html = '<nav class="ariane" aria-label="Fil d\'Ariane"><ol>';
    foreach ($etapes as [$libelle, $lien]) {
        $html .= '<li>' . ($lien !== null ? '<a href="' . e($lien) . '">' . e($libelle) . '</a>' : e($libelle)) . '</li>';
    }

    return $html . '</ol></nav>';
}

/* --- Écritures ---------------------------------------------------------- */

function forumTropRapide(int $membreId): bool
{
    return ligne(
        'SELECT 1 AS n FROM message_forum WHERE membre_id = :m
            AND date_creation > NOW() - INTERVAL ' . FREIN_FORUM_SECONDES . ' SECOND LIMIT 1',
        ['m' => $membreId],
    ) !== null;
}

function sujetsDuJour(int $membreId): int
{
    return (int) (ligne(
        'SELECT COUNT(*) AS n FROM sujet WHERE membre_id = :m AND date_creation > NOW() - INTERVAL 1 DAY',
        ['m' => $membreId],
    )['n'] ?? 0);
}

/** Ouvre un sujet : le sujet, son premier message, l'abonnement de l'auteur. */
function creerSujet(array $membre, int $forumId, string $titre, string $texte, bool $sensible, string $ip): int
{
    executer(
        'INSERT INTO sujet (forum_id, membre_id, pseudo, titre, dernier_pseudo) VALUES (:f, :m, :p, :t, :d)',
        ['f' => $forumId, 'm' => $membre['id'], 'p' => $membre['pseudo'], 't' => $titre, 'd' => $membre['pseudo']],
    );
    $sujetId = (int) bdd()->lastInsertId();
    executer(
        'INSERT INTO message_forum (sujet_id, membre_id, pseudo, texte, ip, sensible) VALUES (:s, :m, :p, :t, :ip, :se)',
        ['s' => $sujetId, 'm' => $membre['id'], 'p' => $membre['pseudo'], 't' => $texte, 'ip' => $ip, 'se' => $sensible ? 1 : 0],
    );
    suivreSujet((int) $membre['id'], $sujetId, true);

    return $sujetId;
}

/**
 * Ajoute une réponse, met le sujet à jour, prévient les abonnés et les cités.
 * Rend le lien du nouveau message.
 *
 * @param array<string, mixed> $sujet id, titre, reponses
 */
function ajouterReponse(array $membre, array $sujet, string $adresse, string $texte, bool $sensible, string $ip): string
{
    $sujetId = (int) $sujet['id'];
    executer(
        'INSERT INTO message_forum (sujet_id, membre_id, pseudo, texte, ip, sensible) VALUES (:s, :m, :p, :t, :ip, :se)',
        ['s' => $sujetId, 'm' => $membre['id'], 'p' => $membre['pseudo'], 't' => $texte, 'ip' => $ip, 'se' => $sensible ? 1 : 0],
    );
    $messageId = (int) bdd()->lastInsertId();
    executer(
        'UPDATE sujet SET reponses = reponses + 1, date_dernier = NOW(), dernier_pseudo = :p WHERE id = :s',
        ['p' => $membre['pseudo'], 's' => $sujetId],
    );

    // Le premier message est en place 0 : la réponse n° N est en place N.
    $lien = lienSujet($adresse, $sujetId, pageDuMessage((int) $sujet['reponses'] + 1)) . '#message-' . $messageId;
    $titre = (string) $sujet['titre'];
    notifierAbonnes($sujetId, (int) $membre['id'], $membre['pseudo'] . ' a répondu à « ' . $titre . ' ».', $lien);
    notifierMentions(mentionsDe($texte), $sujetId, (int) $membre['id'], $membre['pseudo'] . ' vous cite dans « ' . $titre . ' ».', $lien);

    return $lien;
}

function suivreSujet(int $membreId, int $sujetId, bool $suivre): void
{
    $suivre
        ? executer(
            'INSERT INTO abonnement (membre_id, sujet_id) VALUES (:m, :s) ON DUPLICATE KEY UPDATE membre_id = membre_id',
            ['m' => $membreId, 's' => $sujetId],
        )
        : executer('DELETE FROM abonnement WHERE membre_id = :m AND sujet_id = :s', ['m' => $membreId, 's' => $sujetId]);
}

/* --- Lectures, partagées par forum-page.php et db/verifier.php --------- */

/**
 * Les forums visibles, leur nombre de sujets et de messages, et leur sujet
 * le plus récemment actif, pour la colonne « Dernier message ». `d` en tient
 * une ligne au plus par forum : les comptes ne sont pas multipliés.
 * ANY_VALUE : ses colonnes ne dépendent pas de `f.id` aux yeux de MySQL en
 * mode ONLY_FULL_GROUP_BY, bien qu'elles n'aient qu'une valeur.
 */
function forumsDeLAccueil(): array
{
    return lignes(
        "SELECT f.categorie_id, f.adresse, f.titre, f.description,
                COUNT(s.id) AS sujets,
                COALESCE(SUM(s.reponses + 1), 0) AS messages,
                ANY_VALUE(d.id) AS dernier_id,
                ANY_VALUE(d.titre) AS dernier_titre,
                ANY_VALUE(d.dernier_pseudo) AS dernier_pseudo,
                ANY_VALUE(d.reponses) AS dernier_reponses,
                UNIX_TIMESTAMP(ANY_VALUE(d.date_dernier)) AS dernier_ts
           FROM forum f
           LEFT JOIN sujet s ON s.forum_id = f.id
           LEFT JOIN sujet d ON d.id = (
                SELECT s2.id FROM sujet s2 WHERE s2.forum_id = f.id
                 ORDER BY s2.date_dernier DESC, s2.id DESC LIMIT 1)
          WHERE f.etat <> 'masque'
          GROUP BY f.id
          ORDER BY f.ordre, f.id",
    );
}

/** Une page de sujets, épinglés d'abord, puis les plus actifs. */
function sujetsDuForum(int $forumId, int $page): array
{
    return lignes(
        'SELECT id, pseudo, titre, epingle, verrouille, reponses, dernier_pseudo,
                UNIX_TIMESTAMP(date_dernier) AS dernier_ts, UNIX_TIMESTAMP(date_creation) AS creation_ts
           FROM sujet WHERE forum_id = :f
          ORDER BY epingle DESC, date_dernier DESC, id DESC
          LIMIT ' . SUJETS_PAR_PAGE . ' OFFSET ' . (($page - 1) * SUJETS_PAR_PAGE),
        ['f' => $forumId],
    );
}

/** Une page de messages, dans l'ordre, avec le pseudo actuel de l'auteur. */
function messagesDuSujet(int $sujetId, int $page): array
{
    return lignes(
        'SELECT mf.id, mf.membre_id, mf.pseudo, mf.texte, mf.sensible, mf.masque_par,
                UNIX_TIMESTAMP(mf.date_creation) AS date_ts, m.pseudo AS pseudo_actuel, m.banni,
                UNIX_TIMESTAMP(m.date_inscription) AS inscription_ts
           FROM message_forum mf
           LEFT JOIN membre m ON m.id = mf.membre_id
          WHERE mf.sujet_id = :s
          ORDER BY mf.id
          LIMIT ' . MESSAGES_PAR_PAGE . ' OFFSET ' . (($page - 1) * MESSAGES_PAR_PAGE),
        ['s' => $sujetId],
    );
}

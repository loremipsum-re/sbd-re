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
require_once __DIR__ . '/avatar.php';

/**
 * Indexation : ouverte avec la communauté (comptes.md, phase 9). À false,
 * toutes les pages du forum passent en noindex.
 */
const FORUM_OUVERT = true;

const SUJETS_PAR_PAGE = 30;
const MESSAGES_PAR_PAGE = 20;
const MESSAGE_FORUM_MAX = 5000;
const TITRE_SUJET_MIN = 5;
const TITRE_SUJET_MAX = 120;
const FREIN_FORUM_SECONDES = 15;
const SUJETS_PAR_JOUR = 5;
const MENTIONS_MAX = 5;
/** Un message se corrige dans les 24 heures : au-delà, les réponses s'appuient sur lui. */
const MODIFICATION_HEURES = 24;

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

/** Le dernier message d'un sujet : sa page, et l'ancre que rendreSujet pose dessus. */
function lienDernier(string $adresse, int $sujetId, int $reponses): string
{
    return lienSujet($adresse, $sujetId, pageDuMessage($reponses)) . '#dernier';
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

/**
 * Les numéros de page à montrer, comme sur phpBB : la première, la dernière,
 * deux de chaque côté de la page en cours, et 0 pour chaque trou (« … »).
 *
 * @return list<int>
 */
function numerosDePage(int $page, int $pages): array
{
    $numeros = [];
    for ($p = 1; $p <= $pages; $p++) {
        if ($p === 1 || $p === $pages || abs($p - $page) <= 2) {
            if ($numeros !== [] && $p - end($numeros) > 1) {
                $numeros[] = 0;
            }
            $numeros[] = $p;
        }
    }

    return $numeros;
}

function pagination(string $chemin, int $page, int $pages): string
{
    if ($pages <= 1) {
        return '';
    }
    $lien = static fn (int $p): string => e($chemin . ($p > 1 ? '?page=' . $p : ''));
    $html = '<nav class="pagination" aria-label="Pages">';
    if ($page > 1) {
        $html .= '<a href="' . $lien($page - 1) . '" rel="prev">Précédente</a>';
    }
    $html .= '<ol>';
    foreach (numerosDePage($page, $pages) as $p) {
        $html .= '<li>' . match (true) {
            $p === 0 => '<span class="trou" aria-hidden="true">…</span>',
            $p === $page => '<span class="courante" aria-current="page">' . $p . '</span>',
            default => '<a href="' . $lien($p) . '" aria-label="Page ' . $p . '">' . $p . '</a>',
        } . '</li>';
    }
    $html .= '</ol>';
    if ($page < $pages) {
        $html .= '<a href="' . $lien($page + 1) . '" rel="next">Suivante</a>';
    }

    return $html . '</nav>';
}

function barreSmileys(): string
{
    return '<div class="barre-smileys" data-smileys></div>';
}

/** Gras, Italique, Citation, Lien au-dessus du champ, comme sur phpBB. Posés par lib/barre-smileys.ts. */
function barreBalises(): string
{
    return '<div class="barre-balises" data-balises></div>';
}

/** Publier, et « Aperçu », qui montre le texte rendu par le serveur tel qu'il paraîtra. */
function boutonsEnvoi(string $libelle): string
{
    return '<div class="apercu" data-zone-apercu hidden></div>'
        . '<div class="actions"><button type="submit">' . $libelle . '</button>'
        . '<button type="button" class="secondaire" data-apercu>Aperçu</button></div>';
}

function aideBalises(string $id): string
{
    return '<p class="aide" id="' . $id . '">[b]gras[/b], [i]italique[/i], [quote]citation[/quote], '
        . '[url]https://…[/url]. Les smileys d\'époque et les emojis passent. Ctrl + Entrée publie, '
        . 'et le brouillon se garde sur cet appareil.</p>';
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
function rendreIndexForum(array $categories, array $forums, ?array $membre = null): string
{
    $html = '';
    foreach ($categories as $c) {
        $id = 'categorie-' . (int) $c['id'];
        $dans = array_filter($forums, static fn (array $f): bool => (int) $f['categorie_id'] === (int) $c['id']);
        // La catégorie des membres porte, sous ses forums, de quoi en créer un.
        $pied = (int) $c['id'] === CATEGORIE_MEMBRES ? formulaireCreerForum($membre) : '';
        $html .= '<section class="tableau-forum" aria-labelledby="' . $id . '">'
            . barreTableau(e((string) $c['titre']), $id, $dans === [] ? [] : ['Sujets', 'Messages', 'Dernier message']);
        if ($dans === []) {
            $html .= '<p class="ligne-vide">Aucun forum pour l\'instant.</p>' . $pied . '</section>';
            continue;
        }
        $html .= '<ul class="lignes-forum">';
        foreach ($dans as $f) {
            $adresse = (string) $f['adresse'];
            $dernier = $f['dernier_id'] !== null
                ? celluleDernier(
                    (string) $f['dernier_titre'],
                    lienDernier($adresse, (int) $f['dernier_id'], (int) $f['dernier_reponses']),
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
        $html .= '</ul>' . $pied . '</section>';
    }

    return $html;
}

/**
 * Une ligne de sujet, dans un forum ou dans les résultats d'une recherche,
 * où `$dans` nomme son forum.
 *
 * @param array<string, mixed> $s id, pseudo, titre, epingle?, verrouille?, reponses, dernier_pseudo, dernier_ts, creation_ts
 */
function ligneSujet(array $s, string $adresse, string $dans = ''): string
{
    $id = (int) $s['id'];
    $epingle = (int) ($s['epingle'] ?? 0) === 1;
    $etiquettes = ($epingle ? '<span class="etiquette">Épinglé</span> ' : '')
        . ((int) ($s['verrouille'] ?? 0) === 1 ? '<span class="etiquette">Fermé</span> ' : '');

    return '<li class="ligne-forum' . ($epingle ? ' epingle' : '') . '" data-sujet-id="' . $id . '" data-reponses="'
        . (int) $s['reponses'] . '" data-creation="' . (int) $s['creation_ts'] . '" data-adresse="' . e($adresse) . '"><div class="intitule">'
        . '<p class="titre-sujet">' . $etiquettes . '<a href="' . e(lienSujet($adresse, $id)) . '">' . e((string) $s['titre']) . '</a></p>'
        . '<p class="meta">' . ($dans !== '' ? 'dans ' . e($dans) . ', ' : '') . 'par ' . e((string) $s['pseudo']) . ', '
        . balisageDate((int) $s['creation_ts']) . '</p></div>'
        . '<p class="compte">' . nombreColonne((int) $s['reponses'], 'réponse', 'réponses') . '</p>'
        . celluleDernier(null, lienDernier($adresse, $id, (int) $s['reponses']), (string) $s['dernier_pseudo'], (int) $s['dernier_ts'])
        . '</li>';
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
        $html .= '<ul class="lignes-forum">'
            . implode('', array_map(static fn (array $s): string => ligneSujet($s, $adresse), $sujets))
            . '</ul>';
    }
    $html .= '</section>' . pagination('/forum/' . $adresse . '/', $page, $pages);

    $html .= '<section class="ecrire" aria-labelledby="titre-ecrire"><h2 id="titre-ecrire">Ouvrir un sujet</h2>'
        . pasDeFormulaire($membre, 'ouvrir un sujet', $ferme, 'Ce forum est fermé à l\'écriture.');
    if ($membre !== null && !$ferme) {
        $html .= '<form class="form-compte" data-forum-sujet data-forum="' . e($adresse) . '" novalidate>'
            . '<div class="champ"><label for="titre-sujet">Titre</label>'
            . '<input id="titre-sujet" name="titre" required minlength="' . TITRE_SUJET_MIN . '" maxlength="' . TITRE_SUJET_MAX . '" /></div>'
            . '<div class="champ"><label for="texte-sujet">Message</label>' . barreBalises()
            . '<textarea id="texte-sujet" name="texte" rows="8" required maxlength="' . MESSAGE_FORUM_MAX . '" aria-describedby="aide-sujet"></textarea>'
            . barreSmileys() . aideBalises('aide-sujet') . '</div>'
            . boutonsEnvoi('Publier le sujet')
            . '<p class="etat-compte" data-etat role="status" aria-live="polite" hidden></p></form>';
    }
    $html .= '</section>';

    // Renommer : le créateur d'un forum de membre, et l'admin. L'adresse ne
    // change pas, pour qu'aucun lien déjà partagé ne casse.
    if (peutGererForum($membre, $forum)) {
        $html .= '<details class="repli gerer-forum"><summary>Renommer le forum</summary>'
            . '<form class="form-compte" data-forum-renommer data-forum="' . e($adresse) . '" novalidate>'
            . '<p class="aide">Le nom et la description changent, l\'adresse de la page reste la même.</p>'
            . '<div class="champ"><label for="renommer-titre">Nom du forum</label>'
            . '<input id="renommer-titre" name="titre" required minlength="' . TITRE_FORUM_MIN . '" maxlength="' . TITRE_FORUM_MAX
            . '" value="' . e((string) ($forum['titre'] ?? '')) . '" /></div>'
            . '<div class="champ"><label for="renommer-description">Description</label>'
            . '<input id="renommer-description" name="description" maxlength="' . DESCRIPTION_FORUM_MAX
            . '" value="' . e((string) ($forum['description'] ?? '')) . '" /></div>'
            . '<div class="actions"><button type="submit">Renommer</button></div>'
            . '<p class="etat-compte" data-etat role="status" aria-live="polite" hidden></p></form></details>';
    }

    return $html;
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
 * @param list<int>                  $aimes    les messages de la page que le membre aime
 */
function rendreSujet(array $forum, array $sujet, array $messages, int $page, int $pages, ?array $membre, bool $abonne, array $smileys, array $aimes = []): string
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
    // Le titre se corrige comme un message : son auteur, 24 heures, sujet ouvert.
    if ($peutEcrire && peutModifierTitre($membre, $sujet, time())) {
        $html .= ' <button type="button" class="bouton-citer" data-modifier-titre data-sujet="' . $sujetId
            . '" data-titre="' . e((string) $sujet['titre']) . '">Modifier le titre</button>';
    }
    // Le créateur d'un forum de membre, et l'admin, épinglent et ferment.
    if (peutGererForum($membre, $forum)) {
        $epingle = (int) ($sujet['epingle'] ?? 0) === 1;
        $html .= ' <button type="button" class="bouton-citer" data-gerer="epingler" data-sujet="' . $sujetId . '" data-valeur="'
            . ($epingle ? '0' : '1') . '">' . ($epingle ? 'Désépingler' : 'Épingler') . '</button>'
            . ' <button type="button" class="bouton-citer" data-gerer="verrouiller" data-sujet="' . $sujetId . '" data-valeur="'
            . ((int) $sujet['verrouille'] === 1 ? '0' : '1') . '">' . ((int) $sujet['verrouille'] === 1 ? 'Rouvrir' : 'Fermer') . '</button>';
    }
    // La place du dernier message de la page, la première valant 0 : le
    // navigateur retient jusqu'où le lecteur est allé.
    $vu = ($page - 1) * MESSAGES_PAR_PAGE + count($messages) - 1;
    $html .= '</p><ol class="messages-forum" data-sujet-id="' . $sujetId . '" data-vu="' . max(0, $vu) . '">';

    $messages = array_values($messages);
    foreach ($messages as $i => $m) {
        $numero = ($page - 1) * MESSAGES_PAR_PAGE + $i + 1;
        // « Dernier message », partout sur le forum, mène ici (lienDernier).
        $dernier = $page === $pages && $i === count($messages) - 1 ? ' id="dernier"' : '';
        $ancre = 'message-' . (int) $m['id'];
        $pseudo = (string) ($m['pseudo_actuel'] ?? $m['pseudo']);
        // Un compte effacé ou banni n'a plus de profil : son nom reste, sans lien.
        $auteur = ($m['pseudo_actuel'] ?? null) !== null && (int) $m['banni'] === 0
            ? '<a href="/membres/' . e($pseudo) . '/">' . e($pseudo) . '</a>'
            : e($pseudo);
        $op = $auteurSujet !== null && (int) ($m['membre_id'] ?? 0) === (int) $auteurSujet;
        $visible = ($m['pseudo_actuel'] ?? null) !== null && (int) $m['banni'] === 0;
        // L'avatar est décoratif : le pseudo, juste à côté, dit qui écrit.
        $avatar = $visible && ($m['avatar_v'] ?? null) !== null
            ? '<img class="avatar" src="' . e(adresseAvatar($pseudo, (int) $m['avatar_v'])) . '" alt="" width="64" height="64" loading="lazy">'
            : '';

        $html .= '<li' . $dernier . '><article class="message-forum" id="' . $ancre . '">'
            . '<div class="auteur-message">' . $avatar . '<strong class="auteur">' . $auteur . '</strong>'
            . badgeRangHtml($visible ? ($m['role'] ?? null) : null)
            . ($op ? '<span class="etiquette etiquette-op">Auteur du sujet</span>' : '')
            . (($m['inscription_ts'] ?? null) !== null ? '<span class="meta">Membre depuis ' . e(dateFr((int) $m['inscription_ts'], false)) . '</span>' : '')
            . '</div><div class="corps-message">'
            . '<header><a class="numero" id="n' . $numero . '" href="#' . $ancre . '" aria-label="Lien vers le message ' . $numero . '">#' . $numero . '</a> '
            . balisageDate((int) $m['date_ts'])
            . (($m['modif_ts'] ?? null) !== null ? ' <span class="modifie">modifié le ' . balisageDate((int) $m['modif_ts']) . '</span>' : '')
            . '</header>';

        if ($m['masque_par'] !== null) {
            $html .= '<p class="aide">Message masqué par la modération.</p></div></article></li>';
            continue;
        }
        if ((int) $m['sensible'] === 1) {
            $html .= '<p class="avertissement" role="note">' . AVERTISSEMENT_DOPAGE . '</p>';
        }
        $html .= '<div class="texte">' . rendreTexte((string) $m['texte'], $smileys) . '</div>';
        // Citer qui veut répondre ; modifier le sien, 24 heures ; signaler ce qu'un autre a écrit.
        $gestes = ($peutEcrire ? '<button type="button" class="bouton-citer" data-citer data-pseudo="' . e($pseudo)
                . '" data-texte="' . e((string) $m['texte']) . '">Citer</button>' : '')
            . ($peutEcrire && peutModifierMessage($membre, $m, time())
                ? '<button type="button" class="bouton-citer" data-modifier data-message="' . (int) $m['id']
                    . '" data-texte="' . e((string) $m['texte']) . '">Modifier</button>'
                : '')
            . ($membre !== null && (int) ($m['membre_id'] ?? 0) !== (int) $membre['id']
                ? '<button type="button" class="bouton-signaler" data-signaler data-type="message_forum" data-cible="'
                    . (int) $m['id'] . '">Signaler</button>'
                : '');
        $gestes = boutonAime($m, $membre, in_array((int) $m['id'], $aimes, true)) . $gestes;
        if ($gestes !== '') {
            $html .= '<footer>' . $gestes . '</footer>';
        }
        $html .= '</div></article></li>';
    }
    $html .= '</ol>' . pagination(lienSujet((string) $forum['adresse'], $sujetId), $page, $pages);

    $html .= '<section class="ecrire" aria-labelledby="titre-ecrire"><h2 id="titre-ecrire">Répondre</h2>'
        . pasDeFormulaire($membre, 'répondre', $ferme, 'Ce sujet est fermé aux réponses.');
    if ($peutEcrire) {
        $html .= '<form class="form-compte" data-forum-reponse data-sujet="' . $sujetId . '" novalidate>'
            . '<div class="champ"><label for="texte-reponse">Votre réponse</label>' . barreBalises()
            . '<textarea id="texte-reponse" name="texte" rows="6" required maxlength="' . MESSAGE_FORUM_MAX . '" aria-describedby="aide-reponse"></textarea>'
            . barreSmileys() . aideBalises('aide-reponse') . '</div>'
            . boutonsEnvoi('Publier la réponse')
            . '<p class="etat-compte" data-etat role="status" aria-live="polite" hidden></p></form>';
    }

    return $html . '</section>';
}

/**
 * Le badge « Admin » ou « Modo » à côté d'un pseudo, au forum ; rien pour
 * les autres rangs, qui s'affichent « membre » (lib/autorisation.php).
 */
function badgeRangHtml(?string $role): string
{
    $badge = badgeRang($role);

    return $badge === '' ? '' : '<span class="rang rang-' . e((string) $role) . '">' . e($badge) . '</span>';
}

/**
 * « J'aime » : un bouton pour un membre sur le message d'un autre, le compte
 * seul pour son propre message et pour un visiteur, rien à zéro.
 */
function boutonAime(array $m, ?array $membre, bool $aime): string
{
    $n = (int) ($m['aime'] ?? 0);
    $nombre = '<span class="nombre-aime">' . ($n > 0 ? ' · ' . $n : '') . '</span>';
    if (estActif($membre) && ($m['membre_id'] ?? null) !== null && (int) $m['membre_id'] !== (int) $membre['id']) {
        return '<button type="button" class="bouton-aimer" data-aimer data-message="' . (int) $m['id'] . '" aria-pressed="'
            . ($aime ? 'true' : 'false') . '">J\'aime' . $nombre . '</button>';
    }

    return $n > 0 ? '<span class="aime">' . compte($n, 'membre aime', 'membres aiment') . ' ce message</span>' : '';
}

/** Aimer un message, ou ne plus l'aimer. Rend le nouveau compte. */
function aimerMessage(int $membreId, int $messageId, bool $aime): int
{
    $aime
        ? executer(
            'INSERT INTO appreciation (membre_id, message_id) VALUES (:m, :id) ON DUPLICATE KEY UPDATE membre_id = membre_id',
            ['m' => $membreId, 'id' => $messageId],
        )
        : executer('DELETE FROM appreciation WHERE membre_id = :m AND message_id = :id', ['m' => $membreId, 'id' => $messageId]);

    return (int) (ligne(
        'SELECT COUNT(*) AS n FROM appreciation ap JOIN membre ma ON ma.id = ap.membre_id AND ma.banni = 0 WHERE ap.message_id = :id',
        ['id' => $messageId],
    )['n'] ?? 0);
}

/**
 * Parmi ces messages, ceux que le membre aime.
 *
 * @param list<int> $ids
 * @return list<int>
 */
function messagesAimes(int $membreId, array $ids): array
{
    if ($ids === []) {
        return [];
    }
    $params = ['m' => $membreId];
    $noms = [];
    foreach (array_values($ids) as $i => $id) {
        $params["i$i"] = (int) $id;
        $noms[] = ":i$i";
    }

    return array_map('intval', array_column(lignes(
        'SELECT message_id FROM appreciation WHERE membre_id = :m AND message_id IN (' . implode(', ', $noms) . ')',
        $params,
    ), 'message_id'));
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
function remplirGabaritForum(string $gabarit, array $reperes, string $chemin, bool $indexer = true): string
{
    $table = ['/forum/-gabarit/' => $chemin, '%%ROBOTS%%' => FORUM_OUVERT && $indexer ? 'index, follow' : 'noindex'];
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

/**
 * Le même fil d'Ariane, en données structurées BreadcrumbList : Google
 * l'affiche à la place de l'adresse dans ses résultats. La dernière étape
 * est la page elle-même.
 *
 * @param list<array{0: string, 1: ?string}> $etapes
 */
function donneesAriane(array $etapes, string $chemin): string
{
    $elements = [];
    foreach (array_values($etapes) as $i => [$libelle, $lien]) {
        $elements[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $libelle, 'item' => 'https://sbd.re' . ($lien ?? $chemin)];
    }

    return '<script type="application/ld+json">' . json_encode(
        ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $elements],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP,
    ) . '</script>';
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
    oublierAdressesIp();
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
    oublierAdressesIp();
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

/**
 * Son propre message, visible, dans les 24 heures. Le sujet fermé l'interdit
 * aussi : l'appelant le contrôle, il connaît le sujet.
 *
 * @param array<string, mixed> $message membre_id, masque_par, date_ts
 */
function peutModifierMessage(?array $membre, array $message, int $maintenant): bool
{
    return estActif($membre)
        && ($message['membre_id'] ?? null) !== null
        && (int) $message['membre_id'] === (int) $membre['id']
        && ($message['masque_par'] ?? null) === null
        && $maintenant - (int) $message['date_ts'] < MODIFICATION_HEURES * 3600;
}

/** Le titre suit la règle de son premier message : son auteur, 24 heures après l'ouverture. */
function peutModifierTitre(?array $membre, array $sujet, int $maintenant): bool
{
    return peutModifierMessage(
        $membre,
        ['membre_id' => $sujet['membre_id'] ?? null, 'masque_par' => null, 'date_ts' => (int) ($sujet['creation_ts'] ?? 0)],
        $maintenant,
    );
}

/** Le nouveau titre. S'il aborde le dopage, le premier message reçoit l'avertissement. */
function renommerSujet(int $sujetId, string $titre, bool $sensible): void
{
    executer('UPDATE sujet SET titre = :t WHERE id = :s', ['t' => $titre, 's' => $sujetId]);
    if ($sensible) {
        executer('UPDATE message_forum SET sensible = 1 WHERE sujet_id = :s ORDER BY id LIMIT 1', ['s' => $sujetId]);
    }
}

/** Le sujet change de forum, l'admin seul. Ses anciennes adresses renvoient à la nouvelle (forum-page.php). */
function deplacerSujet(int $sujetId, int $forumId): void
{
    executer('UPDATE sujet SET forum_id = :f WHERE id = :s', ['f' => $forumId, 's' => $sujetId]);
}

/** Le nouveau texte, son repérage du dopage refait, et la date qui le dit. Les cités ne sont pas prévenus à nouveau. */
function modifierMessage(int $messageId, string $texte, bool $sensible): void
{
    executer(
        'UPDATE message_forum SET texte = :t, sensible = :s, date_modification = NOW() WHERE id = :id',
        ['t' => $texte, 's' => $sensible ? 1 : 0, 'id' => $messageId],
    );
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
                UNIX_TIMESTAMP(mf.date_creation) AS date_ts, UNIX_TIMESTAMP(mf.date_modification) AS modif_ts,
                m.pseudo AS pseudo_actuel, m.banni, m.role,
                UNIX_TIMESTAMP(m.date_inscription) AS inscription_ts, UNIX_TIMESTAMP(a.date_maj) AS avatar_v,
                (SELECT COUNT(*) FROM appreciation ap JOIN membre ma ON ma.id = ap.membre_id AND ma.banni = 0
                  WHERE ap.message_id = mf.id) AS aime
           FROM message_forum mf
           LEFT JOIN membre m ON m.id = mf.membre_id
           LEFT JOIN avatar a ON a.membre_id = mf.membre_id
          WHERE mf.sujet_id = :s
          ORDER BY mf.id
          LIMIT ' . MESSAGES_PAR_PAGE . ' OFFSET ' . (($page - 1) * MESSAGES_PAR_PAGE),
        ['s' => $sujetId],
    );
}

/**
 * Une liste de sujets pour /compte/ et le profil, les plus récemment actifs
 * d'abord. Le lien mène au dernier message. `$jointure` et `$condition`
 * viennent du code, jamais de la requête.
 *
 * @return list<array{id: int, titre: string, forum: string, lien: string, reponses: int, dernier_pseudo: string, dernier: string}>
 */
function listeSujets(string $jointure, string $condition, int $membreId, int $nombre): array
{
    return array_map(
        static fn (array $s): array => [
            'id'             => (int) $s['id'],
            'titre'          => (string) $s['titre'],
            'forum'          => (string) $s['forum'],
            'lien'           => lienDernier((string) $s['adresse'], (int) $s['id'], (int) $s['reponses']),
            'reponses'       => (int) $s['reponses'],
            'dernier_pseudo' => (string) $s['dernier_pseudo'],
            'dernier'        => dateHeureFr((int) $s['dernier_ts']),
        ],
        lignes(
            "SELECT s.id, s.titre, s.reponses, s.dernier_pseudo, UNIX_TIMESTAMP(s.date_dernier) AS dernier_ts,
                    f.adresse, f.titre AS forum
               FROM sujet s JOIN forum f ON f.id = s.forum_id $jointure
              WHERE $condition AND f.etat <> 'masque'
              ORDER BY s.date_dernier DESC, s.id DESC LIMIT $nombre",
            ['m' => $membreId],
        ),
    );
}

/** Les sujets qu'un membre a ouverts : « Mes sujets » de /compte/, vingt ; son profil public, dix. */
function sujetsDuMembre(int $membreId, int $nombre = 20): array
{
    return listeSujets('', 's.membre_id = :m', $membreId, $nombre);
}

/** Les sujets qu'il suit, ouverts par lui ou non : « Sujets suivis » de /compte/. */
function sujetsSuivis(int $membreId): array
{
    return listeSujets('JOIN abonnement a ON a.sujet_id = s.id', 'a.membre_id = :m', $membreId, 30);
}

/* --- La recherche, l'admin, le pied de l'accueil ----------------------- */

const RECHERCHE_MIN = 3;
const RECHERCHE_MAX = 80;

function rechercheValide(string $q): bool
{
    $n = mb_strlen($q);

    return $n >= RECHERCHE_MIN && $n <= RECHERCHE_MAX && preg_match('/[\x00-\x1F\x7F]/', $q) !== 1;
}

/**
 * Les sujets dont le titre contient `$q`, sans tenir compte des accents ni
 * des majuscules (interclassement utf8mb4_unicode_ci). % et _ sont échappés :
 * ils se cherchent comme des lettres.
 */
function rechercherSujets(string $q): array
{
    return lignes(
        "SELECT s.id, s.titre, s.pseudo, s.reponses, s.dernier_pseudo, UNIX_TIMESTAMP(s.date_dernier) AS dernier_ts,
                UNIX_TIMESTAMP(s.date_creation) AS creation_ts, f.adresse, f.titre AS forum
           FROM sujet s JOIN forum f ON f.id = s.forum_id
          WHERE f.etat <> 'masque' AND s.titre LIKE :q
          ORDER BY s.date_dernier DESC, s.id DESC LIMIT 50",
        ['q' => '%' . addcslashes($q, '%_\\') . '%'],
    );
}

function formulaireRecherche(string $q = ''): string
{
    return '<form class="form-compte recherche-forum" action="/forum/recherche/" method="get" role="search">'
        . '<label for="recherche-forum">Chercher dans les titres des sujets</label>'
        . '<div class="ligne-recherche"><input id="recherche-forum" name="q" type="search" required minlength="' . RECHERCHE_MIN
        . '" maxlength="' . RECHERCHE_MAX . '" value="' . e($q) . '" /><button type="submit">Chercher</button></div></form>';
}

/** La page /forum/recherche/ : le formulaire, puis les sujets trouvés. */
function rendreRecherche(string $q, array $resultats): string
{
    $html = formulaireRecherche($q);
    if ($q === '') {
        return $html;
    }
    if (!rechercheValide($q)) {
        return $html . '<p class="aide">Une recherche tient en ' . RECHERCHE_MIN . ' à ' . RECHERCHE_MAX . ' caractères.</p>';
    }
    $titre = count($resultats) === 0 ? 'Aucun sujet trouvé'
        : compte(count($resultats), 'sujet trouvé', 'sujets trouvés') . (count($resultats) === 50 ? ', les plus récents' : '');
    $html .= '<section class="tableau-forum tableau-sujets" aria-labelledby="titre-resultats">'
        . barreTableau($titre, 'titre-resultats', $resultats === [] ? [] : ['Réponses', 'Dernier message']);
    if ($resultats === []) {
        return $html . '<p class="ligne-vide">Essayez un mot plus court, ou parcourez <a href="/forum/">les forums</a>.</p></section>';
    }

    return $html . '<ul class="lignes-forum">'
        . implode('', array_map(static fn (array $s): string => ligneSujet($s, (string) $s['adresse'], (string) $s['forum']), $resultats))
        . '</ul></section>';
}

/**
 * Déplacer un sujet, pour l'admin : les forums visibles, rangés par catégorie.
 *
 * @param list<array<string, mixed>> $forums adresse, titre, categorie
 */
function formulaireDeplacer(array $forums, int $sujetId, string $adresseActuelle): string
{
    $groupes = [];
    foreach ($forums as $f) {
        $groupes[(string) $f['categorie']][] = $f;
    }
    $options = '';
    foreach ($groupes as $categorie => $liste) {
        $options .= '<optgroup label="' . e($categorie) . '">';
        foreach ($liste as $f) {
            $options .= '<option value="' . e((string) $f['adresse']) . '"' . ($f['adresse'] === $adresseActuelle ? ' selected' : '')
                . '>' . e((string) $f['titre']) . '</option>';
        }
        $options .= '</optgroup>';
    }

    return '<details class="repli gerer-forum"><summary>Déplacer le sujet</summary>'
        . '<form class="form-compte" data-deplacer data-sujet="' . $sujetId . '" novalidate>'
        . '<div class="champ"><label for="deplacer-forum">Vers le forum</label><select id="deplacer-forum" name="forum">' . $options . '</select></div>'
        . '<div class="actions"><button type="submit">Déplacer</button></div>'
        . '<p class="etat-compte" data-etat role="status" aria-live="polite" hidden></p></form></details>';
}

/**
 * Les dix sujets les plus récemment actifs, tous forums confondus : le haut
 * de l'accueil, avant les catégories, comme sur Reddit. Demande de l'auteur
 * du 9 novembre.
 */
function sujetsRecents(): array
{
    return lignes(
        "SELECT s.id, s.titre, s.pseudo, s.verrouille, s.reponses, s.dernier_pseudo,
                UNIX_TIMESTAMP(s.date_dernier) AS dernier_ts, UNIX_TIMESTAMP(s.date_creation) AS creation_ts,
                f.adresse, f.titre AS forum
           FROM sujet s JOIN forum f ON f.id = s.forum_id
          WHERE f.etat <> 'masque'
          ORDER BY s.date_dernier DESC, s.id DESC LIMIT 10",
    );
}

/** Les sujets récents, chacun avec son forum. Rien tant que le forum est vide. */
function rendreSujetsRecents(array $sujets): string
{
    if ($sujets === []) {
        return '';
    }

    return '<section class="tableau-forum tableau-sujets" aria-labelledby="titre-recents">'
        . barreTableau('Sujets récents', 'titre-recents', ['Réponses', 'Dernier message'])
        . '<ul class="lignes-forum">'
        . implode('', array_map(static fn (array $s): string => ligneSujet($s, (string) $s['adresse'], (string) $s['forum']), $sujets))
        . '</ul></section>';
}

/** Les chiffres du pied de l'accueil, comme sur phpBB. */
function statistiquesForum(): array
{
    return ligne(
        "SELECT (SELECT COUNT(*) FROM sujet s JOIN forum f ON f.id = s.forum_id WHERE f.etat <> 'masque') AS sujets,
                (SELECT COALESCE(SUM(s.reponses + 1), 0) FROM sujet s JOIN forum f ON f.id = s.forum_id WHERE f.etat <> 'masque') AS messages,
                (SELECT COUNT(*) FROM membre WHERE courriel_verifie = 1 AND banni = 0) AS membres,
                (SELECT pseudo FROM membre WHERE courriel_verifie = 1 AND banni = 0
                  ORDER BY date_inscription DESC, id DESC LIMIT 1) AS dernier",
    ) ?? [];
}

/**
 * « Qui est en ligne » et les statistiques, au pied de l'accueil. Les
 * personnes en ligne sont visibles de tous, choix de l'auteur du 7 novembre
 * (docs/forum.md, section 9).
 *
 * @param list<string>         $enLigne
 * @param array<string, mixed> $stats   sujets, messages, membres, dernier
 */
function rendrePiedForum(array $enLigne, array $stats): string
{
    $profil = static fn (string $p): string => '<a href="/membres/' . e($p) . '/">' . e($p) . '</a>';
    $presents = $enLigne === []
        ? 'Les membres actifs dans les deux dernières minutes paraissent ici.'
        : compte(count($enLigne), 'membre en ligne', 'membres en ligne') . ' : ' . implode(', ', array_map($profil, $enLigne)) . '.';
    $chiffres = compte((int) ($stats['messages'] ?? 0), 'message', 'messages') . ' dans '
        . compte((int) ($stats['sujets'] ?? 0), 'sujet', 'sujets') . ', '
        . compte((int) ($stats['membres'] ?? 0), 'membre', 'membres') . '.'
        . (($stats['dernier'] ?? null) !== null ? ' Bienvenue à notre dernier membre, ' . $profil((string) $stats['dernier']) . '.' : '');

    return '<section class="pied-forum" aria-labelledby="titre-en-ligne">'
        . '<h2 id="titre-en-ligne">Qui est en ligne</h2><p>' . $presents . '</p>'
        . '<h2>Statistiques</h2><p>' . $chiffres . '</p></section>';
}

/* --- Forums des membres (docs/forum.md, section 6) --------------------- */

const CATEGORIE_MEMBRES = 9;
const FORUMS_PAR_MOIS = 1;
const ANCIENNETE_FORUM_JOURS = 30;
const TITRE_FORUM_MIN = 3;
const TITRE_FORUM_MAX = 80;
const DESCRIPTION_FORUM_MAX = 200;
/** Des adresses que le site emploie déjà sous /forum/. */
const ADRESSES_RESERVEES = ['fil', 'gabarit', 'recherche'];

/** Membre confirmé depuis 30 jours, comme pour voter. */
function peutCreerForum(?array $membre, int $maintenant): bool
{
    if (!estActif($membre)) {
        return false;
    }
    $inscription = strtotime((string) ($membre['date_inscription'] ?? ''));

    return $inscription !== false && $maintenant - $inscription >= ANCIENNETE_FORUM_JOURS * 86400;
}

/** Épingler et fermer : le créateur dans son forum, l'admin partout. */
function peutGererForum(?array $membre, array $forum): bool
{
    return estActif($membre)
        && (estAdmin($membre)
            || (($forum['createur_id'] ?? null) !== null && (int) $forum['createur_id'] === (int) $membre['id']));
}

/** « Préparation au championnat de France » devient « preparation-au-championnat-de-france ». */
function adresseDepuisTitre(string $titre): string
{
    $adresse = trim(str_replace(' ', '-', trim(normaliserMots($titre))), '-');
    $adresse = rtrim(substr($adresse, 0, 50), '-');

    return match (true) {
        $adresse === '' => 'forum',
        in_array($adresse, ADRESSES_RESERVEES, true) => 'forum-' . $adresse,
        default => $adresse,
    };
}

function forumsCreesCeMois(int $membreId): int
{
    return (int) (ligne(
        'SELECT COUNT(*) AS n FROM forum WHERE createur_id = :m AND date_creation > NOW() - INTERVAL 30 DAY',
        ['m' => $membreId],
    )['n'] ?? 0);
}

/**
 * '' si le nom et la description d'un forum passent, la raison sinon. Ils
 * s'affichent à tous : le filtre strict, sans le passe du dopage, qui n'a pas
 * de forum à lui (docs/forum.md, section 2).
 */
function erreurForum(string $titre, string $description): string
{
    $n = mb_strlen($titre);
    if ($n < TITRE_FORUM_MIN || $n > TITRE_FORUM_MAX || mb_strlen($description) > DESCRIPTION_FORUM_MAX
        || preg_match('/[\x00-\x1F\x7F]/', $titre . $description) === 1) {
        return 'Le nom tient en ' . TITRE_FORUM_MIN . ' à ' . TITRE_FORUM_MAX . ' caractères, la description en '
            . DESCRIPTION_FORUM_MAX . '.';
    }

    return analyserTexte($titre . ' ' . $description) !== 'ok'
        ? 'Ce nom ou cette description contient un terme refusé pour un forum.'
        : '';
}

/** Le nom et la description, jamais l'adresse. */
function renommerForum(int $forumId, string $titre, string $description): void
{
    executer('UPDATE forum SET titre = :t, description = :d WHERE id = :f', ['t' => $titre, 'd' => $description, 'f' => $forumId]);
}

/** Crée le forum, publié aussitôt (choix de l'auteur du 7 novembre). Rend son adresse. */
function creerForumMembre(array $membre, string $titre, string $description): string
{
    $base = adresseDepuisTitre($titre);
    $adresse = $base;
    for ($n = 2; ligne('SELECT 1 AS n FROM forum WHERE adresse = :a', ['a' => $adresse]) !== null; $n++) {
        $adresse = $base . '-' . $n;
    }
    executer(
        'INSERT INTO forum (categorie_id, adresse, titre, description, createur_id) VALUES (:c, :a, :t, :d, :m)',
        ['c' => CATEGORIE_MEMBRES, 'a' => $adresse, 't' => $titre, 'd' => $description, 'm' => $membre['id']],
    );

    return $adresse;
}

/**
 * Un forum de membre sans message depuis six mois passe en archive, lisible
 * et fermé à l'écriture. Fait à la lecture de l'accueil : aucune tâche
 * planifiée à configurer. Rend le nombre de forums archivés.
 */
function archiverForumsInactifs(): int
{
    return executer(
        "UPDATE forum f SET f.etat = 'archive'
          WHERE f.createur_id IS NOT NULL AND f.etat = 'ouvert'
            AND f.date_creation < NOW() - INTERVAL 6 MONTH
            AND NOT EXISTS (SELECT 1 FROM sujet s WHERE s.forum_id = f.id AND s.date_dernier > NOW() - INTERVAL 6 MONTH)",
    );
}

/** Épingle ou ferme un sujet. `$champ` vient du code, jamais de la requête. */
function marquerSujet(int $sujetId, string $champ, bool $valeur): void
{
    if (!in_array($champ, ['epingle', 'verrouille'], true)) {
        return;
    }
    executer("UPDATE sujet SET $champ = :v WHERE id = :s", ['v' => $valeur ? 1 : 0, 's' => $sujetId]);
}

/* --- Le fil (docs/forum.md, section 6 ter) ------------------------------ */

/**
 * Les nouveaux sujets, les réponses et les performances vérifiées, mêlés par
 * date. Les nouveaux membres n'y entrent pas : un pseudo qui arrive n'apprend
 * rien au lecteur.
 *
 * @return list<array<string, mixed>>
 */
function filActualite(): array
{
    return lignes(
        "SELECT * FROM (
            (SELECT 'sujet' AS nature, s.id AS sujet_id, NULL AS message_id, s.titre, s.pseudo, f.adresse,
                    CONCAT(c.titre, ', ', f.titre) AS forum, 0 AS position, NULL AS mouvement, NULL AS charge_kg,
                    UNIX_TIMESTAMP(s.date_creation) AS date_ts
               FROM sujet s JOIN forum f ON f.id = s.forum_id JOIN forum_categorie c ON c.id = f.categorie_id
              WHERE f.etat <> 'masque'
              ORDER BY s.date_creation DESC LIMIT 30)
            UNION ALL
            (SELECT 'reponse', s.id, mf.id, s.titre, mf.pseudo, f.adresse, CONCAT(c.titre, ', ', f.titre),
                    (SELECT COUNT(*) FROM message_forum m2 WHERE m2.sujet_id = mf.sujet_id AND m2.id < mf.id),
                    NULL, NULL, UNIX_TIMESTAMP(mf.date_creation)
               FROM message_forum mf JOIN sujet s ON s.id = mf.sujet_id
               JOIN forum f ON f.id = s.forum_id JOIN forum_categorie c ON c.id = f.categorie_id
              WHERE f.etat <> 'masque' AND mf.masque_par IS NULL
                AND mf.id <> (SELECT MIN(m3.id) FROM message_forum m3 WHERE m3.sujet_id = mf.sujet_id)
              ORDER BY mf.id DESC LIMIT 30)
            UNION ALL
            (SELECT CASE WHEN p.date_verif_admin IS NOT NULL THEN 'badge_admin' ELSE 'badge_communaute' END,
                    NULL, NULL, NULL, m.pseudo, NULL, NULL, 0, p.mouvement, p.charge_kg,
                    UNIX_TIMESTAMP(COALESCE(p.date_verif_admin, p.date_verif_communaute))
               FROM performance p JOIN membre m ON m.id = p.membre_id
              WHERE (p.date_verif_admin IS NOT NULL OR p.date_verif_communaute IS NOT NULL) AND m.banni = 0
              ORDER BY COALESCE(p.date_verif_admin, p.date_verif_communaute) DESC LIMIT 30)
         ) AS fil
         ORDER BY date_ts DESC
         LIMIT 50",
    );
}

/** Le fil en HTML, une ligne par évènement, à la manière d'un fil Reddit. */
function rendreFil(array $evenements): string
{
    if ($evenements === []) {
        return '<p class="aide">Rien pour l\'instant. Le fil se remplit des sujets, des réponses et des performances vérifiées.</p>';
    }
    $html = '<ol class="fil-actualite">';
    foreach ($evenements as $ev) {
        $quand = balisageDate((int) $ev['date_ts']);
        $pseudo = e((string) $ev['pseudo']);
        $html .= '<li class="evenement">' . match ($ev['nature']) {
            'sujet' => '<p class="meta">Nouveau sujet · ' . e((string) $ev['forum']) . '</p>'
                . '<p class="titre-sujet"><a href="' . e(lienSujet((string) $ev['adresse'], (int) $ev['sujet_id'])) . '">'
                . e((string) $ev['titre']) . '</a></p><p class="meta">par ' . $pseudo . ', ' . $quand . '</p>',
            'reponse' => '<p class="meta">Réponse · ' . e((string) $ev['forum']) . '</p>'
                . '<p class="titre-sujet"><a href="' . e(lienSujet((string) $ev['adresse'], (int) $ev['sujet_id'], pageDuMessage((int) $ev['position']))
                    . '#message-' . (int) $ev['message_id']) . '">' . e((string) $ev['titre']) . '</a></p>'
                . '<p class="meta">par ' . $pseudo . ', ' . $quand . '</p>',
            default => '<p class="meta">Performance vérifiée ' . ($ev['nature'] === 'badge_admin' ? 'par SBD.re' : 'par la communauté') . '</p>'
                . '<p class="titre-sujet"><a href="/membres/' . $pseudo . '/">' . $pseudo . ', '
                . e(mb_strtolower(MOUVEMENTS[(string) $ev['mouvement']] ?? '')) . ' ' . kgFr((float) $ev['charge_kg']) . '</a></p>'
                . '<p class="meta">' . $quand . '</p>',
        } . '</li>';
    }

    return $html . '</ol>';
}

/** Le formulaire « Créer un forum », sous la catégorie des membres. */
function formulaireCreerForum(?array $membre): string
{
    if ($membre === null) {
        return '<p class="ligne-vide creer-forum"><a href="/compte/connexion/">Connectez-vous</a> pour créer un forum.</p>';
    }

    return '<details class="repli creer-forum"><summary>Créer un forum</summary>'
        . '<form class="form-compte" data-forum-creer novalidate>'
        . '<p class="aide">Un forum public sur un sujet précis : une salle, un programme, une compétition à préparer. '
        . 'Il paraît aussitôt. Membre depuis ' . ANCIENNETE_FORUM_JOURS . ' jours, un forum par mois.</p>'
        . '<div class="champ"><label for="titre-forum">Nom du forum</label>'
        . '<input id="titre-forum" name="titre" required minlength="' . TITRE_FORUM_MIN . '" maxlength="' . TITRE_FORUM_MAX . '" /></div>'
        . '<div class="champ"><label for="description-forum">Description</label>'
        . '<input id="description-forum" name="description" maxlength="' . DESCRIPTION_FORUM_MAX . '" /></div>'
        . '<div class="actions"><button type="submit">Créer le forum</button></div>'
        . '<p class="etat-compte" data-etat role="status" aria-live="polite" hidden></p></form></details>';
}

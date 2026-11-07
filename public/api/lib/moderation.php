<?php
/**
 * Les deux listes de mots, pour le chat et le forum (docs/comptes.md,
 * section 6.5 ; docs/forum.md, section 5 bis).
 *
 *   BLOQUANTS  le message est refusé avant d'entrer en base : contenu sexuel,
 *              haine, insultes qui visent quelqu'un. Une grossièreté seule
 *              passe, le ton du chat est un peu plus libre que celui des
 *              profils (forum.md, section 5).
 *   SENSIBLES  le message passe, sous un avertissement : les noms précis de
 *              produits et de pratiques dopantes. Jamais un mot courant du
 *              métier : « cycle » et « cure » servent à la programmation.
 *
 * Fonction pure, éprouvée sans base par db/verifier-autorisation.php. Les
 * listes se complètent à l'usage : un mot de plus s'écrit en minuscules,
 * sans accent. La comparaison se fait mot entier, pluriel compris.
 */

declare(strict_types=1);

namespace SBDRE;

if (!defined('SBDRE_API')) {
    http_response_code(404);
    exit;
}

const MOTS_BLOQUANTS = [
    // Contenu sexuel.
    'porno', 'pornographie', 'pornographique', 'xxx', 'sodomie', 'fellation', 'branlette',
    // Haine.
    'negre', 'negro', 'bougnoule', 'bicot', 'youpin', 'chinetoque', 'bamboula',
    'pede', 'tarlouze', 'tafiole', 'gouine',
    // Insultes qui visent quelqu'un.
    'fils de pute', 'nique ta mere', 'ta mere la pute', 'encule', 'enculee', 'salope', 'pute',
];

const MOTS_SENSIBLES = [
    'steroide', 'anabolisant', 'testosterone', 'trenbolone', 'nandrolone', 'oxandrolone',
    'dianabol', 'stanozolol', 'winstrol', 'clenbuterol', 'epo', 'sarm', 'hormone de croissance',
    'dopage', 'dopant', 'doper', 'dope',
];

/** L'avertissement, au-dessus d'un message sensible (forum.md, section 5 bis). */
const AVERTISSEMENT_DOPAGE = 'Ce message aborde le dopage. Les produits dopants mettent en danger la santé '
    . 'de qui les prend, et leur usage hors prescription est interdit en compétition. Rien ici n\'est un conseil.';

/** À l'écriture, avant l'envoi. */
const PREVENIR_DOPAGE = 'Votre message aborde le dopage. Il paraîtra avec un avertissement. '
    . 'N\'incitez jamais personne. Envoyez de nouveau pour confirmer.';

/** Minuscules, sans accent, ponctuation réduite à des espaces. */
function normaliserMots(string $texte): string
{
    $texte = mb_strtolower($texte, 'UTF-8');
    $texte = strtr($texte, [
        'à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c', 'œ' => 'oe', 'æ' => 'ae',
    ]);

    return ' ' . trim((string) preg_replace('/[^a-z0-9]+/', ' ', $texte)) . ' ';
}

/** Un des mots de la liste, entier, au singulier ou au pluriel ? */
function contientMot(string $normalise, array $liste): bool
{
    foreach ($liste as $mot) {
        if (preg_match('/ ' . preg_quote($mot, '/') . '(s|x)? /', $normalise) === 1) {
            return true;
        }
    }

    return false;
}

/** « bloque », « sensible » ou « ok ». */
function analyserTexte(string $texte): string
{
    $normalise = normaliserMots($texte);

    return match (true) {
        contientMot($normalise, MOTS_BLOQUANTS) => 'bloque',
        contientMot($normalise, MOTS_SENSIBLES) => 'sensible',
        default => 'ok',
    };
}

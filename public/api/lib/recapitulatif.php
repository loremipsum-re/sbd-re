<?php
/**
 * Le récapitulatif par courriel (docs/forum.md, section 4) : toutes les deux
 * semaines, pour qui le demande dans ses réglages, les notifications non lues
 * depuis le précédent. Rien à dire, rien ne part.
 *
 * SANS TÂCHE PLANIFIÉE, alors que le plan en prévoyait une. En ligne de
 * commande, l'hébergement refuse mail() (« User loremis is not allowed to
 * submit mail », docs/communaute.md), et OVH bride le réseau sortant de ses
 * tâches planifiées comme celui du SSH. Le site, lui, envoie. La cloche de
 * l'en-tête, qu'un membre connecté interroge une fois par minute, fait donc
 * partir au plus deux récapitulatifs dus à chaque passage.
 *
 * Un membre est RÉSERVÉ avant l'envoi, par une écriture qui ne passe que si
 * sa date n'a pas bougé : deux cloches simultanées ne lui écrivent pas deux
 * fois.
 *
 * Les fonctions écrivent sans transaction, pour que db/verifier.php les
 * éprouve dans la sienne. Le corps du courriel est pur, éprouvé sans base.
 */

declare(strict_types=1);

namespace SBDRE;

if (!defined('SBDRE_API')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/comptes.php';

const RECAPITULATIF_JOURS = 14;
const RECAPITULATIFS_PAR_PASSAGE = 2;
const RECAPITULATIF_LIGNES = 20;
/** Le lien « couper » vaut 60 jours : le temps de retrouver un vieux courriel. */
const JETON_RECAPITULATIF_MINUTES = 60 * 24 * 60;

/**
 * Les membres à qui un récapitulatif est dû, les plus anciens d'abord :
 * demandé, quatorze jours passés, et au moins une notification non lue
 * depuis le précédent.
 *
 * @return list<array<string, mixed>> id, pseudo, courriel, date_recapitulatif
 */
function recapitulatifsDus(int $max): array
{
    return lignes(
        'SELECT m.id, m.pseudo, m.courriel, m.date_recapitulatif
           FROM membre m
          WHERE m.recapitulatif = 1 AND m.courriel_verifie = 1 AND m.banni = 0
            AND (m.date_recapitulatif IS NULL OR m.date_recapitulatif < NOW() - INTERVAL ' . RECAPITULATIF_JOURS . ' DAY)
            AND EXISTS (SELECT 1 FROM notification n
                         WHERE n.membre_id = m.id AND n.lue = 0
                           AND (m.date_recapitulatif IS NULL OR n.date_creation > m.date_recapitulatif))
          ORDER BY m.date_recapitulatif, m.id
          LIMIT ' . $max,
    );
}

/** La date avance seulement si personne ne l'a fait avant. Rend false sinon. */
function reserverRecapitulatif(int $membreId, ?string $precedent): bool
{
    return executer(
        'UPDATE membre SET date_recapitulatif = NOW() WHERE id = :id AND date_recapitulatif <=> :d',
        ['id' => $membreId, 'd' => $precedent],
    ) === 1;
}

/**
 * Les non lues depuis le précédent récapitulatif, vingt au plus, et leur
 * nombre. Les barres vérifiées d'abord, puis les plus récentes.
 *
 * @return array{total: int, liste: list<array<string, mixed>>}
 */
function notificationsDuRecapitulatif(int $membreId, ?string $precedent): array
{
    $params = ['m' => $membreId, 'd' => $precedent ?? '1970-01-01 00:00:00'];
    $condition = 'FROM notification WHERE membre_id = :m AND lue = 0 AND date_creation > :d';

    return [
        'total' => (int) (ligne("SELECT COUNT(*) AS n $condition", $params)['n'] ?? 0),
        'liste' => lignes("SELECT nature, texte, lien $condition ORDER BY nature = 'badge' DESC, id DESC LIMIT " . RECAPITULATIF_LIGNES, $params),
    ];
}

/**
 * Le courriel, en texte simple : une ligne par notification et son lien,
 * puis le lien qui coupe le récapitulatif. Une barre vérifiée commence par
 * une coche et le dit, comme sur /compte/notifications/.
 *
 * @param list<array<string, mixed>> $liste nature, texte, lien
 */
function corpsRecapitulatif(string $pseudo, array $liste, int $total, string $jeton): string
{
    $lignes = [
        "Bonjour $pseudo,",
        '',
        ($total > 1 ? "$total notifications vous attendent" : 'Une notification vous attend')
            . ' sur sbd.re depuis votre dernier récapitulatif :',
        '',
    ];
    foreach ($liste as $n) {
        $lignes[] = ($n['nature'] ?? '') === 'badge' ? '✓ Barre vérifiée. ' . $n['texte'] : '- ' . $n['texte'];
        if (str_starts_with((string) $n['lien'], '/')) {
            $lignes[] = '  https://sbd.re' . $n['lien'];
        }
    }
    $reste = $total - count($liste);
    if ($reste > 0) {
        array_push($lignes, '', $reste > 1 ? "Et $reste autres." : 'Et une autre.');
    }

    return implode("\n", array_merge($lignes, [
        '',
        'Toutes vos notifications : https://sbd.re/compte/notifications/',
        '',
        '-- ',
        "Vous recevez ce récapitulatif toutes les deux semaines parce que vous l'avez demandé. Pour le couper :",
        'https://sbd.re/compte/recapitulatif/#jeton=' . $jeton,
    ]));
}

/** Envoie au plus `$max` récapitulatifs dus. Rend le nombre parti. */
function envoyerRecapitulatifs(int $max = RECAPITULATIFS_PAR_PASSAGE): int
{
    $partis = 0;
    foreach (recapitulatifsDus($max) as $m) {
        $precedent = $m['date_recapitulatif'];
        if (!reserverRecapitulatif((int) $m['id'], $precedent)) {
            continue;
        }
        // Les adresses qu'Apple refuse (comptes.md, 6.1) : la date avance, rien ne part.
        if (adresseInjoignable((string) $m['courriel'])) {
            continue;
        }
        $notifications = notificationsDuRecapitulatif((int) $m['id'], $precedent);
        $jeton = creerJeton((int) $m['id'], 'recapitulatif', JETON_RECAPITULATIF_MINUTES);
        $corps = corpsRecapitulatif((string) $m['pseudo'], $notifications['liste'], $notifications['total'], $jeton);
        if (envoyerCourriel((string) $m['courriel'], '[sbd.re] Votre récapitulatif', $corps)) {
            $partis++;
        }
    }

    return $partis;
}

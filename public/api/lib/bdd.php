<?php
/**
 * Connexion à MySQL.
 *
 * Un seul endroit ouvre la connexion, et il impose le `sql_mode` strict. C'est
 * la conséquence directe d'une mesure faite le 30 juillet 2026 : le serveur
 * d'OVH tourne avec `sql_mode` réduit à `NO_ENGINE_SUBSTITUTION`, donc SANS
 * mode strict. Voir docs/communaute.md, section 11.
 */

declare(strict_types=1);

namespace SBDRE;

if (!defined('SBDRE_API')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/configuration.php';

/**
 * La connexion, ouverte à la première demande et réutilisée ensuite.
 *
 * PHP ferme la connexion tout seul à la fin de la requête : il n'y a rien à
 * fermer à la main, et surtout rien à garder ouvert entre deux visiteurs.
 */
function bdd(): \PDO
{
    static $pdo = null;

    if ($pdo instanceof \PDO) {
        return $pdo;
    }

    $c = config_val('bdd');

    if (!is_array($c) || ($c['sql_mode'] ?? '') === '') {
        // Sans `sql_mode`, MySQL raboterait les valeurs aberrantes au lieu de
        // les refuser. Mieux vaut ne pas démarrer du tout que travailler sur
        // une base qui accepte n'importe quoi en silence.
        throw new \RuntimeException('Configuration de base incomplète.');
    }

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $c['hote'],
        $c['base'],
        $c['jeu'] ?? 'utf8mb4',
    );

    try {
        $pdo = new \PDO($dsn, $c['utilisateur'], $c['mot_de_passe'], [
            // Une erreur SQL lève une exception au lieu de renvoyer un code de
            // retour que l'on oublie de lire une fois sur deux.
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,

            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,

            // De VRAIES requêtes préparées, analysées par le serveur, plutôt
            // que l'émulation qui recolle la requête côté PHP avant l'envoi.
            \PDO::ATTR_EMULATE_PREPARES   => false,

            // Rejoué à chaque ouverture, y compris après une reconnexion
            // automatique : aucune requête ne peut s'exécuter en mode permissif.
            \PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION sql_mode = '{$c['sql_mode']}'",
        ]);
    } catch (\PDOException $e) {
        // Le message d'origine contient l'hôte, la base et parfois
        // l'identifiant. Il va au journal, jamais au visiteur.
        error_log('SBD.re : connexion MySQL impossible. ' . $e->getMessage());
        throw new \RuntimeException('Base de données indisponible.');
    }

    return $pdo;
}

/**
 * Exécute une requête préparée et rend les lignes.
 *
 * Toute valeur venant d'un visiteur passe par $params, JAMAIS par $sql. C'est
 * la règle qui ferme les injections SQL par construction plutôt que par
 * vigilance : la valeur ne devient jamais du texte de requête.
 *
 * @param  array<string|int, mixed> $params
 * @return list<array<string, mixed>>
 */
function lignes(string $sql, array $params = []): array
{
    $st = bdd()->prepare($sql);
    $st->execute($params);

    /** @var list<array<string, mixed>> $r */
    $r = $st->fetchAll();
    return $r;
}

/**
 * Première ligne, ou null.
 *
 * @param  array<string|int, mixed> $params
 * @return array<string, mixed>|null
 */
function ligne(string $sql, array $params = []): ?array
{
    $st = bdd()->prepare($sql);
    $st->execute($params);

    $r = $st->fetch();
    return $r === false ? null : $r;
}

/**
 * Exécute une écriture et rend le nombre de lignes touchées.
 *
 * @param array<string|int, mixed> $params
 */
function executer(string $sql, array $params = []): int
{
    $st = bdd()->prepare($sql);
    $st->execute($params);

    return $st->rowCount();
}

<?php
/**
 * Chaque point d'entrée de public/api charge-t-il ses bibliothèques sans
 * erreur fatale ? Sans base de données : seules les lignes require sont
 * jouées, une page à la fois, dans un processus neuf.
 *
 *   php db/verifier-chargement.php
 *
 * POURQUOI. Le 9 novembre 2026, lib/notifications.php s'est mis à charger
 * lib/chat.php. forum-page.php le chargeait ensuite une seconde fois, par un
 * `require` simple : « Cannot redeclare function », et le forum comme la
 * modération ont répondu 500 en production pendant trois jours. `php -l` ne
 * voit pas ce défaut, qui ne paraît qu'à l'exécution. Les points d'entrée
 * chargent donc tout par `require_once`, et ce script le vérifie.
 */

declare(strict_types=1);

$racine = dirname(__DIR__) . '/public/api';
$echecs = 0;

foreach (glob($racine . '/*.php') as $fichier) {
    $code = "<?php\nnamespace SBDRE;\ndefine('SBDRE_API', true);\n";
    foreach (file($fichier) as $ligne) {
        if (preg_match('/^\s*(require|include)(_once)?\s/', $ligne) === 1) {
            $code .= str_replace('__DIR__', var_export(dirname($fichier), true), $ligne);
        }
    }
    $essai = tempnam(sys_get_temp_dir(), 'sbdre') . '.php';
    file_put_contents($essai, $code . "echo 'ok';\n");
    $sortie = trim((string) shell_exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=1 ' . escapeshellarg($essai) . ' 2>&1'));
    unlink($essai);

    if ($sortie !== 'ok') {
        $echecs++;
        echo '  [échec] ' . basename($fichier) . ' : ' . strtok($sortie, "\n") . "\n";
    }
}

if ($echecs > 0) {
    echo "\n  $echecs point(s) d'entrée en échec.\n";
    exit(1);
}
echo "  Les points d'entrée se chargent sans erreur.\n";

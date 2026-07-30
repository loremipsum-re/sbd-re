<?php
/**
 * Chargement de la configuration de la partie communauté.
 *
 * Le fichier `config.php` porte le mot de passe MySQL. Il n'est PAS versionné,
 * il ne passe PAS par le déploiement, et il se dépose une seule fois à la main.
 * Ce fichier-ci ne fait que le trouver et le lire.
 */

declare(strict_types=1);

namespace SBDRE;

/* -------------------------------------------------------------------------
 * Refus d'exécution directe
 *
 * Ce fichier vit sous la racine web : sans ce garde-fou, une requête sur
 * /api/lib/config.php l'exécuterait. Il ne produirait rien de lisible, mais
 * l'habitude de laisser des fichiers exécutables à découvert finit toujours par
 * coûter cher. Le `.htaccess` du dossier interdit déjà l'accès ; ceci en est la
 * seconde couche, au cas où une configuration Apache changerait un jour.
 * ---------------------------------------------------------------------- */

if (!defined('SBDRE_API')) {
    http_response_code(404);
    exit;
}

/**
 * Emplacements possibles du fichier de configuration, du plus sûr au moins sûr.
 *
 * Ce fichier est déployé dans « <racine web>/api/lib/ ». Donc :
 *   dirname(__DIR__, 2) = la racine web
 *   dirname(__DIR__, 3) = le dossier AU-DESSUS, hors de portée du web
 *
 * Le premier emplacement est le bon : hors racine web, donc impossible à
 * demander par HTTP, et hors du miroir du déploiement, donc épargné par la
 * purge `--delete`.
 *
 * Le second n'existe que pour les comptes FTP cloisonnés, où rien n'est
 * accessible au-dessus du dossier du site. Il EXIGE alors deux précautions :
 * une règle de refus dans `.htaccess`, et une exclusion dans le déploiement,
 * faute de quoi le premier envoi effacerait le fichier.
 *
 * @return list<string>
 */
function cheminsConfig(): array
{
    // Le nom du dossier est celui réellement créé sur l'hébergement, vérifié le
    // 30 juillet 2026 : /home/loremis/sbd-re-prive, à côté de /home/loremis/sbd-re
    // qui est la racine du site. Se tromper d'un tiret ici produirait une panne
    // en production seulement, la configuration restant introuvable.
    return [
        dirname(__DIR__, 3) . '/sbd-re-prive/config.php',
        dirname(__DIR__, 2) . '/sbd-re-prive/config.php',
    ];
}

/**
 * Configuration complète, lue une seule fois par requête.
 *
 * @return array<string, mixed>
 */
function config(): array
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    foreach (cheminsConfig() as $chemin) {
        if (is_file($chemin)) {
            /** @var array<string, mixed> $charge */
            $charge = require $chemin;
            $config = $charge;
            return $config;
        }
    }

    // Message volontairement muet sur les chemins essayés : cette exception
    // peut remonter dans une réponse HTTP, et la structure des dossiers du
    // serveur n'a pas à être publiée. Le détail va dans le journal, pas au
    // visiteur.
    error_log('SBD.re : config.php introuvable. Cherché dans : '
              . implode(', ', cheminsConfig()));

    throw new \RuntimeException('Configuration absente.');
}

/**
 * Une valeur de configuration, par chemin pointé : config_val('bdd.hote').
 */
function config_val(string $chemin, mixed $defaut = null): mixed
{
    $valeur = config();

    foreach (explode('.', $chemin) as $clef) {
        if (!is_array($valeur) || !array_key_exists($clef, $valeur)) {
            return $defaut;
        }
        $valeur = $valeur[$clef];
    }

    return $valeur;
}

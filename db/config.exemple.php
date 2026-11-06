<?php
/**
 * Modèle de configuration de la partie communauté.
 *
 * Ce fichier est un EXEMPLE, versionné et sans secret. La vraie configuration
 * se nomme `config.php`, ne part jamais sur GitHub, et se dépose une seule fois
 * à la main chez OVH.
 *
 * -----------------------------------------------------------------------------
 * TROIS PIÈGES À CONNAÎTRE AVANT DE DÉPOSER LE VRAI FICHIER
 * -----------------------------------------------------------------------------
 *
 * 1. LA PURGE DU DÉPLOIEMENT L'EFFACERAIT.
 *    Le déploiement lance `lftp` en mode miroir avec `--delete`, qui supprime
 *    chez OVH tout ce qui n'est pas dans `dist/`. Sans exclusion explicite, le
 *    premier envoi effacerait `config.php` et le site répondrait en erreur 500,
 *    pour une raison parfaitement invisible. Il faut l'exclure comme le sont
 *    déjà `.well-known/` et `.ovhconfig`.
 *
 * 2. IL NE DOIT JAMAIS ÊTRE SERVI EN HTTP.
 *    Déposé sous la racine web, il répondrait à une requête directe. Deux
 *    protections à cumuler : le placer HORS de l'arborescence servie, et le
 *    refuser dans `.htaccess`. Un fichier `.php` correctement interprété ne
 *    montre pas son contenu, mais une mauvaise configuration du serveur suffit
 *    à le faire servir en texte brut, mot de passe compris.
 *
 * 3. IL N'A RIEN À VOIR AVEC LE BUILD.
 *    L'API étant sur la même origine que le site, le navigateur n'a besoin
 *    d'aucune clé ni identifiant. Astro n'a donc aucune variable
 *    d'environnement à recevoir, et le build reste inchangé.
 * -----------------------------------------------------------------------------
 */

return [
    // -------------------------------------------------------------------------
    // Base de données
    // -------------------------------------------------------------------------
    // Ces quatre valeurs viennent de l'espace client OVH, après création de la
    // base. L'hôte n'est PAS « localhost » chez OVH : c'est un nom de serveur
    // dédié, du genre « sbdre.mysql.db ».
    //
    // Rappel : cette base n'est joignable que depuis l'hébergement OVH. Le port
    // 3306 n'est pas exposé à l'extérieur, et aucun réglage ne l'ouvre.
    'bdd' => [
        'hote'         => 'a-completer.mysql.db',
        'base'         => 'a-completer',
        'utilisateur'  => 'a-completer',
        'mot_de_passe' => 'a-completer',

        // utf8mb4 et non « utf8 » : l'ancien jeu de MySQL ne code que trois
        // octets et tronque silencieusement accents composés et emoji.
        'jeu'          => 'utf8mb4',

        // ---------------------------------------------------------------------
        // CE N'EST PAS UN RÉGLAGE. NE PAS ALLÉGER CETTE LIGNE.
        // ---------------------------------------------------------------------
        // Mesuré le 30 juillet 2026 : le serveur OVH tourne en MySQL 8.4.10 avec
        // `sql_mode` réduit au seul `NO_ENGINE_SUBSTITUTION`. MySQL 8 active
        // pourtant par défaut le mode strict et `ONLY_FULL_GROUP_BY` : OVH les a
        // retirés, et sur un mutualisé on ne peut pas changer le réglage global.
        //
        // Sans mode strict, MySQL ne REFUSE pas une valeur invalide : il la
        // rabote et se contente d'un avertissement que personne ne lit. Trois
        // dégâts concrets pour ce projet :
        //
        //  * Un total saisi à 1500 kg par erreur de frappe ne serait pas rejeté.
        //    DECIMAL(5,2) plafonnant à 999.99, MySQL enregistrerait 999.99 — un
        //    record du monde, silencieusement. Et la contrainte CHECK ne sauve
        //    RIEN : elle s'applique à la valeur déjà rabotée, donc elle passe.
        //  * Un pseudo de 60 caractères entrerait tronqué à 40 sans un mot.
        //  * Une date « 0000-00-00 » serait acceptée comme une date valide.
        //
        // Et sans ONLY_FULL_GROUP_BY, une requête de classement qui demande le
        // meilleur soulevé d'un membre peut renvoyer la DATE D'UNE AUTRE de ses
        // performances, sans erreur. C'est exactement le défaut qui avait faussé
        // la date du score Dots côté officiel, voir docs/parcours.md.
        //
        // Le réglage global étant hors d'atteinte, chaque connexion l'impose pour
        // sa session. C'est à faire à la connexion, une fois, sans exception.
        'sql_mode' => 'STRICT_ALL_TABLES,ONLY_FULL_GROUP_BY,NO_ZERO_IN_DATE,'
                    . 'NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION',
    ],

    // -------------------------------------------------------------------------
    // Envoi des courriels par SMTP authentifié
    // -------------------------------------------------------------------------
    // Le mot de passe de la boîte no-reply@sbd.re, à créer dans l'espace client
    // OVH (E-mails, MX Plan, inclus dans l'hébergement). Avec lui, les
    // courriels partent des serveurs de messagerie d'OVH, signés DKIM si DKIM
    // est activé pour sbd.re : iCloud refuse ceux de mail(), qui ne le sont
    // pas. Sans lui, ou en cas d'échec, le site repasse par mail().
    //
    // L'identifiant, le serveur et le port sont écrits dans lib/courriel.php :
    // l'identifiant EST l'expéditeur, comme OVH l'exige.
    'smtp' => [
        'mot_de_passe' => 'a-completer',
    ],

    // Rien d'autre ici. Les durées de session sont des règles du site,
    // versionnées dans lib/session.php. Ce fichier ne garde que ce qui est
    // propre au serveur et secret. Les anciennes clés `session`, `connexion`,
    // `jetons`, `google` et `courriel` d'un config.php déjà déposé sont
    // ignorées, comme `smtp.hote`, `smtp.port` et `smtp.utilisateur`.
];

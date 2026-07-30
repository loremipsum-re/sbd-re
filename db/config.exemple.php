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
    // Sessions
    // -------------------------------------------------------------------------
    'session' => [
        'nom' => 'sbdre_session',

        // Secure : le cookie ne part qu'en HTTPS.
        // HttpOnly : le JavaScript de la page ne peut pas le lire, ce qui limite
        //            fortement les dégâts d'une faille d'injection de script.
        // SameSite=Lax : le cookie n'accompagne pas une requête déclenchée par
        //            un autre site, première barrière contre le CSRF.
        'secure'    => true,
        'http_only' => true,
        'same_site' => 'Lax',

        // Durée d'inactivité avant expiration, en secondes.
        'duree' => 60 * 60 * 24 * 14,
    ],

    // -------------------------------------------------------------------------
    // Freinage des tentatives de connexion
    // -------------------------------------------------------------------------
    // Sans freinage, rien n'empêche de tester des milliers de mots de passe sur
    // une adresse connue. Un hachage solide n'y change rien : il ralentit chaque
    // essai, il n'en limite pas le nombre.
    'connexion' => [
        'echecs_max'      => 5,
        'fenetre_minutes' => 15,
    ],

    // -------------------------------------------------------------------------
    // Jetons envoyés par courriel
    // -------------------------------------------------------------------------
    // Durées de validité, en minutes. Un lien de réinitialisation doit expirer
    // vite : une boîte de courriel consultée des mois plus tard ne doit pas
    // rouvrir un compte.
    'jetons' => [
        'verification_minutes'     => 60 * 48,
        'reinitialisation_minutes' => 60,
    ],

    // -------------------------------------------------------------------------
    // Connexion Google
    // -------------------------------------------------------------------------
    // À remplir le jour où le projet Google Cloud existe. Laisser vide désactive
    // simplement le bouton Google, sans rien casser.
    //
    // Le secret client reste ici, côté serveur, et ne part jamais au navigateur.
    // C'est précisément ce qu'un site purement statique ne permettait pas.
    'google' => [
        'client_id'     => '',
        'client_secret' => '',
        'redirection'   => 'https://sbd.re/api/google/retour',
    ],

    // -------------------------------------------------------------------------
    // Courriels sortants
    // -------------------------------------------------------------------------
    // L'expéditeur DOIT appartenir au domaine du site. L'enregistrement SPF de
    // sbd.re se termine par « -all », ce qui demande le rejet de tout message
    // envoyé au nom du domaine par un serveur non autorisé.
    'courriel' => [
        'expediteur'     => 'bonjour@sbd.re',
        'nom_expediteur' => 'SBD.re',
    ],

    // -------------------------------------------------------------------------
    // SMTP
    // -------------------------------------------------------------------------
    // Mesuré le 30 juillet 2026 : la fonction mail() de PHP est REFUSÉE par
    // l'hébergement, « User loremis is not allowed to submit mail ». Le SMTP
    // authentifié n'est donc pas un contournement, c'est la seule voie, et
    // c'était de toute façon la bonne : les messages sortent alors des serveurs
    // de messagerie qu'autorise le SPF du domaine.
    //
    // Deux combinaisons possibles chez OVH, l'une et l'autre chiffrées :
    //   ssl0.ovh.net port 465 avec 'ssl'
    //   ssl0.ovh.net port 587 avec 'tls'
    //
    // L'utilisateur est l'ADRESSE COMPLÈTE de la boîte, pas un identifiant
    // court, et le mot de passe est celui de cette boîte. Il faut donc que la
    // boîte existe réellement dans l'espace client OVH.
    'smtp' => [
        'hote'         => 'ssl0.ovh.net',
        'port'         => 465,
        'chiffrement'  => 'ssl',
        'utilisateur'  => 'bonjour@sbd.re',
        'mot_de_passe' => 'a-completer',
    ],
];

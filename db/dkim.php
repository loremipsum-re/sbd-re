<?php

declare(strict_types=1);

/**
 * La signature DKIM des courriels du site : créer la clé, vérifier le DNS.
 *
 * -----------------------------------------------------------------------------
 * POURQUOI
 * -----------------------------------------------------------------------------
 * iCloud refuse les courriels non signés (« [HM08] », mesuré le 6 novembre
 * 2026), et le réseau de l'hébergement bloque le SMTP d'OVH, qui signe. Le
 * site signe donc lui-même, voir public/api/lib/courriel.php.
 *
 * -----------------------------------------------------------------------------
 * USAGE, en SSH avec le login principal, depuis ~/sbd-re-prive
 * -----------------------------------------------------------------------------
 *   php dkim.php
 *
 *   1er passage : crée dkim-prive.pem, la clé privée, avec les mêmes droits
 *                 que config.php, puis affiche l'enregistrement DNS à créer.
 *   ensuite     : relit le DNS, vérifie que la clé publiée est bien celle du
 *                 serveur, et signe un message d'essai sans rien envoyer.
 *
 * La clé privée naît sur le serveur et n'en sort jamais : ni GitHub, ni
 * conversation, ni ordinateur personnel. Le script refuse d'en écraser une.
 */

namespace SBDRE;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$bibliotheque = null;
foreach ([
    dirname(__DIR__) . '/public/api/lib/courriel.php', // depuis le dépôt
    dirname(__DIR__) . '/sbd-re/api/lib/courriel.php',  // depuis ~/sbd-re-prive
] as $chemin) {
    if (is_file($chemin)) {
        $bibliotheque = $chemin;
        break;
    }
}
if ($bibliotheque === null) {
    fwrite(STDERR, "lib/courriel.php introuvable, ni dans le dépôt ni dans ~/sbd-re/api/lib/.\n");
    exit(1);
}

define('SBDRE_API', true);
require $bibliotheque;

$cle = cheminCleDkim();
$nomDns = DKIM_SELECTEUR . '._domainkey.' . DKIM_DOMAINE;

echo "Signature DKIM de " . DKIM_DOMAINE . "\n";
echo str_repeat('-', 60) . "\n";
echo "  clé privée : $cle\n";
echo "  DNS        : $nomDns\n\n";

/** La valeur TXT à publier, tirée de la clé privée. */
$valeurDns = static function (string $pem): string {
    $details = openssl_pkey_get_details(openssl_pkey_get_private($pem));
    $base64 = preg_replace('/-----[^-]+-----|\s+/', '', (string) $details['key']);

    return 'v=DKIM1; k=rsa; p=' . $base64;
};

/* --- 1. La clé ---------------------------------------------------------- */

if (!is_file($cle)) {
    if (!is_dir(dirname($cle))) {
        fwrite(STDERR, "Dossier introuvable : " . dirname($cle) . "\n");
        exit(1);
    }

    $nouvelle = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    if ($nouvelle === false || !openssl_pkey_export($nouvelle, $pem)) {
        fwrite(STDERR, "openssl n'a pas pu créer la clé : " . openssl_error_string() . "\n");
        exit(1);
    }

    // Les droits de config.php : ce qui laisse le site lire son mot de
    // passe de base lui laisse lire la clé. Jamais d'écriture pour le
    // groupe ni pour les autres, même si config.php en avait.
    $config = dirname($cle) . '/config.php';
    $droits = (is_file($config) ? fileperms($config) & 0777 : 0600) & 0644;

    file_put_contents($cle, $pem);
    chmod($cle, $droits);

    echo "CLÉ CRÉÉE, droits " . decoct($droits) . " comme config.php.\n\n";
    echo "  Ajouter maintenant cet enregistrement dans la zone DNS de " . DKIM_DOMAINE . " :\n\n";
    echo "    type        : TXT\n";
    echo "    sous-domaine: " . DKIM_SELECTEUR . "._domainkey\n";
    echo "    valeur      : " . $valeurDns($pem) . "\n\n";
    echo "  Puis relancer « php dkim.php » une fois le DNS propagé, quelques\n";
    echo "  minutes à quelques heures.\n";
    exit(0);
}

$pem = (string) file_get_contents($cle);
$attendu = $valeurDns($pem);
echo "  [ok] clé privée lisible, droits " . decoct(fileperms($cle) & 0777) . "\n";

/* --- 2. Le DNS ---------------------------------------------------------- */

$publie = '';
foreach (dns_get_record($nomDns, DNS_TXT) ?: [] as $enregistrement) {
    $texte = isset($enregistrement['entries']) ? implode('', $enregistrement['entries']) : ($enregistrement['txt'] ?? '');
    if (str_contains($texte, 'v=DKIM1')) {
        $publie = $texte;
    }
}

if ($publie === '') {
    echo "  [ATTENTE] aucun enregistrement DKIM publié à $nomDns.\n\n";
    echo "  À créer dans la zone DNS : TXT, sous-domaine " . DKIM_SELECTEUR . "._domainkey,\n";
    echo "  valeur :\n\n    $attendu\n";
    exit(2);
}

$clePublieeBrute = preg_match('/p=([A-Za-z0-9+\/=\s]+)/', $publie, $m) === 1 ? preg_replace('/\s+/', '', $m[1]) : '';
$cleAttendueBrute = substr($attendu, strlen('v=DKIM1; k=rsa; p='));

if ($clePublieeBrute !== $cleAttendueBrute) {
    echo "  [ECHEC] la clé publiée dans le DNS n'est PAS celle du serveur.\n\n";
    echo "  Remplacer la valeur de $nomDns par :\n\n    $attendu\n";
    exit(1);
}
echo "  [ok] la clé publiée dans le DNS est celle du serveur\n";

/* --- 3. Une signature d'essai ------------------------------------------ */

// Le message est préparé comme pour un envoi réel, puis seulement signé :
// rien ne part. La signature est ensuite vérifiée avec la clé du DNS.
$mail = preparerCourriel('essai@exemple.re', '[sbd.re] Essai de signature', "Essai.\n");
$mail->preSend();
$message = $mail->getSentMIMEMessage();

if (preg_match('/^DKIM-Signature:(.*?)(?=\r\n[^ \t])/ms', $message, $s) !== 1) {
    echo "  [ECHEC] le message préparé ne porte aucune signature DKIM.\n";
    exit(1);
}
$signature = preg_replace('/\s+/', '', $s[1]);
preg_match('/(?:^|;)b=([^;]+)/', $signature, $b);
preg_match('/(?:^|;)d=([^;]+)/', $signature, $d);
echo "  [ok] message signé, d=" . ($d[1] ?? '?') . ", sélecteur " . DKIM_SELECTEUR . "\n";

$clePublique = "-----BEGIN PUBLIC KEY-----\n" . chunk_split($clePublieeBrute, 64, "\n") . "-----END PUBLIC KEY-----\n";
$test = 'sbd.re ' . bin2hex(random_bytes(8));
openssl_sign($test, $sig, $pem, OPENSSL_ALGO_SHA256);
if (openssl_verify($test, $sig, $clePublique, OPENSSL_ALGO_SHA256) !== 1) {
    echo "  [ECHEC] une signature de la clé privée ne se vérifie pas avec la clé du DNS.\n";
    exit(1);
}
echo "  [ok] une signature de la clé privée se vérifie avec la clé du DNS\n\n";

echo "DKIM PRÊT. Les courriels du site partent signés.\n";
echo "  Preuve finale : dans un courriel reçu du site, l'en-tête\n";
echo "  Authentication-Results doit dire dkim=pass header.d=" . DKIM_DOMAINE . ".\n";
exit(0);

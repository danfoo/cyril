<?php
/**
 * Utilitaire PONCTUEL — force la recompilation du cache d'opcode PHP (OPcache).
 *
 * À utiliser quand le serveur sert encore l'ANCIEN code PHP du module après une
 * mise à jour (nouvel onglet ou réglage invisible alors que le CSS, lui, est à
 * jour). Le CSS est un fichier statique relu à chaque requête ; le PHP, lui,
 * reste compilé en cache tant qu'OPcache n'est pas réinitialisé.
 *
 * SÉCURITÉ : protégé par un jeton. SUPPRIMEZ ce fichier après usage.
 * URL : .../modules/school_ia_bridge/reset-opcache.php?token=bem-optimize-2026
 */

$token = 'bem-optimize-2026';
if (!isset($_GET['token']) || !hash_equals($token, (string) $_GET['token'])) {
    http_response_code(403);
    exit('Accès refusé.');
}

header('Content-Type: text/plain; charset=utf-8');

if (function_exists('opcache_reset')) {
    $ok = @opcache_reset();
    echo $ok
        ? "✅ OPcache réinitialisé. Rechargez l'admin : le nouvel onglet et les nouveaux réglages doivent apparaître.\n"
        : "⚠️ opcache_reset() a échoué. OPcache est peut-être restreint (opcache.restrict_api) : demandez à l'hébergeur de redémarrer PHP-FPM.\n";
} else {
    echo "ℹ️ OPcache n'est pas actif sur ce serveur — le code PHP devrait déjà être à jour.\n";
}

echo "\n👉 Pensez à SUPPRIMER ce fichier (reset-opcache.php) une fois terminé.\n";

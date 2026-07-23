<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Outil PONCTUEL — force la recompilation du cache d'opcode PHP (OPcache).
 *
 * Pourquoi ça marche là où un fichier .php isolé échoue (404) : ce contrôleur
 * passe par le routage Perfex ({module}/{contrôleur}/{méthode}, comme Api), et
 * comme le fichier est NOUVEAU il n'est pas encore dans OPcache — il est donc
 * compilé à neuf au premier appel et s'exécute, même si le reste du module est
 * servi depuis l'ancien cache.
 *
 * URL : {perfex}/school_ia_bridge/sia_reset?token=bem-optimize-2026
 * SÉCURITÉ : jeton obligatoire. SUPPRIMEZ ce fichier après usage.
 */
class Sia_reset extends App_Controller
{
    public function index()
    {
        header('Content-Type: text/plain; charset=utf-8');

        $token = 'bem-optimize-2026';
        if (!hash_equals($token, (string) $this->input->get('token'))) {
            show_404();
            return;
        }

        if (function_exists('opcache_reset')) {
            $ok = @opcache_reset();
            echo $ok
                ? "✅ OPcache réinitialisé avec succès.\n"
                : "⚠️ opcache_reset() a échoué (opcache.restrict_api ?). Demandez le redémarrage de PHP-FPM.\n";
        } else {
            echo "ℹ️ OPcache n'est pas actif ici — le PHP devrait déjà être à jour (sinon, les fichiers ne sont pas déployés).\n";
        }

        echo "\nRechargez l'admin : « Ma signature » et les nouveaux réglages doivent apparaître.\n";
        echo "👉 SUPPRIMEZ ensuite ce fichier : controllers/Sia_reset.php\n";
    }
}

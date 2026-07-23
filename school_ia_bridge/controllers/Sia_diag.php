<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Diagnostic PONCTUEL — pourquoi le nouveau code du module n'apparaît pas.
 * Nouveau fichier → compilé à neuf par OPcache, donc fiable même si le reste
 * du module est servi depuis un cache périmé.
 *
 * URL : {perfex}/school_ia_bridge/sia_diag?token=bem-optimize-2026
 * SÉCURITÉ : jeton obligatoire. Supprimez ce fichier après usage.
 */
class Sia_diag extends App_Controller
{
    public function index()
    {
        header('Content-Type: text/plain; charset=utf-8');
        if (!hash_equals('bem-optimize-2026', (string) $this->input->get('token'))) {
            show_404();
            return;
        }

        $L = [];

        // 1) Le code du module RÉELLEMENT exécuté est-il à jour ?
        //    Ces fonctions ont été ajoutées avec « Ma signature » / la couleur.
        $L[] = '== Code exécuté (module chargé en mémoire) ==';
        $L[] = 'school_ia_staff_signature() : ' . (function_exists('school_ia_staff_signature') ? 'OUI — code À JOUR' : 'NON — ANCIEN code servi (cache)');
        $L[] = 'school_ia_brand_color()     : ' . (function_exists('school_ia_brand_color') ? 'OUI' : 'NON');

        // 2) Le fichier SUR LE DISQUE est-il à jour ?
        $file = FCPATH . 'modules/school_ia_bridge/school_ia_bridge.php';
        $L[] = '';
        $L[] = '== Fichier sur le disque ==';
        if (is_file($file)) {
            $src = (string) file_get_contents($file);
            $L[] = 'Modifié le : ' . date('Y-m-d H:i:s', (int) filemtime($file));
            $L[] = 'Contient « sia_signature » : ' . (strpos($src, 'sia_signature') !== false ? 'OUI — fichier à jour' : 'NON — fichier ANCIEN sur le disque (déploiement)');
        } else {
            $L[] = 'Introuvable : ' . $file;
        }

        // 3) État d'OPcache (au-delà de la seule fonction reset).
        $L[] = '';
        $L[] = '== OPcache ==';
        $L[] = 'Extension chargée       : ' . (extension_loaded('Zend OPcache') ? 'OUI' : 'non');
        $L[] = 'opcache.enable          : ' . (string) ini_get('opcache.enable');
        $L[] = 'validate_timestamps     : ' . (string) ini_get('opcache.validate_timestamps');
        $L[] = 'revalidate_freq         : ' . (string) ini_get('opcache.revalidate_freq');
        $L[] = 'opcache_reset() dispo   : ' . (function_exists('opcache_reset') ? 'oui' : 'NON (désactivée)');
        $L[] = 'opcache_invalidate() dispo : ' . (function_exists('opcache_invalidate') ? 'oui' : 'NON (désactivée)');

        // 4) Tentative d'invalidation ciblée (peut suffire si l'API est permise).
        if (function_exists('opcache_invalidate') && is_file($file)) {
            $ok = @opcache_invalidate($file, true);
            $L[] = 'opcache_invalidate(module) : ' . ($ok ? 'OK — rechargez l\'admin, ça peut suffire' : 'échec');
        }

        echo implode("\n", $L) . "\n";
        echo "\n👉 Supprimez ensuite controllers/Sia_diag.php et Sia_reset.php.\n";
    }
}

<?php
/**
 * Plugin Name:       School IA — Serveur de licences
 * Plugin URI:        https://maestrodan.art
 * Description:       Autorité de licences maison pour les produits Maestro Dan (dont « School IA »). Génère et révoque les clés, gère les abonnements annuels et les activations par domaine, et livre les mises à jour aux sites sous licence. À installer sur le site de l'éditeur (ex. maestrodan.art) — pas sur le site client.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Maestro Dan
 * Author URI:        https://maestrodan.art
 * License:           GPL-2.0-or-later
 * Text Domain:       sia-license-server
 */

defined('ABSPATH') || exit;

define('SIA_LS_VERSION', '1.0.0');
define('SIA_LS_FILE', __FILE__);
define('SIA_LS_DIR', plugin_dir_path(__FILE__));
define('SIA_LS_REST_NS', 'sia-license/v1');

// Autoloader PSR-4 minimal : SiaLicenseServer\ => includes/.
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'SiaLicenseServer\\')) {
        return;
    }
    $relative = substr($class, strlen('SiaLicenseServer\\'));
    $path = SIA_LS_DIR . 'includes/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

register_activation_hook(__FILE__, ['\SiaLicenseServer\Activator', 'activate']);

add_action('rest_api_init', static function (): void {
    (new \SiaLicenseServer\RestApi())->register();
});

add_action('admin_menu', static function (): void {
    (new \SiaLicenseServer\AdminPage())->register();
});

add_action('admin_post_sia_ls_create', ['\SiaLicenseServer\AdminPage', 'handleCreate']);
add_action('admin_post_sia_ls_license_action', ['\SiaLicenseServer\AdminPage', 'handleLicenseAction']);
add_action('admin_post_sia_ls_release', ['\SiaLicenseServer\AdminPage', 'handleRelease']);

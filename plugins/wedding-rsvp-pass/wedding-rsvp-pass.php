<?php
/**
 * Plugin Name:       Wedding RSVP Pass
 * Plugin URI:        https://example.com/wedding-rsvp-pass
 * Description:       Gestion des invités pour les mariés : chaque marié(e) gère sa liste, envoie les invitations, les invités confirment sur une page élégante, un carton d'invitation avec QR code est généré et validé le jour J par un scanner qui fonctionne hors connexion.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Wedding RSVP Pass
 * License:           GPL-2.0-or-later
 * Text Domain:       wedding-rsvp-pass
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WRP_VERSION', '1.0.0' );
define( 'WRP_DB_VERSION', '1.0.0' );
define( 'WRP_FILE', __FILE__ );
define( 'WRP_DIR', plugin_dir_path( __FILE__ ) );
define( 'WRP_URL', plugin_dir_url( __FILE__ ) );

require_once WRP_DIR . 'includes/class-wrp-db.php';
require_once WRP_DIR . 'includes/class-wrp-settings.php';
require_once WRP_DIR . 'includes/class-wrp-roles.php';
require_once WRP_DIR . 'includes/class-wrp-guests.php';
require_once WRP_DIR . 'includes/class-wrp-mailer.php';
require_once WRP_DIR . 'includes/class-wrp-public.php';
require_once WRP_DIR . 'includes/class-wrp-rest.php';
require_once WRP_DIR . 'includes/class-wrp-admin.php';

register_activation_hook( __FILE__, array( 'WRP_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WRP_Plugin', 'deactivate' ) );

/**
 * Plugin bootstrap.
 */
final class WRP_Plugin {

	public static function init() {
		load_plugin_textdomain( 'wedding-rsvp-pass', false, dirname( plugin_basename( WRP_FILE ) ) . '/languages' );

		WRP_DB::maybe_upgrade();
		WRP_Roles::init();
		WRP_Public::init();
		WRP_REST::init();

		if ( is_admin() ) {
			WRP_Admin::init();
		}
	}

	public static function activate() {
		WRP_DB::install();
		WRP_Roles::install();
		WRP_Settings::ensure_scanner_key();
		WRP_Public::add_rewrite_rules();
		flush_rewrite_rules();
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}
}
add_action( 'plugins_loaded', array( 'WRP_Plugin', 'init' ) );

/**
 * Template helper: escape + echo a setting.
 *
 * @param string $key Setting key.
 */
function wrp_setting( $key ) {
	return WRP_Settings::get( $key );
}

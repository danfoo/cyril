<?php
/**
 * Plugin Name:       Écurie Prestige
 * Description:       Présentez les chevaux de votre écurie de courses avec des widgets Elementor haut de gamme : grille, mosaïque, carrousel cinéma animé, cheval vedette et fiche détaillée (pedigree, performances, palmarès).
 * Version:           1.2.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Écurie Prestige
 * License:           GPL-2.0-or-later
 * Text Domain:       ecurie-prestige
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EP_VERSION', '1.2.0' );
define( 'EP_FILE', __FILE__ );
define( 'EP_DIR', plugin_dir_path( __FILE__ ) );
define( 'EP_URL', plugin_dir_url( __FILE__ ) );

require_once EP_DIR . 'includes/class-ep-cpt.php';
require_once EP_DIR . 'includes/class-ep-render.php';
require_once EP_DIR . 'includes/class-ep-shortcodes.php';
require_once EP_DIR . 'includes/class-ep-admin.php';
require_once EP_DIR . 'includes/class-ep-elementor.php';

add_action(
	'plugins_loaded',
	static function () {
		load_plugin_textdomain( 'ecurie-prestige', false, dirname( plugin_basename( EP_FILE ) ) . '/languages' );
	}
);

EP_CPT::init();
EP_Render::init();
EP_Shortcodes::init();
EP_Admin::init();
EP_Elementor::init();

register_activation_hook(
	__FILE__,
	static function () {
		EP_CPT::register();
		flush_rewrite_rules();
	}
);

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

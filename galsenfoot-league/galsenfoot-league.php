<?php
/**
 * Plugin Name:       GalsenFoot League
 * Plugin URI:        https://galsenfoot.sn/
 * Description:       Gestion complète de championnats de football : compétitions, saisons, clubs, joueurs, matchs, classements et statistiques. Saisie manuelle ou synchronisation automatique via API-Football. Widgets Elementor et shortcodes inclus.
 * Version:           1.2.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            GalsenFoot
 * License:           GPL-2.0-or-later
 * Text Domain:       galsenfoot-league
 * Domain Path:       /languages
 *
 * @package GalsenFootLeague
 */

defined( 'ABSPATH' ) || exit;

define( 'GSF_VERSION', '1.2.0' );
define( 'GSF_FILE', __FILE__ );
define( 'GSF_DIR', plugin_dir_path( __FILE__ ) );
define( 'GSF_URL', plugin_dir_url( __FILE__ ) );

/**
 * Asset version: plugin version + file modification time, so browsers, cache plugins
 * and CDNs always fetch the new file after an update.
 *
 * @param string $relative Path relative to the plugin folder.
 * @return string
 */
function gsf_asset_version( $relative ) {
	$file = GSF_DIR . ltrim( $relative, '/' );
	return file_exists( $file ) ? GSF_VERSION . '.' . filemtime( $file ) : GSF_VERSION;
}

require_once GSF_DIR . 'includes/helpers.php';
require_once GSF_DIR . 'includes/post-types.php';
require_once GSF_DIR . 'includes/stats.php';
require_once GSF_DIR . 'includes/render.php';
require_once GSF_DIR . 'includes/shortcodes.php';
require_once GSF_DIR . 'includes/schema.php';
require_once GSF_DIR . 'includes/rest.php';
require_once GSF_DIR . 'includes/api.php';
require_once GSF_DIR . 'includes/api-national.php';

if ( is_admin() ) {
	require_once GSF_DIR . 'includes/admin-meta.php';
	require_once GSF_DIR . 'includes/admin-settings.php';
	require_once GSF_DIR . 'includes/demo.php';
}

add_action(
	'init',
	static function () {
		load_plugin_textdomain( 'galsenfoot-league', false, dirname( plugin_basename( GSF_FILE ) ) . '/languages' );
	}
);

/**
 * Front-end assets. Registered early, enqueued only when a GSF block is rendered.
 */
add_action(
	'wp_enqueue_scripts',
	static function () {
		wp_register_style( 'gsf', GSF_URL . 'assets/css/gsf.css', array(), gsf_asset_version( 'assets/css/gsf.css' ) );
		wp_register_script( 'gsf', GSF_URL . 'assets/js/gsf.js', array(), gsf_asset_version( 'assets/js/gsf.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
		wp_localize_script(
			'gsf',
			'GSF',
			array(
				'rest' => esc_url_raw( rest_url( 'gsf/v1/' ) ),
				'i18n' => array(
					'd' => __( 'j', 'galsenfoot-league' ),
					'h' => __( 'h', 'galsenfoot-league' ),
					'm' => __( 'min', 'galsenfoot-league' ),
					's' => __( 's', 'galsenfoot-league' ),
				),
			)
		);
		// Styles are tiny and the blocks appear on most pages: load the CSS in <head> to avoid layout shift.
		wp_enqueue_style( 'gsf' );
	}
);

/**
 * Elementor integration.
 */
add_action(
	'elementor/elements/categories_registered',
	static function ( $manager ) {
		$manager->add_category(
			'galsenfoot',
			array(
				'title' => __( 'GalsenFoot', 'galsenfoot-league' ),
				'icon'  => 'eicon-trophy',
			)
		);
	},
	5
);
add_action(
	'elementor/widgets/register',
	static function ( $widgets_manager ) {
		require_once GSF_DIR . 'includes/elementor-widgets.php';
		foreach ( gsf_elementor_widget_classes() as $class ) {
			$widgets_manager->register( new $class() );
		}
	}
);

register_activation_hook(
	__FILE__,
	static function () {
		gsf_register_content_types();
		flush_rewrite_rules();
		gsf_api_reschedule();
	}
);
register_deactivation_hook(
	__FILE__,
	static function () {
		wp_clear_scheduled_hook( 'gsf_api_cron' );
		wp_clear_scheduled_hook( 'gsf_api_continue' );
		flush_rewrite_rules();
	}
);

<?php
/**
 * Éternelle – wedding theme.
 *
 * @package Eternelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ETERNELLE_VERSION', '1.1.1' );
define( 'ETERNELLE_DIR', get_template_directory() );
define( 'ETERNELLE_URI', get_template_directory_uri() );

require ETERNELLE_DIR . '/inc/helpers.php';
require ETERNELLE_DIR . '/inc/customizer.php';
require ETERNELLE_DIR . '/inc/icons.php';
require ETERNELLE_DIR . '/inc/elementor.php';

/**
 * Theme setup.
 */
function eternelle_setup() {
	load_theme_textdomain( 'eternelle', ETERNELLE_DIR . '/languages' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
	add_theme_support( 'custom-logo', array( 'height' => 120, 'width' => 120, 'flex-width' => true, 'flex-height' => true ) );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'editor-styles' );
	// Elementor: let page templates span the full width.
	add_theme_support( 'elementor' );
	add_editor_style( 'assets/css/editor.css' );

	add_image_size( 'eternelle-portrait', 700, 900, true );
	add_image_size( 'eternelle-hero', 2200, 1400, false );

	register_nav_menus(
		array(
			'primary' => __( 'Menu principal', 'eternelle' ),
		)
	);
}
add_action( 'after_setup_theme', 'eternelle_setup' );

/**
 * Fonts URL (Google Fonts). Filterable to self-host.
 */
function eternelle_fonts_url() {
	return apply_filters( 'eternelle_fonts_url', 'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,400&family=Great+Vibes&family=Montserrat:wght@300;400;500;600&display=swap' );
}

/**
 * Front-end assets.
 */
function eternelle_assets() {
	$css_ver = ETERNELLE_VERSION . '.' . filemtime( ETERNELLE_DIR . '/assets/css/theme.css' );
	$js_ver  = ETERNELLE_VERSION . '.' . filemtime( ETERNELLE_DIR . '/assets/js/theme.js' );

	wp_enqueue_style( 'eternelle-fonts', eternelle_fonts_url(), array(), null );
	wp_enqueue_style( 'eternelle', ETERNELLE_URI . '/assets/css/theme.css', array(), $css_ver );
	wp_add_inline_style( 'eternelle', eternelle_css_vars() );

	wp_enqueue_script( 'eternelle', ETERNELLE_URI . '/assets/js/theme.js', array(), $js_ver, true );
	wp_localize_script(
		'eternelle',
		'ETERNELLE',
		array(
			'date'      => eternelle_timestamp() * 1000,
			'petals'    => (bool) eternelle_mod( 'et_fx_petals' ),
			'sparkles'  => (bool) eternelle_mod( 'et_fx_sparkles' ),
			'cursor'    => (bool) eternelle_mod( 'et_fx_cursor' ),
			'hearts'    => (bool) eternelle_mod( 'et_fx_hearts' ),
			'envelope'  => (bool) eternelle_mod( 'et_envelope' ) && is_front_page(),
			'monogram'  => eternelle_initials(),
			'i18n'      => array(
				'days'    => __( 'Jours', 'eternelle' ),
				'hours'   => __( 'Heures', 'eternelle' ),
				'minutes' => __( 'Minutes', 'eternelle' ),
				'seconds' => __( 'Secondes', 'eternelle' ),
				'married' => __( 'Nous sommes mariés ! ♥', 'eternelle' ),
				'close'   => __( 'Fermer', 'eternelle' ),
			),
		)
	);

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'eternelle_assets' );

/**
 * Preconnect to Google Fonts.
 */
function eternelle_resource_hints( $urls, $relation ) {
	if ( 'preconnect' === $relation ) {
		$urls[] = 'https://fonts.googleapis.com';
		$urls[] = array(
			'href' => 'https://fonts.gstatic.com',
			'crossorigin',
		);
	}
	return $urls;
}
add_filter( 'wp_resource_hints', 'eternelle_resource_hints', 10, 2 );

/**
 * CSS custom properties from the Customizer.
 */
function eternelle_css_vars() {
	$gold  = sanitize_hex_color( eternelle_mod( 'et_color_gold' ) ) ?: '#b8925a';
	$blush = sanitize_hex_color( eternelle_mod( 'et_color_blush' ) ) ?: '#f4e1dc';
	$ink   = sanitize_hex_color( eternelle_mod( 'et_color_ink' ) ) ?: '#2e2420';
	$sage  = sanitize_hex_color( eternelle_mod( 'et_color_sage' ) ) ?: '#a3b18a';
	return sprintf( ':root{--et-gold:%1$s;--et-blush:%2$s;--et-ink:%3$s;--et-sage:%4$s}', $gold, $blush, $ink, $sage );
}

/**
 * Body classes.
 */
function eternelle_body_class( $classes ) {
	if ( is_front_page() ) {
		$classes[] = 'et-one-page';
	}
	if ( eternelle_mod( 'et_envelope' ) && is_front_page() ) {
		$classes[] = 'et-has-envelope';
	}
	return $classes;
}
add_filter( 'body_class', 'eternelle_body_class' );

/**
 * Hand our wedding details to the Wedding RSVP Pass plugin as defaults, so
 * the couple fills them once in the Customizer.
 */
function eternelle_wrp_defaults( $defaults ) {
	$map = array(
		'partner_a_name' => 'et_partner_a',
		'partner_b_name' => 'et_partner_b',
		'wedding_date'   => 'et_date',
		'wedding_time'   => 'et_time',
		'venue_name'     => 'et_venue',
		'venue_address'  => 'et_address',
		'map_url'        => 'et_map_url',
		'dress_code'     => 'et_dress_code',
		'color_accent'   => 'et_color_gold',
		'color_blush'    => 'et_color_blush',
		'color_dark'     => 'et_color_ink',
	);
	foreach ( $map as $plugin_key => $mod ) {
		$val = eternelle_mod( $mod );
		if ( '' !== $val && null !== $val ) {
			$defaults[ $plugin_key ] = $val;
		}
	}
	return $defaults;
}
add_filter( 'wrp_settings_defaults', 'eternelle_wrp_defaults' );

/**
 * Excerpt.
 */
add_filter(
	'excerpt_more',
	function () {
		return '…';
	}
);

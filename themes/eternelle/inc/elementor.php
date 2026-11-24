<?php
/**
 * Elementor compatibility.
 *
 * - Theme Builder locations (header, footer, single, archive) for Elementor Pro.
 * - Pages built with Elementor are rendered full width, without the theme title.
 * - "Elementor Canvas" / "Elementor Full Width" templates are supported.
 * - Widgets in the « Éternelle – Mariage » category (hero, countdown, sections…).
 *
 * @package Eternelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Is Elementor active?
 */
function eternelle_has_elementor() {
	return did_action( 'elementor/loaded' ) && class_exists( '\Elementor\Plugin' );
}

/**
 * Was this post built with Elementor?
 *
 * @param int $post_id Post ID (default: current).
 */
function eternelle_built_with_elementor( $post_id = 0 ) {
	if ( ! eternelle_has_elementor() ) {
		return false;
	}
	$post_id = $post_id ? $post_id : get_the_ID();
	if ( ! $post_id ) {
		return false;
	}
	$document = \Elementor\Plugin::$instance->documents->get( $post_id );
	return $document && $document->is_built_with_elementor();
}

/**
 * Render an Elementor Pro Theme Builder location if one is assigned.
 *
 * @param string $location header|footer|single|archive.
 * @return bool True if Elementor printed the location.
 */
function eternelle_elementor_location( $location ) {
	return function_exists( 'elementor_theme_do_location' ) && elementor_theme_do_location( $location );
}

/**
 * What the front page shows: theme sections, Elementor content, or both.
 *
 * @return string theme|elementor|hero_elementor|theme_elementor
 */
function eternelle_front_mode() {
	$mode    = eternelle_mod( 'et_front_mode' );
	$page_id = 'page' === get_option( 'show_on_front' ) ? (int) get_option( 'page_on_front' ) : 0;
	$built   = $page_id && eternelle_built_with_elementor( $page_id );
	if ( 'auto' === $mode || ! $mode ) {
		return $built ? 'elementor' : 'theme';
	}
	if ( ! $page_id && 'theme' !== $mode ) {
		return 'theme';
	}
	return $mode;
}

/**
 * Does the current view start with the theme's animated hero (transparent header)?
 */
function eternelle_hero_on_top() {
	if ( ! is_front_page() ) {
		return false;
	}
	return in_array( eternelle_front_mode(), array( 'theme', 'hero_elementor', 'theme_elementor' ), true );
}

/* ------------------------------------------------------------------
 * Hooks
 * ---------------------------------------------------------------- */

add_action(
	'elementor/theme/register_locations',
	function ( $manager ) {
		$manager->register_all_core_location();
	}
);

add_action(
	'elementor/elements/categories_registered',
	function ( $elements_manager ) {
		$elements_manager->add_category(
			'eternelle',
			array(
				'title' => __( 'Éternelle – Mariage', 'eternelle' ),
				'icon'  => 'eicon-heart',
			)
		);
	}
);

add_action(
	'elementor/widgets/register',
	function ( $widgets_manager ) {
		require_once ETERNELLE_DIR . '/inc/elementor-widgets.php';
		foreach ( eternelle_elementor_widget_classes() as $class ) {
			$widgets_manager->register( new $class() );
		}
	}
);

// Our JS must run again when Elementor (re)renders a widget in the editor.
add_action(
	'elementor/frontend/after_enqueue_scripts',
	function () {
		wp_add_inline_script(
			'eternelle',
			'window.addEventListener("elementor/frontend/init",function(){if(!window.elementorFrontend||!window.EternelleInit){return;}elementorFrontend.hooks.addAction("frontend/element_ready/global",function($el){window.EternelleInit($el[0]);});});'
		);
	}
);

add_filter(
	'body_class',
	function ( $classes ) {
		$classes[] = eternelle_hero_on_top() ? 'et-hero-top' : 'et-solid-header';
		if ( is_singular() && eternelle_built_with_elementor() ) {
			$classes[] = 'et-elementor-page';
		}
		return $classes;
	}
);

/**
 * Use the theme fonts & colours instead of Elementor's default blue / Roboto
 * ("Disable default colors / fonts" in Elementor → Settings). Done once, even if
 * Elementor is installed after the theme; the couple can switch it back.
 */
function eternelle_elementor_defaults() {
	if ( get_option( 'eternelle_el_defaults_done' ) ) {
		return;
	}
	update_option( 'elementor_disable_color_schemes', 'yes' );
	update_option( 'elementor_disable_typography_schemes', 'yes' );
	update_option( 'eternelle_el_defaults_done', 1 );
}
add_action(
	'elementor/init',
	function () {
		if ( is_admin() ) {
			eternelle_elementor_defaults();
		}
	}
);
add_action(
	'after_switch_theme',
	function () {
		delete_option( 'eternelle_el_defaults_done' );
		if ( eternelle_has_elementor() ) {
			eternelle_elementor_defaults();
		}
	}
);

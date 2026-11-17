<?php
/**
 * Elementor widgets provided by the theme: news hero, posts grid, ad location.
 *
 * @package GalsenFoot
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'elementor/elements/categories_registered',
	static function ( $manager ) {
		$manager->add_category(
			'galsenfoot',
			array(
				'title' => __( 'GalsenFoot', 'galsenfoot' ),
				'icon'  => 'eicon-trophy',
			)
		);
	},
	4
);

add_action(
	'elementor/widgets/register',
	static function ( $widgets_manager ) {
		require_once GF_DIR . '/inc/elementor-widgets.php';
		$widgets_manager->register( new GF_Widget_Hero_News() );
		$widgets_manager->register( new GF_Widget_Posts() );
		$widgets_manager->register( new GF_Widget_Ad() );
	}
);

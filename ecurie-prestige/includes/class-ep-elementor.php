<?php
/**
 * Intégration Elementor : catégorie, widgets et rechargement dans l’éditeur.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EP_Elementor {

	public static function init() {
		add_action( 'elementor/elements/categories_registered', array( __CLASS__, 'category' ) );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'widgets' ) );
		add_action( 'elementor/frontend/after_register_scripts', array( 'EP_Render', 'register_assets' ) );
		add_action( 'elementor/frontend/after_register_styles', array( 'EP_Render', 'register_assets' ) );
	}

	public static function category( $elements_manager ) {
		$elements_manager->add_category(
			'ecurie-prestige',
			array(
				'title' => __( 'Écurie Prestige', 'ecurie-prestige' ),
				'icon'  => 'eicon-star-o',
			)
		);
	}

	public static function widgets( $widgets_manager ) {
		require_once EP_DIR . 'includes/widgets/class-ep-widget-base.php';
		require_once EP_DIR . 'includes/widgets/class-ep-widget-grid.php';
		require_once EP_DIR . 'includes/widgets/class-ep-widget-carousel.php';
		require_once EP_DIR . 'includes/widgets/class-ep-widget-featured.php';

		$widgets_manager->register( new EP_Widget_Grid() );
		$widgets_manager->register( new EP_Widget_Carousel() );
		$widgets_manager->register( new EP_Widget_Featured() );
	}
}

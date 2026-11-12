<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;

class EP_Widget_Showcase extends EP_Widget_Base {

	public function get_name() {
		return 'ep-showcase';
	}

	public function get_title() {
		return __( 'Chevaux — Carrousel cinéma', 'ecurie-prestige' );
	}

	public function get_icon() {
		return 'eicon-slider-3d';
	}

	protected function register_controls() {
		$this->register_header_controls();
		$this->register_query_controls();

		$this->start_controls_section( 'section_layout', array( 'label' => __( 'Animation', 'ecurie-prestige' ) ) );
		$this->add_responsive_control(
			'slide_width',
			array(
				'label'          => __( 'Largeur du cheval central', 'ecurie-prestige' ),
				'type'           => Controls_Manager::SLIDER,
				'size_units'     => array( '%' ),
				'range'          => array( '%' => array( 'min' => 24, 'max' => 90 ) ),
				'default'        => array( 'size' => 34, 'unit' => '%' ),
				'tablet_default' => array( 'size' => 56, 'unit' => '%' ),
				'mobile_default' => array( 'size' => 80, 'unit' => '%' ),
				'selectors'      => array( '{{WRAPPER}} .ep' => '--ep-slide-w: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_control( 'autoplay', array( 'label' => __( 'Défilement automatique', 'ecurie-prestige' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes', 'return_value' => 'yes', 'description' => __( 'En pause au survol et pendant la lecture d’une fiche. Désactivé dans l’éditeur.', 'ecurie-prestige' ) ) );
		$this->add_control( 'autoplay_delay', array( 'label' => __( 'Durée par cheval (secondes)', 'ecurie-prestige' ), 'type' => Controls_Manager::NUMBER, 'min' => 2, 'max' => 30, 'step' => 0.5, 'default' => 6, 'condition' => array( 'autoplay' => 'yes' ) ) );
		$this->add_control( 'ghost', array( 'label' => __( 'Nom géant en filigrane', 'ecurie-prestige' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes', 'return_value' => 'yes' ) );
		$this->add_control( 'filters', array( 'label' => __( 'Filtres par discipline', 'ecurie-prestige' ), 'type' => Controls_Manager::SWITCHER, 'default' => '', 'return_value' => 'yes' ) );
		$this->end_controls_section();

		$this->register_card_controls( false );
		$this->register_theme_controls();
	}

	protected function render() {
		$s                      = $this->get_settings_for_display();
		$args                   = $this->collection_args( $s );
		$args['layout']         = 'showcase';
		$args['autoplay']       = $this->yes( $s, 'autoplay' );
		$args['autoplay_delay'] = ! empty( $s['autoplay_delay'] ) ? (float) $s['autoplay_delay'] : 6;
		$args['ghost']          = $this->yes( $s, 'ghost' );
		echo EP_Render::collection( $args ); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML échappé dans EP_Render.
	}
}

<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;

class EP_Widget_Carousel extends EP_Widget_Base {

	public function get_name() {
		return 'ep-carousel';
	}

	public function get_title() {
		return __( 'Chevaux — Carrousel', 'ecurie-prestige' );
	}

	public function get_icon() {
		return 'eicon-slider-push';
	}

	protected function register_controls() {
		$this->register_header_controls();
		$this->register_query_controls();

		$this->start_controls_section( 'section_layout', array( 'label' => __( 'Mise en page', 'ecurie-prestige' ) ) );
		$this->add_responsive_control(
			'per_view',
			array(
				'label'          => __( 'Cartes visibles', 'ecurie-prestige' ),
				'type'           => Controls_Manager::NUMBER,
				'min'            => 1,
				'max'            => 6,
				'step'           => 0.05,
				'default'        => 3.2,
				'tablet_default' => 2.2,
				'mobile_default' => 1.15,
				'description'    => __( 'Une valeur décimale (3,2) laisse deviner la carte suivante.', 'ecurie-prestige' ),
				'selectors'      => array( '{{WRAPPER}} .ep' => '--ep-per-view: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'gap',
			array(
				'label'      => __( 'Espacement', 'ecurie-prestige' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
				'selectors'  => array( '{{WRAPPER}} .ep' => '--ep-gap: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_control( 'filters', array( 'label' => __( 'Filtres par discipline', 'ecurie-prestige' ), 'type' => Controls_Manager::SWITCHER, 'default' => '', 'return_value' => 'yes' ) );
		$this->end_controls_section();

		$this->register_card_controls();
		$this->register_theme_controls();
	}

	protected function render() {
		$args           = $this->collection_args( $this->get_settings_for_display() );
		$args['layout'] = 'carousel';
		echo EP_Render::collection( $args ); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML échappé dans EP_Render.
	}
}

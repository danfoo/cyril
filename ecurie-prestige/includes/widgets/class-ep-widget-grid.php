<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;

class EP_Widget_Grid extends EP_Widget_Base {

	public function get_name() {
		return 'ep-grid';
	}

	public function get_title() {
		return __( 'Chevaux — Grille', 'ecurie-prestige' );
	}

	public function get_icon() {
		return 'eicon-gallery-grid';
	}

	protected function register_controls() {
		$this->register_header_controls();
		$this->register_query_controls();

		$this->start_controls_section( 'section_layout', array( 'label' => __( 'Mise en page', 'ecurie-prestige' ) ) );
		$this->add_control(
			'layout',
			array(
				'label'       => __( 'Disposition', 'ecurie-prestige' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'grid',
				'options'     => array(
					'grid'   => __( 'Grille régulière', 'ecurie-prestige' ),
					'mosaic' => __( 'Mosaïque — premier cheval en grand', 'ecurie-prestige' ),
				),
			)
		);
		$this->add_responsive_control(
			'columns',
			array(
				'label'          => __( 'Colonnes', 'ecurie-prestige' ),
				'type'           => Controls_Manager::SELECT,
				'default'        => '3',
				'tablet_default' => '2',
				'mobile_default' => '1',
				'options'        => array( '1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5' ),
				'selectors'      => array( '{{WRAPPER}} .ep' => '--ep-cols: {{VALUE}};' ),
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
		$this->add_control( 'filters', array( 'label' => __( 'Filtres par discipline', 'ecurie-prestige' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes', 'return_value' => 'yes' ) );
		$this->end_controls_section();

		$this->register_card_controls();
		$this->register_theme_controls();
	}

	protected function render() {
		$s              = $this->get_settings_for_display();
		$args           = $this->collection_args( $s );
		$args['layout'] = isset( $s['layout'] ) && 'mosaic' === $s['layout'] ? 'mosaic' : 'grid';
		echo EP_Render::collection( $args ); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML échappé dans EP_Render.
	}
}

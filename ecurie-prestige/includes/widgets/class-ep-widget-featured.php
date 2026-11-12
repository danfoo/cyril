<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;

class EP_Widget_Featured extends EP_Widget_Base {

	public function get_name() {
		return 'ep-featured';
	}

	public function get_title() {
		return __( 'Cheval vedette', 'ecurie-prestige' );
	}

	public function get_icon() {
		return 'eicon-featured-image';
	}

	protected function register_controls() {
		$this->start_controls_section( 'section_content', array( 'label' => __( 'Cheval', 'ecurie-prestige' ) ) );
		$this->add_control(
			'horse',
			array(
				'label'       => __( 'Cheval à mettre en avant', 'ecurie-prestige' ),
				'type'        => Controls_Manager::SELECT2,
				'label_block' => true,
				'options'     => self::horse_options(),
				'description' => __( 'Laissez vide pour afficher automatiquement le premier cheval vedette.', 'ecurie-prestige' ),
			)
		);
		$this->add_control( 'eyebrow', array( 'label' => __( 'Surtitre', 'ecurie-prestige' ), 'type' => Controls_Manager::TEXT, 'placeholder' => __( 'Discipline — statut', 'ecurie-prestige' ), 'dynamic' => array( 'active' => true ) ) );
		$this->add_control(
			'reverse',
			array(
				'label'   => __( 'Position de la photo', 'ecurie-prestige' ),
				'type'    => Controls_Manager::CHOOSE,
				'options' => array(
					''    => array( 'title' => __( 'Gauche', 'ecurie-prestige' ), 'icon' => 'eicon-h-align-left' ),
					'yes' => array( 'title' => __( 'Droite', 'ecurie-prestige' ), 'icon' => 'eicon-h-align-right' ),
				),
				'default' => '',
				'toggle'  => false,
			)
		);
		$this->add_control(
			'ratio',
			array(
				'label'     => __( 'Format de la photo', 'ecurie-prestige' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => '4 / 5',
				'options'   => array(
					'3 / 4' => __( 'Portrait (3:4)', 'ecurie-prestige' ),
					'4 / 5' => __( 'Portrait doux (4:5)', 'ecurie-prestige' ),
					'1 / 1' => __( 'Carré (1:1)', 'ecurie-prestige' ),
					'4 / 3' => __( 'Paysage (4:3)', 'ecurie-prestige' ),
				),
				'selectors' => array( '{{WRAPPER}} .ep' => '--ep-ratio: {{VALUE}};' ),
			)
		);
		$this->add_control( 'show_stats', array( 'label' => __( 'Statistiques', 'ecurie-prestige' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes', 'return_value' => 'yes' ) );
		$this->add_control( 'show_specs', array( 'label' => __( 'Caractéristiques et origines', 'ecurie-prestige' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes', 'return_value' => 'yes' ) );
		$this->add_control(
			'click',
			array(
				'label'     => __( 'Bouton', 'ecurie-prestige' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'modal',
				'separator' => 'before',
				'options'   => array(
					'modal' => __( 'Ouvrir la fiche en fenêtre', 'ecurie-prestige' ),
					'page'  => __( 'Aller sur la page du cheval', 'ecurie-prestige' ),
					'none'  => __( 'Pas de bouton', 'ecurie-prestige' ),
				),
			)
		);
		$this->add_control( 'cta', array( 'label' => __( 'Texte du bouton', 'ecurie-prestige' ), 'type' => Controls_Manager::TEXT, 'default' => __( 'Découvrir sa fiche', 'ecurie-prestige' ), 'condition' => array( 'click!' => 'none' ) ) );
		$this->end_controls_section();

		$this->register_theme_controls( false );
	}

	protected function render() {
		$s    = $this->get_settings_for_display();
		$html = EP_Render::featured(
			! empty( $s['horse'] ) ? absint( $s['horse'] ) : 0,
			array(
				'theme'      => isset( $s['theme'] ) ? $s['theme'] : 'dark',
				'background' => $this->yes( $s, 'background' ),
				'reverse'    => $this->yes( $s, 'reverse' ),
				'eyebrow'    => isset( $s['eyebrow'] ) ? $s['eyebrow'] : '',
				'cta'        => isset( $s['cta'] ) ? $s['cta'] : '',
				'click'      => isset( $s['click'] ) ? $s['click'] : 'modal',
				'show_stats' => $this->yes( $s, 'show_stats' ),
				'show_specs' => $this->yes( $s, 'show_specs' ),
			)
		);
		if ( '' === $html && \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			$html = '<div class="ep ep--dark ep--bg"><p class="ep-empty">' . esc_html__( 'Ajoutez un premier cheval dans Écurie → Ajouter pour l’afficher ici.', 'ecurie-prestige' ) . '</p></div>';
		}
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- HTML échappé dans EP_Render.
	}
}

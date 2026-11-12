<?php
/**
 * Base commune aux widgets Elementor de l’écurie.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

abstract class EP_Widget_Base extends \Elementor\Widget_Base {

	public function get_categories() {
		return array( 'ecurie-prestige' );
	}

	public function get_keywords() {
		return array( 'écurie', 'ecurie', 'cheval', 'chevaux', 'course', 'hippique', 'équipe', 'horse', 'team' );
	}

	public function get_style_depends() {
		return array( 'ecurie-prestige' );
	}

	public function get_script_depends() {
		return array( 'ecurie-prestige' );
	}

	/** Le contenu dépend des chevaux publiés : pas de mise en cache du rendu. */
	protected function is_dynamic_content(): bool {
		return true;
	}

	protected static function horse_options() {
		$options = array();
		$posts   = get_posts(
			array(
				'post_type'      => EP_CPT::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 300,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		foreach ( $posts as $post ) {
			$options[ $post->ID ] = get_the_title( $post );
		}
		return $options;
	}

	protected static function discipline_options() {
		$options = array();
		$terms   = get_terms( array( 'taxonomy' => EP_CPT::TAXONOMY, 'hide_empty' => false ) );
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$options[ $term->term_id ] = $term->name;
			}
		}
		return $options;
	}

	protected function yes( array $settings, $key ) {
		return isset( $settings[ $key ] ) && 'yes' === $settings[ $key ];
	}

	/* ------------------------------------------------------------------ */

	protected function register_header_controls() {
		$this->start_controls_section( 'section_header', array( 'label' => __( 'En-tête', 'ecurie-prestige' ) ) );
		$this->add_control( 'eyebrow', array( 'label' => __( 'Surtitre', 'ecurie-prestige' ), 'type' => Controls_Manager::TEXT, 'default' => __( 'L’écurie', 'ecurie-prestige' ), 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'title', array( 'label' => __( 'Titre', 'ecurie-prestige' ), 'type' => Controls_Manager::TEXT, 'default' => __( 'Nos champions', 'ecurie-prestige' ), 'label_block' => true, 'description' => __( 'Utilisez <em>…</em> pour mettre un mot en italique doré.', 'ecurie-prestige' ), 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'intro', array( 'label' => __( 'Introduction', 'ecurie-prestige' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 3, 'dynamic' => array( 'active' => true ) ) );
		$this->add_control(
			'align',
			array(
				'label'   => __( 'Alignement', 'ecurie-prestige' ),
				'type'    => Controls_Manager::CHOOSE,
				'options' => array(
					'left'   => array( 'title' => __( 'Gauche', 'ecurie-prestige' ), 'icon' => 'eicon-text-align-left' ),
					'center' => array( 'title' => __( 'Centre', 'ecurie-prestige' ), 'icon' => 'eicon-text-align-center' ),
				),
				'default' => 'left',
				'toggle'  => false,
			)
		);
		$this->end_controls_section();
	}

	protected function register_query_controls() {
		$this->start_controls_section( 'section_query', array( 'label' => __( 'Sélection des chevaux', 'ecurie-prestige' ) ) );
		$this->add_control(
			'source',
			array(
				'label'   => __( 'Afficher', 'ecurie-prestige' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'all',
				'options' => array(
					'all'        => __( 'Tous les chevaux', 'ecurie-prestige' ),
					'discipline' => __( 'Par discipline', 'ecurie-prestige' ),
					'vedette'    => __( 'Chevaux vedettes', 'ecurie-prestige' ),
					'manual'     => __( 'Sélection manuelle', 'ecurie-prestige' ),
				),
			)
		);
		$this->add_control( 'disciplines', array( 'label' => __( 'Disciplines', 'ecurie-prestige' ), 'type' => Controls_Manager::SELECT2, 'multiple' => true, 'label_block' => true, 'options' => self::discipline_options(), 'condition' => array( 'source' => 'discipline' ) ) );
		$this->add_control( 'ids', array( 'label' => __( 'Chevaux', 'ecurie-prestige' ), 'type' => Controls_Manager::SELECT2, 'multiple' => true, 'label_block' => true, 'options' => self::horse_options(), 'condition' => array( 'source' => 'manual' ) ) );
		$statuts = EP_CPT::statuts();
		unset( $statuts[''] );
		$this->add_control( 'statuts', array( 'label' => __( 'Limiter aux statuts', 'ecurie-prestige' ), 'type' => Controls_Manager::SELECT2, 'multiple' => true, 'label_block' => true, 'options' => $statuts ) );
		$this->add_control( 'number', array( 'label' => __( 'Nombre maximum', 'ecurie-prestige' ), 'type' => Controls_Manager::NUMBER, 'default' => 12, 'min' => 1, 'max' => 100 ) );
		$this->add_control(
			'orderby',
			array(
				'label'   => __( 'Trier par', 'ecurie-prestige' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'menu_order',
				'options' => array(
					'menu_order' => __( 'Ordre personnalisé', 'ecurie-prestige' ),
					'title'      => __( 'Nom', 'ecurie-prestige' ),
					'date'       => __( 'Date d’ajout', 'ecurie-prestige' ),
					'victoires'  => __( 'Victoires', 'ecurie-prestige' ),
					'gains'      => __( 'Gains', 'ecurie-prestige' ),
					'rand'       => __( 'Aléatoire', 'ecurie-prestige' ),
				),
			)
		);
		$this->add_control( 'order', array( 'label' => __( 'Sens', 'ecurie-prestige' ), 'type' => Controls_Manager::SELECT, 'default' => 'ASC', 'options' => array( 'ASC' => __( 'Croissant', 'ecurie-prestige' ), 'DESC' => __( 'Décroissant', 'ecurie-prestige' ) ), 'condition' => array( 'orderby!' => 'rand' ) ) );
		$this->end_controls_section();
	}

	protected function register_card_controls( $with_skin = true ) {
		$this->start_controls_section( 'section_cards', array( 'label' => __( 'Cartes', 'ecurie-prestige' ) ) );
		$with_skin && $this->add_control(
			'skin',
			array(
				'label'   => __( 'Style de carte', 'ecurie-prestige' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'prestige',
				'options' => array(
					'prestige'  => __( 'Prestige — texte sur la photo', 'ecurie-prestige' ),
					'editorial' => __( 'Éditorial — texte sous la photo', 'ecurie-prestige' ),
				),
			)
		);
		$this->add_control(
			'ratio',
			array(
				'label'     => __( 'Format des photos', 'ecurie-prestige' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => '3 / 4',
				'options'   => array(
					'2 / 3'  => __( 'Portrait élancé (2:3)', 'ecurie-prestige' ),
					'3 / 4'  => __( 'Portrait (3:4)', 'ecurie-prestige' ),
					'4 / 5'  => __( 'Portrait doux (4:5)', 'ecurie-prestige' ),
					'1 / 1'  => __( 'Carré (1:1)', 'ecurie-prestige' ),
					'4 / 3'  => __( 'Paysage (4:3)', 'ecurie-prestige' ),
					'16 / 9' => __( 'Panoramique (16:9)', 'ecurie-prestige' ),
				),
				'selectors' => array( '{{WRAPPER}} .ep' => '--ep-ratio: {{VALUE}};' ),
			)
		);
		foreach (
			array(
				'show_number'  => __( 'Numéro', 'ecurie-prestige' ),
				'show_badge'   => __( 'Discipline', 'ecurie-prestige' ),
				'show_meta'    => __( 'Race, sexe, âge, robe', 'ecurie-prestige' ),
				'show_tagline' => __( 'Signature', 'ecurie-prestige' ),
				'show_stats'   => __( 'Statistiques', 'ecurie-prestige' ),
			) as $key => $label
		) {
			$this->add_control( $key, array( 'label' => $label, 'type' => Controls_Manager::SWITCHER, 'default' => 'yes', 'return_value' => 'yes' ) );
		}
		$this->add_control(
			'click',
			array(
				'label'     => __( 'Au clic', 'ecurie-prestige' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'modal',
				'separator' => 'before',
				'options'   => array(
					'modal' => __( 'Ouvrir la fiche en fenêtre', 'ecurie-prestige' ),
					'page'  => __( 'Aller sur la page du cheval', 'ecurie-prestige' ),
					'none'  => __( 'Rien', 'ecurie-prestige' ),
				),
			)
		);
		$this->add_control( 'cta', array( 'label' => __( 'Texte du lien', 'ecurie-prestige' ), 'type' => Controls_Manager::TEXT, 'default' => __( 'Découvrir', 'ecurie-prestige' ), 'condition' => array( 'click!' => 'none' ) ) );
		$this->end_controls_section();
	}

	/**
	 * @param bool|string $with_cards    true : widgets à cartes ; false : cheval vedette ; 'section' : blocs de page.
	 * @param string      $default_theme dark | light.
	 */
	protected function register_theme_controls( $with_cards = true, $default_theme = 'dark' ) {
		$section    = 'section' === $with_cards;
		$with_cards = true === $with_cards;
		$this->start_controls_section( 'section_style_theme', array( 'label' => __( 'Ambiance & couleurs', 'ecurie-prestige' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control(
			'theme',
			array(
				'label'   => __( 'Thème', 'ecurie-prestige' ),
				'type'    => Controls_Manager::SELECT,
				'default' => $default_theme,
				'options' => array(
					'dark'  => __( 'Sombre — noir & or', 'ecurie-prestige' ),
					'light' => __( 'Clair — ivoire & or', 'ecurie-prestige' ),
				),
			)
		);
		$this->add_control( 'background', array( 'label' => __( 'Fond du bloc', 'ecurie-prestige' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes', 'return_value' => 'yes', 'description' => __( 'Désactivez pour laisser apparaître le fond de votre section Elementor.', 'ecurie-prestige' ) ) );
		$this->add_control( 'color_accent', array( 'label' => __( 'Couleur d’accent', 'ecurie-prestige' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .ep' => '--ep-accent: {{VALUE}};' ) ) );
		$this->add_control( 'color_bg', array( 'label' => __( 'Couleur de fond', 'ecurie-prestige' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .ep' => '--ep-bg: {{VALUE}};' ), 'condition' => array( 'background' => 'yes' ) ) );
		$this->add_control( 'color_surface', array( 'label' => __( 'Couleur des panneaux', 'ecurie-prestige' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .ep' => '--ep-surface: {{VALUE}};' ) ) );
		$this->add_control( 'color_text', array( 'label' => __( 'Couleur du texte', 'ecurie-prestige' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .ep' => '--ep-text: {{VALUE}};' ) ) );
		$this->add_responsive_control(
			'block_padding',
			array(
				'label'      => __( 'Marges intérieures du bloc', 'ecurie-prestige' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'rem', 'vw' ),
				'selectors'  => array( '{{WRAPPER}} .ep' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		if ( $with_cards ) {
			$this->add_control(
				'radius',
				array(
					'label'      => __( 'Arrondi des cartes', 'ecurie-prestige' ),
					'type'       => Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
					'selectors'  => array( '{{WRAPPER}} .ep' => '--ep-radius: {{SIZE}}{{UNIT}};' ),
				)
			);
		}
		$this->end_controls_section();

		$this->start_controls_section( 'section_style_type', array( 'label' => __( 'Typographie', 'ecurie-prestige' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control(
			'font_display',
			array(
				'label'       => __( 'Police des titres et des noms', 'ecurie-prestige' ),
				'type'        => Controls_Manager::FONT,
				'description' => __( 'Conseil : Cormorant Garamond, Playfair Display, Cinzel ou Bodoni Moda.', 'ecurie-prestige' ),
				'selectors'   => array( '{{WRAPPER}} .ep' => '--ep-font-display: "{{VALUE}}", Georgia, serif;' ),
			)
		);
		$this->add_control(
			'font_body',
			array(
				'label'     => __( 'Police du texte', 'ecurie-prestige' ),
				'type'      => Controls_Manager::FONT,
				'selectors' => array( '{{WRAPPER}} .ep' => '--ep-font-body: "{{VALUE}}", sans-serif;' ),
			)
		);
		if ( $with_cards ) {
			$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'type_title', 'label' => __( 'Titre du bloc', 'ecurie-prestige' ), 'selector' => '{{WRAPPER}} .ep .ep-head__title' ) );
			$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'type_name', 'label' => __( 'Nom du cheval', 'ecurie-prestige' ), 'selector' => '{{WRAPPER}} .ep .ep-card__name' ) );
		} elseif ( $section ) {
			$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'type_title', 'label' => __( 'Titre', 'ecurie-prestige' ), 'selector' => '{{WRAPPER}} .ep .ep-head__title, {{WRAPPER}} .ep .ep-sec__display' ) );
		} else {
			$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'type_name', 'label' => __( 'Nom du cheval', 'ecurie-prestige' ), 'selector' => '{{WRAPPER}} .ep .ep-featured__name' ) );
		}
		$this->end_controls_section();
	}

	/** Convertit les réglages Elementor en arguments du moteur d’affichage. */
	protected function collection_args( array $s ) {
		return array(
			'skin'         => isset( $s['skin'] ) ? $s['skin'] : 'prestige',
			'theme'        => isset( $s['theme'] ) ? $s['theme'] : 'dark',
			'background'   => $this->yes( $s, 'background' ),
			'source'       => isset( $s['source'] ) ? $s['source'] : 'all',
			'disciplines'  => isset( $s['disciplines'] ) ? (array) $s['disciplines'] : array(),
			'ids'          => isset( $s['ids'] ) ? (array) $s['ids'] : array(),
			'statuts'      => isset( $s['statuts'] ) ? (array) $s['statuts'] : array(),
			'number'       => ! empty( $s['number'] ) ? (int) $s['number'] : 12,
			'orderby'      => isset( $s['orderby'] ) ? $s['orderby'] : 'menu_order',
			'order'        => isset( $s['order'] ) ? $s['order'] : 'ASC',
			'filters'      => $this->yes( $s, 'filters' ),
			'show_number'  => $this->yes( $s, 'show_number' ),
			'show_badge'   => $this->yes( $s, 'show_badge' ),
			'show_meta'    => $this->yes( $s, 'show_meta' ),
			'show_tagline' => $this->yes( $s, 'show_tagline' ),
			'show_stats'   => $this->yes( $s, 'show_stats' ),
			'click'        => isset( $s['click'] ) ? $s['click'] : 'modal',
			'cta'          => isset( $s['cta'] ) ? $s['cta'] : '',
			'eyebrow'      => isset( $s['eyebrow'] ) ? $s['eyebrow'] : '',
			'title'        => isset( $s['title'] ) ? $s['title'] : '',
			'intro'        => isset( $s['intro'] ) ? $s['intro'] : '',
			'align'        => isset( $s['align'] ) ? $s['align'] : 'left',
		);
	}
}

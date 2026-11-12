<?php
/**
 * Widgets Elementor des blocs éditoriaux (page « L’élevage » et autres pages de présentation).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Repeater;

abstract class EP_Widget_Section extends EP_Widget_Base {

	/** Nom du bloc dans EP_Sections (hero, statement…). */
	abstract protected function block();

	public function get_keywords() {
		return array( 'écurie', 'ecurie', 'élevage', 'elevage', 'page', 'section', 'bandeau', 'présentation' );
	}

	/** Le contenu est entièrement saisi dans le widget. */
	protected function is_dynamic_content(): bool {
		return false;
	}

	protected function d( $key ) {
		$defaults = EP_Sections::defaults( $this->block() );
		return isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
	}

	protected function text( $id, $label, $type = Controls_Manager::TEXT, $extra = array() ) {
		$this->add_control( $id, array( 'label' => $label, 'type' => $type, 'default' => $this->d( $id ), 'label_block' => true, 'dynamic' => array( 'active' => true ) ) + $extra );
	}

	protected function title_control( $label = '' ) {
		$this->text( 'title', $label ? $label : __( 'Titre', 'ecurie-prestige' ), Controls_Manager::TEXT, array( 'description' => __( 'Utilisez <em>…</em> pour mettre un mot en italique doré.', 'ecurie-prestige' ) ) );
	}

	protected function image_control( $id, $label, $placeholder = false ) {
		$this->add_control( $id, array( 'label' => $label, 'type' => Controls_Manager::MEDIA, 'default' => array( 'url' => $placeholder ? \Elementor\Utils::get_placeholder_image_src() : '' ) ) );
	}

	protected function items_control( $label ) {
		$repeater = new Repeater();
		$repeater->add_control( 'title', array( 'label' => __( 'Titre', 'ecurie-prestige' ), 'type' => Controls_Manager::TEXT, 'label_block' => true ) );
		$repeater->add_control( 'text', array( 'label' => __( 'Texte', 'ecurie-prestige' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 6 ) );
		$this->add_control( 'items', array( 'label' => $label, 'type' => Controls_Manager::REPEATER, 'fields' => $repeater->get_controls(), 'default' => $this->d( 'items' ), 'title_field' => '{{{ title }}}' ) );
	}

	protected function url_of( array $s, $key ) {
		return isset( $s[ $key ]['url'] ) ? $s[ $key ]['url'] : '';
	}

	protected function base_args( array $s ) {
		return array(
			'theme'      => isset( $s['theme'] ) ? $s['theme'] : $this->d( 'theme' ),
			'background' => $this->yes( $s, 'background' ),
		);
	}

	protected function pick( array $s, array $keys ) {
		$out = $this->base_args( $s );
		foreach ( $keys as $key ) {
			$out[ $key ] = isset( $s[ $key ] ) ? $s[ $key ] : '';
		}
		return $out;
	}
}

class EP_Widget_Page_Hero extends EP_Widget_Section {
	protected function block() { return 'hero'; }
	public function get_name() { return 'ep-page-hero'; }
	public function get_title() { return __( 'Page — Bandeau titre', 'ecurie-prestige' ); }
	public function get_icon() { return 'eicon-banner'; }

	protected function register_controls() {
		$this->start_controls_section( 'section_content', array( 'label' => __( 'Bandeau', 'ecurie-prestige' ) ) );
		$this->text( 'eyebrow', __( 'Surtitre', 'ecurie-prestige' ) );
		$this->title_control();
		$this->text( 'subtitle', __( 'Sous-titre', 'ecurie-prestige' ), Controls_Manager::TEXTAREA, array( 'rows' => 3 ) );
		$this->image_control( 'image', __( 'Photo de fond', 'ecurie-prestige' ) );
		$this->add_responsive_control(
			'min_height',
			array(
				'label'      => __( 'Hauteur minimale', 'ecurie-prestige' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'vh', 'px' ),
				'range'      => array( 'vh' => array( 'min' => 30, 'max' => 100 ), 'px' => array( 'min' => 300, 'max' => 1100 ) ),
				'selectors'  => array( '{{WRAPPER}} .ep' => '--ep-hero-h: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->end_controls_section();
		$this->register_theme_controls( 'section', 'dark' );
	}

	protected function render() {
		echo EP_Sections::hero( $this->pick( $this->get_settings_for_display(), array( 'eyebrow', 'title', 'subtitle', 'image' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
}

class EP_Widget_Statement extends EP_Widget_Section {
	protected function block() { return 'statement'; }
	public function get_name() { return 'ep-statement'; }
	public function get_title() { return __( 'Page — Mot de la maison', 'ecurie-prestige' ); }
	public function get_icon() { return 'eicon-blockquote'; }

	protected function register_controls() {
		$this->start_controls_section( 'section_content', array( 'label' => __( 'Mot de la maison', 'ecurie-prestige' ) ) );
		$this->text( 'eyebrow', __( 'Surtitre', 'ecurie-prestige' ) );
		$this->text( 'lead', __( 'Phrase d’accroche (en grand)', 'ecurie-prestige' ), Controls_Manager::TEXTAREA, array( 'rows' => 4 ) );
		$this->text( 'text', __( 'Suite du texte', 'ecurie-prestige' ), Controls_Manager::TEXTAREA, array( 'rows' => 6 ) );
		$this->text( 'signature', __( 'Signature', 'ecurie-prestige' ) );
		$this->text( 'tags', __( 'Mots-clés (séparés par des virgules)', 'ecurie-prestige' ) );
		$this->image_control( 'image', __( 'Photo (facultative)', 'ecurie-prestige' ) );
		$this->end_controls_section();
		$this->register_theme_controls( 'section', 'light' );
	}

	protected function render() {
		echo EP_Sections::statement( $this->pick( $this->get_settings_for_display(), array( 'eyebrow', 'lead', 'text', 'signature', 'tags', 'image' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
}

class EP_Widget_Pillars extends EP_Widget_Section {
	protected function block() { return 'pillars'; }
	public function get_name() { return 'ep-pillars'; }
	public function get_title() { return __( 'Page — Piliers', 'ecurie-prestige' ); }
	public function get_icon() { return 'eicon-columns'; }

	protected function register_controls() {
		$this->start_controls_section( 'section_content', array( 'label' => __( 'Piliers', 'ecurie-prestige' ) ) );
		$this->text( 'eyebrow', __( 'Surtitre', 'ecurie-prestige' ) );
		$this->title_control();
		$this->text( 'intro', __( 'Introduction', 'ecurie-prestige' ), Controls_Manager::TEXTAREA, array( 'rows' => 3 ) );
		$this->items_control( __( 'Piliers', 'ecurie-prestige' ) );
		$this->end_controls_section();
		$this->register_theme_controls( 'section', 'dark' );
	}

	protected function render() {
		echo EP_Sections::pillars( $this->pick( $this->get_settings_for_display(), array( 'eyebrow', 'title', 'intro', 'items' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
}

class EP_Widget_Features extends EP_Widget_Section {
	protected function block() { return 'features'; }
	public function get_name() { return 'ep-features'; }
	public function get_title() { return __( 'Page — Infrastructures', 'ecurie-prestige' ); }
	public function get_icon() { return 'eicon-image-box'; }

	protected function register_controls() {
		$this->start_controls_section( 'section_content', array( 'label' => __( 'Infrastructures', 'ecurie-prestige' ) ) );
		$this->text( 'eyebrow', __( 'Surtitre', 'ecurie-prestige' ) );
		$this->title_control();
		$this->text( 'intro', __( 'Introduction', 'ecurie-prestige' ), Controls_Manager::TEXTAREA, array( 'rows' => 3 ) );
		$this->items_control( __( 'Atouts', 'ecurie-prestige' ) );
		$this->image_control( 'image', __( 'Grande photo', 'ecurie-prestige' ), true );
		$this->image_control( 'image_2', __( 'Petite photo superposée (facultative)', 'ecurie-prestige' ) );
		$this->add_control( 'reverse', array( 'label' => __( 'Photos à droite', 'ecurie-prestige' ), 'type' => Controls_Manager::SWITCHER, 'default' => '', 'return_value' => 'yes' ) );
		$this->end_controls_section();
		$this->register_theme_controls( 'section', 'light' );
	}

	protected function render() {
		$s               = $this->get_settings_for_display();
		$args            = $this->pick( $s, array( 'eyebrow', 'title', 'intro', 'items', 'image', 'image_2' ) );
		$args['reverse'] = $this->yes( $s, 'reverse' );
		echo EP_Sections::features( $args ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
}

class EP_Widget_Cta extends EP_Widget_Section {
	protected function block() { return 'cta'; }
	public function get_name() { return 'ep-cta'; }
	public function get_title() { return __( 'Page — Appel à contact', 'ecurie-prestige' ); }
	public function get_icon() { return 'eicon-call-to-action'; }

	protected function register_controls() {
		$this->start_controls_section( 'section_content', array( 'label' => __( 'Appel à contact', 'ecurie-prestige' ) ) );
		$this->text( 'eyebrow', __( 'Surtitre', 'ecurie-prestige' ) );
		$this->title_control();
		$this->text( 'text', __( 'Texte', 'ecurie-prestige' ), Controls_Manager::TEXTAREA, array( 'rows' => 3 ) );
		$this->text( 'button', __( 'Bouton principal', 'ecurie-prestige' ) );
		$this->add_control( 'url', array( 'label' => __( 'Lien du bouton principal', 'ecurie-prestige' ), 'type' => Controls_Manager::URL, 'default' => array( 'url' => $this->d( 'url' ) ), 'dynamic' => array( 'active' => true ) ) );
		$this->text( 'button_2', __( 'Bouton secondaire', 'ecurie-prestige' ) );
		$archive = get_post_type_archive_link( EP_CPT::POST_TYPE );
		$this->add_control( 'url_2', array( 'label' => __( 'Lien du bouton secondaire', 'ecurie-prestige' ), 'type' => Controls_Manager::URL, 'default' => array( 'url' => $archive ? $archive : '' ), 'dynamic' => array( 'active' => true ) ) );
		$this->end_controls_section();
		$this->register_theme_controls( 'section', 'dark' );
	}

	protected function render() {
		$s             = $this->get_settings_for_display();
		$args          = $this->pick( $s, array( 'eyebrow', 'title', 'text', 'button', 'button_2' ) );
		$args['url']   = $this->url_of( $s, 'url' );
		$args['url_2'] = $this->url_of( $s, 'url_2' );
		echo EP_Sections::cta( $args ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
}

<?php
/**
 * Elementor widgets « Éternelle – Mariage ».
 *
 * Content (names, date, story, programme…) comes from Appearance → Customize →
 * Notre mariage, so it stays identical everywhere (site, invitation cards, e-mails).
 *
 * @package Eternelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

/**
 * List of widget classes to register.
 */
function eternelle_elementor_widget_classes() {
	return array(
		'Eternelle_W_Hero',
		'Eternelle_W_Countdown',
		'Eternelle_W_Title',
		'Eternelle_W_Couple',
		'Eternelle_W_Story',
		'Eternelle_W_Program',
		'Eternelle_W_Venue',
		'Eternelle_W_Gallery',
		'Eternelle_W_Rsvp',
		'Eternelle_W_Gifts',
		'Eternelle_W_Find',
	);
}

/**
 * Common base.
 */
abstract class Eternelle_W_Base extends Widget_Base {

	public function get_categories() {
		return array( 'eternelle' );
	}

	public function get_keywords() {
		return array( 'mariage', 'wedding', 'eternelle' );
	}

	protected function customizer_notice( $what ) {
		$this->add_control(
			'et_notice',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => sprintf(
					/* translators: %s: what is edited */
					esc_html__( '%s se modifie dans Apparence → Personnaliser → 💍 Notre mariage.', 'eternelle' ),
					$what
				) . ' <a href="' . esc_url( admin_url( 'customize.php?autofocus[panel]=eternelle' ) ) . '" target="_blank">' . esc_html__( 'Ouvrir', 'eternelle' ) . '</a>',
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
			)
		);
	}

	protected function yes( $settings, $key ) {
		return isset( $settings[ $key ] ) && 'yes' === $settings[ $key ];
	}
}

/**
 * Animated hero.
 */
class Eternelle_W_Hero extends Eternelle_W_Base {

	public function get_name() {
		return 'eternelle-hero';
	}

	public function get_title() {
		return __( 'Hero animé (mariage)', 'eternelle' );
	}

	public function get_icon() {
		return 'eicon-slider-full-screen';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Contenu', 'eternelle' ) ) );
		$this->customizer_notice( __( 'Les prénoms, la date et le lieu', 'eternelle' ) );
		$this->add_control( 'kicker', array( 'label' => __( 'Texte au-dessus des prénoms', 'eternelle' ), 'type' => Controls_Manager::TEXT, 'default' => eternelle_mod( 'et_hero_kicker' ) ) );
		$this->add_control( 'quote', array( 'label' => __( 'Phrase', 'eternelle' ), 'type' => Controls_Manager::TEXTAREA, 'default' => eternelle_mod( 'et_hero_quote' ) ) );
		$this->add_control( 'cta', array( 'label' => __( 'Texte du bouton', 'eternelle' ), 'type' => Controls_Manager::TEXT, 'default' => eternelle_mod( 'et_hero_cta' ) ) );
		$this->add_control( 'cta_url', array( 'label' => __( 'Lien du bouton', 'eternelle' ), 'type' => Controls_Manager::URL, 'default' => array( 'url' => '#rsvp' ) ) );
		$this->add_control( 'image', array( 'label' => __( 'Photo de fond (vide = photo du Customizer / fond animé)', 'eternelle' ), 'type' => Controls_Manager::MEDIA ) );
		$this->end_controls_section();

		$this->start_controls_section( 'display', array( 'label' => __( 'Affichage', 'eternelle' ) ) );
		foreach ( array(
			'full'      => __( 'Plein écran', 'eternelle' ),
			'monogram'  => __( 'Monogramme doré', 'eternelle' ),
			'venue'     => __( 'Lieu', 'eternelle' ),
			'countdown' => __( 'Compte à rebours', 'eternelle' ),
			'scroll'    => __( 'Indicateur de défilement', 'eternelle' ),
			'wave'      => __( 'Vague en bas', 'eternelle' ),
		) as $key => $label ) {
			$this->add_control( $key, array( 'label' => $label, 'type' => Controls_Manager::SWITCHER, 'default' => 'yes' ) );
		}
		$this->end_controls_section();
	}

	protected function render() {
		$s   = $this->get_settings_for_display();
		$img = ! empty( $s['image']['id'] ) ? wp_get_attachment_image_url( (int) $s['image']['id'], 'eternelle-hero' ) : ( ! empty( $s['image']['url'] ) ? $s['image']['url'] : eternelle_image_url( 'et_hero_image', 'eternelle-hero' ) );
		get_template_part(
			'template-parts/hero',
			null,
			array(
				'kicker'    => $s['kicker'],
				'quote'     => $s['quote'],
				'cta'       => $s['cta'],
				'cta_url'   => ! empty( $s['cta_url']['url'] ) ? $s['cta_url']['url'] : '#rsvp',
				'image'     => $img,
				'full'      => $this->yes( $s, 'full' ),
				'monogram'  => $this->yes( $s, 'monogram' ),
				'venue'     => $this->yes( $s, 'venue' ),
				'countdown' => $this->yes( $s, 'countdown' ),
				'scroll'    => $this->yes( $s, 'scroll' ),
				'wave'      => $this->yes( $s, 'wave' ),
				'id'        => '',
			)
		);
	}
}

/**
 * Countdown.
 */
class Eternelle_W_Countdown extends Eternelle_W_Base {

	public function get_name() {
		return 'eternelle-countdown';
	}

	public function get_title() {
		return __( 'Compte à rebours (mariage)', 'eternelle' );
	}

	public function get_icon() {
		return 'eicon-countdown';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Compte à rebours', 'eternelle' ) ) );
		$this->customizer_notice( __( 'La date du mariage', 'eternelle' ) );
		$this->add_control(
			'style',
			array(
				'label'   => __( 'Style', 'eternelle' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'light',
				'options' => array(
					'light' => __( 'Clair (sur fond clair)', 'eternelle' ),
					'glass' => __( 'Verre (sur photo / fond sombre)', 'eternelle' ),
				),
			)
		);
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		get_template_part( 'template-parts/countdown', null, array( 'style' => $s['style'] ) );
	}
}

/**
 * Decorated title (script font + golden flourish).
 */
class Eternelle_W_Title extends Eternelle_W_Base {

	public function get_name() {
		return 'eternelle-title';
	}

	public function get_title() {
		return __( 'Titre décoré (mariage)', 'eternelle' );
	}

	public function get_icon() {
		return 'eicon-heading';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Titre', 'eternelle' ) ) );
		$this->add_control( 'kicker', array( 'label' => __( 'Petit texte au-dessus', 'eternelle' ), 'type' => Controls_Manager::TEXT, 'default' => __( 'Save the date', 'eternelle' ) ) );
		$this->add_control( 'title', array( 'label' => __( 'Titre', 'eternelle' ), 'type' => Controls_Manager::TEXT, 'default' => __( 'Notre grand jour', 'eternelle' ) ) );
		$this->add_control( 'light', array( 'label' => __( 'Texte clair (sur fond sombre)', 'eternelle' ), 'type' => Controls_Manager::SWITCHER, 'default' => '' ) );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		echo '<div class="et-el-title' . ( $this->yes( $s, 'light' ) ? ' is-light' : '' ) . '">';
		eternelle_section_title( $s['title'], $s['kicker'] );
		echo '</div>';
	}
}

/**
 * Generic widget rendering one of the theme's one-page sections.
 */
abstract class Eternelle_W_Section extends Eternelle_W_Base {

	/** Template part name in template-parts/sections/. */
	abstract protected function section();

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => $this->get_title() ) );
		$this->customizer_notice( __( 'Le contenu de cette section', 'eternelle' ) );
		$this->add_control( 'anchor_note', array( 'type' => Controls_Manager::RAW_HTML, 'raw' => sprintf( esc_html__( 'Ancre pour le menu : #%s', 'eternelle' ), esc_html( $this->section() ) ) ) );
		$this->end_controls_section();
	}

	protected function render() {
		echo '<div class="et-el-section">';
		get_template_part( 'template-parts/sections/' . $this->section() );
		echo '</div>';
	}
}

class Eternelle_W_Couple extends Eternelle_W_Section {
	protected function section() { return 'couple'; }
	public function get_name() { return 'eternelle-couple'; }
	public function get_title() { return __( 'Les mariés', 'eternelle' ); }
	public function get_icon() { return 'eicon-person'; }
}

class Eternelle_W_Story extends Eternelle_W_Section {
	protected function section() { return 'story'; }
	public function get_name() { return 'eternelle-story'; }
	public function get_title() { return __( 'Notre histoire (frise)', 'eternelle' ); }
	public function get_icon() { return 'eicon-time-line'; }
}

class Eternelle_W_Program extends Eternelle_W_Section {
	protected function section() { return 'program'; }
	public function get_name() { return 'eternelle-program'; }
	public function get_title() { return __( 'Programme du jour', 'eternelle' ); }
	public function get_icon() { return 'eicon-clock-o'; }
}

class Eternelle_W_Venue extends Eternelle_W_Section {
	protected function section() { return 'venue'; }
	public function get_name() { return 'eternelle-venue'; }
	public function get_title() { return __( 'Lieu & itinéraire', 'eternelle' ); }
	public function get_icon() { return 'eicon-map-pin'; }
}

class Eternelle_W_Gallery extends Eternelle_W_Section {
	protected function section() { return 'gallery'; }
	public function get_name() { return 'eternelle-gallery'; }
	public function get_title() { return __( 'Galerie mariage (visionneuse)', 'eternelle' ); }
	public function get_icon() { return 'eicon-gallery-masonry'; }
}

class Eternelle_W_Rsvp extends Eternelle_W_Section {
	protected function section() { return 'rsvp'; }
	public function get_name() { return 'eternelle-rsvp'; }
	public function get_title() { return __( 'RSVP (réponse des invités)', 'eternelle' ); }
	public function get_icon() { return 'eicon-envelope'; }
}

class Eternelle_W_Gifts extends Eternelle_W_Section {
	protected function section() { return 'gifts'; }
	public function get_name() { return 'eternelle-gifts'; }
	public function get_title() { return __( 'Liste de mariage', 'eternelle' ); }
	public function get_icon() { return 'eicon-gift'; }
}

/**
 * "Find my invitation" form (Wedding RSVP Pass plugin).
 */
class Eternelle_W_Find extends Eternelle_W_Base {

	public function get_name() {
		return 'eternelle-find';
	}

	public function get_title() {
		return __( 'Retrouver mon invitation', 'eternelle' );
	}

	public function get_icon() {
		return 'eicon-email-field';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => $this->get_title() ) );
		$this->add_control( 'button', array( 'label' => __( 'Texte du bouton', 'eternelle' ), 'type' => Controls_Manager::TEXT, 'default' => __( 'Recevoir mon lien', 'eternelle' ) ) );
		$this->end_controls_section();
	}

	protected function render() {
		if ( ! eternelle_has_rsvp_plugin() ) {
			if ( current_user_can( 'activate_plugins' ) ) {
				echo '<p>' . esc_html__( 'Activez le plugin « Wedding RSVP Pass » pour utiliser ce widget.', 'eternelle' ) . '</p>';
			}
			return;
		}
		$s = $this->get_settings_for_display();
		echo '<div class="et-el-find">' . do_shortcode( '[wrp_find_invitation button="' . esc_attr( $s['button'] ) . '"]' ) . '</div>';
	}
}

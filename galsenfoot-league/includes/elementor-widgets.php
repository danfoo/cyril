<?php
/**
 * Elementor widgets (category "GalsenFoot"). Loaded only when Elementor is active.
 *
 * @package GalsenFootLeague
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

/**
 * Widget classes to register.
 *
 * @return string[]
 */
function gsf_elementor_widget_classes() {
	return array(
		'GSF_Widget_Standings',
		'GSF_Widget_Matches',
		'GSF_Widget_Ticker',
		'GSF_Widget_Next_Match',
		'GSF_Widget_Leaders',
		'GSF_Widget_Clubs',
		'GSF_Widget_Squad',
	);
}

/**
 * Shared behaviour: common controls and rendering through the gsf_render_* functions.
 */
abstract class GSF_Widget extends Widget_Base {

	/**
	 * Render callback name.
	 *
	 * @var string
	 */
	protected $renderer = '';

	/**
	 * Setting keys passed to the renderer.
	 *
	 * @var string[]
	 */
	protected $keys = array();

	public function get_categories() {
		return array( 'galsenfoot' );
	}

	public function get_keywords() {
		return array( 'football', 'foot', 'galsenfoot', 'championnat', 'ligue', 'match', 'classement', 'club', 'joueur' );
	}

	public function get_style_depends() {
		return array( 'gsf' );
	}

	public function get_script_depends() {
		return array( 'gsf' );
	}

	/**
	 * Options of a taxonomy for a select control.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @return array
	 */
	protected function term_options( $taxonomy ) {
		$options = array( '' => __( 'Par défaut (réglages)', 'galsenfoot-league' ) );
		$terms   = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$options[ (string) $term->term_id ] = $term->name;
			}
		}
		return $options;
	}

	/**
	 * Club options for a select control.
	 *
	 * @param string $none Label of the empty option.
	 * @return array
	 */
	protected function club_options( $none ) {
		$options = array( '' => $none );
		foreach ( get_posts( array( 'post_type' => 'gsf_club', 'posts_per_page' => 300, 'orderby' => 'title', 'order' => 'ASC' ) ) as $club ) {
			$options[ (string) $club->ID ] = $club->post_title;
		}
		return $options;
	}

	/**
	 * Start the content section with the title / competition / season controls.
	 *
	 * @param bool $with_terms Whether competition and season apply.
	 */
	protected function start_common( $with_terms = true ) {
		$this->start_controls_section( 'section_content', array( 'label' => __( 'Contenu', 'galsenfoot-league' ) ) );
		$this->add_control(
			'title',
			array(
				'label'       => __( 'Titre', 'galsenfoot-league' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'label_block' => true,
			)
		);
		if ( $with_terms ) {
			$this->add_control(
				'competition',
				array(
					'label'   => __( 'Compétition', 'galsenfoot-league' ),
					'type'    => Controls_Manager::SELECT,
					'options' => $this->term_options( 'gsf_competition' ),
					'default' => '',
				)
			);
			$this->add_control(
				'season',
				array(
					'label'   => __( 'Saison', 'galsenfoot-league' ),
					'type'    => Controls_Manager::SELECT,
					'options' => $this->term_options( 'gsf_season' ),
					'default' => '',
				)
			);
		}
	}

	/**
	 * Number control.
	 *
	 * @param string $label   Label.
	 * @param int    $default Default.
	 * @param int    $max     Maximum.
	 */
	protected function add_limit( $label, $default, $max = 50 ) {
		$this->add_control(
			'limit',
			array(
				'label'   => $label,
				'type'    => Controls_Manager::NUMBER,
				'min'     => 0,
				'max'     => $max,
				'default' => $default,
			)
		);
	}

	/**
	 * Style section: accent colour applied through a CSS variable.
	 */
	protected function add_style_section() {
		$this->start_controls_section(
			'section_style',
			array(
				'label' => __( 'Style', 'galsenfoot-league' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control(
			'accent',
			array(
				'label'     => __( 'Couleur d’accent', 'galsenfoot-league' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .gsf' => '--gsf-accent: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'surface',
			array(
				'label'     => __( 'Fond des blocs', 'galsenfoot-league' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .gsf' => '--gsf-surface: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'text',
			array(
				'label'     => __( 'Couleur du texte', 'galsenfoot-league' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .gsf' => '--gsf-text: {{VALUE}};' ),
			)
		);
		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$args     = array();
		foreach ( array_merge( array( 'title', 'competition', 'season' ), $this->keys ) as $key ) {
			if ( isset( $settings[ $key ] ) ) {
				$value        = $settings[ $key ];
				$args[ $key ] = is_array( $value ) ? ( $value['url'] ?? '' ) : ( 'yes' === $value ? 1 : $value );
			}
		}
		echo '<div class="gsf">' . call_user_func( $this->renderer, $args ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}

/**
 * League table.
 */
class GSF_Widget_Standings extends GSF_Widget {
	protected $renderer = 'gsf_render_standings';
	protected $keys     = array( 'limit', 'compact', 'form', 'highlight', 'link' );

	public function get_name() {
		return 'gsf-standings';
	}
	public function get_title() {
		return __( 'Classement', 'galsenfoot-league' );
	}
	public function get_icon() {
		return 'eicon-table';
	}
	protected function register_controls() {
		$this->start_common();
		$this->add_limit( __( 'Nombre de clubs (0 = tous)', 'galsenfoot-league' ), 0 );
		$this->add_control(
			'compact',
			array(
				'label'        => __( 'Version compacte (colonne latérale)', 'galsenfoot-league' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
			)
		);
		$this->add_control(
			'form',
			array(
				'label'        => __( 'Afficher la forme (5 derniers matchs)', 'galsenfoot-league' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);
		$this->add_control(
			'highlight',
			array(
				'label'   => __( 'Club mis en avant', 'galsenfoot-league' ),
				'type'    => Controls_Manager::SELECT,
				'options' => $this->club_options( __( 'Aucun', 'galsenfoot-league' ) ),
				'default' => '',
			)
		);
		$this->add_control(
			'link',
			array(
				'label' => __( 'Lien « Classement complet »', 'galsenfoot-league' ),
				'type'  => Controls_Manager::URL,
			)
		);
		$this->end_controls_section();
		$this->add_style_section();
	}
}

/**
 * Fixtures / results.
 */
class GSF_Widget_Matches extends GSF_Widget {
	protected $renderer = 'gsf_render_matches';
	protected $keys     = array( 'type', 'limit', 'club', 'matchday', 'link' );

	public function get_name() {
		return 'gsf-matches';
	}
	public function get_title() {
		return __( 'Matchs (calendrier / résultats)', 'galsenfoot-league' );
	}
	public function get_icon() {
		return 'eicon-calendar';
	}
	protected function register_controls() {
		$this->start_common();
		$this->add_control(
			'type',
			array(
				'label'   => __( 'Afficher', 'galsenfoot-league' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'fixtures',
				'options' => array(
					'fixtures' => __( 'Prochains matchs', 'galsenfoot-league' ),
					'results'  => __( 'Derniers résultats', 'galsenfoot-league' ),
					'live'     => __( 'Matchs en direct', 'galsenfoot-league' ),
					'all'      => __( 'Toute la saison', 'galsenfoot-league' ),
				),
			)
		);
		$this->add_limit( __( 'Nombre de matchs', 'galsenfoot-league' ), 7, 400 );
		$this->add_control(
			'club',
			array(
				'label'   => __( 'Club', 'galsenfoot-league' ),
				'type'    => Controls_Manager::SELECT,
				'options' => $this->club_options( __( 'Tous les clubs', 'galsenfoot-league' ) ),
				'default' => '',
			)
		);
		$this->add_control(
			'matchday',
			array(
				'label'   => __( 'Journée (0 = toutes)', 'galsenfoot-league' ),
				'type'    => Controls_Manager::NUMBER,
				'min'     => 0,
				'default' => 0,
			)
		);
		$this->add_control(
			'link',
			array(
				'label' => __( 'Lien « Tous les matchs »', 'galsenfoot-league' ),
				'type'  => Controls_Manager::URL,
			)
		);
		$this->end_controls_section();
		$this->add_style_section();
	}
}

/**
 * Score strip.
 */
class GSF_Widget_Ticker extends GSF_Widget {
	protected $renderer = 'gsf_render_ticker';
	protected $keys     = array( 'limit' );

	public function get_name() {
		return 'gsf-ticker';
	}
	public function get_title() {
		return __( 'Bandeau des scores', 'galsenfoot-league' );
	}
	public function get_icon() {
		return 'eicon-slider-push';
	}
	protected function register_controls() {
		$this->start_common();
		$this->remove_control( 'title' );
		$this->add_limit( __( 'Nombre de matchs', 'galsenfoot-league' ), 12, 30 );
		$this->end_controls_section();
		$this->add_style_section();
	}
}

/**
 * Next match with countdown.
 */
class GSF_Widget_Next_Match extends GSF_Widget {
	protected $renderer = 'gsf_render_next_match';
	protected $keys     = array( 'club' );

	public function get_name() {
		return 'gsf-next-match';
	}
	public function get_title() {
		return __( 'Prochain match (compte à rebours)', 'galsenfoot-league' );
	}
	public function get_icon() {
		return 'eicon-countdown';
	}
	protected function register_controls() {
		$this->start_common();
		$this->add_control(
			'club',
			array(
				'label'       => __( 'Club', 'galsenfoot-league' ),
				'description' => __( 'Vide : prochaine affiche de la compétition.', 'galsenfoot-league' ),
				'type'        => Controls_Manager::SELECT,
				'options'     => $this->club_options( __( 'Tous les clubs', 'galsenfoot-league' ) ),
				'default'     => '',
			)
		);
		$this->end_controls_section();
		$this->add_style_section();
	}
}

/**
 * Player rankings.
 */
class GSF_Widget_Leaders extends GSF_Widget {
	protected $renderer = 'gsf_render_leaders';
	protected $keys     = array( 'stat', 'limit', 'club' );

	public function get_name() {
		return 'gsf-leaders';
	}
	public function get_title() {
		return __( 'Classement des joueurs (buteurs…)', 'galsenfoot-league' );
	}
	public function get_icon() {
		return 'eicon-person';
	}
	protected function register_controls() {
		$this->start_common();
		$this->add_control(
			'stat',
			array(
				'label'   => __( 'Statistique', 'galsenfoot-league' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'goals',
				'options' => array(
					'goals'   => __( 'Buteurs', 'galsenfoot-league' ),
					'assists' => __( 'Passeurs', 'galsenfoot-league' ),
					'yellow'  => __( 'Cartons jaunes', 'galsenfoot-league' ),
					'red'     => __( 'Cartons rouges', 'galsenfoot-league' ),
					'apps'    => __( 'Matchs joués', 'galsenfoot-league' ),
				),
			)
		);
		$this->add_limit( __( 'Nombre de joueurs', 'galsenfoot-league' ), 5, 50 );
		$this->add_control(
			'club',
			array(
				'label'   => __( 'Club', 'galsenfoot-league' ),
				'type'    => Controls_Manager::SELECT,
				'options' => $this->club_options( __( 'Tous les clubs', 'galsenfoot-league' ) ),
				'default' => '',
			)
		);
		$this->end_controls_section();
		$this->add_style_section();
	}
}

/**
 * Clubs grid / strip.
 */
class GSF_Widget_Clubs extends GSF_Widget {
	protected $renderer = 'gsf_render_clubs';
	protected $keys     = array( 'scope', 'layout' );

	public function get_name() {
		return 'gsf-clubs';
	}
	public function get_title() {
		return __( 'Clubs', 'galsenfoot-league' );
	}
	public function get_icon() {
		return 'eicon-gallery-grid';
	}
	protected function register_controls() {
		$this->start_common();
		$this->add_control(
			'scope',
			array(
				'label'   => __( 'Clubs affichés', 'galsenfoot-league' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'competition',
				'options' => array(
					'competition' => __( 'Clubs de la compétition', 'galsenfoot-league' ),
					'all'         => __( 'Tous les clubs', 'galsenfoot-league' ),
				),
			)
		);
		$this->add_control(
			'layout',
			array(
				'label'   => __( 'Disposition', 'galsenfoot-league' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'grid',
				'options' => array(
					'grid'  => __( 'Grille', 'galsenfoot-league' ),
					'strip' => __( 'Bandeau horizontal', 'galsenfoot-league' ),
				),
			)
		);
		$this->end_controls_section();
		$this->add_style_section();
	}
}

/**
 * Club squad.
 */
class GSF_Widget_Squad extends GSF_Widget {
	protected $renderer = 'gsf_render_squad';
	protected $keys     = array( 'club' );

	public function get_name() {
		return 'gsf-squad';
	}
	public function get_title() {
		return __( 'Effectif d’un club', 'galsenfoot-league' );
	}
	public function get_icon() {
		return 'eicon-person';
	}
	protected function register_controls() {
		$this->start_common();
		$this->add_control(
			'club',
			array(
				'label'   => __( 'Club', 'galsenfoot-league' ),
				'type'    => Controls_Manager::SELECT,
				'options' => $this->club_options( __( '— Choisir —', 'galsenfoot-league' ) ),
				'default' => '',
			)
		);
		$this->end_controls_section();
		$this->add_style_section();
	}
}

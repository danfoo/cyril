<?php
/**
 * Theme Elementor widget classes.
 *
 * @package GalsenFoot
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

/**
 * Shared helpers.
 */
abstract class GF_Widget extends Widget_Base {

	public function get_categories() {
		return array( 'galsenfoot' );
	}

	public function get_keywords() {
		return array( 'galsenfoot', 'football', 'articles', 'actualité', 'news' );
	}

	/**
	 * Category options.
	 *
	 * @return array
	 */
	protected function category_options() {
		$options = array( '0' => __( 'Toutes les rubriques', 'galsenfoot' ) );
		foreach ( get_categories( array( 'hide_empty' => false ) ) as $category ) {
			$options[ (string) $category->term_id ] = $category->name;
		}
		return $options;
	}
}

/**
 * Hero mosaic of the latest stories.
 */
class GF_Widget_Hero_News extends GF_Widget {
	public function get_name() {
		return 'gf-hero-news';
	}
	public function get_title() {
		return __( 'À la une (mosaïque)', 'galsenfoot' );
	}
	public function get_icon() {
		return 'eicon-featured-image';
	}
	protected function register_controls() {
		$this->start_controls_section( 'section_content', array( 'label' => __( 'Contenu', 'galsenfoot' ) ) );
		$this->add_control(
			'category',
			array(
				'label'   => __( 'Rubrique', 'galsenfoot' ),
				'type'    => Controls_Manager::SELECT,
				'options' => $this->category_options(),
				'default' => '0',
			)
		);
		$this->add_control(
			'count',
			array(
				'label'   => __( 'Nombre d’articles', 'galsenfoot' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'1' => '1',
					'3' => '3',
					'5' => '5',
				),
				'default' => '5',
			)
		);
		$this->add_control(
			'sticky',
			array(
				'label'        => __( 'Mettre en premier les articles « épinglés »', 'galsenfoot' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);
		$this->end_controls_section();
	}
	protected function render() {
		$settings = $this->get_settings_for_display();
		$count    = (int) $settings['count'];
		$ids      = array();
		if ( 'yes' === $settings['sticky'] ) {
			$sticky = array_filter( array_map( 'intval', (array) get_option( 'sticky_posts' ) ) );
			if ( $sticky ) {
				$ids = get_posts(
					array(
						'post__in'            => $sticky,
						'posts_per_page'      => $count,
						'fields'              => 'ids',
						'cat'                 => (int) $settings['category'],
						'ignore_sticky_posts' => true,
					)
				);
			}
		}
		if ( count( $ids ) < $count ) {
			$ids = array_merge(
				$ids,
				gf_post_ids(
					array(
						'count'    => $count - count( $ids ),
						'category' => (int) $settings['category'],
						'exclude'  => $ids,
					)
				)
			);
		}
		echo gf_hero_mosaic( $ids ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
}

/**
 * Grid / list of articles.
 */
class GF_Widget_Posts extends GF_Widget {
	public function get_name() {
		return 'gf-posts';
	}
	public function get_title() {
		return __( 'Articles (grille / liste)', 'galsenfoot' );
	}
	public function get_icon() {
		return 'eicon-posts-grid';
	}
	protected function register_controls() {
		$this->start_controls_section( 'section_content', array( 'label' => __( 'Contenu', 'galsenfoot' ) ) );
		$this->add_control(
			'title',
			array(
				'label'       => __( 'Titre', 'galsenfoot' ),
				'type'        => Controls_Manager::TEXT,
				'label_block' => true,
			)
		);
		$this->add_control(
			'category',
			array(
				'label'   => __( 'Rubrique', 'galsenfoot' ),
				'type'    => Controls_Manager::SELECT,
				'options' => $this->category_options(),
				'default' => '0',
			)
		);
		$this->add_control(
			'count',
			array(
				'label'   => __( 'Nombre d’articles', 'galsenfoot' ),
				'type'    => Controls_Manager::NUMBER,
				'min'     => 1,
				'max'     => 24,
				'default' => 6,
			)
		);
		$this->add_control(
			'offset',
			array(
				'label'       => __( 'Ignorer les premiers articles', 'galsenfoot' ),
				'description' => __( 'Par exemple 5 si la mosaïque « À la une » affiche déjà les 5 derniers.', 'galsenfoot' ),
				'type'        => Controls_Manager::NUMBER,
				'min'         => 0,
				'max'         => 50,
				'default'     => 0,
			)
		);
		$this->add_control(
			'layout',
			array(
				'label'   => __( 'Disposition', 'galsenfoot' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'grid' => __( 'Grille de cartes', 'galsenfoot' ),
					'list' => __( 'Liste compacte', 'galsenfoot' ),
				),
				'default' => 'grid',
			)
		);
		$this->add_control(
			'columns',
			array(
				'label'     => __( 'Colonnes', 'galsenfoot' ),
				'type'      => Controls_Manager::SELECT,
				'options'   => array(
					'2' => '2',
					'3' => '3',
					'4' => '4',
				),
				'default'   => '3',
				'condition' => array( 'layout' => 'grid' ),
			)
		);
		$this->add_control(
			'more',
			array(
				'label'        => __( 'Lien « Tout voir » vers la rubrique', 'galsenfoot' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);
		$this->end_controls_section();
	}
	protected function render() {
		$settings = $this->get_settings_for_display();
		$category = (int) $settings['category'];
		$ids      = gf_post_ids(
			array(
				'count'    => (int) $settings['count'],
				'category' => $category,
				'offset'   => (int) $settings['offset'],
			)
		);
		if ( ! $ids ) {
			return;
		}
		if ( ! empty( $settings['title'] ) ) {
			$url = ( 'yes' === $settings['more'] && $category ) ? get_category_link( $category ) : '';
			echo gf_heading( $settings['title'], $url ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		$list = 'list' === $settings['layout'];
		echo '<div class="' . ( $list ? 'gf-stack' : 'gf-grid gf-grid--' . (int) $settings['columns'] ) . '">';
		foreach ( $ids as $id ) {
			echo gf_card( $id, $list ? 'row' : 'default', array( 'class' => 'gf-reveal' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</div>';
	}
}

/**
 * Ad location.
 */
class GF_Widget_Ad extends GF_Widget {
	public function get_name() {
		return 'gf-ad';
	}
	public function get_title() {
		return __( 'Emplacement publicitaire', 'galsenfoot' );
	}
	public function get_icon() {
		return 'eicon-banner';
	}
	public function get_keywords() {
		return array( 'pub', 'publicité', 'adsense', 'google', 'ads', 'bannière' );
	}
	protected function register_controls() {
		$this->start_controls_section( 'section_content', array( 'label' => __( 'Publicité', 'galsenfoot' ) ) );
		$options = array();
		foreach ( gf_ad_locations() as $key => $location ) {
			$options[ $key ] = $location[0];
		}
		$this->add_control(
			'location',
			array(
				'label'       => __( 'Emplacement', 'galsenfoot' ),
				'description' => __( 'Le code de chaque emplacement se règle dans Apparence → Publicités & Google.', 'galsenfoot' ),
				'type'        => Controls_Manager::SELECT,
				'options'     => $options,
				'default'     => 'home_top',
			)
		);
		$this->end_controls_section();
	}
	protected function render() {
		echo gf_ad( $this->get_settings_for_display( 'location' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
}

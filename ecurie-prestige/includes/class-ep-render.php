<?php
/**
 * Moteur d’affichage partagé par les widgets Elementor, les shortcodes et la page d’un cheval.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EP_Render {

	private static $in_single = false;

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 5 );
		add_filter( 'the_content', array( __CLASS__, 'single_content' ) );
	}

	public static function register_assets() {
		wp_register_style( 'ecurie-prestige', EP_URL . 'assets/css/ecurie.css', array(), EP_VERSION );
		wp_register_script( 'ecurie-prestige', EP_URL . 'assets/js/ecurie.js', array(), EP_VERSION, true );
	}

	public static function enqueue() {
		if ( ! wp_style_is( 'ecurie-prestige', 'registered' ) ) {
			self::register_assets();
		}
		wp_enqueue_style( 'ecurie-prestige' );
		wp_enqueue_script( 'ecurie-prestige' );
	}

	/* ---------------------------------------------------------------------
	 * Données
	 * ------------------------------------------------------------------- */

	public static function defaults() {
		return array(
			'layout'       => 'grid',      // grid | mosaic | carousel | showcase.
			'autoplay'     => true,        // showcase : défilement automatique.
			'autoplay_delay' => 6,         // showcase : secondes par cheval.
			'ghost'        => true,        // showcase : nom géant en filigrane.
			'skin'         => 'prestige',  // prestige | editorial.
			'theme'        => 'dark',      // dark | light.
			'background'   => true,
			'source'       => 'all',       // all | discipline | manual | vedette.
			'disciplines'  => array(),
			'ids'          => array(),
			'statuts'      => array(),
			'number'       => 12,
			'orderby'      => 'menu_order',
			'order'        => 'ASC',
			'filters'      => true,
			'show_number'  => true,
			'show_badge'   => true,
			'show_meta'    => true,
			'show_tagline' => true,
			'show_stats'   => true,
			'click'        => 'modal',     // modal | page | none.
			'cta'          => __( 'Découvrir', 'ecurie-prestige' ),
			'eyebrow'      => '',
			'title'        => '',
			'intro'        => '',
			'align'        => 'left',
			'columns'      => 0,           // 0 = laissé au CSS / à Elementor.
			'class'        => '',
		);
	}

	public static function query( array $args ) {
		$q = array(
			'post_type'      => EP_CPT::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => (int) $args['number'] > 0 ? (int) $args['number'] : -1,
			'no_found_rows'  => true,
			'order'          => 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC',
		);

		switch ( $args['orderby'] ) {
			case 'title':
			case 'date':
			case 'rand':
				$q['orderby'] = $args['orderby'];
				break;
			case 'victoires':
			case 'gains':
				$q['meta_key'] = '_ep_' . $args['orderby']; // phpcs:ignore WordPress.DB.SlowDBQuery
				$q['orderby']  = array( 'meta_value_num' => $q['order'], 'title' => 'ASC' );
				break;
			default:
				$q['orderby'] = array( 'menu_order' => $q['order'], 'title' => 'ASC' );
		}

		$meta_query = array();
		if ( 'manual' === $args['source'] && ! empty( $args['ids'] ) ) {
			$q['post__in'] = array_map( 'absint', (array) $args['ids'] );
			if ( 'menu_order' === $args['orderby'] ) {
				$q['orderby'] = 'post__in';
			}
		} elseif ( 'discipline' === $args['source'] && ! empty( $args['disciplines'] ) ) {
			$terms          = (array) $args['disciplines'];
			$q['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array(
					'taxonomy' => EP_CPT::TAXONOMY,
					'field'    => is_numeric( reset( $terms ) ) ? 'term_id' : 'slug',
					'terms'    => $terms,
				),
			);
		} elseif ( 'vedette' === $args['source'] ) {
			$meta_query[] = array( 'key' => '_ep_vedette', 'value' => '1' );
		}

		$statuts = array_filter( (array) $args['statuts'] );
		if ( $statuts ) {
			$meta_query[] = array( 'key' => '_ep_statut', 'value' => $statuts, 'compare' => 'IN' );
		}
		if ( $meta_query ) {
			$q['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery
		}

		return get_posts( apply_filters( 'ecurie_prestige_query_args', $q, $args ) );
	}

	/** Rassemble toutes les informations d’un cheval. */
	public static function data( $post ) {
		$post = get_post( $post );
		if ( ! $post || EP_CPT::POST_TYPE !== $post->post_type ) {
			return null;
		}
		$d = array(
			'id'        => $post->ID,
			'slug'      => $post->post_name,
			'name'      => get_the_title( $post ),
			'url'       => get_permalink( $post ),
			'excerpt'   => has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 38, '…' ),
			'thumb'     => (int) get_post_thumbnail_id( $post ),
			'terms'     => array(),
		);
		foreach ( array_keys( EP_CPT::flat_fields() ) as $key ) {
			$d[ $key ] = EP_CPT::meta( $post->ID, $key );
		}
		$terms = get_the_terms( $post, EP_CPT::TAXONOMY );
		if ( $terms && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$d['terms'][ $term->slug ] = $term->name;
			}
		}
		$sexes   = EP_CPT::sexes();
		$statuts = EP_CPT::statuts();
		$d['sexe_label']   = ! empty( $d['sexe'] ) && isset( $sexes[ $d['sexe'] ] ) ? $sexes[ $d['sexe'] ] : '';
		$d['statut_label'] = ! empty( $d['statut'] ) && isset( $statuts[ $d['statut'] ] ) ? $statuts[ $d['statut'] ] : '';
		$d['age']          = '';
		if ( ! empty( $d['naissance'] ) ) {
			$age = (int) wp_date( 'Y' ) - (int) $d['naissance'];
			if ( $age >= 0 && $age < 45 ) {
				/* translators: %d : âge du cheval */
				$d['age'] = sprintf( _n( '%d an', '%d ans', max( 1, $age ), 'ecurie-prestige' ), $age );
			}
		}
		$d['gallery'] = array_values( array_filter( array_map( 'absint', explode( ',', (string) $d['galerie'] ) ) ) );
		if ( ! $d['thumb'] && $d['gallery'] ) {
			$d['thumb'] = $d['gallery'][0];
		}
		$d['results'] = self::parse_palmares( $d['palmares'] );
		$d['bio']     = '';
		if ( '' !== trim( $post->post_content ) ) {
			$d['bio'] = has_blocks( $post->post_content ) ? do_blocks( $post->post_content ) : wpautop( $post->post_content );
			$d['bio'] = wp_kses_post( $d['bio'] );
		}
		return apply_filters( 'ecurie_prestige_data', $d, $post );
	}

	public static function parse_palmares( $raw ) {
		$rows = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $raw ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$parts = array_map( 'trim', explode( '|', $line ) );
			if ( count( $parts ) < 2 ) {
				$rows[] = array( 'year' => '', 'race' => $parts[0], 'rank' => '', 'place' => '' );
				continue;
			}
			$rows[] = array(
				'year'  => $parts[0],
				'race'  => $parts[1],
				'rank'  => isset( $parts[2] ) ? $parts[2] : '',
				'place' => isset( $parts[3] ) ? $parts[3] : '',
			);
		}
		return $rows;
	}

	/** 1 250 000 → « 1,25 M€ » ; 86 500 → « 86,5 k€ ». */
	public static function compact_money( $amount ) {
		$amount = (float) $amount;
		if ( $amount >= 1000000 ) {
			return number_format_i18n( $amount / 1000000, fmod( $amount, 1000000 ) ? ( fmod( $amount, 100000 ) ? 2 : 1 ) : 0 ) . ' M€';
		}
		if ( $amount >= 10000 ) {
			return number_format_i18n( $amount / 1000, fmod( $amount, 1000 ) >= 100 ? 1 : 0 ) . ' k€';
		}
		return number_format_i18n( $amount ) . ' €';
	}

	/** Statistiques affichables (seules les valeurs renseignées sont retournées). */
	public static function stats( array $d, $with_gains = true ) {
		$stats = array();
		if ( (int) $d['courses'] > 0 ) {
			$stats[] = array( 'label' => _n( 'Course', 'Courses', (int) $d['courses'], 'ecurie-prestige' ), 'value' => (int) $d['courses'], 'display' => number_format_i18n( (int) $d['courses'] ) );
		}
		if ( (int) $d['victoires'] > 0 || (int) $d['courses'] > 0 ) {
			$stats[] = array( 'label' => _n( 'Victoire', 'Victoires', (int) $d['victoires'], 'ecurie-prestige' ), 'value' => (int) $d['victoires'], 'display' => number_format_i18n( (int) $d['victoires'] ), 'accent' => true );
		}
		if ( (int) $d['places'] > 0 ) {
			$stats[] = array( 'label' => _n( 'Place', 'Places', (int) $d['places'], 'ecurie-prestige' ), 'value' => (int) $d['places'], 'display' => number_format_i18n( (int) $d['places'] ) );
		}
		if ( $with_gains && (int) $d['gains'] > 0 ) {
			$stats[] = array( 'label' => __( 'Gains', 'ecurie-prestige' ), 'value' => null, 'display' => self::compact_money( $d['gains'] ) );
		}
		return $stats;
	}

	/* ---------------------------------------------------------------------
	 * Briques HTML
	 * ------------------------------------------------------------------- */

	public static function icon( $name ) {
		$icons = array(
			'arrow' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M4 12h16M14 6l6 6-6 6"/></svg>',
			'prev'  => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M20 12H4M10 6l-6 6 6 6"/></svg>',
			'close' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 5l14 14M19 5L5 19"/></svg>',
			'play'  => '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>',
		);
		return isset( $icons[ $name ] ) ? $icons[ $name ] : '';
	}

	public static function image( array $d, $size = 'large', $class = 'ep-img', $sizes = '' ) {
		if ( $d['thumb'] ) {
			$attr = array( 'class' => $class, 'loading' => 'lazy', 'decoding' => 'async' );
			if ( $sizes ) {
				$attr['sizes'] = $sizes;
			}
			$img = wp_get_attachment_image( $d['thumb'], $size, false, $attr );
			if ( $img ) {
				return $img;
			}
		}
		$initial = function_exists( 'mb_substr' ) ? mb_substr( $d['name'], 0, 1 ) : substr( $d['name'], 0, 1 );
		return '<span class="' . esc_attr( $class ) . ' ep-img--placeholder" aria-hidden="true"><span>' . esc_html( $initial ) . '</span></span>';
	}

	private static function stats_html( array $stats, $class, $animate = false ) {
		if ( ! $stats ) {
			return '';
		}
		$html = '<dl class="' . esc_attr( $class ) . '">';
		foreach ( $stats as $stat ) {
			$count = $animate && null !== $stat['value'] ? ' data-ep-count="' . esc_attr( $stat['value'] ) . '"' : '';
			$html .= '<div class="ep-stat' . ( empty( $stat['accent'] ) ? '' : ' ep-stat--accent' ) . '"><dt>' . esc_html( $stat['label'] ) . '</dt><dd' . $count . '>' . esc_html( $stat['display'] ) . '</dd></div>';
		}
		return $html . '</dl>';
	}

	private static function meta_line( array $d ) {
		return implode( ' <i aria-hidden="true">·</i> ', array_map( 'esc_html', array_filter( array( $d['race'], $d['sexe_label'], $d['age'], $d['robe'] ) ) ) );
	}

	public static function card( array $d, array $args, $index ) {
		$modal = 'modal' === $args['click'];
		$tag   = 'none' === $args['click'] ? 'div' : ( $modal ? 'button' : 'a' );
		$attrs = 'class="ep-card__link"';
		if ( 'a' === $tag ) {
			$attrs .= ' href="' . esc_url( $d['url'] ) . '"';
		} elseif ( 'button' === $tag ) {
			/* translators: %s : nom du cheval */
			$attrs .= ' type="button" data-ep-open aria-haspopup="dialog" aria-label="' . esc_attr( sprintf( __( 'Voir la fiche de %s', 'ecurie-prestige' ), $d['name'] ) ) . '"';
		}

		$badge = $d['terms'] ? reset( $d['terms'] ) : '';
		$meta  = $args['show_meta'] ? self::meta_line( $d ) : '';

		$html  = '<article class="ep-card' . ( 'showcase' === $args['layout'] ? ' ep-slide' : '' ) . '" data-ep-terms="' . esc_attr( implode( ' ', array_keys( $d['terms'] ) ) ) . '" style="--ep-i:' . (int) $index . '">';
		$html .= '<' . $tag . ' ' . $attrs . '>';
		$html .= '<span class="ep-card__media">' . self::image( $d, 'large', 'ep-img', '(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw' ) . '</span>';
		$html .= '<span class="ep-card__shade" aria-hidden="true"></span>';
		if ( $args['show_number'] ) {
			$html .= '<span class="ep-card__num" aria-hidden="true">' . esc_html( str_pad( (string) ( $index + 1 ), 2, '0', STR_PAD_LEFT ) ) . '</span>';
		}
		if ( $args['show_badge'] && $badge ) {
			$html .= '<span class="ep-card__badge">' . esc_html( $badge ) . '</span>';
		}
		$html .= '<span class="ep-card__body">';
		if ( $meta ) {
			$html .= '<span class="ep-card__meta">' . $meta . '</span>';
		}
		$html .= '<span class="ep-card__name" role="heading" aria-level="3">' . esc_html( $d['name'] ) . '</span>';
		if ( $args['show_tagline'] && $d['surnom'] ) {
			$html .= '<span class="ep-card__tagline">' . esc_html( $d['surnom'] ) . '</span>';
		}
		$html .= '<span class="ep-card__reveal"><span class="ep-card__reveal-in">';
		if ( $args['show_stats'] ) {
			$stats = self::stats( $d, false );
			if ( $stats ) {
				$html .= '<span class="ep-card__stats">';
				foreach ( $stats as $stat ) {
					$html .= '<span class="ep-stat' . ( empty( $stat['accent'] ) ? '' : ' ep-stat--accent' ) . '"><b>' . esc_html( $stat['display'] ) . '</b><small>' . esc_html( $stat['label'] ) . '</small></span>';
				}
				$html .= '</span>';
			}
		}
		if ( 'none' !== $args['click'] && '' !== $args['cta'] ) {
			$html .= '<span class="ep-card__cta">' . esc_html( $args['cta'] ) . self::icon( 'arrow' ) . '</span>';
		}
		$html .= '</span></span></span>';
		$html .= '</' . $tag . '>';
		if ( $modal ) {
			$html .= '<template class="ep-card__fiche">' . self::fiche( $d, 'modal' ) . '</template>';
		}
		return $html . '</article>';
	}

	/**
	 * Fiche détaillée.
	 *
	 * @param string $context modal | single.
	 */
	public static function fiche( array $d, $context = 'modal' ) {
		$single = 'single' === $context;
		$html   = '<div class="ep-fiche ep-fiche--' . esc_attr( $context ) . '">';

		if ( ! $single ) {
			$html .= '<div class="ep-fiche__media"><div class="ep-fiche__stage">' . self::image( $d, 'large', 'ep-img ep-fiche__main', '(max-width: 900px) 100vw, 45vw' ) . '</div>';
			$html .= self::gallery_html( $d, true ) . '</div>';
		}

		$html .= '<div class="ep-fiche__content">';
		if ( ! $single ) {
			$kicker = array_filter( array( $d['terms'] ? implode( ' / ', $d['terms'] ) : '', $d['statut_label'] ) );
			if ( $kicker ) {
				$html .= '<p class="ep-eyebrow">' . esc_html( implode( ' — ', $kicker ) ) . '</p>';
			}
			$html .= '<h2 class="ep-fiche__name" id="ep-fiche-title-' . (int) $d['id'] . '">' . esc_html( $d['name'] ) . '</h2>';
			if ( $d['surnom'] ) {
				$html .= '<p class="ep-fiche__tagline">' . esc_html( $d['surnom'] ) . '</p>';
			}
		}

		$html .= self::stats_html( self::stats( $d ), 'ep-fiche__stats', true );

		if ( ! $single && $d['bio'] ) {
			$html .= '<div class="ep-fiche__bio">' . $d['bio'] . '</div>';
		}

		// Caractéristiques.
		$specs = array(
			__( 'Race', 'ecurie-prestige' )         => $d['race'],
			__( 'Sexe', 'ecurie-prestige' )         => $d['sexe_label'],
			__( 'Robe', 'ecurie-prestige' )         => $d['robe'],
			__( 'Naissance', 'ecurie-prestige' )    => $d['naissance'] ? $d['naissance'] . ( $d['age'] ? ' (' . $d['age'] . ')' : '' ) : '',
			__( 'Aptitude', 'ecurie-prestige' )     => $d['aptitude'],
			__( 'Statut', 'ecurie-prestige' )       => $single ? $d['statut_label'] : '',
			__( 'Entraîneur', 'ecurie-prestige' )   => $d['entraineur'],
			__( 'Jockey', 'ecurie-prestige' )       => $d['jockey'],
			__( 'Propriétaire', 'ecurie-prestige' ) => $d['proprietaire'],
			__( 'Éleveur', 'ecurie-prestige' )      => $d['eleveur'],
		);
		$specs = array_filter( $specs );
		if ( $specs ) {
			$html .= '<section class="ep-fiche__section"><h3 class="ep-fiche__h">' . esc_html__( 'Caractéristiques', 'ecurie-prestige' ) . '</h3><dl class="ep-specs">';
			foreach ( $specs as $label => $value ) {
				$html .= '<div><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd></div>';
			}
			$html .= '</dl></section>';
		}

		// Pedigree.
		if ( $d['pere'] || $d['mere'] ) {
			$html .= '<section class="ep-fiche__section"><h3 class="ep-fiche__h">' . esc_html__( 'Pedigree', 'ecurie-prestige' ) . '</h3><div class="ep-pedigree">';
			$html .= '<div class="ep-pedigree__self">' . esc_html( $d['name'] ) . '</div><div class="ep-pedigree__parents">';
			if ( $d['pere'] ) {
				$html .= '<div class="ep-pedigree__node"><small>' . esc_html__( 'Père', 'ecurie-prestige' ) . '</small><b>' . esc_html( $d['pere'] ) . '</b></div>';
			}
			if ( $d['mere'] ) {
				$html .= '<div class="ep-pedigree__node"><small>' . esc_html__( 'Mère', 'ecurie-prestige' ) . '</small><b>' . esc_html( $d['mere'] ) . '</b>';
				if ( $d['pere_mere'] ) {
					/* translators: %s : nom du père de mère */
					$html .= '<em>' . esc_html( sprintf( __( 'par %s', 'ecurie-prestige' ), $d['pere_mere'] ) ) . '</em>';
				}
				$html .= '</div>';
			}
			$html .= '</div></div></section>';
		}

		// Palmarès.
		if ( $d['results'] ) {
			$html .= '<section class="ep-fiche__section"><h3 class="ep-fiche__h">' . esc_html__( 'Palmarès', 'ecurie-prestige' ) . '</h3><ol class="ep-palmares">';
			foreach ( $d['results'] as $row ) {
				$win   = (bool) preg_match( '/^\s*(1er|1re|1ère|1st|1|vainqueur)\b/iu', $row['rank'] );
				$html .= '<li class="' . ( $win ? 'is-win' : '' ) . '"><span class="ep-palmares__year">' . esc_html( $row['year'] ) . '</span>';
				$html .= '<span class="ep-palmares__race"><b>' . esc_html( $row['race'] ) . '</b>' . ( $row['place'] ? '<small>' . esc_html( $row['place'] ) . '</small>' : '' ) . '</span>';
				$html .= '<span class="ep-palmares__rank">' . esc_html( $row['rank'] ) . '</span></li>';
			}
			$html .= '</ol></section>';
		}

		if ( $single ) {
			$html .= self::gallery_html( $d, false );
		}

		// Actions.
		$actions = '';
		if ( $d['video'] ) {
			$actions .= '<a class="ep-btn ep-btn--ghost" href="' . esc_url( $d['video'] ) . '" target="_blank" rel="noopener">' . self::icon( 'play' ) . esc_html__( 'Voir la vidéo', 'ecurie-prestige' ) . '</a>';
		}
		if ( ! $single ) {
			$actions .= '<a class="ep-btn" href="' . esc_url( $d['url'] ) . '">' . esc_html__( 'Page complète', 'ecurie-prestige' ) . self::icon( 'arrow' ) . '</a>';
		}
		if ( $actions ) {
			$html .= '<div class="ep-fiche__actions">' . $actions . '</div>';
		}

		return $html . '</div></div>';
	}

	private static function gallery_html( array $d, $thumbs ) {
		$ids = $d['gallery'];
		if ( ! $ids ) {
			return '';
		}
		if ( $thumbs ) {
			if ( $d['thumb'] ) {
				array_unshift( $ids, $d['thumb'] );
			}
			$ids  = array_slice( array_unique( $ids ), 0, 8 );
			$html = '<div class="ep-thumbs">';
			foreach ( $ids as $i => $id ) {
				$full = wp_get_attachment_image_src( $id, 'large' );
				if ( ! $full ) {
					continue;
				}
				/* translators: %d : numéro de la photo */
				$html .= '<button type="button" class="ep-thumbs__item' . ( 0 === $i ? ' is-active' : '' ) . '" data-ep-full="' . esc_url( $full[0] ) . '" aria-label="' . esc_attr( sprintf( __( 'Photo %d', 'ecurie-prestige' ), $i + 1 ) ) . '">' . wp_get_attachment_image( $id, 'thumbnail', false, array( 'loading' => 'lazy' ) ) . '</button>';
			}
			return $html . '</div>';
		}
		$html = '<section class="ep-fiche__section"><h3 class="ep-fiche__h">' . esc_html__( 'Galerie', 'ecurie-prestige' ) . '</h3><div class="ep-gallery">';
		foreach ( $ids as $id ) {
			$full = wp_get_attachment_image_src( $id, 'full' );
			if ( $full ) {
				$html .= '<a href="' . esc_url( $full[0] ) . '" class="ep-gallery__item">' . wp_get_attachment_image( $id, 'large', false, array( 'loading' => 'lazy' ) ) . '</a>';
			}
		}
		return $html . '</div></section>';
	}

	private static function wrapper_classes( array $args, $extra = '' ) {
		$classes = array( 'ep', 'ep--' . ( 'light' === $args['theme'] ? 'light' : 'dark' ) );
		if ( ! empty( $args['background'] ) ) {
			$classes[] = 'ep--bg';
		}
		if ( $extra ) {
			$classes[] = $extra;
		}
		if ( ! empty( $args['class'] ) ) {
			$classes[] = $args['class'];
		}
		return implode( ' ', array_map( 'sanitize_html_class', explode( ' ', implode( ' ', $classes ) ) ) );
	}

	private static function header_html( array $args ) {
		if ( '' === $args['eyebrow'] && '' === $args['title'] && '' === $args['intro'] ) {
			return '';
		}
		$html = '<header class="ep-head ep-head--' . ( 'center' === $args['align'] ? 'center' : 'left' ) . '">';
		if ( '' !== $args['eyebrow'] ) {
			$html .= '<p class="ep-eyebrow">' . esc_html( $args['eyebrow'] ) . '</p>';
		}
		if ( '' !== $args['title'] ) {
			$html .= '<h2 class="ep-head__title">' . wp_kses( $args['title'], array( 'em' => array(), 'br' => array(), 'strong' => array() ) ) . '</h2>';
		}
		if ( '' !== $args['intro'] ) {
			$html .= '<p class="ep-head__intro">' . esc_html( $args['intro'] ) . '</p>';
		}
		return $html . '</header>';
	}

	private static function dialog_html() {
		return '<dialog class="ep-modal" aria-modal="true">'
			. '<div class="ep-modal__bar">'
			. '<button type="button" class="ep-modal__nav" data-ep-prev aria-label="' . esc_attr__( 'Cheval précédent', 'ecurie-prestige' ) . '">' . self::icon( 'prev' ) . '</button>'
			. '<span class="ep-modal__pos" aria-live="polite"></span>'
			. '<button type="button" class="ep-modal__nav" data-ep-next aria-label="' . esc_attr__( 'Cheval suivant', 'ecurie-prestige' ) . '">' . self::icon( 'arrow' ) . '</button>'
			. '<button type="button" class="ep-modal__close" data-ep-close aria-label="' . esc_attr__( 'Fermer', 'ecurie-prestige' ) . '">' . self::icon( 'close' ) . '</button>'
			. '</div><div class="ep-modal__body"></div></dialog>';
	}

	/* ---------------------------------------------------------------------
	 * Blocs complets
	 * ------------------------------------------------------------------- */

	/** Grille, mosaïque ou carrousel de chevaux. */
	public static function collection( array $args ) {
		$args  = wp_parse_args( $args, self::defaults() );
		$posts = self::query( $args );
		self::enqueue();

		$layout = in_array( $args['layout'], array( 'grid', 'mosaic', 'carousel', 'showcase' ), true ) ? $args['layout'] : 'grid';
		$skin   = 'editorial' === $args['skin'] && 'showcase' !== $layout ? 'editorial' : 'prestige';
		$args['layout'] = $layout;
		$style  = (int) $args['columns'] > 0 ? ' style="--ep-cols-d:' . (int) $args['columns'] . ';--ep-per-view-d:' . (int) $args['columns'] . '"' : '';

		$html = '<div class="' . esc_attr( self::wrapper_classes( $args, 'ep-collection' ) ) . '" data-ep="collection"' . $style . '>';
		$html .= self::header_html( $args );

		if ( ! $posts ) {
			$html .= '<p class="ep-empty">' . esc_html__( 'Aucun cheval à afficher pour le moment.', 'ecurie-prestige' ) . '</p></div>';
			return $html;
		}

		$cards = '';
		$terms = array();
		$index = 0;
		foreach ( $posts as $post ) {
			$d = self::data( $post );
			if ( ! $d ) {
				continue;
			}
			$terms += $d['terms'];
			$cards .= self::card( $d, $args, $index++ );
		}

		if ( $args['filters'] && count( $terms ) > 1 ) {
			asort( $terms );
			$html .= '<div class="ep-filters" role="group" aria-label="' . esc_attr__( 'Filtrer par discipline', 'ecurie-prestige' ) . '">';
			$html .= '<button type="button" class="ep-filters__btn is-active" data-ep-filter="*" aria-pressed="true">' . esc_html__( 'Tous', 'ecurie-prestige' ) . '</button>';
			foreach ( $terms as $slug => $name ) {
				$html .= '<button type="button" class="ep-filters__btn" data-ep-filter="' . esc_attr( $slug ) . '" aria-pressed="false">' . esc_html( $name ) . '</button>';
			}
			$html .= '</div>';
		}

		if ( 'showcase' === $layout ) {
			$delay = max( 2, min( 30, (float) $args['autoplay_delay'] ) );
			$html .= '<div class="ep-showcase ep-skin-prestige" style="--ep-autoplay:' . esc_attr( $delay ) . 's"' . ( $args['autoplay'] ? ' data-ep-autoplay' : '' ) . '>';
			if ( $args['ghost'] ) {
				$html .= '<div class="ep-showcase__ghost" aria-hidden="true"><span></span></div>';
			}
			$html .= '<div class="ep-showcase__stage" role="group" aria-roledescription="' . esc_attr__( 'carrousel', 'ecurie-prestige' ) . '" aria-label="' . esc_attr__( 'Les chevaux de l’écurie', 'ecurie-prestige' ) . '"><div class="ep-showcase__sizer" aria-hidden="true"></div>' . $cards . '</div>';
			$html .= '<div class="ep-showcase__ui"><div class="ep-showcase__count"><b aria-live="polite"></b><span class="ep-showcase__bar" aria-hidden="true"><span></span></span><i></i></div>';
			$html .= '<button type="button" class="ep-carousel__btn" data-ep-go="-1" aria-label="' . esc_attr__( 'Cheval précédent', 'ecurie-prestige' ) . '">' . self::icon( 'prev' ) . '</button>';
			$html .= '<button type="button" class="ep-carousel__btn" data-ep-go="1" aria-label="' . esc_attr__( 'Cheval suivant', 'ecurie-prestige' ) . '">' . self::icon( 'arrow' ) . '</button></div></div>';
		} elseif ( 'carousel' === $layout ) {
			$html .= '<div class="ep-carousel ep-skin-' . $skin . '"><div class="ep-carousel__track" tabindex="0">' . $cards . '</div>';
			$html .= '<div class="ep-carousel__ui"><div class="ep-carousel__progress" aria-hidden="true"><span></span></div>';
			$html .= '<button type="button" class="ep-carousel__btn" data-ep-scroll="-1" aria-label="' . esc_attr__( 'Précédent', 'ecurie-prestige' ) . '">' . self::icon( 'prev' ) . '</button>';
			$html .= '<button type="button" class="ep-carousel__btn" data-ep-scroll="1" aria-label="' . esc_attr__( 'Suivant', 'ecurie-prestige' ) . '">' . self::icon( 'arrow' ) . '</button></div></div>';
		} else {
			$html .= '<div class="ep-grid ep-grid--' . $layout . ' ep-skin-' . $skin . '">' . $cards . '</div>';
		}

		if ( 'modal' === $args['click'] ) {
			$html .= self::dialog_html();
		}
		return $html . '</div>';
	}

	/** Mise en avant d’un cheval. */
	public static function featured( $post_id, array $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'theme'      => 'dark',
				'background' => true,
				'reverse'    => false,
				'eyebrow'    => '',
				'cta'        => __( 'Découvrir sa fiche', 'ecurie-prestige' ),
				'click'      => 'page',
				'show_stats' => true,
				'show_specs' => true,
				'class'      => '',
			)
		);
		if ( ! $post_id ) {
			$latest  = self::query( wp_parse_args( array( 'source' => 'vedette', 'number' => 1 ), self::defaults() ) );
			$latest  = $latest ? $latest : self::query( wp_parse_args( array( 'number' => 1 ), self::defaults() ) );
			$post_id = $latest ? $latest[0]->ID : 0;
		}
		$d = $post_id ? self::data( $post_id ) : null;
		if ( ! $d ) {
			return '';
		}
		self::enqueue();

		$eyebrow = '' !== $args['eyebrow'] ? $args['eyebrow'] : implode( ' — ', array_filter( array( $d['terms'] ? reset( $d['terms'] ) : '', $d['statut_label'] ) ) );
		$modal   = 'modal' === $args['click'];

		$html  = '<div class="' . esc_attr( self::wrapper_classes( $args, 'ep-featured' . ( $args['reverse'] ? ' ep-featured--reverse' : '' ) ) ) . '" data-ep="featured">';
		$html .= '<div class="ep-featured__media">' . self::image( $d, '1536x1536', 'ep-img', '(max-width: 900px) 100vw, 55vw' ) . '<span class="ep-featured__frame" aria-hidden="true"></span></div>';
		$html .= '<div class="ep-featured__content">';
		if ( $eyebrow ) {
			$html .= '<p class="ep-eyebrow">' . esc_html( $eyebrow ) . '</p>';
		}
		$html .= '<h2 class="ep-featured__name">' . esc_html( $d['name'] ) . '</h2>';
		if ( $d['surnom'] ) {
			$html .= '<p class="ep-featured__tagline">' . esc_html( $d['surnom'] ) . '</p>';
		}
		if ( $d['excerpt'] ) {
			$html .= '<p class="ep-featured__text">' . esc_html( $d['excerpt'] ) . '</p>';
		}
		if ( $args['show_stats'] ) {
			$html .= self::stats_html( self::stats( $d ), 'ep-featured__stats', true );
		}
		if ( $args['show_specs'] ) {
			$line = array_filter(
				array(
					self::meta_line( $d ),
					/* translators: 1 : père, 2 : mère */
					$d['pere'] && $d['mere'] ? esc_html( sprintf( __( 'par %1$s et %2$s', 'ecurie-prestige' ), $d['pere'], $d['mere'] ) ) : '',
				)
			);
			if ( $line ) {
				$html .= '<p class="ep-featured__specs">' . implode( '<br>', $line ) . '</p>';
			}
		}
		if ( 'none' !== $args['click'] && '' !== $args['cta'] ) {
			if ( $modal ) {
				$html .= '<div class="ep-card ep-featured__action"><button type="button" class="ep-btn" data-ep-open aria-haspopup="dialog">' . esc_html( $args['cta'] ) . self::icon( 'arrow' ) . '</button><template class="ep-card__fiche">' . self::fiche( $d, 'modal' ) . '</template></div>';
			} else {
				$html .= '<a class="ep-btn" href="' . esc_url( $d['url'] ) . '">' . esc_html( $args['cta'] ) . self::icon( 'arrow' ) . '</a>';
			}
		}
		$html .= '</div>';
		if ( $modal ) {
			$html .= self::dialog_html();
		}
		return $html . '</div>';
	}

	/** Ajoute la fiche technique sous le contenu de la page d’un cheval. */
	public static function single_content( $content ) {
		if ( self::$in_single || ! is_singular( EP_CPT::POST_TYPE ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		if ( ! apply_filters( 'ecurie_prestige_single_fiche', true, get_the_ID() ) ) {
			return $content;
		}
		self::$in_single = true;
		$d               = self::data( get_the_ID() );
		self::$in_single = false;
		if ( ! $d ) {
			return $content;
		}
		self::enqueue();
		$theme = apply_filters( 'ecurie_prestige_single_theme', 'light', $d['id'] );
		$head  = $d['surnom'] ? '<p class="ep-fiche__tagline">' . esc_html( $d['surnom'] ) . '</p>' : '';
		$class = 'ep ep--' . ( 'dark' === $theme ? 'dark ep--bg' : 'light' ) . ' ep-single';
		return ( $head ? '<div class="' . $class . ' ep-single--head">' . $head . '</div>' : '' )
			. $content
			. '<div class="' . $class . '" data-ep="single">' . self::fiche( $d, 'single' ) . '</div>';
	}
}

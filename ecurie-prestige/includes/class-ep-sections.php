<?php
/**
 * Blocs éditoriaux de page (bandeau, mot de la maison, piliers, infrastructures, appel à contact)
 * et page « L’élevage » prête à l’emploi.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EP_Sections {

	public static function init() {
		add_shortcode( 'ecurie_elevage', array( __CLASS__, 'shortcode_elevage' ) );
	}

	/** Contenu par défaut de la page « L’élevage ». */
	public static function defaults( $block ) {
		$all = array(
			'hero'      => array(
				'theme'    => 'dark',
				'eyebrow'  => __( 'Écuries Sam Wagne', 'ecurie-prestige' ),
				'title'    => __( 'L’élevage', 'ecurie-prestige' ),
				'subtitle' => __( 'Tradition équestre et standards modernes, entre Thiès et Dakar.', 'ecurie-prestige' ),
				'image'    => array(),
			),
			'statement' => array(
				'theme'     => 'light',
				'eyebrow'   => __( 'Le mot de la maison', 'ecurie-prestige' ),
				'lead'      => __( 'Aux Écuries Sam Wagne, l’élevage est bien plus qu’une activité : c’est une passion et un engagement envers l’excellence.', 'ecurie-prestige' ),
				'text'      => __( 'Situés entre Thiès et Dakar, nous combinons tradition équestre et standards modernes pour élever, soigner et préparer des chevaux d’exception pour la compétition, le loisir et le travail.', 'ecurie-prestige' ),
				'signature' => __( 'Écuries Sam Wagne', 'ecurie-prestige' ),
				'tags'      => __( 'Compétition, Loisir, Travail', 'ecurie-prestige' ),
				'image'     => array(),
			),
			'pillars'   => array(
				'theme'   => 'dark',
				'eyebrow' => __( 'Notre méthode', 'ecurie-prestige' ),
				'title'   => __( 'Nos piliers <em>d’élevage</em>', 'ecurie-prestige' ),
				'intro'   => '',
				'items'   => array(
					array(
						'title' => __( 'Sélection et génétique', 'ecurie-prestige' ),
						'text'  => __( 'Travail rigoureux sur les lignées pour garantir des chevaux dotés d’un excellent tempérament, d’une santé robuste et de belles aptitudes sportives (endurance, vitesse et maniabilité).', 'ecurie-prestige' ),
					),
					array(
						'title' => __( 'Suivi sanitaire & nutrition sur-mesure', 'ecurie-prestige' ),
						'text'  => __( 'Un encadrement vétérinaire strict, des programmes de vaccination et de vermifugation à jour, ainsi qu’une alimentation équilibrée et adaptée aux besoins spécifiques de chaque équidé selon son âge et son stade de développement.', 'ecurie-prestige' ),
					),
					array(
						'title' => __( 'Sevrage et éducation précoce', 'ecurie-prestige' ),
						'text'  => __( 'Une socialisation menée en douceur dès le plus jeune âge. Nos poulains grandissent en extérieur, au contact du troupeau, ce qui favorise leur équilibre mental et physique avant le débourrage.', 'ecurie-prestige' ),
					),
				),
			),
			'features'  => array(
				'theme'   => 'light',
				'eyebrow' => __( 'Le domaine', 'ecurie-prestige' ),
				'title'   => __( 'Nos infrastructures <em>adaptées</em>', 'ecurie-prestige' ),
				'intro'   => '',
				'reverse' => false,
				'image'   => array(),
				'image_2' => array(),
				'items'   => array(
					array(
						'title' => __( 'Des espaces de vie spacieux', 'ecurie-prestige' ),
						'text'  => __( 'Des paddocks sécurisés et des boxes aérés conçus pour le bien-être animal.', 'ecurie-prestige' ),
					),
					array(
						'title' => __( 'Un cadre idéal', 'ecurie-prestige' ),
						'text'  => __( 'Entre la quiétude de nos installations et l’accès à des pistes d’entraînement adaptées dans la zone Thiès-Dakar.', 'ecurie-prestige' ),
					),
				),
			),
			'cta'       => array(
				'theme'    => 'dark',
				'eyebrow'  => __( 'Nous rencontrer', 'ecurie-prestige' ),
				'title'    => __( 'Venez découvrir <em>l’élevage</em>', 'ecurie-prestige' ),
				'text'     => '',
				'button'   => __( 'Nous contacter', 'ecurie-prestige' ),
				'url'      => '/contact/',
				'button_2' => __( 'Voir nos chevaux', 'ecurie-prestige' ),
				'url_2'    => '',
			),
		);
		$base = array( 'background' => true, 'class' => '' );
		return isset( $all[ $block ] ) ? $all[ $block ] + $base : $base;
	}

	/* ---------------------------------------------------------------------
	 * Outils
	 * ------------------------------------------------------------------- */

	private static function inline( $text ) {
		return wp_kses( (string) $text, array( 'em' => array(), 'strong' => array(), 'br' => array() ) );
	}

	private static function open( array $a, $block ) {
		EP_Render::enqueue();
		$classes = 'ep ep--' . ( 'light' === $a['theme'] ? 'light' : 'dark' ) . ( empty( $a['background'] ) ? '' : ' ep--bg' ) . ' ep-sec ep-sec--' . $block;
		return '<section class="' . esc_attr( $classes ) . '" data-ep="section">';
	}

	/** Image depuis un réglage Elementor (id + url) ou un identifiant de média. */
	private static function img( $image, $size = 'large', $sizes = '100vw' ) {
		if ( is_numeric( $image ) ) {
			$image = array( 'id' => (int) $image );
		}
		if ( ! is_array( $image ) ) {
			return '';
		}
		if ( ! empty( $image['id'] ) ) {
			$html = wp_get_attachment_image( (int) $image['id'], $size, false, array( 'class' => 'ep-img', 'loading' => 'lazy', 'sizes' => $sizes ) );
			if ( $html ) {
				return $html;
			}
		}
		if ( ! empty( $image['url'] ) ) {
			return '<img class="ep-img" src="' . esc_url( $image['url'] ) . '" alt="" loading="lazy" decoding="async">';
		}
		return '';
	}

	private static function head( array $a, $tag = 'h2' ) {
		$html = '<header class="ep-sec__head" data-ep-reveal>';
		if ( '' !== $a['eyebrow'] ) {
			$html .= '<p class="ep-eyebrow">' . esc_html( $a['eyebrow'] ) . '</p>';
		}
		if ( '' !== $a['title'] ) {
			$html .= '<' . $tag . ' class="ep-head__title">' . self::inline( $a['title'] ) . '</' . $tag . '>';
		}
		if ( ! empty( $a['intro'] ) ) {
			$html .= '<p class="ep-head__intro">' . esc_html( $a['intro'] ) . '</p>';
		}
		return $html . '</header>';
	}

	private static function items( $items ) {
		$clean = array();
		foreach ( (array) $items as $item ) {
			if ( is_array( $item ) && ( ! empty( $item['title'] ) || ! empty( $item['text'] ) ) ) {
				$clean[] = array( 'title' => isset( $item['title'] ) ? $item['title'] : '', 'text' => isset( $item['text'] ) ? $item['text'] : '' );
			}
		}
		return $clean;
	}

	/* ---------------------------------------------------------------------
	 * Blocs
	 * ------------------------------------------------------------------- */

	/** Bandeau de page : grand titre sur photo. */
	public static function hero( array $a = array() ) {
		$a     = wp_parse_args( $a, self::defaults( 'hero' ) );
		$image = self::img( $a['image'], '2048x2048' );
		$html  = self::open( $a, 'hero' . ( $image ? ' ep-sec--has-image' : '' ) );
		if ( $image ) {
			$html .= '<div class="ep-sec__bg" aria-hidden="true">' . $image . '</div>';
		} else {
			$html .= '<div class="ep-hero__ghost" aria-hidden="true"><span>' . esc_html( wp_strip_all_tags( $a['title'] ) ) . '</span></div>';
		}
		$html .= '<div class="ep-sec__inner">';
		if ( '' !== $a['eyebrow'] ) {
			$html .= '<p class="ep-eyebrow" data-ep-reveal>' . esc_html( $a['eyebrow'] ) . '</p>';
		}
		$html .= '<h1 class="ep-sec__display" data-ep-reveal>' . self::inline( $a['title'] ) . '</h1>';
		if ( '' !== $a['subtitle'] ) {
			$html .= '<p class="ep-sec__subtitle" data-ep-reveal>' . esc_html( $a['subtitle'] ) . '</p>';
		}
		return $html . '</div><span class="ep-sec__scroll" aria-hidden="true"></span></section>';
	}

	/** Le mot de la maison : grande citation éditoriale. */
	public static function statement( array $a = array() ) {
		$a     = wp_parse_args( $a, self::defaults( 'statement' ) );
		$image = self::img( $a['image'], 'large', '(max-width: 900px) 100vw, 40vw' );
		$html  = self::open( $a, 'statement' ) . '<div class="ep-sec__inner ep-statement' . ( $image ? ' ep-statement--image' : '' ) . '">';
		$html .= '<div class="ep-statement__label" data-ep-reveal>';
		if ( '' !== $a['eyebrow'] ) {
			$html .= '<p class="ep-eyebrow">' . esc_html( $a['eyebrow'] ) . '</p>';
		}
		$html .= '<span class="ep-statement__mark" aria-hidden="true">“</span></div>';
		$html .= '<div class="ep-statement__body">';
		if ( '' !== $a['lead'] ) {
			$html .= '<p class="ep-statement__lead" data-ep-reveal>' . self::inline( $a['lead'] ) . '</p>';
		}
		if ( '' !== $a['text'] ) {
			$html .= '<p class="ep-statement__text" data-ep-reveal>' . self::inline( $a['text'] ) . '</p>';
		}
		$tags = array_filter( array_map( 'trim', explode( ',', (string) $a['tags'] ) ) );
		if ( $tags || '' !== $a['signature'] ) {
			$html .= '<div class="ep-statement__foot" data-ep-reveal>';
			if ( '' !== $a['signature'] ) {
				$html .= '<p class="ep-statement__sign">' . esc_html( $a['signature'] ) . '</p>';
			}
			if ( $tags ) {
				$html .= '<p class="ep-statement__tags">';
				foreach ( $tags as $tag ) {
					$html .= '<span>' . esc_html( $tag ) . '</span>';
				}
				$html .= '</p>';
			}
			$html .= '</div>';
		}
		$html .= '</div>';
		if ( $image ) {
			$html .= '<div class="ep-statement__media" data-ep-reveal>' . $image . '<span class="ep-featured__frame" aria-hidden="true"></span></div>';
		}
		return $html . '</div></section>';
	}

	/** Piliers numérotés. */
	public static function pillars( array $a = array() ) {
		$a     = wp_parse_args( $a, self::defaults( 'pillars' ) );
		$items = self::items( $a['items'] );
		$html  = self::open( $a, 'pillars' ) . '<div class="ep-sec__inner">' . self::head( $a );
		$html .= '<div class="ep-pillars" style="--ep-n:' . max( 1, min( 4, count( $items ) ) ) . '">';
		foreach ( $items as $i => $item ) {
			$html .= '<article class="ep-pillar" data-ep-reveal><span class="ep-pillar__num" aria-hidden="true">' . esc_html( str_pad( (string) ( $i + 1 ), 2, '0', STR_PAD_LEFT ) ) . '</span>';
			$html .= '<h3 class="ep-pillar__title">' . self::inline( $item['title'] ) . '</h3><p class="ep-pillar__text">' . self::inline( $item['text'] ) . '</p></article>';
		}
		return $html . '</div></div></section>';
	}

	/** Infrastructures : photos + liste d’atouts. */
	public static function features( array $a = array() ) {
		$a      = wp_parse_args( $a, self::defaults( 'features' ) );
		$items  = self::items( $a['items'] );
		$image  = self::img( $a['image'], 'large', '(max-width: 900px) 100vw, 45vw' );
		$image2 = self::img( $a['image_2'], 'medium_large', '(max-width: 900px) 60vw, 25vw' );
		$html   = self::open( $a, 'features' ) . '<div class="ep-sec__inner ep-features' . ( $image ? ' ep-features--image' : '' ) . ( ! empty( $a['reverse'] ) ? ' ep-features--reverse' : '' ) . '">';
		if ( $image ) {
			$html .= '<div class="ep-features__media' . ( $image2 ? ' ep-features__media--duo' : '' ) . '" data-ep-reveal><div class="ep-features__main">' . $image . '</div>';
			if ( $image2 ) {
				$html .= '<div class="ep-features__second">' . $image2 . '</div>';
			}
			$html .= '</div>';
		}
		$html .= '<div class="ep-features__content">' . self::head( $a ) . '<div class="ep-features__list">';
		foreach ( $items as $i => $item ) {
			$html .= '<div class="ep-feature" data-ep-reveal><span class="ep-feature__num" aria-hidden="true">' . esc_html( str_pad( (string) ( $i + 1 ), 2, '0', STR_PAD_LEFT ) ) . '</span><div><h3 class="ep-feature__title">' . self::inline( $item['title'] ) . '</h3><p class="ep-feature__text">' . self::inline( $item['text'] ) . '</p></div></div>';
		}
		return $html . '</div></div></div></section>';
	}

	/** Appel à contact. */
	public static function cta( array $a = array() ) {
		$a    = wp_parse_args( $a, self::defaults( 'cta' ) );
		$html = self::open( $a, 'cta' ) . '<div class="ep-sec__inner">' . self::head( $a );
		if ( '' !== $a['text'] ) {
			$html .= '<p class="ep-cta__text" data-ep-reveal>' . self::inline( $a['text'] ) . '</p>';
		}
		$buttons = '';
		if ( '' !== $a['button'] && '' !== $a['url'] ) {
			$buttons .= '<a class="ep-btn" href="' . esc_url( $a['url'] ) . '">' . esc_html( $a['button'] ) . EP_Render::icon( 'arrow' ) . '</a>';
		}
		if ( '' !== $a['button_2'] && '' !== $a['url_2'] ) {
			$buttons .= '<a class="ep-btn ep-btn--ghost" href="' . esc_url( $a['url_2'] ) . '">' . esc_html( $a['button_2'] ) . '</a>';
		}
		if ( $buttons ) {
			$html .= '<div class="ep-cta__actions" data-ep-reveal>' . $buttons . '</div>';
		}
		return $html . '</div></section>';
	}

	/* ---------------------------------------------------------------------
	 * Page « L’élevage »
	 * ------------------------------------------------------------------- */

	/** [ecurie_elevage image_bandeau="12" image_mot="13" image_infra="14" image_infra_2="15" lien_contact="/contact/"] */
	public static function shortcode_elevage( $atts ) {
		$a = shortcode_atts(
			array(
				'image_bandeau' => 0,
				'image_mot'     => 0,
				'image_infra'   => 0,
				'image_infra_2' => 0,
				'lien_contact'  => '/contact/',
			),
			$atts,
			'ecurie_elevage'
		);
		$archive = get_post_type_archive_link( EP_CPT::POST_TYPE );
		return '<div class="ep-fullbleed">'
			. self::hero( array( 'image' => absint( $a['image_bandeau'] ) ) )
			. self::statement( array( 'image' => absint( $a['image_mot'] ) ) )
			. self::pillars()
			. self::features( array( 'image' => absint( $a['image_infra'] ), 'image_2' => absint( $a['image_infra_2'] ) ) )
			. self::cta( array( 'url' => esc_url_raw( $a['lien_contact'] ), 'url_2' => $archive ? $archive : '' ) )
			. '</div>';
	}

	/** Structure Elementor de la page (une section pleine largeur par bloc). */
	public static function elementor_template() {
		$content = array();
		foreach ( array( 'ep-page-hero', 'ep-statement', 'ep-pillars', 'ep-features', 'ep-cta' ) as $i => $widget ) {
			$zero      = array( 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true );
			$content[] = array(
				'id'       => substr( md5( 'ep-section-' . $i ), 0, 7 ),
				'elType'   => 'section',
				'isInner'  => false,
				'settings' => array( 'layout' => 'full_width', 'gap' => 'no', 'padding' => $zero, 'margin' => $zero ),
				'elements' => array(
					array(
						'id'       => substr( md5( 'ep-column-' . $i ), 0, 7 ),
						'elType'   => 'column',
						'isInner'  => false,
						'settings' => array( '_column_size' => 100, '_inline_size' => null, 'padding' => $zero ),
						'elements' => array(
							array(
								'id'         => substr( md5( 'ep-widget-' . $i ), 0, 7 ),
								'elType'     => 'widget',
								'widgetType' => $widget,
								'settings'   => new stdClass(),
								'elements'   => array(),
							),
						),
					),
				),
			);
		}
		return $content;
	}

	/** Crée la page « L’élevage » en brouillon. Retourne son identifiant ou un WP_Error. */
	public static function create_elevage_page() {
		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'draft',
				'post_title'   => __( 'L’élevage', 'ecurie-prestige' ),
				'post_content' => '[ecurie_elevage]',
			),
			true
		);
		if ( is_wp_error( $page_id ) ) {
			return $page_id;
		}
		if ( did_action( 'elementor/loaded' ) ) {
			update_post_meta( $page_id, '_elementor_data', wp_slash( wp_json_encode( self::elementor_template() ) ) );
			update_post_meta( $page_id, '_elementor_edit_mode', 'builder' );
			update_post_meta( $page_id, '_elementor_template_type', 'wp-page' );
			update_post_meta( $page_id, '_wp_page_template', 'elementor_header_footer' );
			if ( defined( 'ELEMENTOR_VERSION' ) ) {
				update_post_meta( $page_id, '_elementor_version', ELEMENTOR_VERSION );
			}
		}
		return $page_id;
	}
}

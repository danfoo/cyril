<?php
/**
 * Theme setup, assets, menus, sidebars, performance.
 *
 * @package GalsenFoot
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	static function () {
		load_theme_textdomain( 'galsenfoot', GF_DIR . '/languages' );
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'automatic-feed-links' );
		add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'align-wide' );
		add_theme_support( 'editor-styles' );
		add_theme_support( 'customize-selective-refresh-widgets' );
		add_theme_support(
			'custom-logo',
			array(
				'height'      => 96,
				'width'       => 320,
				'flex-width'  => true,
				'flex-height' => true,
			)
		);
		// Elementor (free and Pro theme builder locations).
		add_theme_support( 'elementor' );
		add_theme_support( 'header-footer-elementor' );

		add_image_size( 'gf-hero', 1280, 720, true );
		add_image_size( 'gf-card', 640, 360, true );
		add_image_size( 'gf-square', 240, 240, true );

		register_nav_menus(
			array(
				'primary' => __( 'Menu principal', 'galsenfoot' ),
				'footer'  => __( 'Menu du pied de page', 'galsenfoot' ),
				'legal'   => __( 'Liens légaux (bas de page)', 'galsenfoot' ),
			)
		);
	}
);

add_action(
	'after_setup_theme',
	static function () {
		$GLOBALS['content_width'] = 780;
	},
	0
);

/**
 * Elementor Pro theme builder locations (header, footer, single, archive).
 */
add_action(
	'elementor/theme/register_locations',
	static function ( $manager ) {
		$manager->register_all_core_location();
	}
);

/**
 * Whether an Elementor Pro template handles a location.
 *
 * @param string $location header|footer|single|archive.
 * @return bool
 */
function gf_elementor_location( $location ) {
	return function_exists( 'elementor_theme_do_location' ) && elementor_theme_do_location( $location );
}

/**
 * Whether the current singular content is built with Elementor.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function gf_is_elementor( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	if ( ! $post_id || ! class_exists( '\Elementor\Plugin' ) ) {
		return false;
	}
	$document = \Elementor\Plugin::$instance->documents->get( $post_id );
	return $document && $document->is_built_with_elementor();
}

add_action(
	'widgets_init',
	static function () {
		$common = array(
			'before_widget' => '<section id="%1$s" class="gf-widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="gf-heading gf-heading--sm"><span>',
			'after_title'   => '</span></h2>',
		);
		register_sidebar(
			array_merge(
				$common,
				array(
					'name'        => __( 'Colonne latérale', 'galsenfoot' ),
					'id'          => 'sidebar-1',
					'description' => __( 'Affichée sur les articles et les archives. Vide : le classement, les buteurs et une publicité sont affichés automatiquement.', 'galsenfoot' ),
				)
			)
		);
		register_sidebar(
			array_merge(
				$common,
				array(
					'name' => __( 'Pied de page', 'galsenfoot' ),
					'id'   => 'footer-1',
				)
			)
		);
	}
);

/**
 * Assets.
 */
add_action(
	'wp_enqueue_scripts',
	static function () {
		$css = GF_DIR . '/assets/css/main.css';
		$js  = GF_DIR . '/assets/js/main.js';
		wp_enqueue_style( 'galsenfoot', GF_URI . '/assets/css/main.css', array(), file_exists( $css ) ? GF_VERSION . '.' . filemtime( $css ) : GF_VERSION );
		wp_add_inline_style( 'galsenfoot', gf_dynamic_css() );
		wp_enqueue_script( 'galsenfoot', GF_URI . '/assets/js/main.js', array(), file_exists( $js ) ? GF_VERSION . '.' . filemtime( $js ) : GF_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		wp_localize_script(
			'galsenfoot',
			'GF',
			array(
				'copied' => __( 'Lien copié !', 'galsenfoot' ),
			)
		);
		if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
			wp_enqueue_script( 'comment-reply' );
		}
	},
	20
);

/**
 * Critical head tags: no-JS guard, colour scheme before paint, font preloads.
 */
add_action(
	'wp_head',
	static function () {
		$scheme = get_theme_mod( 'gf_scheme', 'dark' );
		?>
<script>(function(d){var e=d.documentElement;e.classList.add('js');try{var t=localStorage.getItem('gf-theme');if(t==='light'||t==='dark'){e.setAttribute('data-theme',t)}else if(<?php echo 'auto' === $scheme ? 'true' : 'false'; ?>){e.setAttribute('data-theme',matchMedia('(prefers-color-scheme: light)').matches?'light':'dark')}}catch(_){}})(document);</script>
<link rel="preload" href="<?php echo esc_url( GF_URI . '/assets/fonts/barlow-condensed-latin-800-italic.woff2' ); ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?php echo esc_url( GF_URI . '/assets/fonts/barlow-latin-400-normal.woff2' ); ?>" as="font" type="font/woff2" crossorigin>
<meta name="theme-color" content="<?php echo esc_attr( 'light' === $scheme ? '#ffffff' : '#0a0e0c' ); ?>">
		<?php
	},
	1
);

add_filter(
	'language_attributes',
	static function ( $output ) {
		$scheme = get_theme_mod( 'gf_scheme', 'dark' );
		return $output . ' data-theme="' . esc_attr( 'light' === $scheme ? 'light' : 'dark' ) . '"';
	}
);

add_filter(
	'body_class',
	static function ( $classes ) {
		if ( is_singular() && gf_is_elementor( get_queried_object_id() ) ) {
			$classes[] = 'gf-elementor-page';
		}
		if ( ! function_exists( 'gsf_render_standings' ) ) {
			$classes[] = 'gf-no-league';
		}
		return $classes;
	}
);

/**
 * Performance: drop emoji scripts and other head clutter.
 */
add_action(
	'init',
	static function () {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_action( 'wp_head', 'wp_generator' );
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
		remove_action( 'wp_head', 'wp_shortlink_wp_head' );
	}
);
add_filter( 'emoji_svg_url', '__return_false' );

/**
 * Excerpts.
 */
add_filter(
	'excerpt_length',
	static function () {
		return 24;
	},
	20
);
add_filter(
	'excerpt_more',
	static function () {
		return '…';
	}
);

/**
 * Archive titles without the "Catégorie :" prefix.
 */
add_filter( 'get_the_archive_title_prefix', '__return_empty_string' );

/**
 * Wrap embeds for responsive ratio and lazy-load iframes.
 */
add_filter(
	'embed_oembed_html',
	static function ( $html ) {
		$html = str_replace( '<iframe ', '<iframe loading="lazy" ', $html );
		return '<div class="gf-embed">' . $html . '</div>';
	}
);

/**
 * Search only posts and league content, never pages of the builder.
 */
add_action(
	'pre_get_posts',
	static function ( $query ) {
		if ( ! is_admin() && $query->is_main_query() && $query->is_search() && ! $query->get( 'post_type' ) ) {
			$types = array( 'post' );
			foreach ( array( 'gsf_club', 'gsf_player' ) as $type ) {
				if ( post_type_exists( $type ) ) {
					$types[] = $type;
				}
			}
			$query->set( 'post_type', $types );
		}
	}
);

/**
 * Colours chosen in the Customizer, as CSS variables.
 *
 * @return string
 */
function gf_dynamic_css() {
	$primary = sanitize_hex_color( get_theme_mod( 'gf_primary', '#00a651' ) ) ?: '#00a651';
	$accent  = sanitize_hex_color( get_theme_mod( 'gf_accent', '#fdef42' ) ) ?: '#fdef42';
	$hot     = sanitize_hex_color( get_theme_mod( 'gf_hot', '#e31b23' ) ) ?: '#e31b23';
	return ":root{--gf-primary:{$primary};--gf-accent:{$accent};--gf-hot:{$hot}}";
}

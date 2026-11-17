<?php
/**
 * Built-in SEO: meta description, Open Graph / Twitter cards, robots rules and JSON-LD.
 * Automatically steps aside when a dedicated SEO plugin is active.
 *
 * @package GalsenFoot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether a dedicated SEO plugin manages the head tags.
 */
function gf_has_seo_plugin() {
	return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || defined( 'AIOSEO_VERSION' ) || class_exists( 'The_SEO_Framework\Load' );
}

/**
 * Description of the current view.
 *
 * @return string
 */
function gf_meta_description() {
	$text = '';
	if ( is_singular() ) {
		$post = get_queried_object();
		$text = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
		if ( '' === trim( $text ) && function_exists( 'gsf_match' ) ) {
			$type = get_post_type( $post );
			if ( 'gsf_match' === $type ) {
				$m    = gsf_match( $post->ID );
				/* translators: 1: match title 2: date */
				$text = sprintf( __( '%1$s : score, buteurs, compositions et temps forts du match du %2$s.', 'galsenfoot' ), get_the_title( $post ), gsf_date( $m['ts'] ) );
			} elseif ( 'gsf_player' === $type ) {
				/* translators: %s player */
				$text = sprintf( __( '%s : fiche, club, statistiques, buts et performances cette saison.', 'galsenfoot' ), get_the_title( $post ) );
			} elseif ( 'gsf_club' === $type ) {
				/* translators: %s club */
				$text = sprintf( __( '%s : effectif, calendrier, résultats, classement et statistiques du club.', 'galsenfoot' ), get_the_title( $post ) );
			}
		}
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$text = wp_strip_all_tags( term_description() );
		if ( '' === $text ) {
			/* translators: 1: term 2: site */
			$text = sprintf( __( '%1$s : toute l’actualité, les résultats et les analyses sur %2$s.', 'galsenfoot' ), single_term_title( '', false ), get_bloginfo( 'name' ) );
		}
	} elseif ( is_post_type_archive() ) {
		/* translators: 1: archive 2: site */
		$text = sprintf( __( '%1$s du football sénégalais sur %2$s.', 'galsenfoot' ), post_type_archive_title( '', false ), get_bloginfo( 'name' ) );
	} elseif ( is_front_page() || is_home() ) {
		$text = get_bloginfo( 'description' );
	}
	$text = trim( preg_replace( '/\s+/', ' ', (string) $text ) );
	if ( function_exists( 'mb_strlen' ) && mb_strlen( $text ) > 158 ) {
		$text = rtrim( mb_substr( $text, 0, 155 ) ) . '…';
	}
	return $text;
}

/**
 * Social image of the current view.
 *
 * @return array|null [url, width, height].
 */
function gf_meta_image() {
	if ( is_singular() && has_post_thumbnail() ) {
		$image = wp_get_attachment_image_src( get_post_thumbnail_id(), 'gf-hero' );
		if ( $image ) {
			return array( $image[0], (int) $image[1], (int) $image[2] );
		}
	}
	$default = (int) get_theme_mod( 'custom_logo' );
	$icon    = get_site_icon_url( 512 );
	if ( $icon ) {
		return array( $icon, 512, 512 );
	}
	if ( $default ) {
		$image = wp_get_attachment_image_src( $default, 'full' );
		if ( $image ) {
			return array( $image[0], (int) $image[1], (int) $image[2] );
		}
	}
	return null;
}

add_action(
	'wp_head',
	static function () {
		if ( gf_has_seo_plugin() ) {
			return;
		}
		$description = gf_meta_description();
		$title       = wp_get_document_title();
		$url         = is_singular() ? get_permalink() : ( is_front_page() ? home_url( '/' ) : '' );
		$image       = gf_meta_image();
		$is_article  = is_singular( 'post' );

		if ( $description ) {
			echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
		}
		echo '<meta property="og:locale" content="' . esc_attr( get_locale() ) . '">' . "\n";
		echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";
		echo '<meta property="og:type" content="' . ( $is_article ? 'article' : 'website' ) . '">' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
		if ( $description ) {
			echo '<meta property="og:description" content="' . esc_attr( $description ) . '">' . "\n";
		}
		if ( $url ) {
			echo '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n";
		}
		if ( $image ) {
			echo '<meta property="og:image" content="' . esc_url( $image[0] ) . '">' . "\n";
			echo '<meta property="og:image:width" content="' . (int) $image[1] . '">' . "\n";
			echo '<meta property="og:image:height" content="' . (int) $image[2] . '">' . "\n";
		}
		if ( $is_article ) {
			echo '<meta property="article:published_time" content="' . esc_attr( get_the_date( 'c' ) ) . '">' . "\n";
			echo '<meta property="article:modified_time" content="' . esc_attr( get_the_modified_date( 'c' ) ) . '">' . "\n";
			$category = gf_primary_category( get_queried_object_id() );
			if ( $category ) {
				echo '<meta property="article:section" content="' . esc_attr( $category->name ) . '">' . "\n";
			}
		}
		echo '<meta name="twitter:card" content="' . ( $image && $image[1] >= 600 ? 'summary_large_image' : 'summary' ) . '">' . "\n";
	},
	3
);

/**
 * Google Discover: allow large image previews. Keep thin result pages out of the index.
 */
add_filter(
	'wp_robots',
	static function ( $robots ) {
		if ( gf_has_seo_plugin() ) {
			return $robots;
		}
		if ( is_search() || is_404() || ( is_paged() && ( is_date() || is_author() ) ) ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		} else {
			$robots['max-image-preview'] = 'large';
			$robots['max-snippet']       = '-1';
			$robots['max-video-preview'] = '-1';
		}
		return $robots;
	}
);

/**
 * JSON-LD: Organization, WebSite, NewsArticle and BreadcrumbList.
 */
add_action(
	'wp_head',
	static function () {
		$graph = array();
		$home  = home_url( '/' );
		$logo  = gf_meta_image();

		if ( ! gf_has_seo_plugin() ) {
			$organization = array(
				'@type' => 'Organization',
				'@id'   => $home . '#organization',
				'name'  => get_bloginfo( 'name' ),
				'url'   => $home,
			);
			$logo_id      = (int) get_theme_mod( 'custom_logo' );
			$logo_src     = $logo_id ? wp_get_attachment_image_src( $logo_id, 'full' ) : null;
			if ( $logo_src ) {
				$organization['logo'] = array(
					'@type'  => 'ImageObject',
					'url'    => $logo_src[0],
					'width'  => (int) $logo_src[1],
					'height' => (int) $logo_src[2],
				);
			}
			$same_as = array();
			foreach ( array( 'facebook', 'x', 'instagram', 'youtube', 'tiktok', 'telegram' ) as $network ) {
				$profile = get_theme_mod( 'gf_social_' . $network );
				if ( $profile ) {
					$same_as[] = esc_url_raw( $profile );
				}
			}
			if ( $same_as ) {
				$organization['sameAs'] = $same_as;
			}

			if ( is_front_page() ) {
				$graph[] = $organization;
				$graph[] = array(
					'@type'           => 'WebSite',
					'@id'             => $home . '#website',
					'url'             => $home,
					'name'            => get_bloginfo( 'name' ),
					'description'     => get_bloginfo( 'description' ),
					'inLanguage'      => get_bloginfo( 'language' ),
					'publisher'       => array( '@id' => $home . '#organization' ),
					'potentialAction' => array(
						'@type'       => 'SearchAction',
						'target'      => array(
							'@type'       => 'EntryPoint',
							'urlTemplate' => $home . '?s={search_term_string}',
						),
						'query-input' => 'required name=search_term_string',
					),
				);
			}

			if ( is_singular( 'post' ) ) {
				$post    = get_queried_object();
				$article = array(
					'@type'            => 'NewsArticle',
					'@id'              => get_permalink( $post ) . '#article',
					'mainEntityOfPage' => get_permalink( $post ),
					'headline'         => wp_strip_all_tags( get_the_title( $post ) ),
					'description'      => gf_meta_description(),
					'datePublished'    => get_the_date( 'c', $post ),
					'dateModified'     => get_the_modified_date( 'c', $post ),
					'inLanguage'       => get_bloginfo( 'language' ),
					'author'           => array(
						'@type' => 'Person',
						'name'  => get_the_author_meta( 'display_name', $post->post_author ),
						'url'   => get_author_posts_url( $post->post_author ),
					),
					'publisher'        => $organization,
				);
				if ( $logo && has_post_thumbnail( $post ) ) {
					$article['image'] = array( $logo[0] );
				}
				$category = gf_primary_category( $post->ID );
				if ( $category ) {
					$article['articleSection'] = $category->name;
				}
				$graph[] = $article;
			}
		}

		// Breadcrumb markup matches the visible trail, so it is always output by the theme.
		if ( ! is_front_page() && ! gf_has_seo_plugin() ) {
			$items = gf_breadcrumb_items();
			$list  = array();
			foreach ( $items as $i => $item ) {
				$node = array(
					'@type'    => 'ListItem',
					'position' => $i + 1,
					'name'     => wp_strip_all_tags( $item[0] ),
				);
				if ( $item[1] ) {
					$node['item'] = $item[1];
				}
				$list[] = $node;
			}
			if ( count( $list ) > 1 ) {
				$graph[] = array(
					'@type'           => 'BreadcrumbList',
					'itemListElement' => $list,
				);
			}
		}

		if ( $graph ) {
			echo '<script type="application/ld+json">' . wp_json_encode(
				array(
					'@context' => 'https://schema.org',
					'@graph'   => $graph,
				),
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
		}
	},
	25
);

/**
 * Give content images sensible alt text when the editor left it empty.
 */
add_filter(
	'wp_get_attachment_image_attributes',
	static function ( $attr, $attachment ) {
		if ( empty( $attr['alt'] ) ) {
			$parent      = (int) $attachment->post_parent;
			$attr['alt'] = $parent ? wp_strip_all_tags( get_the_title( $parent ) ) : wp_strip_all_tags( get_the_title( $attachment ) );
		}
		return $attr;
	},
	10,
	2
);

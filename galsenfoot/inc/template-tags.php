<?php
/**
 * Template tags.
 *
 * @package GalsenFoot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Inline SVG icon.
 *
 * @param string $name Icon name.
 * @param int    $size Pixel size.
 * @return string
 */
function gf_icon( $name, $size = 20 ) {
	$paths = array(
		'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.500-3.500"/>',
		'menu'     => '<path d="M3 6h18M3 12h18M3 18h12"/>',
		'close'    => '<path d="M5 5l14 14M19 5 5 19"/>',
		'sun'      => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M4.900 4.900l1.400 1.400M17.700 17.700l1.400 1.400M4.900 19.100l1.400-1.400M17.700 6.300l1.400-1.400"/>',
		'moon'     => '<path d="M20 14.500A8 8 0 0 1 9.500 4 8 8 0 1 0 20 14.500z"/>',
		'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		'arrow'    => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		'up'       => '<path d="M12 19V5M6 11l6-6 6 6"/>',
		'link'     => '<path d="M10 14a4 4 0 0 0 5.700 0l3-3a4 4 0 0 0-5.700-5.700l-1 1"/><path d="M14 10a4 4 0 0 0-5.700 0l-3 3a4 4 0 0 0 5.700 5.700l1-1"/>',
		'ball'     => '<circle cx="12" cy="12" r="9"/><path d="m12 7.500 4.300 3.100-1.600 5H9.300l-1.600-5zM12 3v4.500M3.400 9.300l4.300 1.300M20.600 9.300l-4.300 1.300M6.700 19.300l2.600-3.700M17.300 19.300l-2.600-3.700"/>',
		'facebook' => '<path d="M14 9V7.200c0-.8.200-1.200 1.300-1.200H17V3h-2.600C11.300 3 10 4.700 10 7v2H8v3h2v9h4v-9h2.700l.3-3z" fill="currentColor" stroke="none"/>',
		'x'        => '<path d="M17.500 3h3l-6.700 7.700L21.700 21h-6.100l-4.800-6.300L5.300 21h-3l7.200-8.200L2 3h6.300l4.300 5.700zm-1.100 16.200h1.700L7.400 4.700H5.600z" fill="currentColor" stroke="none"/>',
		'whatsapp' => '<path d="M12 2.500a9.500 9.500 0 0 0-8.100 14.400L2.500 21.500l4.700-1.300A9.500 9.500 0 1 0 12 2.500zm5.500 13.400c-.2.600-1.300 1.200-1.800 1.300-.5.100-1.100.1-1.700-.1-.4-.1-.9-.3-1.600-.6-2.800-1.200-4.600-4-4.700-4.200-.1-.2-1.100-1.500-1.100-2.900s.7-2 1-2.300c.2-.3.500-.3.700-.3h.5c.2 0 .4 0 .6.500l.9 2.100c.1.200.1.400 0 .5l-.3.5-.4.4c-.1.100-.3.300-.1.600.2.300.7 1.200 1.500 1.900 1 .900 1.900 1.200 2.200 1.300.3.100.4.100.6-.1l.8-1c.2-.2.400-.2.600-.1l2 .9c.3.100.4.200.5.300.1.200.1.700-.2 1.300z" fill="currentColor" stroke="none"/>',
		'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.500" cy="6.500" r="1" fill="currentColor" stroke="none"/>',
		'youtube'  => '<path d="M21.600 7.200a2.500 2.500 0 0 0-1.800-1.800C18.200 5 12 5 12 5s-6.200 0-7.800.4A2.500 2.500 0 0 0 2.400 7.200C2 8.800 2 12 2 12s0 3.200.4 4.800a2.500 2.500 0 0 0 1.800 1.800C5.800 19 12 19 12 19s6.200 0 7.800-.4a2.500 2.500 0 0 0 1.800-1.800c.4-1.600.4-4.800.4-4.800s0-3.200-.4-4.800zM10 15V9l5.200 3z" fill="currentColor" stroke="none"/>',
		'tiktok'   => '<path d="M16.500 3c.3 2.300 1.600 3.700 3.800 3.900v3a7 7 0 0 1-3.800-1.200v6.100c0 6.900-8.300 8.200-10.600 3.300-1.500-3.200.6-7.600 5.600-7.600v3.200c-.4.100-.8.200-1.200.300-1.200.400-1.800 1.100-1.600 2.400.4 2.500 4.600 3.200 4.300-1.600V3z" fill="currentColor" stroke="none"/>',
		'telegram' => '<path d="M21.500 4.300 18.400 19c-.2 1-.9 1.300-1.700.8l-4.700-3.500-2.300 2.200c-.3.300-.5.500-1 .5l.3-4.800 8.800-7.900c.4-.3-.1-.5-.6-.2L6.400 13 1.700 11.500c-1-.3-1-1 .2-1.500L20.200 3c.8-.3 1.600.2 1.300 1.300z" fill="currentColor" stroke="none"/>',
	);
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}
	return sprintf(
		'<svg class="gf-icon" width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%2$s</svg>',
		(int) $size,
		$paths[ $name ]
	);
}

/**
 * Site logo: custom logo or animated wordmark.
 *
 * @return string
 */
function gf_logo() {
	if ( has_custom_logo() ) {
		return get_custom_logo();
	}
	$name  = get_bloginfo( 'name' );
	$parts = preg_split( '/(?<=[a-zà-ÿ])(?=[A-Z])|\s+/u', $name, 2 );
	$html  = count( $parts ) > 1 ? esc_html( $parts[0] ) . '<b>' . esc_html( $parts[1] ) . '</b>' : esc_html( $name );
	return '<a class="gf-logo" href="' . esc_url( home_url( '/' ) ) . '" rel="home"><span class="gf-logo__ball">' . gf_icon( 'ball', 30 ) . '</span><span class="gf-logo__text">' . $html . '</span></a>';
}

/**
 * Default navigation when no menu is assigned.
 *
 * @param array $args wp_nav_menu args.
 */
function gf_menu_fallback( $args = array() ) {
	$items = array( array( home_url( '/' ), __( 'Accueil', 'galsenfoot' ), is_front_page() ) );
	if ( post_type_exists( 'gsf_match' ) ) {
		$items[] = array( get_post_type_archive_link( 'gsf_match' ), __( 'Matchs', 'galsenfoot' ), is_post_type_archive( 'gsf_match' ) || is_singular( 'gsf_match' ) );
		$items[] = array( get_post_type_archive_link( 'gsf_club' ), __( 'Clubs', 'galsenfoot' ), is_post_type_archive( 'gsf_club' ) || is_singular( 'gsf_club' ) );
		$items[] = array( get_post_type_archive_link( 'gsf_player' ), __( 'Joueurs', 'galsenfoot' ), is_post_type_archive( 'gsf_player' ) || is_singular( 'gsf_player' ) );
	}
	$categories = get_categories(
		array(
			'orderby' => 'count',
			'order'   => 'DESC',
			'number'  => 4,
			'exclude' => array( (int) get_option( 'default_category' ) ),
		)
	);
	foreach ( $categories as $category ) {
		$items[] = array( get_category_link( $category ), $category->name, is_category( $category->term_id ) );
	}
	echo '<ul class="' . esc_attr( $args['menu_class'] ?? 'gf-menu' ) . '">';
	foreach ( $items as $item ) {
		printf( '<li class="menu-item%s"><a href="%s"%s>%s</a></li>', $item[2] ? ' current-menu-item' : '', esc_url( $item[0] ), $item[2] ? ' aria-current="page"' : '', esc_html( $item[1] ) );
	}
	echo '</ul>';
}

/**
 * Breadcrumb trail as an array of [label, url].
 *
 * @return array[]
 */
function gf_breadcrumb_items() {
	$items = array( array( __( 'Accueil', 'galsenfoot' ), home_url( '/' ) ) );
	if ( is_singular() ) {
		$post = get_queried_object();
		$type = get_post_type( $post );
		if ( 'post' === $type ) {
			$categories = get_the_category( $post->ID );
			if ( $categories ) {
				$category = $categories[0];
				foreach ( array_reverse( get_ancestors( $category->term_id, 'category' ) ) as $ancestor ) {
					$items[] = array( get_cat_name( $ancestor ), get_category_link( $ancestor ) );
				}
				$items[] = array( $category->name, get_category_link( $category ) );
			}
		} elseif ( 'page' !== $type ) {
			$object = get_post_type_object( $type );
			if ( $object && $object->has_archive ) {
				$items[] = array( $object->labels->name, get_post_type_archive_link( $type ) );
			}
			if ( 'gsf_player' === $type ) {
				$club_id = (int) get_post_meta( $post->ID, '_gsf_club', true );
				if ( $club_id && 'publish' === get_post_status( $club_id ) ) {
					$items[] = array( get_the_title( $club_id ), get_permalink( $club_id ) );
				}
			}
		} else {
			foreach ( array_reverse( get_post_ancestors( $post ) ) as $ancestor ) {
				$items[] = array( get_the_title( $ancestor ), get_permalink( $ancestor ) );
			}
		}
		$items[] = array( get_the_title( $post ), '' );
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$term = get_queried_object();
		if ( is_taxonomy_hierarchical( $term->taxonomy ) ) {
			foreach ( array_reverse( get_ancestors( $term->term_id, $term->taxonomy ) ) as $ancestor ) {
				$parent  = get_term( $ancestor );
				$items[] = array( $parent->name, get_term_link( $parent ) );
			}
		}
		$items[] = array( $term->name, '' );
	} elseif ( is_post_type_archive() ) {
		$items[] = array( post_type_archive_title( '', false ), '' );
	} elseif ( is_search() ) {
		/* translators: %s search terms */
		$items[] = array( sprintf( __( 'Recherche : %s', 'galsenfoot' ), get_search_query() ), '' );
	} elseif ( is_author() ) {
		$items[] = array( get_the_author_meta( 'display_name', (int) get_query_var( 'author' ) ), '' );
	} elseif ( is_archive() ) {
		$items[] = array( wp_strip_all_tags( get_the_archive_title() ), '' );
	} elseif ( is_404() ) {
		$items[] = array( __( 'Page introuvable', 'galsenfoot' ), '' );
	} elseif ( is_home() && ! is_front_page() ) {
		$items[] = array( get_the_title( (int) get_option( 'page_for_posts' ) ), '' );
	}
	return $items;
}

/**
 * Print the breadcrumb.
 */
function gf_breadcrumb() {
	if ( is_front_page() ) {
		return;
	}
	$items = gf_breadcrumb_items();
	echo '<nav class="gf-breadcrumb" aria-label="' . esc_attr__( 'Fil d’Ariane', 'galsenfoot' ) . '"><ol>';
	foreach ( $items as $i => $item ) {
		$last = count( $items ) - 1 === $i;
		if ( $item[1] && ! $last ) {
			printf( '<li><a href="%s">%s</a></li>', esc_url( $item[1] ), esc_html( $item[0] ) );
		} else {
			printf( '<li aria-current="page">%s</li>', esc_html( $item[0] ) );
		}
	}
	echo '</ol></nav>';
}

/**
 * Estimated reading time in minutes.
 *
 * @param int $post_id Post ID.
 * @return int
 */
function gf_reading_time( $post_id = 0 ) {
	$words = str_word_count( wp_strip_all_tags( get_post_field( 'post_content', $post_id ? $post_id : get_the_ID() ) ) );
	return max( 1, (int) ceil( $words / 220 ) );
}

/**
 * Relative date for fresh posts, absolute date otherwise.
 *
 * @param int $post_id Post ID.
 * @return string HTML <time>.
 */
function gf_time( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	$ts      = (int) get_post_timestamp( $post_id );
	$diff    = time() - $ts;
	/* translators: %s human time difference */
	$label = ( $diff >= 0 && $diff < 2 * DAY_IN_SECONDS ) ? sprintf( __( 'Il y a %s', 'galsenfoot' ), human_time_diff( $ts ) ) : get_the_date( '', $post_id );
	return '<time datetime="' . esc_attr( get_the_date( 'c', $post_id ) ) . '">' . esc_html( $label ) . '</time>';
}

/**
 * Primary category of a post.
 *
 * @param int $post_id Post ID.
 * @return WP_Term|null
 */
function gf_primary_category( $post_id = 0 ) {
	$post_id    = $post_id ? $post_id : get_the_ID();
	$categories = get_the_category( $post_id );
	if ( ! $categories ) {
		return null;
	}
	$default = (int) get_option( 'default_category' );
	foreach ( $categories as $category ) {
		if ( (int) $category->term_id !== $default ) {
			return $category;
		}
	}
	return $categories[0];
}

/**
 * Featured image, or a generated pitch-style placeholder so cards never look broken.
 *
 * @param int    $post_id Post ID.
 * @param string $size    Image size.
 * @param array  $attr    Image attributes.
 * @return string
 */
function gf_thumb( $post_id, $size = 'gf-card', $attr = array() ) {
	if ( has_post_thumbnail( $post_id ) ) {
		return get_the_post_thumbnail( $post_id, $size, array_merge( array( 'loading' => 'lazy', 'decoding' => 'async' ), $attr ) );
	}
	$category = gf_primary_category( $post_id );
	$hue      = $category ? ( $category->term_id * 47 ) % 360 : 150;
	return '<span class="gf-thumb-fallback" style="--h:' . (int) $hue . '" aria-hidden="true">' . gf_icon( 'ball', 56 ) . '</span>';
}

/**
 * Article card.
 *
 * @param int    $post_id Post ID.
 * @param string $variant overlay|default|row|mini.
 * @param array  $opts    {heading: h2|h3, eager: bool, size: image size, excerpt: bool}.
 * @return string
 */
function gf_card( $post_id, $variant = 'default', $opts = array() ) {
	$opts     = wp_parse_args(
		$opts,
		array(
			'heading' => 'h3',
			'eager'   => false,
			'size'    => 'overlay' === $variant ? 'gf-hero' : ( 'mini' === $variant || 'row' === $variant ? 'gf-square' : 'gf-card' ),
			'excerpt' => 'default' === $variant,
			'class'   => '',
		)
	);
	$heading  = in_array( $opts['heading'], array( 'h2', 'h3', 'h4' ), true ) ? $opts['heading'] : 'h3';
	$category = gf_primary_category( $post_id );
	$url      = get_permalink( $post_id );
	$attr     = $opts['eager'] ? array( 'loading' => 'eager', 'fetchpriority' => 'high' ) : array();
	$html     = '<article class="gf-card gf-card--' . esc_attr( $variant ) . ' ' . esc_attr( $opts['class'] ) . '">';
	$html    .= '<a class="gf-card__media" href="' . esc_url( $url ) . '" tabindex="-1" aria-hidden="true">' . gf_thumb( $post_id, $opts['size'], $attr ) . '</a>';
	$html    .= '<div class="gf-card__body">';
	if ( $category && 'mini' !== $variant ) {
		$html .= '<a class="gf-tag" href="' . esc_url( get_category_link( $category ) ) . '">' . esc_html( $category->name ) . '</a>';
	}
	$html .= '<' . $heading . ' class="gf-card__title"><a href="' . esc_url( $url ) . '">' . esc_html( get_the_title( $post_id ) ) . '</a></' . $heading . '>';
	if ( $opts['excerpt'] ) {
		$html .= '<p class="gf-card__excerpt">' . esc_html( get_the_excerpt( $post_id ) ) . '</p>';
	}
	$html .= '<p class="gf-card__meta">' . gf_time( $post_id );
	if ( 'mini' !== $variant ) {
		/* translators: %d minutes */
		$html .= '<span>' . gf_icon( 'clock', 13 ) . ' ' . esc_html( sprintf( __( '%d min', 'galsenfoot' ), gf_reading_time( $post_id ) ) ) . '</span>';
	}
	$html .= '</p></div></article>';
	return $html;
}

/**
 * Section heading with optional "see all" link.
 *
 * @param string $title Title.
 * @param string $url   Optional link.
 * @param string $label Link label.
 * @param string $tag   Heading tag.
 * @return string
 */
function gf_heading( $title, $url = '', $label = '', $tag = 'h2' ) {
	$link = $url ? '<a class="gf-heading__link" href="' . esc_url( $url ) . '">' . esc_html( $label ? $label : __( 'Tout voir', 'galsenfoot' ) ) . ' ' . gf_icon( 'arrow', 16 ) . '</a>' : '';
	return '<div class="gf-heading-row"><' . $tag . ' class="gf-heading"><span>' . esc_html( $title ) . '</span></' . $tag . '>' . $link . '</div>';
}

/**
 * Hero mosaic: one lead story and four secondary stories.
 *
 * @param int[] $post_ids Post IDs (up to 5).
 * @return string
 */
function gf_hero_mosaic( $post_ids ) {
	if ( ! $post_ids ) {
		return '';
	}
	$html = '<div class="gf-mosaic gf-mosaic--' . count( $post_ids ) . '">';
	foreach ( array_values( $post_ids ) as $i => $post_id ) {
		$html .= gf_card(
			$post_id,
			'overlay',
			array(
				'heading' => 0 === $i ? 'h2' : 'h3',
				'eager'   => 0 === $i,
				'size'    => 0 === $i ? 'gf-hero' : 'gf-card',
				'class'   => 0 === $i ? 'is-lead' : '',
			)
		);
	}
	return $html . '</div>';
}

/**
 * Latest post IDs.
 *
 * @param array $args {count, category, offset, exclude}.
 * @return int[]
 */
function gf_post_ids( $args = array() ) {
	$args  = wp_parse_args(
		$args,
		array(
			'count'    => 6,
			'category' => 0,
			'offset'   => 0,
			'exclude'  => array(),
		)
	);
	$query = new WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => max( 1, (int) $args['count'] ),
			'offset'              => max( 0, (int) $args['offset'] ),
			'cat'                 => (int) $args['category'],
			'post__not_in'        => array_map( 'intval', (array) $args['exclude'] ),
			'ignore_sticky_posts' => true,
			'fields'              => 'ids',
			'no_found_rows'       => true,
		)
	);
	return array_map( 'intval', $query->posts );
}

/**
 * Share links.
 */
function gf_share() {
	$url   = rawurlencode( get_permalink() );
	$title = rawurlencode( html_entity_decode( get_the_title(), ENT_QUOTES, 'UTF-8' ) );
	$links = array(
		'whatsapp' => array( 'https://wa.me/?text=' . $title . '%20' . $url, 'WhatsApp' ),
		'facebook' => array( 'https://www.facebook.com/sharer/sharer.php?u=' . $url, 'Facebook' ),
		'x'        => array( 'https://twitter.com/intent/tweet?url=' . $url . '&text=' . $title, 'X' ),
		'telegram' => array( 'https://t.me/share/url?url=' . $url . '&text=' . $title, 'Telegram' ),
	);
	echo '<div class="gf-share"><span class="gf-share__label">' . esc_html__( 'Partager', 'galsenfoot' ) . '</span>';
	foreach ( $links as $key => $link ) {
		/* translators: %s network */
		printf( '<a class="gf-share__btn is-%s" href="%s" target="_blank" rel="noopener nofollow" aria-label="%s">%s</a>', esc_attr( $key ), esc_url( $link[0] ), esc_attr( sprintf( __( 'Partager sur %s', 'galsenfoot' ), $link[1] ) ), gf_icon( $key, 18 ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '<button type="button" class="gf-share__btn is-copy" data-gf-copy="' . esc_url( get_permalink() ) . '" aria-label="' . esc_attr__( 'Copier le lien', 'galsenfoot' ) . '">' . gf_icon( 'link', 18 ) . '</button></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
}

/**
 * Social profile links from the Customizer.
 *
 * @return string
 */
function gf_social_links() {
	$html = '';
	foreach ( array( 'facebook', 'x', 'instagram', 'youtube', 'tiktok', 'whatsapp', 'telegram' ) as $network ) {
		$url = get_theme_mod( 'gf_social_' . $network );
		if ( $url ) {
			$html .= '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener me" aria-label="' . esc_attr( ucfirst( $network ) ) . '">' . gf_icon( $network, 18 ) . '</a>';
		}
	}
	return $html ? '<div class="gf-social">' . $html . '</div>' : '';
}

/**
 * Pagination.
 */
function gf_pagination() {
	the_posts_pagination(
		array(
			'mid_size'  => 1,
			'prev_text' => '<span aria-hidden="true">←</span><span class="screen-reader-text">' . __( 'Page précédente', 'galsenfoot' ) . '</span>',
			'next_text' => '<span class="screen-reader-text">' . __( 'Page suivante', 'galsenfoot' ) . '</span><span aria-hidden="true">→</span>',
			'class'     => 'gf-pagination',
		)
	);
}

/**
 * League block wrapper: renders a plugin component when GalsenFoot League is active.
 *
 * @param string $callback Render function.
 * @param array  $args     Arguments.
 * @return string
 */
function gf_league( $callback, $args = array() ) {
	if ( ! function_exists( $callback ) ) {
		return '';
	}
	$html = call_user_func( $callback, $args );
	return $html ? '<div class="gsf">' . $html . '</div>' : '';
}

/**
 * Default sidebar content when no widget is set.
 */
function gf_default_sidebar() {
	$standings_url = function_exists( 'gf_page_url' ) ? gf_page_url( 'standings' ) : '';
	echo '<section class="gf-widget">' . gf_heading( __( 'Classement', 'galsenfoot' ), $standings_url, __( 'Complet', 'galsenfoot' ) ) . gf_league( 'gsf_render_standings', array( 'compact' => 1, 'limit' => 8 ) ) . '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo gf_ad( 'sidebar' ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<section class="gf-widget">' . gf_heading( __( 'Buteurs', 'galsenfoot' ) ) . gf_league( 'gsf_render_leaders', array( 'limit' => 5 ) ) . '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput
	$ids = gf_post_ids( array( 'count' => 5, 'exclude' => is_singular() ? array( get_queried_object_id() ) : array() ) );
	if ( $ids ) {
		echo '<section class="gf-widget">' . gf_heading( __( 'Dernières infos', 'galsenfoot' ) ) . '<div class="gf-stack">'; // phpcs:ignore WordPress.Security.EscapeOutput
		foreach ( $ids as $id ) {
			echo gf_card( $id, 'mini', array( 'heading' => 'h3' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</div></section>';
	}
}

/**
 * URL of a key page (standings…) chosen in the Customizer, or a sensible default.
 *
 * @param string $key standings|matches.
 * @return string
 */
function gf_page_url( $key ) {
	$page = (int) get_theme_mod( 'gf_page_' . $key );
	if ( $page && 'publish' === get_post_status( $page ) ) {
		return get_permalink( $page );
	}
	if ( 'matches' === $key && post_type_exists( 'gsf_match' ) ) {
		return (string) get_post_type_archive_link( 'gsf_match' );
	}
	return '';
}

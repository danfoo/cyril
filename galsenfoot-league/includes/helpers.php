<?php
/**
 * Shared helpers.
 *
 * @package GalsenFootLeague
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin settings with defaults.
 *
 * @param string|null $key Optional single key.
 * @return mixed
 */
function gsf_settings( $key = null ) {
	$defaults = array(
		'competition'   => 0,
		'season'        => 0,
		'points_win'    => 3,
		'points_draw'   => 1,
		'zone_top'      => 1,
		'zone_top2'     => 1,
		'zone_bottom'   => 2,
		'api_key'       => '',
		'api_league'    => '',
		'api_season'    => '',
		'api_frequency' => 'off',
		'api_images'    => 1,
		'api_events'    => 1,
		'nt_country'    => 'Senegal',
		'nt_team'       => 0,
		'nt_name'       => '',
		'nt_logo'       => '',
		'nt_league'     => 0,
		'nt_league_name' => '',
		'nt_season'     => 0,
		'nt_extra'      => 1,
		'nt_auto'       => 1,
	);
	$settings = wp_parse_args( (array) get_option( 'gsf_settings', array() ), $defaults );
	if ( null === $key ) {
		return $settings;
	}
	return $settings[ $key ] ?? null;
}

/**
 * Player positions.
 */
function gsf_positions() {
	return array(
		'GK' => __( 'Gardien', 'galsenfoot-league' ),
		'DF' => __( 'Défenseur', 'galsenfoot-league' ),
		'MF' => __( 'Milieu', 'galsenfoot-league' ),
		'FW' => __( 'Attaquant', 'galsenfoot-league' ),
	);
}

/**
 * Match statuses.
 */
function gsf_statuses() {
	return array(
		'scheduled' => __( 'À venir', 'galsenfoot-league' ),
		'live'      => __( 'En direct', 'galsenfoot-league' ),
		'ft'        => __( 'Terminé', 'galsenfoot-league' ),
		'postponed' => __( 'Reporté', 'galsenfoot-league' ),
		'cancelled' => __( 'Annulé', 'galsenfoot-league' ),
	);
}

/**
 * Match event types.
 */
function gsf_event_types() {
	return array(
		'goal'     => __( 'But', 'galsenfoot-league' ),
		'penalty'  => __( 'But sur penalty', 'galsenfoot-league' ),
		'own_goal' => __( 'But contre son camp', 'galsenfoot-league' ),
		'yellow'   => __( 'Carton jaune', 'galsenfoot-league' ),
		'red'      => __( 'Carton rouge', 'galsenfoot-league' ),
	);
}

/**
 * Resolve a competition / season argument (ID, slug or empty) to a term ID.
 *
 * @param mixed  $value    ID, slug or empty for the default.
 * @param string $taxonomy gsf_competition|gsf_season.
 * @return int
 */
function gsf_resolve_term( $value, $taxonomy ) {
	if ( is_numeric( $value ) && (int) $value > 0 ) {
		return (int) $value;
	}
	if ( is_string( $value ) && '' !== $value && '0' !== $value ) {
		$term = get_term_by( 'slug', sanitize_title( $value ), $taxonomy );
		if ( $term ) {
			return (int) $term->term_id;
		}
	}
	$default = (int) gsf_settings( 'gsf_season' === $taxonomy ? 'season' : 'competition' );
	if ( $default && term_exists( $default, $taxonomy ) ) {
		return $default;
	}
	// Fall back to the most recently created term so the plugin works without configuration.
	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'orderby'    => 'term_id',
			'order'      => 'DESC',
			'number'     => 1,
			'fields'     => 'ids',
		)
	);
	return ( ! is_wp_error( $terms ) && $terms ) ? (int) $terms[0] : 0;
}

/**
 * Club display data.
 *
 * @param int $club_id Club post ID.
 * @return array
 */
function gsf_club( $club_id ) {
	static $cache = array();
	$club_id = (int) $club_id;
	if ( isset( $cache[ $club_id ] ) ) {
		return $cache[ $club_id ];
	}
	$post = $club_id ? get_post( $club_id ) : null;
	if ( ! $post || 'gsf_club' !== $post->post_type ) {
		return array(
			'id'    => 0,
			'name'  => __( 'À déterminer', 'galsenfoot-league' ),
			'short' => 'TBD',
			'url'   => '',
			'color' => '#5b6b63',
			'color2' => '#1c2622',
			'national' => false,
		);
	}
	$name  = get_the_title( $post );
	$short = get_post_meta( $club_id, '_gsf_short', true );
	if ( ! $short ) {
		$short = gsf_abbr( $name );
	}
	$cache[ $club_id ] = array(
		'id'     => $club_id,
		'name'   => $name,
		'short'  => $short,
		'url'    => get_permalink( $post ),
		'color'  => sanitize_hex_color( get_post_meta( $club_id, '_gsf_color', true ) ) ?: '#00853f',
		'color2' => sanitize_hex_color( get_post_meta( $club_id, '_gsf_color2', true ) ) ?: '#ffffff',
		'national' => (bool) get_post_meta( $club_id, '_gsf_national', true ),
	);
	return $cache[ $club_id ];
}

/**
 * Three-letter abbreviation from a club name.
 *
 * @param string $name Name.
 * @return string
 */
function gsf_abbr( $name ) {
	$clean = trim( preg_replace( '/\b(AS|ASC|US|FC|SC|CF|AC|de|du|des|la|le)\b/iu', '', $name ) );
	if ( '' === $clean ) {
		$clean = $name;
	}
	$clean = remove_accents( $clean );
	$words = preg_split( '/[\s\-]+/', $clean, -1, PREG_SPLIT_NO_EMPTY );
	if ( count( $words ) >= 3 ) {
		$abbr = $words[0][0] . $words[1][0] . $words[2][0];
	} elseif ( 2 === count( $words ) ) {
		$abbr = substr( $words[0], 0, 2 ) . $words[1][0];
	} else {
		$abbr = substr( $clean, 0, 3 );
	}
	return strtoupper( $abbr );
}

/**
 * Club crest: the featured image if present, otherwise a generated monogram shield.
 *
 * @param int $club_id Club ID.
 * @param int $size    Pixel size.
 * @return string HTML.
 */
function gsf_crest( $club_id, $size = 40 ) {
	$club = gsf_club( $club_id );
	$size = max( 16, (int) $size );
	if ( $club['id'] && has_post_thumbnail( $club['id'] ) ) {
		$img = get_the_post_thumbnail(
			$club['id'],
			$size > 96 ? 'medium' : 'thumbnail',
			array(
				'class'   => 'gsf-crest gsf-crest--img',
				'alt'     => sprintf( /* translators: %s club name */ __( 'Logo %s', 'galsenfoot-league' ), $club['name'] ),
				'loading' => 'lazy',
				'style'   => sprintf( 'width:%1$dpx;height:%1$dpx', $size ),
			)
		);
		if ( $img ) {
			return $img;
		}
	}
	$logo_url = $club['id'] ? get_post_meta( $club['id'], '_gsf_logo_url', true ) : '';
	if ( $logo_url ) {
		return sprintf(
			'<img class="gsf-crest gsf-crest--img" src="%1$s" alt="%2$s" width="%3$d" height="%3$d" loading="lazy" decoding="async">',
			esc_url( $logo_url ),
			esc_attr( sprintf( /* translators: %s club name */ __( 'Logo %s', 'galsenfoot-league' ), $club['name'] ) ),
			$size
		);
	}
	$letters = function_exists( 'mb_substr' ) ? mb_substr( $club['short'], 0, 3 ) : substr( $club['short'], 0, 3 );
	$uid     = 'gsfc' . $club['id'] . 's' . $size . wp_rand( 100, 999 );
	return sprintf(
		'<svg class="gsf-crest gsf-crest--auto" width="%1$d" height="%1$d" viewBox="0 0 48 48" role="img" aria-label="%2$s"><defs><linearGradient id="%6$s" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="%3$s"/><stop offset="1" stop-color="%3$s" stop-opacity=".72"/></linearGradient></defs><path d="M24 2 43 8v15c0 11.500-7.800 19.600-19 23C12.800 42.600 5 34.500 5 23V8z" fill="url(#%6$s)" stroke="%4$s" stroke-width="2.500"/><path d="M5 17 43 8v6L5 23z" fill="%4$s" opacity=".22"/><text x="24" y="29" text-anchor="middle" font-family="\'Barlow Condensed\',Impact,sans-serif" font-size="14" font-weight="800" fill="%4$s" letter-spacing=".5">%5$s</text></svg>',
		$size,
		esc_attr( $club['name'] ),
		esc_attr( $club['color'] ),
		esc_attr( $club['color2'] ),
		esc_html( $letters ),
		esc_attr( $uid )
	);
}

/**
 * Match data in a normalised array.
 *
 * @param int $match_id Match post ID.
 * @return array
 */
function gsf_match( $match_id ) {
	$match_id = (int) $match_id;
	$status   = get_post_meta( $match_id, '_gsf_status', true );
	$statuses = gsf_statuses();
	if ( ! isset( $statuses[ $status ] ) ) {
		$status = 'scheduled';
	}
	$hs     = get_post_meta( $match_id, '_gsf_home_score', true );
	$as     = get_post_meta( $match_id, '_gsf_away_score', true );
	$events = get_post_meta( $match_id, '_gsf_events', true );
	return array(
		'id'         => $match_id,
		'url'        => get_permalink( $match_id ),
		'home'       => (int) get_post_meta( $match_id, '_gsf_home', true ),
		'away'       => (int) get_post_meta( $match_id, '_gsf_away', true ),
		'ts'         => (int) get_post_meta( $match_id, '_gsf_ts', true ),
		'venue'      => (string) get_post_meta( $match_id, '_gsf_venue', true ),
		'matchday'   => (int) get_post_meta( $match_id, '_gsf_matchday', true ),
		'status'     => $status,
		'minute'     => (int) get_post_meta( $match_id, '_gsf_minute', true ),
		'home_score' => '' === $hs ? null : (int) $hs,
		'away_score' => '' === $as ? null : (int) $as,
		'events'     => is_array( $events ) ? $events : array(),
		'has_score'  => in_array( $status, array( 'live', 'ft' ), true ) && '' !== $hs && '' !== $as,
	);
}

/**
 * Format a match timestamp in the site timezone.
 *
 * @param int    $ts     UTC timestamp.
 * @param string $format Date format.
 * @return string
 */
function gsf_date( $ts, $format = '' ) {
	if ( ! $ts ) {
		return '';
	}
	if ( '' === $format ) {
		$format = get_option( 'date_format' );
	}
	return wp_date( $format, (int) $ts );
}

/**
 * Query matches.
 *
 * @param array $args {competition, season, club, status(array|string), order, limit, matchday, from, to}.
 * @return int[] Match IDs.
 */
function gsf_query_matches( $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'competition' => 0,
			'season'      => 0,
			'club'        => 0,
			'status'      => '',
			'order'       => 'ASC',
			'limit'       => 10,
			'matchday'    => 0,
			'from'        => 0,
			'to'          => 0,
			'any_term'    => false,
		)
	);

	$meta_query = array( 'relation' => 'AND' );
	if ( $args['status'] ) {
		$meta_query[] = array(
			'key'     => '_gsf_status',
			'value'   => (array) $args['status'],
			'compare' => 'IN',
		);
	}
	if ( $args['club'] ) {
		$meta_query[] = array(
			'relation' => 'OR',
			array(
				'key'   => '_gsf_home',
				'value' => (int) $args['club'],
			),
			array(
				'key'   => '_gsf_away',
				'value' => (int) $args['club'],
			),
		);
	}
	if ( $args['matchday'] ) {
		$meta_query[] = array(
			'key'   => '_gsf_matchday',
			'value' => (int) $args['matchday'],
		);
	}
	if ( $args['from'] ) {
		$meta_query[] = array(
			'key'     => '_gsf_ts',
			'value'   => (int) $args['from'],
			'compare' => '>=',
			'type'    => 'NUMERIC',
		);
	}
	if ( $args['to'] ) {
		$meta_query[] = array(
			'key'     => '_gsf_ts',
			'value'   => (int) $args['to'],
			'compare' => '<=',
			'type'    => 'NUMERIC',
		);
	}

	$tax_query = array();
	if ( ! $args['any_term'] ) {
		if ( $args['competition'] ) {
			$tax_query[] = array(
				'taxonomy' => 'gsf_competition',
				'terms'    => (int) $args['competition'],
			);
		}
		if ( $args['season'] ) {
			$tax_query[] = array(
				'taxonomy' => 'gsf_season',
				'terms'    => (int) $args['season'],
			);
		}
	}

	$query = new WP_Query(
		array(
			'post_type'              => 'gsf_match',
			'post_status'            => 'publish',
			'posts_per_page'         => (int) $args['limit'],
			'fields'                 => 'ids',
			'meta_key'               => '_gsf_ts', // phpcs:ignore WordPress.DB.SlowDBQuery
			'orderby'                => 'meta_value_num',
			'order'                  => 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC',
			'meta_query'             => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery
			'tax_query'              => $tax_query, // phpcs:ignore WordPress.DB.SlowDBQuery
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		)
	);
	return array_map( 'intval', $query->posts );
}

/**
 * Players of a club.
 *
 * @param int $club_id Club ID.
 * @return WP_Post[]
 */
function gsf_club_players( $club_id ) {
	$national = gsf_is_national( $club_id );
	$num_key  = $national ? '_gsf_nt_number' : '_gsf_number';
	$players  = get_posts(
		array(
			'post_type'      => 'gsf_player',
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'meta_key'       => $national ? '_gsf_national_team' : '_gsf_club', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => (int) $club_id, // phpcs:ignore WordPress.DB.SlowDBQuery
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);
	$order = array_flip( array_keys( gsf_positions() ) );
	usort(
		$players,
		static function ( $a, $b ) use ( $order, $num_key ) {
			$pa = $order[ get_post_meta( $a->ID, '_gsf_position', true ) ] ?? 9;
			$pb = $order[ get_post_meta( $b->ID, '_gsf_position', true ) ] ?? 9;
			if ( $pa !== $pb ) {
				return $pa <=> $pb;
			}
			return (int) get_post_meta( $a->ID, $num_key, true ) <=> (int) get_post_meta( $b->ID, $num_key, true );
		}
	);
	return $players;
}

/**
 * Whether a team is a national team.
 *
 * @param int $club_id Team post ID.
 * @return bool
 */
function gsf_is_national( $club_id ) {
	return $club_id && (bool) get_post_meta( (int) $club_id, '_gsf_national', true );
}

/**
 * Shirt number of a player, for his club or for his national team.
 *
 * @param int  $player_id Player ID.
 * @param bool $national  National team number.
 * @return string
 */
function gsf_player_number( $player_id, $national = false ) {
	$number = (string) get_post_meta( $player_id, $national ? '_gsf_nt_number' : '_gsf_number', true );
	return '' !== $number ? (string) (int) $number : '';
}

/**
 * Age from a Y-m-d birth date.
 *
 * @param string $birth Date.
 * @return int|null
 */
function gsf_age( $birth ) {
	if ( ! $birth ) {
		return null;
	}
	try {
		$date = new DateTimeImmutable( $birth, wp_timezone() );
	} catch ( Exception $e ) {
		return null;
	}
	return (int) $date->diff( new DateTimeImmutable( 'now', wp_timezone() ) )->y;
}

/**
 * Invalidate all computed statistics.
 */
function gsf_flush_cache() {
	update_option( 'gsf_cache_v', time() . wp_rand( 10, 99 ), true );
}

/**
 * Cache key including the current cache generation.
 *
 * @param string $key Key.
 * @return string
 */
function gsf_cache_key( $key ) {
	return 'gsf_' . md5( $key . '|' . get_option( 'gsf_cache_v', '1' ) );
}

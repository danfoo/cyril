<?php
/**
 * Synchronisation with API-Football (https://www.api-football.com — api-sports.io v3).
 *
 * Imports clubs, squads, fixtures / results, match events and season player statistics.
 * Every imported item keeps its remote ID in `_gsf_api_id`, so later runs update instead of duplicating,
 * and manual edits to fields the API does not provide are preserved.
 *
 * @package GalsenFootLeague
 */

defined( 'ABSPATH' ) || exit;

const GSF_API_BASE = 'https://v3.football.api-sports.io/';

/**
 * Call the API.
 *
 * @param string $endpoint Endpoint, e.g. "teams".
 * @param array  $params   Query parameters.
 * @return array|WP_Error Decoded body (with "response" and "paging").
 */
function gsf_api_get( $endpoint, $params = array() ) {
	$key = trim( (string) gsf_settings( 'api_key' ) );
	if ( '' === $key ) {
		return new WP_Error( 'gsf_no_key', __( 'Clé API manquante. Renseignez-la dans GalsenFoot → Réglages.', 'galsenfoot-league' ) );
	}
	$url      = add_query_arg( array_map( 'rawurlencode', array_map( 'strval', $params ) ), GSF_API_BASE . ltrim( $endpoint, '/' ) );
	$response = wp_remote_get(
		$url,
		array(
			'timeout' => 25,
			'headers' => array(
				'x-apisports-key' => $key,
				'Accept'          => 'application/json',
			),
		)
	);
	if ( is_wp_error( $response ) ) {
		return $response;
	}
	$code = (int) wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( 200 !== $code || ! is_array( $body ) ) {
		return new WP_Error( 'gsf_api_http', sprintf( /* translators: %d HTTP status */ __( 'Réponse inattendue de l’API (HTTP %d).', 'galsenfoot-league' ), $code ) );
	}
	if ( ! empty( $body['errors'] ) ) {
		$errors = is_array( $body['errors'] ) ? implode( ' ', array_map( 'strval', $body['errors'] ) ) : (string) $body['errors'];
		return new WP_Error( 'gsf_api_error', sprintf( /* translators: %s message */ __( 'Erreur API : %s', 'galsenfoot-league' ), $errors ) );
	}
	$remaining = wp_remote_retrieve_header( $response, 'x-ratelimit-requests-remaining' );
	if ( '' !== $remaining && null !== $remaining ) {
		update_option( 'gsf_api_remaining', (int) $remaining, false );
	}
	return $body;
}

/**
 * Whether API synchronisation is configured.
 */
function gsf_api_ready() {
	return '' !== trim( (string) gsf_settings( 'api_key' ) ) && gsf_settings( 'api_league' ) && gsf_settings( 'api_season' );
}

/**
 * Find a post by its remote ID.
 *
 * @param string $post_type Post type.
 * @param mixed  $api_id    Remote ID.
 * @return int Post ID or 0.
 */
function gsf_find_by_api_id( $post_type, $api_id ) {
	if ( ! $api_id ) {
		return 0;
	}
	$ids = get_posts(
		array(
			'post_type'      => $post_type,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_gsf_api_id', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => (string) $api_id, // phpcs:ignore WordPress.DB.SlowDBQuery
			'no_found_rows'  => true,
		)
	);
	return $ids ? (int) $ids[0] : 0;
}

/**
 * Create or update a club / player / match from a data array.
 *
 * @param string $post_type gsf_club|gsf_player|gsf_match.
 * @param array  $data      {api_id, title, meta[], only_if_empty[] (meta kept when already set), image}.
 * @return int Post ID (0 on failure).
 */
function gsf_upsert( $post_type, $data ) {
	$post_id = gsf_find_by_api_id( $post_type, $data['api_id'] ?? 0 );
	if ( ! $post_id && ! empty( $data['title'] ) && 'gsf_match' !== $post_type && ( $data['match_title'] ?? true ) ) {
		// Attach to an item created by hand with the same name.
		$existing = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'any',
				'title'          => $data['title'],
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		$post_id  = $existing ? (int) $existing[0] : 0;
	}
	if ( ! $post_id ) {
		$post_id = wp_insert_post(
			array(
				'post_type'   => $post_type,
				'post_status' => 'publish',
				'post_title'  => $data['title'] ?? '',
			)
		);
		if ( ! $post_id || is_wp_error( $post_id ) ) {
			return 0;
		}
	}
	if ( ! empty( $data['api_id'] ) ) {
		update_post_meta( $post_id, '_gsf_api_id', (string) $data['api_id'] );
	}
	foreach ( (array) ( $data['meta'] ?? array() ) as $key => $value ) {
		if ( null === $value || '' === $value ) {
			continue;
		}
		update_post_meta( $post_id, $key, $value );
	}
	foreach ( (array) ( $data['only_if_empty'] ?? array() ) as $key => $value ) {
		if ( null !== $value && '' !== $value && '' === (string) get_post_meta( $post_id, $key, true ) ) {
			update_post_meta( $post_id, $key, $value );
		}
	}
	if ( ! empty( $data['image'] ) && ! has_post_thumbnail( $post_id ) ) {
		$meta_key = 'gsf_player' === $post_type ? '_gsf_photo_url' : '_gsf_logo_url';
		update_post_meta( $post_id, $meta_key, esc_url_raw( $data['image'] ) );
		if ( gsf_settings( 'api_images' ) && 'gsf_club' === $post_type ) {
			gsf_sideload_image( $post_id, $data['image'], $data['title'] ?? '' );
		}
	}
	return (int) $post_id;
}

/**
 * Download a remote image into the media library and set it as featured image.
 *
 * @param int    $post_id Post ID.
 * @param string $url     Image URL.
 * @param string $desc    Description / alt.
 */
function gsf_sideload_image( $post_id, $url, $desc ) {
	if ( ! function_exists( 'media_sideload_image' ) ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}
	$attachment_id = media_sideload_image( esc_url_raw( $url ), $post_id, $desc, 'id' );
	if ( ! is_wp_error( $attachment_id ) && $attachment_id ) {
		set_post_thumbnail( $post_id, (int) $attachment_id );
		update_post_meta( (int) $attachment_id, '_wp_attachment_image_alt', $desc );
	}
}

/**
 * Terms (competition, season) the API data is imported into. Created on first use.
 *
 * @return array{0:int,1:int}
 */
function gsf_api_terms() {
	$settings = gsf_settings();
	$comp     = (int) $settings['competition'];
	$season   = (int) $settings['season'];
	if ( ! $comp || ! term_exists( $comp, 'gsf_competition' ) ) {
		$name = get_option( 'gsf_api_league_name' ) ?: __( 'Championnat', 'galsenfoot-league' );
		$term = term_exists( $name, 'gsf_competition' ) ?: wp_insert_term( $name, 'gsf_competition' );
		$comp = is_wp_error( $term ) ? 0 : (int) $term['term_id'];
	}
	if ( ! $season || ! term_exists( $season, 'gsf_season' ) ) {
		$year   = (int) $settings['api_season'];
		$name   = $year . '-' . ( $year + 1 );
		$term   = term_exists( $name, 'gsf_season' ) ?: wp_insert_term( $name, 'gsf_season' );
		$season = is_wp_error( $term ) ? 0 : (int) $term['term_id'];
	}
	if ( $comp !== (int) $settings['competition'] || $season !== (int) $settings['season'] ) {
		$settings['competition'] = $comp;
		$settings['season']      = $season;
		update_option( 'gsf_settings', $settings );
	}
	return array( $comp, $season );
}

/**
 * Map an API-Football status code to a local status.
 *
 * @param string $short Status short code.
 * @return string
 */
function gsf_api_status( $short ) {
	$short = strtoupper( (string) $short );
	if ( in_array( $short, array( 'FT', 'AET', 'PEN', 'AWD', 'WO' ), true ) ) {
		return 'ft';
	}
	if ( in_array( $short, array( '1H', 'HT', '2H', 'ET', 'BT', 'P', 'LIVE', 'INT', 'SUSP' ), true ) ) {
		return 'live';
	}
	if ( 'PST' === $short ) {
		return 'postponed';
	}
	if ( in_array( $short, array( 'CANC', 'ABD' ), true ) ) {
		return 'cancelled';
	}
	return 'scheduled';
}

/**
 * Import the clubs of the configured league.
 *
 * @return int|WP_Error Number of clubs.
 */
function gsf_api_sync_teams() {
	$body = gsf_api_get(
		'teams',
		array(
			'league' => gsf_settings( 'api_league' ),
			'season' => gsf_settings( 'api_season' ),
		)
	);
	if ( is_wp_error( $body ) ) {
		return $body;
	}
	$count = 0;
	foreach ( (array) $body['response'] as $item ) {
		$team = $item['team'] ?? array();
		if ( empty( $team['id'] ) ) {
			continue;
		}
		$venue = $item['venue'] ?? array();
		$id    = gsf_upsert(
			'gsf_club',
			array(
				'api_id'        => $team['id'],
				'title'         => sanitize_text_field( $team['name'] ?? '' ),
				'image'         => $team['logo'] ?? '',
				'only_if_empty' => array(
					'_gsf_short'    => strtoupper( sanitize_text_field( $team['code'] ?? '' ) ),
					'_gsf_founded'  => absint( $team['founded'] ?? 0 ) ?: '',
					'_gsf_city'     => sanitize_text_field( $venue['city'] ?? '' ),
					'_gsf_stadium'  => sanitize_text_field( $venue['name'] ?? '' ),
					'_gsf_capacity' => absint( $venue['capacity'] ?? 0 ) ?: '',
				),
			)
		);
		$count += $id ? 1 : 0;
	}
	gsf_flush_cache();
	return $count;
}

/**
 * Create or update one match from an API-Football fixture item.
 *
 * @param array $item     Fixture item (fixture, league, teams, goals).
 * @param int   $comp     Competition term ID.
 * @param int   $season   Season term ID.
 * @param bool  $national Whether the teams are national teams.
 * @return int Match ID (0 on failure).
 */
function gsf_api_save_fixture( $item, $comp, $season, $national = false ) {
	$fixture = $item['fixture'] ?? array();
	if ( empty( $fixture['id'] ) ) {
		return 0;
	}
	$clubs = array();
	foreach ( array( 'home', 'away' ) as $side ) {
		$team           = $item['teams'][ $side ] ?? array();
		$clubs[ $side ] = gsf_find_by_api_id( 'gsf_club', $team['id'] ?? 0 );
		if ( ! $clubs[ $side ] && ! empty( $team['id'] ) ) {
			$clubs[ $side ] = gsf_upsert(
				'gsf_club',
				array(
					'api_id'      => $team['id'],
					'title'       => $national && function_exists( 'gsf_nt_team_name' ) ? gsf_nt_team_name( sanitize_text_field( $team['name'] ?? '' ) ) : sanitize_text_field( $team['name'] ?? '' ),
					'image'       => $team['logo'] ?? '',
					'match_title' => ! $national,
					'meta'        => $national ? array( '_gsf_national' => 1 ) : array(),
				)
			);
		}
	}
	$status   = gsf_api_status( $fixture['status']['short'] ?? '' );
	$matchday = 0;
	$round    = (string) ( $item['league']['round'] ?? '' );
	// "Regular Season - 12", "Group Stage - 3"… but not "Friendlies 1" or knockout rounds.
	if ( preg_match( '/(season|group|matchday)\D*(\d+)\s*$/i', $round, $found ) ) {
		$matchday = (int) $found[2];
	}
	$venue    = trim( ( $fixture['venue']['name'] ?? '' ) . ( ! empty( $fixture['venue']['city'] ) ? ', ' . $fixture['venue']['city'] : '' ) );
	$match_id = gsf_find_by_api_id( 'gsf_match', $fixture['id'] );
	if ( ! $match_id ) {
		$match_id = wp_insert_post(
			array(
				'post_type'   => 'gsf_match',
				'post_status' => 'publish',
				'post_title'  => 'match',
			)
		);
		if ( ! $match_id || is_wp_error( $match_id ) ) {
			return 0;
		}
		update_post_meta( $match_id, '_gsf_api_id', (string) $fixture['id'] );
	}
	$has_score = isset( $item['goals']['home'], $item['goals']['away'] );
	update_post_meta( $match_id, '_gsf_home', $clubs['home'] );
	update_post_meta( $match_id, '_gsf_away', $clubs['away'] );
	update_post_meta( $match_id, '_gsf_ts', absint( $fixture['timestamp'] ?? 0 ) );
	update_post_meta( $match_id, '_gsf_status', $status );
	update_post_meta( $match_id, '_gsf_minute', absint( $fixture['status']['elapsed'] ?? 0 ) );
	update_post_meta( $match_id, '_gsf_home_score', $has_score ? (int) $item['goals']['home'] : '' );
	update_post_meta( $match_id, '_gsf_away_score', $has_score ? (int) $item['goals']['away'] : '' );
	if ( $matchday ) {
		update_post_meta( $match_id, '_gsf_matchday', $matchday );
	}
	if ( $venue ) {
		update_post_meta( $match_id, '_gsf_venue', sanitize_text_field( $venue ) );
	}
	if ( ! empty( $item['league']['round'] ) ) {
		update_post_meta( $match_id, '_gsf_round', sanitize_text_field( $item['league']['round'] ) );
	}
	if ( $national ) {
		update_post_meta( $match_id, '_gsf_national_match', 1 );
	}
	if ( $comp ) {
		wp_set_object_terms( $match_id, array( (int) $comp ), 'gsf_competition' );
	}
	if ( $season ) {
		wp_set_object_terms( $match_id, array( (int) $season ), 'gsf_season' );
	}
	gsf_sync_match_title( $match_id );
	return (int) $match_id;
}

/**
 * Import fixtures and results (one request for the whole season).
 *
 * @return int|WP_Error Number of matches.
 */
function gsf_api_sync_fixtures() {
	$body = gsf_api_get(
		'fixtures',
		array(
			'league' => gsf_settings( 'api_league' ),
			'season' => gsf_settings( 'api_season' ),
		)
	);
	if ( is_wp_error( $body ) ) {
		return $body;
	}
	list( $comp, $season ) = gsf_api_terms();
	$count                 = 0;
	foreach ( (array) $body['response'] as $item ) {
		$count += gsf_api_save_fixture( $item, $comp, $season ) ? 1 : 0;
	}
	gsf_flush_cache();
	return $count;
}

/**
 * Convert API-Football events into local events (goals and cards only).
 *
 * @param array  $list     API events.
 * @param string $home_api Remote ID of the home team.
 * @return array[]
 */
function gsf_api_parse_events( $list, $home_api ) {
	$events = array();
	foreach ( (array) $list as $event ) {
		$kind   = strtolower( (string) ( $event['type'] ?? '' ) );
		$detail = strtolower( (string) ( $event['detail'] ?? '' ) );
		$type   = '';
		if ( 'goal' === $kind ) {
			if ( false !== strpos( $detail, 'missed' ) ) {
				continue;
			}
			$type = false !== strpos( $detail, 'own' ) ? 'own_goal' : ( false !== strpos( $detail, 'penalty' ) ? 'penalty' : 'goal' );
		} elseif ( 'card' === $kind ) {
			$type = false !== strpos( $detail, 'yellow' ) && false === strpos( $detail, 'second' ) ? 'yellow' : 'red';
		}
		if ( ! $type ) {
			continue;
		}
		$events[] = array(
			'minute'      => absint( $event['time']['elapsed'] ?? 0 ) + absint( $event['time']['extra'] ?? 0 ),
			'type'        => $type,
			'side'        => (string) ( $event['team']['id'] ?? '' ) === (string) $home_api ? 'home' : 'away',
			'player'      => gsf_find_by_api_id( 'gsf_player', $event['player']['id'] ?? 0 ),
			'player_name' => sanitize_text_field( $event['player']['name'] ?? '' ),
			'assist'      => gsf_find_by_api_id( 'gsf_player', $event['assist']['id'] ?? 0 ),
			'assist_name' => sanitize_text_field( $event['assist']['name'] ?? '' ),
		);
	}
	usort(
		$events,
		static function ( $a, $b ) {
			return $a['minute'] <=> $b['minute'];
		}
	);
	return $events;
}

/**
 * Import goals and cards of finished / live matches that have no events yet.
 * One request per match: limited per run to protect the daily quota.
 *
 * @param int $max Maximum number of matches per run.
 * @return int|WP_Error Number of matches updated.
 */
function gsf_api_sync_events( $max = 6 ) {
	$ids = get_posts(
		array(
			'post_type'      => 'gsf_match',
			'post_status'    => 'publish',
			'posts_per_page' => (int) $max,
			'fields'         => 'ids',
			'meta_key'       => '_gsf_ts', // phpcs:ignore WordPress.DB.SlowDBQuery
			'orderby'        => 'meta_value_num',
			'order'          => 'DESC',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array(
					'key'     => '_gsf_api_id',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => '_gsf_status',
					'value'   => array( 'ft', 'live' ),
					'compare' => 'IN',
				),
				array(
					'key'     => '_gsf_national_match',
					'compare' => 'NOT EXISTS',
				),
				array(
					'relation' => 'OR',
					array(
						'key'     => '_gsf_events_synced',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'   => '_gsf_status',
						'value' => 'live',
					),
				),
			),
		)
	);
	$count = 0;
	foreach ( $ids as $match_id ) {
		$body = gsf_api_get( 'fixtures/events', array( 'fixture' => get_post_meta( $match_id, '_gsf_api_id', true ) ) );
		if ( is_wp_error( $body ) ) {
			return $count ? $count : $body;
		}
		$home_api = (string) get_post_meta( (int) get_post_meta( $match_id, '_gsf_home', true ), '_gsf_api_id', true );
		$events   = gsf_api_parse_events( (array) $body['response'], $home_api );
		usort(
			$events,
			static function ( $a, $b ) {
				return $a['minute'] <=> $b['minute'];
			}
		);
		update_post_meta( $match_id, '_gsf_events', $events );
		if ( 'ft' === get_post_meta( $match_id, '_gsf_status', true ) ) {
			update_post_meta( $match_id, '_gsf_events_synced', time() );
		}
		++$count;
	}
	if ( $count ) {
		gsf_flush_cache();
	}
	return $count;
}

/**
 * Import squads, a few clubs per run (one request per club).
 *
 * @param int $max Clubs per run.
 * @return array|WP_Error {done, total, finished}.
 */
function gsf_api_sync_squads( $max = 8 ) {
	$clubs = get_posts(
		array(
			'post_type'      => 'gsf_club',
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'meta_key'       => '_gsf_api_id', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_compare'   => 'EXISTS',
		)
	);
	$offset = (int) get_option( 'gsf_api_squad_offset', 0 );
	if ( $offset >= count( $clubs ) ) {
		$offset = 0;
	}
	$map   = array(
		'goalkeeper' => 'GK',
		'defender'   => 'DF',
		'midfielder' => 'MF',
		'attacker'   => 'FW',
	);
	$batch = array_slice( $clubs, $offset, (int) $max );
	foreach ( $batch as $club_id ) {
		$body = gsf_api_get( 'players/squads', array( 'team' => get_post_meta( $club_id, '_gsf_api_id', true ) ) );
		if ( is_wp_error( $body ) ) {
			update_option( 'gsf_api_squad_offset', $offset, false );
			return $body;
		}
		foreach ( (array) ( $body['response'][0]['players'] ?? array() ) as $player ) {
			if ( empty( $player['id'] ) ) {
				continue;
			}
			gsf_upsert(
				'gsf_player',
				array(
					'api_id' => $player['id'],
					'title'  => sanitize_text_field( $player['name'] ?? '' ),
					'image'  => $player['photo'] ?? '',
					'meta'   => array(
						'_gsf_club'     => $club_id,
						'_gsf_number'   => isset( $player['number'] ) ? absint( $player['number'] ) : '',
						'_gsf_position' => $map[ strtolower( (string) ( $player['position'] ?? '' ) ) ] ?? '',
					),
				)
			);
		}
		++$offset;
	}
	$finished = $offset >= count( $clubs );
	update_option( 'gsf_api_squad_offset', $finished ? 0 : $offset, false );
	gsf_flush_cache();
	return array(
		'done'     => $offset,
		'total'    => count( $clubs ),
		'finished' => $finished,
	);
}

/**
 * Import season statistics of the players (paginated: 20 players per request).
 *
 * @param int $max Pages per run.
 * @return array|WP_Error {done, total, finished}.
 */
function gsf_api_sync_player_stats( $max = 8 ) {
	list( , $season ) = gsf_api_terms();
	$page             = max( 1, (int) get_option( 'gsf_api_stats_page', 1 ) );
	$total            = $page;
	$map              = array(
		'goalkeeper' => 'GK',
		'defender'   => 'DF',
		'midfielder' => 'MF',
		'attacker'   => 'FW',
	);
	for ( $i = 0; $i < $max && $page <= $total; $i++ ) {
		$body = gsf_api_get(
			'players',
			array(
				'league' => gsf_settings( 'api_league' ),
				'season' => gsf_settings( 'api_season' ),
				'page'   => $page,
			)
		);
		if ( is_wp_error( $body ) ) {
			update_option( 'gsf_api_stats_page', $page, false );
			return $body;
		}
		$total = max( 1, (int) ( $body['paging']['total'] ?? 1 ) );
		foreach ( (array) $body['response'] as $item ) {
			$player = $item['player'] ?? array();
			if ( empty( $player['id'] ) ) {
				continue;
			}
			// A player transferred mid-season has one block per club: add them up, keep the last club.
			$sum     = array(
				'apps'    => 0,
				'goals'   => 0,
				'assists' => 0,
				'yellow'  => 0,
				'red'     => 0,
				'minutes' => 0,
				'rating'  => 0,
			);
			$ratings = array();
			$club_id = 0;
			$pos     = '';
			$number  = '';
			foreach ( (array) ( $item['statistics'] ?? array() ) as $block ) {
				$sum['apps']    += (int) ( $block['games']['appearences'] ?? 0 );
				$sum['minutes'] += (int) ( $block['games']['minutes'] ?? 0 );
				$sum['goals']   += (int) ( $block['goals']['total'] ?? 0 );
				$sum['assists'] += (int) ( $block['goals']['assists'] ?? 0 );
				$sum['yellow']  += (int) ( $block['cards']['yellow'] ?? 0 );
				$sum['red']     += (int) ( $block['cards']['red'] ?? 0 ) + (int) ( $block['cards']['yellowred'] ?? 0 );
				if ( ! empty( $block['games']['rating'] ) ) {
					$ratings[] = (float) $block['games']['rating'];
				}
				$found   = gsf_find_by_api_id( 'gsf_club', $block['team']['id'] ?? 0 );
				$club_id = $found ? $found : $club_id;
				$pos     = $map[ strtolower( (string) ( $block['games']['position'] ?? '' ) ) ] ?? $pos;
				$number  = isset( $block['games']['number'] ) ? absint( $block['games']['number'] ) : $number;
			}
			$sum['rating'] = $ratings ? round( array_sum( $ratings ) / count( $ratings ), 2 ) : 0;
			$height        = (int) preg_replace( '/\D/', '', (string) ( $player['height'] ?? '' ) );
			$player_id     = gsf_upsert(
				'gsf_player',
				array(
					'api_id'        => $player['id'],
					'title'         => sanitize_text_field( $player['name'] ?? '' ),
					'image'         => $player['photo'] ?? '',
					'meta'          => array(
						'_gsf_club' => $club_id ?: '',
					),
					'only_if_empty' => array(
						'_gsf_position'    => $pos,
						'_gsf_number'      => $number,
						'_gsf_birth'       => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $player['birth']['date'] ?? '' ) ) ? $player['birth']['date'] : '',
						'_gsf_nationality' => sanitize_text_field( $player['nationality'] ?? '' ),
						'_gsf_height'      => $height ?: '',
					),
				)
			);
			if ( $player_id && $season ) {
				$all            = get_post_meta( $player_id, '_gsf_api_stats', true );
				$all            = is_array( $all ) ? $all : array();
				$all[ $season ] = $sum;
				update_post_meta( $player_id, '_gsf_api_stats', $all );
			}
		}
		++$page;
	}
	$finished = $page > $total;
	update_option( 'gsf_api_stats_page', $finished ? 1 : $page, false );
	gsf_flush_cache();
	return array(
		'done'     => min( $page - 1, $total ),
		'total'    => $total,
		'finished' => $finished,
	);
}

/**
 * Search leagues by country (helper for the settings screen).
 *
 * @param string $country Country name in English, e.g. "Senegal".
 * @return array|WP_Error
 */
function gsf_api_search_leagues( $country ) {
	$body = gsf_api_get( 'leagues', array( 'country' => $country ) );
	if ( is_wp_error( $body ) ) {
		return $body;
	}
	$out = array();
	foreach ( (array) $body['response'] as $item ) {
		$seasons = wp_list_pluck( (array) ( $item['seasons'] ?? array() ), 'year' );
		$out[]   = array(
			'id'      => (int) ( $item['league']['id'] ?? 0 ),
			'name'    => (string) ( $item['league']['name'] ?? '' ),
			'type'    => (string) ( $item['league']['type'] ?? '' ),
			'seasons' => $seasons ? min( $seasons ) . ' → ' . max( $seasons ) : '',
		);
	}
	return $out;
}

/**
 * Record the outcome of a synchronisation for display in the admin.
 *
 * @param string $step   Step name.
 * @param mixed  $result Result or WP_Error.
 */
function gsf_api_log( $step, $result ) {
	$log = (array) get_option( 'gsf_api_log', array() );
	array_unshift(
		$log,
		array(
			'time'    => time(),
			'step'    => $step,
			'ok'      => ! is_wp_error( $result ),
			'message' => is_wp_error( $result ) ? $result->get_error_message() : ( is_array( $result ) ? $result['done'] . ' / ' . $result['total'] : (string) $result ),
		)
	);
	update_option( 'gsf_api_log', array_slice( $log, 0, 15 ), false );
}

/**
 * Scheduled synchronisation: fixtures and scores, then events of the latest matches.
 */
add_action(
	'gsf_api_cron',
	static function () {
		if ( ! gsf_api_ready() ) {
			return;
		}
		$result = gsf_api_sync_fixtures();
		gsf_api_log( 'fixtures', $result );
		if ( ! is_wp_error( $result ) && gsf_settings( 'api_events' ) ) {
			gsf_api_log( 'events', gsf_api_sync_events() );
		}
	}
);

/**
 * Continuation of long imports (squads, player statistics) in the background.
 *
 * @param string $step squads|stats.
 */
add_action(
	'gsf_api_continue',
	static function ( $step ) {
		if ( ! in_array( $step, array( 'squads', 'stats' ), true ) || ! gsf_api_ready() ) {
			return;
		}
		$result = 'squads' === $step ? gsf_api_sync_squads() : gsf_api_sync_player_stats();
		gsf_api_log( $step, $result );
		if ( is_array( $result ) && ! $result['finished'] ) {
			// The free plan allows 10 requests per minute: wait before the next batch.
			wp_schedule_single_event( time() + 70, 'gsf_api_continue', array( $step ) );
		}
	}
);

add_filter(
	'cron_schedules', // phpcs:ignore WordPress.WP.CronInterval
	static function ( $schedules ) {
		$schedules['gsf_15min'] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __( 'Toutes les 15 minutes', 'galsenfoot-league' ),
		);
		$schedules['gsf_6h']    = array(
			'interval' => 6 * HOUR_IN_SECONDS,
			'display'  => __( 'Toutes les 6 heures', 'galsenfoot-league' ),
		);
		return $schedules;
	}
);

/**
 * (Re)schedule the cron according to the settings.
 */
function gsf_api_reschedule() {
	wp_clear_scheduled_hook( 'gsf_api_cron' );
	$frequency = gsf_settings( 'api_frequency' );
	if ( 'off' !== $frequency && in_array( $frequency, array( 'gsf_15min', 'hourly', 'gsf_6h', 'twicedaily', 'daily' ), true ) ) {
		wp_schedule_event( time() + 60, $frequency, 'gsf_api_cron' );
	}
}
add_action( 'update_option_gsf_settings', 'gsf_api_reschedule' );
add_action( 'add_option_gsf_settings', 'gsf_api_reschedule' );

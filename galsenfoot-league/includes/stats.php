<?php
/**
 * Standings and player statistics, computed from match data.
 *
 * @package GalsenFootLeague
 */

defined( 'ABSPATH' ) || exit;

/**
 * League table for a competition / season.
 *
 * @param int $competition Competition term ID.
 * @param int $season      Season term ID.
 * @return array[] Ordered rows: club, played, won, drawn, lost, gf, ga, gd, points, form[], rank.
 */
function gsf_get_standings( $competition, $season ) {
	$competition = (int) $competition;
	$season      = (int) $season;
	$cache_key   = gsf_cache_key( "standings|$competition|$season" );
	$cached      = get_transient( $cache_key );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	$ids = gsf_query_matches(
		array(
			'competition' => $competition,
			'season'      => $season,
			'limit'       => 2000,
			'order'       => 'ASC',
		)
	);

	$win  = (int) gsf_settings( 'points_win' );
	$draw = (int) gsf_settings( 'points_draw' );
	$rows = array();
	$blank = static function ( $club_id ) {
		return array(
			'club'   => $club_id,
			'played' => 0,
			'won'    => 0,
			'drawn'  => 0,
			'lost'   => 0,
			'gf'     => 0,
			'ga'     => 0,
			'gd'     => 0,
			'points' => 0,
			'form'   => array(),
		);
	};

	foreach ( $ids as $id ) {
		$m = gsf_match( $id );
		if ( ! $m['home'] || ! $m['away'] ) {
			continue;
		}
		foreach ( array( $m['home'], $m['away'] ) as $club_id ) {
			if ( ! isset( $rows[ $club_id ] ) ) {
				$rows[ $club_id ] = $blank( $club_id );
			}
		}
		if ( 'ft' !== $m['status'] || null === $m['home_score'] || null === $m['away_score'] ) {
			continue;
		}
		$sides = array(
			array( $m['home'], $m['home_score'], $m['away_score'] ),
			array( $m['away'], $m['away_score'], $m['home_score'] ),
		);
		foreach ( $sides as $side ) {
			list( $club_id, $for, $against ) = $side;
			$row = &$rows[ $club_id ];
			++$row['played'];
			$row['gf'] += $for;
			$row['ga'] += $against;
			if ( $for > $against ) {
				++$row['won'];
				$row['points'] += $win;
				$row['form'][]  = 'W';
			} elseif ( $for === $against ) {
				++$row['drawn'];
				$row['points'] += $draw;
				$row['form'][]  = 'D';
			} else {
				++$row['lost'];
				$row['form'][] = 'L';
			}
			unset( $row );
		}
	}

	foreach ( $rows as &$row ) {
		$row['gd']   = $row['gf'] - $row['ga'];
		$row['form'] = array_slice( $row['form'], -5 );
		// Manual adjustment (penalty points or bonus) set on the club.
		$adjust = get_post_meta( $row['club'], '_gsf_points_adjust', true );
		if ( is_array( $adjust ) && isset( $adjust[ "$competition:$season" ] ) ) {
			$row['points'] += (int) $adjust[ "$competition:$season" ];
		}
		$row['name'] = gsf_club( $row['club'] )['name'];
	}
	unset( $row );

	$rows = array_values( $rows );
	usort(
		$rows,
		static function ( $a, $b ) {
			return array( $b['points'], $b['gd'], $b['gf'] ) <=> array( $a['points'], $a['gd'], $a['gf'] )
				?: strcasecmp( $a['name'], $b['name'] );
		}
	);
	foreach ( $rows as $i => &$row ) {
		$row['rank'] = $i + 1;
	}
	unset( $row );

	/**
	 * Filter the computed standings (custom tie-breakers, groups…).
	 */
	$rows = apply_filters( 'gsf_standings', $rows, $competition, $season );

	set_transient( $cache_key, $rows, DAY_IN_SECONDS );
	return $rows;
}

/**
 * Statistics of every player for a competition / season.
 *
 * Sources, in order of priority:
 *  1. statistics synchronised from the API for the season (when available);
 *  2. events (goals, cards) and line-ups entered on the matches.
 *
 * @param int $competition Competition term ID (0 = all).
 * @param int $season      Season term ID (0 = all).
 * @return array[] player_id => stats.
 */
function gsf_get_player_stats_table( $competition, $season ) {
	$competition = (int) $competition;
	$season      = (int) $season;
	$cache_key   = gsf_cache_key( "pstats|$competition|$season" );
	$cached      = get_transient( $cache_key );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	$blank = array(
		'apps'    => 0,
		'goals'   => 0,
		'assists' => 0,
		'yellow'  => 0,
		'red'     => 0,
		'minutes' => 0,
		'rating'  => 0,
		'source'  => 'matches',
	);
	$stats = array();
	$add   = static function ( $player_id, $key, $n = 1 ) use ( &$stats, $blank ) {
		$player_id = (int) $player_id;
		if ( ! $player_id ) {
			return;
		}
		if ( ! isset( $stats[ $player_id ] ) ) {
			$stats[ $player_id ] = $blank;
		}
		$stats[ $player_id ][ $key ] += $n;
	};

	$ids = gsf_query_matches(
		array(
			'competition' => $competition,
			'season'      => $season,
			'status'      => 'ft',
			'limit'       => 2000,
		)
	);
	foreach ( $ids as $id ) {
		$events = get_post_meta( $id, '_gsf_events', true );
		$seen   = array();
		foreach ( array( '_gsf_lineup_home', '_gsf_lineup_away' ) as $key ) {
			$lineup = get_post_meta( $id, $key, true );
			foreach ( (array) $lineup as $player_id ) {
				if ( $player_id && ! isset( $seen[ $player_id ] ) ) {
					$seen[ $player_id ] = true;
					$add( $player_id, 'apps' );
				}
			}
		}
		foreach ( (array) $events as $event ) {
			$player = (int) ( $event['player'] ?? 0 );
			$type   = $event['type'] ?? '';
			// A player with an event took part in the match even if no line-up was entered.
			foreach ( array( $player, (int) ( $event['assist'] ?? 0 ) ) as $pid ) {
				if ( $pid && ! isset( $seen[ $pid ] ) ) {
					$seen[ $pid ] = true;
					$add( $pid, 'apps' );
				}
			}
			if ( 'goal' === $type || 'penalty' === $type ) {
				$add( $player, 'goals' );
				$add( (int) ( $event['assist'] ?? 0 ), 'assists' );
			} elseif ( 'yellow' === $type ) {
				$add( $player, 'yellow' );
			} elseif ( 'red' === $type ) {
				$add( $player, 'red' );
			}
		}
	}

	// Overlay API season statistics.
	if ( $season ) {
		global $wpdb;
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_gsf_api_stats'"
		);
		foreach ( $rows as $row ) {
			$all = maybe_unserialize( $row->meta_value );
			if ( is_array( $all ) && isset( $all[ $season ] ) && 'publish' === get_post_status( (int) $row->post_id ) ) {
				$stats[ (int) $row->post_id ] = array_merge( $blank, array_map( 'floatval', $all[ $season ] ), array( 'source' => 'api' ) );
			}
		}
	}

	set_transient( $cache_key, $stats, DAY_IN_SECONDS );
	return $stats;
}

/**
 * Statistics of one player.
 *
 * @param int $player_id   Player ID.
 * @param int $competition Competition term ID.
 * @param int $season      Season term ID.
 * @return array
 */
function gsf_get_player_stats( $player_id, $competition = 0, $season = 0 ) {
	$table = gsf_get_player_stats_table( $competition, $season );
	return $table[ (int) $player_id ] ?? array(
		'apps'    => 0,
		'goals'   => 0,
		'assists' => 0,
		'yellow'  => 0,
		'red'     => 0,
		'minutes' => 0,
		'rating'  => 0,
		'source'  => 'matches',
	);
}

/**
 * Ranking of players on a statistic.
 *
 * @param string $stat        goals|assists|yellow|red|apps.
 * @param int    $competition Competition term ID.
 * @param int    $season      Season term ID.
 * @param int    $limit       Number of rows.
 * @param int    $club        Restrict to a club.
 * @return array[] Rows with player, club, value and stats.
 */
function gsf_get_leaders( $stat, $competition, $season, $limit = 10, $club = 0 ) {
	$allowed = array( 'goals', 'assists', 'yellow', 'red', 'apps' );
	if ( ! in_array( $stat, $allowed, true ) ) {
		$stat = 'goals';
	}
	$rows = array();
	foreach ( gsf_get_player_stats_table( $competition, $season ) as $player_id => $s ) {
		if ( $s[ $stat ] <= 0 || 'publish' !== get_post_status( $player_id ) ) {
			continue;
		}
		$player_club = (int) get_post_meta( $player_id, '_gsf_club', true );
		if ( $club && $player_club !== (int) $club ) {
			continue;
		}
		$rows[] = array(
			'player' => $player_id,
			'name'   => get_the_title( $player_id ),
			'club'   => $player_club,
			'value'  => (int) $s[ $stat ],
			'stats'  => $s,
		);
	}
	usort(
		$rows,
		static function ( $a, $b ) {
			// Ties: fewer appearances first, then alphabetical.
			return array( $b['value'], $a['stats']['apps'] ) <=> array( $a['value'], $b['stats']['apps'] )
				?: strcasecmp( $a['name'], $b['name'] );
		}
	);
	return array_slice( $rows, 0, max( 1, (int) $limit ) );
}

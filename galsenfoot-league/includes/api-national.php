<?php
/**
 * National team import (API-Football): team, current squad, matches of a competition
 * (e.g. Africa Cup of Nations qualifiers), group table, and per-match details
 * (goals, cards, players who took part).
 *
 * Settings used (gsf_settings): nt_team, nt_country, nt_league, nt_league_name, nt_season,
 * nt_extra (also import the last / next 10 matches of every competition), nt_auto.
 *
 * @package GalsenFootLeague
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the national team import is configured.
 */
function gsf_nt_ready() {
	return '' !== trim( (string) gsf_settings( 'api_key' ) ) && gsf_settings( 'nt_team' ) && gsf_settings( 'nt_league' ) && gsf_settings( 'nt_season' );
}

/**
 * French name of a national team (API-Football uses English names).
 * Only applied when the team is created: a name edited by hand is never overwritten.
 *
 * @param string $name English name.
 * @return string
 */
function gsf_nt_team_name( $name ) {
	$map = array(
		'Algeria' => 'Algérie',
		'Angola' => 'Angola',
		'Benin' => 'Bénin',
		'Botswana' => 'Botswana',
		'Burkina Faso' => 'Burkina Faso',
		'Burundi' => 'Burundi',
		'Cameroon' => 'Cameroun',
		'Cape Verde Islands' => 'Cap-Vert',
		'Cape Verde' => 'Cap-Vert',
		'Central African Republic' => 'Centrafrique',
		'Chad' => 'Tchad',
		'Comoros' => 'Comores',
		'Congo' => 'Congo',
		'Congo DR' => 'RD Congo',
		'DR Congo' => 'RD Congo',
		'Djibouti' => 'Djibouti',
		'Egypt' => 'Égypte',
		'Equatorial Guinea' => 'Guinée équatoriale',
		'Eritrea' => 'Érythrée',
		'Eswatini' => 'Eswatini',
		'Swaziland' => 'Eswatini',
		'Ethiopia' => 'Éthiopie',
		'Gabon' => 'Gabon',
		'Gambia' => 'Gambie',
		'Ghana' => 'Ghana',
		'Guinea' => 'Guinée',
		'Guinea-Bissau' => 'Guinée-Bissau',
		'Ivory Coast' => 'Côte d’Ivoire',
		'Cote D\'Ivoire' => 'Côte d’Ivoire',
		'Kenya' => 'Kenya',
		'Lesotho' => 'Lesotho',
		'Liberia' => 'Liberia',
		'Libya' => 'Libye',
		'Madagascar' => 'Madagascar',
		'Malawi' => 'Malawi',
		'Mali' => 'Mali',
		'Mauritania' => 'Mauritanie',
		'Mauritius' => 'Maurice',
		'Morocco' => 'Maroc',
		'Mozambique' => 'Mozambique',
		'Namibia' => 'Namibie',
		'Niger' => 'Niger',
		'Nigeria' => 'Nigeria',
		'Rwanda' => 'Rwanda',
		'Sao Tome and Principe' => 'Sao Tomé-et-Principe',
		'Senegal' => 'Sénégal',
		'Seychelles' => 'Seychelles',
		'Sierra Leone' => 'Sierra Leone',
		'Somalia' => 'Somalie',
		'South Africa' => 'Afrique du Sud',
		'South Sudan' => 'Soudan du Sud',
		'Sudan' => 'Soudan',
		'Tanzania' => 'Tanzanie',
		'Togo' => 'Togo',
		'Tunisia' => 'Tunisie',
		'Uganda' => 'Ouganda',
		'Zambia' => 'Zambie',
		'Zimbabwe' => 'Zimbabwe',
		'France' => 'France',
		'Germany' => 'Allemagne',
		'Spain' => 'Espagne',
		'Italy' => 'Italie',
		'England' => 'Angleterre',
		'Portugal' => 'Portugal',
		'Netherlands' => 'Pays-Bas',
		'Belgium' => 'Belgique',
		'Switzerland' => 'Suisse',
		'Brazil' => 'Brésil',
		'Argentina' => 'Argentine',
		'USA' => 'États-Unis',
		'Mexico' => 'Mexique',
		'Japan' => 'Japon',
		'South Korea' => 'Corée du Sud',
		'Saudi Arabia' => 'Arabie saoudite',
		'Qatar' => 'Qatar',
		'Iran' => 'Iran',
		'Croatia' => 'Croatie',
		'Denmark' => 'Danemark',
		'Poland' => 'Pologne',
		'Serbia' => 'Serbie',
		'Wales' => 'Pays de Galles',
		'Ecuador' => 'Équateur',
		'Uruguay' => 'Uruguay',
		'Colombia' => 'Colombie',
		'Canada' => 'Canada',
		'Australia' => 'Australie',
		'Turkey' => 'Turquie',
		'Sweden' => 'Suède',
		'Norway' => 'Norvège',
		'Austria' => 'Autriche',
		'Greece' => 'Grèce',
		'Ireland' => 'Irlande',
		'Scotland' => 'Écosse',
		'Ukraine' => 'Ukraine',
		'Russia' => 'Russie',
		'China' => 'Chine',
		'Bolivia' => 'Bolivie',
		'Chile' => 'Chili',
		'Peru' => 'Pérou',
		'Paraguay' => 'Paraguay',
		'Jordan' => 'Jordanie',
		'Iraq' => 'Irak',
		'United Arab Emirates' => 'Émirats arabes unis',
		'Kuwait' => 'Koweït',
		'Oman' => 'Oman',
		'Bahrain' => 'Bahreïn',
	);
	$map = (array) apply_filters( 'gsf_national_team_names', $map );
	return $map[ $name ] ?? (string) $name;
}

/**
 * French name of common competitions.
 *
 * @param string $name API league name.
 * @return string
 */
function gsf_nt_competition_name( $name ) {
	$map = array(
		'/africa cup of nations.*qualif/i'   => __( 'Éliminatoires CAN', 'galsenfoot-league' ),
		'/africa cup of nations/i'           => __( 'Coupe d’Afrique des nations', 'galsenfoot-league' ),
		'/world cup.*qualif.*africa/i'       => __( 'Éliminatoires Coupe du monde (Afrique)', 'galsenfoot-league' ),
		'/^world cup$/i'                     => __( 'Coupe du monde', 'galsenfoot-league' ),
		'/african nations championship.*qu/i' => __( 'Éliminatoires CHAN', 'galsenfoot-league' ),
		'/african nations championship/i'    => __( 'CHAN', 'galsenfoot-league' ),
		'/friendlies/i'                      => __( 'Matchs amicaux', 'galsenfoot-league' ),
	);
	foreach ( $map as $pattern => $label ) {
		if ( preg_match( $pattern, (string) $name ) ) {
			return $label;
		}
	}
	return (string) $name;
}

/**
 * Competition and season terms for a remote league / season, created on first use.
 *
 * @param int    $league_id Remote league ID.
 * @param string $name      Remote league name.
 * @param int    $year      Remote season (year).
 * @return array{0:int,1:int} [competition term ID, season term ID].
 */
function gsf_nt_terms( $league_id, $name, $year ) {
	$league_id = (int) $league_id;
	$year      = (int) $year;
	$found     = get_terms(
		array(
			'taxonomy'   => 'gsf_competition',
			'hide_empty' => false,
			'meta_key'   => '_gsf_api_league', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value' => (string) $league_id, // phpcs:ignore WordPress.DB.SlowDBQuery
			'fields'     => 'ids',
			'number'     => 1,
		)
	);
	$comp = ( ! is_wp_error( $found ) && $found ) ? (int) $found[0] : 0;
	if ( ! $comp ) {
		$label = gsf_nt_competition_name( $name );
		$term  = term_exists( $label, 'gsf_competition' );
		if ( ! $term ) {
			$term = wp_insert_term( $label, 'gsf_competition' );
		}
		$comp = is_wp_error( $term ) ? 0 : (int) $term['term_id'];
		if ( $comp ) {
			update_term_meta( $comp, '_gsf_api_league', (string) $league_id );
			update_term_meta( $comp, '_gsf_national', 1 );
		}
	}
	$season = 0;
	if ( $year ) {
		$label  = (string) $year;
		$term   = term_exists( $label, 'gsf_season' );
		$term   = $term ? $term : wp_insert_term( $label, 'gsf_season' );
		$season = is_wp_error( $term ) ? 0 : (int) $term['term_id'];
	}
	return array( $comp, $season );
}

/**
 * Local post of the national team (created if needed).
 *
 * @return int
 */
function gsf_nt_local_team() {
	$api_id = (int) gsf_settings( 'nt_team' );
	$id     = gsf_find_by_api_id( 'gsf_club', $api_id );
	if ( ! $id && $api_id ) {
		$id = gsf_upsert(
			'gsf_club',
			array(
				'api_id'      => $api_id,
				'title'       => gsf_nt_team_name( (string) gsf_settings( 'nt_name' ) ),
				'match_title' => false,
				'image'       => (string) gsf_settings( 'nt_logo' ),
				'meta'        => array( '_gsf_national' => 1 ),
			)
		);
	}
	if ( $id ) {
		update_post_meta( $id, '_gsf_national', 1 );
	}
	return (int) $id;
}

/**
 * Find national teams by country / name.
 *
 * @param string $country Country name in English (API naming), e.g. "Senegal".
 * @return array|WP_Error List of [id, name, logo, code].
 */
function gsf_nt_find_team( $country ) {
	$body = gsf_api_get( 'teams', array( 'name' => $country ) );
	if ( is_wp_error( $body ) ) {
		return $body;
	}
	$out = array();
	foreach ( (array) $body['response'] as $item ) {
		$team = $item['team'] ?? array();
		if ( ! empty( $team['id'] ) && ! empty( $team['national'] ) ) {
			$out[] = array(
				'id'   => (int) $team['id'],
				'name' => sanitize_text_field( $team['name'] ?? '' ),
				'logo' => esc_url_raw( $team['logo'] ?? '' ),
				'code' => sanitize_text_field( $team['code'] ?? '' ),
			);
		}
	}
	return $out;
}

/**
 * Competitions the national team takes part in, most relevant first
 * (AFCON qualifiers, AFCON, World Cup qualifiers…).
 *
 * @param int $team_id Remote team ID.
 * @return array|WP_Error List of [id, name, label, seasons[], current].
 */
function gsf_nt_leagues( $team_id ) {
	$body = gsf_api_get( 'leagues', array( 'team' => (int) $team_id ) );
	if ( is_wp_error( $body ) ) {
		return $body;
	}
	$out = array();
	foreach ( (array) $body['response'] as $item ) {
		$league  = $item['league'] ?? array();
		$seasons = array();
		$current = 0;
		foreach ( (array) ( $item['seasons'] ?? array() ) as $season ) {
			if ( ! empty( $season['year'] ) ) {
				$seasons[] = (int) $season['year'];
				if ( ! empty( $season['current'] ) ) {
					$current = (int) $season['year'];
				}
			}
		}
		rsort( $seasons );
		if ( empty( $league['id'] ) || ! $seasons ) {
			continue;
		}
		$name  = sanitize_text_field( $league['name'] ?? '' );
		$out[] = array(
			'id'      => (int) $league['id'],
			'name'    => $name,
			'label'   => gsf_nt_competition_name( $name ),
			'seasons' => $seasons,
			'current' => $current ? $current : $seasons[0],
			'weight'  => preg_match( '/africa cup of nations.*qualif/i', $name ) ? 0 : ( preg_match( '/africa cup of nations/i', $name ) ? 1 : ( preg_match( '/world cup/i', $name ) ? 2 : 3 ) ),
		);
	}
	usort(
		$out,
		static function ( $a, $b ) {
			return array( $a['weight'], $a['name'] ) <=> array( $b['weight'], $b['name'] );
		}
	);
	return $out;
}

/**
 * Import the current squad of the national team.
 * Players keep their club; membership is stored in `_gsf_national_team`.
 *
 * @return int|WP_Error Number of players.
 */
function gsf_nt_import_squad() {
	$team_id = gsf_nt_local_team();
	$body    = gsf_api_get( 'players/squads', array( 'team' => (int) gsf_settings( 'nt_team' ) ) );
	if ( is_wp_error( $body ) ) {
		return $body;
	}
	$players = (array) ( $body['response'][0]['players'] ?? array() );
	if ( ! $players ) {
		return new WP_Error( 'gsf_nt_empty', __( 'L’API n’a renvoyé aucun joueur pour cette sélection (liste non publiée ou non couverte par votre offre).', 'galsenfoot-league' ) );
	}
	$map  = array(
		'goalkeeper' => 'GK',
		'defender'   => 'DF',
		'midfielder' => 'MF',
		'attacker'   => 'FW',
	);
	$keep = array();
	foreach ( $players as $player ) {
		if ( empty( $player['id'] ) ) {
			continue;
		}
		$player_id = gsf_upsert(
			'gsf_player',
			array(
				'api_id'        => $player['id'],
				'title'         => sanitize_text_field( $player['name'] ?? '' ),
				'image'         => $player['photo'] ?? '',
				// Many players share the same name: never attach to a local player by name.
				'match_title'   => false,
				'meta'          => array(
					'_gsf_national_team' => $team_id,
					'_gsf_nt_number'     => isset( $player['number'] ) ? absint( $player['number'] ) : '',
				),
				'only_if_empty' => array(
					'_gsf_position'    => $map[ strtolower( (string) ( $player['position'] ?? '' ) ) ] ?? '',
					'_gsf_nationality' => gsf_nt_team_name( (string) gsf_settings( 'nt_name' ) ),
				),
			)
		);
		if ( $player_id ) {
			$keep[] = $player_id;
			delete_post_meta( $player_id, '_gsf_nt_former' );
		}
	}
	// Players no longer called up leave the current squad but keep their history.
	$previous = get_posts(
		array(
			'post_type'      => 'gsf_player',
			'post_status'    => 'any',
			'posts_per_page' => 300,
			'fields'         => 'ids',
			'meta_key'       => '_gsf_national_team', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => $team_id, // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	foreach ( array_diff( $previous, $keep ) as $old ) {
		delete_post_meta( $old, '_gsf_national_team' );
		update_post_meta( $old, '_gsf_nt_former', $team_id );
	}
	gsf_flush_cache();
	return count( $keep );
}

/**
 * Import the matches of the national team: the chosen competition / season and,
 * optionally, its last and next 10 matches in every competition.
 *
 * @return int|WP_Error Number of matches.
 */
function gsf_nt_import_fixtures() {
	gsf_nt_local_team();
	$team    = (int) gsf_settings( 'nt_team' );
	$queries = array(
		array(
			'team'   => $team,
			'league' => (int) gsf_settings( 'nt_league' ),
			'season' => (int) gsf_settings( 'nt_season' ),
		),
	);
	if ( gsf_settings( 'nt_extra' ) ) {
		$queries[] = array(
			'team' => $team,
			'last' => 10,
		);
		$queries[] = array(
			'team' => $team,
			'next' => 10,
		);
	}
	$count = 0;
	$seen  = array();
	foreach ( $queries as $i => $query ) {
		$body = gsf_api_get( 'fixtures', $query );
		if ( is_wp_error( $body ) ) {
			if ( 0 === $i ) {
				return $body;
			}
			gsf_api_log( 'nt-fixtures', $body );
			continue;
		}
		foreach ( (array) $body['response'] as $item ) {
			$fixture_id = (int) ( $item['fixture']['id'] ?? 0 );
			if ( ! $fixture_id || isset( $seen[ $fixture_id ] ) ) {
				continue;
			}
			$seen[ $fixture_id ]   = true;
			list( $comp, $season ) = gsf_nt_terms( $item['league']['id'] ?? 0, $item['league']['name'] ?? '', $item['league']['season'] ?? 0 );
			$count                += gsf_api_save_fixture( $item, $comp, $season, true ) ? 1 : 0;
		}
	}
	gsf_flush_cache();
	return $count;
}

/**
 * Import the official group table(s) of the chosen competition that contain the national team.
 * Stored on the competition term, per season, and used instead of a table computed
 * from the imported matches only.
 *
 * @return int|WP_Error Number of teams in the table(s).
 */
function gsf_nt_import_standings() {
	$body = gsf_api_get(
		'standings',
		array(
			'league' => (int) gsf_settings( 'nt_league' ),
			'season' => (int) gsf_settings( 'nt_season' ),
			'team'   => (int) gsf_settings( 'nt_team' ),
		)
	);
	if ( is_wp_error( $body ) ) {
		return $body;
	}
	$league = $body['response'][0]['league'] ?? array();
	if ( empty( $league['standings'] ) ) {
		return 0;
	}
	list( $comp, $season ) = gsf_nt_terms( $league['id'] ?? gsf_settings( 'nt_league' ), $league['name'] ?? (string) gsf_settings( 'nt_league_name' ), $league['season'] ?? gsf_settings( 'nt_season' ) );
	$tables                = array();
	$count                 = 0;
	foreach ( (array) $league['standings'] as $group ) {
		$rows = array();
		foreach ( (array) $group as $row ) {
			$team = $row['team'] ?? array();
			if ( empty( $team['id'] ) ) {
				continue;
			}
			$club_id = gsf_find_by_api_id( 'gsf_club', $team['id'] );
			if ( ! $club_id ) {
				$club_id = gsf_upsert(
					'gsf_club',
					array(
						'api_id'      => $team['id'],
						'title'       => gsf_nt_team_name( sanitize_text_field( $team['name'] ?? '' ) ),
						'image'       => $team['logo'] ?? '',
						'match_title' => false,
						'meta'        => array( '_gsf_national' => 1 ),
					)
				);
			}
			$all    = $row['all'] ?? array();
			$form   = array_values( array_intersect( str_split( strtoupper( preg_replace( '/[^WDL]/i', '', (string) ( $row['form'] ?? '' ) ) ) ), array( 'W', 'D', 'L' ) ) );
			$rows[] = array(
				'club'   => (int) $club_id,
				'rank'   => (int) ( $row['rank'] ?? 0 ),
				'played' => (int) ( $all['played'] ?? 0 ),
				'won'    => (int) ( $all['win'] ?? 0 ),
				'drawn'  => (int) ( $all['draw'] ?? 0 ),
				'lost'   => (int) ( $all['lose'] ?? 0 ),
				'gf'     => (int) ( $all['goals']['for'] ?? 0 ),
				'ga'     => (int) ( $all['goals']['against'] ?? 0 ),
				'gd'     => (int) ( $row['goalsDiff'] ?? 0 ),
				'points' => (int) ( $row['points'] ?? 0 ),
				// Assumption: API-Football lists the most recent result first; local tables read oldest first.
				'form'   => array_reverse( array_slice( $form, 0, 5 ) ),
				'group'  => sanitize_text_field( $row['group'] ?? '' ),
			);
			++$count;
		}
		if ( $rows ) {
			$tables[] = $rows;
		}
	}
	if ( $comp && $season ) {
		$all_tables            = get_term_meta( $comp, '_gsf_api_tables', true );
		$all_tables            = is_array( $all_tables ) ? $all_tables : array();
		$all_tables[ $season ] = array(
			'time'   => time(),
			'tables' => $tables,
		);
		update_term_meta( $comp, '_gsf_api_tables', $all_tables );
	}
	gsf_flush_cache();
	return $count;
}

/**
 * Details of finished / live national team matches: goals, cards and the players who took part.
 * One request per match (fixtures?id=), a few matches per run to protect the quota.
 *
 * @param int $max Matches per run.
 * @return array|WP_Error {done, total, finished}.
 */
function gsf_nt_import_details( $max = 4 ) {
	$pending = get_posts(
		array(
			'post_type'      => 'gsf_match',
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'fields'         => 'ids',
			'meta_key'       => '_gsf_ts', // phpcs:ignore WordPress.DB.SlowDBQuery
			'orderby'        => 'meta_value_num',
			'order'          => 'DESC',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array(
					'key'   => '_gsf_national_match',
					'value' => 1,
				),
				array(
					'key'     => '_gsf_status',
					'value'   => array( 'ft', 'live' ),
					'compare' => 'IN',
				),
				array(
					'relation' => 'OR',
					array(
						'key'     => '_gsf_details_synced',
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
	$done = 0;
	foreach ( array_slice( $pending, 0, (int) $max ) as $match_id ) {
		$body = gsf_api_get( 'fixtures', array( 'id' => get_post_meta( $match_id, '_gsf_api_id', true ) ) );
		if ( is_wp_error( $body ) ) {
			return $done ? array(
				'done'     => $done,
				'total'    => count( $pending ),
				'finished' => false,
			) : $body;
		}
		$item     = $body['response'][0] ?? array();
		$home_api = (string) get_post_meta( (int) get_post_meta( $match_id, '_gsf_home', true ), '_gsf_api_id', true );
		update_post_meta( $match_id, '_gsf_events', gsf_api_parse_events( (array) ( $item['events'] ?? array() ), $home_api ) );

		// Players who actually played (minutes > 0), for the sides whose players exist locally.
		foreach ( (array) ( $item['players'] ?? array() ) as $side_block ) {
			$side   = (string) ( $side_block['team']['id'] ?? '' ) === $home_api ? 'home' : 'away';
			$lineup = array();
			foreach ( (array) ( $side_block['players'] ?? array() ) as $entry ) {
				$minutes = (int) ( $entry['statistics'][0]['games']['minutes'] ?? 0 );
				$local   = gsf_find_by_api_id( 'gsf_player', $entry['player']['id'] ?? 0 );
				if ( $local && $minutes > 0 ) {
					$lineup[] = $local;
				}
			}
			if ( $lineup ) {
				update_post_meta( $match_id, '_gsf_lineup_' . $side, $lineup );
			}
		}
		if ( 'ft' === get_post_meta( $match_id, '_gsf_status', true ) ) {
			update_post_meta( $match_id, '_gsf_details_synced', time() );
		}
		++$done;
	}
	if ( $done ) {
		gsf_flush_cache();
	}
	return array(
		'done'     => $done,
		'total'    => count( $pending ),
		'finished' => count( $pending ) <= $done,
	);
}

/**
 * Full import: squad, matches, group table, then match details (continued in the background).
 *
 * @return array Step => result (int|array|WP_Error).
 */
function gsf_nt_import_all() {
	$results            = array();
	$results['squad']   = gsf_nt_import_squad();
	$results['matches'] = gsf_nt_import_fixtures();
	if ( is_wp_error( $results['matches'] ) ) {
		return $results;
	}
	$results['table']   = gsf_nt_import_standings();
	$results['details'] = gsf_nt_import_details( 3 );
	foreach ( $results as $step => $result ) {
		gsf_api_log( 'nt-' . $step, $result );
	}
	if ( is_array( $results['details'] ) && ! $results['details']['finished'] && ! wp_next_scheduled( 'gsf_api_continue', array( 'nt_details' ) ) ) {
		// The free plan allows 10 requests per minute: continue a minute later.
		wp_schedule_single_event( time() + 70, 'gsf_api_continue', array( 'nt_details' ) );
	}
	update_option( 'gsf_nt_last', time(), false );
	return $results;
}

/**
 * Background continuation of the match details import.
 */
add_action(
	'gsf_api_continue',
	static function ( $step ) {
		if ( 'nt_details' !== $step || ! gsf_nt_ready() ) {
			return;
		}
		$result = gsf_nt_import_details( 4 );
		gsf_api_log( 'nt-details', $result );
		if ( is_array( $result ) && ! $result['finished'] ) {
			wp_schedule_single_event( time() + 70, 'gsf_api_continue', array( 'nt_details' ) );
		}
	},
	5
);

/**
 * Scheduled refresh (when enabled): every 6 hours, or at each run on match days.
 */
add_action(
	'gsf_api_cron',
	static function () {
		if ( ! gsf_nt_ready() || ! gsf_settings( 'nt_auto' ) ) {
			return;
		}
		$team     = gsf_find_by_api_id( 'gsf_club', gsf_settings( 'nt_team' ) );
		$match_on = $team && gsf_query_matches(
			array(
				'club'     => $team,
				'any_term' => true,
				'from'     => time() - 4 * HOUR_IN_SECONDS,
				'to'       => time() + 4 * HOUR_IN_SECONDS,
				'limit'    => 1,
			)
		);
		if ( ! $match_on && time() - (int) get_option( 'gsf_nt_last', 0 ) < 6 * HOUR_IN_SECONDS ) {
			return;
		}
		$result = gsf_nt_import_fixtures();
		gsf_api_log( 'nt-matches', $result );
		if ( ! is_wp_error( $result ) ) {
			gsf_api_log( 'nt-table', gsf_nt_import_standings() );
			gsf_api_log( 'nt-details', gsf_nt_import_details( 3 ) );
		}
		update_option( 'gsf_nt_last', time(), false );
	},
	20
);

<?php
/**
 * Demonstration data: a complete fictional season.
 * Everything created here carries the `_gsf_demo` meta and can be removed in one click.
 *
 * @package GalsenFootLeague
 */

defined( 'ABSPATH' ) || exit;

/**
 * Install the demo.
 *
 * @return string Notice.
 */
function gsf_demo_install() {
	if ( get_posts( array( 'post_type' => 'gsf_club', 'meta_key' => '_gsf_demo', 'posts_per_page' => 1, 'fields' => 'ids', 'post_status' => 'any' ) ) ) { // phpcs:ignore WordPress.DB.SlowDBQuery
		return __( 'La démo est déjà installée.', 'galsenfoot-league' );
	}
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
	wp_defer_term_counting( true );
	wp_suspend_cache_invalidation( true );
	mt_srand( 20261 );

	$year   = (int) wp_date( 'n' ) >= 8 ? (int) wp_date( 'Y' ) : (int) wp_date( 'Y' ) - 1;
	$comp   = wp_insert_term( 'Ligue 1 (démo)', 'gsf_competition', array( 'slug' => 'ligue-1-demo' ) );
	$season = wp_insert_term( $year . '-' . ( $year + 1 ) . ' (démo)', 'gsf_season', array( 'slug' => 'saison-demo' ) );
	$comp   = is_wp_error( $comp ) ? (int) $comp->get_error_data() : (int) $comp['term_id'];
	$season = is_wp_error( $season ) ? (int) $season->get_error_data() : (int) $season['term_id'];
	update_term_meta( $comp, '_gsf_demo', 1 );
	update_term_meta( $season, '_gsf_demo', 1 );

	// name, short, city, stadium, colour 1, colour 2, strength.
	$clubs_data = array(
		array( 'ASC Jaraaf', 'JAR', 'Dakar', 'Stade Iba Mar Diop', '#0b7a3b', '#ffffff', 82 ),
		array( 'Teungueth FC', 'TFC', 'Rufisque', 'Stade Ngalandou Diouf', '#f5c400', '#0a0a0a', 80 ),
		array( 'Génération Foot', 'GF', 'Déni Biram Ndao', 'Stade Djibril Diagne', '#7a1b2e', '#f1d9a0', 79 ),
		array( 'Casa Sports', 'CAS', 'Ziguinchor', 'Stade Aline Sitoé Diatta', '#1f8a3d', '#ffffff', 76 ),
		array( 'AS Pikine', 'ASP', 'Pikine', 'Stade Alassane Djigo', '#0d7d45', '#ffe14d', 74 ),
		array( 'Diambars FC', 'DIA', 'Saly', 'Stade Fodé Wade', '#1d3f94', '#ffffff', 73 ),
		array( 'US Gorée', 'USG', 'Dakar', 'Stade Demba Diop', '#c8102e', '#ffffff', 75 ),
		array( 'Guédiawaye FC', 'GFC', 'Guédiawaye', 'Stade Amadou Barry', '#e2231a', '#f5c400', 71 ),
		array( 'Dakar Sacré-Cœur', 'DSC', 'Dakar', 'Stade de Sacré-Cœur', '#0057a8', '#ffd200', 72 ),
		array( 'AS Douanes', 'ASD', 'Dakar', 'Stade Iba Mar Diop', '#005c2e', '#d7dbd8', 70 ),
		array( 'Stade de Mbour', 'SDM', 'Mbour', 'Stade Caroline Faye', '#d4191f', '#ffffff', 68 ),
		array( 'Linguère de Saint-Louis', 'LIN', 'Saint-Louis', 'Stade Me Babacar Sèye', '#e9a800', '#0f6b34', 69 ),
		array( 'US Ouakam', 'USO', 'Dakar', 'Stade Municipal de Ouakam', '#1a1a1a', '#f2c200', 67 ),
		array( 'Jamono Fatick', 'JAM', 'Fatick', 'Stade Massène Sène', '#f26b1d', '#1a1a1a', 65 ),
	);

	$first = array( 'Mamadou', 'Ibrahima', 'Cheikh', 'Moussa', 'Abdoulaye', 'Ousmane', 'Pape', 'Modou', 'Aliou', 'Babacar', 'Lamine', 'Assane', 'Serigne', 'Khadim', 'El Hadji', 'Malick', 'Souleymane', 'Amadou', 'Youssou', 'Saliou', 'Birame', 'Omar', 'Thierno', 'Mbaye', 'Alassane', 'Daouda', 'Idrissa', 'Samba', 'Demba', 'Bassirou' );
	$last  = array( 'Ndiaye', 'Diop', 'Fall', 'Sarr', 'Faye', 'Gueye', 'Sow', 'Ba', 'Diallo', 'Seck', 'Diouf', 'Thiam', 'Cissé', 'Ndour', 'Mbaye', 'Sy', 'Kane', 'Niang', 'Camara', 'Badji', 'Diatta', 'Sané', 'Mendy', 'Gomis', 'Dieng', 'Wade', 'Samb', 'Touré', 'Diagne', 'Lô' );

	$clubs    = array();
	$squads   = array();
	$strength = array();
	$used     = array();
	$layout   = array( 'GK' => 2, 'DF' => 5, 'MF' => 5, 'FW' => 4 );
	foreach ( $clubs_data as $data ) {
		$club_id = wp_insert_post(
			array(
				'post_type'    => 'gsf_club',
				'post_status'  => 'publish',
				'post_title'   => $data[0],
				'post_content' => sprintf( 'Fiche de démonstration du club %s, basé à %s. Remplacez ce texte par l’histoire et le palmarès du club.', $data[0], $data[2] ),
				'meta_input'   => array(
					'_gsf_demo'    => 1,
					'_gsf_short'   => $data[1],
					'_gsf_city'    => $data[2],
					'_gsf_stadium' => $data[3],
					'_gsf_color'   => $data[4],
					'_gsf_color2'  => $data[5],
				),
			)
		);
		$clubs[]              = $club_id;
		$strength[ $club_id ] = $data[6];
		$number               = 1;
		foreach ( $layout as $pos => $count ) {
			for ( $i = 0; $i < $count; $i++ ) {
				do {
					$name = $first[ mt_rand( 0, count( $first ) - 1 ) ] . ' ' . $last[ mt_rand( 0, count( $last ) - 1 ) ];
				} while ( isset( $used[ $name ] ) );
				$used[ $name ] = true;
				$player_id     = wp_insert_post(
					array(
						'post_type'   => 'gsf_player',
						'post_status' => 'publish',
						'post_title'  => $name,
						'meta_input'  => array(
							'_gsf_demo'        => 1,
							'_gsf_club'        => $club_id,
							'_gsf_position'    => $pos,
							'_gsf_number'      => 'GK' === $pos ? ( 0 === $i ? 1 : 16 ) : ++$number,
							'_gsf_birth'       => sprintf( '%d-%02d-%02d', mt_rand( $year - 33, $year - 18 ), mt_rand( 1, 12 ), mt_rand( 1, 28 ) ),
							'_gsf_nationality' => 'Sénégal',
							'_gsf_height'      => 'GK' === $pos ? mt_rand( 184, 196 ) : mt_rand( 168, 192 ),
							'_gsf_foot'        => mt_rand( 0, 9 ) < 7 ? 'R' : 'L',
						),
					)
				);
				$squads[ $club_id ][ $pos ][] = $player_id;
			}
		}
	}

	// Double round robin (circle method).
	$n      = count( $clubs );
	$rounds = array();
	$list   = $clubs;
	for ( $r = 0; $r < $n - 1; $r++ ) {
		$pairs = array();
		for ( $i = 0; $i < $n / 2; $i++ ) {
			$a       = $list[ $i ];
			$b       = $list[ $n - 1 - $i ];
			$pairs[] = ( $r + $i ) % 2 ? array( $a, $b ) : array( $b, $a );
		}
		$rounds[] = $pairs;
		$fixed    = array_shift( $list );
		array_unshift( $list, array_pop( $list ) );
		array_unshift( $list, $fixed );
	}
	foreach ( $rounds as $pairs ) {
		$rounds[] = array_map( 'array_reverse', $pairs );
		if ( count( $rounds ) >= 2 * ( $n - 1 ) ) {
			break;
		}
	}

	$played_rounds = 15;
	$today         = new DateTimeImmutable( 'today', wp_timezone() );
	$weekday       = (int) $today->format( 'N' );
	// Round 16 is played on the coming weekend.
	$next_saturday = $today->modify( '+' . ( ( 6 - $weekday + 7 ) % 7 ) . ' days' );
	$slots         = array( array( 0, '16:30' ), array( 0, '16:30' ), array( 0, '19:00' ), array( 1, '16:30' ), array( 1, '16:30' ), array( 1, '19:00' ), array( 1, '21:00' ) );
	$pick          = static function ( $pool ) {
		return $pool[ mt_rand( 0, count( $pool ) - 1 ) ];
	};
	$count         = 0;

	foreach ( $rounds as $index => $pairs ) {
		$round    = $index + 1;
		$saturday = $next_saturday->modify( ( $round - $played_rounds - 1 ) * 7 . ' days' );
		foreach ( $pairs as $k => $pair ) {
			list( $home, $away ) = $pair;
			$slot                = $slots[ $k % count( $slots ) ];
			$kickoff             = $saturday->modify( '+' . $slot[0] . ' days' )->modify( $slot[1] );
			$played              = $round <= $played_rounds;
			$meta                = array(
				'_gsf_demo'     => 1,
				'_gsf_home'     => $home,
				'_gsf_away'     => $away,
				'_gsf_ts'       => $kickoff->getTimestamp(),
				'_gsf_matchday' => $round,
				'_gsf_status'   => $played ? 'ft' : 'scheduled',
				'_gsf_venue'    => get_post_meta( $home, '_gsf_stadium', true ),
			);
			if ( $played ) {
				$events = array();
				$score  = array( 'home' => 0, 'away' => 0 );
				foreach ( array( 'home' => array( $home, $away, 6 ), 'away' => array( $away, $home, 0 ) ) as $side => $ctx ) {
					// Expected goals from the strength difference plus home advantage.
					$expected = max( 0.3, 1.15 + ( $strength[ $ctx[0] ] - $strength[ $ctx[1] ] + $ctx[2] ) / 22 );
					$goals    = 0;
					for ( $t = 0; $t < 9; $t++ ) {
						$goals += mt_rand( 0, 999 ) / 1000 < $expected / 9 ? 1 : 0;
					}
					$squad = $squads[ $ctx[0] ];
					for ( $g = 0; $g < $goals; $g++ ) {
						$roll   = mt_rand( 0, 99 );
						// The first striker is the club's main goal scorer.
						$scorer = $roll < 34 ? $squad['FW'][0] : ( $roll < 62 ? $pick( $squad['FW'] ) : ( $roll < 90 ? $pick( $squad['MF'] ) : $pick( $squad['DF'] ) ) );
						do {
							$assist = mt_rand( 0, 99 ) < 30 ? 0 : $pick( array_merge( $squad['MF'], $squad['FW'], array( $squad['DF'][0] ) ) );
						} while ( $assist === $scorer );
						$penalty  = mt_rand( 0, 99 ) < 9;
						$events[] = array(
							'minute' => mt_rand( 3, 92 ),
							'type'   => $penalty ? 'penalty' : 'goal',
							'side'   => $side,
							'player' => $scorer,
							'assist' => $penalty ? 0 : $assist,
						);
					}
					$score[ $side ] = $goals;
					$cards          = mt_rand( 0, 3 );
					for ( $c = 0; $c < $cards; $c++ ) {
						$events[] = array(
							'minute' => mt_rand( 12, 90 ),
							'type'   => mt_rand( 0, 99 ) < 7 ? 'red' : 'yellow',
							'side'   => $side,
							'player' => $pick( array_merge( $squad['DF'], $squad['MF'] ) ),
							'assist' => 0,
						);
					}
					// Line-up: first keeper and a rotating selection of outfield players.
					$outfield = array_merge( $squad['DF'], $squad['MF'], $squad['FW'] );
					shuffle( $outfield );
					$lineup = array_merge( array( $squad['GK'][0], $squad['FW'][0] ), array_slice( $outfield, 0, 11 ) );
					foreach ( $events as $event ) {
						if ( $event['side'] === $side ) {
							$lineup[] = $event['player'];
							$lineup[] = $event['assist'];
						}
					}
					$meta[ '_gsf_lineup_' . $side ] = array_values( array_unique( array_filter( $lineup ) ) );
				}
				usort(
					$events,
					static function ( $a, $b ) {
						return $a['minute'] <=> $b['minute'];
					}
				);
				$meta['_gsf_home_score'] = $score['home'];
				$meta['_gsf_away_score'] = $score['away'];
				$meta['_gsf_events']     = $events;
			}
			$match_id = wp_insert_post(
				array(
					'post_type'   => 'gsf_match',
					'post_status' => 'publish',
					'post_title'  => 'match',
					'meta_input'  => $meta,
				)
			);
			wp_set_object_terms( $match_id, array( $comp ), 'gsf_competition' );
			wp_set_object_terms( $match_id, array( $season ), 'gsf_season' );
			gsf_sync_match_title( $match_id );
			++$count;
		}
	}

	// A few sample articles so the home page is not empty.
	$articles = array(
		array( 'Ligue 1 : le leader s’impose dans le choc de la journée', 'Ligue 1' ),
		array( 'Le classement des buteurs s’emballe à mi-saison', 'Ligue 1' ),
		array( 'Lions : la liste des 26 dévoilée avant les éliminatoires', 'Équipe nationale' ),
		array( 'Mercato : trois jeunes talents à suivre cet hiver', 'Mercato' ),
		array( 'Coupe du Sénégal : le tirage au sort des huitièmes', 'Coupe du Sénégal' ),
		array( 'Formation : les académies, moteur du football sénégalais', 'Analyses' ),
		array( 'Derby de Dakar : ce qu’il faut retenir', 'Ligue 1' ),
		array( 'Arbitrage, VAR, calendrier : les chantiers de la saison', 'Analyses' ),
		array( 'Les Lionnes préparent leur prochain rassemblement', 'Équipe nationale' ),
	);
	$body     = '<p>Ceci est un article de démonstration. Remplacez-le par vos propres contenus : comptes rendus, analyses, interviews et actualité du football sénégalais.</p><h2>Le fait du match</h2><p>Le thème met en forme automatiquement les intertitres, les citations et les images, et insère vos emplacements publicitaires au fil de la lecture.</p><blockquote><p>« On a montré du caractère, il faut continuer comme ça. »</p></blockquote><p>Ajoutez une image mise en avant pour illustrer l’article : elle sera utilisée sur la page d’accueil et lors des partages sur les réseaux sociaux.</p><h2>La suite du calendrier</h2><p>Retrouvez le classement, les résultats et les statistiques des joueurs dans les rubriques dédiées.</p>';
	foreach ( $articles as $i => $article ) {
		$term = term_exists( $article[1], 'category' ) ?: wp_insert_term( $article[1], 'category' );
		wp_insert_post(
			array(
				'post_type'     => 'post',
				'post_status'   => 'publish',
				'post_title'    => $article[0],
				'post_content'  => $body,
				'post_excerpt'  => 'Article de démonstration : remplacez ce résumé par le chapô de votre article.',
				'post_date'     => wp_date( 'Y-m-d H:i:s', time() - $i * 7 * HOUR_IN_SECONDS - 1800 ),
				'post_category' => is_wp_error( $term ) ? array() : array( (int) $term['term_id'] ),
				'meta_input'    => array( '_gsf_demo' => 1 ),
			)
		);
	}

	wp_suspend_cache_invalidation( false );
	wp_defer_term_counting( false );
	wp_cache_flush();

	$settings                = gsf_settings();
	$settings['competition'] = $comp;
	$settings['season']      = $season;
	update_option( 'gsf_settings', $settings );
	gsf_flush_cache();
	flush_rewrite_rules( false );

	return sprintf( /* translators: 1: clubs 2: matches */ __( 'Démo installée : %1$d clubs, %2$d matchs, joueurs et articles créés.', 'galsenfoot-league' ), count( $clubs ), $count );
}

/**
 * Remove every demo item.
 *
 * @return string Notice.
 */
function gsf_demo_remove() {
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
	$ids = get_posts(
		array(
			'post_type'      => array( 'gsf_club', 'gsf_player', 'gsf_match', 'post' ),
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => '_gsf_demo', // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	foreach ( $ids as $id ) {
		wp_delete_post( $id, true );
	}
	foreach ( array( 'gsf_competition', 'gsf_season' ) as $taxonomy ) {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'meta_key'   => '_gsf_demo', // phpcs:ignore WordPress.DB.SlowDBQuery
				'fields'     => 'ids',
			)
		);
		foreach ( (array) $terms as $term_id ) {
			wp_delete_term( (int) $term_id, $taxonomy );
		}
	}
	gsf_flush_cache();
	return sprintf( /* translators: %d items */ __( 'Démo supprimée (%d éléments).', 'galsenfoot-league' ), count( $ids ) );
}

<?php
/**
 * Schema.org structured data for clubs, players and matches (rich results).
 *
 * @package GalsenFootLeague
 */

defined( 'ABSPATH' ) || exit;

/**
 * JSON-LD graph node for the current GSF singular view.
 *
 * @return array|null
 */
function gsf_schema_node() {
	if ( ! is_singular( array( 'gsf_club', 'gsf_player', 'gsf_match' ) ) ) {
		return null;
	}
	$id   = get_queried_object_id();
	$team = static function ( $club_id ) {
		$club = gsf_club( $club_id );
		$node = array(
			'@type' => 'SportsTeam',
			'name'  => $club['name'],
			'sport' => 'Football',
		);
		if ( $club['url'] ) {
			$node['url'] = $club['url'];
		}
		if ( $club['id'] && has_post_thumbnail( $club['id'] ) ) {
			$node['logo'] = get_the_post_thumbnail_url( $club['id'], 'medium' );
		}
		return $node;
	};

	switch ( get_post_type( $id ) ) {
		case 'gsf_club':
			$node    = $team( $id );
			$stadium = get_post_meta( $id, '_gsf_stadium', true );
			$coach   = get_post_meta( $id, '_gsf_coach', true );
			$founded = get_post_meta( $id, '_gsf_founded', true );
			if ( $stadium ) {
				$node['location'] = array(
					'@type' => 'StadiumOrArena',
					'name'  => $stadium,
				);
			}
			if ( $coach ) {
				$node['coach'] = array(
					'@type' => 'Person',
					'name'  => $coach,
				);
			}
			if ( $founded ) {
				$node['foundingDate'] = (string) $founded;
			}
			$athletes = array();
			foreach ( gsf_club_players( $id ) as $player ) {
				$athletes[] = array(
					'@type' => 'Person',
					'name'  => get_the_title( $player ),
					'url'   => get_permalink( $player ),
				);
			}
			if ( $athletes ) {
				$node['athlete'] = $athletes;
			}
			break;

		case 'gsf_player':
			$node = array(
				'@type' => 'Person',
				'name'  => get_the_title( $id ),
				'url'   => get_permalink( $id ),
			);
			$positions = gsf_positions();
			$pos       = get_post_meta( $id, '_gsf_position', true );
			$node['jobTitle'] = isset( $positions[ $pos ] ) ? $positions[ $pos ] : __( 'Footballeur', 'galsenfoot-league' );
			foreach ( array( 'birthDate' => '_gsf_birth', 'nationality' => '_gsf_nationality' ) as $prop => $key ) {
				$value = get_post_meta( $id, $key, true );
				if ( $value ) {
					$node[ $prop ] = $value;
				}
			}
			$height = (int) get_post_meta( $id, '_gsf_height', true );
			if ( $height ) {
				$node['height'] = $height . ' cm';
			}
			if ( has_post_thumbnail( $id ) ) {
				$node['image'] = get_the_post_thumbnail_url( $id, 'large' );
			}
			$club_id = (int) get_post_meta( $id, '_gsf_club', true );
			if ( $club_id ) {
				$node['memberOf'] = $team( $club_id );
			}
			break;

		default:
			$m    = gsf_match( $id );
			$map  = array(
				'postponed' => 'https://schema.org/EventPostponed',
				'cancelled' => 'https://schema.org/EventCancelled',
			);
			$node = array(
				'@type'       => 'SportsEvent',
				'name'        => get_the_title( $id ),
				'url'         => $m['url'],
				'sport'       => 'Football',
				'homeTeam'    => $team( $m['home'] ),
				'awayTeam'    => $team( $m['away'] ),
				'eventStatus' => $map[ $m['status'] ] ?? 'https://schema.org/EventScheduled',
			);
			if ( $m['ts'] ) {
				$node['startDate'] = wp_date( 'c', $m['ts'] );
			}
			if ( $m['venue'] ) {
				$node['location'] = array(
					'@type' => 'StadiumOrArena',
					'name'  => $m['venue'],
				);
			}
			$comp = get_the_terms( $id, 'gsf_competition' );
			if ( $comp && ! is_wp_error( $comp ) ) {
				$node['superEvent'] = array(
					'@type' => 'SportsEvent',
					'name'  => $comp[0]->name,
				);
			}
			if ( $m['has_score'] ) {
				$node['description'] = sprintf( '%s %d – %d %s', gsf_club( $m['home'] )['name'], $m['home_score'], $m['away_score'], gsf_club( $m['away'] )['name'] );
			}
	}
	return $node;
}

add_action(
	'wp_head',
	static function () {
		$node = gsf_schema_node();
		if ( ! $node ) {
			return;
		}
		$node = array( '@context' => 'https://schema.org' ) + $node;
		echo '<script type="application/ld+json">' . wp_json_encode( $node, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	},
	20
);

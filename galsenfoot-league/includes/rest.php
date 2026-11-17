<?php
/**
 * Public read-only REST API: /wp-json/gsf/v1/…
 *
 * @package GalsenFootLeague
 */

defined( 'ABSPATH' ) || exit;

/**
 * Compact match payload.
 *
 * @param int $id Match ID.
 * @return array
 */
function gsf_rest_match( $id ) {
	$m = gsf_match( $id );
	return array(
		'id'         => $m['id'],
		'url'        => $m['url'],
		'status'     => $m['status'],
		'minute'     => $m['minute'],
		'date'       => $m['ts'] ? wp_date( 'c', $m['ts'] ) : null,
		'matchday'   => $m['matchday'],
		'venue'      => $m['venue'],
		'home'       => array(
			'id'    => $m['home'],
			'name'  => gsf_club( $m['home'] )['name'],
			'score' => $m['has_score'] ? $m['home_score'] : null,
		),
		'away'       => array(
			'id'    => $m['away'],
			'name'  => gsf_club( $m['away'] )['name'],
			'score' => $m['has_score'] ? $m['away_score'] : null,
		),
		'centerHtml' => gsf_match_center( $m ),
	);
}

add_action(
	'rest_api_init',
	static function () {
		$term_args = array(
			'competition' => array( 'sanitize_callback' => 'sanitize_text_field' ),
			'season'      => array( 'sanitize_callback' => 'sanitize_text_field' ),
		);

		register_rest_route(
			'gsf/v1',
			'/standings',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'args'                => $term_args,
				'callback'            => static function ( WP_REST_Request $request ) {
					$rows = gsf_get_standings(
						gsf_resolve_term( (string) $request['competition'], 'gsf_competition' ),
						gsf_resolve_term( (string) $request['season'], 'gsf_season' )
					);
					return rest_ensure_response( $rows );
				},
			)
		);

		register_rest_route(
			'gsf/v1',
			'/matches',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'args'                => $term_args + array(
					'type'  => array(
						'default' => 'fixtures',
						'enum'    => array( 'fixtures', 'results', 'live', 'all' ),
					),
					'limit' => array(
						'default'           => 10,
						'sanitize_callback' => 'absint',
					),
					'club'  => array( 'sanitize_callback' => 'absint' ),
					'ids'   => array( 'sanitize_callback' => 'sanitize_text_field' ),
				),
				'callback'            => static function ( WP_REST_Request $request ) {
					if ( $request['ids'] ) {
						// Live refresh of the matches displayed on a page.
						$ids = array_slice( array_filter( array_map( 'absint', explode( ',', $request['ids'] ) ) ), 0, 40 );
						$ids = array_filter(
							$ids,
							static function ( $id ) {
								return 'gsf_match' === get_post_type( $id ) && 'publish' === get_post_status( $id );
							}
						);
					} else {
						$ids = gsf_matches_for(
							array(
								'competition' => gsf_resolve_term( (string) $request['competition'], 'gsf_competition' ),
								'season'      => gsf_resolve_term( (string) $request['season'], 'gsf_season' ),
								'type'        => $request['type'],
								'limit'       => min( 100, max( 1, (int) $request['limit'] ) ),
								'club'        => (int) $request['club'],
							)
						);
					}
					$response = rest_ensure_response( array_map( 'gsf_rest_match', array_values( $ids ) ) );
					$response->header( 'Cache-Control', 'public, max-age=30' );
					return $response;
				},
			)
		);

		register_rest_route(
			'gsf/v1',
			'/leaders',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'args'                => $term_args + array(
					'stat'  => array(
						'default' => 'goals',
						'enum'    => array( 'goals', 'assists', 'yellow', 'red', 'apps' ),
					),
					'limit' => array(
						'default'           => 10,
						'sanitize_callback' => 'absint',
					),
				),
				'callback'            => static function ( WP_REST_Request $request ) {
					$rows = gsf_get_leaders(
						$request['stat'],
						gsf_resolve_term( (string) $request['competition'], 'gsf_competition' ),
						gsf_resolve_term( (string) $request['season'], 'gsf_season' ),
						min( 100, max( 1, (int) $request['limit'] ) )
					);
					return rest_ensure_response( $rows );
				},
			)
		);
	}
);

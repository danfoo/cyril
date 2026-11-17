<?php
/**
 * Shortcodes.
 *
 * [gsf_standings] [gsf_matches type="fixtures|results|live|all"] [gsf_ticker]
 * [gsf_next_match] [gsf_match_center] [gsf_scorers stat="goals|assists|yellow|red|apps"] [gsf_clubs] [gsf_squad club="12"]
 *
 * Common attributes: competition (ID or slug), season (ID or slug), title, limit.
 *
 * @package GalsenFootLeague
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	static function () {
		$map = array(
			'gsf_standings'  => 'gsf_render_standings',
			'gsf_matches'    => 'gsf_render_matches',
			'gsf_ticker'     => 'gsf_render_ticker',
			'gsf_next_match' => 'gsf_render_next_match',
			'gsf_match_center' => 'gsf_render_match_center',
			'gsf_scorers'    => 'gsf_render_leaders',
			'gsf_clubs'      => 'gsf_render_clubs',
			'gsf_squad'      => 'gsf_render_squad',
		);
		foreach ( $map as $tag => $callback ) {
			add_shortcode(
				$tag,
				static function ( $atts ) use ( $callback, $tag ) {
					return '<div class="gsf gsf-sc-' . esc_attr( str_replace( 'gsf_', '', $tag ) ) . '">' . call_user_func( $callback, (array) $atts ) . '</div>';
				}
			);
		}
	}
);

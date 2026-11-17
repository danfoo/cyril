<?php
/**
 * Archives of clubs, players and matches.
 *
 * @package GalsenFoot
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'gsf_render_clubs' ) ) {
	return;
}

if ( is_post_type_archive( 'gsf_club' ) ) {
	echo gf_league( 'gsf_render_clubs', array( 'scope' => 'all' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	return;
}

if ( is_post_type_archive( 'gsf_player' ) ) {
	if ( ! is_paged() ) {
		echo '<div class="gf-cols-2">';
		echo '<div>' . gf_league( 'gsf_render_leaders', array( 'limit' => 5, 'title' => __( 'Meilleurs buteurs', 'galsenfoot' ) ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div>' . gf_league( 'gsf_render_leaders', array( 'limit' => 5, 'stat' => 'assists', 'title' => __( 'Meilleurs passeurs', 'galsenfoot' ) ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</div>';
		echo gf_heading( __( 'Tous les joueurs', 'galsenfoot' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '<div class="gsf"><ul class="gsf-squad__list">';
	while ( have_posts() ) {
		the_post();
		$gf_club = gsf_club( (int) get_post_meta( get_the_ID(), '_gsf_club', true ) );
		$gf_num  = get_post_meta( get_the_ID(), '_gsf_number', true );
		printf(
			'<li><a class="gsf-player-card" href="%s"><span class="gsf-player-card__num">%s</span>%s<span class="gsf-player-card__name">%s</span><span class="gsf-player-card__stats">%s</span></a></li>',
			esc_url( get_permalink() ),
			'' !== $gf_num ? (int) $gf_num : '–',
			gsf_player_photo( get_the_ID() ), // phpcs:ignore WordPress.Security.EscapeOutput
			esc_html( get_the_title() ),
			esc_html( $gf_club['id'] ? $gf_club['name'] : '' )
		);
	}
	echo '</ul></div>';
	gf_pagination();
	return;
}

// Matches (archive, competition or season).
if ( have_posts() ) {
	echo '<div class="gsf"><div class="gsf-matches"><div class="gsf-match-group">';
	while ( have_posts() ) {
		the_post();
		echo gsf_match_row( get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</div></div></div>';
	gf_pagination();
} else {
	echo '<p class="gf-empty">' . esc_html__( 'Aucun match pour le moment.', 'galsenfoot' ) . '</p>';
}

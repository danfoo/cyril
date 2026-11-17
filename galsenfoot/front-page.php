<?php
/**
 * Front page. A static page with content (Elementor or editor) is rendered as is;
 * otherwise the theme composes a full football home page.
 *
 * @package GalsenFoot
 */

defined( 'ABSPATH' ) || exit;

if ( is_home() && is_paged() ) {
	require GF_DIR . '/index.php';
	return;
}

get_header();

$gf_custom = false;
if ( 'page' === get_option( 'show_on_front' ) && have_posts() ) {
	the_post();
	if ( gf_is_elementor() ) {
		$gf_custom = true;
		echo '<h1 class="screen-reader-text">' . esc_html( get_bloginfo( 'name' ) ) . ( get_bloginfo( 'description' ) ? ' — ' . esc_html( get_bloginfo( 'description' ) ) : '' ) . '</h1>';
		the_content();
	} elseif ( '' !== trim( get_post_field( 'post_content', get_the_ID() ) ) ) {
		$gf_custom = true;
		echo '<h1 class="screen-reader-text">' . esc_html( get_bloginfo( 'name' ) ) . '</h1>';
		echo '<div class="gf-container gf-section"><div class="gf-prose gf-prose--wide">';
		the_content();
		echo '</div></div>';
	}
	rewind_posts();
}

if ( ! $gf_custom ) {
	get_template_part( 'template-parts/home' );
}

get_footer();

<?php
/**
 * One-page wedding front page.
 *
 * If "Settings → Reading" shows a static page, its content is displayed
 * after the sections (handy for extra blocks).
 *
 * @package Eternelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
get_header();

get_template_part( 'template-parts/hero' );

foreach ( array_keys( eternelle_sections() ) as $section ) {
	get_template_part( 'template-parts/sections/' . $section );
}

if ( 'page' === get_option( 'show_on_front' ) ) {
	while ( have_posts() ) {
		the_post();
		if ( '' !== trim( get_the_content() ) ) {
			echo '<section class="et-section et-extra"><div class="et-container et-container--narrow et-prose">';
			the_content();
			echo '</div></section>';
		}
	}
}

get_footer();

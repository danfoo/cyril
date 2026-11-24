<?php
/**
 * Front page.
 *
 * Depending on Customizer → Couleurs & sections → « Page d'accueil »:
 * theme sections, Elementor content, or a mix of both.
 *
 * @package Eternelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
get_header();

$mode = eternelle_front_mode();

if ( in_array( $mode, array( 'theme', 'hero_elementor', 'theme_elementor' ), true ) ) {
	get_template_part( 'template-parts/hero' );
}

if ( in_array( $mode, array( 'theme', 'theme_elementor' ), true ) ) {
	foreach ( array_keys( eternelle_sections() ) as $section ) {
		if ( 'wishes' !== $section ) {
			get_template_part( 'template-parts/sections/' . $section );
		}
	}
}

if ( 'page' === get_option( 'show_on_front' ) ) {
	while ( have_posts() ) {
		the_post();
		if ( 'theme' === $mode ) {
			// Classic editor / blocks content after the sections.
			if ( '' !== trim( get_the_content() ) ) {
				echo '<section class="et-section et-extra"><div class="et-container et-container--narrow et-prose">';
				the_content();
				echo '</div></section>';
			}
		} else {
			echo '<div class="et-elementor-content">';
			the_content();
			echo '</div>';
		}
	}
}

// Latest guest-book wishes: always last, whatever the front-page mode.
if ( array_key_exists( 'wishes', eternelle_sections() ) ) {
	get_template_part( 'template-parts/sections/wishes' );
}

get_footer();

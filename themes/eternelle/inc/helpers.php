<?php
/**
 * Template helpers.
 *
 * @package Eternelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * All Customizer defaults (sample content so the site looks finished right away).
 */
function eternelle_defaults() {
	static $d = null;
	if ( null !== $d ) {
		return $d;
	}
	$d = array(
		// Couple.
		'et_partner_a'      => 'Sarah',
		'et_partner_b'      => 'Cyril',
		'et_bio_a'          => 'Rêveuse, passionnée de voyages et de pâtisserie, elle a trouvé en lui son plus beau voyage.',
		'et_bio_b'          => 'Amoureux de musique et de grands espaces, il a su la faire rire dès le premier jour.',
		'et_photo_a'        => '',
		'et_photo_b'        => '',
		'et_couple_title'   => 'Les mariés',
		'et_couple_intro'   => 'Deux chemins, une seule histoire.',

		// Wedding.
		'et_date'           => gmdate( 'Y-m-d', strtotime( '+6 months' ) ),
		'et_time'           => '15:00',
		'et_venue'          => 'Domaine des Roses',
		'et_address'        => '12 allée des Tilleuls, 33000 Bordeaux',
		'et_map_url'        => '',
		'et_map_embed'      => '',
		'et_dress_code'     => 'Chic & champêtre – touches de rose poudré bienvenues',

		// Hero.
		'et_hero_image'     => '',
		'et_hero_kicker'    => 'Nous nous marions',
		'et_hero_quote'     => 'Et c’est ainsi que commence notre pour toujours',
		'et_hero_cta'       => 'Confirmer ma présence',
		'et_envelope'       => 1,
		'et_envelope_text'  => 'Vous avez reçu une invitation',
		'et_music'          => '',
		'et_fx_petals'      => 1,
		'et_fx_sparkles'    => 1,
		'et_fx_cursor'      => 1,
		'et_fx_hearts'      => 1,

		// Story.
		'et_story_title'    => 'Notre histoire',
		'et_story_1_date'   => 'Été 2019',
		'et_story_1_title'  => 'La rencontre',
		'et_story_1_text'   => 'Un mariage d’amis, une piste de danse et un fou rire partagé. Le début de tout.',
		'et_story_2_date'   => 'Printemps 2020',
		'et_story_2_title'  => 'Premier rendez-vous',
		'et_story_2_text'   => 'Un pique-nique au bord de l’eau qui a duré jusqu’au coucher du soleil.',
		'et_story_3_date'   => 'Hiver 2022',
		'et_story_3_title'  => 'Notre nid',
		'et_story_3_text'   => 'Un petit appartement, beaucoup de plantes et encore plus de rêves.',
		'et_story_4_date'   => 'Décembre 2024',
		'et_story_4_title'  => 'La demande',
		'et_story_4_text'   => 'Sous la neige, un genou à terre… et un « oui » sans hésiter !',

		// Programme.
		'et_program_title'  => 'Le programme',
		'et_prog_1_time'    => '15:00',
		'et_prog_1_title'   => 'Cérémonie',
		'et_prog_1_text'    => 'Échange des vœux dans les jardins',
		'et_prog_1_icon'    => 'rings',
		'et_prog_2_time'    => '16:30',
		'et_prog_2_title'   => 'Vin d’honneur',
		'et_prog_2_text'    => 'Cocktail & photos sous les tilleuls',
		'et_prog_2_icon'    => 'glasses',
		'et_prog_3_time'    => '19:30',
		'et_prog_3_title'   => 'Dîner',
		'et_prog_3_text'    => 'Dîner aux chandelles dans l’orangerie',
		'et_prog_3_icon'    => 'dinner',
		'et_prog_4_time'    => '22:00',
		'et_prog_4_title'   => 'Soirée dansante',
		'et_prog_4_text'    => 'Ouverture du bal et piste jusqu’au bout de la nuit',
		'et_prog_4_icon'    => 'music',
		'et_prog_5_time'    => '',
		'et_prog_5_title'   => '',
		'et_prog_5_text'    => '',
		'et_prog_5_icon'    => 'cake',

		// Venue.
		'et_venue_title'    => 'Le lieu',
		'et_venue_text'     => 'Parking sur place. Des navettes seront organisées depuis le centre-ville à 14h15.',
		'et_stay_text'      => 'Quelques hôtels et gîtes à proximité : Hôtel du Lac (5 min), Gîte des Vignes (10 min).',

		// Gallery.
		'et_gallery_title'  => 'Nos plus beaux souvenirs',

		// RSVP.
		'et_rsvp_title'     => 'Serez-vous des nôtres ?',
		'et_rsvp_text'      => 'Chaque invité a reçu un lien personnel par e-mail, WhatsApp ou SMS pour confirmer sa présence. Vous ne le retrouvez plus ? Indiquez votre e-mail ci-dessous, nous vous le renvoyons.',
		'et_contact'        => '',

		// Gifts.
		'et_gifts_title'    => 'Liste de mariage',
		'et_gifts_text'     => 'Votre présence est notre plus beau cadeau. Si vous souhaitez nous gâter, une urne sera présente le jour J, et notre cagnotte voyage est en ligne.',
		'et_gifts_url'      => '',
		'et_gifts_button'   => 'Participer à notre voyage de noces',

		// Footer.
		'et_footer_text'    => 'Merci de faire partie de notre histoire',
		'et_hashtag'        => '',

		// Colours.
		'et_color_gold'     => '#b8925a',
		'et_color_blush'    => '#f4e1dc',
		'et_color_ink'      => '#2e2420',
		'et_color_sage'     => '#a3b18a',

		// Front page.
		'et_front_mode'     => 'auto',

		// Sections.
		'et_show_couple'    => 1,
		'et_show_story'     => 1,
		'et_show_program'   => 1,
		'et_show_venue'     => 1,
		'et_show_gallery'   => 1,
		'et_show_rsvp'      => 1,
		'et_show_gifts'     => 1,
		'et_show_wishes'    => 1,
		'et_wishes_title'   => 'Vos plus beaux vœux',
		'et_wishes_count'   => 12,
	);
	for ( $i = 1; $i <= 4; $i++ ) {
		$d[ 'et_story_' . $i . '_image' ] = '';
	}
	for ( $i = 1; $i <= 12; $i++ ) {
		$d[ 'et_gallery_' . $i ] = '';
	}
	return $d;
}

/**
 * Get a theme mod with our default.
 *
 * @param string $key Theme mod.
 */
function eternelle_mod( $key ) {
	$d = eternelle_defaults();
	return get_theme_mod( $key, isset( $d[ $key ] ) ? $d[ $key ] : '' );
}

/**
 * Wedding timestamp (site timezone).
 */
function eternelle_timestamp() {
	try {
		$dt = new DateTime( eternelle_mod( 'et_date' ) . ' ' . ( eternelle_mod( 'et_time' ) ? eternelle_mod( 'et_time' ) : '00:00' ), wp_timezone() );
		return $dt->getTimestamp();
	} catch ( Exception $e ) {
		return time();
	}
}

function eternelle_date( $format = 'l j F Y' ) {
	return wp_date( $format, eternelle_timestamp() );
}

function eternelle_time() {
	return wp_date( 'H\hi', eternelle_timestamp() );
}

function eternelle_initials() {
	$a = trim( eternelle_mod( 'et_partner_a' ) );
	$b = trim( eternelle_mod( 'et_partner_b' ) );
	return array(
		'a' => $a ? mb_strtoupper( mb_substr( $a, 0, 1 ) ) : 'A',
		'b' => $b ? mb_strtoupper( mb_substr( $b, 0, 1 ) ) : 'B',
	);
}

/**
 * Image URL from an attachment-ID theme mod.
 *
 * @param string $key  Theme mod.
 * @param string $size Image size.
 */
function eternelle_image_url( $key, $size = 'large' ) {
	$val = eternelle_mod( $key );
	if ( ! $val ) {
		return '';
	}
	if ( is_numeric( $val ) ) {
		$src = wp_get_attachment_image_url( (int) $val, $size );
		return $src ? $src : '';
	}
	return esc_url_raw( $val );
}

function eternelle_image_alt( $key ) {
	$val = eternelle_mod( $key );
	if ( is_numeric( $val ) ) {
		return (string) get_post_meta( (int) $val, '_wp_attachment_image_alt', true );
	}
	return '';
}

/**
 * Is the Wedding RSVP Pass plugin active?
 */
function eternelle_has_rsvp_plugin() {
	return class_exists( 'WRP_Settings' );
}

function eternelle_story_items() {
	$items = array();
	for ( $i = 1; $i <= 4; $i++ ) {
		$title = eternelle_mod( 'et_story_' . $i . '_title' );
		if ( ! $title ) {
			continue;
		}
		$items[] = array(
			'date'  => eternelle_mod( 'et_story_' . $i . '_date' ),
			'title' => $title,
			'text'  => eternelle_mod( 'et_story_' . $i . '_text' ),
			'image' => eternelle_image_url( 'et_story_' . $i . '_image', 'medium_large' ),
		);
	}
	return $items;
}

function eternelle_program_items() {
	$items = array();
	for ( $i = 1; $i <= 5; $i++ ) {
		$title = eternelle_mod( 'et_prog_' . $i . '_title' );
		if ( ! $title ) {
			continue;
		}
		$items[] = array(
			'time'  => eternelle_mod( 'et_prog_' . $i . '_time' ),
			'title' => $title,
			'text'  => eternelle_mod( 'et_prog_' . $i . '_text' ),
			'icon'  => eternelle_mod( 'et_prog_' . $i . '_icon' ),
		);
	}
	return $items;
}

function eternelle_gallery_images() {
	$imgs = array();
	for ( $i = 1; $i <= 12; $i++ ) {
		$full = eternelle_image_url( 'et_gallery_' . $i, 'full' );
		if ( $full ) {
			$imgs[] = array(
				'full'  => $full,
				'thumb' => eternelle_image_url( 'et_gallery_' . $i, 'medium_large' ),
				'alt'   => eternelle_image_alt( 'et_gallery_' . $i ),
			);
		}
	}
	return $imgs;
}

/**
 * Sections of the one-page front page, in order.
 */
function eternelle_sections() {
	$all = array(
		'couple'  => __( 'Les mariés', 'eternelle' ),
		'story'   => __( 'Notre histoire', 'eternelle' ),
		'program' => __( 'Programme', 'eternelle' ),
		'venue'   => __( 'Le lieu', 'eternelle' ),
		'gallery' => __( 'Galerie', 'eternelle' ),
		'rsvp'    => __( 'RSVP', 'eternelle' ),
		'gifts'   => __( 'Liste', 'eternelle' ),
		'wishes'  => __( 'Livre d’or', 'eternelle' ),
	);
	$out = array();
	foreach ( $all as $key => $label ) {
		if ( ! eternelle_mod( 'et_show_' . $key ) ) {
			continue;
		}
		if ( 'gallery' === $key && ! eternelle_gallery_images() && ! is_customize_preview() ) {
			continue;
		}
		if ( 'wishes' === $key && ! shortcode_exists( 'wrp_wishes_carousel' ) ) {
			continue;
		}
		$out[ $key ] = $label;
	}
	return apply_filters( 'eternelle_sections', $out );
}

/**
 * Menu fallback: anchors to the one-page sections.
 */
function eternelle_menu_fallback() {
	$base = is_front_page() ? '' : home_url( '/' );
	echo '<ul class="et-menu">';
	foreach ( eternelle_sections() as $key => $label ) {
		// The guest book has its own page.
		if ( 'wishes' === $key && class_exists( 'WRP_Wishes' ) && WRP_Wishes::page_url() ) {
			printf( '<li><a href="%1$s">%2$s</a></li>', esc_url( WRP_Wishes::page_url() ), esc_html( $label ) );
			continue;
		}
		printf( '<li><a href="%1$s#%2$s">%3$s</a></li>', esc_url( $base ), esc_attr( $key ), esc_html( $label ) );
	}
	echo '</ul>';
}

/**
 * Decorative section title.
 *
 * @param string $title Title.
 * @param string $kicker Small text above.
 */
function eternelle_section_title( $title, $kicker = '' ) {
	echo '<header class="et-title" data-reveal>';
	if ( $kicker ) {
		echo '<p class="et-kicker">' . esc_html( $kicker ) . '</p>';
	}
	echo '<h2 class="et-title__h">' . esc_html( $title ) . '</h2>';
	echo eternelle_icon( 'flourish', 'et-title__flourish' ); // phpcs:ignore
	echo '</header>';
}

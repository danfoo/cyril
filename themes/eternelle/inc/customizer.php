<?php
/**
 * Customizer: everything the couple needs, no code required.
 *
 * @package Eternelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function eternelle_sanitize_checkbox( $v ) {
	return $v ? 1 : 0;
}

function eternelle_sanitize_date( $v ) {
	return preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $v ) ? $v : '';
}

function eternelle_sanitize_time( $v ) {
	return preg_match( '/^\d{2}:\d{2}$/', (string) $v ) ? $v : '';
}

function eternelle_sanitize_icon( $v ) {
	return array_key_exists( $v, eternelle_icon_list() ) ? $v : 'heart';
}

function eternelle_sanitize_media( $v ) {
	return is_numeric( $v ) ? absint( $v ) : esc_url_raw( $v );
}

/**
 * Register a setting + control in one call.
 *
 * @param WP_Customize_Manager $wp      Manager.
 * @param string               $section Section ID.
 * @param string               $id      Setting ID.
 * @param string               $label   Label.
 * @param string               $type    text|textarea|checkbox|date|time|url|email|image|audio|color|icon.
 * @param string               $desc    Description.
 */
function eternelle_add( $wp, $section, $id, $label, $type = 'text', $desc = '' ) {
	$defaults = eternelle_defaults();
	$sanitize = array(
		'text'     => 'sanitize_text_field',
		'textarea' => 'sanitize_textarea_field',
		'checkbox' => 'eternelle_sanitize_checkbox',
		'date'     => 'eternelle_sanitize_date',
		'time'     => 'eternelle_sanitize_time',
		'url'      => 'esc_url_raw',
		'email'    => 'sanitize_email',
		'image'    => 'eternelle_sanitize_media',
		'audio'    => 'eternelle_sanitize_media',
		'color'    => 'sanitize_hex_color',
		'icon'     => 'eternelle_sanitize_icon',
	);
	$wp->add_setting(
		$id,
		array(
			'default'           => isset( $defaults[ $id ] ) ? $defaults[ $id ] : '',
			'sanitize_callback' => isset( $sanitize[ $type ] ) ? $sanitize[ $type ] : 'sanitize_text_field',
			'transport'         => 'refresh',
		)
	);
	$args = array(
		'label'       => $label,
		'section'     => $section,
		'description' => $desc,
	);
	switch ( $type ) {
		case 'image':
			$wp->add_control( new WP_Customize_Media_Control( $wp, $id, array_merge( $args, array( 'mime_type' => 'image' ) ) ) );
			break;
		case 'audio':
			$wp->add_control( new WP_Customize_Media_Control( $wp, $id, array_merge( $args, array( 'mime_type' => 'audio' ) ) ) );
			break;
		case 'color':
			$wp->add_control( new WP_Customize_Color_Control( $wp, $id, $args ) );
			break;
		case 'icon':
			$wp->add_control( $id, array_merge( $args, array( 'type' => 'select', 'choices' => eternelle_icon_list() ) ) );
			break;
		default:
			$wp->add_control( $id, array_merge( $args, array( 'type' => $type ) ) );
	}
}

function eternelle_customize_register( WP_Customize_Manager $wp ) {
	$wp->add_panel(
		'eternelle',
		array(
			'title'       => __( '💍 Notre mariage', 'eternelle' ),
			'priority'    => 20,
			'description' => __( 'Personnalisez votre site de mariage. Les informations des mariés, la date et le lieu sont aussi utilisées par le plugin Wedding RSVP Pass (cartons d’invitation).', 'eternelle' ),
		)
	);

	$sections = array(
		'et_s_couple'  => __( 'Les mariés', 'eternelle' ),
		'et_s_day'     => __( 'Date & lieu', 'eternelle' ),
		'et_s_hero'    => __( 'Accueil & effets magiques', 'eternelle' ),
		'et_s_story'   => __( 'Notre histoire', 'eternelle' ),
		'et_s_program' => __( 'Programme', 'eternelle' ),
		'et_s_gallery' => __( 'Galerie photos', 'eternelle' ),
		'et_s_rsvp'    => __( 'Réponses & liste de mariage', 'eternelle' ),
		'et_s_style'   => __( 'Couleurs & sections', 'eternelle' ),
	);
	$p = 10;
	foreach ( $sections as $id => $title ) {
		$wp->add_section(
			$id,
			array(
				'title'    => $title,
				'panel'    => 'eternelle',
				'priority' => $p += 10,
			)
		);
	}

	// Couple.
	eternelle_add( $wp, 'et_s_couple', 'et_partner_a', __( 'Prénom (1)', 'eternelle' ) );
	eternelle_add( $wp, 'et_s_couple', 'et_photo_a', __( 'Photo (1)', 'eternelle' ), 'image' );
	eternelle_add( $wp, 'et_s_couple', 'et_bio_a', __( 'Quelques mots (1)', 'eternelle' ), 'textarea' );
	eternelle_add( $wp, 'et_s_couple', 'et_partner_b', __( 'Prénom (2)', 'eternelle' ) );
	eternelle_add( $wp, 'et_s_couple', 'et_photo_b', __( 'Photo (2)', 'eternelle' ), 'image' );
	eternelle_add( $wp, 'et_s_couple', 'et_bio_b', __( 'Quelques mots (2)', 'eternelle' ), 'textarea' );
	eternelle_add( $wp, 'et_s_couple', 'et_couple_title', __( 'Titre de la section', 'eternelle' ) );
	eternelle_add( $wp, 'et_s_couple', 'et_couple_intro', __( 'Sous-titre', 'eternelle' ) );

	// Day.
	eternelle_add( $wp, 'et_s_day', 'et_date', __( 'Date du mariage', 'eternelle' ), 'date' );
	eternelle_add( $wp, 'et_s_day', 'et_time', __( 'Heure', 'eternelle' ), 'time' );
	eternelle_add( $wp, 'et_s_day', 'et_venue', __( 'Lieu de réception', 'eternelle' ) );
	eternelle_add( $wp, 'et_s_day', 'et_address', __( 'Adresse', 'eternelle' ) );
	eternelle_add( $wp, 'et_s_day', 'et_map_url', __( 'Lien itinéraire (Google Maps, Waze…)', 'eternelle' ), 'url' );
	eternelle_add( $wp, 'et_s_day', 'et_map_embed', __( 'Carte intégrée (URL « src » de l’iframe Google Maps)', 'eternelle' ), 'url', __( 'Google Maps → Partager → Intégrer une carte → copiez uniquement l’adresse entre les guillemets de src="…".', 'eternelle' ) );
	eternelle_add( $wp, 'et_s_day', 'et_dress_code', __( 'Dress code', 'eternelle' ) );
	eternelle_add( $wp, 'et_s_day', 'et_venue_title', __( 'Titre de la section « Lieu »', 'eternelle' ) );
	eternelle_add( $wp, 'et_s_day', 'et_venue_text', __( 'Accès / parking / navettes', 'eternelle' ), 'textarea' );
	eternelle_add( $wp, 'et_s_day', 'et_stay_text', __( 'Hébergements', 'eternelle' ), 'textarea' );

	// Hero & effects.
	eternelle_add( $wp, 'et_s_hero', 'et_hero_image', __( 'Photo de couverture', 'eternelle' ), 'image', __( 'Idéalement 2000 px de large, plutôt sombre ou lumineuse au centre.', 'eternelle' ) );
	eternelle_add( $wp, 'et_s_hero', 'et_hero_kicker', __( 'Petit texte au-dessus des prénoms', 'eternelle' ) );
	eternelle_add( $wp, 'et_s_hero', 'et_hero_quote', __( 'Phrase sous la date', 'eternelle' ) );
	eternelle_add( $wp, 'et_s_hero', 'et_hero_cta', __( 'Texte du bouton', 'eternelle' ) );
	eternelle_add( $wp, 'et_s_hero', 'et_envelope', __( 'Intro « enveloppe » avec sceau de cire', 'eternelle' ), 'checkbox' );
	eternelle_add( $wp, 'et_s_hero', 'et_envelope_text', __( 'Texte de l’enveloppe', 'eternelle' ) );
	eternelle_add( $wp, 'et_s_hero', 'et_music', __( 'Musique d’ambiance (mp3)', 'eternelle' ), 'audio', __( 'Démarre à l’ouverture de l’enveloppe (ou via le bouton ♪).', 'eternelle' ) );
	eternelle_add( $wp, 'et_s_hero', 'et_fx_petals', __( 'Pétales de rose qui tombent', 'eternelle' ), 'checkbox' );
	eternelle_add( $wp, 'et_s_hero', 'et_fx_sparkles', __( 'Poussière d’or & bokeh', 'eternelle' ), 'checkbox' );
	eternelle_add( $wp, 'et_s_hero', 'et_fx_cursor', __( 'Traînée d’étincelles au curseur', 'eternelle' ), 'checkbox' );
	eternelle_add( $wp, 'et_s_hero', 'et_fx_hearts', __( 'Petits cœurs au clic', 'eternelle' ), 'checkbox' );

	// Story.
	eternelle_add( $wp, 'et_s_story', 'et_story_title', __( 'Titre', 'eternelle' ) );
	for ( $i = 1; $i <= 4; $i++ ) {
		/* translators: %d step number */
		eternelle_add( $wp, 'et_s_story', 'et_story_' . $i . '_date', sprintf( __( 'Étape %d – date', 'eternelle' ), $i ) );
		eternelle_add( $wp, 'et_s_story', 'et_story_' . $i . '_title', sprintf( __( 'Étape %d – titre (vide = masquée)', 'eternelle' ), $i ) );
		eternelle_add( $wp, 'et_s_story', 'et_story_' . $i . '_text', sprintf( __( 'Étape %d – texte', 'eternelle' ), $i ), 'textarea' );
		eternelle_add( $wp, 'et_s_story', 'et_story_' . $i . '_image', sprintf( __( 'Étape %d – photo', 'eternelle' ), $i ), 'image' );
	}

	// Program.
	eternelle_add( $wp, 'et_s_program', 'et_program_title', __( 'Titre', 'eternelle' ) );
	for ( $i = 1; $i <= 5; $i++ ) {
		eternelle_add( $wp, 'et_s_program', 'et_prog_' . $i . '_time', sprintf( __( 'Moment %d – heure', 'eternelle' ), $i ) );
		eternelle_add( $wp, 'et_s_program', 'et_prog_' . $i . '_title', sprintf( __( 'Moment %d – titre (vide = masqué)', 'eternelle' ), $i ) );
		eternelle_add( $wp, 'et_s_program', 'et_prog_' . $i . '_text', sprintf( __( 'Moment %d – détail', 'eternelle' ), $i ) );
		eternelle_add( $wp, 'et_s_program', 'et_prog_' . $i . '_icon', sprintf( __( 'Moment %d – icône', 'eternelle' ), $i ), 'icon' );
	}

	// Gallery.
	eternelle_add( $wp, 'et_s_gallery', 'et_gallery_title', __( 'Titre', 'eternelle' ) );
	for ( $i = 1; $i <= 12; $i++ ) {
		eternelle_add( $wp, 'et_s_gallery', 'et_gallery_' . $i, sprintf( __( 'Photo %d', 'eternelle' ), $i ), 'image' );
	}

	// RSVP & gifts.
	eternelle_add( $wp, 'et_s_rsvp', 'et_rsvp_title', __( 'Titre RSVP', 'eternelle' ) );
	eternelle_add( $wp, 'et_s_rsvp', 'et_rsvp_text', __( 'Texte RSVP', 'eternelle' ), 'textarea' );
	eternelle_add( $wp, 'et_s_rsvp', 'et_contact', __( 'Contact des mariés (téléphone ou e-mail)', 'eternelle' ) );
	eternelle_add( $wp, 'et_s_rsvp', 'et_gifts_title', __( 'Titre liste de mariage', 'eternelle' ) );
	eternelle_add( $wp, 'et_s_rsvp', 'et_gifts_text', __( 'Texte liste de mariage', 'eternelle' ), 'textarea' );
	eternelle_add( $wp, 'et_s_rsvp', 'et_gifts_url', __( 'Lien cagnotte / liste', 'eternelle' ), 'url' );
	eternelle_add( $wp, 'et_s_rsvp', 'et_gifts_button', __( 'Texte du bouton', 'eternelle' ) );
	eternelle_add( $wp, 'et_s_rsvp', 'et_footer_text', __( 'Message de pied de page', 'eternelle' ) );
	eternelle_add( $wp, 'et_s_rsvp', 'et_hashtag', __( 'Hashtag (ex : #SarahEtCyril2027)', 'eternelle' ) );

	// Style & sections.
	$wp->add_setting(
		'et_front_mode',
		array(
			'default'           => 'auto',
			'sanitize_callback' => function ( $v ) {
				return in_array( $v, array( 'auto', 'theme', 'elementor', 'hero_elementor', 'theme_elementor' ), true ) ? $v : 'auto';
			},
		)
	);
	$wp->add_control(
		'et_front_mode',
		array(
			'label'       => __( 'Page d’accueil', 'eternelle' ),
			'section'     => 'et_s_style',
			'type'        => 'select',
			'description' => __( 'Pour Elementor : Réglages → Lecture → « Une page statique », puis modifiez cette page avec Elementor.', 'eternelle' ),
			'choices'     => array(
				'auto'            => __( 'Automatique (Elementor si la page d’accueil est faite avec Elementor)', 'eternelle' ),
				'theme'           => __( 'Sections du thème uniquement', 'eternelle' ),
				'elementor'       => __( 'Contenu de la page (Elementor) uniquement', 'eternelle' ),
				'hero_elementor'  => __( 'Hero animé du thème + contenu Elementor', 'eternelle' ),
				'theme_elementor' => __( 'Sections du thème + contenu Elementor en dessous', 'eternelle' ),
			),
		)
	);
	eternelle_add( $wp, 'et_s_style', 'et_color_gold', __( 'Doré (accent)', 'eternelle' ), 'color' );
	eternelle_add( $wp, 'et_s_style', 'et_color_blush', __( 'Rose poudré', 'eternelle' ), 'color' );
	eternelle_add( $wp, 'et_s_style', 'et_color_ink', __( 'Texte', 'eternelle' ), 'color' );
	eternelle_add( $wp, 'et_s_style', 'et_color_sage', __( 'Vert sauge', 'eternelle' ), 'color' );
	foreach ( array(
		'couple'  => __( 'Afficher « Les mariés »', 'eternelle' ),
		'story'   => __( 'Afficher « Notre histoire »', 'eternelle' ),
		'program' => __( 'Afficher « Programme »', 'eternelle' ),
		'venue'   => __( 'Afficher « Le lieu »', 'eternelle' ),
		'gallery' => __( 'Afficher « Galerie »', 'eternelle' ),
		'rsvp'    => __( 'Afficher « RSVP »', 'eternelle' ),
		'gifts'   => __( 'Afficher « Liste de mariage »', 'eternelle' ),
	) as $key => $label ) {
		eternelle_add( $wp, 'et_s_style', 'et_show_' . $key, $label, 'checkbox' );
	}
}
add_action( 'customize_register', 'eternelle_customize_register' );

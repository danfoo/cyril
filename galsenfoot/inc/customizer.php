<?php
/**
 * Customizer options: colours, colour scheme, header, social profiles, footer.
 *
 * @package GalsenFoot
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'customize_register',
	static function ( WP_Customize_Manager $wp_customize ) {
		$wp_customize->add_panel(
			'gf_panel',
			array(
				'title'    => __( 'GalsenFoot', 'galsenfoot' ),
				'priority' => 30,
			)
		);

		// Colours.
		$wp_customize->add_section(
			'gf_colors',
			array(
				'title' => __( 'Couleurs et ambiance', 'galsenfoot' ),
				'panel' => 'gf_panel',
			)
		);
		$colors = array(
			'gf_primary' => array( __( 'Couleur principale', 'galsenfoot' ), '#00a651' ),
			'gf_accent'  => array( __( 'Couleur d’accent', 'galsenfoot' ), '#fdef42' ),
			'gf_hot'     => array( __( 'Couleur « direct / alerte »', 'galsenfoot' ), '#e31b23' ),
		);
		foreach ( $colors as $id => $color ) {
			$wp_customize->add_setting(
				$id,
				array(
					'default'           => $color[1],
					'sanitize_callback' => 'sanitize_hex_color',
				)
			);
			$wp_customize->add_control(
				new WP_Customize_Color_Control(
					$wp_customize,
					$id,
					array(
						'label'   => $color[0],
						'section' => 'gf_colors',
					)
				)
			);
		}
		$wp_customize->add_setting(
			'gf_scheme',
			array(
				'default'           => 'dark',
				'sanitize_callback' => static function ( $value ) {
					return in_array( $value, array( 'dark', 'light', 'auto' ), true ) ? $value : 'dark';
				},
			)
		);
		$wp_customize->add_control(
			'gf_scheme',
			array(
				'label'       => __( 'Mode par défaut', 'galsenfoot' ),
				'description' => __( 'Le visiteur peut toujours basculer avec le bouton soleil / lune.', 'galsenfoot' ),
				'section'     => 'gf_colors',
				'type'        => 'select',
				'choices'     => array(
					'dark'  => __( 'Sombre (stade de nuit)', 'galsenfoot' ),
					'light' => __( 'Clair', 'galsenfoot' ),
					'auto'  => __( 'Selon l’appareil du visiteur', 'galsenfoot' ),
				),
			)
		);
		$wp_customize->add_setting(
			'gf_effects',
			array(
				'default'           => 1,
				'sanitize_callback' => 'absint',
			)
		);
		$wp_customize->add_control(
			'gf_effects',
			array(
				'label'   => __( 'Activer les animations (apparitions au défilement, effets de survol)', 'galsenfoot' ),
				'section' => 'gf_colors',
				'type'    => 'checkbox',
			)
		);

		// Header.
		$wp_customize->add_section(
			'gf_header',
			array(
				'title' => __( 'En-tête et accueil', 'galsenfoot' ),
				'panel' => 'gf_panel',
			)
		);
		$checks = array(
			'gf_ticker' => array( __( 'Afficher le bandeau des scores sous le menu', 'galsenfoot' ), 1 ),
			'gf_sticky' => array( __( 'En-tête fixe au défilement', 'galsenfoot' ), 1 ),
		);
		foreach ( $checks as $id => $check ) {
			$wp_customize->add_setting(
				$id,
				array(
					'default'           => $check[1],
					'sanitize_callback' => 'absint',
				)
			);
			$wp_customize->add_control(
				$id,
				array(
					'label'   => $check[0],
					'section' => 'gf_header',
					'type'    => 'checkbox',
				)
			);
		}
		$wp_customize->add_setting(
			'gf_breaking',
			array(
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		$wp_customize->add_control(
			'gf_breaking',
			array(
				'label'       => __( 'Bandeau « Flash info »', 'galsenfoot' ),
				'description' => __( 'Texte court affiché en haut du site. Laisser vide pour masquer.', 'galsenfoot' ),
				'section'     => 'gf_header',
				'type'        => 'text',
			)
		);
		$wp_customize->add_setting(
			'gf_breaking_url',
			array(
				'default'           => '',
				'sanitize_callback' => 'esc_url_raw',
			)
		);
		$wp_customize->add_control(
			'gf_breaking_url',
			array(
				'label'   => __( 'Lien du flash info', 'galsenfoot' ),
				'section' => 'gf_header',
				'type'    => 'url',
			)
		);
		foreach ( array( 'standings' => __( 'Page « Classement »', 'galsenfoot' ), 'matches' => __( 'Page « Calendrier / résultats »', 'galsenfoot' ) ) as $key => $label ) {
			$wp_customize->add_setting(
				'gf_page_' . $key,
				array(
					'default'           => 0,
					'sanitize_callback' => 'absint',
				)
			);
			$wp_customize->add_control(
				'gf_page_' . $key,
				array(
					'label'   => $label,
					'section' => 'gf_header',
					'type'    => 'dropdown-pages',
				)
			);
		}

		// Social.
		$wp_customize->add_section(
			'gf_social',
			array(
				'title' => __( 'Réseaux sociaux', 'galsenfoot' ),
				'panel' => 'gf_panel',
			)
		);
		foreach ( array( 'facebook' => 'Facebook', 'x' => 'X (Twitter)', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'tiktok' => 'TikTok', 'whatsapp' => __( 'Chaîne WhatsApp', 'galsenfoot' ), 'telegram' => 'Telegram' ) as $network => $label ) {
			$wp_customize->add_setting(
				'gf_social_' . $network,
				array(
					'default'           => '',
					'sanitize_callback' => 'esc_url_raw',
				)
			);
			$wp_customize->add_control(
				'gf_social_' . $network,
				array(
					'label'   => $label,
					'section' => 'gf_social',
					'type'    => 'url',
				)
			);
		}

		// Footer.
		$wp_customize->add_section(
			'gf_footer',
			array(
				'title' => __( 'Pied de page', 'galsenfoot' ),
				'panel' => 'gf_panel',
			)
		);
		$wp_customize->add_setting(
			'gf_footer_about',
			array(
				'default'           => '',
				'sanitize_callback' => 'sanitize_textarea_field',
			)
		);
		$wp_customize->add_control(
			'gf_footer_about',
			array(
				'label'   => __( 'Texte de présentation', 'galsenfoot' ),
				'section' => 'gf_footer',
				'type'    => 'textarea',
			)
		);
		$wp_customize->add_setting(
			'gf_footer_copy',
			array(
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		$wp_customize->add_control(
			'gf_footer_copy',
			array(
				'label'       => __( 'Mention de copyright', 'galsenfoot' ),
				'description' => __( 'Vide : « © année Nom du site ».', 'galsenfoot' ),
				'section'     => 'gf_footer',
				'type'        => 'text',
			)
		);
	}
);

<?php
/**
 * Shortcodes — utilisables sans Elementor.
 *
 * [ecurie_chevaux mise_en_page="grille|mosaique|carrousel|cinema" style="prestige|editorial" theme="sombre|clair"
 *                 defilement_auto="oui" duree="6" (mise_en_page="cinema" : carrousel animé)
 *                 colonnes="3" nombre="12" discipline="plat,obstacle" vedettes="non" filtres="oui"
 *                 tri="ordre|nom|date|victoires|gains|aleatoire" clic="fiche|page|aucun" titre="" surtitre=""]
 * [ecurie_cheval id="12" theme="sombre" inverse="non"]
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EP_Shortcodes {

	public static function init() {
		add_shortcode( 'ecurie_chevaux', array( __CLASS__, 'chevaux' ) );
		add_shortcode( 'ecurie_cheval', array( __CLASS__, 'cheval' ) );
	}

	private static function bool( $value ) {
		return in_array( strtolower( (string) $value ), array( '1', 'oui', 'yes', 'true', 'on' ), true );
	}

	private static function map( $value, array $map, $default ) {
		$value = strtolower( remove_accents( (string) $value ) );
		return isset( $map[ $value ] ) ? $map[ $value ] : $default;
	}

	public static function chevaux( $atts ) {
		$a = shortcode_atts(
			array(
				'mise_en_page' => 'grille',
				'style'        => 'prestige',
				'theme'        => 'sombre',
				'fond'         => 'oui',
				'colonnes'     => 0,
				'nombre'       => 12,
				'discipline'   => '',
				'vedettes'     => 'non',
				'ids'          => '',
				'filtres'      => 'oui',
				'stats'        => 'oui',
				'tri'          => 'ordre',
				'ordre'        => '',
				'clic'         => 'fiche',
				'surtitre'     => '',
				'titre'        => '',
				'intro'        => '',
				'alignement'   => 'gauche',
				'defilement_auto' => 'oui',
				'duree'        => 6,
			),
			$atts,
			'ecurie_chevaux'
		);

		$orderby = self::map( $a['tri'], array( 'ordre' => 'menu_order', 'nom' => 'title', 'date' => 'date', 'victoires' => 'victoires', 'gains' => 'gains', 'aleatoire' => 'rand' ), 'menu_order' );
		$order   = $a['ordre'] ? strtoupper( $a['ordre'] ) : ( in_array( $orderby, array( 'victoires', 'gains', 'date' ), true ) ? 'DESC' : 'ASC' );

		$args = array(
			'layout'      => self::map( $a['mise_en_page'], array( 'grille' => 'grid', 'mosaique' => 'mosaic', 'carrousel' => 'carousel', 'cinema' => 'showcase' ), 'grid' ),
			'autoplay'    => self::bool( $a['defilement_auto'] ),
			'autoplay_delay' => (float) $a['duree'],
			'skin'        => self::map( $a['style'], array( 'prestige' => 'prestige', 'editorial' => 'editorial' ), 'prestige' ),
			'theme'       => self::map( $a['theme'], array( 'sombre' => 'dark', 'clair' => 'light' ), 'dark' ),
			'background'  => self::bool( $a['fond'] ),
			'columns'     => absint( $a['colonnes'] ),
			'number'      => (int) $a['nombre'],
			'filters'     => self::bool( $a['filtres'] ),
			'show_stats'  => self::bool( $a['stats'] ),
			'orderby'     => $orderby,
			'order'       => $order,
			'click'       => self::map( $a['clic'], array( 'fiche' => 'modal', 'page' => 'page', 'aucun' => 'none' ), 'modal' ),
			'eyebrow'     => sanitize_text_field( $a['surtitre'] ),
			'title'       => wp_kses( $a['titre'], array( 'em' => array(), 'br' => array(), 'strong' => array() ) ),
			'intro'       => sanitize_text_field( $a['intro'] ),
			'align'       => 'centre' === self::map( $a['alignement'], array( 'centre' => 'centre' ), '' ) ? 'center' : 'left',
		);

		if ( '' !== trim( $a['ids'] ) ) {
			$args['source'] = 'manual';
			$args['ids']    = wp_parse_id_list( $a['ids'] );
		} elseif ( '' !== trim( $a['discipline'] ) ) {
			$args['source']      = 'discipline';
			$args['disciplines'] = array_map( 'sanitize_title', array_filter( array_map( 'trim', explode( ',', $a['discipline'] ) ) ) );
		} elseif ( self::bool( $a['vedettes'] ) ) {
			$args['source'] = 'vedette';
		}

		return EP_Render::collection( $args );
	}

	public static function cheval( $atts ) {
		$a = shortcode_atts(
			array(
				'id'       => 0,
				'theme'    => 'sombre',
				'fond'     => 'oui',
				'inverse'  => 'non',
				'clic'     => 'page',
				'surtitre' => '',
			),
			$atts,
			'ecurie_cheval'
		);
		return EP_Render::featured(
			absint( $a['id'] ),
			array(
				'theme'      => self::map( $a['theme'], array( 'sombre' => 'dark', 'clair' => 'light' ), 'dark' ),
				'background' => self::bool( $a['fond'] ),
				'reverse'    => self::bool( $a['inverse'] ),
				'click'      => self::map( $a['clic'], array( 'fiche' => 'modal', 'page' => 'page', 'aucun' => 'none' ), 'page' ),
				'eyebrow'    => sanitize_text_field( $a['surtitre'] ),
			)
		);
	}
}

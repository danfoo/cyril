<?php
/**
 * Inline SVG icons (line style, currentColor).
 *
 * @package Eternelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function eternelle_icon_list() {
	return array(
		'rings'   => __( 'Alliances', 'eternelle' ),
		'church'  => __( 'Église / mairie', 'eternelle' ),
		'glasses' => __( 'Coupes de champagne', 'eternelle' ),
		'dinner'  => __( 'Dîner', 'eternelle' ),
		'cake'    => __( 'Gâteau', 'eternelle' ),
		'music'   => __( 'Musique', 'eternelle' ),
		'camera'  => __( 'Photos', 'eternelle' ),
		'heart'   => __( 'Cœur', 'eternelle' ),
		'moon'    => __( 'Nuit', 'eternelle' ),
	);
}

/**
 * SVG markup.
 *
 * @param string $name  Icon.
 * @param string $class Extra class.
 */
function eternelle_icon( $name, $class = '' ) {
	$paths = array(
		'rings'    => '<circle cx="9" cy="14" r="6"/><circle cx="15.5" cy="14" r="6"/><path d="M7.5 5.5 9 3h3l1.5 2.5L11.2 8 7.5 5.5Z"/>',
		'church'   => '<path d="M12 2v4M10 4h4M5 21V11l7-5 7 5v10"/><path d="M10 21v-4a2 2 0 0 1 4 0v4M3 21h18"/>',
		'glasses'  => '<path d="M8 3h-4l.5 6a2.5 2.5 0 0 0 5 0L8 3ZM6.5 11.5V20M4 20.5h5M20 3h-4l-.5 6a2.5 2.5 0 0 0 5 0L20 3ZM17.5 11.5V20M15 20.5h5"/><path d="M11 6l1.5-2M12.5 7.5 14 6"/>',
		'dinner'   => '<circle cx="12" cy="13" r="6"/><circle cx="12" cy="13" r="3.2"/><path d="M3 4v5a2 2 0 0 0 2 2v9M5 4v4M21 4c-1.5 1-2 3-2 6h2v10"/>',
		'cake'     => '<path d="M4 21h16M5 21v-6h14v6M7 15v-4h10v4"/><path d="M12 11V8M12 5.5c.8 0 1.2-.6 1.2-1.2S12 2.5 12 2.5s-1.2 1.2-1.2 1.8.4 1.2 1.2 1.2ZM5 18c1.2 0 1.2-1 2.3-1s1.2 1 2.4 1 1.1-1 2.3-1 1.2 1 2.3 1 1.2-1 2.4-1 1.1 1 2.3 1"/>',
		'music'    => '<path d="M9 18V5l11-2v13"/><circle cx="6.5" cy="18" r="2.5"/><circle cx="17.5" cy="16" r="2.5"/>',
		'camera'   => '<path d="M3 8h4l2-3h6l2 3h4v12H3z"/><circle cx="12" cy="13.5" r="4"/>',
		'heart'    => '<path d="M12 20s-7.5-4.6-9.2-9.3C1.6 7.2 3.8 4 7 4c2 0 3.6 1.2 5 3 1.4-1.8 3-3 5-3 3.2 0 5.4 3.2 4.2 6.7C19.5 15.4 12 20 12 20Z"/>',
		'moon'     => '<path d="M20 15.5A8.5 8.5 0 1 1 8.5 4a7 7 0 0 0 11.5 11.5Z"/><path d="M16 3v3M14.5 4.5h3"/>',
		'pin'      => '<path d="M12 21s-7-6.1-7-11.5A7 7 0 0 1 19 9.5C19 14.9 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.5"/>',
		'gift'     => '<path d="M4 11h16v10H4zM3 7h18v4H3zM12 7v14"/><path d="M12 7c-1.5-3-5-4-5.5-2S9 7 12 7Zm0 0c1.5-3 5-4 5.5-2S15 7 12 7Z"/>',
		'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
		'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		'mail'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
		'hanger'   => '<path d="M12 7a2 2 0 1 1 2-2c0 1.2-2 1.5-2 3v1L3 15.5c-.9.6-.5 1.5.5 1.5h17c1 0 1.4-.9.5-1.5L12 9"/>',
		'bed'      => '<path d="M3 19V6M3 15h18v4M21 15v-3a3 3 0 0 0-3-3h-7v6"/><circle cx="7" cy="11" r="2"/>',
		'play'     => '<path d="M8 5v14l11-7z"/>',
		'pause'    => '<path d="M8 5v14M16 5v14"/>',
		'menu'     => '<path d="M4 7h16M4 12h16M4 17h16"/>',
		'close'    => '<path d="M6 6l12 12M18 6 6 18"/>',
		'left'     => '<path d="m15 5-7 7 7 7"/>',
		'right'    => '<path d="m9 5 7 7-7 7"/>',
	);

	if ( 'flourish' === $name ) {
		return '<svg class="et-icon ' . esc_attr( $class ) . '" viewBox="0 0 220 30" aria-hidden="true" focusable="false"><g fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round"><path class="et-draw" d="M2 15h80M218 15h-80"/><path class="et-draw" d="M82 15c8-10 18-10 22-3-4 5-14 6-22 3Zm56 0c-8-10-18-10-22-3 4 5 14 6 22 3Z"/></g><path fill="currentColor" d="M110 11.2c-1.4-2.4-5.2-1.9-5.2 1.1 0 2.4 3.1 4.3 5.2 6.3 2.1-2 5.2-3.9 5.2-6.3 0-3-3.8-3.5-5.2-1.1Z"/></svg>';
	}

	if ( ! isset( $paths[ $name ] ) ) {
		$name = 'heart';
	}
	$fill = in_array( $name, array( 'play' ), true ) ? 'currentColor' : 'none';
	return '<svg class="et-icon ' . esc_attr( $class ) . '" viewBox="0 0 24 24" width="24" height="24" fill="' . $fill . '" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
}

<?php
/**
 * Countdown boxes (used by the hero and the Elementor widget).
 *
 * $args: style = glass|light.
 *
 * @package Eternelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$style = isset( $args['style'] ) && 'light' === $args['style'] ? ' et-countdown--light' : '';
?>
<div class="et-countdown<?php echo esc_attr( $style ); ?>" data-countdown="<?php echo (int) eternelle_timestamp() * 1000; ?>" aria-live="polite">
	<div><strong data-cd="d">0</strong><span><?php esc_html_e( 'Jours', 'eternelle' ); ?></span></div>
	<div><strong data-cd="h">0</strong><span><?php esc_html_e( 'Heures', 'eternelle' ); ?></span></div>
	<div><strong data-cd="m">0</strong><span><?php esc_html_e( 'Minutes', 'eternelle' ); ?></span></div>
	<div><strong data-cd="s">0</strong><span><?php esc_html_e( 'Secondes', 'eternelle' ); ?></span></div>
</div>

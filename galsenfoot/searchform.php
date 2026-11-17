<?php
/**
 * Search form.
 *
 * @package GalsenFoot
 */

defined( 'ABSPATH' ) || exit;
$gf_id = wp_unique_id( 'gf-s-' );
?>
<form role="search" method="get" class="gf-searchform" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="<?php echo esc_attr( $gf_id ); ?>"><?php esc_html_e( 'Rechercher', 'galsenfoot' ); ?></label>
	<input type="search" id="<?php echo esc_attr( $gf_id ); ?>" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php esc_attr_e( 'Un club, un joueur, une info…', 'galsenfoot' ); ?>" autocomplete="off">
	<button type="submit" class="gf-btn"><?php echo gf_icon( 'search', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php esc_html_e( 'Rechercher', 'galsenfoot' ); ?></span></button>
</form>

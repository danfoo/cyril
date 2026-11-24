<?php
/**
 * Section: latest wishes from the guest book (carousel).
 * Needs the Wedding RSVP Pass plugin.
 *
 * @package Eternelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! shortcode_exists( 'wrp_wishes_carousel' ) ) {
	return;
}
$sc = sprintf(
	'[wrp_wishes_carousel count="%1$d" title="%2$s" kicker="%3$s"]',
	(int) eternelle_mod( 'et_wishes_count' ),
	esc_attr( eternelle_mod( 'et_wishes_title' ) ),
	esc_attr__( 'Livre d’or', 'eternelle' )
);
?>
<div class="et-wishes" id="wishes">
	<?php echo do_shortcode( $sc ); ?>
</div>

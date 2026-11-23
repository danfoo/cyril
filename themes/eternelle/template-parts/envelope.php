<?php
/**
 * Intro: envelope with a wax seal. Click the seal → the envelope opens.
 *
 * @package Eternelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$ini = eternelle_initials();
?>
<div class="et-envelope" data-envelope role="dialog" aria-modal="true" aria-label="<?php echo esc_attr( eternelle_mod( 'et_envelope_text' ) ); ?>">
	<div class="et-envelope__bg" aria-hidden="true"></div>
	<div class="et-envelope__scene">
		<p class="et-envelope__hint et-kicker"><?php echo esc_html( eternelle_mod( 'et_envelope_text' ) ); ?></p>
		<div class="et-env">
			<div class="et-env__back"></div>
			<div class="et-env__letter">
				<p class="et-kicker"><?php esc_html_e( 'Save the date', 'eternelle' ); ?></p>
				<p class="et-env__names"><?php echo esc_html( eternelle_mod( 'et_partner_a' ) ); ?> <em>&amp;</em> <?php echo esc_html( eternelle_mod( 'et_partner_b' ) ); ?></p>
				<p class="et-env__date"><?php echo esc_html( eternelle_date( 'd . m . Y' ) ); ?></p>
			</div>
			<div class="et-env__front"></div>
			<div class="et-env__flap"></div>
			<button class="et-env__seal" type="button" data-envelope-open>
				<span class="et-env__seal-wax" aria-hidden="true"><?php echo esc_html( $ini['a'] ); ?><em>&amp;</em><?php echo esc_html( $ini['b'] ); ?></span>
				<span class="screen-reader-text"><?php esc_html_e( 'Ouvrir l’invitation', 'eternelle' ); ?></span>
			</button>
		</div>
		<p class="et-envelope__tap"><?php esc_html_e( 'Touchez le sceau pour ouvrir', 'eternelle' ); ?></p>
		<button type="button" class="et-envelope__skip" data-envelope-skip><?php esc_html_e( 'Passer', 'eternelle' ); ?></button>
	</div>
</div>

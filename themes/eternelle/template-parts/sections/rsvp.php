<?php
/**
 * Section: RSVP.
 *
 * @package Eternelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$deadline = eternelle_has_rsvp_plugin() ? WRP_Settings::deadline_display() : '';
?>
<section class="et-section et-rsvp" id="rsvp">
	<div class="et-rsvp__petals" aria-hidden="true"></div>
	<div class="et-container">
		<div class="et-rsvp__card" data-reveal>
			<span class="et-rsvp__stamp" aria-hidden="true"><?php echo eternelle_icon( 'mail' ); // phpcs:ignore ?></span>
			<p class="et-kicker"><?php esc_html_e( 'R.S.V.P', 'eternelle' ); ?></p>
			<h2 class="et-title__h"><?php echo esc_html( eternelle_mod( 'et_rsvp_title' ) ); ?></h2>
			<?php echo eternelle_icon( 'flourish', 'et-title__flourish' ); // phpcs:ignore ?>
			<?php if ( $deadline ) : ?>
				<p class="et-rsvp__deadline"><?php echo esc_html( sprintf( __( 'Merci de répondre avant le %s', 'eternelle' ), $deadline ) ); ?></p>
			<?php endif; ?>
			<p class="et-rsvp__text"><?php echo esc_html( eternelle_mod( 'et_rsvp_text' ) ); ?></p>

			<?php if ( eternelle_has_rsvp_plugin() ) : ?>
				<?php echo do_shortcode( '[wrp_find_invitation]' ); ?>
			<?php elseif ( current_user_can( 'activate_plugins' ) ) : ?>
				<p class="et-muted"><?php esc_html_e( '(Administrateur) Activez le plugin « Wedding RSVP Pass » pour gérer les invités, les confirmations et les cartons QR code.', 'eternelle' ); ?></p>
			<?php endif; ?>

			<?php if ( eternelle_mod( 'et_contact' ) ) : ?>
				<?php
				$contact = eternelle_mod( 'et_contact' );
				$href    = is_email( $contact ) ? 'mailto:' . $contact : 'tel:' . preg_replace( '/[^0-9+]/', '', $contact );
				?>
				<p class="et-rsvp__contact"><?php esc_html_e( 'Une question ?', 'eternelle' ); ?> <a href="<?php echo esc_attr( $href ); ?>"><?php echo esc_html( $contact ); ?></a></p>
			<?php endif; ?>
		</div>
	</div>
</section>

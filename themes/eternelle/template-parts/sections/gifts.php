<?php
/**
 * Section: gifts.
 *
 * @package Eternelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="et-section et-gifts" id="gifts">
	<div class="et-container et-container--narrow">
		<div class="et-gifts__icon" data-reveal="zoom"><?php echo eternelle_icon( 'gift' ); // phpcs:ignore ?></div>
		<?php eternelle_section_title( eternelle_mod( 'et_gifts_title' ) ); ?>
		<p class="et-center et-lead" data-reveal><?php echo esc_html( eternelle_mod( 'et_gifts_text' ) ); ?></p>
		<?php if ( eternelle_mod( 'et_gifts_url' ) ) : ?>
			<p class="et-center" data-reveal><a class="et-btn et-btn--gold" href="<?php echo esc_url( eternelle_mod( 'et_gifts_url' ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( eternelle_mod( 'et_gifts_button' ) ); ?></a></p>
		<?php endif; ?>
	</div>
</section>

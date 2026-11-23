<?php
/**
 * Section: venue.
 *
 * @package Eternelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$embed = eternelle_mod( 'et_map_embed' );
$link  = eternelle_mod( 'et_map_url' );
if ( ! $link && eternelle_mod( 'et_address' ) ) {
	$link = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( eternelle_mod( 'et_venue' ) . ' ' . eternelle_mod( 'et_address' ) );
}
?>
<section class="et-section et-venue" id="venue">
	<div class="et-container">
		<?php eternelle_section_title( eternelle_mod( 'et_venue_title' ), __( 'Rendez-vous', 'eternelle' ) ); ?>
		<div class="et-venue__grid<?php echo $embed ? '' : ' no-map'; ?>">
			<div class="et-venue__card" data-reveal="left">
				<?php echo eternelle_icon( 'pin', 'et-venue__pin' ); // phpcs:ignore ?>
				<h3><?php echo esc_html( eternelle_mod( 'et_venue' ) ); ?></h3>
				<p class="et-venue__addr"><?php echo esc_html( eternelle_mod( 'et_address' ) ); ?></p>
				<ul class="et-venue__facts">
					<li><?php echo eternelle_icon( 'calendar' ); // phpcs:ignore ?> <?php echo esc_html( ucfirst( eternelle_date() ) ); ?></li>
					<li><?php echo eternelle_icon( 'clock' ); // phpcs:ignore ?> <?php echo esc_html( eternelle_time() ); ?></li>
				</ul>
				<?php if ( eternelle_mod( 'et_venue_text' ) ) : ?>
					<p><?php echo esc_html( eternelle_mod( 'et_venue_text' ) ); ?></p>
				<?php endif; ?>
				<?php if ( eternelle_mod( 'et_stay_text' ) ) : ?>
					<p class="et-venue__stay"><?php echo eternelle_icon( 'bed' ); // phpcs:ignore ?> <?php echo esc_html( eternelle_mod( 'et_stay_text' ) ); ?></p>
				<?php endif; ?>
				<?php if ( $link ) : ?>
					<a class="et-btn" href="<?php echo esc_url( $link ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Itinéraire', 'eternelle' ); ?></a>
				<?php endif; ?>
			</div>
			<?php if ( $embed ) : ?>
				<div class="et-venue__map" data-reveal="right">
					<iframe src="<?php echo esc_url( $embed ); ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="<?php esc_attr_e( 'Carte du lieu', 'eternelle' ); ?>" allowfullscreen></iframe>
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>

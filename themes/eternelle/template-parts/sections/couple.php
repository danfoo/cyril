<?php
/**
 * Section: the couple.
 *
 * @package Eternelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$people = array(
	array( eternelle_mod( 'et_partner_a' ), eternelle_image_url( 'et_photo_a', 'eternelle-portrait' ), eternelle_mod( 'et_bio_a' ), eternelle_initials()['a'] ),
	array( eternelle_mod( 'et_partner_b' ), eternelle_image_url( 'et_photo_b', 'eternelle-portrait' ), eternelle_mod( 'et_bio_b' ), eternelle_initials()['b'] ),
);
?>
<section class="et-section et-couple" id="couple">
	<div class="et-container">
		<?php eternelle_section_title( eternelle_mod( 'et_couple_title' ), eternelle_mod( 'et_couple_intro' ) ); ?>
		<div class="et-couple__grid">
			<?php foreach ( $people as $i => $p ) : ?>
				<article class="et-person" data-reveal="<?php echo 0 === $i ? 'left' : 'right'; ?>">
					<div class="et-person__photo">
						<svg class="et-person__ring" viewBox="0 0 100 100" aria-hidden="true"><circle cx="50" cy="50" r="48"/></svg>
						<?php if ( $p[1] ) : ?>
							<img src="<?php echo esc_url( $p[1] ); ?>" alt="<?php echo esc_attr( $p[0] ); ?>" loading="lazy">
						<?php else : ?>
							<span class="et-person__initial" aria-hidden="true"><?php echo esc_html( $p[3] ); ?></span>
						<?php endif; ?>
					</div>
					<h3 class="et-person__name"><?php echo esc_html( $p[0] ); ?></h3>
					<p class="et-person__bio"><?php echo esc_html( $p[2] ); ?></p>
				</article>
				<?php if ( 0 === $i ) : ?>
					<div class="et-couple__amp" data-reveal="zoom" aria-hidden="true">
						<span>&amp;</span>
						<?php echo eternelle_icon( 'heart', 'et-couple__heart' ); // phpcs:ignore ?>
					</div>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
	</div>
</section>

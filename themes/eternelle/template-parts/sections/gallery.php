<?php
/**
 * Section: gallery with lightbox.
 *
 * @package Eternelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$imgs = eternelle_gallery_images();
?>
<section class="et-section et-gallery" id="gallery">
	<div class="et-container">
		<?php eternelle_section_title( eternelle_mod( 'et_gallery_title' ), __( 'Moments', 'eternelle' ) ); ?>
		<?php if ( ! $imgs ) : ?>
			<p class="et-center et-muted"><?php esc_html_e( 'Ajoutez vos photos dans Apparence → Personnaliser → Notre mariage → Galerie photos.', 'eternelle' ); ?></p>
		<?php else : ?>
			<div class="et-masonry" data-gallery>
				<?php foreach ( $imgs as $i => $img ) : ?>
					<a class="et-masonry__item" href="<?php echo esc_url( $img['full'] ); ?>" data-index="<?php echo (int) $i; ?>" data-reveal="zoom" style="--d:<?php echo (int) ( $i % 4 ); ?>">
						<img src="<?php echo esc_url( $img['thumb'] ); ?>" alt="<?php echo esc_attr( $img['alt'] ); ?>" loading="lazy">
						<span class="et-masonry__heart" aria-hidden="true"><?php echo eternelle_icon( 'heart' ); // phpcs:ignore ?></span>
					</a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
	<div class="et-lightbox" data-lightbox hidden>
		<button class="et-lightbox__close" type="button" data-lb-close aria-label="<?php esc_attr_e( 'Fermer', 'eternelle' ); ?>"><?php echo eternelle_icon( 'close' ); // phpcs:ignore ?></button>
		<button class="et-lightbox__nav et-lightbox__prev" type="button" data-lb-prev aria-label="<?php esc_attr_e( 'Précédente', 'eternelle' ); ?>"><?php echo eternelle_icon( 'left' ); // phpcs:ignore ?></button>
		<img alt="" data-lb-img>
		<button class="et-lightbox__nav et-lightbox__next" type="button" data-lb-next aria-label="<?php esc_attr_e( 'Suivante', 'eternelle' ); ?>"><?php echo eternelle_icon( 'right' ); // phpcs:ignore ?></button>
	</div>
</section>

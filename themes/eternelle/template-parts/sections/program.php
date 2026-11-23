<?php
/**
 * Section: programme of the day.
 *
 * @package Eternelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$items = eternelle_program_items();
if ( ! $items ) {
	return;
}
?>
<section class="et-section et-program" id="program">
	<div class="et-program__bg" aria-hidden="true"></div>
	<div class="et-container">
		<?php eternelle_section_title( eternelle_mod( 'et_program_title' ), ucfirst( eternelle_date() ) ); ?>
		<div class="et-program__grid">
			<?php foreach ( $items as $i => $it ) : ?>
				<article class="et-moment" data-reveal style="--d:<?php echo (int) $i; ?>">
					<div class="et-moment__icon"><?php echo eternelle_icon( $it['icon'] ); // phpcs:ignore ?></div>
					<p class="et-moment__time"><?php echo esc_html( $it['time'] ); ?></p>
					<h3 class="et-moment__title"><?php echo esc_html( $it['title'] ); ?></h3>
					<p class="et-moment__text"><?php echo esc_html( $it['text'] ); ?></p>
				</article>
			<?php endforeach; ?>
		</div>
		<?php if ( eternelle_mod( 'et_dress_code' ) ) : ?>
			<p class="et-dress" data-reveal><?php echo eternelle_icon( 'hanger' ); // phpcs:ignore ?> <span><?php esc_html_e( 'Dress code', 'eternelle' ); ?></span> <?php echo esc_html( eternelle_mod( 'et_dress_code' ) ); ?></p>
		<?php endif; ?>
	</div>
</section>

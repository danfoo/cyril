<?php
/**
 * Section: our story (timeline).
 *
 * @package Eternelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$items = eternelle_story_items();
if ( ! $items ) {
	return;
}
?>
<section class="et-section et-story" id="story">
	<div class="et-container">
		<?php eternelle_section_title( eternelle_mod( 'et_story_title' ), __( 'Il était une fois', 'eternelle' ) ); ?>
		<ol class="et-timeline">
			<?php foreach ( $items as $i => $it ) : ?>
				<li class="et-timeline__item<?php echo $it['image'] ? ' has-image' : ''; ?>" data-reveal="<?php echo $i % 2 ? 'right' : 'left'; ?>">
					<span class="et-timeline__dot" aria-hidden="true"><?php echo eternelle_icon( 'heart' ); // phpcs:ignore ?></span>
					<div class="et-timeline__card">
						<?php if ( $it['image'] ) : ?>
							<img class="et-timeline__img" src="<?php echo esc_url( $it['image'] ); ?>" alt="" loading="lazy">
						<?php endif; ?>
						<p class="et-timeline__date"><?php echo esc_html( $it['date'] ); ?></p>
						<h3 class="et-timeline__title"><?php echo esc_html( $it['title'] ); ?></h3>
						<p class="et-timeline__text"><?php echo esc_html( $it['text'] ); ?></p>
					</div>
				</li>
			<?php endforeach; ?>
		</ol>
	</div>
</section>

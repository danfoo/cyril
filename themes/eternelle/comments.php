<?php
/**
 * Comments (guest book on posts/pages).
 *
 * @package Eternelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( post_password_required() ) {
	return;
}
?>
<section id="comments" class="et-comments">
	<?php if ( have_comments() ) : ?>
		<h2 class="et-comments__title"><?php echo esc_html( sprintf( _n( '%d petit mot', '%d petits mots', get_comments_number(), 'eternelle' ), get_comments_number() ) ); ?></h2>
		<ol class="et-comments__list">
			<?php
			wp_list_comments(
				array(
					'style'       => 'ol',
					'short_ping'  => true,
					'avatar_size' => 48,
				)
			);
			?>
		</ol>
		<?php the_comments_navigation(); ?>
	<?php endif; ?>
	<?php
	comment_form(
		array(
			'title_reply' => __( 'Laisser un mot doux', 'eternelle' ),
			'label_submit' => __( 'Envoyer', 'eternelle' ),
			'class_submit' => 'et-btn',
		)
	);
	?>
</section>

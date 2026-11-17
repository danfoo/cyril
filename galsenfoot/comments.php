<?php
/**
 * Comments.
 *
 * @package GalsenFoot
 */

defined( 'ABSPATH' ) || exit;

if ( post_password_required() ) {
	return;
}
?>
<section id="comments" class="gf-comments">
	<?php
	if ( have_comments() ) :
		$gf_count = (int) get_comments_number();
		/* translators: %s comment count */
		echo gf_heading( sprintf( _n( '%s commentaire', '%s commentaires', $gf_count, 'galsenfoot' ), number_format_i18n( $gf_count ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		?>
		<ol class="gf-comments__list">
			<?php
			wp_list_comments(
				array(
					'style'       => 'ol',
					'short_ping'  => true,
					'avatar_size' => 44,
				)
			);
			?>
		</ol>
		<?php
		the_comments_pagination();
	endif;

	if ( ! comments_open() && get_comments_number() ) {
		echo '<p class="gf-empty">' . esc_html__( 'Les commentaires sont fermés.', 'galsenfoot' ) . '</p>';
	}

	comment_form(
		array(
			'title_reply'        => __( 'Réagir', 'galsenfoot' ),
			'title_reply_before' => '<h2 id="reply-title" class="gf-heading"><span>',
			'title_reply_after'  => '</span></h2>',
			'class_submit'       => 'gf-btn',
			'label_submit'       => __( 'Publier', 'galsenfoot' ),
		)
	);
	?>
</section>

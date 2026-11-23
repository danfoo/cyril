<?php
/**
 * Footer.
 *
 * @package Eternelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$music = eternelle_image_url( 'et_music' );
if ( ! $music ) {
	$mid = eternelle_mod( 'et_music' );
	$music = is_numeric( $mid ) ? wp_get_attachment_url( (int) $mid ) : '';
}
?>
</div><!-- .et-site -->

<footer class="et-footer">
	<div class="et-footer__hearts" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span><span></span><span></span></div>
	<p class="et-kicker"><?php echo esc_html( eternelle_mod( 'et_footer_text' ) ); ?></p>
	<p class="et-footer__names"><?php echo esc_html( eternelle_mod( 'et_partner_a' ) ); ?> <em>&amp;</em> <?php echo esc_html( eternelle_mod( 'et_partner_b' ) ); ?></p>
	<p class="et-footer__date"><?php echo esc_html( eternelle_date( 'd . m . Y' ) ); ?></p>
	<?php if ( eternelle_mod( 'et_hashtag' ) ) : ?>
		<p class="et-footer__tag"><?php echo esc_html( eternelle_mod( 'et_hashtag' ) ); ?></p>
	<?php endif; ?>
	<p class="et-footer__small">&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> · <?php bloginfo( 'name' ); ?></p>
</footer>

<?php if ( $music ) : ?>
	<button class="et-music" type="button" data-music aria-pressed="false" aria-label="<?php esc_attr_e( 'Musique', 'eternelle' ); ?>">
		<span class="et-music__disc" aria-hidden="true"></span>
		<span class="et-music__bars" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
	</button>
	<audio data-audio src="<?php echo esc_url( $music ); ?>" loop preload="none"></audio>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>

<?php
/**
 * Unknown / expired link.
 *
 * @package WeddingRSVPPass
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$page_title = __( 'Invitation introuvable', 'wedding-rsvp-pass' );
$body_class = 'wrp-page--404';
require WRP_DIR . 'templates/partials/head.php';
?>
<main class="wrp-stage">
	<article class="wrp-paper wrp-reveal">
		<p class="wrp-kicker"><?php echo esc_html( WRP_Settings::couple_names() ); ?></p>
		<h1 class="wrp-names"><span><?php esc_html_e( 'Oups…', 'wedding-rsvp-pass' ); ?></span></h1>
		<?php require WRP_DIR . 'templates/partials/ornament.php'; ?>
		<p class="wrp-intro"><?php esc_html_e( 'Ce lien d’invitation est introuvable. Vérifiez qu’il a été copié en entier, ou contactez les mariés.', 'wedding-rsvp-pass' ); ?></p>
		<p><a class="wrp-button" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Aller sur le site du mariage', 'wedding-rsvp-pass' ); ?></a></p>
	</article>
</main>
<script src="<?php echo esc_url( WRP_Public::asset( 'js/public.js' ) ); ?>" defer></script>
</body>
</html>

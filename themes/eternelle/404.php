<?php
/**
 * 404.
 *
 * @package Eternelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
get_header();
?>
<main class="et-page et-404">
	<header class="et-page__head">
		<p class="et-kicker">404</p>
		<h1 class="et-page__title"><?php esc_html_e( 'Cette page s’est perdue en chemin', 'eternelle' ); ?></h1>
		<?php echo eternelle_icon( 'flourish', 'et-title__flourish' ); // phpcs:ignore ?>
		<p><a class="et-btn" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Retour à l’accueil', 'eternelle' ); ?></a></p>
	</header>
</main>
<?php
get_footer();

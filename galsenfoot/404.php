<?php
/**
 * 404.
 *
 * @package GalsenFoot
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="gf-container gf-404">
	<p class="gf-404__code" aria-hidden="true">4<span><?php echo gf_icon( 'ball', 120 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>4</p>
	<h1><?php esc_html_e( 'Hors-jeu ! Cette page est introuvable.', 'galsenfoot' ); ?></h1>
	<p><?php esc_html_e( 'Le lien est peut-être ancien ou mal saisi. Relancez l’action avec une recherche ou revenez à l’accueil.', 'galsenfoot' ); ?></p>
	<?php get_search_form(); ?>
	<p><a class="gf-btn" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Retour à l’accueil', 'galsenfoot' ); ?></a></p>
</div>
<?php
get_footer();

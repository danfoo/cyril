<?php
/**
 * Gabarit de la page d’un cheval (thèmes classiques).
 * Pour le personnaliser, copiez-le dans votre thème sous le nom single-ep_cheval.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) {
	the_post();
	echo EP_Render::single_page( get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML échappé dans EP_Render.
}

get_footer();

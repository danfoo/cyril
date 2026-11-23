<?php
/**
 * Shared <head> for the plugin's standalone pages.
 *
 * @var string $page_title
 * @var string $body_class
 * @package WeddingRSVPPass
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="robots" content="noindex, nofollow">
	<meta name="theme-color" content="<?php echo esc_attr( sanitize_hex_color( WRP_Settings::get( 'color_blush' ) ) ?: '#f4e1dc' ); ?>">
	<title><?php echo esc_html( $page_title ); ?></title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link rel="stylesheet" href="<?php echo esc_url( WRP_Public::fonts_url() ); ?>">
	<link rel="stylesheet" href="<?php echo esc_url( WRP_Public::asset( 'css/public.css' ) ); ?>">
	<style><?php echo WRP_Public::css_vars(); // phpcs:ignore ?></style>
	<?php
	if ( has_site_icon() ) {
		wp_site_icon();
	}
	do_action( 'wrp_head', $body_class );
	?>
</head>
<body class="wrp-page <?php echo esc_attr( $body_class ); ?>">
<canvas class="wrp-petals" aria-hidden="true"></canvas>

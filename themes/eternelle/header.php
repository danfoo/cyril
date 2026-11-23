<?php
/**
 * Header.
 *
 * @package Eternelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$ini = eternelle_initials();
?><!DOCTYPE html>
<html class="no-js" <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="theme-color" content="<?php echo esc_attr( sanitize_hex_color( eternelle_mod( 'et_color_blush' ) ) ?: '#f4e1dc' ); ?>">
	<script>(function(d){d.className=d.className.replace('no-js','js');try{if(sessionStorage.getItem('et_env_opened')==='1'){d.className+=' et-env-seen';}}catch(e){}})(document.documentElement);</script>
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#content"><?php esc_html_e( 'Aller au contenu', 'eternelle' ); ?></a>

<?php
if ( is_front_page() && eternelle_mod( 'et_envelope' ) ) {
	get_template_part( 'template-parts/envelope' );
}
?>

<header class="et-header<?php echo is_front_page() ? ' is-transparent' : ''; ?>" data-header>
	<div class="et-header__inner">
		<a class="et-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
			<?php if ( has_custom_logo() ) : ?>
				<?php echo wp_kses_post( wp_get_attachment_image( get_theme_mod( 'custom_logo' ), 'thumbnail', false, array( 'class' => 'et-brand__logo' ) ) ); ?>
			<?php else : ?>
				<span class="et-brand__mono"><?php echo esc_html( $ini['a'] ); ?><em>&amp;</em><?php echo esc_html( $ini['b'] ); ?></span>
			<?php endif; ?>
		</a>
		<button class="et-burger" type="button" aria-expanded="false" aria-controls="et-nav" data-burger>
			<span class="screen-reader-text"><?php esc_html_e( 'Menu', 'eternelle' ); ?></span>
			<i></i><i></i><i></i>
		</button>
		<nav id="et-nav" class="et-nav" aria-label="<?php esc_attr_e( 'Menu principal', 'eternelle' ); ?>" data-nav>
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'et-menu',
					'fallback_cb'    => 'eternelle_menu_fallback',
					'depth'          => 2,
				)
			);
			?>
		</nav>
	</div>
</header>
<div id="content" class="et-site">

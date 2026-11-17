<?php
/**
 * Header.
 *
 * @package GalsenFoot
 */

defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class( get_theme_mod( 'gf_effects', 1 ) ? 'gf-fx' : '' ); ?>>
<?php wp_body_open(); ?>
<a class="gf-skip" href="#main"><?php esc_html_e( 'Aller au contenu', 'galsenfoot' ); ?></a>
<div class="gf-progress" aria-hidden="true"><i></i></div>
<?php
if ( ! gf_elementor_location( 'header' ) ) :
	$breaking = trim( (string) get_theme_mod( 'gf_breaking', '' ) );
	if ( '' !== $breaking ) :
		$breaking_url = get_theme_mod( 'gf_breaking_url', '' );
		?>
		<div class="gf-breaking">
			<div class="gf-container">
				<span class="gf-breaking__tag"><?php esc_html_e( 'Flash', 'galsenfoot' ); ?></span>
				<?php if ( $breaking_url ) : ?>
					<a href="<?php echo esc_url( $breaking_url ); ?>"><?php echo esc_html( $breaking ); ?></a>
				<?php else : ?>
					<span><?php echo esc_html( $breaking ); ?></span>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>
	<header class="gf-header<?php echo get_theme_mod( 'gf_sticky', 1 ) ? ' is-sticky' : ''; ?>" id="gf-header">
		<div class="gf-container gf-header__inner">
			<button type="button" class="gf-iconbtn gf-header__burger" aria-controls="gf-nav" aria-expanded="false" aria-label="<?php esc_attr_e( 'Ouvrir le menu', 'galsenfoot' ); ?>" data-gf-toggle="nav"><?php echo gf_icon( 'menu', 24 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
			<?php
			$brand_tag = is_front_page() ? 'div' : 'p';
			echo '<' . $brand_tag . ' class="gf-header__brand">' . gf_logo() . '</' . $brand_tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
			?>
			<nav class="gf-nav" id="gf-nav" aria-label="<?php esc_attr_e( 'Navigation principale', 'galsenfoot' ); ?>">
				<div class="gf-nav__head">
					<?php echo gf_logo(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<button type="button" class="gf-iconbtn" aria-label="<?php esc_attr_e( 'Fermer le menu', 'galsenfoot' ); ?>" data-gf-toggle="nav"><?php echo gf_icon( 'close', 24 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
				</div>
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'primary',
						'container'      => false,
						'menu_class'     => 'gf-menu',
						'depth'          => 2,
						'fallback_cb'    => 'gf_menu_fallback',
					)
				);
				echo gf_social_links(); // phpcs:ignore WordPress.Security.EscapeOutput
				?>
			</nav>
			<div class="gf-header__actions">
				<button type="button" class="gf-iconbtn" aria-controls="gf-search" aria-expanded="false" aria-label="<?php esc_attr_e( 'Rechercher', 'galsenfoot' ); ?>" data-gf-toggle="search"><?php echo gf_icon( 'search', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
				<button type="button" class="gf-iconbtn gf-theme-toggle" aria-label="<?php esc_attr_e( 'Changer de thème clair / sombre', 'galsenfoot' ); ?>" data-gf-theme><?php echo gf_icon( 'sun', 22 ) . gf_icon( 'moon', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
			</div>
		</div>
		<div class="gf-searchbar" id="gf-search" hidden>
			<div class="gf-container"><?php get_search_form(); ?></div>
		</div>
	</header>
	<div class="gf-backdrop" data-gf-toggle="nav" hidden></div>
	<?php
	if ( get_theme_mod( 'gf_ticker', 1 ) ) {
		$ticker = gf_league( 'gsf_render_ticker', array( 'limit' => 14 ) );
		if ( $ticker ) {
			echo '<div class="gf-tickerbar"><div class="gf-container">' . $ticker . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}
endif;

$header_ad = gf_ad( 'header' );
if ( $header_ad ) {
	echo '<div class="gf-container gf-ad-row">' . $header_ad . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
}
?>
<main id="main" class="gf-main">

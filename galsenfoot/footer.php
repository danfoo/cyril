<?php
/**
 * Footer.
 *
 * @package GalsenFoot
 */

defined( 'ABSPATH' ) || exit;
?>
</main>
<?php
$footer_ad = gf_ad( 'footer' );
if ( $footer_ad ) {
	echo '<div class="gf-container gf-ad-row">' . $footer_ad . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
}
if ( ! gf_elementor_location( 'footer' ) ) :
	$about = get_theme_mod( 'gf_footer_about', '' );
	$copy  = get_theme_mod( 'gf_footer_copy', '' );
	?>
	<footer class="gf-footer">
		<div class="gf-footer__stripe" aria-hidden="true"></div>
		<div class="gf-container gf-footer__grid">
			<div class="gf-footer__about">
				<?php echo gf_logo(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<p><?php echo esc_html( $about ? $about : get_bloginfo( 'description' ) ); ?></p>
				<?php echo gf_social_links(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</div>
			<nav class="gf-footer__col" aria-label="<?php esc_attr_e( 'Rubriques', 'galsenfoot' ); ?>">
				<h2 class="gf-footer__title"><?php esc_html_e( 'Rubriques', 'galsenfoot' ); ?></h2>
				<?php
				if ( has_nav_menu( 'footer' ) ) {
					wp_nav_menu(
						array(
							'theme_location' => 'footer',
							'container'      => false,
							'depth'          => 1,
							'menu_class'     => 'gf-footer__menu',
						)
					);
				} else {
					echo '<ul class="gf-footer__menu">';
					wp_list_categories(
						array(
							'title_li' => '',
							'number'   => 6,
							'orderby'  => 'count',
							'order'    => 'DESC',
							'depth'    => 1,
						)
					);
					echo '</ul>';
				}
				?>
			</nav>
			<?php if ( post_type_exists( 'gsf_match' ) ) : ?>
				<nav class="gf-footer__col" aria-label="<?php esc_attr_e( 'Championnat', 'galsenfoot' ); ?>">
					<h2 class="gf-footer__title"><?php esc_html_e( 'Championnat', 'galsenfoot' ); ?></h2>
					<ul class="gf-footer__menu">
						<?php if ( gf_page_url( 'standings' ) ) : ?>
							<li><a href="<?php echo esc_url( gf_page_url( 'standings' ) ); ?>"><?php esc_html_e( 'Classement', 'galsenfoot' ); ?></a></li>
						<?php endif; ?>
						<li><a href="<?php echo esc_url( gf_page_url( 'matches' ) ); ?>"><?php esc_html_e( 'Calendrier et résultats', 'galsenfoot' ); ?></a></li>
						<li><a href="<?php echo esc_url( get_post_type_archive_link( 'gsf_club' ) ); ?>"><?php esc_html_e( 'Clubs', 'galsenfoot' ); ?></a></li>
						<li><a href="<?php echo esc_url( get_post_type_archive_link( 'gsf_player' ) ); ?>"><?php esc_html_e( 'Joueurs', 'galsenfoot' ); ?></a></li>
					</ul>
				</nav>
			<?php endif; ?>
			<?php if ( is_active_sidebar( 'footer-1' ) ) : ?>
				<div class="gf-footer__col gf-footer__widgets"><?php dynamic_sidebar( 'footer-1' ); ?></div>
			<?php endif; ?>
		</div>
		<div class="gf-container gf-footer__bottom">
			<p><?php echo esc_html( $copy ? $copy : '© ' . wp_date( 'Y' ) . ' ' . get_bloginfo( 'name' ) ); ?></p>
			<?php
			if ( has_nav_menu( 'legal' ) ) {
				wp_nav_menu(
					array(
						'theme_location' => 'legal',
						'container'      => false,
						'depth'          => 1,
						'menu_class'     => 'gf-footer__legal',
					)
				);
			}
			if ( gf_ads_settings( 'consent' ) ) {
				echo '<a href="#" data-gf-consent-open>' . esc_html__( 'Gérer les cookies', 'galsenfoot' ) . '</a>';
			}
			?>
		</div>
	</footer>
<?php endif; ?>
<button type="button" class="gf-top" aria-label="<?php esc_attr_e( 'Retour en haut', 'galsenfoot' ); ?>" data-gf-top hidden><?php echo gf_icon( 'up', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
<?php wp_footer(); ?>
</body>
</html>

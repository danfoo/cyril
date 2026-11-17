<?php
/**
 * Blog index, archives fallback and search results.
 *
 * @package GalsenFoot
 */

defined( 'ABSPATH' ) || exit;

get_header();

if ( is_archive() && gf_elementor_location( 'archive' ) ) {
	get_footer();
	return;
}

$gf_league_archive = is_post_type_archive( array( 'gsf_club', 'gsf_player', 'gsf_match' ) ) || is_tax( array( 'gsf_competition', 'gsf_season' ) );
?>
<div class="gf-container">
	<header class="gf-pagehead">
		<?php gf_breadcrumb(); ?>
		<h1 class="gf-pagehead__title">
			<?php
			if ( is_search() ) {
				/* translators: %s search terms */
				printf( esc_html__( 'Résultats pour « %s »', 'galsenfoot' ), '<span>' . esc_html( get_search_query() ) . '</span>' );
			} elseif ( is_home() ) {
				echo esc_html( is_front_page() ? __( 'Toute l’actualité', 'galsenfoot' ) : get_the_title( (int) get_option( 'page_for_posts' ) ) );
			} else {
				echo wp_kses_post( get_the_archive_title() );
			}
			if ( is_paged() ) {
				/* translators: %d page number */
				echo ' <small>' . esc_html( sprintf( __( 'page %d', 'galsenfoot' ), (int) get_query_var( 'paged' ) ) ) . '</small>';
			}
			?>
		</h1>
		<?php
		if ( is_archive() && get_the_archive_description() && ! is_paged() ) {
			echo '<div class="gf-pagehead__desc">' . wp_kses_post( get_the_archive_description() ) . '</div>';
		}
		if ( is_search() ) {
			get_search_form();
		}
		?>
	</header>

	<?php if ( $gf_league_archive ) : ?>
		<div class="gf-section gf-section--flush">
			<?php get_template_part( 'template-parts/archive', 'league' ); ?>
		</div>
	<?php else : ?>
		<div class="gf-layout gf-section gf-section--flush">
			<div class="gf-layout__main">
				<?php if ( have_posts() ) : ?>
					<div class="gf-grid gf-grid--2">
						<?php
						$gf_n     = 0;
						$gf_every = max( 2, (int) gf_ads_settings( 'in_feed_every' ) );
						while ( have_posts() ) :
							the_post();
							++$gf_n;
							if ( in_array( get_post_type(), array( 'gsf_club', 'gsf_player' ), true ) ) {
								$gf_label = 'gsf_club' === get_post_type() ? __( 'Club', 'galsenfoot' ) : __( 'Joueur', 'galsenfoot' );
								echo '<article class="gf-card gf-card--entity gf-reveal"><a class="gf-card__media" href="' . esc_url( get_permalink() ) . '" tabindex="-1" aria-hidden="true">' . ( 'gsf_club' === get_post_type() ? gsf_crest( get_the_ID(), 72 ) : gsf_player_photo( get_the_ID() ) ) . '</a><div class="gf-card__body"><span class="gf-tag">' . esc_html( $gf_label ) . '</span><h2 class="gf-card__title"><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></h2></div></article>'; // phpcs:ignore WordPress.Security.EscapeOutput
							} else {
								echo gf_card( get_the_ID(), 'default', array( 'heading' => 'h2', 'class' => 'gf-reveal', 'eager' => 1 === $gf_n ) ); // phpcs:ignore WordPress.Security.EscapeOutput
							}
							if ( 0 === $gf_n % $gf_every ) {
								$gf_ad = gf_ad( 'in_feed' );
								if ( $gf_ad ) {
									echo '<div class="gf-grid__full">' . $gf_ad . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
								}
							}
						endwhile;
						?>
					</div>
					<?php gf_pagination(); ?>
				<?php else : ?>
					<div class="gf-empty">
						<p><?php esc_html_e( 'Aucun contenu ne correspond pour le moment.', 'galsenfoot' ); ?></p>
						<?php
						if ( ! is_search() ) {
							get_search_form();
						}
						?>
					</div>
				<?php endif; ?>
			</div>
			<?php get_sidebar(); ?>
		</div>
	<?php endif; ?>
</div>
<?php
get_footer();

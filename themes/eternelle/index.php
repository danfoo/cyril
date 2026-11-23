<?php
/**
 * Fallback template (blog, archives, search).
 *
 * @package Eternelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
get_header();
?>
<main class="et-page">
	<header class="et-page__head">
		<p class="et-kicker"><?php bloginfo( 'name' ); ?></p>
		<h1 class="et-page__title">
			<?php
			if ( is_search() ) {
				/* translators: %s search query */
				printf( esc_html__( 'Recherche : %s', 'eternelle' ), esc_html( get_search_query() ) );
			} elseif ( is_archive() ) {
				echo esc_html( wp_strip_all_tags( get_the_archive_title() ) );
			} else {
				esc_html_e( 'Le journal des mariés', 'eternelle' );
			}
			?>
		</h1>
		<?php echo eternelle_icon( 'flourish', 'et-title__flourish' ); // phpcs:ignore ?>
	</header>
	<div class="et-container">
		<?php if ( have_posts() ) : ?>
			<div class="et-posts">
				<?php
				while ( have_posts() ) :
					the_post();
					?>
					<article <?php post_class( 'et-post-card' ); ?> data-reveal>
						<?php if ( has_post_thumbnail() ) : ?>
							<a href="<?php the_permalink(); ?>" class="et-post-card__img"><?php the_post_thumbnail( 'medium_large' ); ?></a>
						<?php endif; ?>
						<div class="et-post-card__body">
							<p class="et-post-card__date"><?php echo esc_html( get_the_date() ); ?></p>
							<h2 class="et-post-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
							<div class="et-post-card__excerpt"><?php the_excerpt(); ?></div>
						</div>
					</article>
				<?php endwhile; ?>
			</div>
			<div class="et-pagination"><?php the_posts_pagination( array( 'mid_size' => 1 ) ); ?></div>
		<?php else : ?>
			<p class="et-center"><?php esc_html_e( 'Rien à afficher pour le moment.', 'eternelle' ); ?></p>
		<?php endif; ?>
	</div>
</main>
<?php
get_footer();

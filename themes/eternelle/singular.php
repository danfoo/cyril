<?php
/**
 * Pages & posts.
 *
 * @package Eternelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
get_header();

if ( ! eternelle_elementor_location( 'single' ) ) :
	while ( have_posts() ) :
		the_post();

		// Built with Elementor → full width, Elementor controls everything.
		if ( eternelle_built_with_elementor() ) :
			echo '<main class="et-elementor-content">';
			the_content();
			echo '</main>';
			continue;
		endif;
		?>
		<main class="et-page">
			<header class="et-page__head">
				<?php if ( is_single() ) : ?>
					<p class="et-kicker"><?php echo esc_html( get_the_date() ); ?></p>
				<?php else : ?>
					<p class="et-kicker"><?php echo esc_html( eternelle_mod( 'et_partner_a' ) . ' & ' . eternelle_mod( 'et_partner_b' ) ); ?></p>
				<?php endif; ?>
				<h1 class="et-page__title"><?php the_title(); ?></h1>
				<?php echo eternelle_icon( 'flourish', 'et-title__flourish' ); // phpcs:ignore ?>
			</header>
			<article <?php post_class( 'et-container et-container--narrow et-prose' ); ?>>
				<?php if ( has_post_thumbnail() ) : ?>
					<figure class="et-page__thumb"><?php the_post_thumbnail( 'large' ); ?></figure>
				<?php endif; ?>
				<?php
				the_content();
				wp_link_pages();
				?>
			</article>
			<?php if ( comments_open() || get_comments_number() ) : ?>
				<div class="et-container et-container--narrow"><?php comments_template(); ?></div>
			<?php endif; ?>
		</main>
		<?php
	endwhile;
endif;

get_footer();

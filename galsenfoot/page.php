<?php
/**
 * Page. Elementor pages are full width without the theme page title.
 *
 * @package GalsenFoot
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	if ( gf_is_elementor() ) {
		the_content();
		continue;
	}
	?>
	<article <?php post_class( 'gf-page' ); ?>>
		<header class="gf-container gf-pagehead">
			<?php gf_breadcrumb(); ?>
			<h1 class="gf-pagehead__title"><?php the_title(); ?></h1>
		</header>
		<div class="gf-container gf-section gf-section--flush">
			<div class="gf-prose gf-prose--wide">
				<?php
				the_content();
				wp_link_pages();
				?>
			</div>
			<?php
			if ( comments_open() || get_comments_number() ) {
				comments_template();
			}
			?>
		</div>
	</article>
	<?php
endwhile;

get_footer();

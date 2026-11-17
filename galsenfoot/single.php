<?php
/**
 * Single article, club, player or match.
 *
 * @package GalsenFoot
 */

defined( 'ABSPATH' ) || exit;

get_header();

if ( gf_elementor_location( 'single' ) ) {
	get_footer();
	return;
}

while ( have_posts() ) :
	the_post();

	if ( 0 === strpos( get_post_type(), 'gsf_' ) ) {
		get_template_part( 'template-parts/single', 'league' );
		continue;
	}

	$gf_category = gf_primary_category();
	?>
	<article <?php post_class( 'gf-article' ); ?>>
		<header class="gf-article__head">
			<div class="gf-container gf-container--narrow">
				<?php gf_breadcrumb(); ?>
				<?php if ( $gf_category ) : ?>
					<a class="gf-tag gf-tag--lg" href="<?php echo esc_url( get_category_link( $gf_category ) ); ?>"><?php echo esc_html( $gf_category->name ); ?></a>
				<?php endif; ?>
				<h1 class="gf-article__title"><?php the_title(); ?></h1>
				<?php if ( has_excerpt() ) : ?>
					<p class="gf-article__lead"><?php echo esc_html( get_the_excerpt() ); ?></p>
				<?php endif; ?>
				<div class="gf-article__meta">
					<?php echo get_avatar( get_the_author_meta( 'ID' ), 40, '', '', array( 'class' => 'gf-avatar' ) ); ?>
					<div>
						<a class="gf-article__author" href="<?php echo esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ); ?>" rel="author"><?php the_author(); ?></a>
						<p>
							<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() . ' · ' . get_the_time() ); ?></time>
							<?php if ( get_the_modified_time( 'U' ) - get_the_time( 'U' ) > HOUR_IN_SECONDS ) : ?>
								<?php /* translators: %s date */ ?>
								<span><?php echo esc_html( sprintf( __( 'Mis à jour le %s', 'galsenfoot' ), get_the_modified_date() ) ); ?></span>
							<?php endif; ?>
							<?php /* translators: %d minutes */ ?>
							<span><?php echo gf_icon( 'clock', 13 ) . ' ' . esc_html( sprintf( __( '%d min de lecture', 'galsenfoot' ), gf_reading_time() ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						</p>
					</div>
					<?php gf_share(); ?>
				</div>
			</div>
		</header>

		<?php if ( has_post_thumbnail() ) : ?>
			<figure class="gf-article__cover gf-container">
				<?php
				the_post_thumbnail(
					'gf-hero',
					array(
						'loading'       => 'eager',
						'fetchpriority' => 'high',
						'sizes'         => '(max-width: 1240px) 100vw, 1200px',
					)
				);
				$gf_caption = get_the_post_thumbnail_caption();
				if ( $gf_caption ) {
					echo '<figcaption>' . wp_kses_post( $gf_caption ) . '</figcaption>';
				}
				?>
			</figure>
		<?php endif; ?>

		<div class="gf-container gf-layout">
			<div class="gf-layout__main">
				<?php echo gf_ad( 'article_top' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<div class="gf-prose" data-gf-progress>
					<?php
					the_content();
					wp_link_pages(
						array(
							'before' => '<nav class="gf-pagelinks" aria-label="' . esc_attr__( 'Pages de l’article', 'galsenfoot' ) . '">',
							'after'  => '</nav>',
						)
					);
					?>
				</div>
				<?php
				$gf_tags = get_the_tags();
				if ( $gf_tags ) {
					echo '<ul class="gf-tags">';
					foreach ( $gf_tags as $gf_tag ) {
						echo '<li><a href="' . esc_url( get_tag_link( $gf_tag ) ) . '" rel="tag">#' . esc_html( $gf_tag->name ) . '</a></li>';
					}
					echo '</ul>';
				}
				gf_share();
				echo gf_ad( 'article_bottom' ); // phpcs:ignore WordPress.Security.EscapeOutput

				$gf_bio = get_the_author_meta( 'description' );
				if ( $gf_bio ) :
					?>
					<aside class="gf-author">
						<?php echo get_avatar( get_the_author_meta( 'ID' ), 72, '', '', array( 'class' => 'gf-avatar' ) ); ?>
						<div>
							<p class="gf-author__name"><a href="<?php echo esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ); ?>"><?php the_author(); ?></a></p>
							<p><?php echo esc_html( $gf_bio ); ?></p>
						</div>
					</aside>
					<?php
				endif;

				$gf_related = gf_post_ids(
					array(
						'count'    => 3,
						'category' => $gf_category ? $gf_category->term_id : 0,
						'exclude'  => array( get_the_ID() ),
					)
				);
				if ( $gf_related ) {
					echo '<section class="gf-related">' . gf_heading( __( 'À lire aussi', 'galsenfoot' ) ) . '<div class="gf-grid gf-grid--3">'; // phpcs:ignore WordPress.Security.EscapeOutput
					foreach ( $gf_related as $gf_id ) {
						echo gf_card( $gf_id, 'default', array( 'class' => 'gf-reveal', 'excerpt' => false ) ); // phpcs:ignore WordPress.Security.EscapeOutput
					}
					echo '</div></section>';
				}

				if ( comments_open() || get_comments_number() ) {
					comments_template();
				}
				?>
			</div>
			<?php get_sidebar(); ?>
		</div>
	</article>
	<?php
endwhile;

get_footer();

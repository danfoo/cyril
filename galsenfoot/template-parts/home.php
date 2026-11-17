<?php
/**
 * Default home page composition.
 *
 * @package GalsenFoot
 */

defined( 'ABSPATH' ) || exit;

$gf_hero = array();
$gf_sticky = array_filter( array_map( 'intval', (array) get_option( 'sticky_posts' ) ) );
if ( $gf_sticky ) {
	$gf_hero = get_posts(
		array(
			'post__in'            => $gf_sticky,
			'posts_per_page'      => 5,
			'fields'              => 'ids',
			'ignore_sticky_posts' => true,
		)
	);
}
if ( count( $gf_hero ) < 5 ) {
	$gf_hero = array_merge( $gf_hero, gf_post_ids( array( 'count' => 5 - count( $gf_hero ), 'exclude' => $gf_hero ) ) );
}
$gf_league = function_exists( 'gsf_render_standings' );
$gf_seen   = $gf_hero;
?>
<h1 class="screen-reader-text"><?php echo esc_html( get_bloginfo( 'name' ) . ( get_bloginfo( 'description' ) ? ' — ' . get_bloginfo( 'description' ) : '' ) ); ?></h1>

<?php if ( $gf_hero ) : ?>
	<section class="gf-section gf-section--hero" aria-label="<?php esc_attr_e( 'À la une', 'galsenfoot' ); ?>">
		<div class="gf-container"><?php echo gf_hero_mosaic( $gf_hero ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
	</section>
<?php endif; ?>

<?php
$gf_ad = gf_ad( 'home_top' );
if ( $gf_ad ) {
	echo '<div class="gf-container gf-ad-row">' . $gf_ad . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
}
?>

<?php if ( $gf_league ) : ?>
	<section class="gf-section gf-section--league">
		<div class="gf-container gf-layout">
			<div class="gf-layout__main">
				<div class="gf-reveal">
					<?php
					echo gf_heading( __( 'L’affiche', 'galsenfoot' ), gf_page_url( 'matches' ), __( 'Calendrier', 'galsenfoot' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
					echo gf_league( 'gsf_render_next_match' ); // phpcs:ignore WordPress.Security.EscapeOutput
					?>
				</div>
				<div class="gf-tabs gf-reveal" data-gf-tabs>
					<div class="gf-tabs__list" role="tablist" aria-label="<?php esc_attr_e( 'Matchs', 'galsenfoot' ); ?>">
						<button type="button" role="tab" id="gf-tab-results" aria-controls="gf-panel-results" aria-selected="true"><?php esc_html_e( 'Résultats', 'galsenfoot' ); ?></button>
						<button type="button" role="tab" id="gf-tab-fixtures" aria-controls="gf-panel-fixtures" aria-selected="false" tabindex="-1"><?php esc_html_e( 'Prochains matchs', 'galsenfoot' ); ?></button>
					</div>
					<div role="tabpanel" id="gf-panel-results" aria-labelledby="gf-tab-results"><?php echo gf_league( 'gsf_render_matches', array( 'type' => 'results', 'limit' => 7 ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
					<div role="tabpanel" id="gf-panel-fixtures" aria-labelledby="gf-tab-fixtures" hidden><?php echo gf_league( 'gsf_render_matches', array( 'type' => 'fixtures', 'limit' => 7 ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
				</div>
			</div>
			<aside class="gf-layout__side" aria-label="<?php esc_attr_e( 'Classements', 'galsenfoot' ); ?>">
				<div class="gf-reveal">
					<?php
					echo gf_heading( __( 'Classement', 'galsenfoot' ), gf_page_url( 'standings' ), __( 'Complet', 'galsenfoot' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
					echo gf_league( 'gsf_render_standings', array( 'compact' => 1, 'limit' => 10 ) ); // phpcs:ignore WordPress.Security.EscapeOutput
					?>
				</div>
				<div class="gf-reveal">
					<?php
					echo gf_heading( __( 'Buteurs', 'galsenfoot' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
					echo gf_league( 'gsf_render_leaders', array( 'limit' => 5 ) ); // phpcs:ignore WordPress.Security.EscapeOutput
					?>
				</div>
				<?php echo gf_ad( 'sidebar' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</aside>
		</div>
	</section>
<?php endif; ?>

<?php
$gf_latest = gf_post_ids( array( 'count' => 6, 'exclude' => $gf_seen ) );
if ( $gf_latest ) :
	$gf_seen     = array_merge( $gf_seen, $gf_latest );
	$gf_blog_url = 'page' === get_option( 'show_on_front' ) && get_option( 'page_for_posts' ) ? get_permalink( (int) get_option( 'page_for_posts' ) ) : '';
	?>
	<section class="gf-section">
		<div class="gf-container">
			<?php echo gf_heading( __( 'Dernières infos', 'galsenfoot' ), $gf_blog_url ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<div class="gf-grid gf-grid--3">
				<?php
				foreach ( $gf_latest as $gf_id ) {
					echo gf_card( $gf_id, 'default', array( 'class' => 'gf-reveal' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
				}
				?>
			</div>
		</div>
	</section>
<?php endif; ?>

<?php
$gf_ad = gf_ad( 'home_middle' );
if ( $gf_ad ) {
	echo '<div class="gf-container gf-ad-row">' . $gf_ad . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
}

$gf_categories = get_categories(
	array(
		'orderby' => 'count',
		'order'   => 'DESC',
		'number'  => 3,
		'exclude' => array( (int) get_option( 'default_category' ) ),
	)
);
foreach ( $gf_categories as $gf_i => $gf_category ) :
	$gf_ids = gf_post_ids( array( 'count' => 4, 'category' => $gf_category->term_id ) );
	if ( count( $gf_ids ) < 2 ) {
		continue;
	}
	?>
	<section class="gf-section<?php echo 0 === $gf_i % 2 ? ' gf-section--alt' : ''; ?>">
		<div class="gf-container">
			<?php echo gf_heading( $gf_category->name, get_category_link( $gf_category ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<div class="gf-feature">
				<?php
				echo gf_card( $gf_ids[0], 'overlay', array( 'class' => 'gf-reveal', 'size' => 'gf-hero' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
				echo '<div class="gf-stack">';
				foreach ( array_slice( $gf_ids, 1 ) as $gf_id ) {
					echo gf_card( $gf_id, 'row', array( 'class' => 'gf-reveal' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
				}
				echo '</div>';
				?>
			</div>
		</div>
	</section>
<?php endforeach; ?>

<?php if ( $gf_league ) : ?>
	<section class="gf-section">
		<div class="gf-container gf-reveal">
			<?php
			echo gf_heading( __( 'Les clubs', 'galsenfoot' ), get_post_type_archive_link( 'gsf_club' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo gf_league( 'gsf_render_clubs', array( 'layout' => 'strip' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			?>
		</div>
	</section>
<?php endif; ?>

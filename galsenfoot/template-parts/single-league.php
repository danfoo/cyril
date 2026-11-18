<?php
/**
 * Club, player and match pages. The body (facts, statistics, squad, timeline…)
 * is produced by the GalsenFoot League plugin through the content filter.
 *
 * @package GalsenFoot
 */

defined( 'ABSPATH' ) || exit;

$gf_type = get_post_type();
$gf_id   = get_the_ID();
?>
<article <?php post_class( 'gf-profile gf-profile--' . str_replace( 'gsf_', '', $gf_type ) ); ?>>
	<?php
	if ( 'gsf_match' === $gf_type ) :
		?>
		<header class="gf-container gf-pagehead">
			<?php gf_breadcrumb(); ?>
			<h1 class="gf-pagehead__title gf-pagehead__title--sm"><?php the_title(); ?></h1>
		</header>
		<?php
	elseif ( function_exists( 'gsf_club' ) ) :
		$gf_club_id = 'gsf_club' === $gf_type ? $gf_id : (int) get_post_meta( $gf_id, '_gsf_club', true );
		$gf_nt_id   = 'gsf_player' === $gf_type ? (int) get_post_meta( $gf_id, '_gsf_national_team', true ) : 0;
		// A player listed only with his national team takes its colours and number.
		$gf_use_nt  = $gf_nt_id && ! $gf_club_id;
		$gf_club    = gsf_club( $gf_use_nt ? $gf_nt_id : $gf_club_id );
		$gf_pos     = gsf_positions()[ get_post_meta( $gf_id, '_gsf_position', true ) ] ?? '';
		$gf_number  = function_exists( 'gsf_player_number' ) ? gsf_player_number( $gf_id, $gf_use_nt ) : get_post_meta( $gf_id, '_gsf_number', true );
		?>
		<header class="gf-profile__head" style="--gf-club:<?php echo esc_attr( $gf_club['color'] ); ?>">
			<div class="gf-container">
				<?php gf_breadcrumb(); ?>
				<div class="gf-profile__row">
					<div class="gf-profile__media">
						<?php
						if ( 'gsf_club' === $gf_type ) {
							echo gsf_crest( $gf_id, 140 ); // phpcs:ignore WordPress.Security.EscapeOutput
						} else {
							echo gsf_player_photo( $gf_id, 'gf-square' ); // phpcs:ignore WordPress.Security.EscapeOutput
						}
						?>
					</div>
					<div class="gf-profile__id">
						<p class="gf-profile__kicker">
							<?php
							if ( 'gsf_club' === $gf_type ) {
								echo esc_html( $gf_club['national'] ? __( 'Équipe nationale', 'galsenfoot' ) : get_post_meta( $gf_id, '_gsf_city', true ) );
							} else {
								echo esc_html( trim( $gf_pos . ( $gf_club['id'] ? ' · ' . $gf_club['name'] : '' ), ' ·' ) );
							}
							?>
						</p>
						<h1 class="gf-profile__name"><?php the_title(); ?></h1>
					</div>
					<?php if ( 'gsf_player' === $gf_type && '' !== $gf_number ) : ?>
						<span class="gf-profile__number" aria-hidden="true"><?php echo (int) $gf_number; ?></span>
					<?php elseif ( 'gsf_player' === $gf_type && $gf_club['id'] ) : ?>
						<span class="gf-profile__crest"><?php echo gsf_crest( $gf_club['id'], 80 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					<?php endif; ?>
				</div>
			</div>
		</header>
	<?php endif; ?>

	<div class="gf-container gf-profile__body">
		<?php
		the_content();
		if ( 'gsf_match' === $gf_type ) {
			gf_share();
			if ( comments_open() || get_comments_number() ) {
				comments_template();
			}
		}
		?>
	</div>
</article>

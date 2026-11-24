<?php
/**
 * Animated hero.
 *
 * Accepts optional $args (used by the Elementor widget):
 * kicker, quote, cta, cta_url, image (URL), countdown, monogram, scroll, full, wave, venue.
 *
 * @package Eternelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$args = wp_parse_args(
	isset( $args ) && is_array( $args ) ? $args : array(),
	array(
		'kicker'    => eternelle_mod( 'et_hero_kicker' ),
		'quote'     => eternelle_mod( 'et_hero_quote' ),
		'cta'       => eternelle_mod( 'et_show_rsvp' ) ? eternelle_mod( 'et_hero_cta' ) : '',
		'cta_url'   => '#rsvp',
		'image'     => eternelle_image_url( 'et_hero_image', 'eternelle-hero' ),
		'countdown' => true,
		'monogram'  => true,
		'scroll'    => true,
		'full'      => true,
		'wave'      => true,
		'venue'     => true,
		'id'        => 'accueil',
	)
);
$img = $args['image'];
$ini = eternelle_initials();
$a   = eternelle_mod( 'et_partner_a' );
$b   = eternelle_mod( 'et_partner_b' );

$letters = function ( $name, $start ) {
	$out   = '';
	$chars = preg_split( '//u', $name, -1, PREG_SPLIT_NO_EMPTY );
	foreach ( $chars as $i => $ch ) {
		$out .= '<span style="--i:' . ( $start + $i ) . '">' . ( ' ' === $ch ? '&nbsp;' : esc_html( $ch ) ) . '</span>';
	}
	return $out;
};
$len_a   = count( preg_split( '//u', $a, -1, PREG_SPLIT_NO_EMPTY ) );
$classes = 'et-hero' . ( $img ? ' has-image' : '' ) . ( $args['full'] ? '' : ' et-hero--auto' ) . ( $args['wave'] ? '' : ' et-hero--nowave' );
$first   = (string) key( eternelle_sections() );
?>
<section class="<?php echo esc_attr( $classes ); ?>"<?php echo $args['id'] ? ' id="' . esc_attr( $args['id'] ) . '"' : ''; ?> data-hero>
	<div class="et-hero__media" aria-hidden="true">
		<?php if ( $img ) : ?>
			<div class="et-hero__img" style="background-image:url('<?php echo esc_url( $img ); ?>')" data-parallax="0.35"></div>
		<?php else : ?>
			<div class="et-hero__aurora"><i></i><i></i><i></i></div>
		<?php endif; ?>
		<div class="et-hero__veil"></div>
		<canvas class="et-hero__fx" data-fx></canvas>
	</div>

	<div class="et-hero__content">
		<?php if ( $args['monogram'] ) : ?>
		<div class="et-monogram" aria-hidden="true">
			<svg viewBox="0 0 200 200">
				<defs>
					<linearGradient id="etGold" x1="0" y1="0" x2="1" y2="1">
						<stop offset="0" stop-color="#f3dfb5"/><stop offset=".5" stop-color="var(--et-gold)"/><stop offset="1" stop-color="#8c6a36"/>
					</linearGradient>
				</defs>
				<circle class="et-mono__ring" cx="100" cy="100" r="88"/>
				<circle class="et-mono__ring et-mono__ring--2" cx="100" cy="100" r="80"/>
				<g class="et-mono__leaves">
					<path d="M100 186c-30-2-58-20-70-50 14 6 22 4 30-2-14-2-26-12-30-24 12 4 22 2 28-4-12-6-20-18-20-30 10 8 20 8 28 4"/>
					<path d="M100 186c30-2 58-20 70-50-14 6-22 4-30-2 14-2 26-12 30-24-12 4-22 2-28-4 12-6 20-18 20-30-10 8-20 8-28 4"/>
				</g>
				<text x="100" y="114" text-anchor="middle" class="et-mono__txt"><?php echo esc_html( $ini['a'] . ' & ' . $ini['b'] ); ?></text>
			</svg>
		</div>
		<?php endif; ?>

		<?php if ( $args['kicker'] ) : ?>
			<p class="et-hero__kicker"><span></span><?php echo esc_html( $args['kicker'] ); ?><span></span></p>
		<?php endif; ?>
		<h1 class="et-hero__names">
			<span class="et-hero__name"><?php echo $letters( $a, 0 ); // phpcs:ignore ?></span>
			<span class="et-hero__amp" style="--i:<?php echo (int) $len_a; ?>">&amp;</span>
			<span class="et-hero__name"><?php echo $letters( $b, $len_a + 1 ); // phpcs:ignore ?></span>
		</h1>
		<p class="et-hero__date"><span class="et-shimmer"><?php echo esc_html( eternelle_date( 'j F Y' ) ); ?></span></p>
		<?php if ( $args['venue'] ) : ?>
			<p class="et-hero__venue"><?php echo esc_html( eternelle_mod( 'et_venue' ) ); ?></p>
		<?php endif; ?>
		<?php if ( $args['quote'] ) : ?>
			<p class="et-hero__quote">« <?php echo esc_html( $args['quote'] ); ?> »</p>
		<?php endif; ?>

		<?php if ( $args['countdown'] ) : ?>
			<?php get_template_part( 'template-parts/countdown' ); ?>
		<?php endif; ?>

		<?php if ( $args['cta'] ) : ?>
			<a class="et-btn et-btn--glow" href="<?php echo esc_url( $args['cta_url'] ); ?>"><?php echo esc_html( $args['cta'] ); ?></a>
		<?php endif; ?>
	</div>

	<?php if ( $args['scroll'] && $first ) : ?>
		<a class="et-scroll" href="#<?php echo esc_attr( $first ); ?>" aria-label="<?php esc_attr_e( 'Défiler', 'eternelle' ); ?>"><span></span></a>
	<?php endif; ?>
	<?php if ( $args['wave'] ) : ?>
		<svg class="et-hero__wave" viewBox="0 0 1440 120" preserveAspectRatio="none" aria-hidden="true"><path d="M0 60c240 50 480 70 720 40s480-90 720-40v60H0z"/></svg>
	<?php endif; ?>
</section>

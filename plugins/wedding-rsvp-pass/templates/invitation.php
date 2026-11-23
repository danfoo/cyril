<?php
/**
 * Invitation card with QR code. Override in your theme: wedding-rsvp-pass/invitation.php
 *
 * @var object $guest
 * @var array  $card
 * @var bool   $welcome
 * @package WeddingRSVPPass
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$page_title = sprintf( __( 'Carton d’invitation – %s', 'wedding-rsvp-pass' ), $card['names'] );
$body_class = 'wrp-page--card' . ( $welcome ? ' is-welcome' : '' );
require WRP_DIR . 'templates/partials/head.php';

$js_card = array(
	'names'       => $card['names'],
	'a'           => $card['partner_a'],
	'b'           => $card['partner_b'],
	'inviteLine'  => $card['invite_line'],
	'date'        => ucfirst( $card['date'] ),
	'time'        => $card['time'],
	'venue'       => $card['venue'],
	'address'     => $card['address'],
	'guest'       => $card['guest_name'],
	'companions'  => $card['companions'],
	'seats'       => $card['seats'],
	'table'       => $card['table'],
	'code'        => $card['code_pretty'],
	'qr'          => $card['qr_payload'],
	'quote'       => $card['quote'],
	'accent'      => $card['accent'],
	'dark'        => $card['dark'],
	'blush'       => $card['blush'],
	'start'       => gmdate( 'Ymd\THis\Z', $card['timestamp'] ),
	'end'         => gmdate( 'Ymd\THis\Z', $card['timestamp'] + 10 * HOUR_IN_SECONDS ),
	'file'        => sanitize_file_name( 'invitation-' . remove_accents( $card['guest_name'] ) ),
	'labels'      => array(
		'nominative' => __( 'Carton nominatif', 'wedding-rsvp-pass' ),
		'seats'      => _n( 'Valable pour %d personne', 'Valable pour %d personnes', $card['seats'], 'wedding-rsvp-pass' ),
		'table'      => __( 'Table', 'wedding-rsvp-pass' ),
		'present'    => __( 'Présentez ce QR code à l’entrée', 'wedding-rsvp-pass' ),
		'calTitle'   => sprintf( __( 'Mariage de %s', 'wedding-rsvp-pass' ), $card['names'] ),
	),
);
?>
<main class="wrp-stage wrp-stage--card">
	<?php if ( $welcome ) : ?>
		<div class="wrp-welcome wrp-reveal">
			<p class="wrp-kicker"><?php esc_html_e( 'Merci !', 'wedding-rsvp-pass' ); ?></p>
			<h1><?php echo esc_html( sprintf( __( 'Nous avons hâte de vous voir, %s ♥', 'wedding-rsvp-pass' ), $guest->first_name ) ); ?></h1>
			<p><?php esc_html_e( 'Voici votre carton d’invitation personnel. Gardez-le précieusement : il sera scanné à votre arrivée.', 'wedding-rsvp-pass' ); ?></p>
		</div>
	<?php endif; ?>

	<div class="wrp-invite-wrap">
		<div class="wrp-invite wrp-reveal" id="wrp-invite">
			<div class="wrp-invite__frame" aria-hidden="true"></div>
			<span class="wrp-corner wrp-corner--tl" aria-hidden="true"></span>
			<span class="wrp-corner wrp-corner--tr" aria-hidden="true"></span>
			<span class="wrp-corner wrp-corner--bl" aria-hidden="true"></span>
			<span class="wrp-corner wrp-corner--br" aria-hidden="true"></span>

			<p class="wrp-kicker"><?php esc_html_e( 'Invitation', 'wedding-rsvp-pass' ); ?></p>
			<h1 class="wrp-names wrp-names--card">
				<span><?php echo esc_html( $card['partner_a'] ); ?></span>
				<em>&amp;</em>
				<span><?php echo esc_html( $card['partner_b'] ); ?></span>
			</h1>
			<p class="wrp-invite__line"><?php echo esc_html( $card['invite_line'] ); ?></p>
			<?php require WRP_DIR . 'templates/partials/ornament.php'; ?>
			<p class="wrp-invite__date"><?php echo esc_html( ucfirst( $card['date'] ) ); ?></p>
			<p class="wrp-invite__time"><?php echo esc_html( sprintf( __( 'à %s', 'wedding-rsvp-pass' ), $card['time'] ) ); ?></p>
			<p class="wrp-invite__venue"><?php echo esc_html( $card['venue'] ); ?><?php if ( $card['address'] ) : ?><br><small><?php echo esc_html( $card['address'] ); ?></small><?php endif; ?></p>

			<div class="wrp-invite__guest">
				<span class="wrp-invite__label"><?php esc_html_e( 'Carton nominatif', 'wedding-rsvp-pass' ); ?></span>
				<strong><?php echo esc_html( $card['guest_name'] ); ?></strong>
				<?php if ( $card['companions'] ) : ?>
					<span class="wrp-invite__comp">&amp; <?php echo esc_html( implode( ', ', $card['companions'] ) ); ?></span>
				<?php endif; ?>
				<span class="wrp-invite__seats">
					<?php echo esc_html( sprintf( _n( 'Valable pour %d personne', 'Valable pour %d personnes', $card['seats'], 'wedding-rsvp-pass' ), $card['seats'] ) ); ?>
					<?php if ( $card['table'] ) : ?>
						· <?php echo esc_html( sprintf( __( 'Table : %s', 'wedding-rsvp-pass' ), $card['table'] ) ); ?>
					<?php endif; ?>
				</span>
			</div>

			<div class="wrp-invite__qr">
				<div class="wrp-qrbox" data-wrp-qr="<?php echo esc_attr( $card['qr_payload'] ); ?>" role="img" aria-label="<?php esc_attr_e( 'QR code d’accès', 'wedding-rsvp-pass' ); ?>"></div>
				<span class="wrp-invite__code"><?php echo esc_html( $card['code_pretty'] ); ?></span>
			</div>

			<?php if ( $card['quote'] ) : ?>
				<p class="wrp-invite__quote">« <?php echo esc_html( $card['quote'] ); ?> »</p>
			<?php endif; ?>
			<?php if ( $card['checked_in'] ) : ?>
				<div class="wrp-stamp" aria-label="<?php esc_attr_e( 'Déjà validé', 'wedding-rsvp-pass' ); ?>"><?php esc_html_e( 'Bienvenue', 'wedding-rsvp-pass' ); ?> ♥</div>
			<?php endif; ?>
		</div>
	</div>

	<div class="wrp-actions-bar">
		<button type="button" class="wrp-button" data-wrp-download><span aria-hidden="true">⤓</span> <?php esc_html_e( 'Télécharger (image)', 'wedding-rsvp-pass' ); ?></button>
		<button type="button" class="wrp-button wrp-button--ghost" onclick="window.print()"><span aria-hidden="true">⎙</span> <?php esc_html_e( 'Imprimer / PDF', 'wedding-rsvp-pass' ); ?></button>
		<button type="button" class="wrp-button wrp-button--ghost" data-wrp-ics><span aria-hidden="true">📅</span> <?php esc_html_e( 'Ajouter au calendrier', 'wedding-rsvp-pass' ); ?></button>
		<?php if ( $card['map_url'] ) : ?>
			<a class="wrp-button wrp-button--ghost" href="<?php echo esc_url( $card['map_url'] ); ?>" target="_blank" rel="noopener"><span aria-hidden="true">📍</span> <?php esc_html_e( 'Itinéraire', 'wedding-rsvp-pass' ); ?></a>
		<?php endif; ?>
	</div>
	<p class="wrp-hint"><?php esc_html_e( 'Astuce : téléchargez l’image ou faites une capture d’écran, votre QR code fonctionnera même sans réseau le jour J.', 'wedding-rsvp-pass' ); ?></p>
	<p class="wrp-hint"><a href="<?php echo esc_url( $card['rsvp_url'] ); ?>"><?php esc_html_e( 'Modifier ma réponse', 'wedding-rsvp-pass' ); ?></a></p>
</main>
<script>window.WRP_CARD = <?php echo wp_json_encode( $js_card ); ?>;</script>
<script src="<?php echo esc_url( WRP_Public::asset( 'vendor/qrcode.js' ) ); ?>" defer></script>
<script src="<?php echo esc_url( WRP_Public::asset( 'js/public.js' ) ); ?>" defer></script>
</body>
</html>

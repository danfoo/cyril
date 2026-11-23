<?php
/**
 * RSVP page. Override in your theme: wedding-rsvp-pass/rsvp.php
 *
 * @var object $guest
 * @var array  $wedding
 * @var string $error
 * @var bool   $closed
 * @var bool   $can_change
 * @var bool   $just_declined
 * @package WeddingRSVPPass
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$page_title = sprintf( __( 'Mariage de %s – Votre réponse', 'wedding-rsvp-pass' ), $wedding['names'] );
$body_class = 'wrp-page--rsvp';
require WRP_DIR . 'templates/partials/head.php';

$companions = WRP_Guests::companions( $guest );
$max        = max( 1, (int) $guest->seats_allowed );
$current    = 'confirmed' === $guest->status ? max( 1, (int) $guest->seats_confirmed ) : $max;
$answered   = in_array( $guest->status, array( 'confirmed', 'declined' ), true );
$show_form  = ! $closed && ( ! $answered || $error );
?>
<main class="wrp-stage">
	<article class="wrp-paper wrp-reveal">
		<span class="wrp-corner wrp-corner--tl" aria-hidden="true"></span>
		<span class="wrp-corner wrp-corner--tr" aria-hidden="true"></span>
		<span class="wrp-corner wrp-corner--bl" aria-hidden="true"></span>
		<span class="wrp-corner wrp-corner--br" aria-hidden="true"></span>

		<p class="wrp-kicker"><?php esc_html_e( 'Le mariage de', 'wedding-rsvp-pass' ); ?></p>
		<h1 class="wrp-names">
			<span><?php echo esc_html( $wedding['partner_a'] ); ?></span>
			<em>&amp;</em>
			<span><?php echo esc_html( $wedding['partner_b'] ); ?></span>
		</h1>
		<?php require WRP_DIR . 'templates/partials/ornament.php'; ?>
		<p class="wrp-date"><?php echo esc_html( ucfirst( $wedding['date'] ) ); ?> · <?php echo esc_html( $wedding['time'] ); ?></p>
		<p class="wrp-venue">
			<?php echo esc_html( $wedding['venue'] ); ?>
			<?php if ( $wedding['address'] ) : ?>
				<br><small><?php echo esc_html( $wedding['address'] ); ?></small>
			<?php endif; ?>
		</p>

		<div class="wrp-letter">
			<p class="wrp-hello"><?php echo esc_html( sprintf( __( 'Cher·e %s,', 'wedding-rsvp-pass' ), $guest->first_name ) ); ?></p>

			<?php if ( $error ) : ?>
				<p class="wrp-alert" role="alert"><?php echo esc_html( $error ); ?></p>
			<?php endif; ?>

			<?php if ( 'confirmed' === $guest->status && ! $error ) : ?>
				<div class="wrp-state wrp-state--yes">
					<div class="wrp-state__icon" aria-hidden="true">♥</div>
					<p><?php echo esc_html( sprintf( _n( 'Votre présence est confirmée pour %d personne. Nous avons tellement hâte !', 'Votre présence est confirmée pour %d personnes. Nous avons tellement hâte !', (int) $guest->seats_confirmed, 'wedding-rsvp-pass' ), (int) $guest->seats_confirmed ) ); ?></p>
					<a class="wrp-button" href="<?php echo esc_url( WRP_Guests::card_url( $guest ) ); ?>"><?php esc_html_e( 'Voir mon carton d’invitation', 'wedding-rsvp-pass' ); ?></a>
					<?php if ( $can_change && ! $closed ) : ?>
						<button type="button" class="wrp-link" data-wrp-edit><?php esc_html_e( 'Modifier ma réponse', 'wedding-rsvp-pass' ); ?></button>
					<?php endif; ?>
				</div>
			<?php elseif ( 'declined' === $guest->status && ! $error ) : ?>
				<div class="wrp-state wrp-state--no">
					<div class="wrp-state__icon" aria-hidden="true">✉</div>
					<p>
						<?php if ( $just_declined ) : ?>
							<?php esc_html_e( 'Merci pour votre réponse. Vous allez beaucoup nous manquer, nous penserons fort à vous ce jour-là.', 'wedding-rsvp-pass' ); ?>
						<?php else : ?>
							<?php esc_html_e( 'Vous nous avez indiqué ne pas pouvoir être présent·e. Vous nous manquerez !', 'wedding-rsvp-pass' ); ?>
						<?php endif; ?>
					</p>
					<?php if ( ! $closed ) : ?>
						<button type="button" class="wrp-link" data-wrp-edit><?php esc_html_e( 'Finalement, je peux venir !', 'wedding-rsvp-pass' ); ?></button>
					<?php endif; ?>
				</div>
			<?php elseif ( $closed ) : ?>
				<div class="wrp-state">
					<p><?php esc_html_e( 'La période de réponse est terminée. Pour toute question, contactez directement les mariés.', 'wedding-rsvp-pass' ); ?></p>
				</div>
			<?php else : ?>
				<p class="wrp-intro">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: couple names 2: invitation line 3: deadline */
							__( '%1$s %2$s. Nous serions honorés de vous compter parmi nous ! Merci de nous répondre avant le %3$s.', 'wedding-rsvp-pass' ),
							$wedding['names'],
							$wedding['invite_line'],
							$wedding['deadline']
						)
					);
					?>
				</p>
			<?php endif; ?>

			<?php if ( ! $closed ) : ?>
			<form method="post" class="wrp-form" data-wrp-form <?php echo $show_form ? '' : 'hidden'; ?> novalidate>
				<?php wp_nonce_field( 'wrp_rsvp_' . $guest->token, 'wrp_nonce' ); ?>
				<h2 class="wrp-question"><?php esc_html_e( 'Serez-vous des nôtres ?', 'wedding-rsvp-pass' ); ?></h2>

				<fieldset class="wrp-choices">
					<legend class="screen-reader-text"><?php esc_html_e( 'Votre réponse', 'wedding-rsvp-pass' ); ?></legend>
					<label class="wrp-choice wrp-choice--yes">
						<input type="radio" name="wrp_answer" value="yes" <?php checked( 'confirmed' === $guest->status ); ?>>
						<span class="wrp-choice__inner">
							<span class="wrp-choice__icon" aria-hidden="true">💍</span>
							<strong><?php esc_html_e( 'Avec joie !', 'wedding-rsvp-pass' ); ?></strong>
							<small><?php esc_html_e( 'Je serai présent·e', 'wedding-rsvp-pass' ); ?></small>
						</span>
					</label>
					<label class="wrp-choice wrp-choice--no">
						<input type="radio" name="wrp_answer" value="no" <?php checked( 'declined' === $guest->status ); ?>>
						<span class="wrp-choice__inner">
							<span class="wrp-choice__icon" aria-hidden="true">🕊️</span>
							<strong><?php esc_html_e( 'À regret', 'wedding-rsvp-pass' ); ?></strong>
							<small><?php esc_html_e( 'Je ne pourrai pas venir', 'wedding-rsvp-pass' ); ?></small>
						</span>
					</label>
				</fieldset>

				<div class="wrp-yes" data-wrp-yes hidden>
					<?php if ( $max > 1 ) : ?>
						<div class="wrp-seats">
							<span class="wrp-label"><?php esc_html_e( 'Nous serons', 'wedding-rsvp-pass' ); ?></span>
							<div class="wrp-stepper" data-max="<?php echo (int) $max; ?>">
								<button type="button" data-step="-1" aria-label="<?php esc_attr_e( 'Moins', 'wedding-rsvp-pass' ); ?>">−</button>
								<input type="number" name="wrp_seats" min="1" max="<?php echo (int) $max; ?>" value="<?php echo (int) $current; ?>" inputmode="numeric" aria-label="<?php esc_attr_e( 'Nombre de personnes', 'wedding-rsvp-pass' ); ?>">
								<button type="button" data-step="1" aria-label="<?php esc_attr_e( 'Plus', 'wedding-rsvp-pass' ); ?>">+</button>
							</div>
							<span class="wrp-label"><?php echo esc_html( sprintf( _n( 'sur %d place réservée', 'sur %d places réservées', $max, 'wedding-rsvp-pass' ), $max ) ); ?></span>
						</div>
						<?php if ( WRP_Settings::get( 'allow_companion_names' ) ) : ?>
							<div class="wrp-companions" data-wrp-companions>
								<?php for ( $i = 0; $i < $max - 1; $i++ ) : ?>
									<label class="wrp-field" data-index="<?php echo (int) $i; ?>">
										<span><?php echo esc_html( sprintf( __( 'Prénom et nom de l’accompagnant·e %d', 'wedding-rsvp-pass' ), $i + 1 ) ); ?></span>
										<input type="text" name="wrp_companions[]" value="<?php echo esc_attr( isset( $companions[ $i ] ) ? $companions[ $i ] : '' ); ?>" autocomplete="off">
									</label>
								<?php endfor; ?>
							</div>
						<?php endif; ?>
					<?php else : ?>
						<input type="hidden" name="wrp_seats" value="1">
					<?php endif; ?>
					<label class="wrp-field">
						<span><?php esc_html_e( 'Allergies ou régime alimentaire', 'wedding-rsvp-pass' ); ?></span>
						<input type="text" name="wrp_dietary" value="<?php echo esc_attr( $guest->dietary ); ?>" placeholder="<?php esc_attr_e( 'Végétarien, sans gluten…', 'wedding-rsvp-pass' ); ?>">
					</label>
				</div>

				<label class="wrp-field">
					<span><?php esc_html_e( 'Un petit mot pour les mariés', 'wedding-rsvp-pass' ); ?></span>
					<textarea name="wrp_message" rows="3" placeholder="<?php esc_attr_e( 'Vos vœux, une anecdote, une chanson pour danser…', 'wedding-rsvp-pass' ); ?>"><?php echo esc_textarea( $guest->guest_message ); ?></textarea>
				</label>

				<button type="submit" class="wrp-button wrp-button--big" data-wrp-submit disabled>
					<span><?php esc_html_e( 'Envoyer ma réponse', 'wedding-rsvp-pass' ); ?></span>
				</button>
			</form>
			<?php endif; ?>
		</div>

		<?php if ( $wedding['dress_code'] ) : ?>
			<p class="wrp-dress"><span><?php esc_html_e( 'Dress code', 'wedding-rsvp-pass' ); ?></span> <?php echo esc_html( $wedding['dress_code'] ); ?></p>
		<?php endif; ?>
		<?php if ( $wedding['map_url'] ) : ?>
			<p class="wrp-map"><a href="<?php echo esc_url( $wedding['map_url'] ); ?>" target="_blank" rel="noopener">📍 <?php esc_html_e( 'Voir l’itinéraire', 'wedding-rsvp-pass' ); ?></a></p>
		<?php endif; ?>
		<?php if ( $wedding['quote'] ) : ?>
			<p class="wrp-quote">« <?php echo esc_html( $wedding['quote'] ); ?> »</p>
		<?php endif; ?>
	</article>
	<p class="wrp-signature"><?php echo esc_html( sprintf( __( 'Avec tout notre amour, %s', 'wedding-rsvp-pass' ), $wedding['names'] ) ); ?></p>
</main>
<script src="<?php echo esc_url( WRP_Public::asset( 'js/public.js' ) ); ?>" defer></script>
</body>
</html>

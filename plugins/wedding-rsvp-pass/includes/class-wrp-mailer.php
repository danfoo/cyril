<?php
/**
 * Beautiful HTML e-mails.
 *
 * @package WeddingRSVPPass
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WRP_Mailer {

	/**
	 * Send the invitation (RSVP link) e-mail.
	 *
	 * @param object $guest Guest.
	 * @return bool
	 */
	public static function send_invitation( $guest ) {
		if ( ! is_email( $guest->email ) ) {
			return false;
		}
		$subject = WRP_Guests::fill( WRP_Settings::get( 'invite_subject' ), $guest );
		$body    = WRP_Guests::fill( WRP_Settings::get( 'invite_body' ), $guest );
		$html    = self::render( $body, WRP_Guests::rsvp_url( $guest ), __( 'Répondre à l’invitation', 'wedding-rsvp-pass' ) );
		$sent    = self::send( $guest->email, $subject, $html );
		if ( $sent ) {
			WRP_Guests::mark_invited( $guest );
		}
		return $sent;
	}

	/**
	 * Send the "here is your card" e-mail after confirmation.
	 *
	 * @param object $guest Guest.
	 * @return bool
	 */
	public static function send_card( $guest ) {
		if ( ! is_email( $guest->email ) ) {
			return false;
		}
		$subject = WRP_Guests::fill( WRP_Settings::get( 'confirm_subject' ), $guest );
		$body    = WRP_Guests::fill( WRP_Settings::get( 'confirm_body' ), $guest );
		$html    = self::render( $body, WRP_Guests::card_url( $guest ), __( 'Voir mon carton d’invitation', 'wedding-rsvp-pass' ), $guest );
		return self::send( $guest->email, $subject, $html );
	}

	private static function send( $to, $subject, $html ) {
		$headers   = array( 'Content-Type: text/html; charset=UTF-8' );
		$from_name = WRP_Settings::get( 'email_from_name' ) ? WRP_Settings::get( 'email_from_name' ) : WRP_Settings::couple_names();
		$from_mail = WRP_Settings::get( 'email_from_address' );
		if ( is_email( $from_mail ) ) {
			$headers[] = sprintf( 'From: %s <%s>', wp_specialchars_decode( $from_name ), $from_mail );
		} else {
			$filter = function () use ( $from_name ) {
				return wp_specialchars_decode( $from_name );
			};
			add_filter( 'wp_mail_from_name', $filter );
		}
		$ok = wp_mail( $to, wp_specialchars_decode( $subject ), $html, $headers );
		if ( isset( $filter ) ) {
			remove_filter( 'wp_mail_from_name', $filter );
		}
		return $ok;
	}

	/**
	 * Turn the plain-text template into a stylish e-mail.
	 * A line containing only the primary URL becomes a button.
	 */
	public static function render( $text, $cta_url, $cta_label, $guest = null ) {
		$accent = sanitize_hex_color( WRP_Settings::get( 'color_accent' ) ) ?: '#b8925a';
		$dark   = sanitize_hex_color( WRP_Settings::get( 'color_dark' ) ) ?: '#3b2f2a';
		$blush  = sanitize_hex_color( WRP_Settings::get( 'color_blush' ) ) ?: '#f4e1dc';

		$button = sprintf(
			'<table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" style="margin:28px auto"><tr><td style="border-radius:40px;background:%1$s"><a href="%2$s" style="display:inline-block;padding:15px 34px;font-family:Georgia,serif;font-size:16px;letter-spacing:1px;color:#ffffff;text-decoration:none;border-radius:40px">%3$s</a></td></tr></table>',
			esc_attr( $accent ),
			esc_url( $cta_url ),
			esc_html( $cta_label )
		);

		$lines     = preg_split( "/\r\n|\n|\r/", (string) $text );
		$html      = '';
		$has_cta   = false;
		$paragraph = array();
		$flush     = function () use ( &$paragraph, &$html ) {
			if ( $paragraph ) {
				$html     .= '<p style="margin:0 0 16px;line-height:1.7">' . implode( '<br>', $paragraph ) . '</p>';
				$paragraph = array();
			}
		};
		foreach ( $lines as $line ) {
			$trim = trim( $line );
			if ( $trim === $cta_url ) {
				$flush();
				$html   .= $button;
				$has_cta = true;
			} elseif ( '' === $trim ) {
				$flush();
			} else {
				$paragraph[] = make_clickable( esc_html( $trim ) );
			}
		}
		$flush();
		if ( ! $has_cta ) {
			$html .= $button;
		}

		$code_block = '';
		if ( $guest && $guest->pass_code ) {
			$code_block = sprintf(
				'<p style="text-align:center;margin:8px 0 0;font-family:Courier New,monospace;font-size:13px;color:%1$s">%2$s <strong style="letter-spacing:3px">%3$s</strong></p>',
				esc_attr( $dark ),
				esc_html__( 'Votre code d’accès :', 'wedding-rsvp-pass' ),
				esc_html( WRP_Guests::format_code( $guest->pass_code ) )
			);
		}

		$names = esc_html( WRP_Settings::couple_names( ' & ' ) );
		$date  = esc_html( WRP_Settings::wedding_date_display() );

		ob_start();
		?>
<!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"></head>
<body style="margin:0;padding:0;background:<?php echo esc_attr( $blush ); ?>">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:<?php echo esc_attr( $blush ); ?>;padding:32px 12px">
<tr><td align="center">
	<table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="max-width:600px;width:100%;background:#fffdf9;border-radius:18px;overflow:hidden;box-shadow:0 10px 40px rgba(0,0,0,.08)">
		<tr><td style="padding:44px 40px 10px;text-align:center;border-bottom:1px solid <?php echo esc_attr( $blush ); ?>">
			<div style="font-family:Georgia,serif;font-size:12px;letter-spacing:5px;text-transform:uppercase;color:<?php echo esc_attr( $accent ); ?>">✦ <?php esc_html_e( 'Mariage', 'wedding-rsvp-pass' ); ?> ✦</div>
			<div style="font-family:'Brush Script MT','Segoe Script',cursive,Georgia,serif;font-size:44px;line-height:1.2;color:<?php echo esc_attr( $dark ); ?>;margin:14px 0 6px"><?php echo $names; // phpcs:ignore ?></div>
			<div style="font-family:Georgia,serif;font-size:14px;letter-spacing:2px;color:<?php echo esc_attr( $accent ); ?>;margin-bottom:26px"><?php echo $date; // phpcs:ignore ?></div>
		</td></tr>
		<tr><td style="padding:32px 44px 36px;font-family:Georgia,serif;font-size:16px;color:<?php echo esc_attr( $dark ); ?>">
			<?php echo $html; // phpcs:ignore ?>
			<?php echo $code_block; // phpcs:ignore ?>
		</td></tr>
		<tr><td style="padding:18px;text-align:center;background:<?php echo esc_attr( $dark ); ?>;font-family:Georgia,serif;font-size:13px;color:#f3e7d3;letter-spacing:1px">♥ <?php echo $names; // phpcs:ignore ?> ♥</td></tr>
	</table>
</td></tr>
</table>
</body></html>
		<?php
		return (string) ob_get_clean();
	}
}

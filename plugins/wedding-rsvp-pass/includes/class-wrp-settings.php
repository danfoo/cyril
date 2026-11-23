<?php
/**
 * Plugin settings (single option array).
 *
 * @package WeddingRSVPPass
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WRP_Settings {

	const OPTION = 'wrp_settings';

	/** @var array|null */
	private static $cache = null;

	public static function defaults() {
		$defaults = array(
			'partner_a_name'     => 'Sarah',
			'partner_b_name'     => 'Cyril',
			'partner_a_user'     => 0,
			'partner_b_user'     => 0,
			'wedding_date'       => gmdate( 'Y-m-d', strtotime( '+6 months' ) ),
			'wedding_time'       => '15:00',
			'venue_name'         => 'Domaine des Roses',
			'venue_address'      => '',
			'map_url'            => '',
			'dress_code'         => '',
			'rsvp_deadline'      => '',
			'email_from_name'    => '',
			'email_from_address' => '',
			'country_code'       => '33',
			'invite_subject'     => 'Vous êtes invité(e) au mariage de {maries} 💍',
			'invite_body'        => "Cher(e) {prenom},\n\nNous avons l'immense joie de vous convier à notre mariage le {date} à {heure}, au {lieu}.\n\nNous vous avons réservé {places} place(s). Merci de nous confirmer votre présence avant le {date_limite} en cliquant sur le lien ci-dessous :\n\n{lien}\n\nAvec tout notre amour,\n{maries}",
			'whatsapp_text'      => "Bonjour {prenom} ✨\n{maries} ont la joie de vous inviter à leur mariage le {date}. Merci de confirmer votre présence ici : {lien}",
			'confirm_subject'    => 'Votre carton d\'invitation – Mariage de {maries}',
			'confirm_body'       => "Cher(e) {prenom},\n\nMerci d'avoir confirmé votre présence ! Nous avons hâte de partager ce jour avec vous.\n\nVoici votre carton d'invitation personnel avec votre QR code. Présentez-le à l'entrée le jour J (sur votre téléphone ou imprimé) :\n\n{carton}\n\nÀ très bientôt,\n{maries}",
			'card_invite_line'   => 'ont la joie de vous convier à la célébration de leur mariage',
			'card_quote'         => 'Deux âmes, un seul cœur.',
			'color_accent'       => '#b8925a',
			'color_dark'         => '#3b2f2a',
			'color_blush'        => '#f4e1dc',
			'scanner_key'        => '',
			'allow_companion_names' => 1,
			'allow_change_after_confirm' => 1,
		);

		/**
		 * Lets a theme pre-fill defaults (names, date, venue...).
		 *
		 * @param array $defaults Default settings.
		 */
		return apply_filters( 'wrp_settings_defaults', $defaults );
	}

	public static function all() {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, array() );
			$saved       = is_array( $saved ) ? $saved : array();
			$defaults    = self::defaults();
			// Empty strings in saved values fall back to defaults (e.g. values from the theme Customizer).
			foreach ( $saved as $k => $v ) {
				if ( '' === $v && isset( $defaults[ $k ] ) && '' !== $defaults[ $k ] ) {
					unset( $saved[ $k ] );
				}
			}
			self::$cache = array_merge( $defaults, $saved );
		}
		return self::$cache;
	}

	public static function get( $key ) {
		$all = self::all();
		$val = isset( $all[ $key ] ) ? $all[ $key ] : '';
		return apply_filters( 'wrp_setting_' . $key, $val );
	}

	public static function update( array $values ) {
		$current = get_option( self::OPTION, array() );
		$current = is_array( $current ) ? $current : array();
		update_option( self::OPTION, array_merge( $current, $values ) );
		self::$cache = null;
	}

	public static function ensure_scanner_key() {
		$saved = get_option( self::OPTION, array() );
		if ( empty( $saved['scanner_key'] ) ) {
			self::update( array( 'scanner_key' => self::generate_key() ) );
		}
	}

	public static function generate_key() {
		// Human-friendly: XXXX-XXXX-XXXX-XXXX (no ambiguous chars).
		$alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
		$parts    = array();
		for ( $p = 0; $p < 4; $p++ ) {
			$s = '';
			for ( $i = 0; $i < 4; $i++ ) {
				$s .= $alphabet[ random_int( 0, strlen( $alphabet ) - 1 ) ];
			}
			$parts[] = $s;
		}
		return implode( '-', $parts );
	}

	/* ---------------------------------------------------------------------
	 * Convenience getters used by templates.
	 * ------------------------------------------------------------------ */

	public static function couple_names( $sep = ' & ' ) {
		return trim( self::get( 'partner_a_name' ) ) . $sep . trim( self::get( 'partner_b_name' ) );
	}

	public static function wedding_timestamp() {
		$date = self::get( 'wedding_date' );
		$time = self::get( 'wedding_time' ) ? self::get( 'wedding_time' ) : '00:00';
		try {
			$dt = new DateTime( $date . ' ' . $time, wp_timezone() );
			return $dt->getTimestamp();
		} catch ( Exception $e ) {
			return time();
		}
	}

	public static function wedding_date_display( $format = 'l j F Y' ) {
		return wp_date( $format, self::wedding_timestamp() );
	}

	public static function wedding_time_display() {
		return wp_date( get_option( 'time_format', 'H:i' ), self::wedding_timestamp() );
	}

	public static function deadline_display() {
		$d = self::get( 'rsvp_deadline' );
		if ( ! $d ) {
			return wp_date( 'j F Y', self::wedding_timestamp() - 21 * DAY_IN_SECONDS );
		}
		try {
			$dt = new DateTime( $d . ' 23:59', wp_timezone() );
			return wp_date( 'j F Y', $dt->getTimestamp() );
		} catch ( Exception $e ) {
			return $d;
		}
	}

	public static function rsvp_is_closed() {
		$d = self::get( 'rsvp_deadline' );
		if ( ! $d ) {
			return false;
		}
		try {
			$dt = new DateTime( $d . ' 23:59:59', wp_timezone() );
			return time() > $dt->getTimestamp();
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Which side (a|b) a user belongs to, or '' if none.
	 *
	 * @param int $user_id User.
	 */
	public static function side_of_user( $user_id ) {
		$user_id = (int) $user_id;
		if ( $user_id && (int) self::get( 'partner_a_user' ) === $user_id ) {
			return 'a';
		}
		if ( $user_id && (int) self::get( 'partner_b_user' ) === $user_id ) {
			return 'b';
		}
		return '';
	}

	/**
	 * Label for a guest owner ("Invité(e) de Sarah").
	 *
	 * @param int $owner_id User ID.
	 */
	public static function owner_label( $owner_id ) {
		$side = self::side_of_user( $owner_id );
		if ( 'a' === $side ) {
			return self::get( 'partner_a_name' );
		}
		if ( 'b' === $side ) {
			return self::get( 'partner_b_name' );
		}
		$u = get_userdata( (int) $owner_id );
		return $u ? $u->display_name : '—';
	}
}

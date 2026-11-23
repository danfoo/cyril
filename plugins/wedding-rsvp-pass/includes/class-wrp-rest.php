<?php
/**
 * REST API used by the offline scanner.
 *
 * Auth: header "X-WRP-Key: XXXX-XXXX-XXXX-XXXX" (scanner key from the settings).
 * The scanner downloads the guest list once (with SHA-256 hashes of the pass
 * codes, never the codes themselves), validates offline and pushes the
 * check-ins later. Sync is idempotent thanks to a client-generated UUID.
 *
 * @package WeddingRSVPPass
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WRP_REST {

	const NS = 'wrp/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		register_rest_route(
			self::NS,
			'/scanner/hello',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'hello' ),
				'permission_callback' => array( __CLASS__, 'check_key' ),
			)
		);
		register_rest_route(
			self::NS,
			'/scanner/guests',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'guests' ),
				'permission_callback' => array( __CLASS__, 'check_key' ),
			)
		);
		register_rest_route(
			self::NS,
			'/scanner/checkins',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'checkins' ),
				'permission_callback' => array( __CLASS__, 'check_key' ),
			)
		);
	}

	/**
	 * Validate the scanner key with a small brute-force guard.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function check_key( WP_REST_Request $request ) {
		$ip      = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'x';
		$bucket  = 'wrp_fail_' . md5( $ip );
		$fails   = (int) get_transient( $bucket );
		if ( $fails >= 20 ) {
			return new WP_Error( 'wrp_rate', __( 'Trop de tentatives. Réessayez dans quelques minutes.', 'wedding-rsvp-pass' ), array( 'status' => 429 ) );
		}

		$given    = self::normalize_key( (string) $request->get_header( 'x_wrp_key' ) );
		$expected = self::normalize_key( (string) WRP_Settings::get( 'scanner_key' ) );

		if ( '' !== $expected && '' !== $given && hash_equals( $expected, $given ) ) {
			return true;
		}
		set_transient( $bucket, $fails + 1, 10 * MINUTE_IN_SECONDS );
		return new WP_Error( 'wrp_key', __( 'Clé de scanner invalide.', 'wedding-rsvp-pass' ), array( 'status' => 401 ) );
	}

	private static function normalize_key( $key ) {
		return preg_replace( '/[^A-Z0-9]/', '', strtoupper( $key ) );
	}

	private static function wedding() {
		return array(
			'names' => WRP_Settings::couple_names(),
			'date'  => WRP_Settings::wedding_date_display( 'j F Y' ),
		);
	}

	public static function hello() {
		return rest_ensure_response(
			array(
				'ok'      => true,
				'wedding' => self::wedding(),
			)
		);
	}

	/**
	 * Hash used in the QR lookup table (also computed client-side).
	 *
	 * @param string $code Pass code.
	 */
	public static function code_hash( $code ) {
		return hash( 'sha256', 'wrp|' . WRP_Guests::normalize_code( $code ) );
	}

	private static function to_iso( $mysql ) {
		if ( ! $mysql ) {
			return null;
		}
		return get_gmt_from_date( $mysql, 'Y-m-d\TH:i:s\Z' );
	}

	public static function guests() {
		global $wpdb;
		$t    = WRP_DB::guests_table();
		$rows = $wpdb->get_results( "SELECT * FROM $t WHERE pass_code IS NOT NULL AND pass_code <> '' ORDER BY last_name, first_name" ); // phpcs:ignore

		$guests = array();
		foreach ( $rows as $g ) {
			$guests[] = array(
				'id'         => (int) $g->id,
				'h'          => self::code_hash( $g->pass_code ),
				'name'       => WRP_Guests::full_name( $g ),
				'companions' => WRP_Guests::companions( $g ),
				'seats'      => (int) $g->seats_confirmed,
				'table'      => $g->table_name,
				'group'      => $g->group_label,
				'side'       => WRP_Settings::owner_label( $g->owner_id ),
				'status'     => $g->status,
				'dietary'    => $g->dietary,
				'checkedAt'  => self::to_iso( $g->checked_in_at ),
				'checkedBy'  => $g->checked_in_device,
				'code4'      => substr( $g->pass_code, -4 ),
			);
		}

		return rest_ensure_response(
			array(
				'generatedAt' => gmdate( 'Y-m-d\TH:i:s\Z' ),
				'wedding'     => self::wedding(),
				'stats'       => WRP_Guests::stats(),
				'guests'      => $guests,
			)
		);
	}

	/**
	 * Receive a batch of offline check-ins.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function checkins( WP_REST_Request $request ) {
		$device_id   = sanitize_text_field( (string) $request->get_param( 'deviceId' ) );
		$device_name = sanitize_text_field( (string) $request->get_param( 'deviceName' ) );
		$items       = $request->get_param( 'checkins' );
		$items       = is_array( $items ) ? array_slice( $items, 0, 500 ) : array();

		$results = array();
		foreach ( $items as $item ) {
			$uuid = isset( $item['uuid'] ) ? strtolower( sanitize_text_field( $item['uuid'] ) ) : '';
			if ( ! preg_match( '/^[0-9a-f-]{36}$/', $uuid ) ) {
				continue;
			}
			$guest = isset( $item['guestId'] ) ? WRP_Guests::get( (int) $item['guestId'] ) : null;
			if ( ! $guest ) {
				$results[] = array(
					'uuid'   => $uuid,
					'result' => 'unknown',
				);
				continue;
			}
			$ts = isset( $item['scannedAt'] ) ? strtotime( (string) $item['scannedAt'] ) : time();
			$ts = $ts ? min( $ts, time() ) : time();
			$at = get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $ts ) );

			$results[] = WRP_Guests::apply_checkin( $guest, $uuid, $device_id, $device_name, isset( $item['seats'] ) ? (int) $item['seats'] : 1, $at );
		}

		return rest_ensure_response(
			array(
				'results' => $results,
				'stats'   => WRP_Guests::stats(),
			)
		);
	}
}

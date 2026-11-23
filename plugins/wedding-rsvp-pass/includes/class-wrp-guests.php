<?php
/**
 * Guest repository & business logic.
 *
 * @package WeddingRSVPPass
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WRP_Guests {

	const STATUSES = array( 'pending', 'invited', 'confirmed', 'declined' );

	const QR_PREFIX = 'WRP:';

	public static function status_label( $status ) {
		$labels = array(
			'pending'   => __( 'À inviter', 'wedding-rsvp-pass' ),
			'invited'   => __( 'Invité·e – en attente', 'wedding-rsvp-pass' ),
			'confirmed' => __( 'Présent·e', 'wedding-rsvp-pass' ),
			'declined'  => __( 'Absent·e', 'wedding-rsvp-pass' ),
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	/* ------------------------------------------------------------------
	 * Read
	 * ---------------------------------------------------------------- */

	public static function get( $id ) {
		global $wpdb;
		$t = WRP_DB::guests_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function get_by_token( $token ) {
		global $wpdb;
		if ( ! preg_match( '/^[a-f0-9]{32}$/', (string) $token ) ) {
			return null;
		}
		$t = WRP_DB::guests_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE token = %s", $token ) ); // phpcs:ignore
	}

	public static function get_by_pass_code( $code ) {
		global $wpdb;
		$code = self::normalize_code( $code );
		if ( '' === $code ) {
			return null;
		}
		$t = WRP_DB::guests_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE pass_code = %s", $code ) ); // phpcs:ignore
	}

	public static function normalize_code( $code ) {
		$code = strtoupper( trim( (string) $code ) );
		if ( 0 === strpos( $code, self::QR_PREFIX ) ) {
			$code = substr( $code, strlen( self::QR_PREFIX ) );
		}
		return preg_replace( '/[^A-Z0-9]/', '', $code );
	}

	/**
	 * Paginated query.
	 *
	 * @param array $args owner_id, status, search, orderby, order, per_page, page.
	 * @return array{items: array, total: int}
	 */
	public static function query( array $args = array() ) {
		global $wpdb;
		$t    = WRP_DB::guests_table();
		$args = wp_parse_args(
			$args,
			array(
				'owner_id' => null,
				'status'   => '',
				'search'   => '',
				'group'    => '',
				'orderby'  => 'last_name',
				'order'    => 'ASC',
				'per_page' => 50,
				'page'     => 1,
			)
		);

		$where  = array( '1=1' );
		$params = array();

		if ( null !== $args['owner_id'] && '' !== $args['owner_id'] ) {
			$where[]  = 'owner_id = %d';
			$params[] = (int) $args['owner_id'];
		}
		if ( $args['status'] && in_array( $args['status'], self::STATUSES, true ) ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		} elseif ( 'checked_in' === $args['status'] ) {
			$where[] = 'checked_in_at IS NOT NULL';
		}
		if ( $args['group'] ) {
			$where[]  = 'group_label = %s';
			$params[] = $args['group'];
		}
		if ( $args['search'] ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR phone LIKE %s OR group_label LIKE %s OR table_name LIKE %s OR pass_code LIKE %s)';
			$params   = array_merge( $params, array_fill( 0, 7, $like ) );
		}

		$allowed_orderby = array( 'last_name', 'first_name', 'status', 'created_at', 'responded_at', 'group_label', 'table_name', 'seats_allowed', 'checked_in_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'last_name';
		$order           = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';
		$per_page        = max( 1, min( 1000, (int) $args['per_page'] ) );
		$offset          = ( max( 1, (int) $args['page'] ) - 1 ) * $per_page;

		$where_sql = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM $t WHERE $where_sql";
		$items_sql = "SELECT * FROM $t WHERE $where_sql ORDER BY $orderby $order, first_name ASC LIMIT $per_page OFFSET $offset";

		if ( $params ) {
			$count_sql = $wpdb->prepare( $count_sql, $params ); // phpcs:ignore
			$items_sql = $wpdb->prepare( $items_sql, $params ); // phpcs:ignore
		}

		return array(
			'items' => $wpdb->get_results( $items_sql ), // phpcs:ignore
			'total' => (int) $wpdb->get_var( $count_sql ), // phpcs:ignore
		);
	}

	public static function groups( $owner_id = null ) {
		global $wpdb;
		$t = WRP_DB::guests_table();
		if ( null !== $owner_id ) {
			return $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT group_label FROM $t WHERE owner_id = %d AND group_label <> '' ORDER BY group_label", $owner_id ) ); // phpcs:ignore
		}
		return $wpdb->get_col( "SELECT DISTINCT group_label FROM $t WHERE group_label <> '' ORDER BY group_label" ); // phpcs:ignore
	}

	/**
	 * Dashboard numbers.
	 *
	 * @param int|null $owner_id Restrict to one list.
	 */
	public static function stats( $owner_id = null ) {
		global $wpdb;
		$t     = WRP_DB::guests_table();
		$where = null !== $owner_id ? $wpdb->prepare( 'WHERE owner_id = %d', $owner_id ) : '';
		$row   = $wpdb->get_row( // phpcs:ignore
			"SELECT
				COUNT(*) AS invitations,
				COALESCE(SUM(seats_allowed),0) AS seats_invited,
				COALESCE(SUM(status = 'pending'),0) AS pending,
				COALESCE(SUM(status = 'invited'),0) AS invited,
				COALESCE(SUM(status = 'confirmed'),0) AS confirmed,
				COALESCE(SUM(status = 'declined'),0) AS declined,
				COALESCE(SUM(CASE WHEN status = 'confirmed' THEN seats_confirmed ELSE 0 END),0) AS seats_confirmed,
				COALESCE(SUM(checked_in_at IS NOT NULL),0) AS checked_in,
				COALESCE(SUM(checked_in_seats),0) AS seats_checked_in
			FROM $t $where",
			ARRAY_A
		);
		return array_map( 'intval', (array) $row );
	}

	/* ------------------------------------------------------------------
	 * Write
	 * ---------------------------------------------------------------- */

	public static function sanitize( array $data ) {
		$out    = array();
		$fields = array(
			'first_name'  => 'sanitize_text_field',
			'last_name'   => 'sanitize_text_field',
			'email'       => 'sanitize_email',
			'phone'       => array( __CLASS__, 'sanitize_phone' ),
			'group_label' => 'sanitize_text_field',
			'table_name'  => 'sanitize_text_field',
			'dietary'     => 'sanitize_text_field',
			'notes'       => 'sanitize_textarea_field',
		);
		foreach ( $fields as $key => $cb ) {
			if ( array_key_exists( $key, $data ) ) {
				$out[ $key ] = call_user_func( $cb, wp_unslash( (string) $data[ $key ] ) );
			}
		}
		if ( isset( $data['seats_allowed'] ) ) {
			$out['seats_allowed'] = max( 1, min( 20, (int) $data['seats_allowed'] ) );
		}
		if ( isset( $data['owner_id'] ) ) {
			$out['owner_id'] = (int) $data['owner_id'];
		}
		if ( isset( $data['status'] ) && in_array( $data['status'], self::STATUSES, true ) ) {
			$out['status'] = $data['status'];
		}
		return $out;
	}

	public static function sanitize_phone( $phone ) {
		return preg_replace( '/[^0-9+ ().-]/', '', (string) $phone );
	}

	public static function create( array $data ) {
		global $wpdb;
		$row = self::sanitize( $data );
		if ( empty( $row['first_name'] ) && empty( $row['last_name'] ) ) {
			return new WP_Error( 'wrp_name', __( 'Le nom de l’invité est obligatoire.', 'wedding-rsvp-pass' ) );
		}
		$now                  = current_time( 'mysql' );
		$row['token']         = self::generate_token();
		$row['created_at']    = $now;
		$row['updated_at']    = $now;
		$row['owner_id']      = isset( $row['owner_id'] ) ? $row['owner_id'] : get_current_user_id();
		$row['seats_allowed'] = isset( $row['seats_allowed'] ) ? $row['seats_allowed'] : 1;
		$row['status']        = isset( $row['status'] ) ? $row['status'] : 'pending';

		$ok = $wpdb->insert( WRP_DB::guests_table(), $row );
		if ( ! $ok ) {
			return new WP_Error( 'wrp_db', $wpdb->last_error );
		}
		$id = (int) $wpdb->insert_id;
		do_action( 'wrp_guest_created', $id );
		return $id;
	}

	public static function update( $id, array $data, $raw = false ) {
		global $wpdb;
		$row               = $raw ? $data : self::sanitize( $data );
		$row['updated_at'] = current_time( 'mysql' );
		$ok                = $wpdb->update( WRP_DB::guests_table(), $row, array( 'id' => (int) $id ) );
		do_action( 'wrp_guest_updated', (int) $id );
		return false !== $ok;
	}

	public static function delete( $id ) {
		global $wpdb;
		$wpdb->delete( WRP_DB::checkins_table(), array( 'guest_id' => (int) $id ) );
		return (bool) $wpdb->delete( WRP_DB::guests_table(), array( 'id' => (int) $id ) );
	}

	public static function generate_token() {
		return bin2hex( random_bytes( 16 ) );
	}

	public static function generate_pass_code() {
		global $wpdb;
		$alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
		$t        = WRP_DB::guests_table();
		do {
			$code = '';
			for ( $i = 0; $i < 8; $i++ ) {
				$code .= $alphabet[ random_int( 0, strlen( $alphabet ) - 1 ) ];
			}
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE pass_code = %s", $code ) ); // phpcs:ignore
		} while ( $exists );
		return $code;
	}

	public static function format_code( $code ) {
		return trim( chunk_split( (string) $code, 4, ' ' ) );
	}

	/* ------------------------------------------------------------------
	 * RSVP
	 * ---------------------------------------------------------------- */

	/**
	 * Guest confirms.
	 *
	 * @param object $guest      Guest.
	 * @param int    $seats      Number of attending people (incl. guest).
	 * @param array  $companions Names of companions.
	 * @param string $message    Message to the couple.
	 * @param string $dietary    Dietary notes.
	 */
	public static function confirm( $guest, $seats, array $companions, $message, $dietary ) {
		$seats      = max( 1, min( (int) $guest->seats_allowed, (int) $seats ) );
		$companions = array_values( array_filter( array_map( 'sanitize_text_field', array_slice( $companions, 0, $seats - 1 ) ) ) );
		$was        = $guest->status;

		$data = array(
			'status'          => 'confirmed',
			'seats_confirmed' => $seats,
			'companions'      => wp_json_encode( $companions ),
			'guest_message'   => sanitize_textarea_field( $message ),
			'dietary'         => sanitize_text_field( $dietary ),
			'responded_at'    => current_time( 'mysql' ),
		);
		if ( empty( $guest->pass_code ) ) {
			$data['pass_code'] = self::generate_pass_code();
		}
		self::update( $guest->id, $data, true );
		$fresh = self::get( $guest->id );

		do_action( 'wrp_guest_confirmed', $fresh, $was );

		if ( 'confirmed' !== $was && is_email( $fresh->email ) ) {
			WRP_Mailer::send_card( $fresh );
		}
		return $fresh;
	}

	public static function decline( $guest, $message ) {
		$was = $guest->status;
		self::update(
			$guest->id,
			array(
				'status'          => 'declined',
				'seats_confirmed' => 0,
				'guest_message'   => sanitize_textarea_field( $message ),
				'responded_at'    => current_time( 'mysql' ),
			),
			true
		);
		$fresh = self::get( $guest->id );
		do_action( 'wrp_guest_declined', $fresh, $was );
		return $fresh;
	}

	public static function mark_invited( $guest ) {
		$data = array( 'invited_at' => current_time( 'mysql' ) );
		if ( 'pending' === $guest->status ) {
			$data['status'] = 'invited';
		}
		self::update( $guest->id, $data, true );
	}

	public static function companions( $guest ) {
		$c = json_decode( (string) $guest->companions, true );
		return is_array( $c ) ? $c : array();
	}

	public static function full_name( $guest ) {
		return trim( $guest->first_name . ' ' . $guest->last_name );
	}

	/* ------------------------------------------------------------------
	 * URLs & messages
	 * ---------------------------------------------------------------- */

	public static function rsvp_url( $guest ) {
		return WRP_Public::url( 'rsvp', $guest->token );
	}

	public static function card_url( $guest ) {
		return WRP_Public::url( 'invitation', $guest->token );
	}

	public static function placeholders( $guest ) {
		return array(
			'{prenom}'      => $guest->first_name,
			'{nom}'         => $guest->last_name,
			'{nom_complet}' => self::full_name( $guest ),
			'{maries}'      => WRP_Settings::couple_names(),
			'{date}'        => WRP_Settings::wedding_date_display(),
			'{heure}'       => WRP_Settings::wedding_time_display(),
			'{lieu}'        => WRP_Settings::get( 'venue_name' ),
			'{adresse}'     => WRP_Settings::get( 'venue_address' ),
			'{places}'      => (string) $guest->seats_allowed,
			'{date_limite}' => WRP_Settings::deadline_display(),
			'{lien}'        => self::rsvp_url( $guest ),
			'{carton}'      => self::card_url( $guest ),
			'{code}'        => self::format_code( (string) $guest->pass_code ),
			'{table}'       => $guest->table_name,
		);
	}

	public static function fill( $template, $guest ) {
		return strtr( (string) $template, self::placeholders( $guest ) );
	}

	/**
	 * International phone number for wa.me (digits only).
	 *
	 * @param string $phone Phone.
	 */
	public static function intl_phone( $phone ) {
		$phone = trim( (string) $phone );
		if ( '' === $phone ) {
			return '';
		}
		$plus   = 0 === strpos( $phone, '+' );
		$digits = preg_replace( '/\D/', '', $phone );
		if ( $plus ) {
			return $digits;
		}
		if ( 0 === strpos( $digits, '00' ) ) {
			return substr( $digits, 2 );
		}
		$cc = preg_replace( '/\D/', '', (string) WRP_Settings::get( 'country_code' ) );
		if ( $cc && 0 === strpos( $digits, '0' ) ) {
			return $cc . substr( $digits, 1 );
		}
		if ( $cc && 0 !== strpos( $digits, $cc ) ) {
			return $cc . $digits;
		}
		return $digits;
	}

	public static function whatsapp_url( $guest, $text = null ) {
		$text  = null === $text ? self::fill( WRP_Settings::get( 'whatsapp_text' ), $guest ) : $text;
		$phone = self::intl_phone( $guest->phone );
		return 'https://wa.me/' . $phone . '?text=' . rawurlencode( $text );
	}

	public static function sms_url( $guest ) {
		$text = self::fill( WRP_Settings::get( 'whatsapp_text' ), $guest );
		return 'sms:' . preg_replace( '/[^0-9+]/', '', (string) $guest->phone ) . '?&body=' . rawurlencode( $text );
	}

	/* ------------------------------------------------------------------
	 * Check-in (used by the REST sync of the offline scanner)
	 * ---------------------------------------------------------------- */

	/**
	 * Apply one check-in coming from a scanner device. Idempotent on $uuid.
	 *
	 * @return array{result: string, guest_id: int, checked_in_at: string|null, device: string}
	 */
	public static function apply_checkin( $guest, $uuid, $device_id, $device_name, $seats, $scanned_at ) {
		global $wpdb;
		$ct = WRP_DB::checkins_table();

		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $ct WHERE uuid = %s", $uuid ) ); // phpcs:ignore
		if ( $existing ) {
			return array(
				'result'   => $existing->result,
				'guest_id' => (int) $guest->id,
				'uuid'     => $uuid,
			);
		}

		$result = 'ok';
		if ( 'confirmed' !== $guest->status ) {
			$result = 'not_confirmed';
		} elseif ( $guest->checked_in_at ) {
			$result = ( $guest->checked_in_device === $device_id ) ? 'ok' : 'duplicate';
		}

		$seats = max( 1, (int) $seats );

		$wpdb->insert(
			$ct,
			array(
				'uuid'        => $uuid,
				'guest_id'    => (int) $guest->id,
				'device_id'   => substr( $device_id, 0, 64 ),
				'device_name' => substr( $device_name, 0, 100 ),
				'seats'       => $seats,
				'scanned_at'  => $scanned_at,
				'synced_at'   => current_time( 'mysql' ),
				'result'      => $result,
			)
		);

		if ( 'ok' === $result && ! $guest->checked_in_at ) {
			self::update(
				$guest->id,
				array(
					'checked_in_at'     => $scanned_at,
					'checked_in_seats'  => min( $seats, max( 1, (int) $guest->seats_confirmed ) ),
					'checked_in_device' => substr( $device_id, 0, 64 ),
				),
				true
			);
		}

		return array(
			'result'   => $result,
			'guest_id' => (int) $guest->id,
			'uuid'     => $uuid,
		);
	}

	public static function reset_checkin( $id ) {
		global $wpdb;
		$wpdb->delete( WRP_DB::checkins_table(), array( 'guest_id' => (int) $id ) );
		$wpdb->update(
			WRP_DB::guests_table(),
			array(
				'checked_in_at'     => null,
				'checked_in_seats'  => 0,
				'checked_in_device' => '',
				'updated_at'        => current_time( 'mysql' ),
			),
			array( 'id' => (int) $id )
		);
	}
}

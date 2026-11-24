<?php
/**
 * "Espace mariés" – installable app (PWA) for the couple and the welcome team.
 *
 *   /espace-maries/          login + guest management (spouses, planner, admin)
 *   /espace-maries/scanner/  wedding-day scanner (also for « Agent d'accueil » accounts)
 *
 * The app shell is static (cached offline by the service worker); everything
 * user-specific comes from:
 *   - admin-ajax `wrp_app_session` / `wrp_app_login` / `wrp_app_logout`
 *   - REST `wrp/v1/app/*` (cookie auth + X-WP-Nonce)
 *
 * @package WeddingRSVPPass
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WRP_App {

	const NS = 'wrp/v1';

	public static function init() {
		foreach ( array( 'session', 'login' ) as $a ) {
			add_action( 'wp_ajax_wrp_app_' . $a, array( __CLASS__, 'ajax_' . $a ) );
			add_action( 'wp_ajax_nopriv_wrp_app_' . $a, array( __CLASS__, 'ajax_' . $a ) );
		}
		add_action( 'wp_ajax_wrp_app_logout', array( __CLASS__, 'ajax_logout' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/* ------------------------------------------------------------------
	 * Page
	 * ---------------------------------------------------------------- */

	public static function view() {
		WRP_Public::enforce_trailing_slash( WRP_Public::url( 'app' ) );
		header( 'X-Robots-Tag: noindex, nofollow', true );
		header( 'Cache-Control: no-cache', true );
		WRP_Public::render(
			'app',
			array(
				'config' => array_merge(
					WRP_Public::pwa_config(),
					array(
						'rest'        => esc_url_raw( rest_url( self::NS . '/app/' ) ),
						'lostPassword' => wp_lostpassword_url( WRP_Public::url( 'app' ) ),
					)
				),
			)
		);
	}

	/* ------------------------------------------------------------------
	 * Session (admin-ajax: works with the auth cookie, no nonce needed)
	 * ---------------------------------------------------------------- */

	private static function no_cache_json() {
		nocache_headers();
		header( 'X-Robots-Tag: noindex', true );
	}

	public static function session_data() {
		if ( ! is_user_logged_in() ) {
			return array( 'loggedIn' => false );
		}
		$u        = wp_get_current_user();
		$can_scan = user_can( $u, 'wrp_scan' ) || user_can( $u, 'wrp_manage_guests' );
		$parts    = preg_split( '/\s+/', trim( $u->display_name ) );
		$initials = mb_strtoupper( mb_substr( $parts[0], 0, 1 ) . ( isset( $parts[1] ) ? mb_substr( $parts[1], 0, 1 ) : '' ) );
		$side     = WRP_Settings::side_of_user( $u->ID );

		$lists = array();
		if ( WRP_Roles::can_manage_all() ) {
			$lists[] = array( 'key' => 'all', 'label' => __( 'Tous', 'wedding-rsvp-pass' ) );
			foreach ( array( 'a', 'b' ) as $sd ) {
				if ( WRP_Settings::get( 'partner_' . $sd . '_user' ) ) {
					$lists[] = array( 'key' => $sd, 'label' => WRP_Settings::get( 'partner_' . $sd . '_name' ) );
				}
			}
		}

		return array(
			'loggedIn'   => true,
			'user'       => array(
				'id'       => $u->ID,
				'name'     => $u->display_name,
				'first'    => $u->first_name ? $u->first_name : $parts[0],
				'initials' => $initials,
				'side'     => $side,
			),
			'can'        => array(
				'manage'   => user_can( $u, 'wrp_manage_guests' ),
				'all'      => WRP_Roles::can_manage_all(),
				'scan'     => $can_scan,
				'team'     => user_can( $u, 'wrp_manage_guests' ),
				'admin'    => user_can( $u, 'wrp_manage_guests' ) || user_can( $u, 'edit_posts' ),
			),
			'lists'      => $lists,
			'owners'     => self::owner_choices(),
			'restNonce'  => wp_create_nonce( 'wp_rest' ),
			'scannerKey' => $can_scan ? WRP_Settings::get( 'scanner_key' ) : '',
			'adminUrl'   => admin_url( 'admin.php?page=wrp-guests' ),
			'wedding'    => array(
				'names'     => WRP_Settings::couple_names(),
				'date'      => WRP_Settings::wedding_date_display(),
				'venue'     => WRP_Settings::get( 'venue_name' ),
				'timestamp' => WRP_Settings::wedding_timestamp() * 1000,
				'deadline'  => WRP_Settings::deadline_display(),
			),
		);
	}

	public static function ajax_session() {
		self::no_cache_json();
		wp_send_json_success( self::session_data() );
	}

	public static function ajax_login() {
		self::no_cache_json();
		$ip     = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'x';
		$bucket = 'wrp_login_' . md5( $ip );
		$tries  = (int) get_transient( $bucket );
		if ( $tries >= 10 ) {
			wp_send_json_error( array( 'message' => __( 'Trop de tentatives. Réessayez dans 15 minutes.', 'wedding-rsvp-pass' ) ), 429 );
		}

		$login = isset( $_POST['login'] ) ? sanitize_text_field( wp_unslash( $_POST['login'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$pass  = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : ''; // phpcs:ignore
		if ( '' === $login || '' === $pass ) {
			wp_send_json_error( array( 'message' => __( 'Saisissez votre identifiant et votre mot de passe.', 'wedding-rsvp-pass' ) ), 400 );
		}
		// Accept an e-mail address too.
		if ( is_email( $login ) ) {
			$by_mail = get_user_by( 'email', $login );
			if ( $by_mail ) {
				$login = $by_mail->user_login;
			}
		}

		$user = wp_signon(
			array(
				'user_login'    => $login,
				'user_password' => $pass,
				'remember'      => true,
			),
			is_ssl()
		);
		if ( is_wp_error( $user ) ) {
			set_transient( $bucket, $tries + 1, 15 * MINUTE_IN_SECONDS );
			wp_send_json_error( array( 'message' => __( 'Identifiant ou mot de passe incorrect.', 'wedding-rsvp-pass' ) ), 401 );
		}
		if ( ! user_can( $user, 'wrp_manage_guests' ) && ! user_can( $user, 'wrp_scan' ) ) {
			wp_logout();
			wp_send_json_error( array( 'message' => __( 'Ce compte n’a pas accès à l’espace des mariés.', 'wedding-rsvp-pass' ) ), 403 );
		}
		delete_transient( $bucket );
		// The new auth cookie is only readable on the next request: the app calls `session` again.
		wp_send_json_success( array( 'ok' => true ) );
	}

	public static function ajax_logout() {
		self::no_cache_json();
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			wp_send_json_error( null, 403 );
		}
		wp_logout();
		wp_send_json_success();
	}

	/* ------------------------------------------------------------------
	 * REST
	 * ---------------------------------------------------------------- */

	public static function routes() {
		$manage = function () {
			return current_user_can( 'wrp_manage_guests' );
		};
		register_rest_route(
			self::NS,
			'/app/guests',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'list_guests' ),
					'permission_callback' => $manage,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'create_guest' ),
					'permission_callback' => $manage,
				),
			)
		);
		register_rest_route(
			self::NS,
			'/app/guests/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'update_guest' ),
					'permission_callback' => $manage,
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( __CLASS__, 'delete_guest' ),
					'permission_callback' => $manage,
				),
			)
		);
		register_rest_route(
			self::NS,
			'/app/guests/(?P<id>\d+)/(?P<action>email|invited|reset)',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'guest_action' ),
				'permission_callback' => $manage,
			)
		);
		register_rest_route(
			self::NS,
			'/app/team',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'list_team' ),
					'permission_callback' => $manage,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'create_team' ),
					'permission_callback' => $manage,
				),
			)
		);
		register_rest_route(
			self::NS,
			'/app/team/(?P<id>\d+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( __CLASS__, 'delete_team' ),
				'permission_callback' => $manage,
			)
		);
	}

	/**
	 * Lists the current user can assign guests to.
	 */
	public static function owner_choices() {
		if ( ! is_user_logged_in() ) {
			return array();
		}
		$me      = get_current_user_id();
		$choices = array();
		if ( WRP_Roles::can_manage_all() ) {
			foreach ( array( 'a', 'b' ) as $sd ) {
				$uid = (int) WRP_Settings::get( 'partner_' . $sd . '_user' );
				if ( $uid ) {
					$choices[] = array( 'id' => $uid, 'label' => WRP_Settings::get( 'partner_' . $sd . '_name' ) );
				}
			}
		}
		$ids = wp_list_pluck( $choices, 'id' );
		if ( ! in_array( $me, $ids, true ) ) {
			$choices[] = array( 'id' => $me, 'label' => WRP_Settings::owner_label( $me ) );
		}
		return $choices;
	}

	private static function scope_from_request( WP_REST_Request $r ) {
		if ( ! WRP_Roles::can_manage_all() ) {
			return get_current_user_id();
		}
		$list = sanitize_key( (string) $r->get_param( 'list' ) );
		if ( in_array( $list, array( 'a', 'b' ), true ) && WRP_Settings::get( 'partner_' . $list . '_user' ) ) {
			return (int) WRP_Settings::get( 'partner_' . $list . '_user' );
		}
		return null;
	}

	public static function format_guest( $g ) {
		$has_card = 'confirmed' === $g->status && $g->pass_code;
		return array(
			'id'             => (int) $g->id,
			'firstName'      => $g->first_name,
			'lastName'       => $g->last_name,
			'name'           => WRP_Guests::full_name( $g ),
			'email'          => $g->email,
			'phone'          => $g->phone,
			'seatsAllowed'   => (int) $g->seats_allowed,
			'seatsConfirmed' => (int) $g->seats_confirmed,
			'companions'     => WRP_Guests::companions( $g ),
			'group'          => $g->group_label,
			'table'          => $g->table_name,
			'status'         => $g->status,
			'statusLabel'    => WRP_Guests::status_label( $g->status ),
			'ownerId'        => (int) $g->owner_id,
			'owner'          => WRP_Settings::owner_label( $g->owner_id ),
			'message'        => (string) $g->guest_message,
			'dietary'        => $g->dietary,
			'notes'          => (string) $g->notes,
			'invitedAt'      => $g->invited_at,
			'respondedAt'    => $g->responded_at,
			'checkedInAt'    => $g->checked_in_at,
			'code'           => $has_card ? WRP_Guests::format_code( $g->pass_code ) : '',
			'links'          => array(
				'rsvp'     => WRP_Guests::rsvp_url( $g ),
				'card'     => $has_card ? WRP_Guests::card_url( $g ) : '',
				'whatsapp' => $g->phone ? WRP_Guests::whatsapp_url( $g ) : '',
				'sms'      => $g->phone ? WRP_Guests::sms_url( $g ) : '',
				'text'     => WRP_Guests::fill( WRP_Settings::get( 'whatsapp_text' ), $g ),
			),
		);
	}

	public static function list_guests( WP_REST_Request $r ) {
		$scope = self::scope_from_request( $r );
		$res   = WRP_Guests::query(
			array(
				'owner_id' => $scope,
				'per_page' => 1000,
				'orderby'  => 'last_name',
			)
		);
		return rest_ensure_response(
			array(
				'guests' => array_map( array( __CLASS__, 'format_guest' ), $res['items'] ),
				'stats'  => WRP_Guests::stats( $scope ),
				'groups' => WRP_Guests::groups( $scope ),
			)
		);
	}

	private static function guest_input( WP_REST_Request $r, $existing = null ) {
		$map  = array(
			'firstName'    => 'first_name',
			'lastName'     => 'last_name',
			'email'        => 'email',
			'phone'        => 'phone',
			'seatsAllowed' => 'seats_allowed',
			'group'        => 'group_label',
			'table'        => 'table_name',
			'dietary'      => 'dietary',
			'notes'        => 'notes',
			'status'       => 'status',
		);
		$data = array();
		foreach ( $map as $in => $col ) {
			if ( null !== $r->get_param( $in ) ) {
				$data[ $col ] = wp_slash( (string) $r->get_param( $in ) );
			}
		}
		$owner = (int) $r->get_param( 'ownerId' );
		if ( $owner && WRP_Roles::can_manage_all() && in_array( $owner, wp_list_pluck( self::owner_choices(), 'id' ), true ) ) {
			$data['owner_id'] = $owner;
		} elseif ( ! $existing ) {
			$data['owner_id'] = get_current_user_id();
		}
		return $data;
	}

	public static function create_guest( WP_REST_Request $r ) {
		$id = WRP_Guests::create( self::guest_input( $r ) );
		if ( is_wp_error( $id ) ) {
			return new WP_Error( 'wrp_invalid', $id->get_error_message(), array( 'status' => 400 ) );
		}
		return rest_ensure_response( array( 'guest' => self::format_guest( WRP_Guests::get( $id ) ) ) );
	}

	private static function guest_or_error( $id ) {
		$g = WRP_Guests::get( (int) $id );
		if ( ! $g || ! WRP_Roles::can_edit_guest( $g ) ) {
			return new WP_Error( 'wrp_forbidden', __( 'Cet invité n’appartient pas à votre liste.', 'wedding-rsvp-pass' ), array( 'status' => 403 ) );
		}
		return $g;
	}

	public static function update_guest( WP_REST_Request $r ) {
		$g = self::guest_or_error( $r['id'] );
		if ( is_wp_error( $g ) ) {
			return $g;
		}
		$row = WRP_Guests::sanitize( self::guest_input( $r, $g ) );
		if ( null !== $r->get_param( 'seatsConfirmed' ) ) {
			$row['seats_confirmed'] = max( 0, min( 20, (int) $r->get_param( 'seatsConfirmed' ) ) );
		}
		// Manual confirmation by the couple also creates the pass (same as wp-admin).
		if ( isset( $row['status'] ) && 'confirmed' === $row['status'] ) {
			if ( empty( $g->pass_code ) ) {
				$row['pass_code'] = WRP_Guests::generate_pass_code();
			}
			if ( empty( $row['seats_confirmed'] ) && empty( $g->seats_confirmed ) ) {
				$row['seats_confirmed'] = isset( $row['seats_allowed'] ) ? $row['seats_allowed'] : $g->seats_allowed;
			}
			if ( ! $g->responded_at ) {
				$row['responded_at'] = current_time( 'mysql' );
			}
		}
		WRP_Guests::update( $g->id, $row, true );
		return rest_ensure_response( array( 'guest' => self::format_guest( WRP_Guests::get( $g->id ) ) ) );
	}

	public static function delete_guest( WP_REST_Request $r ) {
		$g = self::guest_or_error( $r['id'] );
		if ( is_wp_error( $g ) ) {
			return $g;
		}
		WRP_Guests::delete( $g->id );
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	public static function guest_action( WP_REST_Request $r ) {
		$g = self::guest_or_error( $r['id'] );
		if ( is_wp_error( $g ) ) {
			return $g;
		}
		switch ( $r['action'] ) {
			case 'email':
				if ( ! is_email( $g->email ) ) {
					return new WP_Error( 'wrp_no_email', __( 'Cet invité n’a pas d’adresse e-mail.', 'wedding-rsvp-pass' ), array( 'status' => 400 ) );
				}
				$ok = ( 'confirmed' === $g->status && $g->pass_code ) ? WRP_Mailer::send_card( $g ) : WRP_Mailer::send_invitation( $g );
				if ( ! $ok ) {
					return new WP_Error( 'wrp_mail', __( 'L’e-mail n’a pas pu être envoyé. Vérifiez la configuration des e-mails du site.', 'wedding-rsvp-pass' ), array( 'status' => 500 ) );
				}
				break;
			case 'invited':
				WRP_Guests::mark_invited( $g );
				break;
			case 'reset':
				WRP_Guests::reset_checkin( $g->id );
				break;
		}
		return rest_ensure_response( array( 'guest' => self::format_guest( WRP_Guests::get( $g->id ) ) ) );
	}

	/* ---------- Welcome team (scan accounts) ---------- */

	public static function list_team() {
		$users = get_users(
			array(
				'role'    => 'wrp_scanner',
				'orderby' => 'display_name',
			)
		);
		$out   = array();
		foreach ( $users as $u ) {
			$out[] = array(
				'id'      => $u->ID,
				'name'    => $u->display_name,
				'login'   => $u->user_login,
				'email'   => $u->user_email,
				'created' => mysql2date( 'j F Y', $u->user_registered ),
			);
		}
		return rest_ensure_response( array( 'team' => $out ) );
	}

	public static function create_team( WP_REST_Request $r ) {
		$name  = sanitize_text_field( (string) $r->get_param( 'name' ) );
		$email = sanitize_email( (string) $r->get_param( 'email' ) );
		if ( '' === $name ) {
			return new WP_Error( 'wrp_name', __( 'Indiquez le nom de la personne.', 'wedding-rsvp-pass' ), array( 'status' => 400 ) );
		}
		if ( $email && ! is_email( $email ) ) {
			return new WP_Error( 'wrp_email', __( 'Adresse e-mail invalide.', 'wedding-rsvp-pass' ), array( 'status' => 400 ) );
		}
		if ( $email && email_exists( $email ) ) {
			return new WP_Error( 'wrp_email', __( 'Un compte existe déjà avec cette adresse.', 'wedding-rsvp-pass' ), array( 'status' => 400 ) );
		}

		// Short, easy-to-type identifier: "accueil-marie", "accueil-marie2"…
		$base  = 'accueil-' . sanitize_user( strtolower( remove_accents( strtok( $name, ' ' ) ) ), true );
		$base  = preg_replace( '/[^a-z0-9\-]/', '', $base );
		$login = $base;
		$i     = 2;
		while ( username_exists( $login ) ) {
			$login = $base . $i++;
		}
		$alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
		$password = '';
		for ( $k = 0; $k < 10; $k++ ) {
			$password .= $alphabet[ random_int( 0, strlen( $alphabet ) - 1 ) ];
		}

		$id = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_pass'    => $password,
				'user_email'   => $email ? $email : $login . '@' . preg_replace( '/^www\./', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) . '.invalid',
				'display_name' => $name,
				'first_name'   => strtok( $name, ' ' ),
				'role'         => 'wrp_scanner',
			)
		);
		if ( is_wp_error( $id ) ) {
			return new WP_Error( 'wrp_user', $id->get_error_message(), array( 'status' => 400 ) );
		}
		update_user_meta( $id, 'wrp_created_by', get_current_user_id() );

		return rest_ensure_response(
			array(
				'member'   => array(
					'id'    => $id,
					'name'  => $name,
					'login' => $login,
					'email' => $email,
				),
				'password' => $password,
				'message'  => sprintf(
					/* translators: 1: name 2: couple 3: url 4: login 5: password */
					__( "Bonjour %1\$s ! Merci de nous aider à accueillir les invités du mariage de %2\$s 💍\n\n1. Ouvrez ce lien sur votre téléphone : %3\$s\n2. Identifiant : %4\$s\n3. Mot de passe : %5\$s\n4. Ajoutez l'application à votre écran d'accueil. Le jour J, le scan fonctionne même sans réseau.", 'wedding-rsvp-pass' ),
					strtok( $name, ' ' ),
					WRP_Settings::couple_names(),
					WRP_Public::url( 'app' ),
					$login,
					$password
				),
			)
		);
	}

	public static function delete_team( WP_REST_Request $r ) {
		$u = get_userdata( (int) $r['id'] );
		if ( ! $u || ! in_array( 'wrp_scanner', (array) $u->roles, true ) || count( (array) $u->roles ) > 1 ) {
			return new WP_Error( 'wrp_forbidden', __( 'Seuls les comptes « Agent d’accueil » peuvent être supprimés ici.', 'wedding-rsvp-pass' ), array( 'status' => 403 ) );
		}
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $u->ID );
		return rest_ensure_response( array( 'deleted' => true ) );
	}
}

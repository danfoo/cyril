<?php
/**
 * Public pages: RSVP page, invitation card, offline scanner (PWA).
 *
 * URLs (pretty permalinks):
 *   /rsvp/{token}/          → guest answers
 *   /invitation/{token}/    → invitation card with QR code
 *   /espace-maries/         → installable app (PWA): login, guest lists, team
 *   /espace-maries/scanner/ → offline QR scanner for the wedding day
 *
 * @package WeddingRSVPPass
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WRP_Public {

	public static function slugs() {
		return apply_filters(
			'wrp_slugs',
			array(
				'rsvp'       => 'rsvp',
				'invitation' => 'invitation',
				'app'        => 'espace-maries',
			)
		);
	}

	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'route' ), 0 );
		add_filter( 'redirect_canonical', array( __CLASS__, 'no_canonical_for_sw' ), 10, 2 );
		add_shortcode( 'wrp_countdown', array( __CLASS__, 'shortcode_countdown' ) );
		add_shortcode( 'wrp_find_invitation', array( __CLASS__, 'shortcode_find' ) );
		add_action( 'admin_post_nopriv_wrp_find', array( __CLASS__, 'handle_find' ) );
		add_action( 'admin_post_wrp_find', array( __CLASS__, 'handle_find' ) );
	}

	/* ------------------------------------------------------------------
	 * "Find my invitation": a guest types their e-mail and receives their
	 * personal link again. Same answer whether or not the e-mail exists
	 * (no list enumeration), rate-limited per IP.
	 * ---------------------------------------------------------------- */

	public static function shortcode_find( $atts = array() ) {
		$atts   = shortcode_atts( array( 'button' => __( 'Recevoir mon lien', 'wedding-rsvp-pass' ) ), $atts );
		$status = isset( $_GET['wrp_find'] ) ? sanitize_key( $_GET['wrp_find'] ) : ''; // phpcs:ignore
		$out    = '<form class="wrp-find" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '#rsvp">';
		$out   .= '<input type="hidden" name="action" value="wrp_find">';
		$out   .= wp_nonce_field( 'wrp_find', 'wrp_find_nonce', true, false );
		$out   .= '<input type="hidden" name="back" value="' . esc_attr( get_permalink() ? get_permalink() : home_url( '/' ) ) . '">';
		$out   .= '<label class="screen-reader-text" for="wrp-find-email">' . esc_html__( 'Votre adresse e-mail', 'wedding-rsvp-pass' ) . '</label>';
		$out   .= '<input id="wrp-find-email" type="email" name="email" required placeholder="' . esc_attr__( 'Votre adresse e-mail', 'wedding-rsvp-pass' ) . '">';
		$out   .= '<input type="text" name="website" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">';
		$out   .= '<button type="submit">' . esc_html( $atts['button'] ) . '</button>';
		$out   .= '</form>';
		if ( 'sent' === $status ) {
			$out .= '<p class="wrp-find__msg">' . esc_html__( 'Si votre adresse figure sur notre liste, vous allez recevoir votre lien personnel dans quelques instants. Pensez à vérifier vos indésirables ✉', 'wedding-rsvp-pass' ) . '</p>';
		} elseif ( 'wait' === $status ) {
			$out .= '<p class="wrp-find__msg">' . esc_html__( 'Trop de demandes, merci de réessayer un peu plus tard.', 'wedding-rsvp-pass' ) . '</p>';
		}
		return $out;
	}

	public static function handle_find() {
		$back = isset( $_POST['back'] ) ? esc_url_raw( wp_unslash( $_POST['back'] ) ) : home_url( '/' );
		$back = wp_validate_redirect( $back, home_url( '/' ) );

		if ( ! isset( $_POST['wrp_find_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wrp_find_nonce'] ) ), 'wrp_find' ) || ! empty( $_POST['website'] ) ) {
			wp_safe_redirect( add_query_arg( 'wrp_find', 'sent', $back ) . '#rsvp' );
			exit;
		}

		$ip     = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'x';
		$bucket = 'wrp_find_' . md5( $ip );
		$count  = (int) get_transient( $bucket );
		if ( $count >= 5 ) {
			wp_safe_redirect( add_query_arg( 'wrp_find', 'wait', $back ) . '#rsvp' );
			exit;
		}
		set_transient( $bucket, $count + 1, HOUR_IN_SECONDS );

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		if ( is_email( $email ) ) {
			global $wpdb;
			$t     = WRP_DB::guests_table();
			$guest = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE email = %s LIMIT 1", $email ) ); // phpcs:ignore
			if ( $guest ) {
				if ( 'confirmed' === $guest->status && $guest->pass_code ) {
					WRP_Mailer::send_card( $guest );
				} else {
					WRP_Mailer::send_invitation( $guest );
				}
			}
		}
		wp_safe_redirect( add_query_arg( 'wrp_find', 'sent', $back ) . '#rsvp' );
		exit;
	}

	public static function add_rewrite_rules() {
		$s = self::slugs();
		add_rewrite_rule( '^' . preg_quote( $s['rsvp'], '#' ) . '/([a-f0-9]{32})/?$', 'index.php?wrp_view=rsvp&wrp_token=$matches[1]', 'top' );
		add_rewrite_rule( '^' . preg_quote( $s['invitation'], '#' ) . '/([a-f0-9]{32})/?$', 'index.php?wrp_view=invitation&wrp_token=$matches[1]', 'top' );
		$app = preg_quote( $s['app'], '#' );
		add_rewrite_rule( '^' . $app . '/sw\.js$', 'index.php?wrp_view=sw', 'top' );
		add_rewrite_rule( '^' . $app . '/manifest\.webmanifest$', 'index.php?wrp_view=manifest', 'top' );
		add_rewrite_rule( '^' . $app . '/scanner/?$', 'index.php?wrp_view=scanner', 'top' );
		add_rewrite_rule( '^' . $app . '/?$', 'index.php?wrp_view=app', 'top' );
		// Version 1.0 scanner URL.
		add_rewrite_rule( '^wrp-scanner/sw\.js$', 'index.php?wrp_view=legacy_sw', 'top' );
		add_rewrite_rule( '^wrp-scanner/?$', 'index.php?wrp_view=legacy_scanner', 'top' );

		if ( get_option( 'wrp_flush_rules' ) ) {
			delete_option( 'wrp_flush_rules' );
			flush_rewrite_rules( false );
		}
	}

	public static function query_vars( $vars ) {
		$vars[] = 'wrp_view';
		$vars[] = 'wrp_token';
		return $vars;
	}

	/**
	 * Build a public URL, working with or without pretty permalinks.
	 *
	 * @param string $view  rsvp|invitation|scanner|sw|manifest.
	 * @param string $token Guest token.
	 */
	public static function url( $view, $token = '' ) {
		$s = self::slugs();
		if ( get_option( 'permalink_structure' ) ) {
			switch ( $view ) {
				case 'rsvp':
				case 'invitation':
					return home_url( user_trailingslashit( $s[ $view ] . '/' . $token ) );
				case 'app':
					return home_url( $s['app'] . '/' );
				case 'scanner':
					return home_url( $s['app'] . '/scanner/' );
				case 'sw':
					return home_url( $s['app'] . '/sw.js' );
				case 'manifest':
					return home_url( $s['app'] . '/manifest.webmanifest' );
			}
		}
		$args = array( 'wrp_view' => $view );
		if ( $token ) {
			$args['wrp_token'] = $token;
		}
		return add_query_arg( $args, home_url( '/' ) );
	}

	public static function no_canonical_for_sw( $redirect, $requested ) {
		if ( get_query_var( 'wrp_view' ) && in_array( get_query_var( 'wrp_view' ), array( 'sw', 'manifest', 'legacy_sw', 'app', 'scanner' ), true ) ) {
			return false;
		}
		return $redirect;
	}

	public static function route() {
		$view = get_query_var( 'wrp_view' );
		if ( ! $view ) {
			return;
		}

		switch ( $view ) {
			case 'rsvp':
				self::view_rsvp();
				break;
			case 'invitation':
				self::view_invitation();
				break;
			case 'scanner':
				self::view_scanner();
				break;
			case 'sw':
				self::serve_service_worker();
				break;
			case 'manifest':
				self::serve_manifest();
				break;
			case 'app':
				WRP_App::view();
				break;
			case 'legacy_scanner':
				wp_safe_redirect( self::url( 'scanner' ), 301 );
				break;
			case 'legacy_sw':
				header( 'Content-Type: application/javascript; charset=utf-8' );
				header( 'Cache-Control: no-cache' );
				echo "self.addEventListener('install',function(){self.skipWaiting();});self.addEventListener('activate',function(){self.registration.unregister();});";
				break;
			default:
				return;
		}
		exit;
	}

	/* ------------------------------------------------------------------
	 * Helpers
	 * ---------------------------------------------------------------- */

	private static function private_headers() {
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		header( 'Referrer-Policy: no-referrer', true );
	}

	private static function not_found() {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		self::private_headers();
		self::render( 'not-found', array() );
	}

	/**
	 * Render a template. Themes can override with
	 * {theme}/wedding-rsvp-pass/{name}.php
	 */
	public static function render( $name, array $vars ) {
		$template = locate_template( 'wedding-rsvp-pass/' . $name . '.php' );
		if ( ! $template ) {
			$template = WRP_DIR . 'templates/' . $name . '.php';
		}
		$vars = apply_filters( 'wrp_template_vars', $vars, $name );
		extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		include $template;
	}

	public static function asset( $path ) {
		$file = WRP_DIR . 'assets/' . $path;
		$ver  = file_exists( $file ) ? WRP_VERSION . '.' . filemtime( $file ) : WRP_VERSION;
		return add_query_arg( 'ver', $ver, WRP_URL . 'assets/' . $path );
	}

	/**
	 * Inline CSS variables from the settings colours.
	 */
	public static function css_vars() {
		$accent = sanitize_hex_color( WRP_Settings::get( 'color_accent' ) ) ?: '#b8925a';
		$dark   = sanitize_hex_color( WRP_Settings::get( 'color_dark' ) ) ?: '#3b2f2a';
		$blush  = sanitize_hex_color( WRP_Settings::get( 'color_blush' ) ) ?: '#f4e1dc';
		return sprintf( ':root{--wrp-accent:%s;--wrp-dark:%s;--wrp-blush:%s}', $accent, $dark, $blush );
	}

	public static function fonts_url() {
		return apply_filters( 'wrp_fonts_url', 'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400&family=Great+Vibes&family=Montserrat:wght@300;400;500;600&display=swap' );
	}

	/**
	 * Data shared by the RSVP + card templates.
	 */
	public static function wedding_data() {
		return array(
			'names'       => WRP_Settings::couple_names(),
			'partner_a'   => WRP_Settings::get( 'partner_a_name' ),
			'partner_b'   => WRP_Settings::get( 'partner_b_name' ),
			'date'        => WRP_Settings::wedding_date_display(),
			'date_short'  => wp_date( 'd . m . Y', WRP_Settings::wedding_timestamp() ),
			'time'        => WRP_Settings::wedding_time_display(),
			'timestamp'   => WRP_Settings::wedding_timestamp(),
			'venue'       => WRP_Settings::get( 'venue_name' ),
			'address'     => WRP_Settings::get( 'venue_address' ),
			'map_url'     => WRP_Settings::get( 'map_url' ),
			'dress_code'  => WRP_Settings::get( 'dress_code' ),
			'deadline'    => WRP_Settings::deadline_display(),
			'invite_line' => WRP_Settings::get( 'card_invite_line' ),
			'quote'       => WRP_Settings::get( 'card_quote' ),
		);
	}

	/* ------------------------------------------------------------------
	 * RSVP
	 * ---------------------------------------------------------------- */

	private static function view_rsvp() {
		$guest = WRP_Guests::get_by_token( get_query_var( 'wrp_token' ) );
		if ( ! $guest ) {
			self::not_found();
			return;
		}
		self::private_headers();

		$error  = '';
		$closed = WRP_Settings::rsvp_is_closed();

		if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) { // phpcs:ignore
			$nonce_ok = isset( $_POST['wrp_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wrp_nonce'] ) ), 'wrp_rsvp_' . $guest->token );
			$locked   = 'confirmed' === $guest->status && ! WRP_Settings::get( 'allow_change_after_confirm' );

			if ( ! $nonce_ok ) {
				$error = __( 'La session a expiré. Merci de renvoyer le formulaire.', 'wedding-rsvp-pass' );
			} elseif ( $closed ) {
				$error = __( 'Les réponses sont closes. Contactez directement les mariés.', 'wedding-rsvp-pass' );
			} elseif ( $locked ) {
				$error = __( 'Votre présence est déjà confirmée.', 'wedding-rsvp-pass' );
			} else {
				$answer  = isset( $_POST['wrp_answer'] ) ? sanitize_key( $_POST['wrp_answer'] ) : '';
				$message = isset( $_POST['wrp_message'] ) ? wp_unslash( $_POST['wrp_message'] ) : ''; // phpcs:ignore -- sanitized in model.
				if ( 'yes' === $answer ) {
					$seats      = isset( $_POST['wrp_seats'] ) ? (int) $_POST['wrp_seats'] : 1;
					$companions = isset( $_POST['wrp_companions'] ) && is_array( $_POST['wrp_companions'] ) ? wp_unslash( $_POST['wrp_companions'] ) : array(); // phpcs:ignore
					$dietary    = isset( $_POST['wrp_dietary'] ) ? wp_unslash( $_POST['wrp_dietary'] ) : ''; // phpcs:ignore
					WRP_Guests::confirm( $guest, $seats, (array) $companions, $message, $dietary );
					wp_safe_redirect( add_query_arg( 'bienvenue', '1', WRP_Guests::card_url( $guest ) ) );
					exit;
				} elseif ( 'no' === $answer ) {
					WRP_Guests::decline( $guest, $message );
					wp_safe_redirect( add_query_arg( 'reponse', 'merci', WRP_Guests::rsvp_url( $guest ) ) );
					exit;
				} else {
					$error = __( 'Merci d’indiquer si vous serez présent·e.', 'wedding-rsvp-pass' );
				}
			}
		}

		// Track that the invitation was opened (counts as "invited").
		if ( 'pending' === $guest->status ) {
			WRP_Guests::mark_invited( $guest );
			$guest = WRP_Guests::get( $guest->id );
		}

		self::render(
			'rsvp',
			array(
				'guest'      => $guest,
				'wedding'    => self::wedding_data(),
				'error'      => $error,
				'closed'     => $closed,
				'can_change' => (bool) WRP_Settings::get( 'allow_change_after_confirm' ),
				'just_declined' => isset( $_GET['reponse'] ) && 'declined' === $guest->status, // phpcs:ignore
			)
		);
	}

	/* ------------------------------------------------------------------
	 * Invitation card
	 * ---------------------------------------------------------------- */

	private static function view_invitation() {
		$guest = WRP_Guests::get_by_token( get_query_var( 'wrp_token' ) );
		if ( ! $guest ) {
			self::not_found();
			return;
		}
		if ( 'confirmed' !== $guest->status || ! $guest->pass_code ) {
			wp_safe_redirect( WRP_Guests::rsvp_url( $guest ) );
			exit;
		}
		self::private_headers();

		$companions = WRP_Guests::companions( $guest );
		$card       = array_merge(
			self::wedding_data(),
			array(
				'guest_name'  => WRP_Guests::full_name( $guest ),
				'companions'  => $companions,
				'seats'       => (int) $guest->seats_confirmed,
				'table'       => $guest->table_name,
				'code'        => $guest->pass_code,
				'code_pretty' => WRP_Guests::format_code( $guest->pass_code ),
				'qr_payload'  => WRP_Guests::QR_PREFIX . $guest->pass_code,
				'rsvp_url'    => WRP_Guests::rsvp_url( $guest ),
				'accent'      => sanitize_hex_color( WRP_Settings::get( 'color_accent' ) ) ?: '#b8925a',
				'dark'        => sanitize_hex_color( WRP_Settings::get( 'color_dark' ) ) ?: '#3b2f2a',
				'blush'       => sanitize_hex_color( WRP_Settings::get( 'color_blush' ) ) ?: '#f4e1dc',
				'checked_in'  => (bool) $guest->checked_in_at,
			)
		);

		self::render(
			'invitation',
			array(
				'guest'   => $guest,
				'card'    => $card,
				'welcome' => isset( $_GET['bienvenue'] ), // phpcs:ignore
			)
		);
	}

	/* ------------------------------------------------------------------
	 * Offline scanner (PWA)
	 * ---------------------------------------------------------------- */

	/**
	 * Everything the installable app needs offline (cached by the service worker).
	 */
	public static function pwa_assets() {
		return array(
			self::asset( 'app/app.css' ),
			self::asset( 'app/app.js' ),
			self::asset( 'scanner/scanner.css' ),
			self::asset( 'scanner/scanner.js' ),
			self::asset( 'vendor/jsQR.js' ),
			self::asset( 'vendor/qrcode.js' ),
			self::asset( 'img/icon-192.png' ),
			self::asset( 'img/icon-512.png' ),
		);
	}

	/**
	 * PWA pages must be served on their trailing-slash URL (service worker scope).
	 *
	 * @param string $canonical URL.
	 */
	public static function enforce_trailing_slash( $canonical ) {
		if ( get_option( 'permalink_structure' ) ) {
			$req = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH ) : '';
			if ( $req && '/' !== substr( $req, -1 ) ) {
				wp_safe_redirect( $canonical, 301 );
				exit;
			}
		}
	}

	/**
	 * Config shared by the app and the scanner.
	 */
	/**
	 * Build id of the installable app: changes whenever the plugin or one of its
	 * app files changes, so installed apps know they must update.
	 */
	public static function pwa_build() {
		return substr( md5( WRP_VERSION . '|' . implode( '|', self::pwa_assets() ) . '|' . filemtime( WRP_DIR . 'templates/app.php' ) . '|' . filemtime( WRP_DIR . 'templates/scanner.php' ) ), 0, 10 );
	}

	/**
	 * Make sure no page-cache plugin / host cache keeps an old copy of the app.
	 */
	public static function no_page_cache() {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // WP Rocket, W3TC, WP Super Cache…
		}
		if ( ! defined( 'DONOTCACHEOBJECT' ) ) {
			define( 'DONOTCACHEOBJECT', true );
		}
		nocache_headers();
		header( 'Cache-Control: no-cache, no-store, must-revalidate, max-age=0', true );
		header( 'X-LiteSpeed-Cache-Control: no-cache', true );
		header( 'CDN-Cache-Control: no-store', true );
		do_action( 'litespeed_control_set_nocache', 'wedding app' );
	}

	public static function pwa_config() {
		return array(
			'ajax'      => admin_url( 'admin-ajax.php' ),
			'sw'        => self::url( 'sw' ),
			'scope'     => self::url( 'app' ),
			'appUrl'    => self::url( 'app' ),
			'scannerUrl' => self::url( 'scanner' ),
			'names'     => WRP_Settings::couple_names(),
			'date'      => WRP_Settings::wedding_date_display( 'j F Y' ),
			'timestamp' => WRP_Settings::wedding_timestamp() * 1000,
			'siteId'    => substr( md5( home_url() ), 0, 10 ),
			'version'   => WRP_VERSION,
			'build'     => self::pwa_build(),
		);
	}

	private static function view_scanner() {
		self::enforce_trailing_slash( self::url( 'scanner' ) );
		self::no_page_cache();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		self::render(
			'scanner',
			array(
				'config' => array_merge(
					self::pwa_config(),
					array(
						'endpoints' => array(
							'hello'    => esc_url_raw( rest_url( 'wrp/v1/scanner/hello' ) ),
							'guests'   => esc_url_raw( rest_url( 'wrp/v1/scanner/guests' ) ),
							'checkins' => esc_url_raw( rest_url( 'wrp/v1/scanner/checkins' ) ),
						),
					)
				),
			)
		);
	}

	private static function serve_service_worker() {
		self::no_page_cache();
		header( 'Content-Type: application/javascript; charset=utf-8' );
		header( 'Service-Worker-Allowed: ' . wp_parse_url( self::url( 'app' ), PHP_URL_PATH ) );
		header( 'Cache-Control: no-cache' );
		header( 'X-Robots-Tag: noindex' );

		$shells   = array( self::url( 'app' ), self::url( 'scanner' ) );
		$assets   = array_merge( self::pwa_assets(), $shells, array( self::url( 'manifest' ) ) );
		$version  = WRP_VERSION . '-' . self::pwa_build();

		$sw = file_get_contents( WRP_DIR . 'assets/scanner/sw.js' ); // phpcs:ignore
		$sw = str_replace(
			array( '__WRP_CACHE_VERSION__', '__WRP_ASSETS__', '__WRP_SHELLS__' ),
			array( $version, wp_json_encode( array_values( $assets ) ), wp_json_encode( $shells ) ),
			$sw
		);
		echo $sw; // phpcs:ignore
	}

	private static function serve_manifest() {
		self::no_page_cache();
		header( 'Content-Type: application/manifest+json; charset=utf-8' );
		header( 'Cache-Control: no-cache' );
		$manifest = array(
			'id'               => wp_parse_url( self::url( 'app' ), PHP_URL_PATH ),
			/* translators: %s couple names */
			'name'             => sprintf( __( 'Mariage %s', 'wedding-rsvp-pass' ), WRP_Settings::couple_names() ),
			'short_name'       => WRP_Settings::couple_names( '&' ),
			'description'      => __( 'Espace des mariés : invités, réponses et scanner du jour J.', 'wedding-rsvp-pass' ),
			'lang'             => 'fr',
			'start_url'        => self::url( 'app' ),
			'scope'            => self::url( 'app' ),
			'display'          => 'standalone',
			'orientation'      => 'portrait',
			'background_color' => '#1d1714',
			'theme_color'      => '#1d1714',
			'icons'            => array(
				array(
					'src'   => self::asset( 'img/icon-192.png' ),
					'sizes' => '192x192',
					'type'  => 'image/png',
				),
				array(
					'src'     => self::asset( 'img/icon-512.png' ),
					'sizes'   => '512x512',
					'type'    => 'image/png',
					'purpose' => 'any maskable',
				),
			),
			'shortcuts'        => array(
				array(
					'name'      => __( 'Scanner les cartons', 'wedding-rsvp-pass' ),
					'short_name' => __( 'Scanner', 'wedding-rsvp-pass' ),
					'url'       => self::url( 'scanner' ),
					'icons'     => array( array( 'src' => self::asset( 'img/icon-192.png' ), 'sizes' => '192x192' ) ),
				),
			),
		);
		echo wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore
	}

	/* ------------------------------------------------------------------
	 * Shortcode: [wrp_countdown]
	 * ---------------------------------------------------------------- */

	public static function shortcode_countdown() {
		$ts = WRP_Settings::wedding_timestamp();
		return sprintf(
			'<div class="wrp-countdown" data-wrp-countdown="%1$d"><span><b data-u="d">0</b>%2$s</span><span><b data-u="h">0</b>%3$s</span><span><b data-u="m">0</b>%4$s</span><span><b data-u="s">0</b>%5$s</span></div><script>(function(){document.querySelectorAll("[data-wrp-countdown]").forEach(function(el){var t=+el.dataset.wrpCountdown*1000;function u(){var d=Math.max(0,t-Date.now());var v={d:Math.floor(d/864e5),h:Math.floor(d/36e5)%%24,m:Math.floor(d/6e4)%%60,s:Math.floor(d/1e3)%%60};el.querySelectorAll("[data-u]").forEach(function(b){b.textContent=v[b.dataset.u]})}u();setInterval(u,1000)})})();</script>',
			(int) $ts,
			esc_html__( 'jours', 'wedding-rsvp-pass' ),
			esc_html__( 'heures', 'wedding-rsvp-pass' ),
			esc_html__( 'minutes', 'wedding-rsvp-pass' ),
			esc_html__( 'secondes', 'wedding-rsvp-pass' )
		);
	}
}

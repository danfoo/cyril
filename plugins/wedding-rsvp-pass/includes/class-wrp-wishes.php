<?php
/**
 * Guest book (« Livre d'or »).
 *
 * - Public form + wall: shortcode [wrp_guestbook] (a page is created automatically).
 * - Latest wishes carousel: shortcode [wrp_wishes_carousel count="12"].
 * - Messages left on the RSVP page can feed the guest book (setting).
 * - Moderation from wp-admin (Invités → Livre d'or) and from the app.
 *
 * @package WeddingRSVPPass
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WRP_Wishes {

	const NS = 'wrp/v1';

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'wrp_wishes';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$t   = self::table();
		$cs  = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE $t (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			guest_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			name VARCHAR(120) NOT NULL DEFAULT '',
			relation VARCHAR(120) NOT NULL DEFAULT '',
			message TEXT NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			source VARCHAR(20) NOT NULL DEFAULT 'form',
			ip_hash CHAR(32) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY guest_id (guest_id)
		) $cs;"
		);
		// Pages can only be created once post types exist (init).
		if ( ! get_option( 'wrp_guestbook_page' ) ) {
			update_option( 'wrp_need_guestbook_page', 1 );
		}
	}

	/**
	 * Create the « Livre d'or » page once (never recreated if the couple deletes it).
	 */
	public static function ensure_page() {
		if ( get_option( 'wrp_guestbook_page' ) ) {
			return;
		}
		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Livre d’or', 'wedding-rsvp-pass' ),
				'post_name'    => 'livre-d-or',
				'post_content' => '<!-- wp:shortcode -->[wrp_guestbook]<!-- /wp:shortcode -->',
				'comment_status' => 'closed',
			)
		);
		if ( $id && ! is_wp_error( $id ) ) {
			update_option( 'wrp_guestbook_page', (int) $id );
		}
	}

	public static function page_url() {
		$id = (int) get_option( 'wrp_guestbook_page' );
		if ( $id && 'publish' === get_post_status( $id ) ) {
			return get_permalink( $id );
		}
		return '';
	}

	public static function init() {
		add_shortcode( 'wrp_guestbook', array( __CLASS__, 'shortcode_guestbook' ) );
		add_shortcode( 'wrp_wishes_carousel', array( __CLASS__, 'shortcode_carousel' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'wrp_guest_confirmed', array( __CLASS__, 'from_guest' ) );
		add_action( 'wrp_guest_declined', array( __CLASS__, 'from_guest' ) );
		add_action(
			'init',
			function () {
				if ( get_option( 'wrp_need_guestbook_page' ) ) {
					delete_option( 'wrp_need_guestbook_page' );
					self::ensure_page();
				}
			},
			20
		);
	}

	/* ------------------------------------------------------------------
	 * Model
	 * ---------------------------------------------------------------- */

	public static function moderated() {
		return (bool) WRP_Settings::get( 'wishes_moderation' );
	}

	public static function format( $w ) {
		return array(
			'id'       => (int) $w->id,
			'name'     => $w->name,
			'relation' => $w->relation,
			'message'  => $w->message,
			'status'   => $w->status,
			'source'   => $w->source,
			'date'     => mysql2date( 'j F Y', $w->created_at ),
			'ago'      => sprintf( __( 'il y a %s', 'wedding-rsvp-pass' ), human_time_diff( mysql2date( 'U', $w->created_at, false ), current_time( 'timestamp' ) ) ),
		);
	}

	public static function query( $status = 'approved', $limit = 20, $offset = 0 ) {
		global $wpdb;
		$t     = self::table();
		$limit = max( 1, min( 200, (int) $limit ) );
		if ( 'all' === $status ) {
			$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t ORDER BY created_at DESC LIMIT %d OFFSET %d", $limit, (int) $offset ) ); // phpcs:ignore
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t" ); // phpcs:ignore
		} else {
			$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE status = %s ORDER BY created_at DESC LIMIT %d OFFSET %d", $status, $limit, (int) $offset ) ); // phpcs:ignore
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE status = %s", $status ) ); // phpcs:ignore
		}
		return array(
			'items' => $rows,
			'total' => $total,
		);
	}

	public static function counts() {
		global $wpdb;
		$t   = self::table();
		$out = array(
			'pending'  => 0,
			'approved' => 0,
			'hidden'   => 0,
		);
		foreach ( (array) $wpdb->get_results( "SELECT status, COUNT(*) n FROM $t GROUP BY status" ) as $r ) { // phpcs:ignore
			$out[ $r->status ] = (int) $r->n;
		}
		return $out;
	}

	public static function add( $name, $relation, $message, $source = 'form', $guest_id = 0 ) {
		global $wpdb;
		$name    = mb_substr( sanitize_text_field( $name ), 0, 120 );
		$rel     = mb_substr( sanitize_text_field( $relation ), 0, 120 );
		$message = mb_substr( trim( sanitize_textarea_field( $message ) ), 0, 1500 );
		if ( '' === $name || mb_strlen( $message ) < 2 ) {
			return new WP_Error( 'wrp_wish', __( 'Merci d’indiquer votre nom et votre message.', 'wedding-rsvp-pass' ) );
		}
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$wpdb->insert(
			self::table(),
			array(
				'guest_id'   => (int) $guest_id,
				'name'       => $name,
				'relation'   => $rel,
				'message'    => $message,
				'status'     => self::moderated() ? 'pending' : 'approved',
				'source'     => $source,
				'ip_hash'    => $ip ? md5( wp_salt() . $ip ) : '',
				'created_at' => current_time( 'mysql' ),
			)
		);
		$id = (int) $wpdb->insert_id;
		do_action( 'wrp_wish_added', $id );
		return $id;
	}

	public static function get( $id ) {
		global $wpdb;
		$t = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", $id ) ); // phpcs:ignore
	}

	public static function set_status( $id, $status ) {
		global $wpdb;
		if ( ! in_array( $status, array( 'pending', 'approved', 'hidden' ), true ) ) {
			return false;
		}
		return false !== $wpdb->update( self::table(), array( 'status' => $status ), array( 'id' => (int) $id ) );
	}

	public static function delete( $id ) {
		global $wpdb;
		return (bool) $wpdb->delete( self::table(), array( 'id' => (int) $id ) );
	}

	/**
	 * A guest left a message on the RSVP page → it becomes a wish (one per guest).
	 *
	 * @param object $guest Guest row.
	 */
	public static function from_guest( $guest ) {
		if ( ! WRP_Settings::get( 'wishes_from_rsvp' ) || ! $guest || '' === trim( (string) $guest->guest_message ) ) {
			return;
		}
		global $wpdb;
		$t        = self::table();
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE guest_id = %d AND source = 'rsvp'", $guest->id ) ); // phpcs:ignore
		if ( $existing ) {
			if ( $existing->message !== $guest->guest_message ) {
				$wpdb->update( $t, array( 'message' => mb_substr( $guest->guest_message, 0, 1500 ) ), array( 'id' => $existing->id ) );
			}
			return;
		}
		self::add( WRP_Guests::full_name( $guest ), '', $guest->guest_message, 'rsvp', $guest->id );
	}

	/* ------------------------------------------------------------------
	 * REST (public)
	 * ---------------------------------------------------------------- */

	public static function routes() {
		register_rest_route(
			self::NS,
			'/wishes',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'rest_list' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'rest_submit' ),
					'permission_callback' => '__return_true',
				),
			)
		);
		$manage = function () {
			return current_user_can( 'wrp_manage_guests' );
		};
		register_rest_route(
			self::NS,
			'/app/wishes',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_app_list' ),
				'permission_callback' => $manage,
			)
		);
		register_rest_route(
			self::NS,
			'/app/wishes/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'rest_app_update' ),
					'permission_callback' => $manage,
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( __CLASS__, 'rest_app_delete' ),
					'permission_callback' => $manage,
				),
			)
		);
	}

	public static function rest_list( WP_REST_Request $r ) {
		$res = self::query( 'approved', (int) $r->get_param( 'per_page' ) ? (int) $r->get_param( 'per_page' ) : 12, (int) $r->get_param( 'offset' ) );
		return rest_ensure_response(
			array(
				'items' => array_map( array( __CLASS__, 'format' ), $res['items'] ),
				'total' => $res['total'],
			)
		);
	}

	public static function rest_submit( WP_REST_Request $r ) {
		// Honeypot + "too fast" trap (bots submit instantly).
		if ( '' !== (string) $r->get_param( 'website' ) ) {
			return rest_ensure_response( array( 'ok' => true, 'status' => 'pending' ) );
		}
		$started = (int) $r->get_param( 't' );
		if ( $started && ( time() * 1000 - $started ) < 3000 ) {
			return new WP_Error( 'wrp_fast', __( 'Prenez le temps d’écrire votre message ♥', 'wedding-rsvp-pass' ), array( 'status' => 400 ) );
		}
		$ip     = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'x';
		$bucket = 'wrp_wish_' . md5( $ip );
		$n      = (int) get_transient( $bucket );
		if ( $n >= 5 ) {
			return new WP_Error( 'wrp_rate', __( 'Vous avez déjà laissé plusieurs messages, merci ! Réessayez un peu plus tard.', 'wedding-rsvp-pass' ), array( 'status' => 429 ) );
		}
		$id = self::add( (string) $r->get_param( 'name' ), (string) $r->get_param( 'relation' ), (string) $r->get_param( 'message' ) );
		if ( is_wp_error( $id ) ) {
			return new WP_Error( $id->get_error_code(), $id->get_error_message(), array( 'status' => 400 ) );
		}
		set_transient( $bucket, $n + 1, HOUR_IN_SECONDS );
		$w = self::get( $id );
		return rest_ensure_response(
			array(
				'ok'     => true,
				'status' => $w->status,
				'wish'   => 'approved' === $w->status ? self::format( $w ) : null,
			)
		);
	}

	public static function rest_app_list( WP_REST_Request $r ) {
		$status = sanitize_key( (string) $r->get_param( 'status' ) );
		$status = in_array( $status, array( 'pending', 'approved', 'hidden', 'all' ), true ) ? $status : 'all';
		$res    = self::query( $status, 200 );
		return rest_ensure_response(
			array(
				'items'     => array_map( array( __CLASS__, 'format' ), $res['items'] ),
				'counts'    => self::counts(),
				'moderated' => self::moderated(),
				'pageUrl'   => self::page_url(),
			)
		);
	}

	public static function rest_app_update( WP_REST_Request $r ) {
		if ( ! self::get( (int) $r['id'] ) ) {
			return new WP_Error( 'wrp_404', __( 'Message introuvable.', 'wedding-rsvp-pass' ), array( 'status' => 404 ) );
		}
		self::set_status( (int) $r['id'], sanitize_key( (string) $r->get_param( 'status' ) ) );
		return rest_ensure_response(
			array(
				'wish'   => self::format( self::get( (int) $r['id'] ) ),
				'counts' => self::counts(),
			)
		);
	}

	public static function rest_app_delete( WP_REST_Request $r ) {
		self::delete( (int) $r['id'] );
		return rest_ensure_response( array( 'deleted' => true, 'counts' => self::counts() ) );
	}

	/* ------------------------------------------------------------------
	 * Front-end
	 * ---------------------------------------------------------------- */

	public static function register_assets() {
		wp_register_style( 'wrp-guestbook', WRP_Public::asset( 'css/guestbook.css' ), array(), null );
		wp_add_inline_style( 'wrp-guestbook', WRP_Public::css_vars() );
		wp_register_script( 'wrp-guestbook', WRP_Public::asset( 'js/guestbook.js' ), array(), null, true );
		wp_localize_script(
			'wrp-guestbook',
			'WRP_GB',
			array(
				'api'       => esc_url_raw( rest_url( self::NS . '/wishes' ) ),
				'moderated' => self::moderated(),
				'i18n'      => array(
					'thanks'      => __( 'Merci pour ce joli mot ♥', 'wedding-rsvp-pass' ),
					'pending'     => __( 'Il apparaîtra dans le livre d’or dès que les mariés l’auront lu.', 'wedding-rsvp-pass' ),
					'published'   => __( 'Il est maintenant dans le livre d’or.', 'wedding-rsvp-pass' ),
					'error'       => __( 'Oups, le message n’a pas pu être envoyé. Réessayez.', 'wedding-rsvp-pass' ),
					'more'        => __( 'Voir plus de vœux', 'wedding-rsvp-pass' ),
					'chars'       => __( 'caractères restants', 'wedding-rsvp-pass' ),
				),
			)
		);
		// Load early on pages that contain our shortcodes (no flash of unstyled content).
		$post = get_post();
		if ( is_singular() && $post && ( has_shortcode( $post->post_content, 'wrp_guestbook' ) || has_shortcode( $post->post_content, 'wrp_wishes_carousel' ) ) ) {
			wp_enqueue_style( 'wrp-guestbook' );
		}
		if ( apply_filters( 'wrp_enqueue_guestbook_assets', false ) ) {
			wp_enqueue_style( 'wrp-guestbook' );
			wp_enqueue_script( 'wrp-guestbook' );
		}
	}

	private static function enqueue() {
		if ( ! wp_style_is( 'wrp-guestbook', 'registered' ) ) {
			self::register_assets();
		}
		wp_enqueue_style( 'wrp-guestbook' );
		wp_enqueue_script( 'wrp-guestbook' );
	}

	public static function card_html( $w, $tag = 'article' ) {
		$w = is_array( $w ) ? $w : self::format( $w );
		return sprintf(
			'<%1$s class="wrp-wish"><span class="wrp-wish__quote" aria-hidden="true">“</span><p class="wrp-wish__msg">%2$s</p><footer><strong class="wrp-wish__name">%3$s</strong>%4$s<time>%5$s</time></footer></%1$s>',
			tag_escape( $tag ),
			nl2br( esc_html( $w['message'] ) ),
			esc_html( $w['name'] ),
			$w['relation'] ? '<span class="wrp-wish__rel">' . esc_html( $w['relation'] ) . '</span>' : '',
			esc_html( $w['date'] )
		);
	}

	public static function shortcode_guestbook( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'title' => __( 'Laissez-nous un petit mot', 'wedding-rsvp-pass' ),
				'per_page' => 12,
			),
			$atts
		);
		self::enqueue();
		$res     = self::query( 'approved', (int) $atts['per_page'] );
		$names   = WRP_Settings::couple_names();
		$options = array(
			__( 'Famille', 'wedding-rsvp-pass' ),
			__( 'Ami·e', 'wedding-rsvp-pass' ),
			sprintf( __( 'Proche de %s', 'wedding-rsvp-pass' ), WRP_Settings::get( 'partner_a_name' ) ),
			sprintf( __( 'Proche de %s', 'wedding-rsvp-pass' ), WRP_Settings::get( 'partner_b_name' ) ),
			__( 'Collègue', 'wedding-rsvp-pass' ),
		);
		ob_start();
		?>
		<div class="wrp-gb" data-wrp-gb data-total="<?php echo (int) $res['total']; ?>" data-per-page="<?php echo (int) $atts['per_page']; ?>">
			<form class="wrp-gb__form" data-wrp-gb-form novalidate>
				<span class="wrp-gb__seal" aria-hidden="true">✒</span>
				<h2 class="wrp-gb__title"><?php echo esc_html( $atts['title'] ); ?></h2>
				<p class="wrp-gb__intro"><?php echo esc_html( sprintf( __( 'Vos vœux, un souvenir, un conseil pour la vie à deux… %s les garderont précieusement.', 'wedding-rsvp-pass' ), $names ) ); ?></p>
				<div class="wrp-gb__row">
					<label><span><?php esc_html_e( 'Votre prénom et nom', 'wedding-rsvp-pass' ); ?> *</span><input type="text" name="name" maxlength="120" required autocomplete="name"></label>
					<label><span><?php esc_html_e( 'Vous êtes…', 'wedding-rsvp-pass' ); ?></span><input type="text" name="relation" maxlength="120" list="wrp-gb-rel" placeholder="<?php esc_attr_e( 'Famille, ami·e…', 'wedding-rsvp-pass' ); ?>"></label>
					<datalist id="wrp-gb-rel"><?php foreach ( $options as $o ) : ?><option value="<?php echo esc_attr( $o ); ?>"><?php endforeach; ?></datalist>
				</div>
				<label><span><?php esc_html_e( 'Votre message', 'wedding-rsvp-pass' ); ?> *</span><textarea name="message" rows="5" maxlength="1500" required placeholder="<?php esc_attr_e( 'Écrivez avec le cœur…', 'wedding-rsvp-pass' ); ?>"></textarea></label>
				<input type="text" name="website" tabindex="-1" autocomplete="off" class="wrp-gb__hp" aria-hidden="true">
				<div class="wrp-gb__foot">
					<small data-wrp-gb-count>1500</small>
					<button type="submit"><?php esc_html_e( 'Signer le livre d’or', 'wedding-rsvp-pass' ); ?></button>
				</div>
				<p class="wrp-gb__msg" data-wrp-gb-msg role="status" hidden></p>
			</form>

			<div class="wrp-gb__wall" data-wrp-gb-wall>
				<?php
				foreach ( $res['items'] as $w ) {
					echo self::card_html( $w ); // phpcs:ignore
				}
				?>
			</div>
			<p class="wrp-gb__empty" data-wrp-gb-empty <?php echo $res['items'] ? 'hidden' : ''; ?>><?php esc_html_e( 'Soyez le premier ou la première à signer le livre d’or ♥', 'wedding-rsvp-pass' ); ?></p>
			<?php if ( $res['total'] > count( $res['items'] ) ) : ?>
				<p class="wrp-gb__more"><button type="button" data-wrp-gb-more><?php esc_html_e( 'Voir plus de vœux', 'wedding-rsvp-pass' ); ?></button></p>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Latest wishes carousel.
	 *
	 * @param array $atts count, title, kicker, link (1/0), dark (1/0).
	 */
	public static function shortcode_carousel( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'count'  => 12,
				'title'  => __( 'Vos plus beaux vœux', 'wedding-rsvp-pass' ),
				'kicker' => __( 'Livre d’or', 'wedding-rsvp-pass' ),
				'link'   => 1,
				'empty'  => 1,
			),
			$atts
		);
		$res  = self::query( 'approved', (int) $atts['count'] );
		$link = $atts['link'] ? self::page_url() : '';
		if ( ! $res['items'] && ! $atts['empty'] ) {
			return '';
		}
		self::enqueue();
		ob_start();
		?>
		<section class="wrp-carousel" data-wrp-carousel aria-roledescription="carousel" aria-label="<?php echo esc_attr( $atts['title'] ); ?>">
			<div class="wrp-carousel__head">
				<?php if ( $atts['kicker'] ) : ?><p class="wrp-carousel__kicker"><?php echo esc_html( $atts['kicker'] ); ?></p><?php endif; ?>
				<h2 class="wrp-carousel__title"><?php echo esc_html( $atts['title'] ); ?></h2>
			</div>
			<?php if ( $res['items'] ) : ?>
				<div class="wrp-carousel__viewport">
					<button type="button" class="wrp-carousel__nav wrp-carousel__prev" data-prev aria-label="<?php esc_attr_e( 'Vœu précédent', 'wedding-rsvp-pass' ); ?>">‹</button>
					<div class="wrp-carousel__track" data-track tabindex="0">
						<?php
						foreach ( $res['items'] as $w ) {
							echo self::card_html( $w, 'div' ); // phpcs:ignore
						}
						?>
					</div>
					<button type="button" class="wrp-carousel__nav wrp-carousel__next" data-next aria-label="<?php esc_attr_e( 'Vœu suivant', 'wedding-rsvp-pass' ); ?>">›</button>
				</div>
				<div class="wrp-carousel__dots" data-dots></div>
			<?php else : ?>
				<p class="wrp-carousel__empty"><?php esc_html_e( 'Le livre d’or attend vos premiers mots ♥', 'wedding-rsvp-pass' ); ?></p>
			<?php endif; ?>
			<?php if ( $link ) : ?>
				<p class="wrp-carousel__cta"><a href="<?php echo esc_url( $link ); ?>"><?php esc_html_e( 'Écrire dans le livre d’or', 'wedding-rsvp-pass' ); ?></a></p>
			<?php endif; ?>
		</section>
		<?php
		return ob_get_clean();
	}
}

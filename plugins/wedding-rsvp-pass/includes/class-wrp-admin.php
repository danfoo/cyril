<?php
/**
 * Admin screens: guest lists, messages & settings, wedding-day scanner.
 *
 * @package WeddingRSVPPass
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WRP_Admin {

	const PAGE_GUESTS   = 'wrp-guests';
	const PAGE_SCANNER  = 'wrp-dday';
	const PAGE_SETTINGS = 'wrp-settings';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );

		foreach ( array( 'save_guest', 'delete_guest', 'bulk', 'send_one', 'import', 'export', 'save_settings', 'regen_key', 'reset_checkin' ) as $action ) {
			add_action( 'admin_post_wrp_' . $action, array( __CLASS__, 'handle_' . $action ) );
		}
		add_action( 'wp_ajax_wrp_mark_invited', array( __CLASS__, 'ajax_mark_invited' ) );
		add_action( 'wp_ajax_wrp_live_stats', array( __CLASS__, 'ajax_live_stats' ) );
	}

	public static function menu() {
		add_menu_page(
			__( 'Invités du mariage', 'wedding-rsvp-pass' ),
			__( 'Invités', 'wedding-rsvp-pass' ),
			'wrp_manage_guests',
			self::PAGE_GUESTS,
			array( __CLASS__, 'page_guests' ),
			'dashicons-heart',
			3
		);
		add_submenu_page( self::PAGE_GUESTS, __( 'Liste des invités', 'wedding-rsvp-pass' ), __( 'Liste des invités', 'wedding-rsvp-pass' ), 'wrp_manage_guests', self::PAGE_GUESTS, array( __CLASS__, 'page_guests' ) );
		add_submenu_page( self::PAGE_GUESTS, __( 'Jour J – Scanner', 'wedding-rsvp-pass' ), __( 'Jour J – Scanner', 'wedding-rsvp-pass' ), 'wrp_manage_guests', self::PAGE_SCANNER, array( __CLASS__, 'page_scanner' ) );
		add_submenu_page( self::PAGE_GUESTS, __( 'Messages & réglages', 'wedding-rsvp-pass' ), __( 'Messages & réglages', 'wedding-rsvp-pass' ), 'wrp_manage_guests', self::PAGE_SETTINGS, array( __CLASS__, 'page_settings' ) );
	}

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'wrp-' ) ) {
			return;
		}
		wp_enqueue_style( 'wrp-admin-fonts', WRP_Public::fonts_url(), array(), null );
		wp_enqueue_style( 'wrp-admin', WRP_Public::asset( 'css/admin.css' ), array(), null );
		wp_add_inline_style( 'wrp-admin', WRP_Public::css_vars() );
		wp_enqueue_script( 'wrp-qrcode', WRP_Public::asset( 'vendor/qrcode.js' ), array(), null, true );
		wp_enqueue_script( 'wrp-admin', WRP_Public::asset( 'js/admin.js' ), array( 'wrp-qrcode' ), null, true );
		wp_localize_script(
			'wrp-admin',
			'WRP_ADMIN',
			array(
				'ajax'      => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'wrp_ajax' ),
				'copied'    => __( 'Lien copié !', 'wedding-rsvp-pass' ),
				'confirmDel' => __( 'Supprimer définitivement cet invité ?', 'wedding-rsvp-pass' ),
				'confirmBulkDel' => __( 'Supprimer les invités sélectionnés ?', 'wedding-rsvp-pass' ),
				'confirmRegen' => __( 'Générer une nouvelle clé ? Les téléphones déjà configurés devront être reconfigurés.', 'wedding-rsvp-pass' ),
				'timestamp' => WRP_Settings::wedding_timestamp(),
			)
		);
	}

	/* ------------------------------------------------------------------
	 * Helpers
	 * ---------------------------------------------------------------- */

	private static function page_url( $page = self::PAGE_GUESTS, array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
	}

	private static function redirect( $page, array $args = array() ) {
		wp_safe_redirect( self::page_url( $page, $args ) );
		exit;
	}

	private static function require_cap( $cap = 'wrp_manage_guests' ) {
		if ( ! current_user_can( $cap ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'wedding-rsvp-pass' ), 403 );
		}
	}

	/**
	 * Owner filter for the current user: null = everybody (admin "all" tab).
	 */
	private static function current_owner_scope() {
		if ( ! WRP_Roles::can_manage_all() ) {
			return get_current_user_id();
		}
		$list = isset( $_GET['list'] ) ? sanitize_key( $_GET['list'] ) : 'all'; // phpcs:ignore
		if ( 'a' === $list && WRP_Settings::get( 'partner_a_user' ) ) {
			return (int) WRP_Settings::get( 'partner_a_user' );
		}
		if ( 'b' === $list && WRP_Settings::get( 'partner_b_user' ) ) {
			return (int) WRP_Settings::get( 'partner_b_user' );
		}
		if ( 'mine' === $list ) {
			return get_current_user_id();
		}
		return null;
	}

	/**
	 * Users a guest can be assigned to (for admins / planners).
	 */
	private static function owner_choices() {
		$choices = array();
		foreach ( array( 'a', 'b' ) as $side ) {
			$uid = (int) WRP_Settings::get( 'partner_' . $side . '_user' );
			if ( $uid ) {
				/* translators: %s spouse name */
				$choices[ $uid ] = sprintf( __( 'Liste de %s', 'wedding-rsvp-pass' ), WRP_Settings::get( 'partner_' . $side . '_name' ) );
			}
		}
		if ( ! isset( $choices[ get_current_user_id() ] ) ) {
			$choices[ get_current_user_id() ] = sprintf( __( 'Ma liste (%s)', 'wedding-rsvp-pass' ), wp_get_current_user()->display_name );
		}
		return $choices;
	}

	private static function notice() {
		$msg = isset( $_GET['wrp_msg'] ) ? sanitize_key( $_GET['wrp_msg'] ) : ''; // phpcs:ignore
		$n   = isset( $_GET['n'] ) ? (int) $_GET['n'] : 0; // phpcs:ignore
		$map = array(
			'saved'      => array( 'success', __( 'Invité enregistré.', 'wedding-rsvp-pass' ) ),
			'deleted'    => array( 'success', sprintf( _n( '%d invité supprimé.', '%d invités supprimés.', max( 1, $n ), 'wedding-rsvp-pass' ), max( 1, $n ) ) ),
			'sent'       => array( 'success', sprintf( _n( '%d invitation envoyée par e-mail.', '%d invitations envoyées par e-mail.', $n, 'wedding-rsvp-pass' ), $n ) ),
			'sent_none'  => array( 'warning', __( 'Aucun e-mail envoyé (adresse manquante ou erreur d’envoi).', 'wedding-rsvp-pass' ) ),
			'marked'     => array( 'success', sprintf( __( '%d invité(s) marqué(s) comme invité(s).', 'wedding-rsvp-pass' ), $n ) ),
			'imported'   => array( 'success', sprintf( __( '%d invité(s) importé(s).', 'wedding-rsvp-pass' ), $n ) ),
			'settings'   => array( 'success', __( 'Réglages enregistrés.', 'wedding-rsvp-pass' ) ),
			'regen'      => array( 'success', __( 'Nouvelle clé de scanner générée.', 'wedding-rsvp-pass' ) ),
			'reset'      => array( 'success', __( 'Le passage de cet invité a été annulé.', 'wedding-rsvp-pass' ) ),
			'error'      => array( 'error', isset( $_GET['err'] ) ? sanitize_text_field( wp_unslash( $_GET['err'] ) ) : __( 'Une erreur est survenue.', 'wedding-rsvp-pass' ) ), // phpcs:ignore
		);
		if ( isset( $map[ $msg ] ) ) {
			printf( '<div class="notice notice-%1$s is-dismissible wrp-notice"><p>%2$s</p></div>', esc_attr( $map[ $msg ][0] ), esc_html( $map[ $msg ][1] ) );
		}
	}

	private static function header( $title, $subtitle = '' ) {
		$days = (int) ceil( ( WRP_Settings::wedding_timestamp() - time() ) / DAY_IN_SECONDS );
		?>
		<div class="wrp-hero">
			<div class="wrp-hero__petals" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span><span></span></div>
			<div class="wrp-hero__inner">
				<p class="wrp-hero__kicker"><?php echo esc_html( $title ); ?></p>
				<h1 class="wrp-hero__names"><?php echo esc_html( WRP_Settings::couple_names() ); ?></h1>
				<p class="wrp-hero__meta">
					<?php echo esc_html( WRP_Settings::wedding_date_display() ); ?> · <?php echo esc_html( WRP_Settings::get( 'venue_name' ) ); ?>
					<?php if ( $days > 0 ) : ?>
						<span class="wrp-pill">J-<?php echo (int) $days; ?></span>
					<?php elseif ( 0 === $days ) : ?>
						<span class="wrp-pill wrp-pill--gold"><?php esc_html_e( 'C’est aujourd’hui ! ♥', 'wedding-rsvp-pass' ); ?></span>
					<?php endif; ?>
				</p>
				<?php if ( $subtitle ) : ?>
					<p class="wrp-hero__sub"><?php echo esc_html( $subtitle ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	private static function stats_cards( array $s ) {
		$cards = array(
			array( 'heart', $s['invitations'], __( 'Invitations', 'wedding-rsvp-pass' ), sprintf( __( '%d places proposées', 'wedding-rsvp-pass' ), $s['seats_invited'] ) ),
			array( 'yes-alt', $s['seats_confirmed'], __( 'Personnes présentes', 'wedding-rsvp-pass' ), sprintf( __( '%d réponses positives', 'wedding-rsvp-pass' ), $s['confirmed'] ) ),
			array( 'clock', $s['invited'] + $s['pending'], __( 'En attente', 'wedding-rsvp-pass' ), sprintf( __( '%d pas encore invités', 'wedding-rsvp-pass' ), $s['pending'] ) ),
			array( 'dismiss', $s['declined'], __( 'Absents', 'wedding-rsvp-pass' ), __( 'ont décliné', 'wedding-rsvp-pass' ) ),
			array( 'tickets-alt', $s['seats_checked_in'], __( 'Arrivés le jour J', 'wedding-rsvp-pass' ), sprintf( __( '%d cartons scannés', 'wedding-rsvp-pass' ), $s['checked_in'] ) ),
		);
		$rate = $s['invitations'] ? round( ( $s['confirmed'] + $s['declined'] ) / $s['invitations'] * 100 ) : 0;
		echo '<div class="wrp-stats">';
		foreach ( $cards as $c ) {
			printf(
				'<div class="wrp-stat"><span class="dashicons dashicons-%1$s"></span><strong data-stat>%2$d</strong><span class="wrp-stat__label">%3$s</span><small>%4$s</small></div>',
				esc_attr( $c[0] ),
				(int) $c[1],
				esc_html( $c[2] ),
				esc_html( $c[3] )
			);
		}
		echo '</div>';
		printf(
			'<div class="wrp-progress" title="%1$s"><span style="width:%2$d%%"></span><em>%3$s</em></div>',
			esc_attr__( 'Taux de réponse', 'wedding-rsvp-pass' ),
			(int) $rate,
			esc_html( sprintf( __( '%d%% des invités ont répondu', 'wedding-rsvp-pass' ), $rate ) )
		);
	}

	/* ------------------------------------------------------------------
	 * Guests page
	 * ---------------------------------------------------------------- */

	public static function page_guests() {
		self::require_cap();
		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : ''; // phpcs:ignore

		echo '<div class="wrap wrp-wrap">';
		echo '<h1 class="screen-reader-text">' . esc_html__( 'Invités', 'wedding-rsvp-pass' ) . '</h1>';

		if ( 'edit' === $action || 'new' === $action ) {
			self::guest_form( 'edit' === $action && isset( $_GET['id'] ) ? (int) $_GET['id'] : 0 ); // phpcs:ignore
		} elseif ( 'import' === $action ) {
			self::import_form();
		} else {
			self::guest_list();
		}
		echo '</div>';
	}

	private static function guest_list() {
		$scope    = self::current_owner_scope();
		$is_all   = WRP_Roles::can_manage_all();
		$list     = isset( $_GET['list'] ) ? sanitize_key( $_GET['list'] ) : 'all'; // phpcs:ignore
		$status   = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : ''; // phpcs:ignore
		$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore
		$group    = isset( $_GET['group'] ) ? sanitize_text_field( wp_unslash( $_GET['group'] ) ) : ''; // phpcs:ignore
		$orderby  = isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'last_name'; // phpcs:ignore
		$order    = isset( $_GET['order'] ) ? sanitize_key( $_GET['order'] ) : 'asc'; // phpcs:ignore
		$paged    = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore
		$per_page = 50;

		$res   = WRP_Guests::query(
			array(
				'owner_id' => $scope,
				'status'   => $status,
				'search'   => $search,
				'group'    => $group,
				'orderby'  => $orderby,
				'order'    => $order,
				'per_page' => $per_page,
				'page'     => $paged,
			)
		);
		$stats = WRP_Guests::stats( $scope );

		$side      = WRP_Settings::side_of_user( get_current_user_id() );
		$list_name = '';
		if ( null === $scope ) {
			$list_name = __( 'Toutes les listes', 'wedding-rsvp-pass' );
		} else {
			/* translators: %s name */
			$list_name = sprintf( __( 'Liste de %s', 'wedding-rsvp-pass' ), WRP_Settings::owner_label( $scope ) );
		}

		self::header( __( 'Nos invités', 'wedding-rsvp-pass' ), $list_name );
		self::notice();

		if ( ! $is_all && ! $side ) {
			echo '<div class="notice notice-info"><p>' . esc_html__( 'Astuce : dans « Messages & réglages », un administrateur peut associer votre compte au prénom du/de la marié·e.', 'wedding-rsvp-pass' ) . '</p></div>';
		}

		self::stats_cards( $stats );

		// Tabs (admin / planner only).
		if ( $is_all ) {
			$tabs = array( 'all' => __( 'Tous les invités', 'wedding-rsvp-pass' ) );
			foreach ( array( 'a', 'b' ) as $sd ) {
				if ( WRP_Settings::get( 'partner_' . $sd . '_user' ) ) {
					$tabs[ $sd ] = sprintf( __( 'Liste de %s', 'wedding-rsvp-pass' ), WRP_Settings::get( 'partner_' . $sd . '_name' ) );
				}
			}
			if ( ! $side ) {
				$tabs['mine'] = __( 'Ma liste', 'wedding-rsvp-pass' );
			}
			echo '<nav class="wrp-tabs">';
			foreach ( $tabs as $key => $label ) {
				printf( '<a class="%1$s" href="%2$s">%3$s</a>', $key === $list ? 'is-active' : '', esc_url( self::page_url( self::PAGE_GUESTS, array( 'list' => $key ) ) ), esc_html( $label ) );
			}
			echo '</nav>';
		}

		$base_args = array_filter(
			array(
				'list'   => $is_all ? $list : '',
				'status' => $status,
				's'      => $search,
				'group'  => $group,
			)
		);
		?>
		<div class="wrp-toolbar">
			<form method="get" class="wrp-filters">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_GUESTS ); ?>">
				<?php if ( $is_all ) : ?>
					<input type="hidden" name="list" value="<?php echo esc_attr( $list ); ?>">
				<?php endif; ?>
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Rechercher un nom, un e-mail, une table…', 'wedding-rsvp-pass' ); ?>">
				<select name="status">
					<option value=""><?php esc_html_e( 'Tous les statuts', 'wedding-rsvp-pass' ); ?></option>
					<?php foreach ( array_merge( WRP_Guests::STATUSES, array( 'checked_in' ) ) as $st ) : ?>
						<option value="<?php echo esc_attr( $st ); ?>" <?php selected( $status, $st ); ?>><?php echo esc_html( 'checked_in' === $st ? __( 'Arrivé·e le jour J', 'wedding-rsvp-pass' ) : WRP_Guests::status_label( $st ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php $groups = WRP_Guests::groups( $scope ); ?>
				<?php if ( $groups ) : ?>
					<select name="group">
						<option value=""><?php esc_html_e( 'Tous les groupes', 'wedding-rsvp-pass' ); ?></option>
						<?php foreach ( $groups as $g ) : ?>
							<option value="<?php echo esc_attr( $g ); ?>" <?php selected( $group, $g ); ?>><?php echo esc_html( $g ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>
				<button class="button"><?php esc_html_e( 'Filtrer', 'wedding-rsvp-pass' ); ?></button>
			</form>
			<div class="wrp-toolbar__actions">
				<a class="button button-primary wrp-btn" href="<?php echo esc_url( self::page_url( self::PAGE_GUESTS, array( 'action' => 'new' ) ) ); ?>"><span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Ajouter un invité', 'wedding-rsvp-pass' ); ?></a>
				<a class="button wrp-btn-ghost" href="<?php echo esc_url( self::page_url( self::PAGE_GUESTS, array( 'action' => 'import' ) ) ); ?>"><span class="dashicons dashicons-upload"></span> <?php esc_html_e( 'Importer', 'wedding-rsvp-pass' ); ?></a>
				<a class="button wrp-btn-ghost" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array_merge( array( 'action' => 'wrp_export' ), $base_args ), admin_url( 'admin-post.php' ) ), 'wrp_export' ) ); ?>"><span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Exporter', 'wedding-rsvp-pass' ); ?></a>
			</div>
		</div>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="wrp-bulk-form">
			<input type="hidden" name="action" value="wrp_bulk">
			<input type="hidden" name="return" value="<?php echo esc_attr( wp_json_encode( $base_args ) ); ?>">
			<?php wp_nonce_field( 'wrp_bulk' ); ?>

			<div class="wrp-bulkbar">
				<select name="bulk_action">
					<option value=""><?php esc_html_e( 'Actions groupées', 'wedding-rsvp-pass' ); ?></option>
					<option value="send"><?php esc_html_e( 'Envoyer l’invitation par e-mail', 'wedding-rsvp-pass' ); ?></option>
					<option value="resend_card"><?php esc_html_e( 'Renvoyer le carton (confirmés)', 'wedding-rsvp-pass' ); ?></option>
					<option value="mark_invited"><?php esc_html_e( 'Marquer comme invité', 'wedding-rsvp-pass' ); ?></option>
					<option value="delete"><?php esc_html_e( 'Supprimer', 'wedding-rsvp-pass' ); ?></option>
				</select>
				<button class="button" type="submit"><?php esc_html_e( 'Appliquer', 'wedding-rsvp-pass' ); ?></button>
				<span class="wrp-count"><?php echo esc_html( sprintf( _n( '%d invitation', '%d invitations', $res['total'], 'wedding-rsvp-pass' ), $res['total'] ) ); ?></span>
			</div>

			<div class="wrp-table-wrap">
			<table class="wrp-table">
				<thead>
					<tr>
						<th class="wrp-check"><input type="checkbox" id="wrp-check-all" aria-label="<?php esc_attr_e( 'Tout sélectionner', 'wedding-rsvp-pass' ); ?>"></th>
						<?php
						$cols = array(
							'last_name'   => __( 'Invité', 'wedding-rsvp-pass' ),
							'group_label' => __( 'Groupe', 'wedding-rsvp-pass' ),
							'seats_allowed' => __( 'Places', 'wedding-rsvp-pass' ),
							'status'      => __( 'Réponse', 'wedding-rsvp-pass' ),
							'table_name'  => __( 'Table', 'wedding-rsvp-pass' ),
						);
						foreach ( $cols as $key => $label ) {
							$new_order = ( $orderby === $key && 'asc' === $order ) ? 'desc' : 'asc';
							printf(
								'<th><a href="%1$s" class="%2$s">%3$s</a></th>',
								esc_url( self::page_url( self::PAGE_GUESTS, array_merge( $base_args, array( 'orderby' => $key, 'order' => $new_order ) ) ) ),
								$orderby === $key ? 'is-sorted is-' . esc_attr( $order ) : '',
								esc_html( $label )
							);
						}
						if ( null === $scope ) {
							echo '<th>' . esc_html__( 'Liste', 'wedding-rsvp-pass' ) . '</th>';
						}
						?>
						<th class="wrp-actions-col"><?php esc_html_e( 'Envoyer / Gérer', 'wedding-rsvp-pass' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( ! $res['items'] ) : ?>
					<tr><td colspan="8" class="wrp-empty">
						<div class="wrp-empty__icon">💌</div>
						<p><?php esc_html_e( 'Aucun invité pour le moment.', 'wedding-rsvp-pass' ); ?></p>
						<a class="button button-primary wrp-btn" href="<?php echo esc_url( self::page_url( self::PAGE_GUESTS, array( 'action' => 'new' ) ) ); ?>"><?php esc_html_e( 'Ajouter le premier invité', 'wedding-rsvp-pass' ); ?></a>
					</td></tr>
				<?php endif; ?>
				<?php foreach ( $res['items'] as $g ) : ?>
					<?php self::guest_row( $g, null === $scope ); ?>
				<?php endforeach; ?>
				</tbody>
			</table>
			</div>
		</form>
		<?php
		$pages = (int) ceil( $res['total'] / $per_page );
		if ( $pages > 1 ) {
			echo '<div class="wrp-pagination">';
			echo paginate_links( // phpcs:ignore
				array(
					'base'    => add_query_arg( 'paged', '%#%' ),
					'format'  => '',
					'current' => $paged,
					'total'   => $pages,
				)
			);
			echo '</div>';
		}
	}

	private static function guest_row( $g, $show_owner ) {
		$companions = WRP_Guests::companions( $g );
		$rsvp       = WRP_Guests::rsvp_url( $g );
		?>
		<tr class="wrp-row wrp-row--<?php echo esc_attr( $g->status ); ?>">
			<td class="wrp-check"><input type="checkbox" name="ids[]" value="<?php echo (int) $g->id; ?>"></td>
			<td class="wrp-guest">
				<a class="wrp-guest__name" href="<?php echo esc_url( self::page_url( self::PAGE_GUESTS, array( 'action' => 'edit', 'id' => $g->id ) ) ); ?>"><?php echo esc_html( WRP_Guests::full_name( $g ) ); ?></a>
				<?php if ( $companions ) : ?>
					<span class="wrp-guest__comp">+ <?php echo esc_html( implode( ', ', $companions ) ); ?></span>
				<?php endif; ?>
				<span class="wrp-guest__contact">
					<?php echo esc_html( implode( ' · ', array_filter( array( $g->email, $g->phone ) ) ) ); ?>
				</span>
				<?php if ( $g->guest_message ) : ?>
					<span class="wrp-guest__msg" title="<?php echo esc_attr( $g->guest_message ); ?>">💬 <?php echo esc_html( wp_trim_words( $g->guest_message, 12 ) ); ?></span>
				<?php endif; ?>
			</td>
			<td><?php echo $g->group_label ? '<span class="wrp-tag">' . esc_html( $g->group_label ) . '</span>' : '—'; ?></td>
			<td class="wrp-seats">
				<?php if ( 'confirmed' === $g->status ) : ?>
					<strong><?php echo (int) $g->seats_confirmed; ?></strong>/<?php echo (int) $g->seats_allowed; ?>
				<?php else : ?>
					<?php echo (int) $g->seats_allowed; ?>
				<?php endif; ?>
			</td>
			<td>
				<span class="wrp-badge wrp-badge--<?php echo esc_attr( $g->status ); ?>"><?php echo esc_html( WRP_Guests::status_label( $g->status ) ); ?></span>
				<?php if ( $g->checked_in_at ) : ?>
					<span class="wrp-badge wrp-badge--in" title="<?php echo esc_attr( $g->checked_in_at ); ?>">✓ <?php echo esc_html( mysql2date( 'H:i', $g->checked_in_at ) ); ?></span>
				<?php endif; ?>
				<?php if ( $g->dietary ) : ?>
					<span class="wrp-guest__diet" title="<?php esc_attr_e( 'Régime / allergies', 'wedding-rsvp-pass' ); ?>">🍽 <?php echo esc_html( $g->dietary ); ?></span>
				<?php endif; ?>
			</td>
			<td><?php echo $g->table_name ? esc_html( $g->table_name ) : '—'; ?></td>
			<?php if ( $show_owner ) : ?>
				<td><span class="wrp-owner"><?php echo esc_html( WRP_Settings::owner_label( $g->owner_id ) ); ?></span></td>
			<?php endif; ?>
			<td class="wrp-actions">
				<?php if ( is_email( $g->email ) ) : ?>
					<a class="wrp-icon" title="<?php esc_attr_e( 'Envoyer l’invitation par e-mail', 'wedding-rsvp-pass' ); ?>" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'wrp_send_one', 'id' => $g->id ), admin_url( 'admin-post.php' ) ), 'wrp_send_one_' . $g->id ) ); ?>"><span class="dashicons dashicons-email-alt"></span></a>
				<?php endif; ?>
				<?php if ( $g->phone ) : ?>
					<a class="wrp-icon wrp-icon--wa" data-wrp-mark="<?php echo (int) $g->id; ?>" target="_blank" rel="noopener" title="<?php esc_attr_e( 'Envoyer par WhatsApp', 'wedding-rsvp-pass' ); ?>" href="<?php echo esc_url( WRP_Guests::whatsapp_url( $g ) ); ?>"><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.2-.4.7-1.4.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c1.7.7 2.3.8 3.2.6.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.1-1.2l-.5-.2Z"/></svg></a>
					<a class="wrp-icon" data-wrp-mark="<?php echo (int) $g->id; ?>" title="<?php esc_attr_e( 'Envoyer par SMS', 'wedding-rsvp-pass' ); ?>" href="<?php echo esc_url( WRP_Guests::sms_url( $g ), array( 'sms' ) ); ?>"><span class="dashicons dashicons-smartphone"></span></a>
				<?php endif; ?>
				<button type="button" class="wrp-icon" data-wrp-copy="<?php echo esc_attr( $rsvp ); ?>" data-wrp-mark="<?php echo (int) $g->id; ?>" title="<?php esc_attr_e( 'Copier le lien personnel de réponse', 'wedding-rsvp-pass' ); ?>"><span class="dashicons dashicons-admin-links"></span></button>
				<?php if ( 'confirmed' === $g->status && $g->pass_code ) : ?>
					<a class="wrp-icon wrp-icon--gold" target="_blank" rel="noopener" title="<?php esc_attr_e( 'Voir le carton d’invitation', 'wedding-rsvp-pass' ); ?>" href="<?php echo esc_url( WRP_Guests::card_url( $g ) ); ?>"><span class="dashicons dashicons-tickets-alt"></span></a>
				<?php endif; ?>
				<a class="wrp-icon" title="<?php esc_attr_e( 'Modifier', 'wedding-rsvp-pass' ); ?>" href="<?php echo esc_url( self::page_url( self::PAGE_GUESTS, array( 'action' => 'edit', 'id' => $g->id ) ) ); ?>"><span class="dashicons dashicons-edit"></span></a>
				<a class="wrp-icon wrp-icon--danger" data-wrp-confirm="del" title="<?php esc_attr_e( 'Supprimer', 'wedding-rsvp-pass' ); ?>" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'wrp_delete_guest', 'id' => $g->id ), admin_url( 'admin-post.php' ) ), 'wrp_delete_' . $g->id ) ); ?>"><span class="dashicons dashicons-trash"></span></a>
			</td>
		</tr>
		<?php
	}

	private static function guest_form( $id ) {
		$g = $id ? WRP_Guests::get( $id ) : null;
		if ( $id && ! WRP_Roles::can_edit_guest( $g ) ) {
			wp_die( esc_html__( 'Cet invité n’appartient pas à votre liste.', 'wedding-rsvp-pass' ) );
		}
		$v = function ( $field, $default = '' ) use ( $g ) {
			return $g && isset( $g->$field ) ? $g->$field : $default;
		};

		self::header( $g ? __( 'Modifier un invité', 'wedding-rsvp-pass' ) : __( 'Nouvel invité', 'wedding-rsvp-pass' ) );
		self::notice();
		?>
		<p><a class="wrp-back" href="<?php echo esc_url( self::page_url() ); ?>">← <?php esc_html_e( 'Retour à la liste', 'wedding-rsvp-pass' ); ?></a></p>
		<div class="wrp-columns">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wrp-card wrp-form">
			<input type="hidden" name="action" value="wrp_save_guest">
			<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
			<?php wp_nonce_field( 'wrp_save_guest' ); ?>

			<div class="wrp-grid">
				<label><span><?php esc_html_e( 'Prénom', 'wedding-rsvp-pass' ); ?> *</span><input type="text" name="first_name" required value="<?php echo esc_attr( $v( 'first_name' ) ); ?>"></label>
				<label><span><?php esc_html_e( 'Nom', 'wedding-rsvp-pass' ); ?></span><input type="text" name="last_name" value="<?php echo esc_attr( $v( 'last_name' ) ); ?>"></label>
				<label><span><?php esc_html_e( 'E-mail', 'wedding-rsvp-pass' ); ?></span><input type="email" name="email" value="<?php echo esc_attr( $v( 'email' ) ); ?>"></label>
				<label><span><?php esc_html_e( 'Téléphone (WhatsApp / SMS)', 'wedding-rsvp-pass' ); ?></span><input type="tel" name="phone" value="<?php echo esc_attr( $v( 'phone' ) ); ?>" placeholder="+33 6 12 34 56 78"></label>
				<label><span><?php esc_html_e( 'Nombre de places (invité inclus)', 'wedding-rsvp-pass' ); ?></span><input type="number" min="1" max="20" name="seats_allowed" value="<?php echo (int) $v( 'seats_allowed', 1 ); ?>"></label>
				<label><span><?php esc_html_e( 'Groupe (famille, amis, collègues…)', 'wedding-rsvp-pass' ); ?></span><input type="text" name="group_label" list="wrp-groups" value="<?php echo esc_attr( $v( 'group_label' ) ); ?>"></label>
				<datalist id="wrp-groups"><?php foreach ( WRP_Guests::groups() as $grp ) : ?><option value="<?php echo esc_attr( $grp ); ?>"><?php endforeach; ?></datalist>
				<label><span><?php esc_html_e( 'Table', 'wedding-rsvp-pass' ); ?></span><input type="text" name="table_name" value="<?php echo esc_attr( $v( 'table_name' ) ); ?>" placeholder="<?php esc_attr_e( 'ex : Table des Roses', 'wedding-rsvp-pass' ); ?>"></label>
				<?php if ( WRP_Roles::can_manage_all() ) : ?>
					<label><span><?php esc_html_e( 'Liste', 'wedding-rsvp-pass' ); ?></span>
						<select name="owner_id">
							<?php
							$choices = self::owner_choices();
							$current = (int) $v( 'owner_id', get_current_user_id() );
							if ( $current && ! isset( $choices[ $current ] ) ) {
								$choices[ $current ] = WRP_Settings::owner_label( $current );
							}
							foreach ( $choices as $uid => $label ) {
								printf( '<option value="%1$d" %2$s>%3$s</option>', (int) $uid, selected( $current, $uid, false ), esc_html( $label ) );
							}
							?>
						</select>
					</label>
				<?php endif; ?>
				<?php if ( $g ) : ?>
					<label><span><?php esc_html_e( 'Réponse', 'wedding-rsvp-pass' ); ?></span>
						<select name="status">
							<?php foreach ( WRP_Guests::STATUSES as $st ) : ?>
								<option value="<?php echo esc_attr( $st ); ?>" <?php selected( $g->status, $st ); ?>><?php echo esc_html( WRP_Guests::status_label( $st ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label><span><?php esc_html_e( 'Personnes confirmées', 'wedding-rsvp-pass' ); ?></span><input type="number" min="0" max="20" name="seats_confirmed" value="<?php echo (int) $g->seats_confirmed; ?>"></label>
				<?php endif; ?>
				<label class="wrp-grid__full"><span><?php esc_html_e( 'Régime alimentaire / allergies', 'wedding-rsvp-pass' ); ?></span><input type="text" name="dietary" value="<?php echo esc_attr( $v( 'dietary' ) ); ?>"></label>
				<label class="wrp-grid__full"><span><?php esc_html_e( 'Notes privées', 'wedding-rsvp-pass' ); ?></span><textarea name="notes" rows="3"><?php echo esc_textarea( $v( 'notes' ) ); ?></textarea></label>
			</div>
			<p class="wrp-form__submit">
				<button class="button button-primary wrp-btn" type="submit" name="then" value="list"><?php esc_html_e( 'Enregistrer', 'wedding-rsvp-pass' ); ?></button>
				<?php if ( ! $g ) : ?>
					<button class="button wrp-btn-ghost" type="submit" name="then" value="new"><?php esc_html_e( 'Enregistrer et ajouter un autre', 'wedding-rsvp-pass' ); ?></button>
				<?php endif; ?>
			</p>
		</form>

		<?php if ( $g ) : ?>
			<aside class="wrp-card wrp-side">
				<h3><?php esc_html_e( 'Invitation', 'wedding-rsvp-pass' ); ?></h3>
				<p class="wrp-muted"><?php esc_html_e( 'Lien personnel de réponse :', 'wedding-rsvp-pass' ); ?></p>
				<div class="wrp-linkbox"><input type="text" readonly value="<?php echo esc_attr( WRP_Guests::rsvp_url( $g ) ); ?>" onclick="this.select()"><button type="button" class="button" data-wrp-copy="<?php echo esc_attr( WRP_Guests::rsvp_url( $g ) ); ?>"><?php esc_html_e( 'Copier', 'wedding-rsvp-pass' ); ?></button></div>
				<div class="wrp-side__buttons">
					<?php if ( is_email( $g->email ) ) : ?>
						<a class="button wrp-btn-ghost" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'wrp_send_one', 'id' => $g->id, 'back' => 'edit' ), admin_url( 'admin-post.php' ) ), 'wrp_send_one_' . $g->id ) ); ?>">✉ <?php esc_html_e( 'E-mail', 'wedding-rsvp-pass' ); ?></a>
					<?php endif; ?>
					<?php if ( $g->phone ) : ?>
						<a class="button wrp-btn-ghost" target="_blank" rel="noopener" data-wrp-mark="<?php echo (int) $g->id; ?>" href="<?php echo esc_url( WRP_Guests::whatsapp_url( $g ) ); ?>">WhatsApp</a>
						<a class="button wrp-btn-ghost" data-wrp-mark="<?php echo (int) $g->id; ?>" href="<?php echo esc_url( WRP_Guests::sms_url( $g ), array( 'sms' ) ); ?>">SMS</a>
					<?php endif; ?>
				</div>
				<?php if ( $g->invited_at ) : ?>
					<p class="wrp-muted"><?php echo esc_html( sprintf( __( 'Invitation envoyée / ouverte le %s', 'wedding-rsvp-pass' ), mysql2date( 'j F Y à H:i', $g->invited_at ) ) ); ?></p>
				<?php endif; ?>
				<?php if ( $g->responded_at ) : ?>
					<p class="wrp-muted"><?php echo esc_html( sprintf( __( 'Réponse le %s', 'wedding-rsvp-pass' ), mysql2date( 'j F Y à H:i', $g->responded_at ) ) ); ?></p>
				<?php endif; ?>

				<?php if ( 'confirmed' === $g->status && $g->pass_code ) : ?>
					<hr>
					<h3><?php esc_html_e( 'Carton d’invitation', 'wedding-rsvp-pass' ); ?></h3>
					<div class="wrp-qr" data-wrp-qr="<?php echo esc_attr( WRP_Guests::QR_PREFIX . $g->pass_code ); ?>"></div>
					<p class="wrp-code"><?php echo esc_html( WRP_Guests::format_code( $g->pass_code ) ); ?></p>
					<p><a class="button button-primary wrp-btn" target="_blank" rel="noopener" href="<?php echo esc_url( WRP_Guests::card_url( $g ) ); ?>"><?php esc_html_e( 'Ouvrir le carton', 'wedding-rsvp-pass' ); ?></a></p>
					<?php if ( $g->checked_in_at ) : ?>
						<p class="wrp-ok">✓ <?php echo esc_html( sprintf( __( 'Arrivé·e le %s', 'wedding-rsvp-pass' ), mysql2date( 'j F à H:i', $g->checked_in_at ) ) ); ?></p>
						<a class="button" data-wrp-confirm="reset" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'wrp_reset_checkin', 'id' => $g->id ), admin_url( 'admin-post.php' ) ), 'wrp_reset_' . $g->id ) ); ?>"><?php esc_html_e( 'Annuler le passage', 'wedding-rsvp-pass' ); ?></a>
					<?php endif; ?>
				<?php endif; ?>
				<?php if ( $g->guest_message ) : ?>
					<hr>
					<h3><?php esc_html_e( 'Son petit mot', 'wedding-rsvp-pass' ); ?></h3>
					<blockquote class="wrp-quote"><?php echo nl2br( esc_html( $g->guest_message ) ); ?></blockquote>
				<?php endif; ?>
			</aside>
		<?php endif; ?>
		</div>
		<?php
	}

	private static function import_form() {
		self::header( __( 'Importer des invités', 'wedding-rsvp-pass' ) );
		self::notice();
		?>
		<p><a class="wrp-back" href="<?php echo esc_url( self::page_url() ); ?>">← <?php esc_html_e( 'Retour à la liste', 'wedding-rsvp-pass' ); ?></a></p>
		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wrp-card wrp-form" style="max-width:760px">
			<input type="hidden" name="action" value="wrp_import">
			<?php wp_nonce_field( 'wrp_import' ); ?>
			<p><?php esc_html_e( 'Importez un fichier CSV (export Excel / Google Sheets). Première ligne = en-têtes. Colonnes reconnues :', 'wedding-rsvp-pass' ); ?></p>
			<p><code>prenom ; nom ; email ; telephone ; places ; groupe ; table ; notes</code></p>
			<p class="wrp-muted"><?php esc_html_e( 'Séparateur « ; » ou « , » détecté automatiquement. Les invités sont ajoutés à la liste choisie ci-dessous.', 'wedding-rsvp-pass' ); ?></p>
			<div class="wrp-grid">
				<label class="wrp-grid__full"><span><?php esc_html_e( 'Fichier CSV', 'wedding-rsvp-pass' ); ?></span><input type="file" name="csv" accept=".csv,text/csv" required></label>
				<?php if ( WRP_Roles::can_manage_all() ) : ?>
					<label><span><?php esc_html_e( 'Ajouter à', 'wedding-rsvp-pass' ); ?></span>
						<select name="owner_id">
							<?php foreach ( self::owner_choices() as $uid => $label ) : ?>
								<option value="<?php echo (int) $uid; ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				<?php endif; ?>
			</div>
			<p class="wrp-form__submit"><button class="button button-primary wrp-btn"><?php esc_html_e( 'Importer', 'wedding-rsvp-pass' ); ?></button></p>
		</form>
		<?php
	}

	/* ------------------------------------------------------------------
	 * Handlers
	 * ---------------------------------------------------------------- */

	public static function handle_save_guest() {
		self::require_cap();
		check_admin_referer( 'wrp_save_guest' );
		$id   = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$data = wp_unslash( $_POST ); // phpcs:ignore -- sanitized in WRP_Guests::sanitize.

		if ( ! WRP_Roles::can_manage_all() ) {
			unset( $data['owner_id'] );
		} elseif ( isset( $data['owner_id'] ) && ! array_key_exists( (int) $data['owner_id'], self::owner_choices() ) ) {
			// Only allow keeping the existing owner or choosing one of the lists.
			$existing = $id ? WRP_Guests::get( $id ) : null;
			if ( ! $existing || (int) $existing->owner_id !== (int) $data['owner_id'] ) {
				unset( $data['owner_id'] );
			}
		}

		if ( $id ) {
			$g = WRP_Guests::get( $id );
			if ( ! WRP_Roles::can_edit_guest( $g ) ) {
				wp_die( esc_html__( 'Accès refusé.', 'wedding-rsvp-pass' ), 403 );
			}
			$row = WRP_Guests::sanitize( $data );
			if ( isset( $data['seats_confirmed'] ) ) {
				$row['seats_confirmed'] = max( 0, min( 20, (int) $data['seats_confirmed'] ) );
			}
			// Manual confirmation by the couple also creates the pass.
			if ( isset( $row['status'] ) && 'confirmed' === $row['status'] ) {
				if ( empty( $g->pass_code ) ) {
					$row['pass_code'] = WRP_Guests::generate_pass_code();
				}
				if ( empty( $row['seats_confirmed'] ) ) {
					$row['seats_confirmed'] = isset( $row['seats_allowed'] ) ? $row['seats_allowed'] : $g->seats_allowed;
				}
				if ( ! $g->responded_at ) {
					$row['responded_at'] = current_time( 'mysql' );
				}
			}
			WRP_Guests::update( $id, $row, true );
		} else {
			$id = WRP_Guests::create( $data );
			if ( is_wp_error( $id ) ) {
				self::redirect( self::PAGE_GUESTS, array( 'action' => 'new', 'wrp_msg' => 'error', 'err' => $id->get_error_message() ) );
			}
		}

		if ( isset( $_POST['then'] ) && 'new' === $_POST['then'] ) {
			self::redirect( self::PAGE_GUESTS, array( 'action' => 'new', 'wrp_msg' => 'saved' ) );
		}
		self::redirect( self::PAGE_GUESTS, array( 'wrp_msg' => 'saved' ) );
	}

	public static function handle_delete_guest() {
		self::require_cap();
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		check_admin_referer( 'wrp_delete_' . $id );
		$g = WRP_Guests::get( $id );
		if ( WRP_Roles::can_edit_guest( $g ) ) {
			WRP_Guests::delete( $id );
		}
		self::redirect( self::PAGE_GUESTS, array( 'wrp_msg' => 'deleted', 'n' => 1 ) );
	}

	public static function handle_send_one() {
		self::require_cap();
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		check_admin_referer( 'wrp_send_one_' . $id );
		$g = WRP_Guests::get( $id );
		if ( ! WRP_Roles::can_edit_guest( $g ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'wedding-rsvp-pass' ), 403 );
		}
		$ok   = WRP_Mailer::send_invitation( $g );
		$args = array( 'wrp_msg' => $ok ? 'sent' : 'sent_none', 'n' => $ok ? 1 : 0 );
		if ( isset( $_GET['back'] ) ) {
			$args['action'] = 'edit';
			$args['id']     = $id;
		}
		self::redirect( self::PAGE_GUESTS, $args );
	}

	public static function handle_bulk() {
		self::require_cap();
		check_admin_referer( 'wrp_bulk' );
		$ids    = isset( $_POST['ids'] ) ? array_map( 'intval', (array) $_POST['ids'] ) : array();
		$action = isset( $_POST['bulk_action'] ) ? sanitize_key( $_POST['bulk_action'] ) : '';
		$return = isset( $_POST['return'] ) ? json_decode( wp_unslash( $_POST['return'] ), true ) : array(); // phpcs:ignore
		$return = is_array( $return ) ? array_map( 'sanitize_text_field', $return ) : array();
		$n      = 0;
		$msg    = '';

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore
		}

		foreach ( $ids as $id ) {
			$g = WRP_Guests::get( $id );
			if ( ! WRP_Roles::can_edit_guest( $g ) ) {
				continue;
			}
			switch ( $action ) {
				case 'send':
					$n  += WRP_Mailer::send_invitation( $g ) ? 1 : 0;
					$msg = $n ? 'sent' : 'sent_none';
					break;
				case 'resend_card':
					if ( 'confirmed' === $g->status ) {
						$n += WRP_Mailer::send_card( $g ) ? 1 : 0;
					}
					$msg = $n ? 'sent' : 'sent_none';
					break;
				case 'mark_invited':
					WRP_Guests::mark_invited( $g );
					++$n;
					$msg = 'marked';
					break;
				case 'delete':
					WRP_Guests::delete( $id );
					++$n;
					$msg = 'deleted';
					break;
			}
		}
		self::redirect( self::PAGE_GUESTS, array_merge( $return, array( 'wrp_msg' => $msg ? $msg : 'error', 'n' => $n ) ) );
	}

	public static function handle_import() {
		self::require_cap();
		check_admin_referer( 'wrp_import' );
		if ( empty( $_FILES['csv']['tmp_name'] ) || ! is_uploaded_file( $_FILES['csv']['tmp_name'] ) ) { // phpcs:ignore
			self::redirect( self::PAGE_GUESTS, array( 'action' => 'import', 'wrp_msg' => 'error' ) );
		}
		$owner = get_current_user_id();
		if ( WRP_Roles::can_manage_all() && isset( $_POST['owner_id'] ) && array_key_exists( (int) $_POST['owner_id'], self::owner_choices() ) ) {
			$owner = (int) $_POST['owner_id'];
		}

		$content = file_get_contents( $_FILES['csv']['tmp_name'] ); // phpcs:ignore
		$content = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $content );
		if ( ! seems_utf8( $content ) ) {
			$content = mb_convert_encoding( $content, 'UTF-8', 'Windows-1252' );
		}
		$lines = preg_split( "/\r\n|\n|\r/", trim( $content ) );
		if ( count( $lines ) < 2 ) {
			self::redirect( self::PAGE_GUESTS, array( 'action' => 'import', 'wrp_msg' => 'error', 'err' => __( 'Fichier vide.', 'wedding-rsvp-pass' ) ) );
		}
		$delim   = substr_count( $lines[0], ';' ) >= substr_count( $lines[0], ',' ) ? ';' : ',';
		$headers = array_map(
			function ( $h ) {
				$h = strtolower( remove_accents( trim( $h ) ) );
				return preg_replace( '/[^a-z_]/', '', str_replace( array( ' ', '-' ), '_', $h ) );
			},
			str_getcsv( array_shift( $lines ), $delim )
		);
		$alias = array(
			'prenom'        => 'first_name',
			'first_name'    => 'first_name',
			'firstname'     => 'first_name',
			'nom'           => 'last_name',
			'last_name'     => 'last_name',
			'lastname'      => 'last_name',
			'email'         => 'email',
			'mail'          => 'email',
			'e_mail'        => 'email',
			'telephone'     => 'phone',
			'tel'           => 'phone',
			'phone'         => 'phone',
			'whatsapp'      => 'phone',
			'places'        => 'seats_allowed',
			'nb_places'     => 'seats_allowed',
			'seats'         => 'seats_allowed',
			'groupe'        => 'group_label',
			'group'         => 'group_label',
			'table'         => 'table_name',
			'notes'         => 'notes',
		);
		$n = 0;
		foreach ( $lines as $line ) {
			if ( '' === trim( str_replace( $delim, '', $line ) ) ) {
				continue;
			}
			$cells = str_getcsv( $line, $delim );
			$row   = array( 'owner_id' => $owner );
			foreach ( $headers as $i => $h ) {
				if ( isset( $alias[ $h ] ) && isset( $cells[ $i ] ) ) {
					$row[ $alias[ $h ] ] = $cells[ $i ];
				}
			}
			if ( ! isset( $row['seats_allowed'] ) || '' === trim( (string) $row['seats_allowed'] ) ) {
				$row['seats_allowed'] = 1;
			}
			$res = WRP_Guests::create( wp_slash( $row ) );
			if ( ! is_wp_error( $res ) ) {
				++$n;
			}
		}
		self::redirect( self::PAGE_GUESTS, array( 'wrp_msg' => 'imported', 'n' => $n ) );
	}

	public static function handle_export() {
		self::require_cap();
		check_admin_referer( 'wrp_export' );

		$scope = null;
		if ( ! WRP_Roles::can_manage_all() ) {
			$scope = get_current_user_id();
		} else {
			$list = isset( $_GET['list'] ) ? sanitize_key( $_GET['list'] ) : 'all';
			if ( in_array( $list, array( 'a', 'b' ), true ) && WRP_Settings::get( 'partner_' . $list . '_user' ) ) {
				$scope = (int) WRP_Settings::get( 'partner_' . $list . '_user' );
			} elseif ( 'mine' === $list ) {
				$scope = get_current_user_id();
			}
		}
		$res = WRP_Guests::query(
			array(
				'owner_id' => $scope,
				'status'   => isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '',
				'search'   => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
				'group'    => isset( $_GET['group'] ) ? sanitize_text_field( wp_unslash( $_GET['group'] ) ) : '',
				'per_page' => 1000,
			)
		);

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=invites-mariage-' . gmdate( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore -- Excel-friendly BOM.
		fputcsv( $out, array( 'prenom', 'nom', 'email', 'telephone', 'places', 'places_confirmees', 'accompagnants', 'groupe', 'table', 'liste', 'statut', 'regime', 'message', 'code', 'arrive_le', 'lien_reponse', 'lien_carton', 'notes' ), ';' );
		foreach ( $res['items'] as $g ) {
			fputcsv(
				$out,
				array(
					$g->first_name,
					$g->last_name,
					$g->email,
					$g->phone,
					$g->seats_allowed,
					$g->seats_confirmed,
					implode( ', ', WRP_Guests::companions( $g ) ),
					$g->group_label,
					$g->table_name,
					WRP_Settings::owner_label( $g->owner_id ),
					WRP_Guests::status_label( $g->status ),
					$g->dietary,
					$g->guest_message,
					$g->pass_code,
					$g->checked_in_at,
					WRP_Guests::rsvp_url( $g ),
					$g->pass_code ? WRP_Guests::card_url( $g ) : '',
					$g->notes,
				),
				';'
			);
		}
		fclose( $out ); // phpcs:ignore
		exit;
	}

	public static function handle_reset_checkin() {
		self::require_cap();
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		check_admin_referer( 'wrp_reset_' . $id );
		$g = WRP_Guests::get( $id );
		if ( WRP_Roles::can_edit_guest( $g ) ) {
			WRP_Guests::reset_checkin( $id );
		}
		self::redirect( self::PAGE_GUESTS, array( 'action' => 'edit', 'id' => $id, 'wrp_msg' => 'reset' ) );
	}

	public static function handle_regen_key() {
		self::require_cap( 'wrp_manage_settings' );
		check_admin_referer( 'wrp_regen_key' );
		WRP_Settings::update( array( 'scanner_key' => WRP_Settings::generate_key() ) );
		self::redirect( self::PAGE_SCANNER, array( 'wrp_msg' => 'regen' ) );
	}

	public static function ajax_mark_invited() {
		check_ajax_referer( 'wrp_ajax', 'nonce' );
		$g = isset( $_POST['id'] ) ? WRP_Guests::get( (int) $_POST['id'] ) : null;
		if ( ! WRP_Roles::can_edit_guest( $g ) ) {
			wp_send_json_error( null, 403 );
		}
		WRP_Guests::mark_invited( $g );
		wp_send_json_success( array( 'status' => WRP_Guests::get( $g->id )->status ) );
	}

	public static function ajax_live_stats() {
		check_ajax_referer( 'wrp_ajax', 'nonce' );
		if ( ! current_user_can( 'wrp_manage_guests' ) ) {
			wp_send_json_error( null, 403 );
		}
		global $wpdb;
		$t    = WRP_DB::guests_table();
		$rows = $wpdb->get_results( "SELECT first_name, last_name, checked_in_at, checked_in_seats, table_name FROM $t WHERE checked_in_at IS NOT NULL ORDER BY checked_in_at DESC LIMIT 30" ); // phpcs:ignore
		$list = array();
		foreach ( $rows as $r ) {
			$list[] = array(
				'name'  => trim( $r->first_name . ' ' . $r->last_name ),
				'time'  => mysql2date( 'H:i', $r->checked_in_at ),
				'seats' => (int) $r->checked_in_seats,
				'table' => $r->table_name,
			);
		}
		wp_send_json_success(
			array(
				'stats'    => WRP_Guests::stats(),
				'arrivals' => $list,
			)
		);
	}

	/* ------------------------------------------------------------------
	 * Wedding-day page
	 * ---------------------------------------------------------------- */

	public static function page_scanner() {
		self::require_cap();
		$stats   = WRP_Guests::stats();
		$url     = WRP_Public::url( 'scanner' );
		$key     = WRP_Settings::get( 'scanner_key' );
		$can_key = current_user_can( 'wrp_manage_settings' ) || WRP_Roles::can_manage_all() || WRP_Settings::side_of_user( get_current_user_id() );
		echo '<div class="wrap wrp-wrap">';
		self::header( __( 'Le jour J', 'wedding-rsvp-pass' ), __( 'Validation des cartons à l’entrée – même sans connexion', 'wedding-rsvp-pass' ) );
		self::notice();
		?>
		<div class="wrp-dday">
			<div class="wrp-card wrp-dday__setup">
				<h2><?php esc_html_e( '1. Installez le scanner sur les téléphones', 'wedding-rsvp-pass' ); ?></h2>
				<p><?php esc_html_e( 'Scannez ce QR code avec le téléphone de la personne qui accueillera les invités (ou ouvrez le lien), puis « Ajouter à l’écran d’accueil ».', 'wedding-rsvp-pass' ); ?></p>
				<div class="wrp-qr wrp-qr--big" data-wrp-qr="<?php echo esc_attr( $url ); ?>"></div>
				<div class="wrp-linkbox"><input type="text" readonly value="<?php echo esc_attr( $url ); ?>" onclick="this.select()"><button type="button" class="button" data-wrp-copy="<?php echo esc_attr( $url ); ?>"><?php esc_html_e( 'Copier', 'wedding-rsvp-pass' ); ?></button></div>

				<?php if ( $can_key ) : ?>
					<h2><?php esc_html_e( '2. Activez-le avec la clé d’accès', 'wedding-rsvp-pass' ); ?></h2>
					<p><?php esc_html_e( 'Dans l’application, touchez « Scanner le QR d’activation » et visez ce code (ou saisissez la clé).', 'wedding-rsvp-pass' ); ?></p>
					<details class="wrp-secret">
						<summary><?php esc_html_e( 'Afficher le QR d’activation & la clé', 'wedding-rsvp-pass' ); ?></summary>
						<div class="wrp-qr wrp-qr--big" data-wrp-qr="<?php echo esc_attr( 'WRPKEY:' . $key ); ?>"></div>
						<p class="wrp-code wrp-code--big"><?php echo esc_html( $key ); ?></p>
						<?php if ( current_user_can( 'wrp_manage_settings' ) ) : ?>
							<a class="button" data-wrp-confirm="regen" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wrp_regen_key' ), 'wrp_regen_key' ) ); ?>"><?php esc_html_e( 'Générer une nouvelle clé', 'wedding-rsvp-pass' ); ?></a>
						<?php endif; ?>
					</details>
				<?php endif; ?>

				<h2><?php esc_html_e( '3. Avant la cérémonie', 'wedding-rsvp-pass' ); ?></h2>
				<ul class="wrp-steps">
					<li><?php esc_html_e( 'Ouvrez le scanner avec du réseau et touchez « Synchroniser » : la liste des invités est enregistrée dans le téléphone.', 'wedding-rsvp-pass' ); ?></li>
					<li><?php esc_html_e( 'Ensuite, le scan fonctionne entièrement hors connexion (mode avion possible).', 'wedding-rsvp-pass' ); ?></li>
					<li><?php esc_html_e( 'Dès que le réseau revient, les passages sont envoyés automatiquement ici.', 'wedding-rsvp-pass' ); ?></li>
					<li><?php esc_html_e( 'Plusieurs téléphones ? Synchronisez-les régulièrement pour détecter les doubles passages.', 'wedding-rsvp-pass' ); ?></li>
				</ul>
			</div>
			<div class="wrp-card wrp-dday__live">
				<h2><?php esc_html_e( 'Arrivées en direct', 'wedding-rsvp-pass' ); ?></h2>
				<div class="wrp-ring" data-wrp-ring>
					<svg viewBox="0 0 120 120"><circle cx="60" cy="60" r="52" class="wrp-ring__bg"/><circle cx="60" cy="60" r="52" class="wrp-ring__fg" style="--p:<?php echo $stats['seats_confirmed'] ? (int) round( $stats['seats_checked_in'] / $stats['seats_confirmed'] * 100 ) : 0; ?>"/></svg>
					<div class="wrp-ring__txt"><strong data-live="in"><?php echo (int) $stats['seats_checked_in']; ?></strong><span>/ <b data-live="expected"><?php echo (int) $stats['seats_confirmed']; ?></b> <?php esc_html_e( 'personnes', 'wedding-rsvp-pass' ); ?></span></div>
				</div>
				<ul class="wrp-arrivals" data-wrp-arrivals><li class="wrp-muted"><?php esc_html_e( 'Chargement…', 'wedding-rsvp-pass' ); ?></li></ul>
			</div>
		</div>
		<?php
		echo '</div>';
	}

	/* ------------------------------------------------------------------
	 * Settings page
	 * ---------------------------------------------------------------- */

	private static function settings_fields() {
		return array(
			'couple'   => array(
				'title'  => __( 'Les mariés', 'wedding-rsvp-pass' ),
				'admin'  => true,
				'fields' => array(
					'partner_a_name' => array( 'text', __( 'Prénom de la mariée / du marié n°1', 'wedding-rsvp-pass' ) ),
					'partner_a_user' => array( 'user', __( 'Compte WordPress (sa liste)', 'wedding-rsvp-pass' ) ),
					'partner_b_name' => array( 'text', __( 'Prénom du marié / de la mariée n°2', 'wedding-rsvp-pass' ) ),
					'partner_b_user' => array( 'user', __( 'Compte WordPress (sa liste)', 'wedding-rsvp-pass' ) ),
				),
			),
			'event'    => array(
				'title'  => __( 'Le grand jour', 'wedding-rsvp-pass' ),
				'admin'  => true,
				'fields' => array(
					'wedding_date'  => array( 'date', __( 'Date', 'wedding-rsvp-pass' ) ),
					'wedding_time'  => array( 'time', __( 'Heure', 'wedding-rsvp-pass' ) ),
					'venue_name'    => array( 'text', __( 'Lieu', 'wedding-rsvp-pass' ) ),
					'venue_address' => array( 'text', __( 'Adresse', 'wedding-rsvp-pass' ) ),
					'map_url'       => array( 'url', __( 'Lien Google Maps / Waze', 'wedding-rsvp-pass' ) ),
					'dress_code'    => array( 'text', __( 'Dress code', 'wedding-rsvp-pass' ) ),
					'rsvp_deadline' => array( 'date', __( 'Date limite de réponse', 'wedding-rsvp-pass' ) ),
					'allow_change_after_confirm' => array( 'checkbox', __( 'Les invités peuvent modifier leur réponse jusqu’à la date limite', 'wedding-rsvp-pass' ) ),
				),
			),
			'messages' => array(
				'title'  => __( 'Messages aux invités', 'wedding-rsvp-pass' ),
				'help'   => __( 'Variables : {prenom} {nom} {nom_complet} {maries} {date} {heure} {lieu} {adresse} {places} {date_limite} {lien} {carton} {code} {table}', 'wedding-rsvp-pass' ),
				'fields' => array(
					'invite_subject'  => array( 'text', __( 'Objet de l’e-mail d’invitation', 'wedding-rsvp-pass' ) ),
					'invite_body'     => array( 'textarea', __( 'E-mail d’invitation', 'wedding-rsvp-pass' ) ),
					'whatsapp_text'   => array( 'textarea', __( 'Message WhatsApp / SMS', 'wedding-rsvp-pass' ) ),
					'confirm_subject' => array( 'text', __( 'Objet de l’e-mail du carton', 'wedding-rsvp-pass' ) ),
					'confirm_body'    => array( 'textarea', __( 'E-mail envoyé après confirmation (avec le carton)', 'wedding-rsvp-pass' ) ),
					'country_code'    => array( 'text', __( 'Indicatif pays par défaut pour WhatsApp (ex : 33, 225, 237, 32, 41, 1)', 'wedding-rsvp-pass' ) ),
					'email_from_name' => array( 'text', __( 'Nom de l’expéditeur (vide = prénoms des mariés)', 'wedding-rsvp-pass' ) ),
					'email_from_address' => array( 'email', __( 'Adresse de l’expéditeur (optionnel)', 'wedding-rsvp-pass' ) ),
				),
			),
			'card'     => array(
				'title'  => __( 'Carton d’invitation', 'wedding-rsvp-pass' ),
				'admin'  => true,
				'fields' => array(
					'card_invite_line' => array( 'text', __( 'Phrase d’invitation', 'wedding-rsvp-pass' ) ),
					'card_quote'       => array( 'text', __( 'Citation', 'wedding-rsvp-pass' ) ),
					'color_accent'     => array( 'color', __( 'Couleur dorée / accent', 'wedding-rsvp-pass' ) ),
					'color_dark'       => array( 'color', __( 'Couleur du texte', 'wedding-rsvp-pass' ) ),
					'color_blush'      => array( 'color', __( 'Couleur poudrée (fond)', 'wedding-rsvp-pass' ) ),
				),
			),
		);
	}

	private static function can_edit_section( $section ) {
		return empty( $section['admin'] ) || current_user_can( 'wrp_manage_settings' );
	}

	public static function page_settings() {
		self::require_cap();
		$all = WRP_Settings::all();
		echo '<div class="wrap wrp-wrap">';
		self::header( __( 'Messages & réglages', 'wedding-rsvp-pass' ) );
		self::notice();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wrp-settings">
			<input type="hidden" name="action" value="wrp_save_settings">
			<?php wp_nonce_field( 'wrp_save_settings' ); ?>
			<?php foreach ( self::settings_fields() as $sid => $section ) : ?>
				<?php
				if ( ! self::can_edit_section( $section ) ) {
					continue;
				}
				?>
				<section class="wrp-card">
					<h2><?php echo esc_html( $section['title'] ); ?></h2>
					<?php if ( ! empty( $section['help'] ) ) : ?>
						<p class="wrp-muted"><code><?php echo esc_html( $section['help'] ); ?></code></p>
					<?php endif; ?>
					<div class="wrp-grid">
					<?php foreach ( $section['fields'] as $key => $field ) : ?>
						<?php
						list( $type, $label ) = $field;
						$val  = isset( $all[ $key ] ) ? $all[ $key ] : '';
						$name = 'wrp[' . $key . ']';
						$full = in_array( $type, array( 'textarea', 'checkbox' ), true ) ? ' wrp-grid__full' : '';
						?>
						<label class="<?php echo esc_attr( trim( $full ) ); ?>">
							<?php if ( 'checkbox' === $type ) : ?>
								<span class="wrp-check-line"><input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="0"><input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( (int) $val, 1 ); ?>> <?php echo esc_html( $label ); ?></span>
							<?php else : ?>
								<span><?php echo esc_html( $label ); ?></span>
								<?php if ( 'textarea' === $type ) : ?>
									<textarea name="<?php echo esc_attr( $name ); ?>" rows="8"><?php echo esc_textarea( $val ); ?></textarea>
								<?php elseif ( 'user' === $type ) : ?>
									<?php
									wp_dropdown_users(
										array(
											'name'             => $name,
											'selected'         => (int) $val,
											'show_option_none' => __( '— Aucun —', 'wedding-rsvp-pass' ),
											'option_none_value' => 0,
											'role__in'         => array( 'administrator', 'wrp_spouse', 'wrp_planner', 'editor' ),
										)
									);
									?>
								<?php else : ?>
									<input type="<?php echo esc_attr( $type ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $val ); ?>">
								<?php endif; ?>
							<?php endif; ?>
						</label>
					<?php endforeach; ?>
					</div>
					<?php if ( 'couple' === $sid ) : ?>
						<p class="wrp-muted"><?php printf( wp_kses_post( __( 'Créez un compte pour chaque marié·e avec le rôle <strong>Marié(e)</strong> (Utilisateurs → Ajouter) : chacun ne verra et ne gérera que sa propre liste d’invités.', 'wedding-rsvp-pass' ) ) ); ?></p>
					<?php endif; ?>
				</section>
			<?php endforeach; ?>
			<p><button class="button button-primary wrp-btn"><?php esc_html_e( 'Enregistrer les réglages', 'wedding-rsvp-pass' ); ?></button></p>
		</form>
		<?php
		echo '</div>';
	}

	public static function handle_save_settings() {
		self::require_cap();
		check_admin_referer( 'wrp_save_settings' );
		$input = isset( $_POST['wrp'] ) && is_array( $_POST['wrp'] ) ? wp_unslash( $_POST['wrp'] ) : array(); // phpcs:ignore
		$clean = array();
		foreach ( self::settings_fields() as $section ) {
			if ( ! self::can_edit_section( $section ) ) {
				continue;
			}
			foreach ( $section['fields'] as $key => $field ) {
				if ( ! array_key_exists( $key, $input ) ) {
					continue;
				}
				$raw = $input[ $key ];
				switch ( $field[0] ) {
					case 'textarea':
						$clean[ $key ] = sanitize_textarea_field( $raw );
						break;
					case 'url':
						$clean[ $key ] = esc_url_raw( $raw );
						break;
					case 'email':
						$clean[ $key ] = sanitize_email( $raw );
						break;
					case 'color':
						$clean[ $key ] = sanitize_hex_color( $raw ) ?: '';
						break;
					case 'user':
					case 'checkbox':
						$clean[ $key ] = (int) $raw;
						break;
					case 'date':
						$clean[ $key ] = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ? $raw : '';
						break;
					case 'time':
						$clean[ $key ] = preg_match( '/^\d{2}:\d{2}$/', $raw ) ? $raw : '';
						break;
					default:
						$clean[ $key ] = sanitize_text_field( $raw );
				}
			}
		}
		WRP_Settings::update( $clean );
		self::redirect( self::PAGE_SETTINGS, array( 'wrp_msg' => 'settings' ) );
	}
}

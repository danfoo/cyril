<?php
/**
 * Roles & capabilities.
 *
 * - wrp_manage_guests      : manage your OWN guest list (each spouse).
 * - wrp_manage_all_guests  : see / edit every list (admin, wedding planner).
 * - wrp_manage_settings    : plugin settings & scanner devices.
 * - wrp_scan               : use the wedding-day scanner (agents d'accueil).
 *
 * @package WeddingRSVPPass
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WRP_Roles {

	public static function init() {
		// Spouses land directly on their guest list after login.
		add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 10, 3 );
		// Scan agents never see wp-admin.
		add_action( 'admin_init', array( __CLASS__, 'keep_scanners_out' ) );
		add_filter( 'show_admin_bar', array( __CLASS__, 'admin_bar' ) );
	}

	public static function install() {
		add_role(
			'wrp_spouse',
			__( 'Marié(e)', 'wedding-rsvp-pass' ),
			array(
				'read'              => true,
				'upload_files'      => true,
				'wrp_manage_guests' => true,
				'wrp_scan'          => true,
			)
		);
		$spouse = get_role( 'wrp_spouse' );
		if ( $spouse ) {
			$spouse->add_cap( 'wrp_scan' );
		}

		add_role(
			'wrp_scanner',
			__( 'Agent d’accueil (scan)', 'wedding-rsvp-pass' ),
			array(
				'read'     => true,
				'wrp_scan' => true,
			)
		);

		add_role(
			'wrp_planner',
			__( 'Organisateur·rice du mariage', 'wedding-rsvp-pass' ),
			array(
				'read'                  => true,
				'wrp_manage_guests'     => true,
				'wrp_manage_all_guests' => true,
				'wrp_scan'              => true,
			)
		);
		$planner = get_role( 'wrp_planner' );
		if ( $planner ) {
			$planner->add_cap( 'wrp_scan' );
		}

		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( 'wrp_manage_guests' );
			$admin->add_cap( 'wrp_manage_all_guests' );
			$admin->add_cap( 'wrp_manage_settings' );
			$admin->add_cap( 'wrp_scan' );
		}
	}

	public static function can_manage_all( $user_id = 0 ) {
		$user_id = $user_id ? $user_id : get_current_user_id();
		return user_can( $user_id, 'wrp_manage_all_guests' );
	}

	/**
	 * Can the current user edit this guest row?
	 *
	 * @param object $guest Guest row.
	 */
	public static function can_edit_guest( $guest ) {
		if ( ! $guest || ! current_user_can( 'wrp_manage_guests' ) ) {
			return false;
		}
		if ( self::can_manage_all() ) {
			return true;
		}
		return (int) $guest->owner_id === get_current_user_id();
	}

	public static function is_scanner_only( $user = null ) {
		$user = $user ? $user : wp_get_current_user();
		return $user instanceof WP_User && $user->exists() && user_can( $user, 'wrp_scan' ) && ! user_can( $user, 'wrp_manage_guests' ) && ! user_can( $user, 'edit_posts' );
	}

	public static function keep_scanners_out() {
		if ( wp_doing_ajax() || ! self::is_scanner_only() ) {
			return;
		}
		wp_safe_redirect( WRP_Public::url( 'scanner' ) );
		exit;
	}

	public static function admin_bar( $show ) {
		return self::is_scanner_only() ? false : $show;
	}

	public static function login_redirect( $redirect_to, $requested, $user ) {
		if ( self::is_scanner_only( $user instanceof WP_User ? $user : null ) ) {
			return WRP_Public::url( 'scanner' );
		}
		if ( $user instanceof WP_User && in_array( 'wrp_spouse', (array) $user->roles, true ) && ( ! $requested || admin_url() === $redirect_to ) ) {
			return admin_url( 'admin.php?page=wrp-guests' );
		}
		return $redirect_to;
	}
}

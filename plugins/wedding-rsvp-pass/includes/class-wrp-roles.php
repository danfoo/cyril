<?php
/**
 * Roles & capabilities.
 *
 * - wrp_manage_guests      : manage your OWN guest list (each spouse).
 * - wrp_manage_all_guests  : see / edit every list (admin, wedding planner).
 * - wrp_manage_settings    : plugin settings & scanner devices.
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
	}

	public static function install() {
		add_role(
			'wrp_spouse',
			__( 'Marié(e)', 'wedding-rsvp-pass' ),
			array(
				'read'              => true,
				'upload_files'      => true,
				'wrp_manage_guests' => true,
			)
		);

		add_role(
			'wrp_planner',
			__( 'Organisateur·rice du mariage', 'wedding-rsvp-pass' ),
			array(
				'read'                  => true,
				'wrp_manage_guests'     => true,
				'wrp_manage_all_guests' => true,
			)
		);

		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( 'wrp_manage_guests' );
			$admin->add_cap( 'wrp_manage_all_guests' );
			$admin->add_cap( 'wrp_manage_settings' );
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

	public static function login_redirect( $redirect_to, $requested, $user ) {
		if ( $user instanceof WP_User && in_array( 'wrp_spouse', (array) $user->roles, true ) && ( ! $requested || admin_url() === $redirect_to ) ) {
			return admin_url( 'admin.php?page=wrp-guests' );
		}
		return $redirect_to;
	}
}

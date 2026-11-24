<?php
/**
 * Database schema.
 *
 * @package WeddingRSVPPass
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WRP_DB {

	public static function guests_table() {
		global $wpdb;
		return $wpdb->prefix . 'wrp_guests';
	}

	public static function checkins_table() {
		global $wpdb;
		return $wpdb->prefix . 'wrp_checkins';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$guests  = self::guests_table();
		$checks  = self::checkins_table();

		// dbDelta is picky: two spaces after PRIMARY KEY, one field per line.
		$sql_guests = "CREATE TABLE $guests (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			owner_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			first_name VARCHAR(100) NOT NULL DEFAULT '',
			last_name VARCHAR(100) NOT NULL DEFAULT '',
			email VARCHAR(190) NOT NULL DEFAULT '',
			phone VARCHAR(40) NOT NULL DEFAULT '',
			group_label VARCHAR(100) NOT NULL DEFAULT '',
			table_name VARCHAR(100) NOT NULL DEFAULT '',
			seats_allowed SMALLINT UNSIGNED NOT NULL DEFAULT 1,
			seats_confirmed SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			companions TEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			token CHAR(32) NOT NULL,
			pass_code VARCHAR(20) NULL,
			guest_message TEXT NULL,
			dietary VARCHAR(255) NOT NULL DEFAULT '',
			notes TEXT NULL,
			invited_at DATETIME NULL,
			responded_at DATETIME NULL,
			checked_in_at DATETIME NULL,
			checked_in_seats SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			checked_in_device VARCHAR(64) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token (token),
			UNIQUE KEY pass_code (pass_code),
			KEY owner_id (owner_id),
			KEY status (status)
		) $charset;";

		$sql_checkins = "CREATE TABLE $checks (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			uuid CHAR(36) NOT NULL,
			guest_id BIGINT UNSIGNED NOT NULL,
			device_id VARCHAR(64) NOT NULL DEFAULT '',
			device_name VARCHAR(100) NOT NULL DEFAULT '',
			seats SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			scanned_at DATETIME NOT NULL,
			synced_at DATETIME NOT NULL,
			result VARCHAR(20) NOT NULL DEFAULT 'ok',
			PRIMARY KEY  (id),
			UNIQUE KEY uuid (uuid),
			KEY guest_id (guest_id)
		) $charset;";

		dbDelta( $sql_guests );
		dbDelta( $sql_checkins );

		update_option( 'wrp_db_version', WRP_DB_VERSION );
	}

	public static function maybe_upgrade() {
		if ( get_option( 'wrp_db_version' ) !== WRP_DB_VERSION ) {
			self::install();
			WRP_Roles::install();
			WRP_Settings::ensure_scanner_key();
			// New URLs (e.g. /espace-maries/) need a rewrite flush after an update.
			update_option( 'wrp_flush_rules', 1 );
		}
	}
}

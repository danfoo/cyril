<?php
/**
 * Uninstall: remove plugin options. Clubs, players and matches are kept on purpose
 * (they are editorial content); delete them from their admin lists if needed.
 *
 * @package GalsenFootLeague
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

foreach ( array( 'gsf_settings', 'gsf_cache_v', 'gsf_rewrite_v', 'gsf_api_log', 'gsf_api_remaining', 'gsf_api_squad_offset', 'gsf_api_stats_page', 'gsf_api_league_name' ) as $gsf_option ) {
	delete_option( $gsf_option );
}
wp_clear_scheduled_hook( 'gsf_api_cron' );

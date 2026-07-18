<?php
/**
 * Désinstallation : suppression complète des données (tables + options).
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

global $wpdb;

$tables = [
    'bem_leads', 'bem_events', 'bem_chat_messages', 'bem_scoring_rules',
    'bem_triggers', 'bem_competitor_mentions',
    'bem_financing_options', 'bem_message_variants', 'bem_handoffs',
];
foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}");
}
// Ancienne table d'index vectoriel (versions RAG antérieures).
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}bem_content_index");

delete_option('bem_lead_ai_settings');
delete_option('bem_lead_ai_db_version');
delete_option('bem_lead_ai_kb');
delete_option('bem_lead_ai_wa_cursor');

// Nettoyage des résumés par lead et des crons.
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'bem_lead_ai_last_summary_%'");
wp_clear_scheduled_hook('bem_lead_ai_cron_disengagement');
wp_clear_scheduled_hook('bem_lead_ai_cron_rebuild_kb');
wp_clear_scheduled_hook('bem_lead_ai_cron_bandit');

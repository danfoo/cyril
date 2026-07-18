<?php
/**
 * Désinstallation.
 *
 * IMPORTANT : par défaut, on NE SUPPRIME RIEN. Ainsi, mettre à jour le plugin
 * (supprimer puis re-téléverser) conserve la clé API, le design, les leads et
 * l'historique des conversations. La suppression complète n'a lieu que si
 * l'administrateur a explicitement coché « Tout supprimer à la désinstallation »
 * dans les réglages (option bem_lead_ai_delete_data = 1).
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

if (get_option('bem_lead_ai_delete_data') !== '1') {
    return; // conservation des données (comportement par défaut, sûr pour les mises à jour)
}

global $wpdb;

$tables = [
    'bem_leads', 'bem_events', 'bem_chat_messages', 'bem_scoring_rules',
    'bem_triggers', 'bem_competitor_mentions',
    'bem_financing_options', 'bem_message_variants', 'bem_handoffs',
];
foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}");
}
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}bem_content_index");

delete_option('bem_lead_ai_settings');
delete_option('bem_lead_ai_db_version');
delete_option('bem_lead_ai_kb');
delete_option('bem_lead_ai_wa_cursor');
delete_option('bem_lead_ai_delete_data');

$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'bem_lead_ai_last_summary_%'");
wp_clear_scheduled_hook('bem_lead_ai_cron_disengagement');
wp_clear_scheduled_hook('bem_lead_ai_cron_rebuild_kb');
wp_clear_scheduled_hook('bem_lead_ai_cron_bandit');

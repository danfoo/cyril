<?php

namespace BemLeadAi\Leads;

defined('ABSPATH') || exit;

final class EventRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'bem_events';
    }

    /**
     * Enregistre un événement puis déclenche le pipeline scoring → triggers.
     */
    public function record(int $leadId, string $type, array $payload = [], string $canal = 'web'): int
    {
        global $wpdb;
        $wpdb->insert($this->table, [
            'lead_id' => $leadId,
            'type' => $type,
            'canal' => $canal,
            'payload' => wp_json_encode($payload),
            'created_at' => current_time('mysql'),
        ]);
        $id = (int) $wpdb->insert_id;

        do_action('bem_lead_ai_event_recorded', $leadId, $type, $payload, $canal);

        return $id;
    }

    /** @return object[] */
    public function forLead(int $leadId, int $sinceDays = 90): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE lead_id = %d AND created_at >= DATE_SUB(%s, INTERVAL %d DAY) ORDER BY created_at ASC",
            $leadId,
            current_time('mysql'),
            $sinceDays
        )) ?: [];
    }

    public function countSince(int $leadId, string $type, int $hours): int
    {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE lead_id = %d AND type = %s AND created_at >= DATE_SUB(%s, INTERVAL %d HOUR)",
            $leadId,
            $type,
            current_time('mysql'),
            $hours
        ));
    }

    public function lastOfType(int $leadId, string $type): ?object
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE lead_id = %d AND type = %s ORDER BY created_at DESC LIMIT 1",
            $leadId,
            $type
        ));
        return $row ?: null;
    }
}

<?php

namespace BemLeadAi\Leads;

defined('ABSPATH') || exit;

final class LeadRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'bem_leads';
    }

    public function findById(int $id): ?object
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id));
        return $row ?: null;
    }

    public function findBySessionId(string $sessionId): ?object
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE session_id = %s", $sessionId));
        return $row ?: null;
    }

    public function findByEmail(string $email): ?object
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE email = %s", $email));
        return $row ?: null;
    }

    /**
     * Crée ou retrouve un lead pour un identifiant de session et enregistre le
     * canal utilisé.
     */
    public function findOrCreate(string $sessionId, string $canal = 'web'): object
    {
        global $wpdb;
        $lead = $this->findBySessionId($sessionId);
        if ($lead) {
            $this->touch((int) $lead->id, $canal);
            return $this->findById((int) $lead->id);
        }
        $now = current_time('mysql');
        $wpdb->insert($this->table, [
            'session_id' => $sessionId,
            'channels' => $canal,
            'first_seen' => $now,
            'last_seen' => $now,
        ]);
        return $this->findById((int) $wpdb->insert_id);
    }

    public function touch(int $id, string $canal): void
    {
        global $wpdb;
        $lead = $this->findById($id);
        if (!$lead) {
            return;
        }
        $channels = array_filter(array_unique(array_merge(explode(',', (string) $lead->channels), [$canal])));
        $wpdb->update($this->table, [
            'last_seen' => current_time('mysql'),
            'channels' => implode(',', $channels),
        ], ['id' => $id]);
    }

    public function update(int $id, array $fields): void
    {
        global $wpdb;
        $wpdb->update($this->table, $fields, ['id' => $id]);
    }

    /** Signaux IA du lead (urgence, sensibilité prix, etc.), fusionnés au fil des classifications. */
    public function mergeSignals(int $id, array $newSignals): array
    {
        $lead = $this->findById($id);
        $signals = $lead && $lead->signals ? (json_decode($lead->signals, true) ?: []) : [];
        $signals = array_merge($signals, $newSignals);
        $this->update($id, ['signals' => wp_json_encode($signals)]);
        return $signals;
    }

    public function delete(int $id): void
    {
        global $wpdb;
        $p = $wpdb->prefix;
        foreach (['bem_events', 'bem_chat_messages', 'bem_competitor_mentions', 'bem_handoffs', 'bem_crm_activities'] as $table) {
            $wpdb->delete($p . $table, ['lead_id' => $id]);
        }
        $wpdb->delete($this->table, ['id' => $id]);
    }
}

<?php

namespace BemLeadAi\Chat;

defined('ABSPATH') || exit;

final class ConversationRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'bem_chat_messages';
    }

    public function add(int $leadId, string $role, string $contenu, string $canal = 'web'): int
    {
        global $wpdb;
        $wpdb->insert($this->table, [
            'lead_id' => $leadId,
            'canal' => $canal,
            'role' => $role,
            'contenu' => $contenu,
            'created_at' => current_time('mysql'),
        ]);
        return (int) $wpdb->insert_id;
    }

    /** Nombre de messages « user » d'un lead — 1 = conversation qui vient de démarrer. */
    public function countUserMessages(int $leadId): int
    {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE lead_id = %d AND role = 'user'",
            $leadId
        ));
    }

    /** @return object[] Derniers messages, ordre chronologique. */
    public function history(int $leadId, int $limit = 20): array
    {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE lead_id = %d ORDER BY id DESC LIMIT %d",
            $leadId,
            $limit
        )) ?: [];
        return array_reverse($rows);
    }

    /** Messages postérieurs à un id donné (polling du widget pendant un handoff). */
    public function since(int $leadId, int $afterId): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, role, contenu, canal, created_at FROM {$this->table} WHERE lead_id = %d AND id > %d ORDER BY id ASC",
            $leadId,
            $afterId
        )) ?: [];
    }

    /** @return object[] Messages utilisateur pas encore passés par la classification multi-signaux. */
    public function unclassifiedUserMessages(int $leadId, int $limit = 10): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE lead_id = %d AND role = 'user' AND classified = 0 ORDER BY id ASC LIMIT %d",
            $leadId,
            $limit
        )) ?: [];
    }

    public function markClassified(array $ids): void
    {
        global $wpdb;
        if (!$ids) {
            return;
        }
        $in = implode(',', array_map('intval', $ids));
        $wpdb->query("UPDATE {$this->table} SET classified = 1 WHERE id IN ($in)");
    }
}

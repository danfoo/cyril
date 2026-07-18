<?php

namespace BemLeadAi\Crm;

defined('ABSPATH') || exit;

/**
 * CRM natif : pipeline d'admission, activités de suivi (notes, tâches,
 * appels, changements d'étape, affectations) et fil chronologique.
 *
 * Volontairement sans dépendance externe : chaque fiche lead devient un
 * mini-CRM directement dans l'admin WordPress. Les intégrations Perfex/HubSpot
 * restent disponibles en parallèle (synchro sortante).
 */
final class CrmRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'bem_crm_activities';
    }

    /**
     * Étapes du pipeline d'admission, dans l'ordre. La logique marketing :
     * du premier contact anonyme jusqu'à l'inscription (ou la perte).
     *
     * @return array<string, array{label:string, color:string}>
     */
    public static function stages(): array
    {
        return [
            'nouveau'     => ['label' => __('Nouveau', 'bem-lead-ai'), 'color' => '#646970'],
            'contacte'    => ['label' => __('Contacté', 'bem-lead-ai'), 'color' => '#2271b1'],
            'qualifie'    => ['label' => __('Qualifié', 'bem-lead-ai'), 'color' => '#2e7d32'],
            'relance'     => ['label' => __('En relance', 'bem-lead-ai'), 'color' => '#dba617'],
            'candidature' => ['label' => __('Candidature', 'bem-lead-ai'), 'color' => '#8250df'],
            'inscrit'     => ['label' => __('Inscrit', 'bem-lead-ai'), 'color' => '#00a32a'],
            'perdu'       => ['label' => __('Perdu', 'bem-lead-ai'), 'color' => '#d63638'],
        ];
    }

    public static function stageLabel(string $slug): string
    {
        return self::stages()[$slug]['label'] ?? ucfirst($slug);
    }

    public static function stageColor(string $slug): string
    {
        return self::stages()[$slug]['color'] ?? '#646970';
    }

    public static function isStage(string $slug): bool
    {
        return isset(self::stages()[$slug]);
    }

    /**
     * Journalise une activité de suivi.
     *
     * @param array<string, mixed> $meta
     */
    public function log(int $leadId, string $type, string $content = '', array $meta = [], ?string $dueAt = null): int
    {
        global $wpdb;
        $wpdb->insert($this->table, [
            'lead_id'    => $leadId,
            'type'       => $type,
            'content'    => $content,
            'author_id'  => get_current_user_id() ?: 0,
            'due_at'     => $dueAt,
            'done'       => 0,
            'meta'       => $meta ? wp_json_encode($meta) : null,
            'created_at' => current_time('mysql'),
        ]);
        return (int) $wpdb->insert_id;
    }

    /** @return object[] Activités du lead, les plus récentes d'abord. */
    public function activities(int $leadId, int $limit = 100): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE lead_id = %d ORDER BY created_at DESC, id DESC LIMIT %d",
            $leadId,
            $limit
        )) ?: [];
    }

    /** @return object[] Tâches non terminées d'un lead (échéance croissante). */
    public function openTasks(int $leadId): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE lead_id = %d AND type = 'task' AND done = 0 ORDER BY due_at IS NULL, due_at ASC",
            $leadId
        )) ?: [];
    }

    public function findActivity(int $id): ?object
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id)) ?: null;
    }

    /** Bascule l'état « terminé » d'une tâche. */
    public function toggleTask(int $activityId): void
    {
        global $wpdb;
        $task = $this->findActivity($activityId);
        if (!$task || $task->type !== 'task') {
            return;
        }
        $wpdb->update($this->table, ['done' => $task->done ? 0 : 1], ['id' => $activityId]);
    }

    public function deleteActivity(int $activityId): void
    {
        global $wpdb;
        $wpdb->delete($this->table, ['id' => $activityId]);
    }

    /**
     * Tâches à échéance (aujourd'hui ou en retard) non terminées, tous leads
     * confondus — alimente les rappels et le tableau de bord.
     *
     * @return object[]
     */
    public function dueTasks(int $withinDays = 0): array
    {
        global $wpdb;
        $p = $wpdb->prefix;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, l.prenom, l.email, l.phone
             FROM {$this->table} a
             INNER JOIN {$p}bem_leads l ON l.id = a.lead_id
             WHERE a.type = 'task' AND a.done = 0 AND a.due_at IS NOT NULL
               AND a.due_at <= DATE_ADD(NOW(), INTERVAL %d DAY)
             ORDER BY a.due_at ASC",
            $withinDays
        )) ?: [];
    }

    public function countOpenTasks(int $leadId): int
    {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE lead_id = %d AND type = 'task' AND done = 0",
            $leadId
        ));
    }
}

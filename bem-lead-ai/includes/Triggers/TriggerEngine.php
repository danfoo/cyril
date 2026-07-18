<?php

namespace BemLeadAi\Triggers;

use BemLeadAi\Core\Queue;
use BemLeadAi\Leads\LeadRepository;

defined('ABSPATH') || exit;

/**
 * Moteur de règles configurable : conditions JSON sur le profil du lead,
 * ses signaux IA et l'événement déclencheur → actions envoyées dans la file
 * asynchrone (Action Scheduler). L'escalade humaine part en priorité haute.
 *
 * Anti-harcèlement marketing : chaque trigger a un cooldown PAR LEAD —
 * un lead chaud ne doit pas déclencher une notification à chaque page vue.
 */
final class TriggerEngine
{
    public function evaluate(int $leadId, array $event): void
    {
        global $wpdb;
        $p = $wpdb->prefix;

        $lead = (new LeadRepository())->findById($leadId);
        if (!$lead) {
            return;
        }
        $signals = $lead->signals ? (json_decode($lead->signals, true) ?: []) : [];

        $triggers = $wpdb->get_results("SELECT * FROM {$p}bem_triggers WHERE actif = 1") ?: [];
        foreach ($triggers as $trigger) {
            $condition = json_decode($trigger->condition_json, true) ?: [];
            if (!$this->conditionMet($condition, $lead, $signals, $event)) {
                continue;
            }
            if ($this->inCooldown($leadId, (int) $trigger->id, (int) $trigger->cooldown_hours)) {
                continue;
            }

            $wpdb->insert("{$p}bem_events", [
                'lead_id' => $leadId,
                'type' => 'trigger_fired',
                'canal' => 'system',
                'payload' => wp_json_encode(['trigger_id' => (int) $trigger->id, 'trigger_nom' => $trigger->nom]),
                'created_at' => current_time('mysql'),
            ]);
            $wpdb->update("{$p}bem_triggers", ['last_run' => current_time('mysql')], ['id' => $trigger->id]);

            $actions = json_decode($trigger->actions_json, true) ?: [];
            foreach ($actions as $action) {
                $type = (string) ($action['type'] ?? '');
                if ($type === '') {
                    continue;
                }
                // Escalade humaine : quasi-temps réel → priorité haute.
                Queue::dispatch('bem_lead_ai_job_action', [$type, $leadId, $action], $type === 'escalate');
            }
        }
    }

    /**
     * Format de condition : {"all": [{"field": ..., "op": ..., "value": ...}]}
     * Champs disponibles : score_final, score_comportemental, score_intention,
     * statut, kb_mode, canal, signal.<clé>, event.type, event.payload.<clé>.
     */
    private function conditionMet(array $condition, object $lead, array $signals, array $event): bool
    {
        $clauses = $condition['all'] ?? [];
        if (!$clauses) {
            return false;
        }
        foreach ($clauses as $clause) {
            $actual = $this->resolveField((string) ($clause['field'] ?? ''), $lead, $signals, $event);
            if (!$this->compare($actual, (string) ($clause['op'] ?? '='), $clause['value'] ?? null)) {
                return false;
            }
        }
        return true;
    }

    private function resolveField(string $field, object $lead, array $signals, array $event): mixed
    {
        if (str_starts_with($field, 'signal.')) {
            return $signals[substr($field, 7)] ?? null;
        }
        if ($field === 'event.type') {
            return $event['type'] ?? null;
        }
        if (str_starts_with($field, 'event.payload.')) {
            return $event['payload'][substr($field, 14)] ?? null;
        }
        if ($field === 'canal') {
            return $event['canal'] ?? null;
        }
        return $lead->{$field} ?? null;
    }

    private function compare(mixed $actual, string $op, mixed $expected): bool
    {
        return match ($op) {
            '=' => $actual == $expected,
            '!=' => $actual != $expected,
            '>=' => is_numeric($actual) && (float) $actual >= (float) $expected,
            '<=' => is_numeric($actual) && (float) $actual <= (float) $expected,
            '>' => is_numeric($actual) && (float) $actual > (float) $expected,
            '<' => is_numeric($actual) && (float) $actual < (float) $expected,
            'contains' => is_string($actual) && str_contains(strtolower($actual), strtolower((string) $expected)),
            default => false,
        };
    }

    private function inCooldown(int $leadId, int $triggerId, int $cooldownHours): bool
    {
        if ($cooldownHours <= 0) {
            return false;
        }
        global $wpdb;
        $p = $wpdb->prefix;
        $lastFired = $wpdb->get_var($wpdb->prepare(
            "SELECT created_at FROM {$p}bem_events
             WHERE lead_id = %d AND type = 'trigger_fired'
               AND payload LIKE %s
             ORDER BY created_at DESC LIMIT 1",
            $leadId,
            '%"trigger_id":' . $triggerId . ',%'
        ));
        return $lastFired && strtotime($lastFired) > current_time('timestamp') - $cooldownHours * HOUR_IN_SECONDS;
    }
}

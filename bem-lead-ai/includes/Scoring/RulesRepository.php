<?php

namespace BemLeadAi\Scoring;

defined('ABSPATH') || exit;

final class RulesRepository
{
    /** @return object[] Règles actives, condition décodée dans ->condition. */
    public function activeRules(): array
    {
        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}bem_scoring_rules WHERE actif = 1") ?: [];
        foreach ($rows as $row) {
            $row->condition = json_decode($row->condition_json, true) ?: [];
        }
        return $rows;
    }

    /**
     * Une règle "event" matche un événement si toutes ses clés correspondent :
     *  - event_type : type exact de l'événement
     *  - payload.X : égalité sur la clé X du payload
     *  - payload.X_gte : la clé X du payload doit être >= la valeur
     */
    public function ruleMatchesEvent(array $condition, object $event, array $payload): bool
    {
        foreach ($condition as $key => $expected) {
            if ($key === 'event_type') {
                if ($event->type !== $expected) {
                    return false;
                }
                continue;
            }
            if (str_starts_with($key, 'payload.')) {
                $field = substr($key, 8);
                if (str_ends_with($field, '_gte')) {
                    $field = substr($field, 0, -4);
                    if (!isset($payload[$field]) || (float) $payload[$field] < (float) $expected) {
                        return false;
                    }
                } elseif (($payload[$field] ?? null) != $expected) {
                    return false;
                }
            }
        }
        return true;
    }
}

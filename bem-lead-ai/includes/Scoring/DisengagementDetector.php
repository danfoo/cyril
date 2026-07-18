<?php

namespace BemLeadAi\Scoring;

use BemLeadAi\Core\Options;
use BemLeadAi\Leads\EventRepository;

defined('ABSPATH') || exit;

/**
 * Détection de désengagement par vélocité : identifie les leads dont
 * l'activité retombe APRÈS un pic — le moment exact où ils risquent de
 * partir vers une école concurrente, et où une relance a le plus de valeur.
 *
 * Heuristique : sur les 14 derniers jours, si la fenêtre 72 h la plus active
 * (le pic) est nettement supérieure à l'activité des 72 dernières heures
 * (chute ≥ ratio configuré) ET que le lead avait atteint au moins la bande
 * « tiède », on émet un événement `disengagement` qui alimente le trigger
 * de relance.
 */
final class DisengagementDetector
{
    private const WINDOW_HOURS = 72;
    private const LOOKBACK_DAYS = 14;

    public function run(): void
    {
        global $wpdb;
        $p = $wpdb->prefix;

        $minScore = (float) Options::get('disengagement_min_score');
        $dropRatio = (float) Options::get('disengagement_drop_ratio');

        // Candidats : leads encore prospects, assez engagés, inactifs depuis
        // au moins 48 h mais vus dans les 14 derniers jours.
        $candidates = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM {$p}bem_leads
             WHERE statut = 'prospect'
               AND score_final >= %f
               AND last_seen < DATE_SUB(%s, INTERVAL 48 HOUR)
               AND last_seen > DATE_SUB(%s, INTERVAL %d DAY)",
            $minScore,
            current_time('mysql'),
            current_time('mysql'),
            self::LOOKBACK_DAYS
        )) ?: [];

        $events = new EventRepository();
        foreach ($candidates as $candidate) {
            $leadId = (int) $candidate->id;

            // Une seule relance par période de lookback : si un désengagement
            // a déjà été signalé récemment, on ne le répète pas.
            $last = $events->lastOfType($leadId, 'disengagement');
            if ($last && strtotime($last->created_at) > strtotime('-' . self::LOOKBACK_DAYS . ' days', current_time('timestamp'))) {
                continue;
            }

            [$peak, $recent] = $this->activityWindows($leadId);
            if ($peak >= 3 && $recent <= $peak * (1 - $dropRatio)) {
                $events->record($leadId, 'disengagement', [
                    'peak_activity' => $peak,
                    'recent_activity' => $recent,
                ], 'system');
            }
        }
    }

    /** @return array{0:int,1:int} [activité du pic 72 h, activité des 72 dernières heures] */
    private function activityWindows(int $leadId): array
    {
        global $wpdb;
        $p = $wpdb->prefix;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT created_at FROM {$p}bem_events
             WHERE lead_id = %d
               AND type NOT IN ('disengagement', 'signal_update', 'trigger_fired', 'followup_sent')
               AND created_at >= DATE_SUB(%s, INTERVAL %d DAY)
             ORDER BY created_at ASC",
            $leadId,
            current_time('mysql'),
            self::LOOKBACK_DAYS
        )) ?: [];

        if (!$rows) {
            return [0, 0];
        }

        $timestamps = array_map(fn($r) => strtotime($r->created_at), $rows);
        $now = current_time('timestamp');
        $windowSeconds = self::WINDOW_HOURS * HOUR_IN_SECONDS;

        $recent = count(array_filter($timestamps, fn($t) => $t >= $now - $windowSeconds));

        // Pic : fenêtre glissante de 72 h la plus dense.
        $peak = 0;
        $count = count($timestamps);
        $j = 0;
        for ($i = 0; $i < $count; $i++) {
            while ($timestamps[$i] - $timestamps[$j] > $windowSeconds) {
                $j++;
            }
            $peak = max($peak, $i - $j + 1);
        }

        return [$peak, $recent];
    }
}

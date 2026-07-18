<?php

namespace BemLeadAi\Learning;

use BemLeadAi\Core\Options;

defined('ABSPATH') || exit;

/**
 * Bandit epsilon-greedy sur les variantes de message de relance.
 *
 * Apprentissage continu : chaque relance envoyée est une impression ; une
 * conversion est comptée si le lead redevient actif (nouvel événement
 * entrant) dans la fenêtre configurée (72 h par défaut).
 *
 * Avec peu de volume, le bandit tourne naturellement en mode exploration
 * (choix aléatoire fréquent) sans dégrader l'expérience — la sélection ne
 * devient discriminante qu'avec des données suffisantes. Passage à Thompson
 * sampling à envisager quand le volume de conversions le justifie.
 */
final class VariantBandit
{
    public function pick(string $triggerKey): ?object
    {
        global $wpdb;
        $p = $wpdb->prefix;
        $variants = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$p}bem_message_variants WHERE trigger_key = %s AND actif = 1",
            $triggerKey
        )) ?: [];
        if (!$variants) {
            return null;
        }

        $epsilon = (float) Options::get('bandit_epsilon');
        if (count($variants) === 1 || mt_rand() / mt_getrandmax() < $epsilon) {
            return $variants[array_rand($variants)]; // exploration
        }

        // Exploitation : meilleur taux de conversion lissé (Laplace).
        usort($variants, function ($a, $b) {
            $rateA = ($a->conversions + 1) / ($a->impressions + 2);
            $rateB = ($b->conversions + 1) / ($b->impressions + 2);
            return $rateB <=> $rateA;
        });
        return $variants[0];
    }

    public function recordImpression(int $variantId): void
    {
        global $wpdb;
        $p = $wpdb->prefix;
        $wpdb->query($wpdb->prepare(
            "UPDATE {$p}bem_message_variants SET impressions = impressions + 1 WHERE id = %d",
            $variantId
        ));
    }

    /**
     * Cron horaire : pour chaque relance envoyée dont la fenêtre de conversion
     * vient d'expirer, vérifie si le lead a eu une activité entrante après
     * l'envoi → crédite la variante.
     */
    public function sweepConversions(): void
    {
        global $wpdb;
        $p = $wpdb->prefix;
        $windowHours = max(1, (int) Options::get('bandit_conversion_window_hours'));
        $now = current_time('mysql');

        $followups = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$p}bem_events
             WHERE type = 'followup_sent'
               AND created_at <= DATE_SUB(%s, INTERVAL %d HOUR)
               AND created_at > DATE_SUB(%s, INTERVAL %d HOUR)",
            $now,
            $windowHours,
            $now,
            $windowHours * 2
        )) ?: [];

        foreach ($followups as $followup) {
            $payload = json_decode((string) $followup->payload, true) ?: [];
            if (empty($payload['variant_id']) || !empty($payload['conversion_counted'])) {
                continue;
            }

            $activity = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$p}bem_events
                 WHERE lead_id = %d
                   AND created_at > %s
                   AND created_at <= DATE_ADD(%s, INTERVAL %d HOUR)
                   AND type IN ('page_view','chat_message','cta_click','brochure_download','financing_simulated','return_visit','email_captured')",
                (int) $followup->lead_id,
                $followup->created_at,
                $followup->created_at,
                $windowHours
            ));

            if ($activity > 0) {
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$p}bem_message_variants SET conversions = conversions + 1 WHERE id = %d",
                    (int) $payload['variant_id']
                ));
            }

            $payload['conversion_counted'] = true;
            $wpdb->update("{$p}bem_events", ['payload' => wp_json_encode($payload)], ['id' => (int) $followup->id]);
        }
    }
}

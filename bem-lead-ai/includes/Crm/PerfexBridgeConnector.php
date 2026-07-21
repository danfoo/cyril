<?php

namespace BemLeadAi\Crm;

use BemLeadAi\Core\Options;
use BemLeadAi\Scoring\ScoringEngine;

defined('ABSPATH') || exit;

/**
 * Connecteur « Pont School IA » — envoie le lead au module Perfex maison
 * (school_ia_bridge) via son point d'entrée public, authentifié par un secret
 * partagé. Aucune dépendance au module REST API payant de Perfex.
 */
final class PerfexBridgeConnector implements CrmConnectorInterface
{
    /** Résultat du dernier envoi (diagnostic). */
    public int $lastCode = 0;
    public string $lastError = '';

    public function isConfigured(): bool
    {
        return Options::get('perfex_url') !== '' && Options::hasSecret('perfex_bridge_secret');
    }

    public function upsertLead(object $lead): void
    {
        $base = rtrim((string) Options::get('perfex_url'), '/');
        $band = ScoringEngine::band((float) $lead->score_final);

        $payload = [
            'external_id' => (string) $lead->id,
            'source_site' => home_url(),
            'name'        => trim(($lead->prenom ?: 'Lead') . ' #' . $lead->id),
            'email'       => (string) ($lead->email ?? ''),
            'phone'       => (string) ($lead->phone ?? ''),
            'formation'   => (string) ($lead->formation_interet ?? ''),
            'score'       => (float) $lead->score_final,
            'band'        => $band,
            'description' => sprintf(
                "Score: %s/100 (%s)\nFormation d'intérêt: %s\nCanaux: %s\nDernière activité: %s\nProvenance: %s",
                $lead->score_final,
                str_replace('_', ' ', $band),
                $lead->formation_interet ?: '—',
                $lead->channels,
                $lead->last_seen,
                defined('BEM_LEAD_AI_BRAND') ? BEM_LEAD_AI_BRAND : 'School IA'
            ),
        ];

        $response = wp_remote_post($base . '/school_ia_bridge/api/receive', [
            'timeout' => 30,
            'headers' => [
                'X-SIA-Secret' => (string) Options::get('perfex_bridge_secret'),
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            $this->lastCode = 0;
            $this->lastError = $response->get_error_message();
            error_log('[bem-lead-ai] Pont Perfex injoignable: ' . $this->lastError);
            return;
        }

        $this->lastCode = (int) wp_remote_retrieve_response_code($response);
        if ($this->lastCode < 200 || $this->lastCode >= 300) {
            $this->lastError = wp_strip_all_tags((string) wp_remote_retrieve_body($response));
            error_log('[bem-lead-ai] Pont Perfex a répondu ' . $this->lastCode . ': ' . $this->lastError);
        } else {
            $this->lastError = '';
        }
    }
}

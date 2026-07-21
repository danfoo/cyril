<?php

namespace BemLeadAi\Crm;

use BemLeadAi\Core\Options;
use BemLeadAi\Leads\LeadRepository;
use BemLeadAi\Scoring\ScoringEngine;

defined('ABSPATH') || exit;

/**
 * Connecteur Perfex CRM (module API REST) — cible prioritaire.
 */
final class PerfexConnector implements CrmConnectorInterface
{
    public function isConfigured(): bool
    {
        return Options::get('perfex_url') !== '' && Options::hasSecret('perfex_api_key');
    }

    public function upsertLead(object $lead): void
    {
        $base = rtrim((string) Options::get('perfex_url'), '/');
        $band = ScoringEngine::band((float) $lead->score_final);

        $payload = [
            'name' => trim(($lead->prenom ?: 'Lead') . ' #' . $lead->id),
            'email' => (string) ($lead->email ?? ''),
            'phonenumber' => (string) ($lead->phone ?? ''),
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

        // L'API Perfex attend des ID numériques pour source/statut ; on ne les
        // envoie que s'ils sont renseignés (sinon Perfex applique ses défauts).
        $source = (int) Options::get('perfex_lead_source');
        $status = (int) Options::get('perfex_lead_status');
        if ($source > 0) {
            $payload['source'] = $source;
        }
        if ($status > 0) {
            $payload['status'] = $status;
        }

        $isUpdate = !empty($lead->crm_id_perfex);
        $url = $base . '/api/leads' . ($isUpdate ? '/' . rawurlencode((string) $lead->crm_id_perfex) : '');

        $response = wp_remote_request($url, [
            'method' => $isUpdate ? 'PUT' : 'POST',
            'timeout' => 30,
            'headers' => [
                'authtoken' => (string) Options::get('perfex_api_key'),
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            error_log('[bem-lead-ai] Perfex injoignable: ' . $response->get_error_message());
            return;
        }

        if (!$isUpdate) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $remoteId = $body['insert_id'] ?? $body['id'] ?? $body['data']['id'] ?? null;
            if ($remoteId) {
                (new LeadRepository())->update((int) $lead->id, ['crm_id_perfex' => (string) $remoteId]);
            }
        }
    }

    /** Pousse un résumé de conversation comme note sur le lead Perfex. */
    public function addNote(object $lead, string $note): void
    {
        if (empty($lead->crm_id_perfex)) {
            $this->upsertLead($lead);
            $lead = (new LeadRepository())->findById((int) $lead->id);
            if (!$lead || empty($lead->crm_id_perfex)) {
                return;
            }
        }
        $base = rtrim((string) Options::get('perfex_url'), '/');
        wp_remote_post($base . '/api/leads/' . rawurlencode((string) $lead->crm_id_perfex) . '/notes', [
            'timeout' => 30,
            'headers' => [
                'authtoken' => (string) Options::get('perfex_api_key'),
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode(['description' => $note]),
        ]);
    }
}

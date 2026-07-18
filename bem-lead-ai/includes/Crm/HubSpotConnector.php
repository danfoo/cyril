<?php

namespace BemLeadAi\Crm;

use BemLeadAi\Core\Options;
use BemLeadAi\Leads\LeadRepository;

defined('ABSPATH') || exit;

/**
 * Connecteur HubSpot (optionnel), activable sans toucher au moteur de triggers.
 */
final class HubSpotConnector implements CrmConnectorInterface
{
    public function isConfigured(): bool
    {
        return Options::hasSecret('hubspot_api_key');
    }

    public function upsertLead(object $lead): void
    {
        if (empty($lead->email)) {
            return; // HubSpot déduplique par email — sans email, pas de contact fiable.
        }

        $properties = [
            'email' => (string) $lead->email,
            'firstname' => (string) ($lead->prenom ?? ''),
            'phone' => (string) ($lead->phone ?? ''),
            'hs_lead_status' => 'NEW',
        ];

        $isUpdate = !empty($lead->crm_id_hubspot);
        $url = $isUpdate
            ? 'https://api.hubapi.com/crm/v3/objects/contacts/' . rawurlencode((string) $lead->crm_id_hubspot)
            : 'https://api.hubapi.com/crm/v3/objects/contacts';

        $response = wp_remote_request($url, [
            'method' => $isUpdate ? 'PATCH' : 'POST',
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . Options::get('hubspot_api_key'),
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode(['properties' => $properties]),
        ]);

        if (is_wp_error($response)) {
            error_log('[bem-lead-ai] HubSpot injoignable: ' . $response->get_error_message());
            return;
        }
        if (!$isUpdate) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (!empty($body['id'])) {
                (new LeadRepository())->update((int) $lead->id, ['crm_id_hubspot' => (string) $body['id']]);
            }
        }
    }
}

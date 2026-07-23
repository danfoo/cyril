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

        $prenom = trim((string) ($lead->prenom ?? ''));
        $payload = [
            'external_id' => (string) $lead->id,
            'source_site' => home_url(),
            // Nom réel du prospect si connu, sinon « Anonyme » — jamais « Lead »,
            // qui prêtait à confusion avec d'anciens leads de test.
            'name'        => ($prenom !== '' ? $prenom : 'Anonyme') . ' #' . $lead->id,
            'email'       => (string) ($lead->email ?? ''),
            'phone'       => (string) ($lead->phone ?? ''),
            'formation'   => (string) ($lead->formation_interet ?? ''),
            'score'       => (float) $lead->score_final,
            'band'        => $band,
            // Vraie date d'arrivée du lead (première visite), pour que Perfex
            // affiche « reçu le » à la bonne date et non à l'heure de synchro.
            'received_at' => (string) ($lead->first_seen ?? ''),
            'last_activity' => (string) ($lead->last_seen ?? ''),
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

        // Envoi en GET : la protection CSRF de Perfex ne bloque que le POST
        // (erreur 419). Le secret voyage dans l'en-tête ET en paramètre (repli
        // si l'hébergeur filtre les en-têtes personnalisés).
        $secret = (string) Options::get('perfex_bridge_secret');
        // http_build_query encode correctement clés ET valeurs (retours à la
        // ligne, accents…), ce que add_query_arg ne fait pas → URL valide.
        $query = http_build_query(array_merge($payload, ['secret' => $secret]));
        $url = $base . '/school_ia_bridge/api/receive?' . $query;

        $response = wp_remote_get($url, [
            'timeout' => 30,
            'headers' => [
                'X-SIA-Secret' => $secret,
                'Accept'       => 'application/json',
            ],
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

    /**
     * Envoie un message de la conversation IA vers la fiche lead Perfex.
     * Le lead doit déjà exister côté Perfex (envoyé par upsertLead au préalable) ;
     * il est retrouvé par external_id + source_site.
     *
     * Le contenu est tronqué : le transport se fait en GET (la protection CSRF
     * de Perfex ne bloque que le POST), et une réponse IA très longue ferait
     * dépasser la longueur d'URL acceptée par certains hébergeurs.
     */
    public function sendChatMessage(int $leadId, int $messageId, string $role, string $content, string $canal = 'web'): void
    {
        $base = rtrim((string) Options::get('perfex_url'), '/');
        $secret = (string) Options::get('perfex_bridge_secret');

        $content = mb_substr($content, 0, 2500);

        $payload = [
            'external_id'         => (string) $leadId,
            'source_site'         => home_url(),
            'external_message_id' => (string) $messageId,
            'role'                => $role,
            'content'             => $content,
            'canal'               => $canal,
        ];
        $query = http_build_query(array_merge($payload, ['secret' => $secret]));
        $url = $base . '/school_ia_bridge/api/receive_message?' . $query;

        $response = wp_remote_get($url, [
            'timeout' => 20,
            'headers' => [
                'X-SIA-Secret' => $secret,
                'Accept'       => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            $this->lastCode = 0;
            $this->lastError = $response->get_error_message();
            error_log('[bem-lead-ai] Pont Perfex (message) injoignable: ' . $this->lastError);
            return;
        }

        $this->lastCode = (int) wp_remote_retrieve_response_code($response);
        if ($this->lastCode < 200 || $this->lastCode >= 300) {
            $this->lastError = wp_strip_all_tags((string) wp_remote_retrieve_body($response));
            error_log('[bem-lead-ai] Pont Perfex (message) a répondu ' . $this->lastCode . ': ' . $this->lastError);
        } else {
            $this->lastError = '';
        }
    }

    /**
     * Envoie une mention de concurrent (veille concurrentielle) vers la fiche
     * lead Perfex. Le lead doit déjà exister côté Perfex.
     */
    public function sendCompetitorMention(int $leadId, int $mentionId, string $name, string $context = ''): void
    {
        $base = rtrim((string) Options::get('perfex_url'), '/');
        $secret = (string) Options::get('perfex_bridge_secret');

        $payload = [
            'external_id'  => (string) $leadId,
            'source_site'  => home_url(),
            'kind'         => 'competitor',
            'external_ref' => 'm' . $mentionId,
            'name'         => $name,
            'context'      => mb_substr($context, 0, 1500),
        ];
        // On passe par « receive_message » (chemin non filtré par l'hébergeur)
        // avec kind=competitor : « receive_competitor » était intercepté et
        // renvoyait un faux 200 sans jamais atteindre le code.
        $query = http_build_query(array_merge($payload, ['secret' => $secret]));
        $url = $base . '/school_ia_bridge/api/receive_message?' . $query;

        $response = wp_remote_get($url, [
            'timeout' => 20,
            'headers' => [
                'X-SIA-Secret' => $secret,
                'Accept'       => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            $this->lastCode = 0;
            $this->lastError = $response->get_error_message();
            error_log('[bem-lead-ai] Pont Perfex (concurrent) injoignable: ' . $this->lastError);
            return;
        }

        $this->lastCode = (int) wp_remote_retrieve_response_code($response);
        if ($this->lastCode < 200 || $this->lastCode >= 300) {
            $this->lastError = wp_strip_all_tags((string) wp_remote_retrieve_body($response));
            error_log('[bem-lead-ai] Pont Perfex (concurrent) a répondu ' . $this->lastCode . ': ' . $this->lastError);
        } else {
            $this->lastError = '';
        }
    }
}

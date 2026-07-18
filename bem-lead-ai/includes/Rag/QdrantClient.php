<?php

namespace BemLeadAi\Rag;

use BemLeadAi\Core\Options;
use WP_Error;

defined('ABSPATH') || exit;

/**
 * Client REST Qdrant Cloud (vector store managé — pas d'infra à maintenir).
 */
final class QdrantClient
{
    private function request(string $method, string $path, ?array $body = null): array|WP_Error
    {
        $baseUrl = rtrim((string) Options::get('qdrant_url'), '/');
        if ($baseUrl === '') {
            return new WP_Error('bem_no_qdrant', __('URL Qdrant non configurée.', 'bem-lead-ai'));
        }
        $args = [
            'method' => $method,
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'api-key' => (string) Options::get('qdrant_api_key'),
            ],
        ];
        if ($body !== null) {
            $args['body'] = wp_json_encode($body);
        }
        $response = wp_remote_request($baseUrl . $path, $args);
        if (is_wp_error($response)) {
            return $response;
        }
        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if ($code < 200 || $code >= 300) {
            return new WP_Error('bem_qdrant_error', sprintf('Qdrant HTTP %d: %s', $code, wp_remote_retrieve_body($response)));
        }
        return is_array($data) ? $data : [];
    }

    private function collection(): string
    {
        return (string) Options::get('qdrant_collection');
    }

    public function ensureCollection(int $vectorSize): void
    {
        $existing = $this->request('GET', '/collections/' . $this->collection());
        if (!is_wp_error($existing) && !empty($existing['result'])) {
            return;
        }
        $this->request('PUT', '/collections/' . $this->collection(), [
            'vectors' => ['size' => $vectorSize, 'distance' => 'Cosine'],
        ]);
    }

    /** @param array $points [['id' => uuid, 'vector' => float[], 'payload' => array], ...] */
    public function upsert(array $points): array|WP_Error
    {
        return $this->request('PUT', '/collections/' . $this->collection() . '/points?wait=true', ['points' => $points]);
    }

    public function deletePoints(array $ids): void
    {
        if ($ids) {
            $this->request('POST', '/collections/' . $this->collection() . '/points/delete?wait=true', ['points' => array_values($ids)]);
        }
    }

    /**
     * Recherche sémantique filtrée par base de connaissance
     * (formations pour les prospects, onboarding pour les inscrits).
     *
     * @return array [['score' => float, 'payload' => array], ...]
     */
    public function search(array $vector, string $kb, int $limit = 5): array
    {
        $result = $this->request('POST', '/collections/' . $this->collection() . '/points/search', [
            'vector' => $vector,
            'limit' => $limit,
            'with_payload' => true,
            'filter' => ['must' => [['key' => 'kb', 'match' => ['value' => $kb]]]],
        ]);
        if (is_wp_error($result)) {
            error_log('[bem-lead-ai] Recherche Qdrant échouée: ' . $result->get_error_message());
            return [];
        }
        return $result['result'] ?? [];
    }
}

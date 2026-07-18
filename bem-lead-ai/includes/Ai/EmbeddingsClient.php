<?php

namespace BemLeadAi\Ai;

use BemLeadAi\Core\Options;
use WP_Error;

defined('ABSPATH') || exit;

/**
 * Embeddings pluggables : Voyage AI (recommandé avec Claude) ou OpenAI.
 */
final class EmbeddingsClient
{
    /**
     * @param string[] $texts
     * @return array|WP_Error Liste de vecteurs (float[][]).
     */
    public function embed(array $texts, string $inputType = 'document'): array|WP_Error
    {
        $provider = (string) Options::get('embeddings_provider');
        $apiKey = (string) Options::get('embeddings_api_key');
        $model = (string) Options::get('embeddings_model');
        if ($apiKey === '') {
            return new WP_Error('bem_no_embed_key', __('Clé API embeddings non configurée.', 'bem-lead-ai'));
        }

        if ($provider === 'openai') {
            $url = 'https://api.openai.com/v1/embeddings';
            $body = ['model' => $model ?: 'text-embedding-3-small', 'input' => $texts];
            $headers = ['Authorization' => 'Bearer ' . $apiKey, 'Content-Type' => 'application/json'];
        } else {
            $url = 'https://api.voyageai.com/v1/embeddings';
            $body = ['model' => $model ?: 'voyage-3.5', 'input' => $texts, 'input_type' => $inputType === 'query' ? 'query' : 'document'];
            $headers = ['Authorization' => 'Bearer ' . $apiKey, 'Content-Type' => 'application/json'];
        }

        $response = wp_remote_post($url, [
            'timeout' => 60,
            'headers' => $headers,
            'body' => wp_json_encode($body),
        ]);
        if (is_wp_error($response)) {
            return $response;
        }
        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if ($code !== 200 || empty($data['data'])) {
            return new WP_Error('bem_embed_error', sprintf('Embeddings HTTP %d: %s', $code, wp_remote_retrieve_body($response)));
        }
        usort($data['data'], fn($a, $b) => ($a['index'] ?? 0) <=> ($b['index'] ?? 0));
        return array_map(fn($d) => $d['embedding'], $data['data']);
    }

    public function embedOne(string $text, string $inputType = 'query'): array|WP_Error
    {
        $result = $this->embed([$text], $inputType);
        return is_wp_error($result) ? $result : $result[0];
    }
}

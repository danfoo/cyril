<?php

namespace BemLeadAi\Ai;

use BemLeadAi\Core\Options;
use WP_Error;

defined('ABSPATH') || exit;

/**
 * Client minimal de l'API Anthropic Messages.
 * Sonnet pour la conversation/les résumés, Haiku pour la classification
 * haute fréquence — sépare qualité conversationnelle et coût.
 */
final class ClaudeClient
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';

    /**
     * @param array $messages [['role' => 'user'|'assistant', 'content' => string], ...]
     * @return string|WP_Error Texte de la réponse.
     */
    public function complete(string $model, string $system, array $messages, int $maxTokens = 1024, float $temperature = 0.4): string|WP_Error
    {
        $apiKey = (string) Options::get('anthropic_api_key');
        if ($apiKey === '') {
            return new WP_Error('bem_no_api_key', __('Clé API Anthropic non configurée.', 'bem-lead-ai'));
        }

        $response = wp_remote_post(self::ENDPOINT, [
            'timeout' => 60,
            'headers' => [
                'x-api-key' => $apiKey,
                'anthropic-version' => self::API_VERSION,
                'content-type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'model' => $model,
                'max_tokens' => $maxTokens,
                'temperature' => $temperature,
                'system' => $system,
                'messages' => $messages,
            ]),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if ($code !== 200 || !is_array($body)) {
            return new WP_Error('bem_claude_error', sprintf('Claude API HTTP %d: %s', $code, wp_remote_retrieve_body($response)));
        }

        $text = '';
        foreach ($body['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'];
            }
        }
        return $text;
    }

    /**
     * Appel avec sortie JSON stricte : préremplit la réponse de l'assistant
     * avec "{" pour forcer un objet JSON, puis parse.
     *
     * @return array|WP_Error
     */
    public function completeJson(string $model, string $system, array $messages, int $maxTokens = 1024): array|WP_Error
    {
        $messages[] = ['role' => 'assistant', 'content' => '{'];
        $raw = $this->complete($model, $system, $messages, $maxTokens, 0.0);
        if (is_wp_error($raw)) {
            return $raw;
        }
        $json = '{' . $raw;
        // Coupe tout ce qui suit l'objet JSON (sécurité parse).
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            if (preg_match('/\{.*\}/s', $json, $m)) {
                $decoded = json_decode($m[0], true);
            }
        }
        return is_array($decoded) ? $decoded : new WP_Error('bem_claude_json', 'Sortie JSON invalide: ' . substr($json, 0, 500));
    }
}

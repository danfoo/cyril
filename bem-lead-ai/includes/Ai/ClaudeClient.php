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
     * @param string|array $system Chaîne simple, ou tableau de blocs
     *   ['type'=>'text','text'=>..., 'cache_control'=>['type'=>'ephemeral']]
     *   pour mettre en cache un préfixe volumineux (catalogue de formations).
     * @param array $messages [['role' => 'user'|'assistant', 'content' => string], ...]
     * @return string|WP_Error Texte de la réponse.
     */
    /**
     * Note : on N'ENVOIE PAS `temperature`. Les modèles récents (Claude
     * Sonnet 5, Opus 4.8/4.7, Fable 5) rejettent ce paramètre avec une erreur
     * 400 ; l'omettre fonctionne sur TOUS les modèles.
     */
    public function complete(string $model, string|array $system, array $messages, int $maxTokens = 1024, ?float $temperature = null): string|WP_Error
    {
        $apiKey = (string) Options::get('anthropic_api_key');
        if ($apiKey === '') {
            return new WP_Error('bem_no_api_key', __('Clé API Anthropic non configurée.', 'bem-lead-ai'));
        }

        $payload = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'system' => $system,
            'messages' => $messages,
        ];

        $response = wp_remote_post(self::ENDPOINT, [
            'timeout' => 60,
            'headers' => [
                'x-api-key' => $apiKey,
                'anthropic-version' => self::API_VERSION,
                'content-type' => 'application/json',
            ],
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('bem_claude_network', 'Connexion à l\'API Claude impossible : ' . $response->get_error_message());
        }
        $code = wp_remote_retrieve_response_code($response);
        $rawBody = wp_remote_retrieve_body($response);
        $body = json_decode($rawBody, true);
        if ($code !== 200 || !is_array($body)) {
            $detail = is_array($body) && isset($body['error']['message']) ? $body['error']['message'] : $rawBody;
            return new WP_Error('bem_claude_error', sprintf('Claude API HTTP %d — %s', $code, $detail));
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
     * Appel avec sortie JSON. On demande le JSON par le prompt système (pas de
     * préremplissage assistant, non supporté par Sonnet 5 / Opus 4.8), puis on
     * extrait l'objet JSON de la réponse.
     *
     * @return array|WP_Error
     */
    public function completeJson(string $model, string $system, array $messages, int $maxTokens = 1024): array|WP_Error
    {
        $raw = $this->complete($model, $system, $messages, $maxTokens);
        if (is_wp_error($raw)) {
            return $raw;
        }
        $decoded = json_decode(trim($raw), true);
        if (!is_array($decoded) && preg_match('/\{.*\}/s', $raw, $m)) {
            $decoded = json_decode($m[0], true);
        }
        return is_array($decoded) ? $decoded : new WP_Error('bem_claude_json', 'Sortie JSON invalide: ' . substr($raw, 0, 500));
    }
}

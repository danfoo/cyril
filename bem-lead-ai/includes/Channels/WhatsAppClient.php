<?php

namespace BemLeadAi\Channels;

use BemLeadAi\Core\Options;

defined('ABSPATH') || exit;

/**
 * Envoi de messages via Meta WhatsApp Cloud API.
 *
 * Rappel conformité WhatsApp Business : un message texte libre n'est
 * accepté que dans la fenêtre de 24 h suivant le dernier message entrant
 * de l'utilisateur ; au-delà, seuls des templates approuvés par Meta
 * peuvent être envoyés.
 */
final class WhatsAppClient
{
    private const GRAPH_URL = 'https://graph.facebook.com/v19.0/';

    public function sendText(string $waid, string $message): bool
    {
        $phoneNumberId = (string) Options::get('whatsapp_phone_number_id');
        $token = (string) Options::get('whatsapp_token');
        if ($phoneNumberId === '' || $token === '') {
            return false;
        }

        $response = wp_remote_post(self::GRAPH_URL . rawurlencode($phoneNumberId) . '/messages', [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'messaging_product' => 'whatsapp',
                'to' => $waid,
                'type' => 'text',
                'text' => ['body' => $message],
            ]),
        ]);

        if (is_wp_error($response)) {
            error_log('[bem-lead-ai] Envoi WhatsApp échoué: ' . $response->get_error_message());
            return false;
        }
        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            error_log('[bem-lead-ai] Envoi WhatsApp HTTP ' . $code . ': ' . wp_remote_retrieve_body($response));
            return false;
        }
        return true;
    }
}

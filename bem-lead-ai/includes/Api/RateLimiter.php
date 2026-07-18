<?php

namespace BemLeadAi\Api;

use BemLeadAi\Core\Options;

defined('ABSPATH') || exit;

/**
 * Rate limiting simple par clé (IP + session) sur fenêtre glissante d'une
 * minute — maîtrise des coûts LLM et protection contre les abus.
 */
final class RateLimiter
{
    public static function allow(string $bucket, string $key, ?int $limit = null): bool
    {
        $limit ??= max(1, (int) Options::get('rate_limit_per_minute'));
        $transientKey = 'bem_rl_' . md5($bucket . '|' . $key . '|' . gmdate('YmdHi'));
        $count = (int) get_transient($transientKey);
        if ($count >= $limit) {
            return false;
        }
        set_transient($transientKey, $count + 1, 2 * MINUTE_IN_SECONDS);
        return true;
    }

    public static function clientKey(\WP_REST_Request $request): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $session = (string) $request->get_param('session_id');
        return $ip . '|' . $session;
    }
}

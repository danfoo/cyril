<?php

namespace BemLeadAi\Core;

defined('ABSPATH') || exit;

/**
 * Accès centralisé aux réglages. Les clés API sont chiffrées au repos
 * (libsodium, clé dérivée des salts WordPress) — jamais stockées en clair.
 */
final class Options
{
    private const OPTION = 'bem_lead_ai_settings';

    /** Clés considérées comme secrètes → chiffrées au stockage. */
    public const SECRET_KEYS = [
        'anthropic_api_key',
        'perfex_api_key',
        'hubspot_api_key',
        'crm_webhook_secret',
    ];

    /**
     * Modèles Claude proposés dans l'admin, par usage.
     * Chat/résumés : qualité conversationnelle. Classification : haute
     * fréquence, on privilégie un modèle léger.
     */
    public static function chatModels(): array
    {
        return [
            'claude-sonnet-5' => 'Claude Sonnet 5 — équilibré (recommandé)',
            'claude-opus-4-8' => 'Claude Opus 4.8 — le plus capable',
            'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5 — le plus rapide/économique',
        ];
    }

    public static function classifierModels(): array
    {
        return [
            'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5 — rapide/économique (recommandé)',
            'claude-sonnet-5' => 'Claude Sonnet 5 — plus fin',
        ];
    }

    /** Lien vers la console Anthropic pour créer/gérer la clé API. */
    public const ANTHROPIC_CONSOLE_URL = 'https://console.anthropic.com/settings/keys';

    public static function defaults(): array
    {
        return [
            // IA
            'anthropic_api_key' => '',
            'chat_model' => 'claude-sonnet-5',
            'classifier_model' => 'claude-haiku-4-5-20251001',
            // Base de connaissance (catalogue en contexte, mis en cache LLM)
            'indexed_post_types' => 'formation,page',
            'onboarding_category' => 'onboarding',
            'kb_cache_ttl' => '1h', // 5m | 1h — durée de vie du cache de préfixe LLM
            'kb_max_chars_per_post' => 4000,
            // Scoring — logique marketing
            'score_decay_half_life_days' => 7,
            'score_blend_intent_weight' => 0.55, // l'intention conversationnelle pèse plus que le comportement
            'threshold_warm' => 30,
            'threshold_hot' => 60,
            'threshold_very_hot' => 80,
            'disengagement_drop_ratio' => 0.7,
            'disengagement_min_score' => 30,
            // Notifications
            'admissions_email' => get_option('admin_email'),
            'slack_webhook_url' => '',
            // WhatsApp — passerelle "click-to-chat" (pas d'API Business)
            'whatsapp_enabled' => 1,
            'whatsapp_cta_label' => 'Continuer sur WhatsApp',
            'whatsapp_numbers' => "Admissions BEM|221770000000|\n", // Label|numéro|formation(optionnel), une ligne par numéro
            'whatsapp_prefill' => "Bonjour, je viens du site de BEM Dakar. Je m'intéresse à {formation} et j'aimerais en savoir plus.",
            // CRM
            'perfex_url' => '',
            'perfex_api_key' => '',
            'hubspot_api_key' => '',
            'crm_webhook_secret' => '',
            // Bandit
            'bandit_epsilon' => 0.3,
            'bandit_conversion_window_hours' => 72,
            // Widget — contenu
            'widget_enabled' => 1,
            'widget_title' => 'Conseiller d\'orientation BEM',
            'widget_subtitle' => 'Réponses en quelques secondes',
            'widget_greeting' => 'Bonjour 👋 Je suis le conseiller d\'orientation virtuel de BEM Dakar. Posez-moi vos questions sur nos formations, les admissions ou le financement.',
            'rate_limit_per_minute' => 20,
            // Widget — design personnalisable
            'widget_primary_color' => '#0b3d91',
            'widget_accent_color' => '#e6b800',
            'widget_bubble_user_color' => '#0b3d91',
            'widget_avatar_url' => '',
            'widget_launcher_icon' => '💬',
            'widget_position' => 'right', // right | left
            'widget_corner_radius' => 20, // px, coins du panneau et des bulles
            'widget_theme' => 'light', // light | dark — apparence de la zone de conversation
        ];
    }

    public static function ensureDefaults(): void
    {
        $current = get_option(self::OPTION, []);
        if (!is_array($current)) {
            $current = [];
        }
        update_option(self::OPTION, array_merge(self::defaults(), $current));
    }

    public static function all(): array
    {
        $stored = get_option(self::OPTION, []);
        return array_merge(self::defaults(), is_array($stored) ? $stored : []);
    }

    public static function get(string $key): mixed
    {
        $all = self::all();
        $value = $all[$key] ?? null;
        if (in_array($key, self::SECRET_KEYS, true) && is_string($value) && $value !== '') {
            return self::decrypt($value);
        }
        return $value;
    }

    public static function update(array $values): void
    {
        $current = self::all();
        foreach ($values as $key => $value) {
            if (in_array($key, self::SECRET_KEYS, true) && is_string($value)) {
                if ($value === '') {
                    continue; // champ laissé vide dans le formulaire = conserver la clé existante
                }
                $value = self::encrypt($value);
            }
            $current[$key] = $value;
        }
        update_option(self::OPTION, $current);
    }

    public static function hasSecret(string $key): bool
    {
        $all = self::all();
        return !empty($all[$key]);
    }

    /**
     * Numéros WhatsApp parsés depuis le réglage texte.
     * @return array<int, array{label:string, number:string, formation:string}>
     */
    public static function whatsappNumbers(): array
    {
        $raw = (string) self::get('whatsapp_numbers');
        $numbers = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            $digits = preg_replace('/[^0-9]/', '', $parts[1] ?? '');
            if ($digits === '') {
                continue;
            }
            $numbers[] = [
                'label' => $parts[0] !== '' ? $parts[0] : 'BEM Dakar',
                'number' => $digits,
                'formation' => $parts[2] ?? '',
            ];
        }
        return $numbers;
    }

    private static function encryptionKey(): string
    {
        return sodium_crypto_generichash(wp_salt('auth') . '|bem-lead-ai', '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    private static function encrypt(string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plain, $nonce, self::encryptionKey());
        return 'enc:' . base64_encode($nonce . $cipher);
    }

    private static function decrypt(string $stored): string
    {
        if (!str_starts_with($stored, 'enc:')) {
            return $stored; // rétro-compatibilité valeur en clair
        }
        $raw = base64_decode(substr($stored, 4), true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return '';
        }
        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open($cipher, $nonce, self::encryptionKey());
        return $plain === false ? '' : $plain;
    }
}

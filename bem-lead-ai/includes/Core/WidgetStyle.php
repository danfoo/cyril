<?php

namespace BemLeadAi\Core;

defined('ABSPATH') || exit;

/**
 * Variables CSS du widget dérivées des réglages de design.
 *
 * Source unique de vérité, partagée par :
 *  - l'affichage WordPress (Plugin::enqueueWidget → wp_add_inline_style) ;
 *  - l'embarquement inter-sites (RestController::embedConfig → injecté par embed.js).
 *
 * Sans ça, un site non-WordPress récupérait le widget mais pas ses couleurs
 * (le <style> inline n'était émis que côté WordPress).
 */
final class WidgetStyle
{
    public static function css(): string
    {
        $primary = self::hex((string) Options::get('widget_primary_color'), '#0b3d91');
        $accent = self::hex((string) Options::get('widget_accent_color'), '#e6b800');
        $userBubble = self::hex((string) Options::get('widget_bubble_user_color'), $primary);
        $dark = self::darken($primary, 0.18);
        $radius = max(0, min(28, (int) Options::get('widget_corner_radius')));
        $side = Options::get('widget_position') === 'left' ? 'left' : 'right';
        $otherSide = $side === 'left' ? 'right' : 'left';
        $isDark = Options::get('widget_theme') === 'dark';

        $convBg = $isDark ? '#12151c' : '#f6f7fb';
        $botBubble = $isDark ? '#232733' : '#ffffff';
        $textColor = $isDark ? '#e8eaf0' : '#1f2430';
        $muted = $isDark ? '#9aa3b2' : '#8a93a6';
        $inputBg = $isDark ? '#1a1e27' : '#ffffff';
        $inputBorder = $isDark ? '#2c313d' : '#e2e6ef';

        return ".bem-widget{"
            . "--bem-primary:{$primary};--bem-primary-dark:{$dark};--bem-accent:{$accent};--bem-user:{$userBubble};"
            . "--bem-radius:{$radius}px;--bem-conv-bg:{$convBg};--bem-bot:{$botBubble};--bem-text:{$textColor};"
            . "--bem-muted:{$muted};--bem-input-bg:{$inputBg};--bem-input-border:{$inputBorder};"
            . "{$side}:22px;{$otherSide}:auto;}"
            . ".bem-panel{{$side}:0;{$otherSide}:auto;transform-origin:bottom {$side};}";
    }

    /** Assainit une couleur hexadécimale, avec repli. */
    private static function hex(string $value, string $fallback): string
    {
        if (function_exists('sanitize_hex_color')) {
            return sanitize_hex_color($value) ?: $fallback;
        }
        return preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value) ? $value : $fallback;
    }

    public static function darken(string $hex, float $amount): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6) {
            return '#082a66';
        }
        $rgb = array_map(fn($c) => max(0, (int) round(hexdec($c) * (1 - $amount))), str_split($hex, 2));
        return sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]);
    }
}

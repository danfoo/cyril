<?php

namespace BemLeadAi\Admin;

use BemLeadAi\Core\Options;

defined('ABSPATH') || exit;

/**
 * Marque produit « School IA » (éditeur Maestro Dan). Centralise le nom
 * affiché, l'éditeur et le logo d'administration (personnalisable via un
 * réglage média). Les identifiants techniques restent inchangés.
 */
final class Branding
{
    public static function name(): string
    {
        return defined('BEM_LEAD_AI_BRAND') ? BEM_LEAD_AI_BRAND : 'School IA';
    }

    public static function vendor(): string
    {
        return defined('BEM_LEAD_AI_VENDOR') ? BEM_LEAD_AI_VENDOR : 'Maestro Dan';
    }

    /** Logo personnalisé (réglage média) ou, à défaut, le logo Maestro Dan fourni. */
    public static function logoUrl(): string
    {
        $custom = trim((string) Options::get('brand_logo_url'));
        return $custom !== '' ? $custom : self::defaultLogoUrl();
    }

    /** Logo Maestro Dan livré avec le plugin (colorimétrie pour fond clair). */
    public static function defaultLogoUrl(): string
    {
        $rel = 'assets/images/logo-maestrodan-white.png';
        if (defined('BEM_LEAD_AI_DIR') && is_file(BEM_LEAD_AI_DIR . $rel)) {
            return BEM_LEAD_AI_URL . $rel;
        }
        return '';
    }

    /** Icône du menu (data URI SVG monochrome, teintée par WordPress). */
    public static function menuIcon(): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#a7aaad" '
            . 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
            . '<path d="M22 10 12 5 2 10l10 5 10-5z"/><path d="M6 12v5c0 1 2.5 3 6 3s6-2 6-3v-5"/>'
            . '<line x1="22" y1="10" x2="22" y2="16"/></svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /** Logo + navigation horizontale, en haut des pages d'administration. */
    public static function bar(): void
    {
        $logo = self::logoUrl();
        echo '<div class="bem-brandbar">';
        if ($logo !== '') {
            echo '<img class="bem-brand-logo" src="' . esc_url($logo) . '" alt="' . esc_attr(self::name()) . '">';
        } else {
            echo '<span class="bem-brand-badge">' . Icons::get('cap') . '</span>';
            echo '<span class="bem-brand-txt"><span class="bem-brand-name">' . esc_html(self::name()) . '</span>'
                . '<span class="bem-brand-by">' . esc_html(sprintf(__('par %s', 'bem-lead-ai'), self::vendor())) . '</span></span>';
        }
        echo '</div>';
        self::nav();
    }

    /** Barre de navigation horizontale (onglets des sections du CRM). */
    public static function nav(): void
    {
        $current = isset($_GET['page']) ? sanitize_text_field((string) $_GET['page']) : '';
        $items = [
            'bem-lead-ai'             => __('Tableau de bord', 'bem-lead-ai'),
            'bem-lead-ai-leads'       => __('Leads', 'bem-lead-ai'),
            'bem-lead-ai-inbox'       => __('Inbox conseiller', 'bem-lead-ai'),
            'bem-lead-ai-competitors' => __('Veille concurrentielle', 'bem-lead-ai'),
            'bem-lead-ai-settings'    => __('Réglages', 'bem-lead-ai'),
        ];
        echo '<nav class="bem-topnav"><div class="bem-topnav-inner">';
        foreach ($items as $slug => $label) {
            $active = $current === $slug ? ' is-active' : '';
            echo '<a class="bem-topnav-link' . $active . '" href="' . esc_url(admin_url('admin.php?page=' . $slug)) . '">' . esc_html($label) . '</a>';
        }
        echo '</div></nav>';
    }

    /** Pied de page « VERSION DU CRM x.y.z ». */
    public static function footer(): void
    {
        $version = defined('BEM_LEAD_AI_VERSION') ? BEM_LEAD_AI_VERSION : '';
        echo '<div class="bem-crm-footer">' . esc_html(sprintf(__('Version du CRM %s', 'bem-lead-ai'), $version))
            . ' · ' . esc_html(self::name()) . ' ' . esc_html__('par', 'bem-lead-ai') . ' ' . esc_html(self::vendor()) . '</div>';
    }
}

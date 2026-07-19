<?php

namespace BemLeadAi\License;

use BemLeadAi\Admin\Branding;
use BemLeadAi\Admin\Icons;

defined('ABSPATH') || exit;

/**
 * Écran « Licence » : saisie et activation de la clé, état de l'abonnement
 * (date d'expiration, jours restants), activation/désactivation par site et
 * revalidation manuelle. Communique avec le serveur de licences via
 * LicenseClient. Aucune donnée n'est jamais bloquée : la licence ne pilote
 * que les mises à jour.
 */
final class LicensePage
{
    public const PAGE = 'bem-lead-ai-license';

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden');
        }
        $status = LicenseClient::status();
        $hasKey = LicenseClient::hasKey();
        $active = LicenseClient::isActive();
        $days = LicenseClient::daysLeft();

        echo '<div class="wrap"><h1>' . esc_html(Branding::name()) . ' — ' . esc_html__('Licence', 'bem-lead-ai') . '</h1>';

        foreach (['activated' => __('Licence activée.', 'bem-lead-ai'),
                  'deactivated' => __('Licence désactivée sur ce site.', 'bem-lead-ai'),
                  'refreshed' => __('Statut de licence actualisé.', 'bem-lead-ai')] as $flag => $msg) {
            if (isset($_GET[$flag])) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($msg) . '</p></div>';
            }
        }

        // Carte d'état.
        echo '<div class="bem-license-card bem-license-' . esc_attr($active ? 'ok' : ($hasKey ? 'warn' : 'off')) . '">';
        echo '<div class="bem-license-head">' . Icons::get($active ? 'check-circle' : 'clock', 'bem-ico') . '<div>';
        echo '<div class="bem-license-state">' . esc_html($this->stateLabel($status['state'], $active)) . '</div>';
        if ($status['expires_at'] !== '') {
            $when = mysql2date('d/m/Y', $status['expires_at']);
            if ($active && $days !== null) {
                echo '<div class="bem-license-sub">' . esc_html(sprintf(
                    _n('Abonnement valide — expire le %1$s (%2$d jour restant)', 'Abonnement valide — expire le %1$s (%2$d jours restants)', (int) $days, 'bem-lead-ai'),
                    $when, (int) $days
                )) . '</div>';
            } else {
                echo '<div class="bem-license-sub">' . esc_html(sprintf(__('Expiration : %s', 'bem-lead-ai'), $when)) . '</div>';
            }
        }
        if ($status['message'] !== '') {
            echo '<div class="bem-license-sub">' . esc_html($status['message']) . '</div>';
        }
        echo '</div></div>';

        // Rappel de renouvellement proche.
        if ($active && $days !== null && $days <= 30) {
            echo '<p class="bem-license-renew">' . esc_html(sprintf(__('Votre abonnement expire dans %d jours. Pensez à le renouveler pour continuer à recevoir les mises à jour.', 'bem-lead-ai'), (int) $days)) . '</p>';
        }
        echo '</div>';

        // Formulaire clé.
        $postUrl = esc_url(admin_url('admin-post.php'));
        echo '<form method="post" action="' . $postUrl . '" class="bem-license-form">';
        wp_nonce_field('bem_license_activate');
        echo '<input type="hidden" name="action" value="bem_license_activate">';
        echo '<table class="form-table"><tbody><tr><th scope="row">' . esc_html__('Clé de licence', 'bem-lead-ai') . '</th><td>';
        if ($hasKey) {
            echo '<input type="text" name="license_key" value="" class="regular-text" placeholder="'
                . esc_attr__('●●●● configurée — saisir une nouvelle clé pour la remplacer', 'bem-lead-ai') . '">';
        } else {
            echo '<input type="text" name="license_key" value="" class="regular-text" placeholder="SIA-XXXX-XXXX-XXXX-XXXX">';
        }
        echo '<p class="description">' . esc_html__('Saisissez la clé reçue à l\'achat, puis cliquez sur « Activer ». La clé est verrouillée sur le domaine de ce site.', 'bem-lead-ai') . '</p>';
        echo '</td></tr></tbody></table>';
        submit_button($hasKey ? __('Réactiver / remplacer la clé', 'bem-lead-ai') : __('Activer la licence', 'bem-lead-ai'), 'primary', 'submit', false);
        echo '</form>';

        // Actions secondaires.
        if ($hasKey) {
            echo '<p style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap;">';
            echo '<a class="button" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=bem_license_refresh'), 'bem_license_refresh')) . '">'
                . esc_html__('Revérifier maintenant', 'bem-lead-ai') . '</a>';
            echo '<a class="button" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=bem_license_deactivate'), 'bem_license_deactivate')) . '" '
                . 'onclick="return confirm(\'' . esc_js(__('Désactiver la licence sur ce site ? Un emplacement sera libéré.', 'bem-lead-ai')) . '\');">'
                . esc_html__('Désactiver sur ce site', 'bem-lead-ai') . '</a>';
            echo '</p>';
        }

        echo '<hr><p class="description" style="max-width:760px;">'
            . esc_html__('Sans licence active, School IA continue de fonctionner normalement : seules les mises à jour automatiques sont suspendues. Vos leads, votre CRM et vos réglages ne sont jamais affectés.', 'bem-lead-ai')
            . '</p>';

        echo '</div>';
    }

    private function stateLabel(string $state, bool $active): string
    {
        if ($active) {
            return __('Licence active', 'bem-lead-ai');
        }
        return match ($state) {
            'expired' => __('Abonnement expiré', 'bem-lead-ai'),
            'invalid' => __('Clé invalide ou révoquée', 'bem-lead-ai'),
            default => __('Aucune licence active', 'bem-lead-ai'),
        };
    }

    /* --- Handlers ------------------------------------------------------- */

    public static function handleActivate(): void
    {
        self::guard('bem_license_activate');
        $key = isset($_POST['license_key']) ? sanitize_text_field(wp_unslash((string) $_POST['license_key'])) : '';
        if ($key === '' && LicenseClient::hasKey()) {
            // Champ laissé vide = revalider la clé existante.
            LicenseClient::validate();
        } else {
            LicenseClient::activate($key);
        }
        wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE . '&activated=1'));
        exit;
    }

    public static function handleDeactivate(): void
    {
        self::guard('bem_license_deactivate');
        LicenseClient::deactivate();
        wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE . '&deactivated=1'));
        exit;
    }

    public static function handleRefresh(): void
    {
        self::guard('bem_license_refresh');
        LicenseClient::validate();
        wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE . '&refreshed=1'));
        exit;
    }

    /** Rappel d'activation discret sur les écrans du plugin. */
    public static function notice(): void
    {
        if (!current_user_can('manage_options') || LicenseClient::isActive()) {
            return;
        }
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $onPlugin = $screen && str_contains((string) ($screen->id ?? ''), 'bem-lead-ai');
        if (!$onPlugin) {
            return;
        }
        if (str_contains((string) ($screen->id ?? ''), self::PAGE)) {
            return;
        }
        $url = esc_url(admin_url('admin.php?page=' . self::PAGE));
        $msg = LicenseClient::hasKey()
            ? __('Votre licence School IA n\'est pas active — les mises à jour sont suspendues.', 'bem-lead-ai')
            : __('Activez votre licence School IA pour recevoir les mises à jour.', 'bem-lead-ai');
        echo '<div class="notice notice-warning"><p>' . esc_html($msg)
            . ' <a class="button button-small" href="' . $url . '">' . esc_html__('Gérer la licence', 'bem-lead-ai') . '</a></p></div>';
    }

    private static function guard(string $nonce): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden');
        }
        check_admin_referer($nonce);
    }
}

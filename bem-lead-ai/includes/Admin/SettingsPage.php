<?php

namespace BemLeadAi\Admin;

use BemLeadAi\Core\Options;
use BemLeadAi\Rag\ContentIndexer;

defined('ABSPATH') || exit;

/**
 * Écran de réglages : clés API (chiffrées), modèles, scoring, WhatsApp, CRM.
 * Les champs secrets affichent un placeholder « ●●●● configuré » sans jamais
 * renvoyer la valeur en clair au navigateur.
 */
final class SettingsPage
{
    public function render(): void
    {
        $o = Options::all();
        $lastReindex = get_option('bem_lead_ai_last_reindex', '—');

        echo '<div class="wrap"><h1>' . esc_html__('BEM Lead AI — Réglages', 'bem-lead-ai') . '</h1>';

        if (isset($_GET['saved'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Réglages enregistrés.', 'bem-lead-ai') . '</p></div>';
        }
        if (isset($_GET['reindexed'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Réindexation terminée.', 'bem-lead-ai') . '</p></div>';
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('bem_save_settings');
        echo '<input type="hidden" name="action" value="bem_save_settings">';

        $this->section(__('Intelligence artificielle (Claude)', 'bem-lead-ai'), [
            $this->secret('anthropic_api_key', 'Clé API Anthropic', $o),
            $this->text('chat_model', 'Modèle chat / résumés', $o),
            $this->text('classifier_model', 'Modèle classification (léger)', $o),
        ]);

        $this->section(__('Embeddings & Vector store', 'bem-lead-ai'), [
            $this->select('embeddings_provider', 'Fournisseur embeddings', $o, ['voyage' => 'Voyage AI', 'openai' => 'OpenAI']),
            $this->secret('embeddings_api_key', 'Clé API embeddings', $o),
            $this->text('embeddings_model', 'Modèle embeddings', $o),
            $this->text('qdrant_url', 'URL Qdrant', $o),
            $this->secret('qdrant_api_key', 'Clé API Qdrant', $o),
            $this->text('qdrant_collection', 'Collection Qdrant', $o),
            $this->text('indexed_post_types', 'Types de contenu indexés (csv)', $o),
            $this->text('onboarding_category', 'Catégorie/tag onboarding', $o),
        ]);

        $this->section(__('Scoring (logique marketing)', 'bem-lead-ai'), [
            $this->number('score_decay_half_life_days', 'Demi-vie du score (jours)', $o),
            $this->number('score_blend_intent_weight', 'Poids de l\'intention conversationnelle (0-1)', $o),
            $this->number('threshold_warm', 'Seuil « tiède »', $o),
            $this->number('threshold_hot', 'Seuil « chaud » → CRM', $o),
            $this->number('threshold_very_hot', 'Seuil « très chaud »', $o),
            $this->number('disengagement_drop_ratio', 'Chute d\'activité = désengagement (0-1)', $o),
            $this->number('disengagement_min_score', 'Score min. pour surveiller le désengagement', $o),
        ]);

        $this->section(__('Notifications', 'bem-lead-ai'), [
            $this->text('admissions_email', 'Email équipe admissions', $o),
            $this->text('slack_webhook_url', 'Webhook Slack (optionnel)', $o),
        ]);

        $this->section(__('WhatsApp Business (Meta Cloud API)', 'bem-lead-ai'), [
            $this->checkbox('whatsapp_enabled', 'Activer WhatsApp', $o),
            $this->text('whatsapp_phone_number_id', 'Phone Number ID', $o),
            $this->secret('whatsapp_token', 'Token permanent', $o),
            $this->text('whatsapp_verify_token', 'Verify token (webhook)', $o),
            $this->secret('whatsapp_webhook_secret', 'App secret (signature webhook)', $o),
        ], $this->whatsappHelp());

        $this->section(__('CRM', 'bem-lead-ai'), [
            $this->text('perfex_url', 'URL Perfex', $o),
            $this->secret('perfex_api_key', 'Token API Perfex', $o),
            $this->secret('hubspot_api_key', 'Token API HubSpot (optionnel)', $o),
            $this->secret('crm_webhook_secret', 'Secret webhook retour CRM', $o),
        ], $this->crmHelp());

        $this->section(__('Widget de chat', 'bem-lead-ai'), [
            $this->checkbox('widget_enabled', 'Afficher le widget', $o),
            $this->text('widget_title', 'Titre du widget', $o),
            $this->textarea('widget_greeting', 'Message d\'accueil', $o),
            $this->number('rate_limit_per_minute', 'Limite messages / minute', $o),
        ]);

        $this->section(__('Apprentissage (bandit)', 'bem-lead-ai'), [
            $this->number('bandit_epsilon', 'Taux d\'exploration epsilon (0-1)', $o),
            $this->number('bandit_conversion_window_hours', 'Fenêtre de conversion (heures)', $o),
        ]);

        submit_button(__('Enregistrer les réglages', 'bem-lead-ai'));
        echo '</form>';

        // Réindexation manuelle.
        echo '<hr><h2>' . esc_html__('Indexation du contenu (RAG)', 'bem-lead-ai') . '</h2>';
        echo '<p>' . esc_html__('Dernière réindexation :', 'bem-lead-ai') . ' <code>' . esc_html((string) $lastReindex) . '</code></p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('bem_reindex');
        echo '<input type="hidden" name="action" value="bem_reindex">';
        submit_button(__('Réindexer maintenant', 'bem-lead-ai'), 'secondary');
        echo '</form>';

        echo '<hr><h2>' . esc_html__('Webhooks à configurer côté prestataires', 'bem-lead-ai') . '</h2>';
        echo '<table class="widefat" style="max-width:820px;"><tbody>';
        echo '<tr><th>WhatsApp (Meta)</th><td><code>' . esc_html(rest_url(BEM_LEAD_AI_REST_NS . '/whatsapp-webhook')) . '</code></td></tr>';
        echo '<tr><th>Retour CRM (statut inscrit)</th><td><code>' . esc_html(rest_url(BEM_LEAD_AI_REST_NS . '/crm-status-webhook')) . '</code> <em>(header <code>X-Bem-Secret</code>)</em></td></tr>';
        echo '</tbody></table>';

        echo '</div>';
    }

    private function whatsappHelp(): string
    {
        return __('L\'activation de WhatsApp Business API nécessite une validation Meta (vérification d\'entreprise + templates approuvés) pouvant prendre plusieurs semaines. À lancer tôt, en parallèle du reste.', 'bem-lead-ai');
    }

    private function crmHelp(): string
    {
        return __('Perfex est le CRM prioritaire ; HubSpot est optionnel et s\'active sans reconfigurer les triggers.', 'bem-lead-ai');
    }

    /* --- Rendu des champs --------------------------------------------- */

    private function section(string $title, array $rows, string $help = ''): void
    {
        echo '<h2>' . esc_html($title) . '</h2>';
        if ($help) {
            echo '<p class="description">' . esc_html($help) . '</p>';
        }
        echo '<table class="form-table"><tbody>' . implode('', $rows) . '</tbody></table>';
    }

    private function row(string $label, string $control): string
    {
        return '<tr><th scope="row">' . esc_html($label) . '</th><td>' . $control . '</td></tr>';
    }

    private function text(string $key, string $label, array $o): string
    {
        return $this->row($label, '<input type="text" name="s[' . esc_attr($key) . ']" value="' . esc_attr((string) ($o[$key] ?? '')) . '" class="regular-text">');
    }

    private function textarea(string $key, string $label, array $o): string
    {
        return $this->row($label, '<textarea name="s[' . esc_attr($key) . ']" rows="3" class="large-text">' . esc_textarea((string) ($o[$key] ?? '')) . '</textarea>');
    }

    private function number(string $key, string $label, array $o): string
    {
        return $this->row($label, '<input type="number" step="any" name="s[' . esc_attr($key) . ']" value="' . esc_attr((string) ($o[$key] ?? '')) . '" class="small-text">');
    }

    private function checkbox(string $key, string $label, array $o): string
    {
        return $this->row($label, '<input type="checkbox" name="s[' . esc_attr($key) . ']" value="1" ' . checked(1, (int) ($o[$key] ?? 0), false) . '>');
    }

    private function select(string $key, string $label, array $o, array $choices): string
    {
        $html = '<select name="s[' . esc_attr($key) . ']">';
        foreach ($choices as $value => $text) {
            $html .= '<option value="' . esc_attr($value) . '" ' . selected($o[$key] ?? '', $value, false) . '>' . esc_html($text) . '</option>';
        }
        return $this->row($label, $html . '</select>');
    }

    /** Champ secret : ne renvoie jamais la valeur, placeholder si déjà configuré. */
    private function secret(string $key, string $label, array $o): string
    {
        $configured = !empty($o[$key]);
        $placeholder = $configured ? '●●●●●●●● ' . __('configuré (laisser vide pour conserver)', 'bem-lead-ai') : __('non configuré', 'bem-lead-ai');
        return $this->row($label, '<input type="password" autocomplete="new-password" name="s[' . esc_attr($key) . ']" value="" placeholder="' . esc_attr($placeholder) . '" class="regular-text">');
    }

    /* --- Handlers ------------------------------------------------------ */

    public static function handleSave(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden');
        }
        check_admin_referer('bem_save_settings');

        $input = (array) ($_POST['s'] ?? []);
        $defaults = Options::defaults();
        $clean = [];
        foreach ($defaults as $key => $default) {
            if (in_array($key, Options::SECRET_KEYS, true)) {
                // Champ secret vide = conserver (géré dans Options::update).
                $clean[$key] = (string) ($input[$key] ?? '');
                continue;
            }
            if (is_int($default) || is_float($default)) {
                $clean[$key] = isset($input[$key]) && $input[$key] !== '' ? (is_float($default) ? (float) $input[$key] : (int) $input[$key]) : $default;
            } elseif (in_array($key, ['whatsapp_enabled', 'widget_enabled'], true)) {
                $clean[$key] = isset($input[$key]) ? 1 : 0;
            } elseif ($key === 'widget_greeting') {
                $clean[$key] = sanitize_textarea_field((string) ($input[$key] ?? $default));
            } else {
                $clean[$key] = sanitize_text_field((string) ($input[$key] ?? $default));
            }
        }
        Options::update($clean);
        wp_safe_redirect(admin_url('admin.php?page=bem-lead-ai-settings&saved=1'));
        exit;
    }

    public static function handleReindex(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden');
        }
        check_admin_referer('bem_reindex');
        (new ContentIndexer())->reindexAll();
        wp_safe_redirect(admin_url('admin.php?page=bem-lead-ai-settings&reindexed=1'));
        exit;
    }
}

<?php

namespace BemLeadAi\Admin;

use BemLeadAi\Core\Options;
use BemLeadAi\Knowledge\KnowledgeBaseBuilder;

defined('ABSPATH') || exit;

/**
 * Écran de réglages : clé API + choix des modèles Claude, base de connaissance,
 * scoring, design du widget, passerelle WhatsApp click-to-chat, CRM.
 * Les champs secrets affichent un placeholder sans jamais renvoyer la valeur.
 */
final class SettingsPage
{
    public function render(): void
    {
        $o = Options::all();
        $kb = new KnowledgeBaseBuilder();

        echo '<div class="wrap"><h1>' . esc_html__('BEM Lead AI — Réglages', 'bem-lead-ai') . '</h1>';

        if (isset($_GET['saved'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Réglages enregistrés.', 'bem-lead-ai') . '</p></div>';
        }
        if (isset($_GET['rebuilt'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Catalogue reconstruit.', 'bem-lead-ai') . '</p></div>';
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('bem_save_settings');
        echo '<input type="hidden" name="action" value="bem_save_settings">';

        // --- IA ---
        $consoleLink = '<a href="' . esc_url(Options::ANTHROPIC_CONSOLE_URL) . '" target="_blank" rel="noopener">console.anthropic.com</a>';
        $this->section(
            __('Intelligence artificielle (Claude)', 'bem-lead-ai'),
            [
                $this->secret('anthropic_api_key', 'Clé API Anthropic', $o),
                $this->select('chat_model', 'Modèle du conseiller (chat / résumés)', $o, Options::chatModels()),
                $this->select('classifier_model', 'Modèle de classification (signaux)', $o, Options::classifierModels()),
            ],
            sprintf(__('Créez et gérez votre clé API sur %s. Le modèle du conseiller privilégie la qualité conversationnelle ; celui de classification, la rapidité et le coût (haute fréquence).', 'bem-lead-ai'), $consoleLink),
            true
        );

        // --- Base de connaissance ---
        $this->section(__('Base de connaissance (catalogue en contexte)', 'bem-lead-ai'), [
            $this->text('indexed_post_types', 'Types de contenu inclus (csv)', $o),
            $this->text('onboarding_category', 'Catégorie/tag onboarding', $o),
            $this->select('kb_cache_ttl', 'Durée du cache LLM', $o, ['1h' => '1 heure', '5m' => '5 minutes']),
            $this->number('kb_max_chars_per_post', 'Caractères max par page', $o),
        ], __('Le contenu des formations est injecté directement dans le prompt et mis en cache côté Claude (pas de vector store). Il est reconstruit automatiquement à chaque modification de contenu.', 'bem-lead-ai'));

        // --- Scoring ---
        $this->section(__('Scoring (logique marketing)', 'bem-lead-ai'), [
            $this->number('score_decay_half_life_days', 'Demi-vie du score (jours)', $o),
            $this->number('score_blend_intent_weight', 'Poids de l\'intention conversationnelle (0-1)', $o),
            $this->number('threshold_warm', 'Seuil « tiède »', $o),
            $this->number('threshold_hot', 'Seuil « chaud » → CRM', $o),
            $this->number('threshold_very_hot', 'Seuil « très chaud »', $o),
            $this->number('disengagement_drop_ratio', 'Chute d\'activité = désengagement (0-1)', $o),
            $this->number('disengagement_min_score', 'Score min. pour surveiller le désengagement', $o),
        ]);

        // --- Notifications ---
        $this->section(__('Notifications', 'bem-lead-ai'), [
            $this->text('admissions_email', 'Email équipe admissions', $o),
            $this->text('slack_webhook_url', 'Webhook Slack (optionnel)', $o),
        ]);

        // --- WhatsApp click-to-chat ---
        $this->section(__('WhatsApp (continuer la discussion)', 'bem-lead-ai'), [
            $this->checkbox('whatsapp_enabled', 'Proposer WhatsApp dans le chat', $o),
            $this->text('whatsapp_cta_label', 'Libellé du bouton', $o),
            $this->textarea('whatsapp_numbers', 'Numéros — une ligne par numéro : Label|indicatif+numéro|formation (optionnel)', $o),
            $this->textarea('whatsapp_prefill', 'Message pré-rempli ({prenom}, {formation})', $o),
        ], __('Aucune API WhatsApp Business requise : le prospect est redirigé vers un de vos numéros via un lien wa.me pré-rempli. Si plusieurs numéros, ils sont utilisés en rotation ; un numéro associé à une formation est priorisé pour les prospects intéressés par cette formation.', 'bem-lead-ai'));

        // --- CRM ---
        $this->section(__('CRM', 'bem-lead-ai'), [
            $this->text('perfex_url', 'URL Perfex', $o),
            $this->secret('perfex_api_key', 'Token API Perfex', $o),
            $this->secret('hubspot_api_key', 'Token API HubSpot (optionnel)', $o),
            $this->secret('crm_webhook_secret', 'Secret webhook retour CRM', $o),
        ], __('Perfex est le CRM prioritaire ; HubSpot est optionnel et s\'active sans reconfigurer les triggers.', 'bem-lead-ai'));

        // --- Widget : contenu ---
        $this->section(__('Widget de chat — contenu', 'bem-lead-ai'), [
            $this->checkbox('widget_enabled', 'Afficher le widget', $o),
            $this->text('widget_title', 'Titre du widget', $o),
            $this->text('widget_subtitle', 'Sous-titre (sous le titre)', $o),
            $this->textarea('widget_greeting', 'Message d\'accueil', $o),
            $this->number('rate_limit_per_minute', 'Limite messages / minute', $o),
        ]);

        // --- Widget : design ---
        $this->section(__('Widget de chat — design', 'bem-lead-ai'), [
            $this->color('widget_primary_color', 'Couleur principale', $o),
            $this->color('widget_accent_color', 'Couleur d\'accent', $o),
            $this->color('widget_bubble_user_color', 'Couleur des bulles utilisateur', $o),
            $this->image('widget_avatar_url', 'Avatar / logo du conseiller', $o),
            $this->text('widget_launcher_icon', 'Icône du bouton (emoji)', $o),
            $this->select('widget_theme', 'Thème de la conversation', $o, ['light' => 'Clair', 'dark' => 'Sombre']),
            $this->number('widget_corner_radius', 'Arrondi des coins (px)', $o),
            $this->select('widget_position', 'Position à l\'écran', $o, ['right' => 'En bas à droite', 'left' => 'En bas à gauche']),
        ], __('Personnalisez l\'apparence pour l\'aligner sur la charte de BEM Dakar. Les changements s\'appliquent immédiatement au widget.', 'bem-lead-ai'));

        // --- Bandit ---
        $this->section(__('Apprentissage (bandit)', 'bem-lead-ai'), [
            $this->number('bandit_epsilon', 'Taux d\'exploration epsilon (0-1)', $o),
            $this->number('bandit_conversion_window_hours', 'Fenêtre de conversion (heures)', $o),
        ]);

        submit_button(__('Enregistrer les réglages', 'bem-lead-ai'));
        echo '</form>';

        // Reconstruction manuelle du catalogue.
        echo '<hr><h2>' . esc_html__('Catalogue de connaissance', 'bem-lead-ai') . '</h2>';
        echo '<p>' . esc_html__('Dernière reconstruction :', 'bem-lead-ai') . ' <code>' . esc_html((string) ($kb->builtAt() ?: '—')) . '</code></p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('bem_rebuild_kb');
        echo '<input type="hidden" name="action" value="bem_rebuild_kb">';
        submit_button(__('Reconstruire le catalogue maintenant', 'bem-lead-ai'), 'secondary');
        echo '</form>';

        echo '<hr><h2>' . esc_html__('Webhook à configurer côté CRM', 'bem-lead-ai') . '</h2>';
        echo '<table class="widefat" style="max-width:820px;"><tbody>';
        echo '<tr><th>' . esc_html__('Retour CRM (statut inscrit)', 'bem-lead-ai') . '</th><td><code>' . esc_html(rest_url(BEM_LEAD_AI_REST_NS . '/crm-status-webhook')) . '</code> <em>(header <code>X-Bem-Secret</code>)</em></td></tr>';
        echo '</tbody></table>';

        echo '</div>';
        $this->mediaPickerScript();
    }

    /* --- Rendu des champs --------------------------------------------- */

    private function section(string $title, array $rows, string $help = '', bool $allowHtmlHelp = false): void
    {
        echo '<h2>' . esc_html($title) . '</h2>';
        if ($help) {
            echo '<p class="description">' . ($allowHtmlHelp ? wp_kses_post($help) : esc_html($help)) . '</p>';
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

    private function color(string $key, string $label, array $o): string
    {
        $val = (string) ($o[$key] ?? '#0b3d91');
        return $this->row($label,
            '<input type="color" name="s[' . esc_attr($key) . ']" value="' . esc_attr($val) . '" style="width:60px;height:34px;vertical-align:middle;">'
            . ' <code>' . esc_html($val) . '</code>');
    }

    private function select(string $key, string $label, array $o, array $choices): string
    {
        $html = '<select name="s[' . esc_attr($key) . ']">';
        foreach ($choices as $value => $text) {
            $html .= '<option value="' . esc_attr($value) . '" ' . selected($o[$key] ?? '', $value, false) . '>' . esc_html($text) . '</option>';
        }
        return $this->row($label, $html . '</select>');
    }

    /** Champ image avec sélecteur média WordPress. */
    private function image(string $key, string $label, array $o): string
    {
        $val = (string) ($o[$key] ?? '');
        $preview = $val !== ''
            ? '<img src="' . esc_url($val) . '" alt="" style="max-height:48px;border-radius:8px;vertical-align:middle;margin-right:8px;">'
            : '';
        $control = '<span class="bem-media-preview">' . $preview . '</span>'
            . '<input type="text" class="regular-text bem-media-url" name="s[' . esc_attr($key) . ']" value="' . esc_attr($val) . '" placeholder="https://…"> '
            . '<button type="button" class="button bem-media-pick">' . esc_html__('Choisir une image', 'bem-lead-ai') . '</button> '
            . '<button type="button" class="button bem-media-clear">' . esc_html__('Retirer', 'bem-lead-ai') . '</button>';
        return $this->row($label, $control);
    }

    /** Champ secret : ne renvoie jamais la valeur, placeholder si déjà configuré. */
    private function secret(string $key, string $label, array $o): string
    {
        $configured = !empty($o[$key]);
        $placeholder = $configured ? '●●●●●●●● ' . __('configuré (laisser vide pour conserver)', 'bem-lead-ai') : __('non configuré', 'bem-lead-ai');
        return $this->row($label, '<input type="password" autocomplete="new-password" name="s[' . esc_attr($key) . ']" value="" placeholder="' . esc_attr($placeholder) . '" class="regular-text">');
    }

    private function mediaPickerScript(): void
    {
        ?>
        <script>
        (function () {
            document.querySelectorAll('.bem-media-pick').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    if (!window.wp || !wp.media) { return; }
                    var row = btn.closest('td');
                    var frame = wp.media({ title: 'Sélectionner une image', multiple: false, library: { type: 'image' } });
                    frame.on('select', function () {
                        var att = frame.state().get('selection').first().toJSON();
                        row.querySelector('.bem-media-url').value = att.url;
                        row.querySelector('.bem-media-preview').innerHTML =
                            '<img src="' + att.url + '" style="max-height:48px;border-radius:8px;vertical-align:middle;margin-right:8px;">';
                    });
                    frame.open();
                });
            });
            document.querySelectorAll('.bem-media-clear').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var row = btn.closest('td');
                    row.querySelector('.bem-media-url').value = '';
                    row.querySelector('.bem-media-preview').innerHTML = '';
                });
            });
        })();
        </script>
        <?php
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
        $checkboxes = ['whatsapp_enabled', 'widget_enabled'];
        $textareas = ['widget_greeting', 'whatsapp_numbers', 'whatsapp_prefill'];
        $clean = [];
        foreach ($defaults as $key => $default) {
            if (in_array($key, Options::SECRET_KEYS, true)) {
                // Champ secret vide = conserver la clé existante (géré dans Options::update).
                $clean[$key] = (string) ($input[$key] ?? '');
            } elseif (in_array($key, $checkboxes, true)) {
                // Les cases à cocher DOIVENT être traitées avant la détection
                // int/float (leur défaut vaut 1, un entier).
                $clean[$key] = !empty($input[$key]) ? 1 : 0;
            } elseif (is_int($default) || is_float($default)) {
                $clean[$key] = isset($input[$key]) && $input[$key] !== ''
                    ? (is_float($default) ? (float) $input[$key] : (int) $input[$key])
                    : $default;
            } elseif (str_ends_with($key, '_color')) {
                $clean[$key] = sanitize_hex_color((string) ($input[$key] ?? '')) ?: $default;
            } elseif ($key === 'widget_avatar_url') {
                $clean[$key] = esc_url_raw((string) ($input[$key] ?? ''));
            } elseif (in_array($key, $textareas, true)) {
                $clean[$key] = sanitize_textarea_field((string) ($input[$key] ?? $default));
            } else {
                $clean[$key] = sanitize_text_field((string) ($input[$key] ?? $default));
            }
        }
        Options::update($clean);
        wp_safe_redirect(admin_url('admin.php?page=bem-lead-ai-settings&saved=1'));
        exit;
    }

    public static function handleRebuildKb(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden');
        }
        check_admin_referer('bem_rebuild_kb');
        (new KnowledgeBaseBuilder())->rebuild();
        wp_safe_redirect(admin_url('admin.php?page=bem-lead-ai-settings&rebuilt=1'));
        exit;
    }
}

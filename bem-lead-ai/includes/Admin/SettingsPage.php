<?php

namespace BemLeadAi\Admin;

use BemLeadAi\Ai\ClaudeClient;
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

        echo '<div class="wrap"><h1>' . esc_html(Branding::name()) . ' — ' . esc_html__('Réglages', 'bem-lead-ai') . '</h1>';

        if (isset($_GET['saved'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Réglages enregistrés.', 'bem-lead-ai') . '</p></div>';
        }
        if (isset($_GET['saveerror'])) {
            echo '<div class="notice notice-error"><p><strong>' . esc_html__('Échec de la persistance des réglages.', 'bem-lead-ai') . '</strong> '
                . esc_html__('L\'écriture en base n\'a pas été relue correctement — cause quasi certaine : un cache d\'objet persistant (Redis / Memcached) ou un plugin de cache. Videz le cache de l\'objet (Outils → ou votre plugin de cache), puis réessayez. Si le problème persiste, contactez votre hébergeur : l\'option "' . Options::optionName() . '" n\'est pas écrite.', 'bem-lead-ai') . '</p></div>';
        }
        if (isset($_GET['rebuilt'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Catalogue reconstruit.', 'bem-lead-ai') . '</p></div>';
        }
        if (isset($_GET['purged'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Tous les leads ont été supprimés.', 'bem-lead-ai') . '</p></div>';
        }
        if (isset($_GET['cleaned'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(sprintf(__('%d lead(s) sans engagement supprimé(s).', 'bem-lead-ai'), (int) $_GET['cleaned'])) . '</p></div>';
        }

        // Diagnostic de persistance visible (valeur réellement stockée en base).
        $persisted = Options::persistedFromDb();
        $storedTitle = $persisted ? ($persisted['widget_title'] ?? '—') : '(option absente ou illisible en base)';
        echo '<p class="description">' . esc_html__('Valeur actuellement stockée en base (titre du widget) :', 'bem-lead-ai')
            . ' <code>' . esc_html((string) $storedTitle) . '</code></p>';

        // Résultat du test de connexion Claude (le cas échéant).
        $test = get_transient('bem_lead_ai_claude_test');
        if (is_array($test)) {
            delete_transient('bem_lead_ai_claude_test');
            if (!empty($test['ok'])) {
                echo '<div class="notice notice-success"><p><strong>' . esc_html__('Connexion Claude OK.', 'bem-lead-ai') . '</strong> '
                    . esc_html__('Réponse du modèle :', 'bem-lead-ai') . ' <code>' . esc_html((string) $test['msg']) . '</code></p></div>';
            } else {
                echo '<div class="notice notice-error"><p><strong>' . esc_html__('Échec de la connexion à Claude.', 'bem-lead-ai') . '</strong><br>'
                    . '<code>' . esc_html((string) $test['msg']) . '</code></p></div>';
            }
        }

        // Bouton de test de connexion (diagnostic de l'erreur "souci technique momentané").
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:12px 0;">';
        wp_nonce_field('bem_test_claude');
        echo '<input type="hidden" name="action" value="bem_test_claude">';
        submit_button(__('Tester la connexion à Claude', 'bem-lead-ai'), 'secondary', 'submit', false);
        echo ' <span class="description">' . esc_html__('Vérifie la clé API et le modèle choisi, et affiche l\'erreur exacte le cas échéant.', 'bem-lead-ai') . '</span>';
        echo '</form>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('bem_save_settings');
        echo '<input type="hidden" name="action" value="bem_save_settings">';

        // --- Identité de l'école ---
        $this->section(__('Identité de l\'établissement', 'bem-lead-ai'), [
            $this->text('school_name', 'Nom de l\'école (ex. BEM Conakry)', $o),
            $this->text('school_location', 'Ville / pays (ex. Conakry, Guinée)', $o),
            $this->image('brand_logo_url', 'Logo affiché en haut de l\'administration (School IA)', $o),
        ], __('Le nom de l\'école est utilisé par le conseiller IA (« Je suis le conseiller de… ») et dans les messages. Le logo s\'affiche centré en haut des pages d\'administration ; laissez vide pour le logo Maestro Dan fourni.', 'bem-lead-ai'));

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
            $this->textarea('program_links', 'Liens des programmes — une ligne par programme : Nom du programme | https://…', $o),
        ], __('Le contenu des formations est injecté dans le prompt et mis en cache côté Claude. Ajoutez les liens de vos programmes ci-dessus : le conseiller les partagera (cliquables) quand il recommande un programme.', 'bem-lead-ai'));

        // --- Intégrations formulaires ---
        $this->section(__('Formulaires (Gravity Forms, Contact Form 7, WPForms, Ninja Forms)', 'bem-lead-ai'), [
            $this->checkbox('capture_forms', 'Capturer les leads des formulaires', $o),
        ], __('Quand un visiteur soumet un de vos formulaires, School IA crée automatiquement un lead (email, téléphone, prénom, formation détectés) — aucune configuration par formulaire nécessaire. La détection est automatique dès que le plugin de formulaire est actif.', 'bem-lead-ai'));

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
            $this->text('widget_teaser', 'Bulle d\'accroche animée (près du bouton, incite au clic — laisser vide pour la désactiver)', $o),
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

        // --- Données ---
        $purge = get_option('bem_lead_ai_delete_data') === '1';
        echo '<h2>' . esc_html__('Données', 'bem-lead-ai') . '</h2>';
        echo '<p class="description">' . esc_html__('Par défaut, vos données (clé, design, leads, historique) sont CONSERVÉES si vous désinstallez le plugin — une mise à jour n\'efface donc rien.', 'bem-lead-ai') . '</p>';
        echo '<table class="form-table"><tbody><tr><th scope="row">' . esc_html__('Suppression à la désinstallation', 'bem-lead-ai') . '</th><td>'
            . '<label><input type="checkbox" name="purge_on_uninstall" value="1" ' . checked(true, $purge, false) . '> '
            . esc_html__('Tout supprimer (tables + réglages) quand le plugin est désinstallé', 'bem-lead-ai') . '</label></td></tr></tbody></table>';

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

        $this->renderMaintenance();

        echo '</div>';
        $this->mediaPickerScript();
    }

    /** Zone de maintenance des données (déplacée ici pour éviter les clics accidentels). */
    private function renderMaintenance(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        global $wpdb;
        $p = $wpdb->prefix;
        $postUrl = esc_url(admin_url('admin-post.php'));

        echo '<hr><h2 style="color:#d63638;">' . esc_html__('Maintenance des données (zone sensible)', 'bem-lead-ai') . '</h2>';
        echo '<div class="bem-panel-card" style="max-width:820px;border-color:#f3c2c2;">';

        // Nettoyage ciblé des leads sans engagement (robots/bruit).
        $anon = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$p}bem_leads l
             WHERE l.email IS NULL AND l.phone IS NULL AND (l.prenom IS NULL OR l.prenom = '')
               AND NOT EXISTS (SELECT 1 FROM {$p}bem_chat_messages m WHERE m.lead_id = l.id)"
        );
        echo '<p><strong>' . esc_html__('Nettoyer les leads sans engagement', 'bem-lead-ai') . '</strong><br>'
            . '<span class="description">' . esc_html__('Supprime uniquement les fiches anonymes — sans email/téléphone ni conversation — typiquement du trafic robot. Vos vrais leads sont conservés.', 'bem-lead-ai') . '</span></p>';
        echo '<form method="post" action="' . $postUrl . '" style="margin:0 0 18px;" '
            . 'onsubmit="return confirm(\'' . esc_js(__('Supprimer les leads anonymes sans conversation ni coordonnées ?', 'bem-lead-ai')) . '\');">';
        wp_nonce_field('bem_purge_anon');
        echo '<input type="hidden" name="action" value="bem_purge_anon">';
        submit_button(sprintf(__('Nettoyer les leads sans engagement (%d)', 'bem-lead-ai'), $anon), 'secondary', 'submit', false, $anon ? [] : ['disabled' => 'disabled']);
        echo '</form>';

        // Réinitialisation complète.
        echo '<hr style="border:none;border-top:1px solid #f0e0e0;margin:16px 0;">';
        echo '<p><strong style="color:#d63638;">' . esc_html__('Vider tous les leads (remise à zéro)', 'bem-lead-ai') . '</strong><br>'
            . '<span class="description">' . esc_html__('Efface TOUS les leads, événements, conversations, tâches CRM et la veille. Règles, triggers, catalogue et réglages sont conservés. Irréversible.', 'bem-lead-ai') . '</span></p>';
        echo '<form method="post" action="' . $postUrl . '" style="margin:0;" '
            . 'onsubmit="return confirm(\'' . esc_js(__('Supprimer TOUS les leads, événements et conversations ? Cette action est irréversible.', 'bem-lead-ai')) . '\');">';
        wp_nonce_field('bem_purge_leads');
        echo '<input type="hidden" name="action" value="bem_purge_leads">';
        submit_button(__('Vider tous les leads', 'bem-lead-ai'), 'delete', 'submit', false);
        echo '</form>';

        echo '</div>';
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
        return $this->row($label, '<input type="text" name="s[' . esc_attr($key) . ']" value="' . esc_attr(stripslashes((string) ($o[$key] ?? ''))) . '" class="regular-text">');
    }

    private function textarea(string $key, string $label, array $o): string
    {
        return $this->row($label, '<textarea name="s[' . esc_attr($key) . ']" rows="3" class="large-text">' . esc_textarea(stripslashes((string) ($o[$key] ?? ''))) . '</textarea>');
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

        // wp_unslash indispensable : WordPress ajoute des antislashs aux
        // superglobales ($_POST). Sans ça, une apostrophe devient d\'orientation
        // et les antislashs s'accumulent à chaque enregistrement.
        $input = wp_unslash((array) ($_POST['s'] ?? []));
        $defaults = Options::defaults();
        $checkboxes = ['whatsapp_enabled', 'widget_enabled', 'capture_forms'];
        $textareas = ['widget_greeting', 'whatsapp_numbers', 'whatsapp_prefill', 'program_links'];
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
            } elseif ($key === 'widget_avatar_url' || $key === 'brand_logo_url') {
                $clean[$key] = esc_url_raw((string) ($input[$key] ?? ''));
            } elseif (in_array($key, $textareas, true)) {
                $clean[$key] = sanitize_textarea_field((string) ($input[$key] ?? $default));
            } else {
                $clean[$key] = sanitize_text_field((string) ($input[$key] ?? $default));
            }
        }
        Options::update($clean);

        // Option de purge à la désinstallation (option autonome, lue par uninstall.php).
        update_option('bem_lead_ai_delete_data', !empty($_POST['purge_on_uninstall']) ? '1' : '0');

        // Vérifie que l'écriture a réellement persisté (relecture DIRECTE en
        // base, cache contourné). Un échec ici = couche DB, pas notre code.
        $persisted = Options::persistedFromDb();
        $ok = ($persisted['widget_title'] ?? null) === $clean['widget_title'];

        $flag = $ok ? 'saved=1' : 'saveerror=1';
        wp_safe_redirect(admin_url('admin.php?page=bem-lead-ai-settings&' . $flag));
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

    public static function handleTestClaude(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden');
        }
        check_admin_referer('bem_test_claude');

        $res = (new ClaudeClient())->complete(
            (string) Options::get('chat_model'),
            'Tu es un assistant de test. Réponds en un mot.',
            [['role' => 'user', 'content' => 'Réponds exactement : OK']],
            16
        );
        if (is_wp_error($res)) {
            set_transient('bem_lead_ai_claude_test', ['ok' => false, 'msg' => $res->get_error_message()], 180);
        } else {
            set_transient('bem_lead_ai_claude_test', ['ok' => true, 'msg' => trim((string) $res)], 180);
        }
        wp_safe_redirect(admin_url('admin.php?page=bem-lead-ai-settings'));
        exit;
    }
}

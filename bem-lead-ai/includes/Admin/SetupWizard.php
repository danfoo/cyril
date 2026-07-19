<?php

namespace BemLeadAi\Admin;

use BemLeadAi\Ai\ClaudeClient;
use BemLeadAi\Core\Activator;
use BemLeadAi\Core\Options;
use BemLeadAi\Knowledge\KnowledgeBaseBuilder;

defined('ABSPATH') || exit;

/**
 * Assistant de configuration (setup wizard) au premier lancement.
 *
 * Guide l'administrateur d'une nouvelle installation à travers l'essentiel en
 * 4 étapes — établissement + clé IA, WhatsApp, widget, catalogue — pour qu'il
 * soit opérationnel sans devoir explorer la page de réglages complète. Chaque
 * étape enregistre ses champs via Options (mêmes règles de nettoyage que la
 * page de réglages) puis redirige vers la suivante. Réexécutable à volonté
 * depuis le menu ; ne s'affiche automatiquement qu'une fois, à l'activation.
 */
final class SetupWizard
{
    public const PAGE = 'bem-lead-ai-setup';

    /** Drapeau « configuration terminée » (option autonome). */
    private const DONE_OPTION = 'bem_lead_ai_setup_complete';

    /** Transient posé à l'activation pour déclencher la redirection unique. */
    private const REDIRECT_FLAG = 'bem_lead_ai_activation_redirect';

    private const TOTAL_STEPS = 4;

    /** @var array<int, array{key:string, label:string}> Libellés des étapes. */
    private static function steps(): array
    {
        return [
            1 => ['key' => 'school', 'label' => __('Établissement & IA', 'bem-lead-ai')],
            2 => ['key' => 'whatsapp', 'label' => __('WhatsApp', 'bem-lead-ai')],
            3 => ['key' => 'widget', 'label' => __('Widget de chat', 'bem-lead-ai')],
            4 => ['key' => 'catalogue', 'label' => __('Catalogue', 'bem-lead-ai')],
        ];
    }

    /** Champs (et règles) enregistrés par chaque étape. */
    private static function fieldsForStep(int $step): array
    {
        return match ($step) {
            1 => ['school_name', 'school_location', 'brand_logo_url', 'anthropic_api_key', 'chat_model'],
            2 => ['whatsapp_enabled', 'whatsapp_cta_label', 'whatsapp_numbers', 'whatsapp_prefill'],
            3 => ['widget_enabled', 'widget_title', 'widget_subtitle', 'widget_greeting', 'widget_teaser', 'widget_primary_color', 'widget_accent_color'],
            4 => ['indexed_post_types', 'onboarding_category', 'kb_cache_ttl', 'kb_rebuild_frequency'],
            default => [],
        };
    }

    public static function isComplete(): bool
    {
        return get_option(self::DONE_OPTION) === '1';
    }

    public static function markComplete(): void
    {
        update_option(self::DONE_OPTION, '1', false);
    }

    /** Posé à l'activation → déclenche une seule redirection vers l'assistant. */
    public static function armRedirect(): void
    {
        if (!self::isComplete()) {
            set_transient(self::REDIRECT_FLAG, 1, MINUTE_IN_SECONDS * 5);
        }
    }

    /** Redirection unique vers l'assistant juste après l'activation du plugin. */
    public static function maybeRedirect(): void
    {
        if (!get_transient(self::REDIRECT_FLAG)) {
            return;
        }
        delete_transient(self::REDIRECT_FLAG);
        // Pas de redirection lors d'une activation groupée, en AJAX, ou sans droits.
        if (wp_doing_ajax() || isset($_GET['activate-multi']) || !current_user_can('manage_options')) {
            return;
        }
        if (self::isComplete()) {
            return;
        }
        wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE));
        exit;
    }

    /**
     * Bandeau discret invitant à lancer l'assistant tant que la configuration
     * n'est pas terminée et que la clé API manque (installation neuve).
     */
    public static function notice(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        // Message de bienvenue juste après la fin de l'assistant.
        if (isset($_GET['welcome']) && isset($_GET['page']) && $_GET['page'] === 'bem-lead-ai') {
            echo '<div class="notice notice-success is-dismissible"><p><strong>'
                . esc_html(sprintf(__('%s est configuré 🎉', 'bem-lead-ai'), Branding::name())) . '</strong> '
                . esc_html__('Votre conseiller IA est prêt. Ajustez les détails à tout moment dans les Réglages.', 'bem-lead-ai')
                . '</p></div>';
            return;
        }
        if (self::isComplete()) {
            return;
        }
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && str_contains((string) ($screen->id ?? ''), self::PAGE)) {
            return; // déjà sur l'assistant
        }
        if (Options::hasSecret('anthropic_api_key')) {
            return; // l'essentiel est en place, on n'insiste plus
        }
        $url = esc_url(admin_url('admin.php?page=' . self::PAGE));
        echo '<div class="notice notice-info"><p><strong>' . esc_html(Branding::name()) . '</strong> — '
            . esc_html__('Terminez la configuration en quelques minutes avec l\'assistant.', 'bem-lead-ai')
            . ' <a class="button button-primary" style="margin-left:6px;" href="' . $url . '">'
            . esc_html__('Lancer l\'assistant de configuration', 'bem-lead-ai') . '</a></p></div>';
    }

    /* --- Rendu ---------------------------------------------------------- */

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden');
        }
        $step = max(1, min(self::TOTAL_STEPS, (int) ($_GET['step'] ?? 1)));
        $o = Options::all();

        echo '<div class="wrap bem-wizard">';
        echo '<h1>' . esc_html(sprintf(__('Assistant de configuration — %s', 'bem-lead-ai'), Branding::name())) . '</h1>';
        echo '<p class="description" style="max-width:720px;">'
            . esc_html__('Quatre étapes pour rendre votre conseiller IA opérationnel. Vous pourrez tout modifier plus tard dans les Réglages. Vos réglages existants sont préremplis.', 'bem-lead-ai')
            . '</p>';

        if (isset($_GET['stepsaved'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Étape enregistrée.', 'bem-lead-ai') . '</p></div>';
        }

        $this->progress($step);

        echo '<div class="bem-wizard-card">';
        match ($step) {
            1 => $this->stepSchool($o),
            2 => $this->stepWhatsapp($o),
            3 => $this->stepWidget($o),
            4 => $this->stepCatalogue($o),
            default => $this->stepSchool($o),
        };
        echo '</div>';

        echo '<p class="bem-wizard-skip"><a href="' . esc_url($this->actionUrl('bem_setup_skip', 'bem_setup_skip')) . '">'
            . esc_html__('Passer l\'assistant et aller au tableau de bord', 'bem-lead-ai') . '</a></p>';
        echo '</div>';

        $this->mediaPickerScript();
    }

    /** Barre de progression des 4 étapes. */
    private function progress(int $current): void
    {
        echo '<ol class="bem-wizard-steps">';
        foreach (self::steps() as $n => $meta) {
            $cls = 'bem-wizard-step';
            if ($n < $current) {
                $cls .= ' is-done';
            } elseif ($n === $current) {
                $cls .= ' is-active';
            }
            echo '<li class="' . esc_attr($cls) . '">';
            echo '<span class="bem-wizard-dot">' . ($n < $current ? Icons::get('check-circle') : esc_html((string) $n)) . '</span>';
            echo '<span class="bem-wizard-label">' . esc_html($meta['label']) . '</span>';
            echo '</li>';
        }
        echo '</ol>';
    }

    private function stepSchool(array $o): void
    {
        echo '<h2>' . Icons::get('cap') . ' ' . esc_html__('Votre établissement et l\'IA', 'bem-lead-ai') . '</h2>';
        echo '<p class="description">' . esc_html__('Le nom sert au conseiller (« Je suis le conseiller de… »). La clé API Claude est indispensable pour que le chatbot réponde.', 'bem-lead-ai') . '</p>';

        $this->formOpen(1, 'bem_setup_save');
        echo '<table class="form-table"><tbody>';
        echo $this->text('school_name', __('Nom de l\'école', 'bem-lead-ai'), $o, 'BEM Conakry');
        echo $this->text('school_location', __('Ville / pays', 'bem-lead-ai'), $o, 'Conakry, Guinée');
        echo $this->image('brand_logo_url', __('Logo (optionnel)', 'bem-lead-ai'), $o);
        echo $this->secret('anthropic_api_key', __('Clé API Anthropic (Claude)', 'bem-lead-ai'), $o);
        echo $this->select('chat_model', __('Modèle du conseiller', 'bem-lead-ai'), $o, Options::chatModels());
        echo '</tbody></table>';
        $console = '<a href="' . esc_url(Options::ANTHROPIC_CONSOLE_URL) . '" target="_blank" rel="noopener">console.anthropic.com</a>';
        echo '<p class="description">' . sprintf(
            wp_kses(__('Pas encore de clé ? Créez-en une gratuitement sur %s. Vous pourrez tester la connexion depuis les Réglages après l\'assistant.', 'bem-lead-ai'), ['a' => ['href' => [], 'target' => [], 'rel' => []]]),
            $console
        ) . '</p>';
        $this->actions(1);
        $this->formClose();
    }

    private function stepWhatsapp(array $o): void
    {
        echo '<h2>' . Icons::get('phone') . ' ' . esc_html__('Passerelle WhatsApp', 'bem-lead-ai') . '</h2>';
        echo '<p class="description">' . esc_html__('Le conseiller peut proposer de continuer sur WhatsApp (lien wa.me pré-rempli — aucune API Business requise). Une ligne par numéro : Libellé | indicatif+numéro | formation (optionnelle).', 'bem-lead-ai') . '</p>';

        $this->formOpen(2, 'bem_setup_save');
        echo '<table class="form-table"><tbody>';
        echo $this->checkbox('whatsapp_enabled', __('Proposer WhatsApp dans le chat', 'bem-lead-ai'), $o);
        echo $this->text('whatsapp_cta_label', __('Libellé du bouton', 'bem-lead-ai'), $o, 'Continuer sur WhatsApp');
        echo $this->textarea('whatsapp_numbers', __('Numéros WhatsApp', 'bem-lead-ai'), $o, "Admissions|221770000000|");
        echo $this->textarea('whatsapp_prefill', __('Message pré-rempli ({prenom}, {formation})', 'bem-lead-ai'), $o);
        echo '</tbody></table>';
        $this->actions(2);
        $this->formClose();
    }

    private function stepWidget(array $o): void
    {
        echo '<h2>' . Icons::get('message') . ' ' . esc_html__('Widget de chat', 'bem-lead-ai') . '</h2>';
        echo '<p class="description">' . esc_html__('Apparence et messages du conseiller affiché sur votre site. Les couleurs peuvent reprendre votre charte.', 'bem-lead-ai') . '</p>';

        $this->formOpen(3, 'bem_setup_save');
        echo '<table class="form-table"><tbody>';
        echo $this->checkbox('widget_enabled', __('Afficher le widget sur le site', 'bem-lead-ai'), $o);
        echo $this->text('widget_title', __('Titre du widget', 'bem-lead-ai'), $o);
        echo $this->text('widget_subtitle', __('Sous-titre', 'bem-lead-ai'), $o);
        echo $this->textarea('widget_greeting', __('Message d\'accueil', 'bem-lead-ai'), $o);
        echo $this->text('widget_teaser', __('Bulle d\'accroche (incite au clic — vide = désactivée)', 'bem-lead-ai'), $o);
        echo $this->color('widget_primary_color', __('Couleur principale', 'bem-lead-ai'), $o);
        echo $this->color('widget_accent_color', __('Couleur d\'accent', 'bem-lead-ai'), $o);
        echo '</tbody></table>';
        $this->actions(3);
        $this->formClose();
    }

    private function stepCatalogue(array $o): void
    {
        echo '<h2>' . Icons::get('bulb') . ' ' . esc_html__('Catalogue de connaissance', 'bem-lead-ai') . '</h2>';
        echo '<p class="description">' . esc_html__('Le conseiller répond à partir du contenu réel de votre site (formations, pages). Indiquez les types de contenu à inclure ; le catalogue sera construit à la fin de l\'assistant.', 'bem-lead-ai') . '</p>';

        $this->formOpen(4, 'bem_setup_finish');
        echo '<table class="form-table"><tbody>';
        echo $this->text('indexed_post_types', __('Types de contenu inclus (séparés par des virgules)', 'bem-lead-ai'), $o, 'formation,page');
        echo $this->text('onboarding_category', __('Catégorie/tag onboarding (étudiants inscrits)', 'bem-lead-ai'), $o);
        echo $this->select('kb_cache_ttl', __('Durée du cache LLM', 'bem-lead-ai'), $o, [
            '1h' => __('1 heure — trafic soutenu', 'bem-lead-ai'),
            '5m' => __('5 minutes — trafic faible (souvent plus économique au démarrage)', 'bem-lead-ai'),
        ]);
        echo $this->select('kb_rebuild_frequency', __('Reconstruction automatique du catalogue', 'bem-lead-ai'), $o, [
            'weekly' => __('Chaque semaine (recommandé)', 'bem-lead-ai'),
            'daily' => __('Chaque jour', 'bem-lead-ai'),
            'manual' => __('Manuelle uniquement (+ à chaque modification de contenu)', 'bem-lead-ai'),
        ]);
        echo '</tbody></table>';
        echo '<p class="description">' . esc_html__('En cliquant sur « Terminer », le catalogue est construit immédiatement et l\'assistant se ferme.', 'bem-lead-ai') . '</p>';
        $this->actions(4, true);
        $this->formClose();
    }

    /* --- Blocs de formulaire ------------------------------------------- */

    private function actionUrl(string $action, string $nonce): string
    {
        return wp_nonce_url(admin_url('admin-post.php?action=' . $action), $nonce);
    }

    private function formOpen(int $step, string $action): void
    {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('bem_setup_step_' . $step);
        echo '<input type="hidden" name="action" value="' . esc_attr($action) . '">';
        echo '<input type="hidden" name="step" value="' . esc_attr((string) $step) . '">';
    }

    private function formClose(): void
    {
        echo '</form>';
    }

    private function actions(int $step, bool $isFinal = false): void
    {
        echo '<div class="bem-wizard-actions">';
        if ($step > 1) {
            $prev = esc_url(admin_url('admin.php?page=' . self::PAGE . '&step=' . ($step - 1)));
            echo '<a class="button button-secondary" href="' . $prev . '">' . esc_html__('Précédent', 'bem-lead-ai') . '</a>';
        } else {
            echo '<span></span>';
        }
        $label = $isFinal ? __('Terminer la configuration', 'bem-lead-ai') : __('Enregistrer et continuer', 'bem-lead-ai');
        echo '<button type="submit" class="button button-primary button-hero">' . esc_html($label) . '</button>';
        echo '</div>';
    }

    /* --- Champs (mêmes conventions que SettingsPage) -------------------- */

    private function row(string $label, string $control): string
    {
        return '<tr><th scope="row">' . esc_html($label) . '</th><td>' . $control . '</td></tr>';
    }

    private function text(string $key, string $label, array $o, string $placeholder = ''): string
    {
        return $this->row($label, '<input type="text" name="s[' . esc_attr($key) . ']" value="'
            . esc_attr(stripslashes((string) ($o[$key] ?? ''))) . '" class="regular-text" placeholder="' . esc_attr($placeholder) . '">');
    }

    private function textarea(string $key, string $label, array $o, string $placeholder = ''): string
    {
        return $this->row($label, '<textarea name="s[' . esc_attr($key) . ']" rows="3" class="large-text" placeholder="'
            . esc_attr($placeholder) . '">' . esc_textarea(stripslashes((string) ($o[$key] ?? ''))) . '</textarea>');
    }

    private function checkbox(string $key, string $label, array $o): string
    {
        return $this->row($label, '<label><input type="checkbox" name="s[' . esc_attr($key) . ']" value="1" '
            . checked(1, (int) ($o[$key] ?? 0), false) . '> ' . esc_html__('Activé', 'bem-lead-ai') . '</label>');
    }

    private function color(string $key, string $label, array $o): string
    {
        $val = (string) ($o[$key] ?? '#0b3d91');
        return $this->row($label, '<input type="color" name="s[' . esc_attr($key) . ']" value="' . esc_attr($val)
            . '" style="width:60px;height:34px;vertical-align:middle;"> <code>' . esc_html($val) . '</code>');
    }

    private function select(string $key, string $label, array $o, array $choices): string
    {
        $html = '<select name="s[' . esc_attr($key) . ']">';
        foreach ($choices as $value => $text) {
            $html .= '<option value="' . esc_attr($value) . '" ' . selected($o[$key] ?? '', $value, false) . '>' . esc_html($text) . '</option>';
        }
        return $this->row($label, $html . '</select>');
    }

    private function image(string $key, string $label, array $o): string
    {
        $val = (string) ($o[$key] ?? '');
        $preview = $val !== '' ? '<img src="' . esc_url($val) . '" alt="" style="max-height:48px;border-radius:8px;vertical-align:middle;margin-right:8px;">' : '';
        $control = '<span class="bem-media-preview">' . $preview . '</span>'
            . '<input type="text" class="regular-text bem-media-url" name="s[' . esc_attr($key) . ']" value="' . esc_attr($val) . '" placeholder="https://…"> '
            . '<button type="button" class="button bem-media-pick">' . esc_html__('Choisir une image', 'bem-lead-ai') . '</button> '
            . '<button type="button" class="button bem-media-clear">' . esc_html__('Retirer', 'bem-lead-ai') . '</button>';
        return $this->row($label, $control);
    }

    private function secret(string $key, string $label, array $o): string
    {
        $configured = !empty($o[$key]);
        $placeholder = $configured ? '●●●●●●●● ' . __('configurée (laisser vide pour conserver)', 'bem-lead-ai') : __('sk-ant-…', 'bem-lead-ai');
        return $this->row($label, '<input type="password" autocomplete="new-password" name="s[' . esc_attr($key)
            . ']" value="" placeholder="' . esc_attr($placeholder) . '" class="regular-text">');
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

    /* --- Handlers ------------------------------------------------------- */

    /** Enregistre une étape puis avance vers la suivante. */
    public static function handleSave(): void
    {
        $step = self::guardStep('bem_setup_save');
        self::persistStep($step);
        $next = min(self::TOTAL_STEPS, $step + 1);
        wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE . '&step=' . $next . '&stepsaved=1'));
        exit;
    }

    /** Dernière étape : enregistre, construit le catalogue, clôt l'assistant. */
    public static function handleFinish(): void
    {
        $step = self::guardStep('bem_setup_finish');
        self::persistStep($step);
        (new KnowledgeBaseBuilder())->rebuild();
        self::markComplete();
        wp_safe_redirect(admin_url('admin.php?page=bem-lead-ai&welcome=1'));
        exit;
    }

    /** L'utilisateur passe l'assistant : on le marque terminé sans forcer. */
    public static function handleSkip(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden');
        }
        check_admin_referer('bem_setup_skip');
        self::markComplete();
        wp_safe_redirect(admin_url('admin.php?page=bem-lead-ai'));
        exit;
    }

    /** Vérifie droits + nonce d'étape et renvoie le numéro d'étape validé. */
    private static function guardStep(string $expectedAction): int
    {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden');
        }
        $step = max(1, min(self::TOTAL_STEPS, (int) ($_POST['step'] ?? 1)));
        check_admin_referer('bem_setup_step_' . $step);
        return $step;
    }

    /** Nettoie et enregistre uniquement les champs de l'étape donnée. */
    private static function persistStep(int $step): void
    {
        $allowed = self::fieldsForStep($step);
        $input = wp_unslash((array) ($_POST['s'] ?? []));
        $defaults = Options::defaults();
        $checkboxes = ['whatsapp_enabled', 'widget_enabled', 'capture_forms'];
        $textareas = ['widget_greeting', 'whatsapp_numbers', 'whatsapp_prefill', 'program_links'];

        $clean = [];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $defaults)) {
                continue;
            }
            $default = $defaults[$key];
            if (in_array($key, Options::SECRET_KEYS, true)) {
                // Vide = conserver la clé existante (géré dans Options::update).
                $clean[$key] = (string) ($input[$key] ?? '');
            } elseif (in_array($key, $checkboxes, true)) {
                $clean[$key] = !empty($input[$key]) ? 1 : 0;
            } elseif (is_int($default) || is_float($default)) {
                $clean[$key] = isset($input[$key]) && $input[$key] !== ''
                    ? (is_float($default) ? (float) $input[$key] : (int) $input[$key])
                    : $default;
            } elseif (str_ends_with($key, '_color')) {
                $clean[$key] = sanitize_hex_color((string) ($input[$key] ?? '')) ?: $default;
            } elseif ($key === 'brand_logo_url' || $key === 'widget_avatar_url') {
                $clean[$key] = esc_url_raw((string) ($input[$key] ?? ''));
            } elseif (in_array($key, $textareas, true)) {
                $clean[$key] = sanitize_textarea_field((string) ($input[$key] ?? $default));
            } else {
                $clean[$key] = sanitize_text_field((string) ($input[$key] ?? $default));
            }
        }
        if ($clean) {
            Options::update($clean);
        }
        // La fréquence de reconstruction peut changer → réaligne la tâche cron.
        if (in_array('kb_rebuild_frequency', $allowed, true)) {
            Activator::syncKbCron();
        }
    }
}

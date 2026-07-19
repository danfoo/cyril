<?php

namespace BemLeadAi\Core;

use BemLeadAi\Admin\AdminMenu;
use BemLeadAi\Ai\SignalClassifier;
use BemLeadAi\Api\RestController;
use BemLeadAi\Knowledge\KnowledgeBaseBuilder;
use BemLeadAi\Learning\VariantBandit;
use BemLeadAi\Scoring\DisengagementDetector;
use BemLeadAi\Scoring\ScoringEngine;
use BemLeadAi\Triggers\ActionRunner;
use BemLeadAi\Triggers\TriggerEngine;

defined('ABSPATH') || exit;

/**
 * Point d'assemblage : enregistre REST, admin, widget, crons et jobs de file.
 *
 * Pipeline central : événement enregistré → recalcul du score → évaluation
 * des triggers. Tout ce qui est coûteux (LLM, CRM, envois) part dans la file
 * asynchrone pour ne jamais bloquer la page ni la conversation.
 */
final class Plugin
{
    private static ?Plugin $instance = null;

    /** @var array<string, object> */
    private array $services = [];

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    private bool $booted = false;

    public function boot(): void
    {
        if ($this->booted) {
            return; // garde anti-double-initialisation
        }
        $this->booted = true;

        // Auto-migration : si les fichiers ont été mis à jour sans réactivation,
        // on aligne schéma + options + catalogue (idempotent, une seule fois).
        if (Activator::needsUpgrade()) {
            Activator::runMigrations();
        }

        add_action('rest_api_init', fn() => (new RestController())->registerRoutes());

        if (is_admin()) {
            (new AdminMenu())->register();
        }

        // Mises à jour automatiques depuis le serveur de licences maison.
        (new \BemLeadAi\License\Updater())->register();

        // Revalidation périodique de la licence (abonnement annuel).
        add_action('bem_lead_ai_cron_license', ['\BemLeadAi\License\LicenseClient', 'validate']);

        // Capture des leads depuis les plugins de formulaires (Gravity Forms, etc.).
        (new \BemLeadAi\Integrations\FormCapture())->register();

        add_action('wp_enqueue_scripts', [$this, 'enqueueWidget']);

        // Pipeline événement → scoring → triggers.
        add_action('bem_lead_ai_event_recorded', [$this, 'onEventRecorded'], 10, 4);

        // Jobs asynchrones (Action Scheduler ou WP-Cron en repli).
        add_action('bem_lead_ai_job_classify', [$this, 'jobClassify'], 10, 1);
        add_action('bem_lead_ai_job_action', [$this, 'jobAction'], 10, 3);

        // Reconstruction de la base de connaissance (catalogue caché) à chaque
        // modification de contenu, pour garder le préfixe LLM à jour.
        add_action('save_post', [$this, 'onContentChanged'], 20, 1);
        add_action('deleted_post', [$this, 'onContentChanged'], 20, 1);
        add_action('bem_lead_ai_job_rebuild_kb', fn() => (new KnowledgeBaseBuilder())->rebuild());

        // Crons récurrents.
        add_action('bem_lead_ai_cron_disengagement', fn() => (new DisengagementDetector())->run());
        add_action('bem_lead_ai_cron_rebuild_kb', fn() => (new KnowledgeBaseBuilder())->rebuild());
        add_action('bem_lead_ai_cron_bandit', fn() => (new VariantBandit())->sweepConversions());
        add_action('bem_lead_ai_cron_crm_tasks', ['\BemLeadAi\Crm\TaskReminder', 'run']);

        // Export / effacement des données personnelles (droit à l'oubli, loi n°2008-12).
        add_filter('wp_privacy_personal_data_erasers', function (array $erasers): array {
            $erasers['bem-lead-ai'] = [
                'eraser_friendly_name' => defined('BEM_LEAD_AI_BRAND') ? BEM_LEAD_AI_BRAND : 'School IA',
                'callback' => ['\BemLeadAi\Privacy\PrivacyManager', 'eraseByEmail'],
            ];
            return $erasers;
        });
    }

    /** Version d'un asset = son filemtime (cache-busting fiable), repli sur la version du plugin. */
    private static function assetVersion(string $relPath): string
    {
        $file = BEM_LEAD_AI_DIR . $relPath;
        $mtime = is_file($file) ? filemtime($file) : 0;
        return $mtime ? (string) $mtime : BEM_LEAD_AI_VERSION;
    }

    public function enqueueWidget(): void
    {
        if (!(int) Options::get('widget_enabled')) {
            return;
        }
        // Version basée sur filemtime : toute édition des assets casse le cache
        // navigateur automatiquement (fini les designs "qui ne s'appliquent pas").
        wp_enqueue_style('bem-lead-ai-widget', BEM_LEAD_AI_URL . 'assets/css/widget.css', [], self::assetVersion('assets/css/widget.css'));
        wp_enqueue_script('bem-lead-ai-widget', BEM_LEAD_AI_URL . 'assets/js/widget.js', [], self::assetVersion('assets/js/widget.js'), true);

        // stripslashes défensif : nettoie les antislashs éventuellement stockés
        // par d'anciens enregistrements (avant le correctif wp_unslash).
        $clean = static fn($v) => stripslashes((string) $v);

        wp_localize_script('bem-lead-ai-widget', 'BemLeadAiConfig', [
            'restUrl' => esc_url_raw(rest_url(BEM_LEAD_AI_REST_NS)),
            'nonce' => wp_create_nonce('wp_rest'),
            'title' => $clean(Options::get('widget_title')),
            'subtitle' => $clean(Options::get('widget_subtitle')),
            'greeting' => $clean(Options::get('widget_greeting')),
            'teaser' => $clean(Options::get('widget_teaser')),
            'pageContext' => $this->currentPageContext(),
            'whatsappEnabled' => (new \BemLeadAi\Channels\WhatsAppHandoff())->isEnabled(),
            'whatsappLabel' => $clean(Options::get('whatsapp_cta_label')),
            'design' => [
                'primary' => Options::get('widget_primary_color'),
                'accent' => Options::get('widget_accent_color'),
                'userBubble' => Options::get('widget_bubble_user_color'),
                'avatar' => esc_url_raw((string) Options::get('widget_avatar_url')),
                'launcher' => Options::get('widget_launcher_icon'),
                'position' => Options::get('widget_position') === 'left' ? 'left' : 'right',
                'theme' => Options::get('widget_theme') === 'dark' ? 'dark' : 'light',
            ],
        ]);

        wp_add_inline_style('bem-lead-ai-widget', $this->widgetInlineStyle());
    }

    /** Variables CSS dérivées des réglages de design (couleurs, rayon, thème, position). */
    private function widgetInlineStyle(): string
    {
        $primary = sanitize_hex_color((string) Options::get('widget_primary_color')) ?: '#0b3d91';
        $accent = sanitize_hex_color((string) Options::get('widget_accent_color')) ?: '#e6b800';
        $userBubble = sanitize_hex_color((string) Options::get('widget_bubble_user_color')) ?: $primary;
        $dark = $this->darken($primary, 0.18);
        $radius = max(0, min(28, (int) Options::get('widget_corner_radius')));
        $side = Options::get('widget_position') === 'left' ? 'left' : 'right';
        $otherSide = $side === 'left' ? 'right' : 'left';
        $isDark = Options::get('widget_theme') === 'dark';

        // Palette de la zone de conversation selon le thème.
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

    private function darken(string $hex, float $amount): string
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

    /** Planifie une reconstruction du catalogue peu après une modification. */
    public function onContentChanged(int $postId): void
    {
        if (wp_is_post_revision($postId) || wp_is_post_autosave($postId)) {
            return;
        }
        Queue::dispatchIn(30, 'bem_lead_ai_job_rebuild_kb');
    }

    /**
     * Contexte marketing de la page courante, transmis au tracker :
     * permet de pondérer différemment formation / frais / admission.
     */
    private function currentPageContext(): array
    {
        $kind = 'generic';
        if (is_singular()) {
            $post = get_post();
            if ($post) {
                $slugAndTitle = strtolower($post->post_name . ' ' . $post->post_title);
                if ($post->post_type === 'formation' || str_contains($slugAndTitle, 'formation') || str_contains($slugAndTitle, 'master') || str_contains($slugAndTitle, 'licence') || str_contains($slugAndTitle, 'bachelor') || str_contains($slugAndTitle, 'mba')) {
                    $kind = 'formation';
                }
                if (preg_match('/frais|tarif|cout|coût|prix|financement/', $slugAndTitle)) {
                    $kind = 'pricing';
                }
                if (preg_match('/admission|candidat|inscri|concours/', $slugAndTitle)) {
                    $kind = 'admission';
                }
            }
        }
        return [
            'page_kind' => $kind,
            'post_id' => is_singular() ? get_the_ID() : 0,
            'url' => home_url(add_query_arg([])),
            'title' => wp_get_document_title(),
        ];
    }

    public function onEventRecorded(int $leadId, string $type, array $payload, string $canal): void
    {
        $scoring = new ScoringEngine();
        $scoring->recalculate($leadId);

        (new TriggerEngine())->evaluate($leadId, ['type' => $type, 'payload' => $payload, 'canal' => $canal]);
    }

    public function jobClassify(int $leadId): void
    {
        (new SignalClassifier())->classifyPendingMessages($leadId);
    }

    public function jobAction(string $actionType, int $leadId, array $args = []): void
    {
        (new ActionRunner())->run($actionType, $leadId, $args);
    }
}

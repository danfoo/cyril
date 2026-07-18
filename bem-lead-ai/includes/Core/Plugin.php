<?php

namespace BemLeadAi\Core;

use BemLeadAi\Admin\AdminMenu;
use BemLeadAi\Ai\SignalClassifier;
use BemLeadAi\Api\RestController;
use BemLeadAi\Learning\VariantBandit;
use BemLeadAi\Rag\ContentIndexer;
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

    public function boot(): void
    {
        add_action('rest_api_init', fn() => (new RestController())->registerRoutes());

        if (is_admin()) {
            (new AdminMenu())->register();
        }

        add_action('wp_enqueue_scripts', [$this, 'enqueueWidget']);

        // Pipeline événement → scoring → triggers.
        add_action('bem_lead_ai_event_recorded', [$this, 'onEventRecorded'], 10, 4);

        // Jobs asynchrones (Action Scheduler ou WP-Cron en repli).
        add_action('bem_lead_ai_job_classify', [$this, 'jobClassify'], 10, 1);
        add_action('bem_lead_ai_job_action', [$this, 'jobAction'], 10, 3);
        add_action('bem_lead_ai_job_wa_inbound', [$this, 'jobWhatsAppInbound'], 10, 3);

        // Crons récurrents.
        add_action('bem_lead_ai_cron_disengagement', fn() => (new DisengagementDetector())->run());
        add_action('bem_lead_ai_cron_reindex', fn() => (new ContentIndexer())->reindexAll());
        add_action('bem_lead_ai_cron_bandit', fn() => (new VariantBandit())->sweepConversions());

        // Export / effacement des données personnelles (droit à l'oubli, loi n°2008-12).
        add_filter('wp_privacy_personal_data_erasers', function (array $erasers): array {
            $erasers['bem-lead-ai'] = [
                'eraser_friendly_name' => 'BEM Lead AI',
                'callback' => ['\BemLeadAi\Privacy\PrivacyManager', 'eraseByEmail'],
            ];
            return $erasers;
        });
    }

    public function enqueueWidget(): void
    {
        if (!(int) Options::get('widget_enabled')) {
            return;
        }
        wp_enqueue_style('bem-lead-ai-widget', BEM_LEAD_AI_URL . 'assets/css/widget.css', [], BEM_LEAD_AI_VERSION);
        wp_enqueue_script('bem-lead-ai-widget', BEM_LEAD_AI_URL . 'assets/js/widget.js', [], BEM_LEAD_AI_VERSION, true);

        wp_localize_script('bem-lead-ai-widget', 'BemLeadAiConfig', [
            'restUrl' => esc_url_raw(rest_url(BEM_LEAD_AI_REST_NS)),
            'nonce' => wp_create_nonce('wp_rest'),
            'title' => Options::get('widget_title'),
            'greeting' => Options::get('widget_greeting'),
            'pageContext' => $this->currentPageContext(),
        ]);
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

    /**
     * Message WhatsApp entrant, traité hors du webhook (Meta exige un 200
     * rapide) : même orchestrateur que le web, réponse renvoyée via Cloud API.
     */
    public function jobWhatsAppInbound(string $waid, string $text, string $profileName = ''): void
    {
        $adapter = new \BemLeadAi\Chat\ChannelAdapter();
        $lead = $adapter->resolveWhatsAppLead($waid, $profileName ?: null);
        $result = (new \BemLeadAi\Chat\ChatOrchestrator())->handleMessage($lead, $text, 'whatsapp');
        if ($result['reply'] !== null) {
            (new \BemLeadAi\Channels\WhatsAppClient())->sendText($waid, $result['reply']);
        }
    }
}

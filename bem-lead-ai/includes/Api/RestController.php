<?php

namespace BemLeadAi\Api;

use BemLeadAi\Channels\WhatsAppHandoff;
use BemLeadAi\Chat\ChannelAdapter;
use BemLeadAi\Chat\ChatOrchestrator;
use BemLeadAi\Chat\ConversationRepository;
use BemLeadAi\Core\Options;
use BemLeadAi\Financing\FinancingSimulator;
use BemLeadAi\Handoff\HandoffManager;
use BemLeadAi\Knowledge\KnowledgeBaseBuilder;
use BemLeadAi\Leads\EventRepository;
use BemLeadAi\Leads\LeadRepository;
use BemLeadAi\Scoring\ScoringEngine;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined('ABSPATH') || exit;

/**
 * Endpoints REST — namespace bem-lead-ai/v1.
 */
final class RestController
{
    public function registerRoutes(): void
    {
        $ns = BEM_LEAD_AI_REST_NS;

        register_rest_route($ns, '/chat', [
            'methods' => 'POST',
            'callback' => [$this, 'chat'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($ns, '/messages', [
            'methods' => 'GET',
            'callback' => [$this, 'messages'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($ns, '/history', [
            'methods' => 'GET',
            'callback' => [$this, 'history'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($ns, '/track', [
            'methods' => 'POST',
            'callback' => [$this, 'track'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($ns, '/whatsapp-link', [
            'methods' => 'POST',
            'callback' => [$this, 'whatsappLink'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($ns, '/lead/(?P<session_id>[A-Za-z0-9_\-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'lead'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);

        register_rest_route($ns, '/rebuild-kb', [
            'methods' => 'POST',
            'callback' => fn() => rest_ensure_response((new KnowledgeBaseBuilder())->rebuild()),
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);

        register_rest_route($ns, '/handoff/(?P<lead_id>\d+)/reply', [
            'methods' => 'POST',
            'callback' => [$this, 'handoffReply'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        register_rest_route($ns, '/handoff/(?P<handoff_id>\d+)/close', [
            'methods' => 'POST',
            'callback' => [$this, 'handoffClose'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        // Réponse d'un conseiller depuis le module Perfex (School IA Bridge) :
        // authentifiée par le secret partagé du pont (le même que celui utilisé
        // par PerfexBridgeConnector, saisi côté Perfex → « School IA — Réglages »).
        register_rest_route($ns, '/handoff/reply-from-crm', [
            'methods' => 'POST',
            'callback' => [$this, 'handoffReplyFromCrm'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($ns, '/crm-status-webhook', [
            'methods' => 'POST',
            'callback' => [$this, 'crmStatusWebhook'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($ns, '/financing/options', [
            'methods' => 'GET',
            'callback' => fn() => rest_ensure_response(array_map(fn($o) => [
                'id' => (int) $o->id,
                'formation' => $o->formation_label,
                'frais_total' => (int) $o->frais_total,
                'devise' => $o->devise,
            ], (new FinancingSimulator())->activeOptions())),
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($ns, '/financing/simulate', [
            'methods' => 'POST',
            'callback' => [$this, 'financingSimulate'],
            'permission_callback' => '__return_true',
        ]);

        // Embarquement sur un site NON-WordPress : configuration publique du widget
        // et capture de formulaire, authentifiées par la clé de site (embed_site_key).
        register_rest_route($ns, '/embed/config', [
            'methods' => 'GET',
            'callback' => [$this, 'embedConfig'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($ns, '/embed/capture', [
            'methods' => 'POST',
            'callback' => [$this, 'embedCapture'],
            'permission_callback' => '__return_true',
        ]);

        // En-têtes CORS pour autoriser les appels depuis les domaines des sites clients.
        add_filter('rest_pre_serve_request', [$this, 'sendCorsHeaders'], 10, 3);
    }

    /* ------------------------------------------------------------------ */
    /* Embarquement inter-sites (sites non-WordPress)                      */
    /* ------------------------------------------------------------------ */

    /** Clé publique de site, auto-générée au premier accès (identifie le tenant). */
    public static function siteKey(): string
    {
        $key = (string) get_option('bem_lead_ai_embed_site_key', '');
        if ($key === '') {
            $key = function_exists('wp_generate_password') ? wp_generate_password(32, false) : bin2hex(random_bytes(16));
            update_option('bem_lead_ai_embed_site_key', $key, false);
        }
        return $key;
    }

    /** Origines autorisées (une par ligne dans les réglages). */
    private function allowedOrigins(): array
    {
        $raw = (string) Options::get('embed_allowed_origins');
        $list = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $raw) ?: []));
        return array_map(fn($o) => rtrim($o, '/'), $list);
    }

    /** Origine à renvoyer dans Access-Control-Allow-Origin, ou '' si non autorisée. */
    private function corsOrigin(): string
    {
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? rtrim((string) $_SERVER['HTTP_ORIGIN'], '/') : '';
        if ($origin === '') {
            return '';
        }
        $allowed = $this->allowedOrigins();
        // Liste vide = prototype permissif (on reflète l'origine) ; sinon strict.
        if (!$allowed || in_array($origin, $allowed, true)) {
            return $origin;
        }
        return '';
    }

    /** Ajoute les en-têtes CORS aux réponses de notre namespace REST. */
    public function sendCorsHeaders($served, $result, $request)
    {
        if (!($request instanceof WP_REST_Request)) {
            return $served;
        }
        if (strpos($request->get_route(), '/' . BEM_LEAD_AI_REST_NS) !== 0) {
            return $served;
        }
        $origin = $this->corsOrigin();
        if ($origin !== '') {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, X-WP-Nonce');
        }
        return $served;
    }

    /** Attribution de campagne (UTM) transmise par le widget/snippet. */
    private function utmFromRequest(WP_REST_Request $request): array
    {
        $utm = $request->get_param('utm');
        if (!is_array($utm)) {
            return [];
        }
        return [
            'source'   => sanitize_text_field((string) ($utm['source'] ?? '')),
            'medium'   => sanitize_text_field((string) ($utm['medium'] ?? '')),
            'campaign' => sanitize_text_field((string) ($utm['campaign'] ?? '')),
        ];
    }

    private function keyValid(WP_REST_Request $request): bool
    {
        $provided = (string) ($request->get_param('key') ?? '');
        return $provided !== '' && hash_equals(self::siteKey(), $provided);
    }

    /** Configuration publique du widget (design + textes), sans nonce. */
    public function embedConfig(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (!$this->keyValid($request)) {
            return new WP_Error('bem_forbidden', 'Invalid site key', ['status' => 403]);
        }
        $clean = static fn($v) => stripslashes((string) $v);
        return rest_ensure_response([
            'restUrl' => esc_url_raw(rest_url(BEM_LEAD_AI_REST_NS)),
            'assetsUrl' => esc_url_raw(BEM_LEAD_AI_URL . 'assets/'),
            // CSS des couleurs/design : sous WordPress il est injecté en inline ;
            // pour un site externe on le fournit ici, injecté par embed.js.
            'inlineCss' => \BemLeadAi\Core\WidgetStyle::css(),
            'title' => $clean(Options::get('widget_title')),
            'subtitle' => $clean(Options::get('widget_subtitle')),
            'greeting' => $clean(Options::get('widget_greeting')),
            'teaser' => $clean(Options::get('widget_teaser')),
            'whatsappEnabled' => (new WhatsAppHandoff())->isEnabled(),
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
    }

    /** Capture d'un formulaire soumis depuis un site externe (non-WordPress). */
    public function embedCapture(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (!$this->keyValid($request)) {
            return new WP_Error('bem_forbidden', 'Invalid site key', ['status' => 403]);
        }
        if (\BemLeadAi\Core\BotDetector::isBot()) {
            return new WP_Error('bem_forbidden', 'Forbidden', ['status' => 403]);
        }
        $p = static fn($k) => is_string($request->get_param($k)) ? trim((string) $request->get_param($k)) : '';
        $leadId = (new \BemLeadAi\Integrations\FormCapture())->captureLead(
            $p('email') ?: null,
            $p('phone') ?: null,
            $p('name') ?: null,
            $p('formation') ?: null,
            'embed',
            $p('source_form'),
            $p('session_id') ?: null,
            $this->utmFromRequest($request)
        );
        if ($leadId === null) {
            return new WP_Error('bem_no_contact', 'Aucune coordonnée exploitable', ['status' => 422]);
        }
        return rest_ensure_response(['ok' => true, 'lead_id' => $leadId]);
    }

    /* ------------------------------------------------------------------ */
    /* Chat web                                                            */
    /* ------------------------------------------------------------------ */

    public function chat(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        // Robots/scanners : on n'engage ni lead ni appel LLM.
        if (\BemLeadAi\Core\BotDetector::isBot()) {
            return new WP_Error('bem_forbidden', 'Forbidden', ['status' => 403]);
        }
        if (!RateLimiter::allow('chat', RateLimiter::clientKey($request))) {
            return new WP_Error('bem_rate_limited', __('Trop de messages, réessayez dans une minute.', 'bem-lead-ai'), ['status' => 429]);
        }

        $sessionId = sanitize_text_field((string) $request->get_param('session_id'));
        $message = trim(wp_strip_all_tags((string) $request->get_param('message')));
        if ($sessionId === '' || $message === '' || mb_strlen($message) > 2000) {
            return new WP_Error('bem_bad_request', __('Requête invalide.', 'bem-lead-ai'), ['status' => 400]);
        }

        $adapter = new ChannelAdapter();
        $lead = $adapter->resolveWebLead($sessionId);

        // Écrire au chat = engagement réel : c'est ce message (pas la navigation)
        // qui fait naître le lead. On marque le consentement d'échange.
        if ((int) $lead->consent !== 1) {
            (new LeadRepository())->update((int) $lead->id, ['consent' => 1]);
        }
        // Attribution de campagne (first-touch) transmise par le widget.
        (new LeadRepository())->applyUtm((int) $lead->id, $this->utmFromRequest($request));

        $email = $request->get_param('email');
        $phone = $request->get_param('phone');
        if ($email || $phone) {
            $lead = $adapter->attachIdentity($lead, $email ? (string) $email : null, $phone ? (string) $phone : null);
        }

        $result = (new ChatOrchestrator())->handleMessage($lead, $message, 'web');

        return rest_ensure_response([
            'reply' => $result['reply'],
            'handoff' => $result['handoff'],
            'last_message_id' => $result['message_id'],
            'whatsapp' => $result['whatsapp'],
        ]);
    }

    /** Historique complet de la conversation d'une session (rechargé à l'ouverture du widget). */
    public function history(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (!RateLimiter::allow('history', RateLimiter::clientKey($request), 30)) {
            return new WP_Error('bem_rate_limited', 'Rate limited', ['status' => 429]);
        }
        $sessionId = sanitize_text_field((string) $request->get_param('session_id'));
        $lead = $sessionId !== '' ? (new LeadRepository())->findBySessionId($sessionId) : null;
        if (!$lead) {
            return rest_ensure_response(['messages' => [], 'handoff' => false]);
        }
        $rows = (new ConversationRepository())->history((int) $lead->id, 100);
        $messages = [];
        foreach ($rows as $m) {
            if ($m->canal === 'whatsapp') {
                continue; // les échanges WhatsApp se poursuivent hors du widget
            }
            $messages[] = [
                'id' => (int) $m->id,
                'role' => $m->role,
                'contenu' => $m->contenu,
            ];
        }
        return rest_ensure_response([
            'messages' => $messages,
            'handoff' => (int) $lead->handoff_active === 1,
        ]);
    }

    /** Polling du widget : nouveaux messages (relances proactives, réponses conseiller). */
    public function messages(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (!RateLimiter::allow('messages', RateLimiter::clientKey($request), 60)) {
            return new WP_Error('bem_rate_limited', 'Rate limited', ['status' => 429]);
        }
        $sessionId = sanitize_text_field((string) $request->get_param('session_id'));
        $afterId = (int) $request->get_param('after_id');
        $lead = (new LeadRepository())->findBySessionId($sessionId);
        if (!$lead) {
            return rest_ensure_response(['messages' => [], 'handoff' => false]);
        }
        $messages = array_values(array_filter(
            (new ConversationRepository())->since((int) $lead->id, $afterId),
            fn($m) => $m->role !== 'user' && $m->canal !== 'whatsapp'
        ));
        return rest_ensure_response([
            'messages' => array_map(fn($m) => [
                'id' => (int) $m->id,
                'role' => $m->role,
                'contenu' => $m->contenu,
            ], $messages),
            'handoff' => (int) $lead->handoff_active === 1,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Tracking comportemental                                             */
    /* ------------------------------------------------------------------ */

    public function track(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        // Trafic non humain : jamais de création de lead ni d'événement.
        if (\BemLeadAi\Core\BotDetector::isBot()) {
            return rest_ensure_response(['ok' => false, 'reason' => 'bot']);
        }
        if (!RateLimiter::allow('track', RateLimiter::clientKey($request), 60)) {
            return new WP_Error('bem_rate_limited', 'Rate limited', ['status' => 429]);
        }

        $sessionId = sanitize_text_field((string) $request->get_param('session_id'));
        $type = sanitize_key((string) $request->get_param('type'));
        $allowed = ['page_view', 'time_on_page', 'cta_click', 'brochure_download', 'return_visit', 'consent_given', 'email_captured', 'phone_captured'];
        if ($sessionId === '' || !in_array($type, $allowed, true)) {
            return new WP_Error('bem_bad_request', 'Requête invalide.', ['status' => 400]);
        }

        $adapter = new ChannelAdapter();
        $leads = new LeadRepository();
        $payload = $request->get_param('payload');
        $payload = is_array($payload) ? array_map('sanitize_text_field', array_filter($payload, 'is_scalar')) : [];

        // RÈGLE CLÉ : un lead n'est JAMAIS créé par la navigation ou l'acceptation
        // du bandeau. Seul un ENGAGEMENT réel crée une fiche : message de chat,
        // formulaire, ou communication de coordonnées. Résultat : plus de leads
        // « anonymes » à score 0/1 issus de simples visites (ou de bots à UA
        // de navigateur qui échappent au filtre User-Agent).

        // Coordonnées fournies = engagement → on crée/compléte le lead.
        if ($type === 'email_captured' && !empty($payload['email'])) {
            $lead = $leads->findBySessionId($sessionId) ?: $adapter->resolveWebLead($sessionId);
            $leads->update((int) $lead->id, ['consent' => 1]);
            $adapter->attachIdentity($lead, (string) $payload['email'], null);
            $leads->applyUtm((int) $lead->id, $this->utmFromRequest($request));
            return rest_ensure_response(['ok' => true]);
        }
        if ($type === 'phone_captured' && !empty($payload['phone'])) {
            $lead = $leads->findBySessionId($sessionId) ?: $adapter->resolveWebLead($sessionId);
            $leads->update((int) $lead->id, ['consent' => 1]);
            $adapter->attachIdentity($lead, null, (string) $payload['phone']);
            $leads->applyUtm((int) $lead->id, $this->utmFromRequest($request));
            return rest_ensure_response(['ok' => true]);
        }

        // Tous les autres événements (consentement, page vue, temps, CTA…) ne
        // sont enregistrés que pour un lead DÉJÀ engagé — sinon on ne crée rien.
        $lead = $leads->findBySessionId($sessionId);
        if (!$lead) {
            return rest_ensure_response(['ok' => false, 'reason' => 'not_engaged']);
        }

        if ($type === 'consent_given') {
            $leads->update((int) $lead->id, ['consent' => 1]);
            return rest_ensure_response(['ok' => true]);
        }

        (new EventRepository())->record((int) $lead->id, $type, $payload, 'web');
        return rest_ensure_response(['ok' => true]);
    }

    /* ------------------------------------------------------------------ */
    /* WhatsApp — passerelle click-to-chat                                 */
    /* ------------------------------------------------------------------ */

    /**
     * Renvoie le lien wa.me pré-rempli pour continuer sur WhatsApp, et trace
     * le clic comme signal de forte intention.
     */
    public function whatsappLink(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (\BemLeadAi\Core\BotDetector::isBot()) {
            return new WP_Error('bem_forbidden', 'Forbidden', ['status' => 403]);
        }
        if (!RateLimiter::allow('whatsapp', RateLimiter::clientKey($request), 30)) {
            return new WP_Error('bem_rate_limited', 'Rate limited', ['status' => 429]);
        }
        $handoff = new WhatsAppHandoff();
        if (!$handoff->isEnabled()) {
            return new WP_Error('bem_disabled', __('WhatsApp non configuré.', 'bem-lead-ai'), ['status' => 404]);
        }

        // Lecture seule : on ne crée pas de lead depuis un clic WhatsApp isolé.
        $sessionId = sanitize_text_field((string) $request->get_param('session_id'));
        $lead = $sessionId !== '' ? (new LeadRepository())->findBySessionId($sessionId) : null;
        if (!$lead) {
            return new WP_Error('bem_bad_request', 'Session invalide.', ['status' => 400]);
        }

        $link = $handoff->buildLink($lead);
        if (!$link) {
            return new WP_Error('bem_disabled', 'Aucun numéro disponible.', ['status' => 404]);
        }

        // Clic = signal de forte intention (alimente scoring + triggers).
        (new LeadRepository())->touch((int) $lead->id, 'whatsapp');
        (new EventRepository())->record((int) $lead->id, 'whatsapp_handoff_clicked', [
            'number' => $link['number'],
        ], 'whatsapp');

        // On renvoie AUSSI la liste complète : si l'école a plusieurs numéros,
        // le widget affiche un choix plutôt qu'une redirection unique.
        $link['numbers'] = $handoff->allLinks($lead);
        return rest_ensure_response($link);
    }

    /* ------------------------------------------------------------------ */
    /* Admin / intégrations                                                */
    /* ------------------------------------------------------------------ */

    public function lead(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $lead = (new LeadRepository())->findBySessionId((string) $request['session_id']);
        if (!$lead) {
            return new WP_Error('bem_not_found', 'Lead introuvable.', ['status' => 404]);
        }
        return rest_ensure_response([
            'id' => (int) $lead->id,
            'scores' => [
                'comportemental' => (float) $lead->score_comportemental,
                'intention' => (float) $lead->score_intention,
                'final' => (float) $lead->score_final,
                'bande' => ScoringEngine::band((float) $lead->score_final),
            ],
            'statut' => $lead->statut,
            'kb_mode' => $lead->kb_mode,
            'formation_interet' => $lead->formation_interet,
            'channels' => $lead->channels,
            'signals' => $lead->signals ? json_decode($lead->signals, true) : null,
        ]);
    }

    public function handoffReply(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $message = trim(wp_strip_all_tags((string) $request->get_param('message')));
        if ($message === '') {
            return new WP_Error('bem_bad_request', 'Message vide.', ['status' => 400]);
        }
        $ok = (new HandoffManager())->reply((int) $request['lead_id'], $message, get_current_user_id());
        return $ok
            ? rest_ensure_response(['ok' => true])
            : new WP_Error('bem_not_found', 'Lead introuvable.', ['status' => 404]);
    }

    public function handoffClose(WP_REST_Request $request): WP_REST_Response
    {
        (new HandoffManager())->close((int) $request['handoff_id']);
        return rest_ensure_response(['ok' => true]);
    }

    /**
     * Réponse d'un conseiller envoyée depuis la fiche lead du module Perfex
     * (School IA Bridge), authentifiée par le secret partagé du pont plutôt
     * que par une session WordPress connectée.
     */
    public function handoffReplyFromCrm(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $secret = (string) Options::get('perfex_bridge_secret');
        $provided = (string) $request->get_header('X-SIA-Secret');
        if ($provided === '') {
            $provided = (string) $request->get_param('secret');
        }
        if ($secret === '' || !hash_equals($secret, $provided)) {
            return new WP_Error('bem_forbidden', 'Secret invalide.', ['status' => 401]);
        }

        $leadId = (int) $request->get_param('lead_id');
        $message = trim(wp_strip_all_tags((string) $request->get_param('message')));
        if ($leadId <= 0 || $message === '') {
            return new WP_Error('bem_bad_request', 'Paramètres invalides.', ['status' => 400]);
        }

        $messageId = (new HandoffManager())->reply($leadId, $message, 0);
        return $messageId > 0
            ? rest_ensure_response(['ok' => true, 'message_id' => $messageId])
            : new WP_Error('bem_not_found', 'Lead introuvable.', ['status' => 404]);
    }

    /**
     * Webhook retour CRM : lead passé « inscrit » → bascule onboarding.
     * Authentifié par secret partagé (header X-Bem-Secret).
     */
    public function crmStatusWebhook(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $secret = (string) Options::get('crm_webhook_secret');
        if ($secret === '' || !hash_equals($secret, (string) $request->get_header('x-bem-secret'))) {
            return new WP_Error('bem_forbidden', 'Secret invalide.', ['status' => 403]);
        }

        $leads = new LeadRepository();
        $lead = null;
        if ($crmId = $request->get_param('crm_id_perfex')) {
            global $wpdb;
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}bem_leads WHERE crm_id_perfex = %s",
                sanitize_text_field((string) $crmId)
            ));
            $lead = $row ?: null;
        }
        if (!$lead && ($email = $request->get_param('email'))) {
            $lead = $leads->findByEmail(sanitize_email((string) $email));
        }
        if (!$lead) {
            return new WP_Error('bem_not_found', 'Lead introuvable.', ['status' => 404]);
        }

        $statut = sanitize_key((string) $request->get_param('statut'));
        if ($statut === 'inscrit') {
            $leads->update((int) $lead->id, ['statut' => 'inscrit']);
            (new EventRepository())->record((int) $lead->id, 'crm_status_inscrit', [], 'system');
        }

        return rest_ensure_response(['ok' => true]);
    }

    public function financingSimulate(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (!RateLimiter::allow('financing', RateLimiter::clientKey($request), 30)) {
            return new WP_Error('bem_rate_limited', 'Rate limited', ['status' => 429]);
        }
        $result = (new FinancingSimulator())->simulate(
            (int) $request->get_param('option_id'),
            (int) $request->get_param('months')
        );
        if (!$result) {
            return new WP_Error('bem_bad_request', 'Simulation impossible.', ['status' => 400]);
        }

        // La simulation est un signal d'intention fort : tracé si session fournie.
        $sessionId = sanitize_text_field((string) $request->get_param('session_id'));
        if ($sessionId !== '') {
            $lead = (new LeadRepository())->findBySessionId($sessionId);
            if ($lead && (int) $lead->consent === 1) {
                (new EventRepository())->record((int) $lead->id, 'financing_simulated', ['option_id' => (int) $request->get_param('option_id')], 'web');
            }
        }
        return rest_ensure_response($result);
    }
}

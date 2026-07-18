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
    }

    /* ------------------------------------------------------------------ */
    /* Chat web                                                            */
    /* ------------------------------------------------------------------ */

    public function chat(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
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

        // Écrire au chat vaut consentement d'échange ; le tracking navigation
        // reste soumis au consentement explicite du bandeau.
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
        $lead = $adapter->resolveWebLead($sessionId);
        $leads = new LeadRepository();

        if ($type === 'consent_given') {
            $leads->update((int) $lead->id, ['consent' => 1]);
            return rest_ensure_response(['ok' => true]);
        }

        // Loi n°2008-12 : pas de tracking comportemental sans consentement.
        if ((int) $lead->consent !== 1) {
            return rest_ensure_response(['ok' => false, 'reason' => 'no_consent']);
        }

        $payload = $request->get_param('payload');
        $payload = is_array($payload) ? array_map('sanitize_text_field', array_filter($payload, 'is_scalar')) : [];

        if ($type === 'email_captured' && !empty($payload['email'])) {
            $adapter->attachIdentity($lead, (string) $payload['email'], null);
            return rest_ensure_response(['ok' => true]);
        }
        if ($type === 'phone_captured' && !empty($payload['phone'])) {
            $adapter->attachIdentity($lead, null, (string) $payload['phone']);
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
        if (!RateLimiter::allow('whatsapp', RateLimiter::clientKey($request), 30)) {
            return new WP_Error('bem_rate_limited', 'Rate limited', ['status' => 429]);
        }
        $handoff = new WhatsAppHandoff();
        if (!$handoff->isEnabled()) {
            return new WP_Error('bem_disabled', __('WhatsApp non configuré.', 'bem-lead-ai'), ['status' => 404]);
        }

        $sessionId = sanitize_text_field((string) $request->get_param('session_id'));
        $lead = $sessionId !== '' ? (new ChannelAdapter())->resolveWebLead($sessionId) : null;
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

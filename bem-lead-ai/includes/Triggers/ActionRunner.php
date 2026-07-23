<?php

namespace BemLeadAi\Triggers;

use BemLeadAi\Ai\Summarizer;
use BemLeadAi\Chat\ConversationRepository;
use BemLeadAi\Core\Options;
use BemLeadAi\Crm\HubSpotConnector;
use BemLeadAi\Crm\PerfexBridgeConnector;
use BemLeadAi\Crm\PerfexConnector;
use BemLeadAi\Financing\FinancingSimulator;
use BemLeadAi\Handoff\HandoffManager;
use BemLeadAi\Leads\EventRepository;
use BemLeadAi\Leads\LeadRepository;
use BemLeadAi\Learning\VariantBandit;
use BemLeadAi\Scoring\ScoringEngine;

defined('ABSPATH') || exit;

/**
 * Exécute les actions déclenchées par le TriggerEngine, hors du flux de la
 * requête (file asynchrone avec retries).
 */
final class ActionRunner
{
    public function run(string $type, int $leadId, array $args = []): void
    {
        $lead = (new LeadRepository())->findById($leadId);
        if (!$lead) {
            return;
        }

        match ($type) {
            'crm_sync' => $this->crmSync($lead),
            'sync_chat_message' => $this->syncChatMessage($lead, $args),
            'sync_competitor' => $this->syncCompetitor($lead, $args),
            'notify' => $this->notify($lead, $args),
            'notify_new_conversation' => $this->notifyNewConversation($lead, $args),
            'escalate' => (new HandoffManager())->open($lead, (string) ($args['motif'] ?? __('Escalade automatique', 'bem-lead-ai'))),
            'followup' => $this->followup($lead, (string) ($args['variant_group'] ?? 'relance_desengagement')),
            'financing_offer' => $this->financingOffer($lead),
            'summarize' => $this->summarizeToCrm($lead),
            'switch_kb' => $this->switchKb($lead, (string) ($args['kb'] ?? 'onboarding')),
            default => error_log('[bem-lead-ai] Action inconnue: ' . $type),
        };
    }

    private function crmSync(object $lead): void
    {
        // Pont School IA (module Perfex maison, gratuit) — prioritaire s'il est
        // configuré ; sinon on tente l'API REST Perfex.
        $bridge = new PerfexBridgeConnector();
        if ($bridge->isConfigured()) {
            $bridge->upsertLead($lead);
        } else {
            $perfex = new PerfexConnector();
            if ($perfex->isConfigured()) {
                $perfex->upsertLead($lead);
            }
        }
        $hubspot = new HubSpotConnector();
        if ($hubspot->isConfigured()) {
            $hubspot->upsertLead($lead);
        }
    }

    /** Envoie un message de la conversation IA vers le pont Perfex (hors du flux de chat). */
    private function syncChatMessage(object $lead, array $args): void
    {
        $bridge = new PerfexBridgeConnector();
        if (!$bridge->isConfigured()) {
            return;
        }
        $bridge->sendChatMessage(
            (int) $lead->id,
            (int) ($args['message_id'] ?? 0),
            (string) ($args['role'] ?? ''),
            (string) ($args['content'] ?? ''),
            (string) ($args['canal'] ?? 'web')
        );
    }

    /** Envoie une mention de concurrent vers le pont Perfex (hors du flux). */
    private function syncCompetitor(object $lead, array $args): void
    {
        $bridge = new PerfexBridgeConnector();
        if (!$bridge->isConfigured()) {
            return;
        }
        // Le lead doit exister côté Perfex : on l'y pousse d'abord si besoin.
        $bridge->upsertLead($lead);
        $bridge->sendCompetitorMention(
            (int) $lead->id,
            (int) ($args['mention_id'] ?? 0),
            (string) ($args['name'] ?? ''),
            (string) ($args['context'] ?? '')
        );
    }

    private function notify(object $lead, array $args): void
    {
        $subject = (string) ($args['subject'] ?? __('Alerte lead', 'bem-lead-ai'));
        $band = ScoringEngine::band((float) $lead->score_final);
        $adminUrl = admin_url('admin.php?page=bem-lead-ai-leads&lead_id=' . (int) $lead->id);

        // E-mail HTML brandé (respecte l'interrupteur « lead chaud »).
        $bodyHtml = '<p>' . esc_html__('Un lead mérite votre attention :', 'bem-lead-ai') . '</p>'
            . \BemLeadAi\Notifications\Mailer::detailList([
                __('Lead', 'bem-lead-ai') => '#' . (int) $lead->id,
                __('Score', 'bem-lead-ai') => $lead->score_final . '/100 (' . str_replace('_', ' ', $band) . ')',
                __('Formation d\'intérêt', 'bem-lead-ai') => $lead->formation_interet ?: '—',
                __('Email', 'bem-lead-ai') => $lead->email ?: '—',
                __('Téléphone', 'bem-lead-ai') => $lead->phone ?: '—',
                __('Canaux', 'bem-lead-ai') => $lead->channels,
            ]);
        \BemLeadAi\Notifications\Mailer::sendEvent('hot_lead', $subject, $bodyHtml, $adminUrl, __('Ouvrir la fiche lead', 'bem-lead-ai'));

        // Slack (texte), inchangé.
        $slack = (string) Options::get('slack_webhook_url');
        if ($slack) {
            $text = sprintf("*%s*\nLead #%d — score %s/100 (%s)\n%s",
                $subject, (int) $lead->id, $lead->score_final, str_replace('_', ' ', $band), $adminUrl);
            wp_remote_post($slack, [
                'timeout' => 10,
                'headers' => ['Content-Type' => 'application/json'],
                'body' => wp_json_encode(['text' => $text]),
            ]);
        }
    }

    /**
     * Nouvelle conversation : un prospect vient d'engager le chat. On prévient
     * les conseillers par e-mail (gabarit brandé, couleurs de base) pour une
     * prise en charge rapide, avec un extrait du premier message et un lien
     * direct vers l'inbox.
     */
    private function notifyNewConversation(object $lead, array $args): void
    {
        $inbox    = admin_url('admin.php?page=bem-lead-ai-inbox');
        $canal    = (string) ($args['canal'] ?? 'web');
        $first    = trim((string) ($args['message'] ?? ''));
        $excerpt  = $first !== '' ? wp_html_excerpt($first, 240, '…') : '—';
        $band     = ScoringEngine::band((float) $lead->score_final);

        $bodyHtml = '<p>' . esc_html__('Un prospect vient de démarrer une conversation. Prenez-la en charge dès que possible.', 'bem-lead-ai') . '</p>'
            . \BemLeadAi\Notifications\Mailer::detailList([
                __('Lead', 'bem-lead-ai') => '#' . (int) $lead->id . ($lead->prenom ? ' — ' . $lead->prenom : ''),
                __('Contact', 'bem-lead-ai') => $lead->email ?: ($lead->phone ?: __('non communiqué', 'bem-lead-ai')),
                __('Formation d\'intérêt', 'bem-lead-ai') => $lead->formation_interet ?: '—',
                __('Score', 'bem-lead-ai') => $lead->score_final . '/100 (' . str_replace('_', ' ', $band) . ')',
                __('Canal', 'bem-lead-ai') => $canal,
            ])
            . '<p style="margin:14px 0 4px;color:#8a93a6;font-size:13px;">' . esc_html__('Premier message :', 'bem-lead-ai') . '</p>'
            . '<blockquote style="margin:0;padding:10px 14px;border-radius:8px;background:#f3f5f9;color:#1f2430;font-size:14px;font-style:italic;">'
            . esc_html($excerpt) . '</blockquote>';

        \BemLeadAi\Notifications\Mailer::sendEvent(
            'new_conversation',
            __('Nouvelle conversation — prise en charge', 'bem-lead-ai'),
            $bodyHtml,
            $inbox,
            __('Prendre en charge dans l\'inbox', 'bem-lead-ai')
        );
    }

    /**
     * Relance automatique : la variante est choisie par le bandit
     * (epsilon-greedy) parmi les formulations testées en continu.
     */
    private function followup(object $lead, string $variantGroup): void
    {
        $bandit = new VariantBandit();
        $variant = $bandit->pick($variantGroup);
        if (!$variant) {
            return;
        }

        $message = strtr($variant->texte_variante, [
            '{prenom}' => $lead->prenom ?: 'bonjour',
            '{formation}' => $lead->formation_interet ?: 'nos formations',
        ]);

        $delivered = $this->deliverMessage($lead, $message);
        if ($delivered) {
            $bandit->recordImpression((int) $variant->id);
            (new EventRepository())->record((int) $lead->id, 'followup_sent', [
                'variant_id' => (int) $variant->id,
                'variant_group' => $variantGroup,
                'channel' => $delivered,
            ], 'system');
        }
    }

    private function financingOffer(object $lead): void
    {
        $simulator = new FinancingSimulator();
        $message = $simulator->buildOfferMessage($lead);
        if ($message && $this->deliverMessage($lead, $message)) {
            (new EventRepository())->record((int) $lead->id, 'financing_offer_sent', [], 'system');
        }
    }

    private function summarizeToCrm(object $lead): void
    {
        $summary = (new Summarizer())->summarize((int) $lead->id);
        if (!$summary) {
            return;
        }
        update_option('bem_lead_ai_last_summary_' . (int) $lead->id, $summary, false);

        $perfex = new PerfexConnector();
        if ($perfex->isConfigured()) {
            $perfex->addNote($lead, $summary);
        }
    }

    private function switchKb(object $lead, string $kb): void
    {
        (new LeadRepository())->update((int) $lead->id, [
            'kb_mode' => $kb === 'onboarding' ? 'onboarding' : 'formations',
            'statut' => $kb === 'onboarding' ? 'inscrit' : $lead->statut,
        ]);
    }

    /**
     * Livraison d'un message proactif (relance, offre de financement) :
     * déposé dans le fil de chat — le widget l'affiche à la prochaine visite —
     * et doublé par email si l'adresse est connue.
     *
     * @return string|false Canal principal utilisé.
     */
    private function deliverMessage(object $lead, string $message): string|false
    {
        (new ConversationRepository())->add((int) $lead->id, 'assistant', $message, 'web');

        if ($lead->email) {
            $school = trim((string) Options::get('school_name')) ?: 'BEM Conakry';
            wp_mail(
                (string) $lead->email,
                sprintf(
                    /* translators: %s = nom de l'école */
                    __('%s — nous restons à votre écoute', 'bem-lead-ai'),
                    $school
                ),
                $message . "\n\n" . home_url()
            );
        }

        return 'web';
    }
}

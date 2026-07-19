<?php

namespace BemLeadAi\Triggers;

use BemLeadAi\Ai\Summarizer;
use BemLeadAi\Chat\ConversationRepository;
use BemLeadAi\Core\Options;
use BemLeadAi\Crm\HubSpotConnector;
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
            'notify' => $this->notify($lead, $args),
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
        $perfex = new PerfexConnector();
        if ($perfex->isConfigured()) {
            $perfex->upsertLead($lead);
        }
        $hubspot = new HubSpotConnector();
        if ($hubspot->isConfigured()) {
            $hubspot->upsertLead($lead);
        }
    }

    private function notify(object $lead, array $args): void
    {
        $subject = (string) ($args['subject'] ?? __('Alerte lead BEM', 'bem-lead-ai'));
        $band = ScoringEngine::band((float) $lead->score_final);
        $adminUrl = admin_url('admin.php?page=bem-lead-ai-leads&lead_id=' . (int) $lead->id);

        $lines = [
            sprintf('Lead #%d — score %s/100 (%s)', $lead->id, $lead->score_final, str_replace('_', ' ', $band)),
            'Formation d\'intérêt : ' . ($lead->formation_interet ?: '—'),
            'Email : ' . ($lead->email ?: '—') . ' | Téléphone : ' . ($lead->phone ?: '—'),
            'Canaux : ' . $lead->channels,
            'Fiche : ' . $adminUrl,
        ];
        $body = implode("\n", $lines);

        $to = (string) Options::get('admissions_email');
        if ($to) {
            $brand = defined('BEM_LEAD_AI_BRAND') ? BEM_LEAD_AI_BRAND : 'School IA';
            wp_mail($to, '[' . $brand . '] ' . $subject, $body);
        }

        $slack = (string) Options::get('slack_webhook_url');
        if ($slack) {
            wp_remote_post($slack, [
                'timeout' => 10,
                'headers' => ['Content-Type' => 'application/json'],
                'body' => wp_json_encode(['text' => '*' . $subject . "*\n" . $body]),
            ]);
        }
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

<?php

namespace BemLeadAi\Handoff;

use BemLeadAi\Chat\ConversationRepository;
use BemLeadAi\Core\Options;
use BemLeadAi\Crm\PerfexBridgeConnector;
use BemLeadAi\Leads\LeadRepository;

defined('ABSPATH') || exit;

/**
 * Escalade humaine en temps réel : la conversation passe en mode « prise en
 * main humaine », l'IA se met en pause sur ce fil, un conseiller est alerté
 * et répond depuis l'inbox admin.
 */
final class HandoffManager
{
    public function open(object $lead, string $motif): void
    {
        global $wpdb;
        $p = $wpdb->prefix;

        // Un seul handoff ouvert par lead.
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$p}bem_handoffs WHERE lead_id = %d AND statut IN ('open','assigned')",
            (int) $lead->id
        ));
        if ($existing) {
            return;
        }

        $wpdb->insert("{$p}bem_handoffs", [
            'lead_id' => (int) $lead->id,
            'motif' => $motif,
            'statut' => 'open',
            'opened_at' => current_time('mysql'),
        ]);

        (new LeadRepository())->update((int) $lead->id, ['handoff_active' => 1]);
        $this->syncToPerfex((int) $lead->id, true, $motif);

        // Message de transition côté prospect : rassurer, pas de vide.
        $transition = __("Je vous mets en relation avec un conseiller de l'équipe admissions qui va prendre le relais tout de suite. Un instant…", 'bem-lead-ai');
        (new ConversationRepository())->add((int) $lead->id, 'assistant', $transition, 'web');

        // Alerte immédiate des conseillers (email HTML brandé + Slack).
        $inbox = admin_url('admin.php?page=bem-lead-ai-inbox');
        $brand = defined('BEM_LEAD_AI_BRAND') ? BEM_LEAD_AI_BRAND : 'School IA';

        $bodyHtml = '<p style="color:#d63638;font-weight:600;">' . esc_html__('Un prospect demande à parler à un conseiller humain.', 'bem-lead-ai') . '</p>'
            . \BemLeadAi\Notifications\Mailer::detailList([
                __('Lead', 'bem-lead-ai') => '#' . (int) $lead->id . ' — ' . ($lead->email ?: ($lead->phone ?: __('contact inconnu', 'bem-lead-ai'))),
                __('Motif', 'bem-lead-ai') => $motif,
                __('Score', 'bem-lead-ai') => $lead->score_final . '/100',
            ]);
        \BemLeadAi\Notifications\Mailer::sendEvent('handoff', __('Escalade humaine — réponse attendue', 'bem-lead-ai'), $bodyHtml, $inbox, __('Répondre dans l\'inbox', 'bem-lead-ai'));

        $slack = (string) Options::get('slack_webhook_url');
        if ($slack) {
            $text = sprintf("*%s*\nLead #%d — %s\nMotif : %s\n%s",
                sprintf(__('Escalade humaine — %s', 'bem-lead-ai'), $brand),
                (int) $lead->id, $lead->email ?: ($lead->phone ?: '—'), $motif, $inbox);
            wp_remote_post($slack, [
                'timeout' => 10,
                'headers' => ['Content-Type' => 'application/json'],
                'body' => wp_json_encode(['text' => $text]),
            ]);
        }
    }

    /**
     * Réponse d'un conseiller (inbox WordPress, ou conseiller côté Perfex via
     * le pont retour) : affichée dans le fil de chat web. Ouvre/active
     * silencieusement la prise en main humaine si aucune escalade n'était
     * déjà en cours (l'IA se met en pause sur ce fil).
     *
     * @return int Id du message créé (0 si le lead est introuvable).
     */
    public function reply(int $leadId, string $message, int $agentUserId): int
    {
        $lead = (new LeadRepository())->findById($leadId);
        if (!$lead) {
            return 0;
        }
        global $wpdb;
        $p = $wpdb->prefix;

        $openId = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$p}bem_handoffs WHERE lead_id = %d AND statut IN ('open','assigned')",
            $leadId
        ));
        if ($openId) {
            $wpdb->update("{$p}bem_handoffs", [
                'statut' => 'assigned',
                'conseiller_id' => $agentUserId,
            ], ['id' => $openId]);
        } else {
            $wpdb->insert("{$p}bem_handoffs", [
                'lead_id' => $leadId,
                'motif' => __('Prise en charge manuelle par un conseiller', 'bem-lead-ai'),
                'statut' => 'assigned',
                'conseiller_id' => $agentUserId,
                'opened_at' => current_time('mysql'),
            ]);
        }
        (new LeadRepository())->update($leadId, ['handoff_active' => 1]);

        // Si la réponse vient d'un conseiller WordPress (pas du pont retour
        // Perfex, qui connaît déjà l'état côté Perfex — inutile de le renvoyer).
        if ($agentUserId > 0) {
            $this->syncToPerfex($leadId, true);
        }

        return (new ConversationRepository())->add($leadId, 'agent', $message, 'web');
    }

    /** Clôture : l'IA reprend la main sur le fil. */
    public function close(int $handoffId): void
    {
        global $wpdb;
        $p = $wpdb->prefix;
        $handoff = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}bem_handoffs WHERE id = %d", $handoffId));
        if (!$handoff) {
            return;
        }
        $wpdb->update("{$p}bem_handoffs", [
            'statut' => 'closed',
            'closed_at' => current_time('mysql'),
        ], ['id' => $handoffId]);
        (new LeadRepository())->update((int) $handoff->lead_id, ['handoff_active' => 0]);
        $this->syncToPerfex((int) $handoff->lead_id, false);
    }

    /** Clôture par id de lead (utilisé par le pont retour Perfex, qui ne connaît que le lead). */
    public function closeByLead(int $leadId): bool
    {
        global $wpdb;
        $p = $wpdb->prefix;
        $handoffId = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$p}bem_handoffs WHERE lead_id = %d AND statut IN ('open','assigned')",
            $leadId
        ));
        if (!$handoffId) {
            // Pas d'escalade formelle ouverte : on désactive quand même la
            // prise en main humaine si elle l'était (réponse manuelle sans
            // handoff), pour que l'IA reprenne la main.
            (new LeadRepository())->update($leadId, ['handoff_active' => 0]);
            return true;
        }
        $wpdb->update("{$p}bem_handoffs", [
            'statut' => 'closed',
            'closed_at' => current_time('mysql'),
        ], ['id' => $handoffId]);
        (new LeadRepository())->update($leadId, ['handoff_active' => 0]);
        return true;
    }

    /**
     * Filet de sécurité (cron horaire) : renvoie l'état de TOUTES les
     * escalades actuellement ouvertes/assignées vers Perfex. Rattrape les
     * handoffs déjà en cours au moment de l'installation du pont retour, une
     * coupure réseau ponctuelle, ou un pont mal configuré au moment exact de
     * l'escalade (ex. secret changé entre-temps puis corrigé).
     */
    public function resyncActiveToPerfex(): void
    {
        $bridge = new PerfexBridgeConnector();
        if (!$bridge->isConfigured()) {
            return;
        }
        global $wpdb;
        $p = $wpdb->prefix;
        $rows = $wpdb->get_results(
            "SELECT lead_id, motif FROM {$p}bem_handoffs WHERE statut IN ('open','assigned')"
        ) ?: [];
        foreach ($rows as $row) {
            $bridge->syncHandoffStatus((int) $row->lead_id, true, (string) $row->motif);
        }
    }

    /** Répercute l'état de la prise en main humaine côté Perfex (pont retour), si configuré. */
    private function syncToPerfex(int $leadId, bool $active, string $motif = ''): void
    {
        $bridge = new PerfexBridgeConnector();
        if ($bridge->isConfigured()) {
            $bridge->syncHandoffStatus($leadId, $active, $motif);
        }
    }
}

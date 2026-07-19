<?php

namespace BemLeadAi\Handoff;

use BemLeadAi\Chat\ConversationRepository;
use BemLeadAi\Core\Options;
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

        // Message de transition côté prospect : rassurer, pas de vide.
        $transition = __("Je vous mets en relation avec un conseiller de l'équipe admissions qui va prendre le relais tout de suite. Un instant…", 'bem-lead-ai');
        (new ConversationRepository())->add((int) $lead->id, 'assistant', $transition, 'web');

        // Alerte immédiate des conseillers (email + Slack).
        $inbox = admin_url('admin.php?page=bem-lead-ai-inbox');
        $body = sprintf(
            "Escalade humaine demandée.\nLead #%d — %s\nMotif : %s\nScore : %s/100\nRépondre : %s",
            $lead->id,
            $lead->email ?: ($lead->phone ?: 'contact inconnu'),
            $motif,
            $lead->score_final,
            $inbox
        );
        $brand = defined('BEM_LEAD_AI_BRAND') ? BEM_LEAD_AI_BRAND : 'School IA';
        $to = (string) Options::get('admissions_email');
        if ($to) {
            wp_mail($to, sprintf(__('[%s] Escalade humaine — réponse attendue', 'bem-lead-ai'), $brand), $body);
        }
        $slack = (string) Options::get('slack_webhook_url');
        if ($slack) {
            wp_remote_post($slack, [
                'timeout' => 10,
                'headers' => ['Content-Type' => 'application/json'],
                'body' => wp_json_encode(['text' => "*" . sprintf(__('Escalade humaine — %s', 'bem-lead-ai'), $brand) . "*\n" . $body]),
            ]);
        }
    }

    /** Réponse d'un conseiller depuis l'inbox : affichée dans le fil de chat web. */
    public function reply(int $leadId, string $message, int $agentUserId): bool
    {
        $lead = (new LeadRepository())->findById($leadId);
        if (!$lead) {
            return false;
        }
        global $wpdb;
        $p = $wpdb->prefix;
        $wpdb->query($wpdb->prepare(
            "UPDATE {$p}bem_handoffs SET statut = 'assigned', conseiller_id = %d
             WHERE lead_id = %d AND statut = 'open'",
            $agentUserId,
            $leadId
        ));

        (new ConversationRepository())->add($leadId, 'agent', $message, 'web');
        return true;
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
    }
}

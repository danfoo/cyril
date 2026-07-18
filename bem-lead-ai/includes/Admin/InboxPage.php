<?php

namespace BemLeadAi\Admin;

use BemLeadAi\Chat\ConversationRepository;
use BemLeadAi\Handoff\HandoffManager;
use BemLeadAi\Leads\LeadRepository;

defined('ABSPATH') || exit;

/**
 * Inbox conseiller : handoffs actifs, conversation en direct, réponse
 * relayée automatiquement sur le canal du prospect (web ou WhatsApp).
 */
final class InboxPage
{
    public function render(): void
    {
        global $wpdb;
        $p = $wpdb->prefix;

        echo '<div class="wrap"><h1>' . esc_html__('Inbox conseiller — escalades humaines', 'bem-lead-ai') . '</h1>';
        echo '<p>' . esc_html__('Quand l\'IA détecte une urgence ou une hésitation critique, la conversation arrive ici et l\'IA se met en pause. Votre réponse part instantanément sur le canal du prospect.', 'bem-lead-ai') . '</p>';

        $handoffs = $wpdb->get_results(
            "SELECT h.*, l.email, l.phone, l.prenom, l.score_final, l.channels
             FROM {$p}bem_handoffs h
             JOIN {$p}bem_leads l ON l.id = h.lead_id
             WHERE h.statut IN ('open','assigned')
             ORDER BY h.opened_at ASC"
        ) ?: [];

        if (!$handoffs) {
            echo '<p><em>' . esc_html__('Aucune escalade en attente. 👌', 'bem-lead-ai') . '</em></p></div>';
            return;
        }

        $conversations = new ConversationRepository();
        foreach ($handoffs as $handoff) {
            $contact = $handoff->email ?: ($handoff->phone ?: 'anonyme');
            echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px;margin:16px 0;max-width:780px;">';
            echo '<h2 style="margin-top:0;">Lead #' . (int) $handoff->lead_id . ' — ' . esc_html(($handoff->prenom ? $handoff->prenom . ' · ' : '') . $contact)
                . ' <span style="font-weight:400;color:#646970;">(score ' . esc_html((string) round((float) $handoff->score_final)) . '/100)</span></h2>';
            echo '<p><strong>' . esc_html__('Motif :', 'bem-lead-ai') . '</strong> ' . esc_html($handoff->motif)
                . ' — <em>' . esc_html__('ouvert le', 'bem-lead-ai') . ' ' . esc_html($handoff->opened_at) . '</em></p>';

            $summary = get_option('bem_lead_ai_last_summary_' . (int) $handoff->lead_id);
            if ($summary) {
                echo '<details><summary style="cursor:pointer;font-weight:600;">' . esc_html__('Résumé IA + approche recommandée', 'bem-lead-ai') . '</summary>'
                    . '<div style="white-space:pre-wrap;padding:8px;background:#f6f7f7;border-radius:6px;margin-top:8px;">' . esc_html($summary) . '</div></details>';
            }

            echo '<div style="max-height:280px;overflow:auto;border:1px solid #f0f0f1;border-radius:6px;padding:12px;margin:12px 0;">';
            foreach ($conversations->history((int) $handoff->lead_id, 30) as $m) {
                $who = $m->role === 'user' ? '🧑' : ($m->role === 'agent' ? '👤' : '🤖');
                echo '<p style="margin:6px 0;">' . $who . ' <em style="color:#646970;">' . esc_html($m->created_at) . '</em><br>' . esc_html($m->contenu) . '</p>';
            }
            echo '</div>';

            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('bem_handoff_reply_' . (int) $handoff->lead_id);
            echo '<input type="hidden" name="action" value="bem_handoff_reply">'
                . '<input type="hidden" name="lead_id" value="' . (int) $handoff->lead_id . '">'
                . '<textarea name="message" rows="3" class="large-text" required placeholder="' . esc_attr__('Votre réponse au prospect…', 'bem-lead-ai') . '"></textarea>';
            submit_button(__('Envoyer la réponse', 'bem-lead-ai'), 'primary', 'submit', false);
            echo '</form>';

            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:8px;">';
            wp_nonce_field('bem_handoff_close_' . (int) $handoff->id);
            echo '<input type="hidden" name="action" value="bem_handoff_close">'
                . '<input type="hidden" name="handoff_id" value="' . (int) $handoff->id . '">';
            submit_button(__('Clôturer — l\'IA reprend la main', 'bem-lead-ai'), 'secondary', 'submit', false);
            echo '</form></div>';
        }
        echo '</div>';
    }

    public static function handleReply(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die('Forbidden');
        }
        $leadId = (int) ($_POST['lead_id'] ?? 0);
        check_admin_referer('bem_handoff_reply_' . $leadId);
        $message = trim(wp_strip_all_tags((string) ($_POST['message'] ?? '')));
        if ($message !== '') {
            (new HandoffManager())->reply($leadId, $message, get_current_user_id());
        }
        wp_safe_redirect(admin_url('admin.php?page=bem-lead-ai-inbox'));
        exit;
    }

    public static function handleClose(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die('Forbidden');
        }
        $handoffId = (int) ($_POST['handoff_id'] ?? 0);
        check_admin_referer('bem_handoff_close_' . $handoffId);
        (new HandoffManager())->close($handoffId);
        wp_safe_redirect(admin_url('admin.php?page=bem-lead-ai-inbox'));
        exit;
    }
}

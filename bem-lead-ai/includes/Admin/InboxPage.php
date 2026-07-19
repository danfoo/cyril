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
        echo '<p class="description" style="max-width:820px;">' . esc_html__('Quand l\'IA détecte une urgence ou une hésitation critique, la conversation arrive ici et l\'IA se met en pause. Votre réponse part instantanément sur le canal du prospect.', 'bem-lead-ai') . '</p>';

        $handoffs = $wpdb->get_results(
            "SELECT h.*, l.email, l.phone, l.prenom, l.score_final, l.channels
             FROM {$p}bem_handoffs h
             JOIN {$p}bem_leads l ON l.id = h.lead_id
             WHERE h.statut IN ('open','assigned')
             ORDER BY h.opened_at ASC"
        ) ?: [];

        echo '<div class="bem-inbox">';

        if (!$handoffs) {
            echo '<div class="bem-empty">' . Icons::get('inbox', 'bem-ico bem-empty-ico')
                . '<p><strong>' . esc_html__('Aucune escalade en attente.', 'bem-lead-ai') . '</strong></p>'
                . '<p>' . esc_html__('Tout est sous contrôle — l\'IA gère les conversations en cours.', 'bem-lead-ai') . '</p></div>';
            echo '</div></div>';
            return;
        }

        $conversations = new ConversationRepository();
        foreach ($handoffs as $handoff) {
            $lid = (int) $handoff->lead_id;
            $contact = $handoff->email ?: ($handoff->phone ?: __('anonyme', 'bem-lead-ai'));
            $initial = mb_strtoupper(mb_substr($handoff->prenom ?: '?', 0, 1));
            $leadUrl = admin_url('admin.php?page=bem-lead-ai-leads&lead_id=' . $lid);

            echo '<div class="bem-panel-card bem-inbox-card">';

            // En-tête escalade.
            echo '<div class="bem-inbox-head">';
            echo '<span class="bem-avatar bem-avatar-lg" style="background:#d63638;">' . esc_html($initial) . '</span>';
            echo '<div class="bem-inbox-id"><a href="' . esc_url($leadUrl) . '"><strong>' . esc_html($handoff->prenom ?: __('Lead', 'bem-lead-ai')) . '</strong> #' . $lid . '</a>'
                . '<span class="bem-inbox-contact">' . esc_html($contact) . '</span></div>';
            echo '<div class="bem-inbox-meta">'
                . '<span class="bem-badge bem-badge-urgent">' . Icons::get('clock-alert') . ' ' . esc_html($handoff->motif) . '</span>'
                . '<span class="bem-badge bem-badge-ghost">' . esc_html__('Score', 'bem-lead-ai') . ' ' . esc_html((string) round((float) $handoff->score_final)) . '/100</span>'
                . '<span class="bem-inbox-time">' . esc_html(sprintf(__('ouvert le %s', 'bem-lead-ai'), mysql2date('d/m/Y H:i', $handoff->opened_at))) . '</span>'
                . '</div>';
            echo '</div>';

            // Résumé IA (repliable, mis en évidence).
            $summary = get_option('bem_lead_ai_last_summary_' . $lid);
            if ($summary) {
                echo '<details class="bem-inbox-summary"><summary>' . Icons::get('bulb') . ' ' . esc_html__('Résumé IA & approche recommandée', 'bem-lead-ai') . '</summary>'
                    . '<div class="bem-highlight-body" style="white-space:pre-wrap;margin-top:10px;">' . esc_html($summary) . '</div></details>';
            }

            // Conversation en bulles.
            echo '<div class="bem-inbox-thread">';
            foreach ($conversations->history($lid, 30) as $m) {
                $role = $m->role === 'user' ? 'user' : ($m->role === 'agent' ? 'agent' : 'ai');
                $who = $m->role === 'user' ? __('Prospect', 'bem-lead-ai') : ($m->role === 'agent' ? __('Conseiller', 'bem-lead-ai') : 'IA');
                $ico = Icons::get($m->role === 'user' ? 'user' : ($m->role === 'agent' ? 'headset' : 'cpu'));
                echo '<div class="bem-tl bem-tl-chat bem-msg-' . esc_attr($role) . '"><span class="bem-tl-ico">' . $ico . '</span>'
                    . '<div><div class="bem-tl-meta"><strong>' . esc_html($who) . '</strong> · ' . esc_html(mysql2date('d/m/Y H:i', $m->created_at)) . '</div>'
                    . '<div class="bem-tl-body">' . esc_html($m->contenu) . '</div></div></div>';
            }
            echo '</div>';

            // Réponse au prospect.
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="bem-inbox-reply">';
            wp_nonce_field('bem_handoff_reply_' . $lid);
            echo '<input type="hidden" name="action" value="bem_handoff_reply">'
                . '<input type="hidden" name="lead_id" value="' . $lid . '">'
                . '<textarea name="message" rows="3" class="large-text" required placeholder="' . esc_attr__('Votre réponse au prospect…', 'bem-lead-ai') . '"></textarea>';
            echo '<div class="bem-inbox-actions">'
                . '<button type="submit" class="button button-primary">' . Icons::get('send') . ' ' . esc_html__('Envoyer la réponse', 'bem-lead-ai') . '</button>'
                . '</div>';
            echo '</form>';

            // Clôture de l'escalade.
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="bem-inbox-close">';
            wp_nonce_field('bem_handoff_close_' . (int) $handoff->id);
            echo '<input type="hidden" name="action" value="bem_handoff_close">'
                . '<input type="hidden" name="handoff_id" value="' . (int) $handoff->id . '">';
            echo '<button type="submit" class="button">' . Icons::get('check-circle') . ' ' . esc_html__('Clôturer — l\'IA reprend la main', 'bem-lead-ai') . '</button>';
            echo '</form>';

            echo '</div>'; // /card
        }
        echo '</div></div>'; // /inbox /wrap
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

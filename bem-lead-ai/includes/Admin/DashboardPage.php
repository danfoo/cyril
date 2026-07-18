<?php

namespace BemLeadAi\Admin;

use BemLeadAi\Chat\ConversationRepository;
use BemLeadAi\Leads\LeadRepository;
use BemLeadAi\Privacy\PrivacyManager;
use BemLeadAi\Scoring\ScoringEngine;

defined('ABSPATH') || exit;

/**
 * Tableau de bord : KPIs du funnel + liste des leads triés par score
 * (les plus chauds d'abord — c'est la to-do list de l'équipe admissions).
 */
final class DashboardPage
{
    public function render(): void
    {
        global $wpdb;
        $p = $wpdb->prefix;

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$p}bem_leads");
        $hot = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}bem_leads WHERE score_final >= %f AND statut = 'prospect'", (float) \BemLeadAi\Core\Options::get('threshold_hot')));
        $identified = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$p}bem_leads WHERE email IS NOT NULL OR phone IS NOT NULL");
        $inscrits = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$p}bem_leads WHERE statut = 'inscrit'");
        $openHandoffs = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$p}bem_handoffs WHERE statut IN ('open','assigned')");
        $conversations7d = (int) $wpdb->get_var("SELECT COUNT(DISTINCT lead_id) FROM {$p}bem_chat_messages WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");

        echo '<div class="wrap"><h1>BEM Lead AI — ' . esc_html__('Tableau de bord', 'bem-lead-ai') . '</h1>';

        if (isset($_GET['purged'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Leads de test supprimés.', 'bem-lead-ai') . '</p></div>';
        }

        if ($openHandoffs > 0) {
            echo '<div class="notice notice-warning"><p><strong>' . sprintf(esc_html__('%d escalade(s) humaine(s) en attente dans l\'inbox conseiller.', 'bem-lead-ai'), $openHandoffs)
                . '</strong> <a href="' . esc_url(admin_url('admin.php?page=bem-lead-ai-inbox')) . '">' . esc_html__('Répondre maintenant', 'bem-lead-ai') . '</a></p></div>';
        }

        $cards = [
            [__('Leads total', 'bem-lead-ai'), $total],
            [__('Leads chauds à traiter', 'bem-lead-ai'), $hot],
            [__('Leads identifiés (email/tél.)', 'bem-lead-ai'), $identified],
            [__('Conversations (7 j)', 'bem-lead-ai'), $conversations7d],
            [__('Inscrits', 'bem-lead-ai'), $inscrits],
        ];
        echo '<div style="display:flex;gap:16px;flex-wrap:wrap;margin:16px 0;">';
        foreach ($cards as [$label, $value]) {
            echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px 24px;min-width:160px;">'
                . '<div style="font-size:28px;font-weight:600;">' . esc_html((string) $value) . '</div>'
                . '<div style="color:#646970;">' . esc_html($label) . '</div></div>';
        }
        echo '</div>';

        // Outil de nettoyage des données de test (admin).
        if (current_user_can('manage_options')) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:8px 0 16px;" '
                . 'onsubmit="return confirm(\'' . esc_js(__('Supprimer TOUS les leads, événements et conversations ? (règles, triggers et catalogue conservés)', 'bem-lead-ai')) . '\');">';
            wp_nonce_field('bem_purge_leads');
            echo '<input type="hidden" name="action" value="bem_purge_leads">';
            submit_button(__('Vider les leads (données de test)', 'bem-lead-ai'), 'delete', 'submit', false);
            echo ' <span class="description">' . esc_html__('Remet à zéro leads, événements, conversations et veille. À utiliser après vos tests.', 'bem-lead-ai') . '</span>';
            echo '</form>';
        }

        $this->renderLeadTable(20);
        echo '</div>';
    }

    public static function handlePurgeLeads(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden');
        }
        check_admin_referer('bem_purge_leads');
        global $wpdb;
        $p = $wpdb->prefix;
        foreach (['bem_leads', 'bem_events', 'bem_chat_messages', 'bem_competitor_mentions', 'bem_handoffs'] as $t) {
            $wpdb->query("TRUNCATE TABLE {$p}{$t}");
        }
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'bem_lead_ai_last_summary_%'");
        delete_option('bem_lead_ai_wa_cursor');
        wp_safe_redirect(admin_url('admin.php?page=bem-lead-ai&purged=1'));
        exit;
    }

    public function renderLeads(): void
    {
        $leadId = isset($_GET['lead_id']) ? (int) $_GET['lead_id'] : 0;
        echo '<div class="wrap"><h1>' . esc_html__('Leads', 'bem-lead-ai') . '</h1>';
        if ($leadId > 0) {
            $this->renderLeadDetail($leadId);
        } else {
            $this->renderLeadTable(100);
        }
        echo '</div>';
    }

    private function renderLeadTable(int $limit): void
    {
        global $wpdb;
        $leads = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bem_leads ORDER BY score_final DESC, last_seen DESC LIMIT %d",
            $limit
        )) ?: [];

        echo '<h2>' . esc_html__('Leads par score (les plus chauds d\'abord)', 'bem-lead-ai') . '</h2>';
        echo '<p class="description">' . esc_html__('Cliquez sur une ligne pour ouvrir la fiche complète du lead (coordonnées, scores, conversation).', 'bem-lead-ai') . '</p>';
        echo '<table class="widefat striped bem-clickable-rows"><thead><tr>'
            . '<th>ID</th><th>' . esc_html__('Contact', 'bem-lead-ai') . '</th><th>' . esc_html__('Email', 'bem-lead-ai') . '</th>'
            . '<th>' . esc_html__('Téléphone', 'bem-lead-ai') . '</th><th>' . esc_html__('Formation', 'bem-lead-ai') . '</th>'
            . '<th>' . esc_html__('Score', 'bem-lead-ai') . '</th><th>' . esc_html__('Bande', 'bem-lead-ai') . '</th>'
            . '<th>' . esc_html__('Statut', 'bem-lead-ai') . '</th>'
            . '<th>' . esc_html__('Dernière activité', 'bem-lead-ai') . '</th><th></th></tr></thead><tbody>';

        $bandColors = ['tres_chaud' => '#d63638', 'chaud' => '#dba617', 'tiede' => '#2271b1', 'froid' => '#646970'];
        foreach ($leads as $lead) {
            $band = ScoringEngine::band((float) $lead->score_final);
            $url = admin_url('admin.php?page=bem-lead-ai-leads&lead_id=' . (int) $lead->id);
            $name = $lead->prenom ?: '<em>anonyme</em>';
            echo '<tr style="cursor:pointer;" onclick="window.location=\'' . esc_url($url) . '\';">'
                . '<td><a href="' . esc_url($url) . '"><strong>#' . (int) $lead->id . '</strong></a></td>'
                . '<td>' . wp_kses_post($name) . '</td>'
                . '<td>' . esc_html($lead->email ?: '—') . '</td>'
                . '<td>' . esc_html($lead->phone ?: '—') . '</td>'
                . '<td>' . esc_html($lead->formation_interet ?: '—') . '</td>'
                . '<td><strong>' . esc_html((string) round((float) $lead->score_final)) . '</strong>/100</td>'
                . '<td><span style="color:' . esc_attr($bandColors[$band]) . ';font-weight:600;">' . esc_html(str_replace('_', ' ', $band)) . '</span></td>'
                . '<td>' . esc_html($lead->statut) . '</td>'
                . '<td>' . esc_html($lead->last_seen) . '</td>'
                . '<td><a class="button button-small" href="' . esc_url($url) . '">' . esc_html__('Voir la fiche', 'bem-lead-ai') . ' →</a></td>'
                . '</tr>';
        }
        echo '</tbody></table>';
    }

    private function renderLeadDetail(int $leadId): void
    {
        $lead = (new LeadRepository())->findById($leadId);
        if (!$lead) {
            echo '<p>' . esc_html__('Lead introuvable.', 'bem-lead-ai') . '</p>';
            return;
        }
        $band = ScoringEngine::band((float) $lead->score_final);
        $signals = $lead->signals ? json_decode($lead->signals, true) : [];

        echo '<h2>Lead #' . (int) $lead->id . ' — ' . esc_html($lead->prenom ?: 'anonyme') . '</h2>';
        echo '<table class="widefat" style="max-width:720px;"><tbody>';
        $rows = [
            ['Score final', round((float) $lead->score_final) . '/100 (' . str_replace('_', ' ', $band) . ')'],
            ['Score comportemental', round((float) $lead->score_comportemental)],
            ['Score intention (IA)', round((float) $lead->score_intention)],
            ['Email / Téléphone', ($lead->email ?: '—') . ' / ' . ($lead->phone ?: '—')],
            ['Formation d\'intérêt', $lead->formation_interet ?: '—'],
            ['Canaux', $lead->channels],
            ['Statut / Base de connaissance', $lead->statut . ' / ' . $lead->kb_mode],
            ['Urgence détectée', esc_html($signals['urgency'] ?? 'none')],
            ['Sensibilité prix', !empty($signals['price_sensitivity']) ? 'oui' : 'non'],
            ['Première / dernière visite', $lead->first_seen . ' → ' . $lead->last_seen],
            ['CRM Perfex / HubSpot', ($lead->crm_id_perfex ?: '—') . ' / ' . ($lead->crm_id_hubspot ?: '—')],
        ];
        foreach ($rows as [$label, $value]) {
            echo '<tr><th style="width:240px;text-align:left;">' . esc_html($label) . '</th><td>' . wp_kses_post((string) $value) . '</td></tr>';
        }
        echo '</tbody></table>';

        $summary = get_option('bem_lead_ai_last_summary_' . $leadId);
        if ($summary) {
            echo '<h3>' . esc_html__('Dernier résumé IA + approche recommandée', 'bem-lead-ai') . '</h3>';
            echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px;max-width:720px;white-space:pre-wrap;">' . esc_html($summary) . '</div>';
        }

        echo '<h3>' . esc_html__('Conversation', 'bem-lead-ai') . '</h3>';
        $messages = (new ConversationRepository())->history($leadId, 50);
        echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px;max-width:720px;max-height:400px;overflow:auto;">';
        foreach ($messages as $m) {
            $who = $m->role === 'user' ? '🧑 Prospect' : ($m->role === 'agent' ? '👤 Conseiller' : '🤖 IA');
            echo '<p style="margin:8px 0;"><strong>' . esc_html($who) . '</strong> <em style="color:#646970;">(' . esc_html($m->canal . ' · ' . $m->created_at) . ')</em><br>' . esc_html($m->contenu) . '</p>';
        }
        echo '</div>';

        // Droit à l'oubli (loi n°2008-12).
        if (current_user_can('manage_options')) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:16px;" onsubmit="return confirm(\'' . esc_js(__('Supprimer définitivement ce lead et tout son historique ?', 'bem-lead-ai')) . '\');">';
            wp_nonce_field('bem_lead_delete_' . $leadId);
            echo '<input type="hidden" name="action" value="bem_lead_delete"><input type="hidden" name="lead_id" value="' . (int) $leadId . '">';
            submit_button(__('Supprimer ce lead (droit à l\'oubli)', 'bem-lead-ai'), 'delete', 'submit', false);
            echo '</form>';
        }
    }

    public static function handleLeadDelete(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden');
        }
        $leadId = (int) ($_POST['lead_id'] ?? 0);
        check_admin_referer('bem_lead_delete_' . $leadId);
        PrivacyManager::eraseLead($leadId);
        delete_option('bem_lead_ai_last_summary_' . $leadId);
        wp_safe_redirect(admin_url('admin.php?page=bem-lead-ai-leads'));
        exit;
    }
}

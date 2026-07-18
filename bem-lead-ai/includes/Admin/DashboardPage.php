<?php

namespace BemLeadAi\Admin;

use BemLeadAi\Chat\ConversationRepository;
use BemLeadAi\Crm\CrmRepository;
use BemLeadAi\Leads\LeadRepository;
use BemLeadAi\Privacy\PrivacyManager;
use BemLeadAi\Scoring\ScoringEngine;
use BemLeadAi\Channels\WhatsAppHandoff;

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

        $dueTasks = (new CrmRepository())->dueTasks(0);
        $cards = [
            [__('Leads total', 'bem-lead-ai'), $total],
            [__('Leads chauds à traiter', 'bem-lead-ai'), $hot],
            [__('Leads identifiés (email/tél.)', 'bem-lead-ai'), $identified],
            [__('Conversations (7 j)', 'bem-lead-ai'), $conversations7d],
            [__('Suivis à traiter', 'bem-lead-ai'), count($dueTasks)],
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

        // À faire : tâches de suivi à échéance (ou en retard).
        if ($dueTasks) {
            echo '<h2>' . esc_html__('À faire — suivis à traiter', 'bem-lead-ai') . '</h2>';
            echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Échéance', 'bem-lead-ai') . '</th><th>'
                . esc_html__('Lead', 'bem-lead-ai') . '</th><th>' . esc_html__('Tâche', 'bem-lead-ai') . '</th><th></th></tr></thead><tbody>';
            foreach ($dueTasks as $t) {
                $url = admin_url('admin.php?page=bem-lead-ai-leads&lead_id=' . (int) $t->lead_id);
                $overdue = strtotime($t->due_at) < current_time('timestamp');
                $who = $t->prenom ?: ($t->email ?: ($t->phone ?: 'Lead #' . (int) $t->lead_id));
                echo '<tr><td>' . ($overdue ? '<strong style="color:#d63638;">' : '') . esc_html(mysql2date('d/m/Y', $t->due_at)) . ($overdue ? ' ⚠</strong>' : '') . '</td>'
                    . '<td><a href="' . esc_url($url) . '">' . esc_html($who) . '</a></td>'
                    . '<td>' . esc_html($t->content) . '</td>'
                    . '<td><a class="button button-small" href="' . esc_url($url) . '">' . esc_html__('Ouvrir', 'bem-lead-ai') . '</a></td></tr>';
            }
            echo '</tbody></table>';
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
        foreach (['bem_leads', 'bem_events', 'bem_chat_messages', 'bem_competitor_mentions', 'bem_handoffs', 'bem_crm_activities'] as $t) {
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
        $p = $wpdb->prefix;
        $leads = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$p}bem_leads ORDER BY score_final DESC, last_seen DESC LIMIT %d",
            $limit
        )) ?: [];

        // Nombre de tâches ouvertes par lead, en une seule requête (évite le N+1).
        $openTasks = [];
        foreach ($wpdb->get_results("SELECT lead_id, COUNT(*) AS n FROM {$p}bem_crm_activities WHERE type = 'task' AND done = 0 GROUP BY lead_id") ?: [] as $r) {
            $openTasks[(int) $r->lead_id] = (int) $r->n;
        }

        echo '<h2>' . esc_html__('Leads par score (les plus chauds d\'abord)', 'bem-lead-ai') . '</h2>';
        echo '<p class="description">' . esc_html__('Cliquez sur une ligne pour ouvrir la fiche CRM du lead (pipeline, notes, tâches de suivi, conversation).', 'bem-lead-ai') . '</p>';
        echo '<table class="widefat striped bem-clickable-rows"><thead><tr>'
            . '<th>ID</th><th>' . esc_html__('Contact', 'bem-lead-ai') . '</th><th>' . esc_html__('Email', 'bem-lead-ai') . '</th>'
            . '<th>' . esc_html__('Téléphone', 'bem-lead-ai') . '</th><th>' . esc_html__('Formation', 'bem-lead-ai') . '</th>'
            . '<th>' . esc_html__('Score', 'bem-lead-ai') . '</th>'
            . '<th>' . esc_html__('Étape', 'bem-lead-ai') . '</th>'
            . '<th>' . esc_html__('Responsable', 'bem-lead-ai') . '</th>'
            . '<th>' . esc_html__('Suivi', 'bem-lead-ai') . '</th>'
            . '<th>' . esc_html__('Dernière activité', 'bem-lead-ai') . '</th><th></th></tr></thead><tbody>';

        foreach ($leads as $lead) {
            $url = admin_url('admin.php?page=bem-lead-ai-leads&lead_id=' . (int) $lead->id);
            $name = $lead->prenom ?: '<em>anonyme</em>';
            $stage = CrmRepository::isStage((string) $lead->pipeline_stage) ? (string) $lead->pipeline_stage : 'nouveau';
            $owner = $lead->owner_id ? get_the_author_meta('display_name', (int) $lead->owner_id) : '—';
            $tasks = $openTasks[(int) $lead->id] ?? 0;
            echo '<tr style="cursor:pointer;" onclick="window.location=\'' . esc_url($url) . '\';">'
                . '<td><a href="' . esc_url($url) . '"><strong>#' . (int) $lead->id . '</strong></a></td>'
                . '<td>' . wp_kses_post($name) . '</td>'
                . '<td>' . esc_html($lead->email ?: '—') . '</td>'
                . '<td>' . esc_html($lead->phone ?: '—') . '</td>'
                . '<td>' . esc_html($lead->formation_interet ?: '—') . '</td>'
                . '<td><strong>' . esc_html((string) round((float) $lead->score_final)) . '</strong>/100</td>'
                . '<td><span class="bem-badge" style="background:' . esc_attr(CrmRepository::stageColor($stage)) . ';">' . esc_html(CrmRepository::stageLabel($stage)) . '</span></td>'
                . '<td>' . esc_html($owner) . '</td>'
                . '<td>' . ($tasks ? '<span class="bem-badge bem-badge-task">' . esc_html((string) $tasks) . ' ' . esc_html(_n('tâche', 'tâches', $tasks, 'bem-lead-ai')) . '</span>' : '—') . '</td>'
                . '<td>' . esc_html($lead->last_seen) . '</td>'
                . '<td><a class="button button-small" href="' . esc_url($url) . '">' . esc_html__('Ouvrir', 'bem-lead-ai') . ' →</a></td>'
                . '</tr>';
        }
        echo '</tbody></table>';
    }

    /** Fiche lead = espace CRM complet (pipeline, suivi, activités). */
    private function renderLeadDetail(int $leadId): void
    {
        $lead = (new LeadRepository())->findById($leadId);
        if (!$lead) {
            echo '<p>' . esc_html__('Lead introuvable.', 'bem-lead-ai') . '</p>';
            return;
        }
        $crm = new CrmRepository();
        $band = ScoringEngine::band((float) $lead->score_final);
        $signals = $lead->signals ? json_decode($lead->signals, true) : [];
        $stage = CrmRepository::isStage((string) $lead->pipeline_stage) ? (string) $lead->pipeline_stage : 'nouveau';
        $nonce = wp_create_nonce('bem_crm_' . $leadId);
        $postUrl = esc_url(admin_url('admin-post.php'));

        $this->crmNotices();

        // ---- En-tête : identité, étape, score ----
        echo '<p><a href="' . esc_url(admin_url('admin.php?page=bem-lead-ai-leads')) . '">&larr; ' . esc_html__('Tous les leads', 'bem-lead-ai') . '</a></p>';
        echo '<div class="bem-crm-head">';
        echo '<div><h2 style="margin:0;">' . esc_html($lead->prenom ?: __('Lead anonyme', 'bem-lead-ai')) . ' <span style="color:#8a93a6;font-weight:400;">#' . (int) $lead->id . '</span></h2>';
        echo '<span class="bem-badge" style="background:' . esc_attr(CrmRepository::stageColor($stage)) . ';">' . esc_html(CrmRepository::stageLabel($stage)) . '</span> ';
        echo '<span class="bem-badge bem-badge-ghost">' . esc_html__('Score', 'bem-lead-ai') . ' ' . esc_html((string) round((float) $lead->score_final)) . '/100 · ' . esc_html(str_replace('_', ' ', $band)) . '</span>';
        echo '</div>';
        echo $this->quickActions($lead);
        echo '</div>';

        // ---- Pipeline cliquable ----
        echo '<div class="bem-pipeline">';
        foreach (CrmRepository::stages() as $slug => $conf) {
            $active = $slug === $stage;
            echo '<form method="post" action="' . $postUrl . '" class="bem-pipeline-step">';
            echo '<input type="hidden" name="action" value="bem_crm_stage">';
            echo '<input type="hidden" name="lead_id" value="' . (int) $leadId . '">';
            echo '<input type="hidden" name="stage" value="' . esc_attr($slug) . '">';
            echo '<input type="hidden" name="_wpnonce" value="' . esc_attr($nonce) . '">';
            echo '<button type="submit" class="bem-pipe-btn' . ($active ? ' is-active' : '') . '" style="' . ($active ? '--pipe:' . esc_attr($conf['color']) . ';' : '') . '">' . esc_html($conf['label']) . '</button>';
            echo '</form>';
        }
        echo '</div>';

        echo '<div class="bem-crm-grid">';

        // ================= Colonne principale : suivi =================
        echo '<div class="bem-crm-main">';

        // Ajouter une note.
        echo '<div class="bem-panel-card"><h3>' . esc_html__('Ajouter une note', 'bem-lead-ai') . '</h3>';
        echo '<form method="post" action="' . $postUrl . '">';
        echo '<input type="hidden" name="action" value="bem_crm_note"><input type="hidden" name="lead_id" value="' . (int) $leadId . '"><input type="hidden" name="_wpnonce" value="' . esc_attr($nonce) . '">';
        echo '<textarea name="content" rows="2" class="large-text" placeholder="' . esc_attr__('Compte-rendu d\'appel, information importante…', 'bem-lead-ai') . '" required></textarea>';
        echo '<p><button type="submit" class="button button-primary">' . esc_html__('Enregistrer la note', 'bem-lead-ai') . '</button></p>';
        echo '</form></div>';

        // Planifier une tâche de suivi.
        echo '<div class="bem-panel-card"><h3>' . esc_html__('Planifier un suivi', 'bem-lead-ai') . '</h3>';
        echo '<form method="post" action="' . $postUrl . '" class="bem-task-form">';
        echo '<input type="hidden" name="action" value="bem_crm_task"><input type="hidden" name="lead_id" value="' . (int) $leadId . '"><input type="hidden" name="_wpnonce" value="' . esc_attr($nonce) . '">';
        echo '<input type="text" name="content" class="regular-text" placeholder="' . esc_attr__('Rappeler, envoyer la brochure, relancer…', 'bem-lead-ai') . '" required> ';
        echo '<input type="date" name="due_at" value="' . esc_attr(date('Y-m-d', current_time('timestamp') + 86400)) . '"> ';
        echo '<button type="submit" class="button button-primary">' . esc_html__('Ajouter la tâche', 'bem-lead-ai') . '</button>';
        echo '</form>';

        // Tâches ouvertes.
        $tasks = $crm->openTasks($leadId);
        if ($tasks) {
            echo '<ul class="bem-tasks">';
            foreach ($tasks as $t) {
                $overdue = $t->due_at && strtotime($t->due_at) < current_time('timestamp');
                echo '<li class="bem-task' . ($overdue ? ' is-overdue' : '') . '">';
                echo '<form method="post" action="' . $postUrl . '" style="display:inline;">';
                echo '<input type="hidden" name="action" value="bem_crm_task_toggle"><input type="hidden" name="lead_id" value="' . (int) $leadId . '"><input type="hidden" name="activity_id" value="' . (int) $t->id . '"><input type="hidden" name="_wpnonce" value="' . esc_attr($nonce) . '">';
                echo '<button type="submit" class="bem-task-check" title="' . esc_attr__('Marquer comme fait', 'bem-lead-ai') . '">○</button>';
                echo '</form>';
                echo '<span class="bem-task-text">' . esc_html($t->content) . '</span>';
                if ($t->due_at) {
                    echo ' <span class="bem-task-due">' . esc_html(mysql2date('d/m/Y', $t->due_at)) . ($overdue ? ' · ' . esc_html__('en retard', 'bem-lead-ai') : '') . '</span>';
                }
                echo '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p class="description">' . esc_html__('Aucune tâche en cours.', 'bem-lead-ai') . '</p>';
        }
        echo '</div>';

        // Fil d'activité (CRM + conversation).
        echo '<div class="bem-panel-card"><h3>' . esc_html__('Fil d\'activité', 'bem-lead-ai') . '</h3>';
        $this->renderTimeline($leadId, $crm, $nonce, $postUrl);
        echo '</div>';

        echo '</div>'; // /main

        // ================= Colonne latérale : infos =================
        echo '<div class="bem-crm-side">';

        // Responsable.
        echo '<div class="bem-panel-card"><h3>' . esc_html__('Responsable du suivi', 'bem-lead-ai') . '</h3>';
        echo '<form method="post" action="' . $postUrl . '">';
        echo '<input type="hidden" name="action" value="bem_crm_assign"><input type="hidden" name="lead_id" value="' . (int) $leadId . '"><input type="hidden" name="_wpnonce" value="' . esc_attr($nonce) . '">';
        echo '<select name="owner_id" style="width:100%;max-width:100%;">';
        echo '<option value="0">' . esc_html__('— Non attribué —', 'bem-lead-ai') . '</option>';
        foreach (get_users(['capability' => 'edit_posts', 'number' => 100]) as $u) {
            echo '<option value="' . (int) $u->ID . '" ' . selected((int) $lead->owner_id, (int) $u->ID, false) . '>' . esc_html($u->display_name) . '</option>';
        }
        echo '</select>';
        echo '<p><button type="submit" class="button">' . esc_html__('Attribuer', 'bem-lead-ai') . '</button></p>';
        echo '</form></div>';

        // Coordonnées & signaux.
        echo '<div class="bem-panel-card"><h3>' . esc_html__('Informations', 'bem-lead-ai') . '</h3>';
        echo '<table class="bem-info"><tbody>';
        $rows = [
            [__('Email', 'bem-lead-ai'), $lead->email ?: '—'],
            [__('Téléphone', 'bem-lead-ai'), $lead->phone ?: '—'],
            [__('Formation d\'intérêt', 'bem-lead-ai'), $lead->formation_interet ?: '—'],
            [__('Score comportemental', 'bem-lead-ai'), round((float) $lead->score_comportemental)],
            [__('Score intention (IA)', 'bem-lead-ai'), round((float) $lead->score_intention)],
            [__('Urgence détectée', 'bem-lead-ai'), $signals['urgency'] ?? 'none'],
            [__('Sensibilité prix', 'bem-lead-ai'), !empty($signals['price_sensitivity']) ? __('oui', 'bem-lead-ai') : __('non', 'bem-lead-ai')],
            [__('Canaux', 'bem-lead-ai'), $lead->channels],
            [__('Base de connaissance', 'bem-lead-ai'), $lead->kb_mode],
            [__('Première visite', 'bem-lead-ai'), $lead->first_seen],
            [__('Dernière visite', 'bem-lead-ai'), $lead->last_seen],
            [__('CRM Perfex / HubSpot', 'bem-lead-ai'), ($lead->crm_id_perfex ?: '—') . ' / ' . ($lead->crm_id_hubspot ?: '—')],
        ];
        foreach ($rows as [$label, $value]) {
            echo '<tr><th>' . esc_html($label) . '</th><td>' . esc_html((string) $value) . '</td></tr>';
        }
        echo '</tbody></table></div>';

        // Résumé IA.
        $summary = get_option('bem_lead_ai_last_summary_' . $leadId);
        if ($summary) {
            echo '<div class="bem-panel-card"><h3>' . esc_html__('Résumé IA & approche recommandée', 'bem-lead-ai') . '</h3>';
            echo '<div style="white-space:pre-wrap;">' . esc_html($summary) . '</div></div>';
        }

        // Droit à l'oubli (loi n°2008-12).
        if (current_user_can('manage_options')) {
            echo '<div class="bem-panel-card"><h3>' . esc_html__('Zone sensible', 'bem-lead-ai') . '</h3>';
            echo '<form method="post" action="' . $postUrl . '" onsubmit="return confirm(\'' . esc_js(__('Supprimer définitivement ce lead et tout son historique ?', 'bem-lead-ai')) . '\');">';
            wp_nonce_field('bem_lead_delete_' . $leadId);
            echo '<input type="hidden" name="action" value="bem_lead_delete"><input type="hidden" name="lead_id" value="' . (int) $leadId . '">';
            submit_button(__('Supprimer ce lead (droit à l\'oubli)', 'bem-lead-ai'), 'delete', 'submit', false);
            echo '</form></div>';
        }

        echo '</div>'; // /side
        echo '</div>'; // /grid
    }

    /** Boutons d'action rapide (WhatsApp, email, téléphone). */
    private function quickActions(object $lead): string
    {
        $out = '<div class="bem-quick">';
        $wa = new WhatsAppHandoff();
        if ($wa->isEnabled()) {
            $link = $wa->buildLink($lead);
            if ($link) {
                $out .= '<a class="button bem-quick-wa" target="_blank" rel="noopener" href="' . esc_url($link['url']) . '">✆ WhatsApp</a> ';
            }
        }
        if ($lead->email) {
            $out .= '<a class="button" href="' . esc_attr('mailto:' . $lead->email) . '">✉ ' . esc_html__('Email', 'bem-lead-ai') . '</a> ';
        }
        if ($lead->phone) {
            $out .= '<a class="button" href="' . esc_attr('tel:' . preg_replace('/[^0-9+]/', '', $lead->phone)) . '">☎ ' . esc_html__('Appeler', 'bem-lead-ai') . '</a>';
        }
        return $out . '</div>';
    }

    /** Fil chronologique : activités CRM + messages de conversation fusionnés. */
    private function renderTimeline(int $leadId, CrmRepository $crm, string $nonce, string $postUrl): void
    {
        $items = [];
        foreach ($crm->activities($leadId, 200) as $a) {
            $items[] = ['ts' => $a->created_at, 'kind' => 'crm', 'data' => $a];
        }
        foreach ((new ConversationRepository())->history($leadId, 100) as $m) {
            $items[] = ['ts' => $m->created_at, 'kind' => 'chat', 'data' => $m];
        }
        usort($items, static fn($a, $b) => strcmp((string) $b['ts'], (string) $a['ts']));

        if (!$items) {
            echo '<p class="description">' . esc_html__('Aucune activité pour le moment.', 'bem-lead-ai') . '</p>';
            return;
        }

        echo '<ul class="bem-timeline">';
        foreach ($items as $it) {
            if ($it['kind'] === 'chat') {
                $m = $it['data'];
                $who = $m->role === 'user' ? __('Prospect', 'bem-lead-ai') : ($m->role === 'agent' ? __('Conseiller', 'bem-lead-ai') : 'IA');
                $icon = $m->role === 'user' ? '🧑' : ($m->role === 'agent' ? '👤' : '🤖');
                echo '<li class="bem-tl bem-tl-chat"><span class="bem-tl-ico">' . $icon . '</span>'
                    . '<div><div class="bem-tl-meta"><strong>' . esc_html($who) . '</strong> · ' . esc_html($m->canal . ' · ' . mysql2date('d/m/Y H:i', $m->created_at)) . '</div>'
                    . '<div class="bem-tl-body">' . esc_html($m->contenu) . '</div></div></li>';
                continue;
            }
            $a = $it['data'];
            [$icon, $title] = $this->activityLabel($a);
            $author = $a->author_id ? get_the_author_meta('display_name', (int) $a->author_id) : '';
            echo '<li class="bem-tl bem-tl-' . esc_attr($a->type) . '"><span class="bem-tl-ico">' . $icon . '</span>'
                . '<div><div class="bem-tl-meta"><strong>' . esc_html($title) . '</strong> · ' . esc_html(mysql2date('d/m/Y H:i', $a->created_at)) . ($author ? ' · ' . esc_html($author) : '') . '</div>';
            if ($a->content !== null && $a->content !== '') {
                echo '<div class="bem-tl-body">' . esc_html($a->content) . ($a->type === 'task' && $a->due_at ? ' <em>(' . esc_html(mysql2date('d/m/Y', $a->due_at)) . ')</em>' : '') . '</div>';
            }
            echo '<form method="post" action="' . $postUrl . '" style="display:inline;" onsubmit="return confirm(\'' . esc_js(__('Supprimer cette activité ?', 'bem-lead-ai')) . '\');">'
                . '<input type="hidden" name="action" value="bem_crm_activity_delete"><input type="hidden" name="lead_id" value="' . (int) $leadId . '"><input type="hidden" name="activity_id" value="' . (int) $a->id . '"><input type="hidden" name="_wpnonce" value="' . esc_attr($nonce) . '">'
                . '<button type="submit" class="bem-tl-del" title="' . esc_attr__('Supprimer', 'bem-lead-ai') . '">×</button></form>';
            echo '</div></li>';
        }
        echo '</ul>';
    }

    /** @return array{0:string,1:string} [icône, libellé] d'une activité. */
    private function activityLabel(object $a): array
    {
        $meta = $a->meta ? json_decode($a->meta, true) : [];
        switch ($a->type) {
            case 'note': return ['📝', __('Note', 'bem-lead-ai')];
            case 'task': return [$a->done ? '✅' : '⏰', $a->done ? __('Tâche terminée', 'bem-lead-ai') : __('Tâche de suivi', 'bem-lead-ai')];
            case 'stage_change':
                $to = $meta['to'] ?? '';
                return ['🔀', sprintf(__('Étape → %s', 'bem-lead-ai'), CrmRepository::stageLabel((string) $to))];
            case 'assignment': return ['👥', __('Attribution', 'bem-lead-ai')];
            default: return ['•', ucfirst($a->type)];
        }
    }

    private function crmNotices(): void
    {
        $map = [
            'stage' => __('Étape du pipeline mise à jour.', 'bem-lead-ai'),
            'note' => __('Note ajoutée.', 'bem-lead-ai'),
            'task' => __('Tâche de suivi planifiée.', 'bem-lead-ai'),
            'task_toggle' => __('Tâche mise à jour.', 'bem-lead-ai'),
            'assign' => __('Responsable mis à jour.', 'bem-lead-ai'),
            'deleted' => __('Activité supprimée.', 'bem-lead-ai'),
        ];
        $k = isset($_GET['crm']) ? sanitize_key((string) $_GET['crm']) : '';
        if (isset($map[$k])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($map[$k]) . '</p></div>';
        }
    }

    /* ------------------------------------------------------------------ */
    /* Actions CRM (admin-post)                                            */
    /* ------------------------------------------------------------------ */

    private static function crmGuard(int $leadId): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die('Forbidden');
        }
        check_admin_referer('bem_crm_' . $leadId);
    }

    private static function crmRedirect(int $leadId, string $flag): void
    {
        wp_safe_redirect(admin_url('admin.php?page=bem-lead-ai-leads&lead_id=' . $leadId . '&crm=' . $flag));
        exit;
    }

    public static function handleCrmStage(): void
    {
        $leadId = (int) ($_POST['lead_id'] ?? 0);
        self::crmGuard($leadId);
        $stage = sanitize_key((string) ($_POST['stage'] ?? ''));
        if ($leadId && CrmRepository::isStage($stage)) {
            $lead = (new LeadRepository())->findById($leadId);
            $from = $lead ? (string) $lead->pipeline_stage : '';
            if ($from !== $stage) {
                (new LeadRepository())->update($leadId, ['pipeline_stage' => $stage]);
                (new CrmRepository())->log($leadId, 'stage_change', '', ['from' => $from, 'to' => $stage]);
            }
        }
        self::crmRedirect($leadId, 'stage');
    }

    public static function handleCrmAssign(): void
    {
        $leadId = (int) ($_POST['lead_id'] ?? 0);
        self::crmGuard($leadId);
        $ownerId = (int) ($_POST['owner_id'] ?? 0);
        (new LeadRepository())->update($leadId, ['owner_id' => $ownerId ?: null]);
        $name = $ownerId ? get_the_author_meta('display_name', $ownerId) : __('Non attribué', 'bem-lead-ai');
        (new CrmRepository())->log($leadId, 'assignment', $name, ['owner_id' => $ownerId]);
        self::crmRedirect($leadId, 'assign');
    }

    public static function handleCrmNote(): void
    {
        $leadId = (int) ($_POST['lead_id'] ?? 0);
        self::crmGuard($leadId);
        $content = trim((string) sanitize_textarea_field(wp_unslash((string) ($_POST['content'] ?? ''))));
        if ($content !== '') {
            (new CrmRepository())->log($leadId, 'note', $content);
        }
        self::crmRedirect($leadId, 'note');
    }

    public static function handleCrmTask(): void
    {
        $leadId = (int) ($_POST['lead_id'] ?? 0);
        self::crmGuard($leadId);
        $content = trim((string) sanitize_text_field(wp_unslash((string) ($_POST['content'] ?? ''))));
        $due = sanitize_text_field((string) ($_POST['due_at'] ?? ''));
        $dueAt = ($due && preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) ? $due . ' 09:00:00' : null;
        if ($content !== '') {
            $crm = new CrmRepository();
            $crm->log($leadId, 'task', $content, [], $dueAt);
            if ($dueAt) {
                (new LeadRepository())->update($leadId, ['next_action_at' => $dueAt]);
            }
        }
        self::crmRedirect($leadId, 'task');
    }

    public static function handleCrmTaskToggle(): void
    {
        $leadId = (int) ($_POST['lead_id'] ?? 0);
        self::crmGuard($leadId);
        (new CrmRepository())->toggleTask((int) ($_POST['activity_id'] ?? 0));
        self::crmRedirect($leadId, 'task_toggle');
    }

    public static function handleCrmActivityDelete(): void
    {
        $leadId = (int) ($_POST['lead_id'] ?? 0);
        self::crmGuard($leadId);
        (new CrmRepository())->deleteActivity((int) ($_POST['activity_id'] ?? 0));
        self::crmRedirect($leadId, 'deleted');
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

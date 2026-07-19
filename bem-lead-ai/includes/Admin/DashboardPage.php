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

        echo '<div class="wrap"><h1>' . esc_html(Branding::name()) . ' — ' . esc_html__('Tableau de bord', 'bem-lead-ai') . '</h1>';

        if (isset($_GET['purged'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Leads de test supprimés.', 'bem-lead-ai') . '</p></div>';
        }

        if ($openHandoffs > 0) {
            echo '<div class="notice notice-warning"><p><strong>' . sprintf(esc_html__('%d escalade(s) humaine(s) en attente dans l\'inbox conseiller.', 'bem-lead-ai'), $openHandoffs)
                . '</strong> <a href="' . esc_url(admin_url('admin.php?page=bem-lead-ai-inbox')) . '">' . esc_html__('Répondre maintenant', 'bem-lead-ai') . '</a></p></div>';
        }

        $dueTasks = (new CrmRepository())->dueTasks(0);
        $convRate = $identified > 0 ? round($inscrits / $identified * 100) : 0;

        // ---- Cartes KPI premium ----
        $cards = [
            ['icon' => 'users', 'accent' => 'blue', 'label' => __('Leads total', 'bem-lead-ai'), 'value' => $total],
            ['icon' => 'flame', 'accent' => 'red', 'label' => __('Leads chauds à traiter', 'bem-lead-ai'), 'value' => $hot],
            ['icon' => 'user-check', 'accent' => 'violet', 'label' => __('Identifiés (email/tél.)', 'bem-lead-ai'), 'value' => $identified],
            ['icon' => 'message', 'accent' => 'teal', 'label' => __('Conversations (7 j)', 'bem-lead-ai'), 'value' => $conversations7d],
            ['icon' => 'clock', 'accent' => 'amber', 'label' => __('Suivis à traiter', 'bem-lead-ai'), 'value' => count($dueTasks)],
            ['icon' => 'award', 'accent' => 'green', 'label' => __('Inscrits', 'bem-lead-ai'), 'value' => $inscrits, 'sub' => $convRate . '% ' . __('de conversion', 'bem-lead-ai')],
        ];
        echo '<div class="bem-kpis">';
        foreach ($cards as $c) {
            echo '<div class="bem-kpi bem-kpi-' . esc_attr($c['accent']) . '">'
                . '<div class="bem-kpi-ico">' . Icons::get($c['icon']) . '</div>'
                . '<div class="bem-kpi-txt"><div class="bem-kpi-val">' . esc_html((string) $c['value']) . '</div>'
                . '<div class="bem-kpi-lbl">' . esc_html($c['label']) . '</div>'
                . (!empty($c['sub']) ? '<div class="bem-kpi-sub">' . esc_html($c['sub']) . '</div>' : '')
                . '</div></div>';
        }
        echo '</div>';

        // ---- Entonnoir du pipeline ----
        $stageCounts = [];
        foreach ($wpdb->get_results("SELECT pipeline_stage AS s, COUNT(*) AS n FROM {$p}bem_leads GROUP BY pipeline_stage") ?: [] as $r) {
            $stageCounts[(string) $r->s] = (int) $r->n;
        }
        $maxStage = max(1, $stageCounts ? max($stageCounts) : 1);
        echo '<div class="bem-panel-card bem-funnel-card" style="max-width:none;"><h3>' . esc_html__('Pipeline d\'admission', 'bem-lead-ai') . '</h3>';
        echo '<div class="bem-funnel">';
        foreach (CrmRepository::stages() as $slug => $conf) {
            $n = $stageCounts[$slug] ?? 0;
            $pct = round($n / $maxStage * 100);
            $url = admin_url('admin.php?page=bem-lead-ai-leads&stage=' . $slug);
            echo '<a class="bem-funnel-row" href="' . esc_url($url) . '">'
                . '<span class="bem-funnel-name">' . esc_html($conf['label']) . '</span>'
                . '<span class="bem-funnel-bar"><span class="bem-funnel-fill" style="width:' . (int) max(6, $pct) . '%;background:' . esc_attr($conf['color']) . ';"></span></span>'
                . '<span class="bem-funnel-n">' . (int) $n . '</span></a>';
        }
        echo '</div></div>';

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
                echo '<tr><td>' . ($overdue ? '<strong style="color:#d63638;">' : '') . esc_html(mysql2date('d/m/Y', $t->due_at)) . ($overdue ? ' · ' . esc_html__('en retard', 'bem-lead-ai') . '</strong>' : '') . '</td>'
                    . '<td><a href="' . esc_url($url) . '">' . esc_html($who) . '</a></td>'
                    . '<td>' . esc_html($t->content) . '</td>'
                    . '<td><a class="button button-small" href="' . esc_url($url) . '">' . esc_html__('Ouvrir', 'bem-lead-ai') . '</a></td></tr>';
            }
            echo '</tbody></table>';
        }

        // Aperçu des leads les plus chauds (liste complète + filtres sur la page Leads).
        global $wpdb;
        $hotLeads = $wpdb->get_results("SELECT * FROM {$p}bem_leads ORDER BY score_final DESC, last_seen DESC LIMIT 8") ?: [];
        echo '<div class="bem-section-head"><h2>' . esc_html__('Leads les plus chauds', 'bem-lead-ai') . '</h2>'
            . '<a class="button" href="' . esc_url(admin_url('admin.php?page=bem-lead-ai-leads')) . '">' . esc_html__('Voir tous les leads', 'bem-lead-ai') . ' →</a></div>';
        $this->renderLeadRows($hotLeads);
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
            $this->renderLeadsBrowser();
        }
        echo '</div>';
    }

    /** Liste des leads avec recherche, filtres et pagination (CRM premium). */
    private function renderLeadsBrowser(): void
    {
        global $wpdb;
        $p = $wpdb->prefix;
        $perPage = 25;

        // Filtres.
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash((string) $_GET['s'])) : '';
        $fStage = isset($_GET['stage']) ? sanitize_key((string) $_GET['stage']) : '';
        $fBand = isset($_GET['band']) ? sanitize_key((string) $_GET['band']) : '';
        $fOwner = isset($_GET['owner']) ? (int) $_GET['owner'] : 0;
        $paged = max(1, isset($_GET['paged']) ? (int) $_GET['paged'] : 1);

        // Construction sécurisée de la clause WHERE.
        $where = ['1=1'];
        $args = [];
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(prenom LIKE %s OR email LIKE %s OR phone LIKE %s OR formation_interet LIKE %s)';
            array_push($args, $like, $like, $like, $like);
        }
        if (CrmRepository::isStage($fStage)) {
            $where[] = 'pipeline_stage = %s';
            $args[] = $fStage;
        }
        if ($fOwner > 0) {
            $where[] = 'owner_id = %d';
            $args[] = $fOwner;
        }
        $bands = [
            'tres_chaud' => [(float) \BemLeadAi\Core\Options::get('threshold_very_hot'), null],
            'chaud' => [(float) \BemLeadAi\Core\Options::get('threshold_hot'), (float) \BemLeadAi\Core\Options::get('threshold_very_hot')],
            'tiede' => [(float) \BemLeadAi\Core\Options::get('threshold_warm'), (float) \BemLeadAi\Core\Options::get('threshold_hot')],
            'froid' => [null, (float) \BemLeadAi\Core\Options::get('threshold_warm')],
        ];
        if (isset($bands[$fBand])) {
            [$min, $max] = $bands[$fBand];
            if ($min !== null) { $where[] = 'score_final >= %f'; $args[] = $min; }
            if ($max !== null) { $where[] = 'score_final < %f'; $args[] = $max; }
        }
        $whereSql = implode(' AND ', $where);

        // Total (pour la pagination).
        $countSql = "SELECT COUNT(*) FROM {$p}bem_leads WHERE {$whereSql}";
        $total = (int) ($args ? $wpdb->get_var($wpdb->prepare($countSql, $args)) : $wpdb->get_var($countSql));
        $pages = max(1, (int) ceil($total / $perPage));
        $paged = min($paged, $pages);
        $offset = ($paged - 1) * $perPage;

        $listSql = "SELECT * FROM {$p}bem_leads WHERE {$whereSql} ORDER BY score_final DESC, last_seen DESC LIMIT %d OFFSET %d";
        $listArgs = array_merge($args, [$perPage, $offset]);
        $leads = $wpdb->get_results($wpdb->prepare($listSql, $listArgs)) ?: [];

        // Barre de filtres.
        $owners = get_users(['capability' => 'edit_posts', 'number' => 100]);
        echo '<form method="get" class="bem-filters">';
        echo '<input type="hidden" name="page" value="bem-lead-ai-leads">';
        echo '<input type="search" name="s" value="' . esc_attr($search) . '" placeholder="' . esc_attr__('Rechercher (nom, email, téléphone, formation)…', 'bem-lead-ai') . '" class="bem-filter-search">';
        echo '<select name="stage"><option value="">' . esc_html__('Toutes les étapes', 'bem-lead-ai') . '</option>';
        foreach (CrmRepository::stages() as $slug => $conf) {
            echo '<option value="' . esc_attr($slug) . '" ' . selected($fStage, $slug, false) . '>' . esc_html($conf['label']) . '</option>';
        }
        echo '</select>';
        echo '<select name="band"><option value="">' . esc_html__('Toutes les températures', 'bem-lead-ai') . '</option>';
        foreach (['tres_chaud' => __('Très chaud', 'bem-lead-ai'), 'chaud' => __('Chaud', 'bem-lead-ai'), 'tiede' => __('Tiède', 'bem-lead-ai'), 'froid' => __('Froid', 'bem-lead-ai')] as $slug => $label) {
            echo '<option value="' . esc_attr($slug) . '" ' . selected($fBand, $slug, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<select name="owner"><option value="0">' . esc_html__('Tous les responsables', 'bem-lead-ai') . '</option>';
        foreach ($owners as $u) {
            echo '<option value="' . (int) $u->ID . '" ' . selected($fOwner, (int) $u->ID, false) . '>' . esc_html($u->display_name) . '</option>';
        }
        echo '</select>';
        echo '<button type="submit" class="button button-primary">' . esc_html__('Filtrer', 'bem-lead-ai') . '</button>';
        if ($search !== '' || $fStage || $fBand || $fOwner) {
            echo ' <a class="button" href="' . esc_url(admin_url('admin.php?page=bem-lead-ai-leads')) . '">' . esc_html__('Réinitialiser', 'bem-lead-ai') . '</a>';
        }
        echo '</form>';

        // Compteur + pagination haute.
        echo '<div class="bem-section-head"><p class="description" style="margin:0;">'
            . sprintf(esc_html(_n('%s lead trouvé', '%s leads trouvés', $total, 'bem-lead-ai')), '<strong>' . number_format_i18n($total) . '</strong>')
            . '</p>' . $this->paginationLinks($paged, $pages) . '</div>';

        $this->renderLeadRows($leads);

        // Pagination basse.
        if ($pages > 1) {
            echo '<div class="bem-section-head" style="justify-content:flex-end;">' . $this->paginationLinks($paged, $pages) . '</div>';
        }
    }

    /** Liens de pagination conservant les filtres courants. */
    private function paginationLinks(int $paged, int $pages): string
    {
        if ($pages <= 1) {
            return '';
        }
        $base = admin_url('admin.php');
        $keep = array_filter([
            'page' => 'bem-lead-ai-leads',
            's' => isset($_GET['s']) ? sanitize_text_field(wp_unslash((string) $_GET['s'])) : '',
            'stage' => isset($_GET['stage']) ? sanitize_key((string) $_GET['stage']) : '',
            'band' => isset($_GET['band']) ? sanitize_key((string) $_GET['band']) : '',
            'owner' => isset($_GET['owner']) ? (int) $_GET['owner'] : 0,
        ], static fn($v) => $v !== '' && $v !== 0);
        $link = static function (int $page) use ($base, $keep): string {
            $keep['paged'] = $page;
            return esc_url(add_query_arg($keep, $base));
        };
        $out = '<span class="bem-pagination">';
        if ($paged > 1) {
            $out .= '<a class="button button-small" href="' . $link($paged - 1) . '">‹</a>';
        }
        $out .= '<span class="bem-page-info">' . sprintf(esc_html__('Page %1$d / %2$d', 'bem-lead-ai'), $paged, $pages) . '</span>';
        if ($paged < $pages) {
            $out .= '<a class="button button-small" href="' . $link($paged + 1) . '">›</a>';
        }
        return $out . '</span>';
    }

    /** Rend le tableau des leads (en-tête + lignes) à partir d'une liste. */
    private function renderLeadRows(array $leads): void
    {
        global $wpdb;
        $p = $wpdb->prefix;

        if (!$leads) {
            echo '<div class="bem-empty">' . esc_html__('Aucun lead à afficher.', 'bem-lead-ai') . '</div>';
            return;
        }

        // Tâches ouvertes par lead, en une seule requête (évite le N+1).
        $ids = implode(',', array_map(static fn($l) => (int) $l->id, $leads));
        $openTasks = [];
        foreach ($wpdb->get_results("SELECT lead_id, COUNT(*) AS n FROM {$p}bem_crm_activities WHERE type = 'task' AND done = 0 AND lead_id IN ({$ids}) GROUP BY lead_id") ?: [] as $r) {
            $openTasks[(int) $r->lead_id] = (int) $r->n;
        }

        $bandColors = ['tres_chaud' => '#d63638', 'chaud' => '#dba617', 'tiede' => '#2271b1', 'froid' => '#646970'];
        echo '<table class="widefat striped bem-clickable-rows bem-leads-table"><thead><tr>'
            . '<th>' . esc_html__('Contact', 'bem-lead-ai') . '</th>'
            . '<th>' . esc_html__('Coordonnées', 'bem-lead-ai') . '</th>'
            . '<th>' . esc_html__('Formation', 'bem-lead-ai') . '</th>'
            . '<th>' . esc_html__('Score', 'bem-lead-ai') . '</th>'
            . '<th>' . esc_html__('Étape', 'bem-lead-ai') . '</th>'
            . '<th>' . esc_html__('Responsable', 'bem-lead-ai') . '</th>'
            . '<th>' . esc_html__('Suivi', 'bem-lead-ai') . '</th>'
            . '<th>' . esc_html__('Dernière activité', 'bem-lead-ai') . '</th></tr></thead><tbody>';

        foreach ($leads as $lead) {
            $url = admin_url('admin.php?page=bem-lead-ai-leads&lead_id=' . (int) $lead->id);
            $band = ScoringEngine::band((float) $lead->score_final);
            $stage = CrmRepository::isStage((string) $lead->pipeline_stage) ? (string) $lead->pipeline_stage : 'nouveau';
            $owner = $lead->owner_id ? get_the_author_meta('display_name', (int) $lead->owner_id) : '—';
            $tasks = $openTasks[(int) $lead->id] ?? 0;
            $initial = mb_strtoupper(mb_substr($lead->prenom ?: '?', 0, 1));
            $contacts = trim(($lead->email ? esc_html($lead->email) : '') . ($lead->email && $lead->phone ? '<br>' : '') . ($lead->phone ? esc_html($lead->phone) : ''));
            echo '<tr onclick="window.location=\'' . esc_url($url) . '\';">'
                . '<td><span class="bem-lead-id"><span class="bem-avatar" style="background:' . esc_attr($bandColors[$band]) . ';">' . esc_html($initial) . '</span>'
                . '<span><strong>' . esc_html($lead->prenom ?: __('Anonyme', 'bem-lead-ai')) . '</strong><span class="bem-lead-num">#' . (int) $lead->id . '</span></span></span></td>'
                . '<td class="bem-cell-muted">' . ($contacts !== '' ? $contacts : '—') . '</td>'
                . '<td>' . esc_html($lead->formation_interet ?: '—') . '</td>'
                . '<td><span class="bem-score" style="color:' . esc_attr($bandColors[$band]) . ';">' . esc_html((string) round((float) $lead->score_final)) . '</span><span class="bem-score-max">/100</span></td>'
                . '<td><span class="bem-badge" style="background:' . esc_attr(CrmRepository::stageColor($stage)) . ';">' . esc_html(CrmRepository::stageLabel($stage)) . '</span></td>'
                . '<td>' . esc_html($owner) . '</td>'
                . '<td>' . ($tasks ? '<span class="bem-badge bem-badge-task">' . esc_html((string) $tasks) . '</span>' : '—') . '</td>'
                . '<td class="bem-cell-muted">' . esc_html(mysql2date('d/m/Y H:i', $lead->last_seen)) . '</td>'
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
        echo '<div class="bem-crm-wrap">';
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
                echo '<button type="submit" class="bem-task-check" title="' . esc_attr__('Marquer comme fait', 'bem-lead-ai') . '">' . Icons::get('check-circle', 'bem-ico bem-task-check-ico') . '</button>';
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

        // Résumé IA & approche recommandée (mis en évidence).
        $summary = get_option('bem_lead_ai_last_summary_' . $leadId);
        if ($summary) {
            echo '<div class="bem-panel-card bem-highlight"><h3>' . Icons::get('bulb') . ' ' . esc_html__('Résumé IA & approche recommandée', 'bem-lead-ai') . '</h3>';
            echo '<div class="bem-highlight-body" style="white-space:pre-wrap;">' . esc_html($summary) . '</div></div>';
        }

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
        $emailHtml = $lead->email
            ? '<a href="' . esc_url('mailto:' . $lead->email) . '">' . esc_html($lead->email) . '</a>'
            : '—';
        $phoneHtml = $lead->phone
            ? '<a href="' . esc_attr('tel:' . preg_replace('/[^0-9+]/', '', $lead->phone)) . '">' . esc_html($lead->phone) . '</a>'
            : '—';
        echo '<div class="bem-panel-card"><h3>' . esc_html__('Informations', 'bem-lead-ai') . '</h3>';
        echo '<table class="bem-info"><tbody>';
        $rows = [
            [__('Email', 'bem-lead-ai'), $emailHtml, true],
            [__('Téléphone', 'bem-lead-ai'), $phoneHtml, true],
            [__('Formation d\'intérêt', 'bem-lead-ai'), $lead->formation_interet ?: '—', false],
            [__('Score comportemental', 'bem-lead-ai'), round((float) $lead->score_comportemental), false],
            [__('Score intention (IA)', 'bem-lead-ai'), round((float) $lead->score_intention), false],
            [__('Urgence détectée', 'bem-lead-ai'), $signals['urgency'] ?? 'none', false],
            [__('Sensibilité prix', 'bem-lead-ai'), !empty($signals['price_sensitivity']) ? __('oui', 'bem-lead-ai') : __('non', 'bem-lead-ai'), false],
            [__('Canaux', 'bem-lead-ai'), $lead->channels, false],
            [__('Base de connaissance', 'bem-lead-ai'), $lead->kb_mode, false],
            [__('Première visite', 'bem-lead-ai'), $lead->first_seen, false],
            [__('Dernière visite', 'bem-lead-ai'), $lead->last_seen, false],
            [__('CRM Perfex / HubSpot', 'bem-lead-ai'), ($lead->crm_id_perfex ?: '—') . ' / ' . ($lead->crm_id_hubspot ?: '—'), false],
        ];
        foreach ($rows as [$label, $value, $isHtml]) {
            echo '<tr><th>' . esc_html($label) . '</th><td>' . ($isHtml ? wp_kses_post((string) $value) : esc_html((string) $value)) . '</td></tr>';
        }
        echo '</tbody></table></div>';

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
        echo '</div>'; // /wrap
    }

    /** Boutons d'action rapide (WhatsApp, email, téléphone). */
    private function quickActions(object $lead): string
    {
        $out = '<div class="bem-quick">';
        $wa = new WhatsAppHandoff();
        if ($wa->isEnabled()) {
            $link = $wa->buildLink($lead);
            if ($link) {
                $out .= '<a class="button bem-quick-wa" target="_blank" rel="noopener" href="' . esc_url($link['url']) . '">' . Icons::get('message') . ' WhatsApp</a> ';
            }
        }
        if ($lead->email) {
            $school = trim((string) \BemLeadAi\Core\Options::get('school_name')) ?: 'BEM Conakry';
            $subject = rawurlencode(sprintf(__('%s — votre projet de formation', 'bem-lead-ai'), $school));
            $out .= '<a class="button" href="' . esc_url('mailto:' . $lead->email . '?subject=' . $subject) . '">' . Icons::get('mail') . ' ' . esc_html__('Email', 'bem-lead-ai') . '</a> ';
        }
        if ($lead->phone) {
            $out .= '<a class="button" href="' . esc_attr('tel:' . preg_replace('/[^0-9+]/', '', $lead->phone)) . '">' . Icons::get('phone') . ' ' . esc_html__('Appeler', 'bem-lead-ai') . '</a>';
        }
        return $out . '</div>';
    }

    /**
     * Fil d'activité organisé par onglets (Tout / Notes / Tâches / Suivi CRM /
     * Conversation) — meilleur suivi qu'une liste unique interminable.
     * Onglets en CSS pur (radios), aucune dépendance JS.
     */
    private function renderTimeline(int $leadId, CrmRepository $crm, string $nonce, string $postUrl): void
    {
        // Fusion chronologique, chaque item classé dans une catégorie.
        $items = [];
        foreach ($crm->activities($leadId, 200) as $a) {
            $cat = $a->type === 'note' ? 'notes' : ($a->type === 'task' ? 'tasks' : 'crm');
            $items[] = ['ts' => $a->created_at, 'cat' => $cat, 'html' => $this->crmActivityHtml($a, $leadId, $nonce, $postUrl)];
        }
        foreach ((new ConversationRepository())->history($leadId, 100) as $m) {
            $items[] = ['ts' => $m->created_at, 'cat' => 'chat', 'html' => $this->chatItemHtml($m)];
        }
        usort($items, static fn($a, $b) => strcmp((string) $b['ts'], (string) $a['ts']));

        if (!$items) {
            echo '<p class="description">' . esc_html__('Aucune activité pour le moment.', 'bem-lead-ai') . '</p>';
            return;
        }

        $tabs = [
            'all'   => __('Tout', 'bem-lead-ai'),
            'notes' => __('Notes', 'bem-lead-ai'),
            'tasks' => __('Tâches', 'bem-lead-ai'),
            'crm'   => __('Suivi', 'bem-lead-ai'),
            'chat'  => __('Conversation', 'bem-lead-ai'),
        ];
        $counts = ['all' => count($items), 'notes' => 0, 'tasks' => 0, 'crm' => 0, 'chat' => 0];
        foreach ($items as $it) {
            $counts[$it['cat']]++;
        }

        echo '<div class="bem-tabs">';
        // Radios + labels (l'ordre : tous les radios, puis les panneaux).
        foreach ($tabs as $key => $label) {
            $checked = $key === 'all' ? ' checked' : '';
            echo '<input type="radio" name="bem-tl-tab-' . (int) $leadId . '" id="bem-tab-' . esc_attr($key) . '-' . (int) $leadId . '" class="bem-tab-radio bem-tab-radio-' . esc_attr($key) . '"' . $checked . '>';
        }
        echo '<div class="bem-tab-labels">';
        foreach ($tabs as $key => $label) {
            echo '<label for="bem-tab-' . esc_attr($key) . '-' . (int) $leadId . '" class="bem-tab-label bem-tab-label-' . esc_attr($key) . '">' . esc_html($label) . ' <span class="bem-tab-count">' . (int) $counts[$key] . '</span></label>';
        }
        echo '</div>';

        echo '<div class="bem-tab-panels">';
        foreach (array_keys($tabs) as $key) {
            echo '<div class="bem-tab-panel bem-tab-panel-' . esc_attr($key) . '"><ul class="bem-timeline">';
            $any = false;
            foreach ($items as $it) {
                if ($key === 'all' || $it['cat'] === $key) {
                    echo $it['html'];
                    $any = true;
                }
            }
            if (!$any) {
                echo '<li class="description" style="padding:12px 0;">' . esc_html__('Rien dans cette catégorie.', 'bem-lead-ai') . '</li>';
            }
            echo '</ul></div>';
        }
        echo '</div>'; // /panels
        echo '</div>'; // /tabs
    }

    private function chatItemHtml(object $m): string
    {
        $who = $m->role === 'user' ? __('Prospect', 'bem-lead-ai') : ($m->role === 'agent' ? __('Conseiller', 'bem-lead-ai') : 'IA');
        $icon = Icons::get($m->role === 'user' ? 'user' : ($m->role === 'agent' ? 'headset' : 'cpu'));
        return '<li class="bem-tl bem-tl-chat"><span class="bem-tl-ico">' . $icon . '</span>'
            . '<div><div class="bem-tl-meta"><strong>' . esc_html($who) . '</strong> · ' . esc_html($m->canal . ' · ' . mysql2date('d/m/Y H:i', $m->created_at)) . '</div>'
            . '<div class="bem-tl-body">' . esc_html($m->contenu) . '</div></div></li>';
    }

    private function crmActivityHtml(object $a, int $leadId, string $nonce, string $postUrl): string
    {
        [$icon, $title] = $this->activityLabel($a);
        $author = $a->author_id ? get_the_author_meta('display_name', (int) $a->author_id) : '';
        $html = '<li class="bem-tl bem-tl-' . esc_attr($a->type) . '"><span class="bem-tl-ico">' . $icon . '</span>'
            . '<div><div class="bem-tl-meta"><strong>' . esc_html($title) . '</strong> · ' . esc_html(mysql2date('d/m/Y H:i', $a->created_at)) . ($author ? ' · ' . esc_html($author) : '') . '</div>';
        if ($a->content !== null && $a->content !== '') {
            $html .= '<div class="bem-tl-body">' . esc_html($a->content) . ($a->type === 'task' && $a->due_at ? ' <em>(' . esc_html(mysql2date('d/m/Y', $a->due_at)) . ')</em>' : '') . '</div>';
        }
        $html .= '<form method="post" action="' . $postUrl . '" style="display:inline;" onsubmit="return confirm(\'' . esc_js(__('Supprimer cette activité ?', 'bem-lead-ai')) . '\');">'
            . '<input type="hidden" name="action" value="bem_crm_activity_delete"><input type="hidden" name="lead_id" value="' . (int) $leadId . '"><input type="hidden" name="activity_id" value="' . (int) $a->id . '"><input type="hidden" name="_wpnonce" value="' . esc_attr($nonce) . '">'
            . '<button type="submit" class="bem-tl-del" title="' . esc_attr__('Supprimer', 'bem-lead-ai') . '">×</button></form>';
        return $html . '</div></li>';
    }

    /** @return array{0:string,1:string} [icône SVG, libellé] d'une activité. */
    private function activityLabel(object $a): array
    {
        $meta = $a->meta ? json_decode($a->meta, true) : [];
        switch ($a->type) {
            case 'note': return [Icons::get('edit'), __('Note', 'bem-lead-ai')];
            case 'task': return [Icons::get($a->done ? 'check-circle' : 'clock'), $a->done ? __('Tâche terminée', 'bem-lead-ai') : __('Tâche de suivi', 'bem-lead-ai')];
            case 'stage_change':
                $to = $meta['to'] ?? '';
                return [Icons::get('shuffle'), sprintf(__('Étape → %s', 'bem-lead-ai'), CrmRepository::stageLabel((string) $to))];
            case 'assignment': return [Icons::get('user-plus'), __('Attribution', 'bem-lead-ai')];
            default: return [Icons::get('edit'), ucfirst($a->type)];
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

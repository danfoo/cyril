<?php

namespace BemLeadAi\Admin;

defined('ABSPATH') || exit;

/**
 * Veille concurrentielle passive : agrégation des écoles concurrentes
 * mentionnées dans les conversations (captées sans appel LLM supplémentaire).
 */
final class CompetitorsPage
{
    public function render(): void
    {
        global $wpdb;
        $p = $wpdb->prefix;
        $school = trim((string) \BemLeadAi\Core\Options::get('school_name')) ?: 'BEM Conakry';

        echo '<div class="wrap"><h1>' . esc_html__('Veille concurrentielle', 'bem-lead-ai') . '</h1>';
        echo '<p class="description" style="max-width:860px;">' . esc_html(sprintf(__('Écoles concurrentes évoquées spontanément par les prospects dans leurs conversations. Un signal marketing direct : qui %s affronte réellement dans la décision des étudiants.', 'bem-lead-ai'), $school)) . '</p>';

        $ranking = $wpdb->get_results(
            "SELECT nom_concurrent, COUNT(*) AS mentions, COUNT(DISTINCT lead_id) AS leads, MAX(created_at) AS derniere
             FROM {$p}bem_competitor_mentions
             GROUP BY nom_concurrent ORDER BY mentions DESC LIMIT 50"
        ) ?: [];

        if (!$ranking) {
            echo '<div class="bem-empty">' . Icons::get('target', 'bem-ico bem-empty-ico')
                . '<p><strong>' . esc_html__('Aucune mention de concurrent pour l\'instant.', 'bem-lead-ai') . '</strong></p>'
                . '<p>' . esc_html__('Les écoles citées par les prospects apparaîtront ici automatiquement.', 'bem-lead-ai') . '</p></div></div>';
            return;
        }

        // KPI de synthèse.
        $totalMentions = 0;
        $totalLeads = (int) $wpdb->get_var("SELECT COUNT(DISTINCT lead_id) FROM {$p}bem_competitor_mentions");
        foreach ($ranking as $r) {
            $totalMentions += (int) $r->mentions;
        }
        $kpis = [
            ['flame', 'red', $totalMentions, __('Mentions totales', 'bem-lead-ai')],
            ['target', 'violet', count($ranking), __('Concurrents identifiés', 'bem-lead-ai')],
            ['users', 'blue', $totalLeads, __('Prospects concernés', 'bem-lead-ai')],
        ];
        echo '<div class="bem-kpis" style="grid-template-columns:repeat(auto-fit,minmax(190px,1fr));max-width:640px;">';
        foreach ($kpis as [$icon, $accent, $val, $label]) {
            echo '<div class="bem-kpi bem-kpi-' . esc_attr($accent) . '"><div class="bem-kpi-ico">' . Icons::get($icon) . '</div>'
                . '<div class="bem-kpi-txt"><div class="bem-kpi-val">' . (int) $val . '</div><div class="bem-kpi-lbl">' . esc_html($label) . '</div></div></div>';
        }
        echo '</div>';

        // Classement en barres.
        $max = max(1, (int) $ranking[0]->mentions);
        echo '<div class="bem-panel-card" style="max-width:760px;"><h3>' . esc_html__('Classement des concurrents', 'bem-lead-ai') . '</h3>';
        echo '<div class="bem-funnel">';
        foreach ($ranking as $row) {
            $pct = round((int) $row->mentions / $max * 100);
            echo '<div class="bem-funnel-row" style="cursor:default;">'
                . '<span class="bem-funnel-name" style="flex-basis:180px;">' . esc_html($row->nom_concurrent) . '</span>'
                . '<span class="bem-funnel-bar"><span class="bem-funnel-fill" style="width:' . (int) max(6, $pct) . '%;background:#8250df;"></span></span>'
                . '<span class="bem-funnel-n">' . (int) $row->mentions . '</span></div>';
        }
        echo '</div>';
        echo '<p class="description" style="margin-top:10px;">' . esc_html__('Barre = nombre de mentions. Cliquez sur un extrait ci-dessous pour ouvrir le lead concerné.', 'bem-lead-ai') . '</p>';
        echo '</div>';

        // Extraits de contexte en cartes.
        echo '<div class="bem-section-head"><h2>' . esc_html__('Derniers extraits de contexte', 'bem-lead-ai') . '</h2></div>';
        $recent = $wpdb->get_results(
            "SELECT nom_concurrent, extrait_contexte, lead_id, created_at
             FROM {$p}bem_competitor_mentions
             WHERE extrait_contexte <> '' ORDER BY created_at DESC LIMIT 30"
        ) ?: [];
        echo '<div class="bem-quote-list">';
        foreach ($recent as $row) {
            $url = admin_url('admin.php?page=bem-lead-ai-leads&lead_id=' . (int) $row->lead_id);
            echo '<div class="bem-quote">'
                . '<div class="bem-quote-top"><span class="bem-badge" style="background:#8250df;">' . esc_html($row->nom_concurrent) . '</span>'
                . '<span class="bem-quote-meta">' . esc_html(mysql2date('d/m/Y H:i', $row->created_at)) . ' · <a href="' . esc_url($url) . '">#' . (int) $row->lead_id . '</a></span></div>'
                . '<p class="bem-quote-text">« ' . esc_html($row->extrait_contexte) . ' »</p>'
                . '</div>';
        }
        echo '</div>';
        echo '</div>';
    }
}

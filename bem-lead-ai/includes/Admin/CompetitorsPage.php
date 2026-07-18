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

        echo '<div class="wrap"><h1>' . esc_html__('Veille concurrentielle', 'bem-lead-ai') . '</h1>';
        echo '<p>' . esc_html__('Écoles concurrentes évoquées spontanément par les prospects dans leurs conversations. Un signal marketing direct : qui BEM Dakar affronte réellement dans la décision des étudiants.', 'bem-lead-ai') . '</p>';

        $ranking = $wpdb->get_results(
            "SELECT nom_concurrent, COUNT(*) AS mentions, COUNT(DISTINCT lead_id) AS leads, MAX(created_at) AS derniere
             FROM {$p}bem_competitor_mentions
             GROUP BY nom_concurrent ORDER BY mentions DESC LIMIT 50"
        ) ?: [];

        if (!$ranking) {
            echo '<p><em>' . esc_html__('Aucune mention de concurrent pour l\'instant.', 'bem-lead-ai') . '</em></p></div>';
            return;
        }

        echo '<table class="widefat striped" style="max-width:720px;"><thead><tr>'
            . '<th>' . esc_html__('Concurrent', 'bem-lead-ai') . '</th>'
            . '<th>' . esc_html__('Mentions', 'bem-lead-ai') . '</th>'
            . '<th>' . esc_html__('Leads distincts', 'bem-lead-ai') . '</th>'
            . '<th>' . esc_html__('Dernière mention', 'bem-lead-ai') . '</th></tr></thead><tbody>';
        foreach ($ranking as $row) {
            echo '<tr><td><strong>' . esc_html($row->nom_concurrent) . '</strong></td>'
                . '<td>' . (int) $row->mentions . '</td>'
                . '<td>' . (int) $row->leads . '</td>'
                . '<td>' . esc_html($row->derniere) . '</td></tr>';
        }
        echo '</tbody></table>';

        echo '<h2>' . esc_html__('Derniers extraits de contexte', 'bem-lead-ai') . '</h2>';
        $recent = $wpdb->get_results(
            "SELECT nom_concurrent, extrait_contexte, lead_id, created_at
             FROM {$p}bem_competitor_mentions
             WHERE extrait_contexte <> '' ORDER BY created_at DESC LIMIT 30"
        ) ?: [];
        echo '<table class="widefat striped" style="max-width:900px;"><thead><tr>'
            . '<th>' . esc_html__('Concurrent', 'bem-lead-ai') . '</th><th>' . esc_html__('Contexte', 'bem-lead-ai') . '</th>'
            . '<th>Lead</th><th>' . esc_html__('Date', 'bem-lead-ai') . '</th></tr></thead><tbody>';
        foreach ($recent as $row) {
            echo '<tr><td>' . esc_html($row->nom_concurrent) . '</td>'
                . '<td><em>' . esc_html($row->extrait_contexte) . '</em></td>'
                . '<td><a href="' . esc_url(admin_url('admin.php?page=bem-lead-ai-leads&lead_id=' . (int) $row->lead_id)) . '">#' . (int) $row->lead_id . '</a></td>'
                . '<td>' . esc_html($row->created_at) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
}

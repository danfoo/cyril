<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * CRM School IA (interne) : boîte de réception, pipeline Kanban, fiche lead.
 * URL admin : {perfex}/admin/school_ia_bridge
 */
class School_ia_bridge extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('school_ia_bridge/school_ia_bridge_model');
        $this->school_ia_bridge_model->ensure_schema();

        if (!staff_can('view', 'school_ia_bridge')) {
            access_denied('School IA CRM');
        }
    }

    /** Refuse l'accès si le membre n'a pas la capacité demandée. */
    private function need(string $cap): void
    {
        if (!staff_can($cap, 'school_ia_bridge')) {
            access_denied('School IA CRM');
        }
    }

    /** Tableau de bord : indicateurs, entonnoir, segmentation et suivi opérationnel. */
    public function dashboard()
    {
        $filters = [
            'period'    => (int) $this->input->get('period'), // 0 = tout, sinon nb de jours
            'date_from' => trim((string) $this->input->get('date_from')),
            'date_to'   => trim((string) $this->input->get('date_to')),
            'rentree'   => trim((string) $this->input->get('rentree')),
        ];
        // Cadrage : sans la permission « view_global », chaque conseiller ne voit
        // que ses propres leads/tâches/activités ; les admins et rôles autorisés
        // conservent la vue globale (tous les conseillers).
        $scopeOwner = staff_can('view_global', 'school_ia_bridge') ? null : (int) get_staff_user_id();
        if ($scopeOwner !== null) {
            $filters['owner_id'] = $scopeOwner;
        }

        $data['title']     = 'School IA — Tableau de bord';
        $data['isGlobal']  = $scopeOwner === null;
        $data['filters']   = $filters;
        $data['rentrees']  = $this->school_ia_bridge_model->rentrees();
        $data['stats']     = $this->school_ia_bridge_model->stats(60, $filters);
        $data['byFormation'] = $this->school_ia_bridge_model->by_formation($filters);
        $data['byStaff']   = $this->school_ia_bridge_model->by_staff($filters);
        $data['unassignedCount'] = $this->school_ia_bridge_model->unassigned_count($filters);
        $data['unassignedLeads'] = $this->school_ia_bridge_model->unassigned_leads($filters, 6);
        $data['recentLeads']     = $this->school_ia_bridge_model->recent_leads($filters, 8);
        $data['avgFirstContact'] = $this->school_ia_bridge_model->avg_first_contact_hours($filters);
        $data['finance']   = $this->school_ia_bridge_model->finance_summary($filters);
        $data['target']    = (int) get_option('sia_target_inscrits');
        $data['currency']  = (string) (get_option('sia_currency') ?: 'GNF');
        $data['dueTasks']  = $this->school_ia_bridge_model->pending_tasks(8, $scopeOwner);
        $data['recentActivities'] = $this->school_ia_bridge_model->global_activities(
            $scopeOwner !== null ? ['staff_id' => $scopeOwner] : [], 8
        );
        $data['model']     = $this->school_ia_bridge_model;
        $this->load->view('school_ia_bridge/dashboard', $data);
    }

    /** Boîte de réception : leads reçus, avec recherche + filtres. */
    public function index()
    {
        $filters = [
            'q'           => $this->input->get('q'),
            'stage'       => $this->input->get('stage'),
            'min_score'   => $this->input->get('min_score'),
            'rentree'     => $this->input->get('rentree'),
            'unassigned'  => $this->input->get('unassigned'),
        ];
        $leads = $this->school_ia_bridge_model->search($filters);

        // Synthèse contextuelle (sur le résultat filtré affiché).
        $summary = ['total' => count($leads), 'hot' => 0, 'warm' => 0, 'cold' => 0, 'unassigned' => 0, 'by_formation' => []];
        foreach ($leads as $l) {
            $sc = (float) $l->score;
            if ($sc >= 60) { $summary['hot']++; } elseif ($sc >= 40) { $summary['warm']++; } else { $summary['cold']++; }
            if (empty($l->owner_id)) { $summary['unassigned']++; }
            $key = trim((string) $l->formation) !== '' ? (string) $l->formation : '—';
            $summary['by_formation'][$key] = ($summary['by_formation'][$key] ?? 0) + 1;
        }
        arsort($summary['by_formation']);

        $data['title']    = 'School IA — Leads';
        $data['leads']    = $leads;
        $data['summary']  = $summary;
        $data['filters']  = $filters;
        $data['rentrees'] = $this->school_ia_bridge_model->rentrees();
        $data['staff']    = $this->db->where('active', 1)->get(db_prefix() . 'staff')->result();
        $data['feesIndex'] = $this->school_ia_bridge_model->fees_index();
        $data['currency']  = (string) (get_option('sia_currency') ?: 'GNF');
        $data['model']    = $this->school_ia_bridge_model;
        $this->load->view('school_ia_bridge/leads', $data);
    }

    /** Actions groupées sur les leads (assigner / changer d'étape / supprimer). */
    public function leads_bulk()
    {
        $this->need('manage_leads');
        $ids    = array_values(array_filter(array_map('intval', (array) $this->input->post('ids'))));
        $action = (string) $this->input->post('do');
        $return = $this->input->post('return') ?: admin_url('school_ia_bridge');

        if (empty($ids)) {
            set_alert('warning', 'Sélectionnez au moins un lead.');
            redirect($return);
        }

        $staffId = (int) $this->input->post('assign_staff');
        $stage   = (string) $this->input->post('stage');
        $me      = get_staff_user_id();
        $n = 0;

        foreach ($ids as $id) {
            if (!$this->school_ia_bridge_model->get_lead($id)) {
                continue;
            }
            if ($action === 'assign') {
                $this->school_ia_bridge_model->set_owner($id, $staffId);
                $this->school_ia_bridge_model->add_activity($id, 'assignment',
                    'Responsable : ' . ($staffId ? get_staff_full_name($staffId) : '—') . ' (action groupée)', $me);
                $n++;
            } elseif ($action === 'stage' && $stage !== '') {
                $this->school_ia_bridge_model->set_stage($id, $stage);
                $this->school_ia_bridge_model->add_activity($id, 'stage_change',
                    'Étape → ' . $this->school_ia_bridge_model->stageLabel($stage) . ' (action groupée)', $me);
                $n++;
            } elseif ($action === 'delete') {
                $this->school_ia_bridge_model->delete_lead($id);
                $n++;
            }
        }

        $msg = [
            'assign' => $n . ' lead(s) réassigné(s).',
            'stage'  => $n . ' lead(s) déplacé(s) d\'étape.',
            'delete' => $n . ' lead(s) supprimé(s).',
        ][$action] ?? ($n . ' lead(s) mis à jour.');
        set_alert('success', $msg);
        redirect($return);
    }

    /** Statistiques des campagnes (ouvertures/clics/conversion, par type). */
    public function campaigns()
    {
        $period = (int) $this->input->get('period');
        $type   = (string) $this->input->get('type'); // '' | bulk | sequence | single
        if (!in_array($type, ['', 'bulk', 'sequence', 'single'], true)) { $type = ''; }

        $data['title']    = 'School IA — Statistiques des campagnes';
        $data['period']   = $period;
        $data['type']     = $type;
        $data['stats']    = $this->school_ia_bridge_model->message_stats($period, $type);
        $data['groups']   = $this->school_ia_bridge_model->campaign_groups($period, $type);
        $data['model']    = $this->school_ia_bridge_model;
        $this->load->view('school_ia_bridge/campaigns', $data);
    }

    /** Sous-vue AJAX : destinataires d'un lot de campagne (qui a ouvert / cliqué). */
    public function campaign_detail()
    {
        $channel  = (string) $this->input->get('channel');
        $campaign = (string) $this->input->get('campaign');
        $subject  = (string) $this->input->get('subject');
        $day      = (string) $this->input->get('day');
        $recipients = $this->school_ia_bridge_model->campaign_recipients($channel, $campaign, $subject, $day);

        $rows = '';
        foreach ($recipients as $r) {
            $name = $r->lead_name ?: ('Lead #' . (int) $r->lead_id);
            $link = admin_url('school_ia_bridge/lead/' . (int) $r->lead_id);
            $opened = $r->opened_at ? '<span class="label label-success">Ouvert</span>' : '<span class="label label-default">—</span>';
            $clicked = ((int) $r->clicks > 0) ? '<span class="label label-info">' . (int) $r->clicks . ' clic(s)</span>' : '<span class="text-muted">—</span>';
            $inscrit = ($r->lead_stage === 'inscrit') ? ' <span class="label label-success"><i class="fa fa-graduation-cap"></i> Inscrit</span>' : '';
            $rows .= '<tr>'
                . '<td><a href="' . $link . '">' . htmlspecialchars($name, ENT_QUOTES) . '</a>' . $inscrit . '</td>'
                . '<td>' . $opened . '</td>'
                . '<td>' . $clicked . '</td>'
                . '<td class="text-muted">' . htmlspecialchars((string) $r->sent_at, ENT_QUOTES) . '</td>'
                . '</tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="4" class="text-muted">Aucun destinataire.</td></tr>';
        }
        header('Content-Type: text/html; charset=utf-8');
        echo '<table class="table no-margin"><thead><tr><th>Lead</th><th>Ouverture</th><th>Clics</th><th>Envoyé</th></tr></thead><tbody>' . $rows . '</tbody></table>';
    }

    /** Reporting : agrégats par période + rapports rédigés par l'IA. */
    public function reporting()
    {
        $this->need('view_reports');
        $period = $this->input->get('period') ?: 'month';
        $date   = (string) $this->input->get('date');
        [$from, $to, $label] = school_ia_period_range($period, $date);

        // Période précédente (comparaison) + granularité du graphique de tendance.
        [$pFrom, $pTo, $pLabel] = school_ia_prev_range($period, $from);
        $granularity = ['day' => 'hour', 'week' => 'day', 'month' => 'day', 'year' => 'month'][$period] ?? 'day';

        $data['title']    = 'School IA — Reporting';
        $data['period']   = $period;
        $data['date']     = $date;
        $data['label']    = $label;
        $data['from']     = $from;
        $data['to']       = $to;
        $data['agg']      = $this->school_ia_bridge_model->report_data($from, $to);
        $data['prevAgg']  = $this->school_ia_bridge_model->report_data($pFrom, $pTo);
        $data['prevLabel'] = $pLabel;
        $data['series']   = $this->school_ia_bridge_model->leads_series($from, $to, $granularity);
        $data['byStaff']  = $this->school_ia_bridge_model->by_staff_range($from, $to);
        $data['activityBreakdown'] = $this->school_ia_bridge_model->activity_breakdown($from, $to);
        $data['reports']  = $this->school_ia_bridge_model->list_reports();
        // Affiche par défaut le dernier rapport IA de la période (s'il existe).
        $data['report']   = $this->input->get('report')
            ? $this->school_ia_bridge_model->get_report((int) $this->input->get('report'))
            : $this->school_ia_bridge_model->latest_report($period, $from, $to);
        $data['ai_ready'] = trim((string) get_option('sia_ai_api_key')) !== '';
        $data['currency'] = (string) (get_option('sia_currency') ?: 'GNF');
        // Prévision financière : indépendante de la période affichée — elle
        // reflète l'état du pipeline actuel et le taux de conversion historique
        // de l'école, pas seulement l'activité de la période sélectionnée.
        $data['financeForecast'] = $this->school_ia_bridge_model->finance_forecast();
        $data['model']    = $this->school_ia_bridge_model;
        $this->load->view('school_ia_bridge/reporting', $data);
    }

    /**
     * Version imprimable / PDF du reporting : document AUTONOME, sans le menu
     * ni la barre latérale de la plateforme (contrairement à un simple
     * window.print() sur la page admin). Ouverte dans un nouvel onglet, elle
     * déclenche automatiquement la boîte de dialogue d'impression.
     */
    public function reporting_print()
    {
        $this->need('view_reports');
        $period = $this->input->get('period') ?: 'month';
        $date   = (string) $this->input->get('date');
        [$from, $to, $label] = school_ia_period_range($period, $date);
        [$pFrom, $pTo, $pLabel] = school_ia_prev_range($period, $from);
        $granularity = ['day' => 'hour', 'week' => 'day', 'month' => 'day', 'year' => 'month'][$period] ?? 'day';

        $data['period']    = $period;
        $data['label']     = $label;
        $data['from']      = $from;
        $data['to']        = $to;
        $data['prevLabel'] = $pLabel;
        $data['agg']       = $this->school_ia_bridge_model->report_data($from, $to);
        $data['prevAgg']   = $this->school_ia_bridge_model->report_data($pFrom, $pTo);
        $data['series']    = $this->school_ia_bridge_model->leads_series($from, $to, $granularity);
        $data['byStaff']   = $this->school_ia_bridge_model->by_staff_range($from, $to);
        $data['activityBreakdown'] = $this->school_ia_bridge_model->activity_breakdown($from, $to);
        $data['report']    = $this->input->get('report')
            ? $this->school_ia_bridge_model->get_report((int) $this->input->get('report'))
            : $this->school_ia_bridge_model->latest_report($period, $from, $to);
        $data['currency']  = (string) (get_option('sia_currency') ?: 'GNF');
        $data['financeForecast'] = $this->school_ia_bridge_model->finance_forecast();
        $data['brandColor'] = school_ia_brand_color();
        $data['reportLogo'] = (string) get_option('sia_report_logo');
        $data['schoolName'] = (string) (get_option('companyname') ?: 'School AI');
        $data['model']     = $this->school_ia_bridge_model;
        $this->load->view('school_ia_bridge/reporting_print', $data);
    }

    /** Export CSV de la synthèse de la période (KPIs + ventilations). */
    public function reporting_export()
    {
        $this->need('view_reports');
        $period = $this->input->get('period') ?: 'month';
        $date   = (string) $this->input->get('date');
        [$from, $to, $label] = school_ia_period_range($period, $date);
        $agg    = $this->school_ia_bridge_model->report_data($from, $to);
        $byStaff = $this->school_ia_bridge_model->by_staff_range($from, $to);

        $rows = [];
        $rows[] = ['School IA — ' . $label];
        $rows[] = ['Du', date('d/m/Y', strtotime($from)), 'au', date('d/m/Y', strtotime($to))];
        $rows[] = [];
        $rows[] = ['Indicateur', 'Valeur'];
        $rows[] = ['Nouveaux leads', $agg['leads_total']];
        $rows[] = ['Inscrits', $agg['inscrits']];
        $rows[] = ['Taux de conversion (%)', $agg['conversion']];
        $rows[] = ['E-mails envoyés', $agg['email_sent']];
        $rows[] = ['E-mails ouverts', $agg['email_opened']];
        $rows[] = ['Taux d\'ouverture (%)', $agg['open_rate']];
        $rows[] = ['E-mails cliqués', $agg['email_clicked']];
        $rows[] = ['Taux de clic (%)', $agg['click_rate']];
        $rows[] = ['SMS envoyés', $agg['sms_sent']];
        $rows[] = ['SMS en échec', $agg['sms_failed']];
        $rows[] = ['Tâches/relances créées', $agg['tasks']];
        $rows[] = ['Délai moyen 1re réponse (h)', $agg['first_response_hours'] ?? '—'];
        $rows[] = ['Délai moyen de conversion (j)', $agg['conversion_days'] ?? '—'];
        if (!empty($agg['has_fees'])) {
            $currency = (string) (get_option('sia_currency') ?: 'GNF');
            $forecast = $this->school_ia_bridge_model->finance_forecast();
            $rows[] = ['CA réalisé période (' . $currency . ')', (int) round($agg['finance_realized'])];
            $rows[] = ['Valeur ajoutée au pipeline (' . $currency . ')', (int) round($agg['finance_pipeline'])];
            $rows[] = ['Revenu prévisionnel (' . $currency . ')', (int) round($forecast['projected'] ?? 0)];
            $rows[] = ['Taux de conversion historique (%)', $forecast['conversion_rate'] ?? 0];
        }
        $rows[] = [];
        $rows[] = ['Étape du pipeline', 'Leads'];
        foreach ($agg['by_stage'] as $stage => $n) { $rows[] = [$stage, $n]; }
        $rows[] = [];
        $rows[] = ['Formation', 'Leads'];
        foreach ($agg['top_formations'] as $f => $n) { $rows[] = [$f, $n]; }
        $rows[] = [];
        if (!empty($agg['revenue_by_formation'])) {
            $rows[] = ['Revenu par formation', 'Montant'];
            foreach ($agg['revenue_by_formation'] as $f => $v) { $rows[] = [$f, (int) round($v)]; }
            $rows[] = [];
        }
        $rows[] = ['Source (site)', 'Leads'];
        foreach ($agg['by_source'] as $s => $n) { $rows[] = [$s, $n]; }
        $rows[] = [];
        $rows[] = ['Canal (UTM)', 'Leads'];
        foreach (($agg['by_channel'] ?? []) as $c => $n) { $rows[] = [$c, $n]; }
        $rows[] = [];
        $rows[] = ['Campagne (UTM)', 'Leads'];
        foreach (($agg['by_campaign'] ?? []) as $c => $n) { $rows[] = [$c, $n]; }
        $rows[] = [];
        $rows[] = ['Motif de perte', 'Leads'];
        foreach (($agg['loss_reasons'] ?? []) as $r => $n) { $rows[] = [$r, $n]; }
        $rows[] = [];
        $rows[] = ['Conseiller', 'Leads', 'Inscrits'];
        foreach ($byStaff as $st) { $rows[] = [$st->name, (int) $st->total, (int) $st->inscrits]; }

        $this->load->helper('download');
        $out = "\xEF\xBB\xBF";
        foreach ($rows as $r) {
            $out .= implode(';', array_map(static function ($c) {
                return '"' . str_replace('"', '""', (string) $c) . '"';
            }, $r)) . "\r\n";
        }
        force_download('rapport_' . $period . '_' . date('Ymd', strtotime($from)) . '.csv', $out);
    }

    /** Envoie un rapport IA enregistré par e-mail (form Perfex → CSRF). */
    public function reporting_email()
    {
        $this->need('view_reports');
        $reportId = (int) $this->input->post('report_id');
        $to       = trim((string) $this->input->post('email'));
        $report   = $this->school_ia_bridge_model->get_report($reportId);
        $backUrl  = admin_url('school_ia_bridge/reporting?report=' . $reportId);

        if (!$report) {
            set_alert('warning', 'Rapport introuvable.');
            redirect(admin_url('school_ia_bridge/reporting'));
        }
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            set_alert('warning', 'Adresse e-mail invalide.');
            redirect($backUrl);
        }

        $subject = 'School IA — ' . (string) $report->label;
        $html = '<h3 style="font-family:Arial,sans-serif;">' . htmlspecialchars((string) $report->label, ENT_QUOTES) . '</h3>'
            . '<div style="font-family:Arial,sans-serif;font-size:14px;line-height:1.6;color:#222;">' . (string) $report->content . '</div>';

        $this->load->library('email');
        $this->email->clear(true);
        $this->email->from(get_option('smtp_email') ?: get_option('companyname'), get_option('companyname'));
        $this->email->to($to);
        $this->email->subject($subject);
        $this->email->message($html);
        $this->email->set_mailtype('html');
        if ($this->email->send(false)) {
            set_alert('success', 'Rapport envoyé à ' . $to . '.');
        } else {
            set_alert('danger', 'Échec de l\'envoi. Vérifiez la configuration SMTP de Perfex (Setup → Settings → Email).');
        }
        redirect($backUrl);
    }

    /** Génère le rapport IA pour la période choisie (form Perfex → CSRF). */
    public function reporting_generate()
    {
        $this->need('view_reports');
        $period = $this->input->post('period') ?: 'month';
        $date   = (string) $this->input->post('date');
        [$from, $to, $label] = school_ia_period_range($period, $date);
        $agg = $this->school_ia_bridge_model->report_data($from, $to);

        // Comparaison avec la période précédente + performance par conseiller.
        [$pFrom, $pTo, $pLabel] = school_ia_prev_range($period, $from);
        $prev = $this->school_ia_bridge_model->report_data($pFrom, $pTo);
        $byStaff = $this->school_ia_bridge_model->by_staff_range($from, $to);
        $staffLines = [];
        foreach ($byStaff as $st) {
            $staffLines[] = $st->name . ' (' . (int) $st->total . ' leads, ' . (int) $st->inscrits . ' inscrits)';
        }

        // Données mises en forme pour l'IA.
        $lines = [];
        $lines[] = 'Période : ' . $label . ' (du ' . $from . ' au ' . $to . ')';
        $lines[] = 'Nouveaux leads : ' . $agg['leads_total'] . ' (période précédente ' . $pLabel . ' : ' . $prev['leads_total'] . ')';
        $lines[] = 'Inscrits : ' . $agg['inscrits'] . ' (taux de conversion ' . $agg['conversion'] . '% ; période précédente : ' . $prev['inscrits'] . ' inscrits, ' . $prev['conversion'] . '%)';
        $lines[] = 'Répartition par étape : ' . school_ia_kv($agg['by_stage']);
        $lines[] = 'Par source : ' . school_ia_kv($agg['by_source']);
        $lines[] = 'Formations les plus demandées : ' . (school_ia_kv($agg['top_formations']) ?: 'n/d');
        $lines[] = 'Performance par conseiller : ' . (implode(' ; ', $staffLines) ?: 'aucun lead assigné');
        $lines[] = 'E-mails envoyés : ' . $agg['email_sent'] . ' (ouverts ' . $agg['email_opened'] . ', taux d\'ouverture ' . $agg['open_rate'] . '% ; cliqués ' . $agg['email_clicked'] . ', taux de clic ' . $agg['click_rate'] . '%)';
        $lines[] = 'SMS : ' . $agg['sms_sent'] . ' envoyés, ' . $agg['sms_failed'] . ' en échec';
        $lines[] = 'Tâches/relances créées : ' . $agg['tasks'];

        $system = 'Tu es analyste CRM pour une école supérieure. À partir des données fournies, rédige un rapport clair, concis et ACTIONNABLE en français. '
            . 'Réponds en HTML simple (balises autorisées : h4, h5, p, ul, ol, li, strong, em) — sans <html>, <head>, <body>, ni styles. '
            . 'Structure : 1) Synthèse (2-3 phrases), 2) Évolution vs période précédente (croissance/baisse chiffrée), 3) Points forts, 4) Points de vigilance, 5) Recommandations concrètes, 6) Prochaines actions. '
            . 'Sois factuel, cite les chiffres, compare à la période précédente, et donne des conseils réalistes pour améliorer les admissions.';
        $prompt = "Données de la période :\n" . implode("\n", $lines);

        [$ok, $out] = school_ia_ai_generate($system, $prompt);
        if (!$ok) {
            set_alert('danger', 'Génération impossible : ' . $out);
            redirect(admin_url('school_ia_bridge/reporting?period=' . $period . ($date ? '&date=' . $date : '')));
        }

        $id = $this->school_ia_bridge_model->save_report([
            'period'    => $period,
            'label'     => $label,
            'date_from' => $from,
            'date_to'   => $to,
            'content'   => $out,
            'staff_id'  => get_staff_user_id(),
        ]);
        set_alert('success', 'Rapport généré.');
        redirect(admin_url('school_ia_bridge/reporting?period=' . $period . ($date ? '&date=' . $date : '') . '&report=' . $id));
    }

    public function reporting_delete($id = 0)
    {
        $this->school_ia_bridge_model->delete_report((int) $id);
        set_alert('success', 'Rapport supprimé.');
        redirect(admin_url('school_ia_bridge/reporting'));
    }

    /** Journal d'activité global. */
    public function activity()
    {
        $this->need('view_reports');
        $type    = $this->input->get('type') ?: null;
        $staffId = (int) $this->input->get('staff');
        $from    = (string) $this->input->get('from');
        $to      = (string) $this->input->get('to');

        // Raccourci de période : ?range=today|7|30 renseigne from/to.
        $range = (string) $this->input->get('range');
        if ($range === 'today') {
            $from = $to = date('Y-m-d');
        } elseif ($range === '7') {
            $from = date('Y-m-d', strtotime('-6 days'));
            $to   = date('Y-m-d');
        } elseif ($range === '30') {
            $from = date('Y-m-d', strtotime('-29 days'));
            $to   = date('Y-m-d');
        }

        $filters = ['type' => $type, 'staff_id' => $staffId, 'from' => $from, 'to' => $to];

        $data['title']       = 'School IA — Journal d\'activité';
        $data['type']        = $type;
        $data['staffId']     = $staffId;
        $data['from']        = $from;
        $data['to']          = $to;
        $data['range']       = $range;
        $data['activities']  = $this->school_ia_bridge_model->global_activities($filters);
        $data['staff']       = $this->db->where('active', 1)->order_by('firstname')->get(db_prefix() . 'staff')->result();
        // Classement des plus actifs : par défaut sur aujourd'hui si aucune période choisie.
        $lbFrom = $from ?: date('Y-m-d');
        $lbTo   = $to ?: date('Y-m-d');
        $data['leaderboard'] = $this->school_ia_bridge_model->activity_leaderboard($lbFrom, $lbTo);
        $data['lbLabel']     = ($from || $to)
            ? 'sur la période sélectionnée'
            : "aujourd'hui";
        $this->load->view('school_ia_bridge/activity', $data);
    }

    /** Veille concurrentielle : écoles concurrentes citées par les prospects. */
    public function competitors()
    {
        $period  = (string) ($this->input->get('period') ?: 'all');
        $program = (string) $this->input->get('program');
        $status  = (string) $this->input->get('status');

        // Traduit la période en date de début.
        $from = null;
        if ($period === 'month')   { $from = date('Y-m-01 00:00:00'); }
        elseif ($period === 'quarter') { $from = date('Y-m-01 00:00:00', strtotime('-2 months')); }
        elseif ($period === 'year')    { $from = date('Y-01-01 00:00:00'); }
        $filters = ['from' => $from, 'program' => $program, 'status' => $status];

        $data['title']    = 'School IA — Veille concurrentielle';
        $data['period']   = $period;
        $data['program']  = $program;
        $data['status']   = $status;
        $data['programs'] = $this->school_ia_bridge_model->programs();
        $data['ranking']  = $this->school_ia_bridge_model->competitor_ranking($filters);
        $data['recent']   = $this->school_ia_bridge_model->recent_competitor_mentions($filters);
        $data['totals']   = $this->school_ia_bridge_model->competitor_totals($filters);
        $data['trend']    = $this->school_ia_bridge_model->competitor_trend(['program' => $program, 'status' => $status]);
        $data['cards']    = $this->school_ia_bridge_model->battlecards();
        $data['model']    = $this->school_ia_bridge_model;
        $data['ai_ready'] = trim((string) get_option('sia_ai_api_key')) !== '';
        $this->load->view('school_ia_bridge/competitors', $data);
    }

    /** Bascule l'état « traité » d'une mention de concurrent (lien GET). */
    public function competitor_toggle($id = 0)
    {
        $this->need('view');
        $this->school_ia_bridge_model->toggle_mention_handled((int) $id);
        redirect($this->input->get('return') ?: admin_url('school_ia_bridge/competitors'));
    }

    /** Enregistre l'argumentaire de contre (« battle card ») d'un concurrent. */
    public function competitor_card_save()
    {
        $this->need('manage_settings');
        $name = trim((string) $this->input->post('name'));
        $arg  = trim((string) $this->input->post('argument'));
        if ($name !== '') {
            $this->school_ia_bridge_model->save_battlecard($name, $arg, get_staff_user_id());
            set_alert('success', 'Argumentaire enregistré pour « ' . $name .' ».');
        }
        redirect($this->input->post('return') ?: admin_url('school_ia_bridge/competitors'));
    }

    /**
     * Analyse les conversations déjà stockées dans Perfex avec l'IA (Claude) pour
     * en extraire les écoles concurrentes + la formation — sans dépendre de WordPress.
     * ?force=1 réanalyse tout (ignore les repères de scan).
     */
    public function competitors_scan()
    {
        $this->need('manage_settings');
        @set_time_limit(0);

        $force = (string) $this->input->get('force') === '1';
        $res = school_ia_scan_competitors($force);

        if (!empty($res['error'])) {
            set_alert('warning', 'IA : ' . $res['error']);
        } else {
            set_alert('success', "Analyse terminée : {$res['scanned']} conversation(s) analysée(s), {$res['found']} mention(s) de concurrent enregistrée(s).");
        }
        redirect(admin_url('school_ia_bridge/competitors'));
    }

    /** Page de diagnostic : état des tables, dernier appel concurrent, test d'écriture. */
    public function debug()
    {
        $this->need('manage_config');
        $data['title']      = 'School IA — Diagnostic';
        $data['tables']     = $this->school_ia_bridge_model->diag_counts();
        $data['writeTest']  = $this->school_ia_bridge_model->diag_write_test();
        $data['compCalls']  = (int) get_option('sia_competitor_calls');
        $data['lastCall']   = json_decode((string) get_option('sia_last_competitor_call'), true);
        $data['recentChat'] = $this->school_ia_bridge_model->recent_chat();
        $this->load->view('school_ia_bridge/debug', $data);
    }

    /** Supprime les faux messages de chat créés par d'anciennes tentatives de veille. */
    public function debug_purge_noise()
    {
        $this->need('manage_config');
        $n = $this->school_ia_bridge_model->purge_competitor_chat_noise();
        set_alert('success', $n . ' faux message(s) de veille supprimé(s).');
        redirect(admin_url('school_ia_bridge/debug'));
    }

    /** Réglages : point d'entrée + secret + identifiants SMS LAfricaMobile. */
    public function settings()
    {
        $this->need('manage_config');
        $data['title']       = 'School IA — Réglages';
        $data['secret']      = get_option('school_ia_bridge_secret');
        $data['endpoint']    = site_url('school_ia_bridge/api/receive');
        $data['sms_account'] = get_option('sia_sms_accountid');
        $data['sms_sender']  = get_option('sia_sms_sender');
        $data['sms_has_pwd'] = get_option('sia_sms_password') !== '';
        $ep = trim((string) get_option('sia_sms_endpoint'));
        $data['sms_endpoint'] = ($ep === '' || stripos($ep, 'apiSend') !== false) ? '/api' : $ep;
        $data['ai_has_key']  = trim((string) get_option('sia_ai_api_key')) !== '';
        $data['ai_model']    = get_option('sia_ai_model') ?: 'claude-opus-4-8';
        $data['program_fees'] = get_option('sia_program_fees');
        $data['target_inscrits'] = (int) get_option('sia_target_inscrits');
        $data['currency']    = (string) (get_option('sia_currency') ?: 'GNF');
        $data['brand_color'] = school_ia_brand_color();
        $data['report_logo'] = get_option('sia_report_logo');
        $this->load->view('school_ia_bridge/settings', $data);
    }

    /** Enregistre les identifiants SMS (form Perfex → CSRF). */
    public function save_settings()
    {
        $this->need('manage_config');
        // On ne met à jour que les champs réellement présents (formulaires
        // distincts : SMS d'un côté, Programmes de l'autre).
        if ($this->input->post('sms_accountid') !== null) {
            update_option('sia_sms_accountid', trim((string) $this->input->post('sms_accountid')));
        }
        if ($this->input->post('sms_sender') !== null) {
            update_option('sia_sms_sender', trim((string) $this->input->post('sms_sender')));
        }
        if ($this->input->post('sms_endpoint') !== null) {
            // On ne conserve que le chemin (ex. /apiSend) : évite d'exposer une
            // URL dans les futurs POST (pare-feu) et normalise l'entrée.
            $ep = trim((string) $this->input->post('sms_endpoint'));
            if ($ep !== '' && preg_match('#^https?://#i', $ep)) {
                $ep = (string) parse_url($ep, PHP_URL_PATH);
            }
            update_option('sia_sms_endpoint', $ep ?: '/apiSend');
        }
        $pwd = (string) $this->input->post('sms_password');
        if ($pwd !== '') { // ne pas écraser si laissé vide
            update_option('sia_sms_password', $pwd);
        }
        if ($this->input->post('programs') !== null) {
            update_option('sia_programs', (string) $this->input->post('programs'));
        }
        if ($this->input->post('program_fees') !== null) {
            update_option('sia_program_fees', (string) $this->input->post('program_fees'));
        }
        if ($this->input->post('target_inscrits') !== null) {
            update_option('sia_target_inscrits', (int) $this->input->post('target_inscrits'));
        }
        if ($this->input->post('currency') !== null) {
            $cur = trim((string) $this->input->post('currency'));
            update_option('sia_currency', $cur !== '' ? substr($cur, 0, 8) : 'GNF');
        }
        if ($this->input->post('reminders_form') !== null) {
            update_option('sia_reminders_enabled', $this->input->post('reminders_enabled') ? '1' : '0');
        }
        if ($this->input->post('notify_form') !== null) {
            update_option('sia_notify_new_conv', $this->input->post('notify_new_conv') ? '1' : '0');
            $notifyEmail = trim((string) $this->input->post('notify_email'));
            update_option('sia_notify_email', (filter_var($notifyEmail, FILTER_VALIDATE_EMAIL) ? $notifyEmail : ''));
        }
        if ($this->input->post('appearance_form') !== null) {
            $color = trim((string) $this->input->post('brand_color'));
            // On n'accepte qu'un hex #RRGGBB valide ; sinon on retombe sur le défaut.
            update_option('sia_brand_color', preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? strtolower($color) : '#d11349');
            // Logo affiché en en-tête des rapports PDF (URL absolue) ; vide = pas de logo.
            $logo = trim((string) $this->input->post('report_logo'));
            update_option('sia_report_logo', ($logo !== '' && filter_var($logo, FILTER_VALIDATE_URL)) ? $logo : '');
        }
        if ($this->input->post('ai_form') !== null) {
            $aiKey = (string) $this->input->post('ai_api_key');
            if ($aiKey !== '') { // ne pas écraser si laissé vide
                update_option('sia_ai_api_key', $aiKey);
            }
            $aiModel = trim((string) $this->input->post('ai_model'));
            if ($aiModel === '__custom__') { // « Personnalisé… » : valeur saisie à la main
                $aiModel = trim((string) $this->input->post('ai_model_custom'));
            }
            update_option('sia_ai_model', $aiModel ?: 'claude-opus-4-8');
            update_option('sia_comp_auto', $this->input->post('comp_auto') ? '1' : '0');
        }
        set_alert('success', 'Réglages enregistrés.');
        redirect(admin_url('school_ia_bridge/settings'));
    }

    /** Signature e-mail personnelle du conseiller connecté (édition + aperçu). */
    public function my_signature()
    {
        $this->need('send');
        $staffId = (int) get_staff_user_id();

        if ($this->input->post('signature_form') !== null) {
            $raw = (string) $this->input->post('signature');
            $clean = function_exists('html_purify') ? html_purify($raw) : $raw;
            update_option('sia_email_signature_' . $staffId, trim($clean));
            set_alert('success', 'Signature enregistrée.');
            redirect(admin_url('school_ia_bridge/my_signature'));
            return;
        }

        $data['title']     = 'School IA — Ma signature';
        $data['signature'] = school_ia_staff_signature($staffId);
        $this->load->view('school_ia_bridge/my_signature', $data);
    }

    /** Envoie un e-mail au lead (moteur d'e-mail de Perfex). */
    public function send_email($id = 0)
    {
        $this->need('send');
        $id = (int) $id;
        $lead = $this->school_ia_bridge_model->get_lead($id);
        $subject = trim((string) $this->input->post('subject'));
        $message = trim((string) $this->input->post('message'));

        if (!$lead || !$lead->email) {
            set_alert('warning', 'Ce lead n\'a pas d\'adresse e-mail.');
            redirect(admin_url('school_ia_bridge/lead/' . $id));
        }

        $attach = (array) $this->input->post('attachments');
        [$ok, $names] = $this->deliver_email($lead, $subject, $message, $attach, 'single');
        if ($ok) {
            $note = 'E-mail envoyé : ' . $subject . ($names ? ' (PJ : ' . implode(', ', $names) . ')' : '');
            $this->school_ia_bridge_model->add_activity($id, 'email', $note, get_staff_user_id());
            set_alert('success', 'E-mail envoyé.');
        } else {
            set_alert('danger', 'Échec de l\'envoi. Vérifiez la configuration SMTP de Perfex (Setup → Settings → Email).');
        }
        redirect(admin_url('school_ia_bridge/lead/' . $id));
    }

    /**
     * Envoie un e-mail à un lead. Renvoie [ok, [titres des PJ]].
     * Réutilisé par l'envoi unitaire et l'envoi groupé.
     */
    private function deliver_email(object $lead, string $subject, string $message, array $attachIds, string $campaign = 'single'): array
    {
        $paths = [];
        $names = [];
        foreach ($attachIds as $docId) {
            $doc = $this->school_ia_bridge_model->get_document((int) $docId);
            if ($doc) {
                $path = $this->docsDir() . $doc->stored_name;
                if (is_file($path)) {
                    $paths[] = $path;
                    $names[] = $doc->title;
                }
            }
        }
        // Envoi avec suivi (pixel d'ouverture + liens traqués + journal).
        $ok = school_ia_send_tracked_email($lead, $subject, $message, $campaign, $paths, $this->currentCampaignId);
        return [$ok, $names];
    }

    /** Id de la campagne nommée en cours d'envoi (rattachement des messages). */
    private $currentCampaignId = null;

    /** Personnalise un texte pour un lead ({prenom}, {formation}). */
    private function personalize(string $text, object $lead): string
    {
        $prenom = trim(explode('#', (string) $lead->name)[0]);
        return strtr($text, [
            '{prenom}'    => $prenom !== '' ? $prenom : 'bonjour',
            '{formation}' => $lead->formation ?: 'votre formation',
        ]);
    }

    /** Lit les filtres de ciblage d'un envoi groupé (POST). */
    private function bulkFilters(): array
    {
        return [
            'stage'     => $this->input->post('stage'),
            'program'   => $this->input->post('program'),
            'min_score' => $this->input->post('min_score'),
            'owner'     => $this->input->post('owner'),
            'date_from' => $this->input->post('date_from'),
            'date_to'   => $this->input->post('date_to'),
        ];
    }

    /** Page d'envoi groupé d'e-mails. */
    public function bulk()
    {
        $this->need('send');
        $data['title']     = 'School IA — Envoi groupé';
        $data['programs']  = $this->school_ia_bridge_model->programs();
        $data['emailTpls'] = $this->school_ia_bridge_model->templates('email');
        $data['smsTpls']   = $this->school_ia_bridge_model->templates('sms');
        $data['documents'] = $this->school_ia_bridge_model->documents();
        $data['staff']     = $this->db->where('active', 1)->get(db_prefix() . 'staff')->result();
        $data['counts']    = $this->school_ia_bridge_model->count_recipients([]);
        $data['model']     = $this->school_ia_bridge_model;
        $this->load->view('school_ia_bridge/bulk', $data);
    }

    /** Compteur dynamique de destinataires + échantillon (AJAX GET, JSON). */
    public function bulk_count()
    {
        $this->need('send');
        $filters = [
            'stage'     => $this->input->get('stage'),
            'program'   => $this->input->get('program'),
            'min_score' => $this->input->get('min_score'),
            'owner'     => $this->input->get('owner'),
            'date_from' => $this->input->get('date_from'),
            'date_to'   => $this->input->get('date_to'),
        ];
        $counts  = $this->school_ia_bridge_model->count_recipients($filters);

        // Échantillon (premier destinataire) pour l'aperçu.
        $sample = ['prenom' => 'Prénom', 'formation' => 'votre formation'];
        $rs = $this->school_ia_bridge_model->email_recipients($filters);
        if (empty($rs)) {
            $rs = $this->school_ia_bridge_model->sms_recipients($filters);
        }
        if (!empty($rs)) {
            $first = $rs[0];
            $p = trim(explode('#', (string) $first->name)[0]);
            $sample = ['prenom' => $p !== '' ? $p : 'Prénom', 'formation' => $first->formation ?: 'votre formation'];
        }

        header('Content-Type: application/json');
        echo json_encode(['email' => $counts['email'], 'sms' => $counts['sms'], 'sample' => $sample]);
    }

    /** Traite l'envoi groupé (form Perfex → CSRF). */
    public function bulk_send()
    {
        $this->need('send');
        $filters = $this->bulkFilters();
        $subjectTpl = trim((string) $this->input->post('subject'));
        $bodyTpl    = trim((string) $this->input->post('message'));
        $attach     = (array) $this->input->post('attachments');
        $name       = trim((string) $this->input->post('campaign_name'));

        $recipients = $this->school_ia_bridge_model->email_recipients($filters);
        $leadIds = array_map(static fn($l) => (int) $l->id, $recipients);

        // Persiste la campagne + met les destinataires en FILE D'ATTENTE : l'envoi
        // se fait en arrière-plan par lots (cron), sans bloquer ni risquer le
        // timeout sur les gros volumes.
        $campaignId = $this->school_ia_bridge_model->create_campaign([
            'name'        => $name !== '' ? $name : ('E-mail — ' . mb_substr($subjectTpl, 0, 60)),
            'channel'     => 'email',
            'subject'     => $subjectTpl,
            'body'        => $bodyTpl,
            'filters'     => $filters,
            'attachments' => $attach,
            'staff_id'    => get_staff_user_id(),
            'volume'      => count($leadIds),
            'status'      => 'queued',
        ]);
        $this->school_ia_bridge_model->enqueue_campaign($campaignId, $leadIds);

        // Premier lot immédiat (retour visible tout de suite sur les petites
        // campagnes) ; le reste part via le cron Perfex.
        school_ia_bridge_process_campaign_queue(25);

        set_alert('success', count($leadIds) . ' destinataire(s) programmé(s) — envoi en arrière-plan par lots.');
        redirect(admin_url('school_ia_bridge/campaign/' . (int) $campaignId));
    }

    /** Traite l'envoi groupé de SMS (form Perfex → CSRF). */
    public function bulk_sms_send()
    {
        $this->need('send');
        $filters = $this->bulkFilters();
        $bodyTpl = trim((string) $this->input->post('text'));
        $name    = trim((string) $this->input->post('campaign_name'));

        $recipients = $this->school_ia_bridge_model->sms_recipients($filters);
        $leadIds = array_map(static fn($l) => (int) $l->id, $recipients);

        $campaignId = $this->school_ia_bridge_model->create_campaign([
            'name'     => $name !== '' ? $name : ('SMS — ' . date('d/m/Y H:i')),
            'channel'  => 'sms',
            'subject'  => null,
            'body'     => $bodyTpl,
            'filters'  => $filters,
            'staff_id' => get_staff_user_id(),
            'volume'   => count($leadIds),
            'status'   => 'queued',
        ]);
        $this->school_ia_bridge_model->enqueue_campaign($campaignId, $leadIds);

        // Premier lot immédiat, le reste via le cron Perfex.
        school_ia_bridge_process_campaign_queue(25);

        set_alert('success', count($leadIds) . ' destinataire(s) programmé(s) — envoi en arrière-plan par lots.');
        redirect(admin_url('school_ia_bridge/campaign/' . (int) $campaignId));
    }

    /** Liste des campagnes (envois de masse persistés). */
    public function campaigns_list()
    {
        $data['title']     = 'School IA — Campagnes';
        $data['campaigns'] = $this->school_ia_bridge_model->campaigns_list();
        $this->load->view('school_ia_bridge/campaigns_list', $data);
    }

    /** Fiche détail d'une campagne : KPIs + destinataires + relance. */
    public function campaign($id = 0)
    {
        $id = (int) $id;
        $campaign = $this->school_ia_bridge_model->get_campaign($id);
        if (!$campaign) {
            show_404();
        }
        $filter = (string) ($this->input->get('f') ?: 'all');
        if (!in_array($filter, ['all', 'opened', 'clicked', 'unopened', 'converted'], true)) { $filter = 'all'; }

        $data['title']       = 'Campagne — ' . $campaign->name;
        $data['campaign']    = $campaign;
        $data['filter']      = $filter;
        $data['kpis']        = $this->school_ia_bridge_model->campaign_kpis($id);
        $data['recipients']  = $this->school_ia_bridge_model->campaign_recipients_by_id($id, $filter);
        $data['nonOpeners']  = ($campaign->channel === 'email') ? count($this->school_ia_bridge_model->campaign_non_openers($id)) : 0;
        $data['model']       = $this->school_ia_bridge_model;
        $this->load->view('school_ia_bridge/campaign_detail', $data);
    }

    /** Supprime une campagne (les messages restent dans l'historique). */
    public function campaign_delete($id = 0)
    {
        $this->need('send');
        if ($this->school_ia_bridge_model->get_campaign((int) $id)) {
            $this->school_ia_bridge_model->delete_campaign((int) $id);
            set_alert('success', 'Campagne supprimée.');
        }
        redirect(admin_url('school_ia_bridge/campaigns_list'));
    }

    /** Relance les destinataires n'ayant pas ouvert une campagne e-mail. */
    public function campaign_resend($id = 0)
    {
        $this->need('send');
        $id = (int) $id;
        $campaign = $this->school_ia_bridge_model->get_campaign($id);
        if (!$campaign || $campaign->channel !== 'email') {
            set_alert('warning', 'Relance impossible pour cette campagne.');
            redirect(admin_url('school_ia_bridge/campaign/' . $id));
        }
        $targets = $this->school_ia_bridge_model->campaign_non_openers($id);
        if (empty($targets)) {
            set_alert('success', 'Aucun non-ouvreur à relancer — tout le monde a ouvert. 🎉');
            redirect(admin_url('school_ia_bridge/campaign/' . $id));
        }

        // Les nouveaux envois sont rattachés à la même campagne (le suivi cumule).
        $this->currentCampaignId = $id;
        $subjectTpl = 'Rappel : ' . (string) $campaign->subject;
        $attach = $campaign->attachments ? array_filter(array_map('intval', explode(',', (string) $campaign->attachments))) : [];
        $ok = 0;
        foreach ($targets as $lead) {
            $subject = $this->personalize($subjectTpl, $lead);
            $message = $this->personalize((string) $campaign->body, $lead);
            [$sent] = $this->deliver_email($lead, $subject, $message, $attach, 'bulk');
            if ($sent) {
                $ok++;
                $this->school_ia_bridge_model->add_activity((int) $lead->id, 'email', 'Relance non-ouvreurs : ' . $subject, get_staff_user_id());
            }
        }
        $this->school_ia_bridge_model->set_campaign_volume($id, (int) $campaign->volume + $ok);
        set_alert('success', $ok . ' relance(s) envoyée(s) aux non-ouvreurs.');
        redirect(admin_url('school_ia_bridge/campaign/' . $id));
    }

    /** Envoie un SMS au lead via LAfricaMobile. */
    public function send_sms($id = 0)
    {
        $this->need('send');
        $id = (int) $id;
        $lead = $this->school_ia_bridge_model->get_lead($id);
        $text = trim((string) $this->input->post('text'));

        if (!$lead || !$lead->phone) {
            set_alert('warning', 'Ce lead n\'a pas de numéro de téléphone.');
            redirect(admin_url('school_ia_bridge/lead/' . $id));
        }

        [$ok, $info] = $this->lam_send_sms((string) $lead->phone, $text, $id);
        school_ia_log_sms($id, (bool) $ok, 'single');
        if ($ok) {
            $this->school_ia_bridge_model->add_activity($id, 'sms', 'SMS envoyé : ' . mb_substr($text, 0, 120), get_staff_user_id());
            set_alert('success', 'SMS envoyé.');
        } else {
            set_alert('danger', 'Échec de l\'envoi du SMS : ' . $info);
        }
        redirect(admin_url('school_ia_bridge/lead/' . $id));
    }

    /** Appel bas niveau à l'API SMS LAfricaMobile. Renvoie [ok, info]. */
    private function lam_send_sms(string $phone, string $text, int $leadId): array
    {
        $accountid = (string) get_option('sia_sms_accountid');
        $password  = (string) get_option('sia_sms_password');
        $sender    = (string) (get_option('sia_sms_sender') ?: 'SchoolIA');
        // Endpoint officiel LAfricaMobile (« Send via JSON ») : /api sur
        // lamsms.lafricamobile.com. On stocke un simple CHEMIN pour éviter qu'un
        // pare-feu (ModSecurity) ne bloque un POST contenant une URL.
        $endpoint = trim((string) get_option('sia_sms_endpoint'));
        // Auto-correction d'un ancien réglage erroné (/apiSend renvoyait 404).
        if ($endpoint === '' || stripos($endpoint, 'apiSend') !== false) {
            $endpoint = '/api';
        }
        if (!preg_match('#^https?://#i', $endpoint)) {
            $endpoint = 'https://lamsms.lafricamobile.com/' . ltrim($endpoint, '/');
        }

        if ($accountid === '' || $password === '') {
            return [false, 'SMS non configuré (Réglages → SMS).'];
        }
        $num = preg_replace('/\D+/', '', $phone);
        if ($num === '') {
            return [false, 'Numéro invalide.'];
        }

        $body = json_encode([
            'accountid' => $accountid,
            'password'  => $password,
            'sender'    => $sender,
            'priority'  => '2',
            'text'      => $text,
            'to'        => [['sia_' . $leadId => $num]],
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 30,
        ]);
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        curl_close($ch);

        // Nettoie un texte destiné à une alerte (Perfex l'injecte dans du JS :
        // guillemets, retours à la ligne et balises casseraient la page).
        $clean = static function (string $s): string {
            $s = strip_tags($s);
            $s = str_replace(['"', "'", '`', '\\', '<', '>', "\r", "\n", "\t"], ' ', $s);
            return trim((string) preg_replace('/\s+/', ' ', $s));
        };

        if ($resp === false) {
            return [false, 'Connexion échouée : ' . $clean($cerr)];
        }
        // Ne pas se fier au seul code HTTP : LAM répond en text/plain et peut
        // renvoyer 200 avec un message d'erreur. On inspecte la réponse.
        $ok = $code >= 200 && $code < 300;
        $respStr = (string) $resp;
        $low = mb_strtolower($respStr);
        foreach (['error', 'erreur', 'invalid', 'denied', 'forbidden', 'not found', 'unauthor', 'failed', 'echec', 'échec', '<html', '<!doctype'] as $needle) {
            if (mb_strpos($low, $needle) !== false) { $ok = false; break; }
        }
        // Si LAM répond en JSON avec un statut explicite, on le respecte.
        $decoded = json_decode($respStr, true);
        if (is_array($decoded)) {
            $status = $decoded['code'] ?? $decoded['status'] ?? $decoded['response'] ?? null;
            if ($status !== null) {
                $ok = in_array((string) $status, ['200', '0', 'OK', 'ok', 'success', 'SUCCESS', 'sent', 'SENT', 'ACCEPTED'], true);
            }
            if (!empty($decoded['error']) || !empty($decoded['errors'])) {
                $ok = false;
            }
        }
        return [$ok, 'HTTP ' . $code . ' — ' . $clean(mb_substr($respStr, 0, 300))];
    }

    /** Envoie un SMS de test et affiche la réponse BRUTE de LAfricaMobile. */
    public function test_sms()
    {
        $this->need('manage_config');
        $num = trim((string) $this->input->post('test_number'));
        if ($num === '') {
            set_alert('warning', 'Indiquez un numéro de test.');
            redirect(admin_url('school_ia_bridge/settings'));
        }
        [$ok, $info] = $this->lam_send_sms($num, 'Test SMS School IA — ' . date('H:i:s'), 0);
        set_alert($ok ? 'success' : 'danger',
            ($ok ? 'SMS de test accepté par LAM. ' : 'Échec / réponse anormale de LAM. ') . 'Réponse brute : ' . $info);
        redirect(admin_url('school_ia_bridge/settings'));
    }

    /** Régénère le secret partagé (à recopier ensuite dans le plugin). */
    public function regenerate_secret()
    {
        $this->need('manage_config');
        update_option('school_ia_bridge_secret', bin2hex(random_bytes(16)));
        set_alert('warning', 'Nouveau secret généré. Recopiez-le dans le plugin School IA (Réglages → CRM), sinon les leads n\'arriveront plus.');
        redirect(admin_url('school_ia_bridge/settings'));
    }

    /** Pipeline Kanban d'admission. */
    public function pipeline()
    {
        $data['title']   = 'School IA — Pipeline';
        $data['grouped'] = $this->school_ia_bridge_model->by_stage();
        $data['model']   = $this->school_ia_bridge_model;
        $this->load->view('school_ia_bridge/pipeline', $data);
    }

    /** Export des leads (CSV ou Excel), en respectant les filtres. */
    public function export()
    {
        $filters = [
            'q'         => $this->input->get('q'),
            'stage'     => $this->input->get('stage'),
            'min_score' => $this->input->get('min_score'),
            'rentree'   => $this->input->get('rentree'),
        ];
        $leads = $this->school_ia_bridge_model->search($filters, 100000);

        $rows = [['ID', 'Nom', 'E-mail', 'Téléphone', 'Formation', 'Score', 'Étape', 'Conseiller', 'Rentrée', 'Source', 'Reçu le']];
        foreach ($leads as $l) {
            $rows[] = [
                (int) $l->id,
                (string) $l->name,
                (string) $l->email,
                (string) $l->phone,
                (string) $l->formation,
                (string) $l->score,
                $this->school_ia_bridge_model->stageLabel($l->stage ?? 'nouveau'),
                (string) trim((string) ($l->owner_name ?? '')),
                (string) ($l->rentree ?? ''),
                (string) $l->source_site,
                (string) $l->received_at,
            ];
        }

        $this->load->helper('download');

        if (strtolower((string) $this->input->get('format')) === 'xlsx'
            && class_exists('\\PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
            $ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $ss->getActiveSheet()->fromArray($rows, null, 'A1');
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss);
            ob_start();
            $writer->save('php://output');
            force_download('leads_export_' . date('Ymd') . '.xlsx', ob_get_clean());
            return;
        }

        // CSV — séparateur « ; » + BOM (ouverture directe dans Excel FR).
        $out = "\xEF\xBB\xBF";
        foreach ($rows as $r) {
            $out .= implode(';', array_map(static function ($c) {
                return '"' . str_replace('"', '""', (string) $c) . '"';
            }, $r)) . "\r\n";
        }
        force_download('leads_export_' . date('Ymd') . '.csv', $out);
    }

    /** Formulaire d'import CSV / Excel. */
    public function import()
    {
        $this->need('manage_leads');
        $data['title']    = 'School IA — Importer des leads';
        $data['programs'] = $this->school_ia_bridge_model->programs();
        $data['rentrees'] = $this->school_ia_bridge_model->rentrees();
        $data['model']    = $this->school_ia_bridge_model;
        $this->load->view('school_ia_bridge/import', $data);
    }

    /** Modèle CSV à télécharger. */
    public function import_template()
    {
        $this->need('manage_leads');
        $this->load->helper('download');
        $csv = "nom,email,telephone,formation,score,etape\n"
             . "Awa Diallo,awa@exemple.com,221771234567,Licence Marketing,20,nouveau\n";
        force_download('modele_import_leads.csv', "\xEF\xBB\xBF" . $csv);
    }

    private function norm(string $s): string
    {
        $s = mb_strtolower(trim($s));
        return strtr($s, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c',
        ]);
    }

    /** Traite l'import (form multipart Perfex → CSRF). */
    public function import_run()
    {
        $this->need('manage_leads');
        if (empty($_FILES['file']['name']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            set_alert('warning', 'Aucun fichier valide sélectionné.');
            redirect(admin_url('school_ia_bridge/import'));
        }
        $file = $_FILES['file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        // Lecture des lignes selon le format.
        $rows = [];
        if (in_array($ext, ['xlsx', 'xls'], true)) {
            if (!class_exists('\\PhpOffice\\PhpSpreadsheet\\IOFactory')) {
                set_alert('danger', 'Format Excel non pris en charge sur ce serveur. Enregistrez le fichier en CSV et réessayez.');
                redirect(admin_url('school_ia_bridge/import'));
            }
            $rows = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name'])
                ->getActiveSheet()->toArray(null, true, true, false);
        } elseif (in_array($ext, ['csv', 'txt'], true)) {
            $content = (string) file_get_contents($file['tmp_name']);
            $content = preg_replace('/^\xEF\xBB\xBF/', '', $content); // BOM
            $lines = preg_split('/\r\n|\r|\n/', $content);
            $first = '';
            foreach ($lines as $l) { if (trim($l) !== '') { $first = $l; break; } }
            $delim = (substr_count($first, ';') > substr_count($first, ',')) ? ';' : ',';
            foreach ($lines as $l) {
                if (trim($l) !== '') { $rows[] = str_getcsv($l, $delim); }
            }
        } else {
            set_alert('danger', 'Format non supporté (' . $ext . '). Utilisez CSV ou Excel.');
            redirect(admin_url('school_ia_bridge/import'));
        }

        if (count($rows) < 2) {
            set_alert('warning', 'Le fichier ne contient pas de données (en-tête + au moins une ligne attendus).');
            redirect(admin_url('school_ia_bridge/import'));
        }

        // Cartographie des colonnes d'après l'en-tête.
        $headers = array_shift($rows);
        $map = [];
        foreach ($headers as $i => $h) {
            $n = $this->norm((string) $h);
            if (strpos($n, 'mail') !== false || strpos($n, 'courriel') !== false) {
                $map[$i] = 'email';
            } elseif (strpos($n, 'tel') !== false || strpos($n, 'phone') !== false || strpos($n, 'numero') !== false || strpos($n, 'mobile') !== false || strpos($n, 'gsm') !== false) {
                $map[$i] = 'phone';
            } elseif (strpos($n, 'formation') !== false || strpos($n, 'programme') !== false || strpos($n, 'program') !== false || strpos($n, 'filiere') !== false || strpos($n, 'cursus') !== false) {
                $map[$i] = 'formation';
            } elseif (strpos($n, 'score') !== false || strpos($n, 'note') !== false) {
                $map[$i] = 'score';
            } elseif (strpos($n, 'etape') !== false || strpos($n, 'stage') !== false || strpos($n, 'statut') !== false || strpos($n, 'status') !== false) {
                $map[$i] = 'stage';
            } elseif (strpos($n, 'rentree') !== false || strpos($n, 'promo') !== false || strpos($n, 'session') !== false || strpos($n, 'annee') !== false) {
                $map[$i] = 'rentree';
            } elseif (strpos($n, 'nom') !== false || strpos($n, 'name') !== false || strpos($n, 'prenom') !== false) {
                $map[$i] = 'name';
            }
        }

        $defProgram = trim((string) $this->input->post('default_program'));
        $defStage = (string) $this->input->post('default_stage') ?: 'nouveau';
        $defRentree = trim((string) $this->input->post('default_rentree'));

        $imported = 0;
        $skipped = 0;
        foreach ($rows as $r) {
            $rec = ['name' => '', 'email' => '', 'phone' => '', 'formation' => '', 'score' => '', 'stage' => '', 'rentree' => ''];
            foreach ($map as $i => $field) {
                $rec[$field] = isset($r[$i]) ? trim((string) $r[$i]) : '';
            }
            if ($rec['name'] === '' && $rec['email'] === '' && $rec['phone'] === '') {
                continue; // ligne vide
            }
            if ($rec['formation'] === '' && $defProgram !== '') {
                $rec['formation'] = $defProgram;
            }
            if ($rec['stage'] === '') {
                $rec['stage'] = $defStage;
            }
            if ($rec['rentree'] === '' && $defRentree !== '') {
                $rec['rentree'] = $defRentree;
            }
            if ($rec['email'] !== '' && $this->school_ia_bridge_model->email_exists($rec['email'])) {
                $skipped++;
                continue;
            }
            $this->school_ia_bridge_model->create_lead($rec + ['source_site' => 'Import fichier']);
            $imported++;
        }

        set_alert('success', $imported . ' lead(s) importé(s)' . ($skipped > 0 ? ', ' . $skipped . ' doublon(s) ignoré(s).' : '.'));
        redirect(admin_url('school_ia_bridge'));
    }

    /** Formulaire d'ajout manuel d'un lead. */
    public function new_lead()
    {
        $this->need('manage_leads');
        $data['title']    = 'School IA — Ajouter un lead';
        $data['programs'] = $this->school_ia_bridge_model->programs();
        $data['rentrees'] = $this->school_ia_bridge_model->rentrees();
        $data['model']    = $this->school_ia_bridge_model;
        $this->load->view('school_ia_bridge/lead_new', $data);
    }

    /** Enregistre le lead saisi manuellement (form Perfex → CSRF). */
    public function store_lead()
    {
        $this->need('manage_leads');
        $name  = trim((string) $this->input->post('name'));
        $email = trim((string) $this->input->post('email'));
        $phone = trim((string) $this->input->post('phone'));

        if ($name === '' && $email === '' && $phone === '') {
            set_alert('warning', 'Renseignez au moins un nom, un e-mail ou un téléphone.');
            redirect(admin_url('school_ia_bridge/new_lead'));
        }

        $id = $this->school_ia_bridge_model->create_lead([
            'name'      => $name,
            'email'     => $email,
            'phone'     => $phone,
            'formation' => $this->input->post('formation'),
            'score'     => $this->input->post('score'),
            'stage'     => $this->input->post('stage'),
            'rentree'   => $this->input->post('rentree'),
        ]);
        $this->school_ia_bridge_model->add_activity($id, 'note', 'Lead créé manuellement.', get_staff_user_id());
        set_alert('success', 'Lead ajouté.');
        redirect(admin_url('school_ia_bridge/lead/' . $id));
    }

    /** Formulaire d'édition d'un lead existant. */
    public function edit_lead($id = 0)
    {
        $this->need('manage_leads');
        $id = (int) $id;
        $lead = $this->school_ia_bridge_model->get_lead($id);
        if (!$lead) {
            show_404();
        }
        $data['title']    = 'Modifier — ' . ($lead->name ?: ('Lead #' . $lead->id));
        $data['lead']     = $lead;
        $data['programs'] = $this->school_ia_bridge_model->programs();
        $data['rentrees'] = $this->school_ia_bridge_model->rentrees();
        $data['model']    = $this->school_ia_bridge_model;
        $this->load->view('school_ia_bridge/lead_edit', $data);
    }

    /** Enregistre les modifications d'un lead (form Perfex → CSRF). */
    public function update_lead()
    {
        $this->need('manage_leads');
        $id = (int) $this->input->post('id');
        $lead = $this->school_ia_bridge_model->get_lead($id);
        if (!$lead) {
            show_404();
        }
        $name  = trim((string) $this->input->post('name'));
        $email = trim((string) $this->input->post('email'));
        $phone = trim((string) $this->input->post('phone'));

        if ($name === '' && $email === '' && $phone === '') {
            set_alert('warning', 'Renseignez au moins un nom, un e-mail ou un téléphone.');
            redirect(admin_url('school_ia_bridge/edit_lead/' . $id));
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_alert('warning', 'L\'adresse e-mail saisie n\'est pas valide.');
            redirect(admin_url('school_ia_bridge/edit_lead/' . $id));
        }

        $this->school_ia_bridge_model->update_lead($id, [
            'name'      => $name,
            'email'     => $email,
            'phone'     => $phone,
            'formation' => $this->input->post('formation'),
            'score'     => $this->input->post('score'),
            'stage'     => $this->input->post('stage'),
            'rentree'   => $this->input->post('rentree'),
        ]);
        $this->school_ia_bridge_model->add_activity($id, 'note', 'Informations du lead modifiées.', get_staff_user_id());
        set_alert('success', 'Lead mis à jour.');
        redirect(admin_url('school_ia_bridge/lead/' . $id));
    }

    /** Fiche détaillée d'un lead. */
    public function lead($id = 0)
    {
        $lead = $this->school_ia_bridge_model->get_lead((int) $id);
        if (!$lead) {
            show_404();
        }
        $data['title']      = $lead->name ?: ('Lead #' . $lead->id);
        $data['lead']       = $lead;
        $data['activities'] = $this->school_ia_bridge_model->activities((int) $lead->id);
        $data['tasks']      = $this->school_ia_bridge_model->tasks_for_lead((int) $lead->id);
        $data['staff']      = $this->db->where('active', 1)->get(db_prefix() . 'staff')->result();
        $data['rentrees']   = $this->school_ia_bridge_model->rentrees();
        $data['emailTpls']  = $this->school_ia_bridge_model->templates('email');
        $data['smsTpls']    = $this->school_ia_bridge_model->templates('sms');
        $data['documents']  = $this->school_ia_bridge_model->documents();
        $data['sequences']  = $this->school_ia_bridge_model->active_sequences();
        $data['enrollments'] = $this->school_ia_bridge_model->enrollments_for_lead((int) $lead->id);
        $data['chatMessages'] = $this->school_ia_bridge_model->chat_messages((int) $lead->id);
        $data['competitors']  = $this->school_ia_bridge_model->competitors_for_lead((int) $lead->id);
        $data['ai_ready']   = trim((string) get_option('sia_ai_api_key')) !== '';
        $data['aiSummary']  = json_decode((string) get_option('sia_lead_summary_' . (int) $lead->id), true) ?: null;
        $data['model']      = $this->school_ia_bridge_model;
        $this->load->view('school_ia_bridge/lead', $data);
    }

    /** Génère un résumé IA (3 points) de la conversation du lead. */
    public function lead_summarize($id = 0)
    {
        $this->need('manage_leads');
        $id = (int) $id;
        $lead = $this->school_ia_bridge_model->get_lead($id);
        if (!$lead) {
            show_404();
        }
        $back = admin_url('school_ia_bridge/lead/' . $id) . '#tab-ia';

        if (trim((string) get_option('sia_ai_api_key')) === '') {
            set_alert('warning', 'Configurez d\'abord la clé API IA (Réglages → Rapports IA).');
            redirect($back);
        }
        $messages = $this->school_ia_bridge_model->chat_messages($id);
        if (empty($messages)) {
            set_alert('warning', 'Aucune conversation à résumer pour ce lead.');
            redirect($back);
        }

        $lines = [];
        foreach ($messages as $m) {
            $who = $m->role === 'user' ? 'Prospect' : 'Conseiller IA';
            $lines[] = $who . ' : ' . trim((string) $m->content);
        }
        $system = 'Tu es assistant CRM pour une école supérieure. Résume la conversation ci-dessous pour un conseiller commercial qui doit rappeler ce prospect. '
            . 'Réponds en HTML simple (uniquement <ul><li> et <strong>), sans <html>/<head>/<body>. '
            . '3 à 5 puces maximum : besoin/projet du prospect, formation visée, objections ou concurrents cités, niveau d\'urgence, et LA prochaine action recommandée pour le conseiller.';
        $prompt = "Conversation :\n" . implode("\n", $lines);

        [$ok, $out] = school_ia_ai_generate($system, $prompt);
        if (!$ok) {
            set_alert('danger', 'Résumé impossible : ' . $out);
            redirect($back);
        }
        update_option('sia_lead_summary_' . $id, json_encode(['content' => $out, 'at' => date('Y-m-d H:i:s')], JSON_UNESCAPED_UNICODE));
        set_alert('success', 'Résumé généré.');
        redirect($back);
    }

    /** Supprime un lead et toutes ses données rattachées. */
    public function lead_delete($id = 0)
    {
        $this->need('manage_leads');
        if ($this->school_ia_bridge_model->get_lead((int) $id)) {
            $this->school_ia_bridge_model->delete_lead((int) $id);
            set_alert('success', 'Lead supprimé.');
        }
        redirect(admin_url('school_ia_bridge'));
    }

    /** Dossier de stockage des documents. */
    private function docsDir(): string
    {
        return FCPATH . 'uploads/school_ia_documents/';
    }

    /** Gestionnaire de documents, groupés par programme. */
    public function documents()
    {
        $this->need('manage_settings');
        $data['title']    = 'School IA — Documents';
        $data['grouped']  = $this->school_ia_bridge_model->documents_grouped();
        $data['programs'] = $this->school_ia_bridge_model->programs();
        $this->load->view('school_ia_bridge/documents', $data);
    }

    /** Upload d'un document (form multipart Perfex → CSRF). */
    public function doc_upload()
    {
        $this->need('manage_settings');
        $program = trim((string) $this->input->post('program'));
        $title   = trim((string) $this->input->post('title'));

        if (empty($_FILES['file']['name']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            set_alert('warning', 'Aucun fichier valide sélectionné.');
            redirect(admin_url('school_ia_bridge/documents'));
        }
        $file = $_FILES['file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'zip', 'txt', 'csv'];
        if (!in_array($ext, $allowed, true)) {
            set_alert('danger', 'Type de fichier non autorisé (' . $ext . ').');
            redirect(admin_url('school_ia_bridge/documents'));
        }
        if ($file['size'] > 20 * 1024 * 1024) {
            set_alert('danger', 'Fichier trop volumineux (max 20 Mo).');
            redirect(admin_url('school_ia_bridge/documents'));
        }

        $dir = $this->docsDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $stored = uniqid('doc_', true) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $dir . $stored)) {
            set_alert('danger', 'Échec de l\'enregistrement du fichier.');
            redirect(admin_url('school_ia_bridge/documents'));
        }

        $this->school_ia_bridge_model->add_document([
            'program'     => $program,
            'title'       => $title !== '' ? $title : $file['name'],
            'orig_name'   => $file['name'],
            'stored_name' => $stored,
            'mime'        => $file['type'],
            'filesize'    => (int) $file['size'],
            'staff_id'    => get_staff_user_id(),
        ]);
        set_alert('success', 'Document ajouté.');
        redirect(admin_url('school_ia_bridge/documents'));
    }

    /** Téléchargement d'un document. */
    public function doc_download($id = 0)
    {
        $this->need('manage_settings');
        $doc = $this->school_ia_bridge_model->get_document((int) $id);
        if (!$doc) {
            show_404();
        }
        $path = $this->docsDir() . $doc->stored_name;
        if (!is_file($path)) {
            show_404();
        }
        $this->load->helper('download');
        force_download($doc->orig_name ?: $doc->title, file_get_contents($path));
    }

    public function doc_delete($id = 0)
    {
        $this->need('manage_settings');
        $doc = $this->school_ia_bridge_model->get_document((int) $id);
        if ($doc) {
            $path = $this->docsDir() . $doc->stored_name;
            if (is_file($path)) {
                @unlink($path);
            }
            $this->school_ia_bridge_model->delete_document((int) $doc->id);
            set_alert('success', 'Document supprimé.');
        }
        redirect(admin_url('school_ia_bridge/documents'));
    }

    /** Séquences de relance : liste + gestion des étapes d'une séquence. */
    public function sequences()
    {
        $this->need('manage_settings');
        $data['title']     = 'School IA — Séquences';
        $data['sequences'] = $this->school_ia_bridge_model->sequences();
        $current = $this->input->get('id') ? $this->school_ia_bridge_model->get_sequence((int) $this->input->get('id')) : null;
        $data['current']   = $current;
        $data['steps']     = $current ? $this->school_ia_bridge_model->sequence_steps((int) $current->id) : [];
        $data['emailTpls'] = $this->school_ia_bridge_model->templates('email');
        $data['smsTpls']   = $this->school_ia_bridge_model->templates('sms');
        $data['model']     = $this->school_ia_bridge_model;
        $this->load->view('school_ia_bridge/sequences', $data);
    }

    public function sequence_save()
    {
        $this->need('manage_settings');
        $id = $this->school_ia_bridge_model->save_sequence([
            'id'     => (int) $this->input->post('id'),
            'name'   => $this->input->post('name'),
            'active' => $this->input->post('active'),
        ]);
        set_alert('success', 'Séquence enregistrée.');
        redirect(admin_url('school_ia_bridge/sequences?id=' . $id));
    }

    public function sequence_delete($id = 0)
    {
        $this->need('manage_settings');
        $this->school_ia_bridge_model->delete_sequence((int) $id);
        set_alert('success', 'Séquence supprimée.');
        redirect(admin_url('school_ia_bridge/sequences'));
    }

    public function step_add()
    {
        $this->need('manage_settings');
        $seqId = (int) $this->input->post('sequence_id');
        $this->school_ia_bridge_model->add_step([
            'sequence_id' => $seqId,
            'channel'     => $this->input->post('channel'),
            'template_id' => $this->input->post('template_id'),
            'delay_days'  => $this->input->post('delay_days'),
            'delay_hours' => $this->input->post('delay_hours'),
        ]);
        set_alert('success', 'Étape ajoutée.');
        redirect(admin_url('school_ia_bridge/sequences?id=' . $seqId));
    }

    public function step_delete($id = 0)
    {
        $this->need('manage_settings');
        $step = $this->db->where('id', (int) $id)->get(db_prefix() . 'school_ia_sequence_steps')->row();
        $this->school_ia_bridge_model->delete_step((int) $id);
        set_alert('success', 'Étape supprimée.');
        redirect(admin_url('school_ia_bridge/sequences?id=' . ($step ? (int) $step->sequence_id : 0)));
    }

    public function enroll($leadId = 0)
    {
        $this->need('manage_leads');
        $leadId = (int) $leadId;
        $seqId = (int) $this->input->post('sequence_id');
        if ($this->school_ia_bridge_model->get_lead($leadId) && $seqId) {
            if ($this->school_ia_bridge_model->enroll($seqId, $leadId)) {
                $this->school_ia_bridge_model->add_activity($leadId, 'note', 'Inscrit à une séquence de relance.', get_staff_user_id());
                set_alert('success', 'Lead inscrit à la séquence.');
            } else {
                set_alert('warning', 'Déjà inscrit (ou séquence sans étape).');
            }
        }
        redirect(admin_url('school_ia_bridge/lead/' . $leadId));
    }

    public function unenroll($enrollmentId = 0)
    {
        $this->need('manage_leads');
        $en = $this->db->where('id', (int) $enrollmentId)->get(db_prefix() . 'school_ia_enrollments')->row();
        if ($en) {
            $this->school_ia_bridge_model->stop_enrollment((int) $enrollmentId);
            set_alert('success', 'Séquence arrêtée pour ce lead.');
        }
        redirect(admin_url('school_ia_bridge/lead/' . ($en ? (int) $en->lead_id : 0)));
    }

    /** Bibliothèque de modèles e-mail / SMS. */
    public function templates()
    {
        $this->need('manage_settings');
        $data['title']     = 'School IA — Modèles';
        $data['templates'] = $this->school_ia_bridge_model->templates();
        $data['edit']      = $this->input->get('edit') ? $this->school_ia_bridge_model->get_template((int) $this->input->get('edit')) : null;
        $this->load->view('school_ia_bridge/templates', $data);
    }

    public function template_save()
    {
        $this->need('manage_settings');
        $this->school_ia_bridge_model->save_template([
            'id'      => (int) $this->input->post('id'),
            'type'    => $this->input->post('type'),
            'name'    => $this->input->post('name'),
            'subject' => $this->input->post('subject'),
            'body'    => $this->input->post('body'),
        ]);
        set_alert('success', 'Modèle enregistré.');
        redirect(admin_url('school_ia_bridge/templates'));
    }

    public function template_delete($id = 0)
    {
        $this->need('manage_settings');
        $this->school_ia_bridge_model->delete_template((int) $id);
        set_alert('success', 'Modèle supprimé.');
        redirect(admin_url('school_ia_bridge/templates'));
    }

    /** Page Tâches : outil de pilotage (filtres statut/priorité, actions groupées). */
    public function tasks()
    {
        $filter   = (string) ($this->input->get('filter') ?: 'todo');
        $priority = (string) $this->input->get('priority');
        $data['title']    = 'School IA — Tâches';
        $data['filter']   = $filter;
        $data['priority'] = $priority;
        $data['tasks']    = $this->school_ia_bridge_model->task_list($filter, $priority);
        $data['counts']   = $this->school_ia_bridge_model->task_counts();
        $data['staff']    = $this->db->where('active', 1)->get(db_prefix() . 'staff')->result();
        $data['leads']    = $this->school_ia_bridge_model->get_leads(500);
        $data['model']    = $this->school_ia_bridge_model;
        $this->load->view('school_ia_bridge/tasks', $data);
    }

    /** Création rapide d'une tâche depuis la page Tâches (lead facultatif). */
    public function task_quick_add()
    {
        $this->need('manage_leads');
        $title = trim((string) $this->input->post('title'));
        $due   = trim((string) $this->input->post('due_at'));
        $dueAt = $due !== '' ? date('Y-m-d H:i:s', strtotime($due)) : null;
        $leadId = (int) $this->input->post('lead_id');
        $staffId = (int) $this->input->post('staff_id') ?: get_staff_user_id();
        $priority = (string) $this->input->post('priority');

        if ($title === '') {
            set_alert('warning', 'Indiquez au moins un intitulé de tâche.');
            redirect(admin_url('school_ia_bridge/tasks'));
        }
        // Rattache au lead seulement s'il existe réellement.
        if ($leadId && !$this->school_ia_bridge_model->get_lead($leadId)) {
            $leadId = 0;
        }
        $this->school_ia_bridge_model->add_task($leadId, $title, $dueAt, $staffId ?: null, $priority);
        set_alert('success', 'Tâche ajoutée.');
        redirect(admin_url('school_ia_bridge/tasks'));
    }

    /** Actions groupées sur les tâches (terminer / reporter / réassigner / supprimer). */
    public function tasks_bulk()
    {
        $this->need('manage_leads');
        $ids    = (array) $this->input->post('ids');
        $action = (string) $this->input->post('do');
        $staff  = (int) $this->input->post('reassign_staff');
        if (empty($ids)) {
            set_alert('warning', 'Sélectionnez au moins une tâche.');
            redirect(admin_url('school_ia_bridge/tasks'));
        }
        $n = $this->school_ia_bridge_model->bulk_tasks($ids, $action, $staff ?: null);
        set_alert('success', $n . ' tâche(s) mise(s) à jour.');
        redirect($this->input->post('return') ?: admin_url('school_ia_bridge/tasks'));
    }

    /** Change la priorité d'une tâche (lien GET). */
    public function task_priority($taskId = 0)
    {
        $this->need('manage_leads');
        $this->school_ia_bridge_model->set_task_priority((int) $taskId, (string) $this->input->get('p'));
        redirect($this->input->get('return') ?: admin_url('school_ia_bridge/tasks'));
    }

    /** Ajoute une tâche à un lead (form Perfex → CSRF). */
    public function task_add($leadId = 0)
    {
        $this->need('manage_leads');
        $leadId = (int) $leadId;
        $title = trim((string) $this->input->post('title'));
        $due = trim((string) $this->input->post('due_at'));
        $priority = (string) $this->input->post('priority');
        // <input type="datetime-local"> renvoie "Y-m-d\TH:i" → format MySQL.
        $dueAt = $due !== '' ? date('Y-m-d H:i:s', strtotime($due)) : null;
        if ($this->school_ia_bridge_model->get_lead($leadId) && $title !== '') {
            $this->school_ia_bridge_model->add_task($leadId, $title, $dueAt, get_staff_user_id(), $priority);
            set_alert('success', 'Tâche ajoutée.');
        }
        redirect(admin_url('school_ia_bridge/lead/' . $leadId));
    }

    /** Coche/décoche une tâche (lien GET). */
    public function task_toggle($taskId = 0)
    {
        $this->need('manage_leads');
        $task = $this->school_ia_bridge_model->get_task((int) $taskId);
        if ($task) {
            $this->school_ia_bridge_model->toggle_task((int) $taskId);
        }
        $back = (string) $this->input->get('back');
        if ($back === 'activity') {
            redirect(admin_url('school_ia_bridge/activity' . ($this->input->get('qs') ? '?' . $this->input->get('qs') : '')));
        }
        if ($back === 'tasks' || !$task || (int) $task->lead_id === 0) {
            redirect(admin_url('school_ia_bridge/tasks'));
        }
        redirect(admin_url('school_ia_bridge/lead/' . (int) $task->lead_id));
    }

    /** Supprime une tâche (lien GET). */
    public function task_delete($taskId = 0)
    {
        $this->need('manage_leads');
        $task = $this->school_ia_bridge_model->get_task((int) $taskId);
        if ($task) {
            $this->school_ia_bridge_model->delete_task((int) $taskId);
        }
        // Retour à la page Tâches si demandé ou si la tâche n'est rattachée à aucun lead.
        if ($this->input->get('back') === 'tasks' || !$task || (int) $task->lead_id === 0) {
            redirect(admin_url('school_ia_bridge/tasks'));
        }
        redirect(admin_url('school_ia_bridge/lead/' . (int) $task->lead_id));
    }

    /** Change l'étape du pipeline (lien GET → pas de blocage CSRF). */
    public function move($id = 0)
    {
        $this->need('manage_leads');
        $id = (int) $id;
        $stage = $this->input->get('stage');
        $lead = $this->school_ia_bridge_model->get_lead($id);
        $done = false;
        if ($lead && $stage) {
            $fromLabel = $this->school_ia_bridge_model->stageLabel($lead->stage ?? 'nouveau');
            $toLabel   = $this->school_ia_bridge_model->stageLabel($stage);
            $this->school_ia_bridge_model->set_stage($id, $stage);
            // Motif de perte (facultatif) renseigné depuis la fiche lead.
            $reason = trim((string) $this->input->get('reason'));
            if ($stage === 'perdu' && $reason !== '') {
                $this->school_ia_bridge_model->set_lost_reason($id, $reason);
                $toLabel .= ' (' . $reason . ')';
            }
            $this->school_ia_bridge_model->add_activity(
                $id,
                'stage_change',
                'Étape : ' . $fromLabel . ' → ' . $toLabel,
                get_staff_user_id()
            );
            $done = true;
        }

        // Appel AJAX (glisser-déposer) : réponse JSON, pas de redirection.
        if ($this->input->is_ajax_request() || $this->input->get('ajax')) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => $done]);
            return;
        }

        if ($done) {
            set_alert('success', 'Étape mise à jour.');
        }
        redirect($this->input->get('back') === 'pipeline'
            ? admin_url('school_ia_bridge/pipeline')
            : admin_url('school_ia_bridge/lead/' . $id));
    }

    /** Ajoute une note (form Perfex → jeton CSRF inclus). */
    public function note($id = 0)
    {
        $this->need('manage_leads');
        $id = (int) $id;
        $content = trim((string) $this->input->post('content'));
        $lead = $this->school_ia_bridge_model->get_lead($id);
        if ($lead && $content !== '') {
            $this->school_ia_bridge_model->add_activity($id, 'note', $content, get_staff_user_id());
            set_alert('success', 'Note ajoutée.');
        }
        redirect(admin_url('school_ia_bridge/lead/' . $id));
    }

    /** Assigne un responsable (staff). */
    public function assign($id = 0)
    {
        $this->need('manage_leads');
        $id = (int) $id;
        $staffId = (int) $this->input->post('owner_id');
        if (!$this->school_ia_bridge_model->get_lead($id)) {
            redirect(admin_url('school_ia_bridge/lead/' . $id));
            return;
        }

        // « Prendre en charge » = prise en charge premier arrivé, premier servi :
        // on n'écrase JAMAIS un responsable déjà positionné (plusieurs conseillers
        // peuvent cliquer depuis la même alerte e-mail).
        if ($this->input->post('claim')) {
            if ($this->school_ia_bridge_model->claim_owner($id, $staffId)) {
                $this->school_ia_bridge_model->add_activity($id, 'assignment',
                    'Pris en charge par ' . get_staff_full_name($staffId), get_staff_user_id());
                set_alert('success', 'Vous avez pris en charge ce lead.');
            } else {
                $current = (int) ($this->school_ia_bridge_model->get_lead($id)->owner_id ?? 0);
                $who = $current ? get_staff_full_name($current) : 'un autre conseiller';
                set_alert('warning', 'Ce lead a déjà été pris en charge par ' . $who . '.');
            }
            redirect(admin_url('school_ia_bridge/lead/' . $id));
            return;
        }

        // Réassignation manuelle (liste déroulante, ex. par un responsable) :
        // l'écrasement reste autorisé.
        $this->school_ia_bridge_model->set_owner($id, $staffId);
        $this->school_ia_bridge_model->add_activity($id, 'assignment',
            'Responsable : ' . ($staffId ? get_staff_full_name($staffId) : '—'), get_staff_user_id());
        set_alert('success', 'Responsable mis à jour.');
        redirect(admin_url('school_ia_bridge/lead/' . $id));
    }

    /** Renseigne la rentrée / année académique visée par le lead. */
    public function set_rentree($id = 0)
    {
        $this->need('manage_leads');
        $id = (int) $id;
        $rentree = trim((string) $this->input->post('rentree'));
        if ($this->school_ia_bridge_model->get_lead($id)) {
            $this->school_ia_bridge_model->set_rentree($id, $rentree);
            $this->school_ia_bridge_model->add_activity($id, 'note',
                'Rentrée : ' . ($rentree !== '' ? $rentree : '—'), get_staff_user_id());
            set_alert('success', 'Rentrée mise à jour.');
        }
        redirect(admin_url('school_ia_bridge/lead/' . $id));
    }
}

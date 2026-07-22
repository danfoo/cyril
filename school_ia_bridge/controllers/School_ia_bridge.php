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

    /** Tableau de bord : indicateurs + entonnoir. */
    public function dashboard()
    {
        $period = (int) $this->input->get('period'); // 0 = tout, sinon nb de jours
        $data['title']    = 'School IA — Tableau de bord';
        $data['period']   = $period;
        $data['stats']    = $this->school_ia_bridge_model->stats(60, $period);
        $data['bySource'] = $this->school_ia_bridge_model->by_source($period);
        $data['byStaff']  = $this->school_ia_bridge_model->by_staff($period);
        $data['dueTasks'] = $this->school_ia_bridge_model->pending_tasks(8);
        $data['model']    = $this->school_ia_bridge_model;
        $this->load->view('school_ia_bridge/dashboard', $data);
    }

    /** Boîte de réception : leads reçus, avec recherche + filtres. */
    public function index()
    {
        $filters = [
            'q'         => $this->input->get('q'),
            'stage'     => $this->input->get('stage'),
            'min_score' => $this->input->get('min_score'),
        ];
        $data['title']   = 'School IA — Leads';
        $data['leads']   = $this->school_ia_bridge_model->search($filters);
        $data['filters'] = $filters;
        $data['model']   = $this->school_ia_bridge_model;
        $this->load->view('school_ia_bridge/leads', $data);
    }

    /** Statistiques des campagnes (ouvertures/clics e-mail, SMS). */
    public function campaigns()
    {
        $period = (int) $this->input->get('period');
        $data['title']    = 'School IA — Statistiques des campagnes';
        $data['period']   = $period;
        $data['stats']    = $this->school_ia_bridge_model->message_stats($period);
        $data['messages'] = $this->school_ia_bridge_model->recent_messages(50);
        $this->load->view('school_ia_bridge/campaigns', $data);
    }

    /** Reporting : agrégats par période + rapports rédigés par l'IA. */
    public function reporting()
    {
        $period = $this->input->get('period') ?: 'month';
        $date   = (string) $this->input->get('date');
        [$from, $to, $label] = school_ia_period_range($period, $date);

        $data['title']   = 'School IA — Reporting';
        $data['period']  = $period;
        $data['date']    = $date;
        $data['label']   = $label;
        $data['from']    = $from;
        $data['to']      = $to;
        $data['agg']     = $this->school_ia_bridge_model->report_data($from, $to);
        $data['reports'] = $this->school_ia_bridge_model->list_reports();
        $data['report']  = $this->input->get('report') ? $this->school_ia_bridge_model->get_report((int) $this->input->get('report')) : null;
        $data['ai_ready'] = trim((string) get_option('sia_ai_api_key')) !== '';
        $data['model']   = $this->school_ia_bridge_model;
        $this->load->view('school_ia_bridge/reporting', $data);
    }

    /** Génère le rapport IA pour la période choisie (form Perfex → CSRF). */
    public function reporting_generate()
    {
        $period = $this->input->post('period') ?: 'month';
        $date   = (string) $this->input->post('date');
        [$from, $to, $label] = school_ia_period_range($period, $date);
        $agg = $this->school_ia_bridge_model->report_data($from, $to);

        // Données mises en forme pour l'IA.
        $lines = [];
        $lines[] = 'Période : ' . $label . ' (du ' . $from . ' au ' . $to . ')';
        $lines[] = 'Nouveaux leads : ' . $agg['leads_total'];
        $lines[] = 'Inscrits : ' . $agg['inscrits'] . ' (taux de conversion ' . $agg['conversion'] . '%)';
        $lines[] = 'Répartition par étape : ' . school_ia_kv($agg['by_stage']);
        $lines[] = 'Par source : ' . school_ia_kv($agg['by_source']);
        $lines[] = 'Formations les plus demandées : ' . (school_ia_kv($agg['top_formations']) ?: 'n/d');
        $lines[] = 'E-mails envoyés : ' . $agg['email_sent'] . ' (ouverts ' . $agg['email_opened'] . ', taux d\'ouverture ' . $agg['open_rate'] . '%)';
        $lines[] = 'SMS envoyés : ' . $agg['sms_sent'];
        $lines[] = 'Tâches/relances créées : ' . $agg['tasks'];

        $system = 'Tu es analyste CRM pour une école supérieure. À partir des données fournies, rédige un rapport clair, concis et ACTIONNABLE en français. '
            . 'Réponds en HTML simple (balises autorisées : h4, h5, p, ul, ol, li, strong, em) — sans <html>, <head>, <body>, ni styles. '
            . 'Structure : 1) Synthèse (2-3 phrases), 2) Points forts, 3) Points de vigilance, 4) Recommandations concrètes, 5) Prochaines actions. '
            . 'Sois factuel, cite les chiffres, et donne des conseils réalistes pour améliorer les admissions.';
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
        $type = $this->input->get('type') ?: null;
        $data['title']      = 'School IA — Journal d\'activité';
        $data['type']       = $type;
        $data['activities'] = $this->school_ia_bridge_model->global_activities($type);
        $this->load->view('school_ia_bridge/activity', $data);
    }

    /** Réglages : point d'entrée + secret + identifiants SMS LAfricaMobile. */
    public function settings()
    {
        $this->need('manage_settings');
        $data['title']       = 'School IA — Réglages';
        $data['secret']      = get_option('school_ia_bridge_secret');
        $data['endpoint']    = site_url('school_ia_bridge/api/receive');
        $data['sms_account'] = get_option('sia_sms_accountid');
        $data['sms_sender']  = get_option('sia_sms_sender');
        $data['sms_has_pwd'] = get_option('sia_sms_password') !== '';
        $data['ai_has_key']  = trim((string) get_option('sia_ai_api_key')) !== '';
        $data['ai_model']    = get_option('sia_ai_model') ?: 'claude-opus-4-8';
        $this->load->view('school_ia_bridge/settings', $data);
    }

    /** Enregistre les identifiants SMS (form Perfex → CSRF). */
    public function save_settings()
    {
        $this->need('manage_settings');
        // On ne met à jour que les champs réellement présents (formulaires
        // distincts : SMS d'un côté, Programmes de l'autre).
        if ($this->input->post('sms_accountid') !== null) {
            update_option('sia_sms_accountid', trim((string) $this->input->post('sms_accountid')));
        }
        if ($this->input->post('sms_sender') !== null) {
            update_option('sia_sms_sender', trim((string) $this->input->post('sms_sender')));
        }
        $pwd = (string) $this->input->post('sms_password');
        if ($pwd !== '') { // ne pas écraser si laissé vide
            update_option('sia_sms_password', $pwd);
        }
        if ($this->input->post('programs') !== null) {
            update_option('sia_programs', (string) $this->input->post('programs'));
        }
        if ($this->input->post('reminders_form') !== null) {
            update_option('sia_reminders_enabled', $this->input->post('reminders_enabled') ? '1' : '0');
        }
        if ($this->input->post('ai_form') !== null) {
            $aiKey = (string) $this->input->post('ai_api_key');
            if ($aiKey !== '') { // ne pas écraser si laissé vide
                update_option('sia_ai_api_key', $aiKey);
            }
            update_option('sia_ai_model', trim((string) $this->input->post('ai_model')) ?: 'claude-opus-4-8');
        }
        set_alert('success', 'Réglages enregistrés.');
        redirect(admin_url('school_ia_bridge/settings'));
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
        $ok = school_ia_send_tracked_email($lead, $subject, $message, $campaign, $paths);
        return [$ok, $names];
    }

    /** Personnalise un texte pour un lead ({prenom}, {formation}). */
    private function personalize(string $text, object $lead): string
    {
        $prenom = trim(explode('#', (string) $lead->name)[0]);
        return strtr($text, [
            '{prenom}'    => $prenom !== '' ? $prenom : 'bonjour',
            '{formation}' => $lead->formation ?: 'votre formation',
        ]);
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
        $data['model']     = $this->school_ia_bridge_model;
        $this->load->view('school_ia_bridge/bulk', $data);
    }

    /** Traite l'envoi groupé (form Perfex → CSRF). */
    public function bulk_send()
    {
        $this->need('send');
        $filters = [
            'stage'     => $this->input->post('stage'),
            'program'   => $this->input->post('program'),
            'min_score' => $this->input->post('min_score'),
        ];
        $subjectTpl = trim((string) $this->input->post('subject'));
        $bodyTpl    = trim((string) $this->input->post('message'));
        $attach     = (array) $this->input->post('attachments');

        $recipients = $this->school_ia_bridge_model->email_recipients($filters);
        $ok = 0;
        $fail = 0;
        foreach ($recipients as $lead) {
            $subject = $this->personalize($subjectTpl, $lead);
            $message = $this->personalize($bodyTpl, $lead);
            [$sent] = $this->deliver_email($lead, $subject, $message, $attach, 'bulk');
            if ($sent) {
                $ok++;
                $this->school_ia_bridge_model->add_activity((int) $lead->id, 'email', 'E-mail (envoi groupé) : ' . $subject, get_staff_user_id());
            } else {
                $fail++;
            }
        }

        set_alert($fail > 0 ? 'warning' : 'success',
            $ok . ' e-mail(s) envoyé(s)' . ($fail > 0 ? ', ' . $fail . ' échec(s).' : '.'));
        redirect(admin_url('school_ia_bridge/bulk'));
    }

    /** Traite l'envoi groupé de SMS (form Perfex → CSRF). */
    public function bulk_sms_send()
    {
        $this->need('send');
        $filters = [
            'stage'     => $this->input->post('stage'),
            'program'   => $this->input->post('program'),
            'min_score' => $this->input->post('min_score'),
        ];
        $bodyTpl = trim((string) $this->input->post('text'));

        $recipients = $this->school_ia_bridge_model->sms_recipients($filters);
        $ok = 0;
        $fail = 0;
        foreach ($recipients as $lead) {
            $text = $this->personalize($bodyTpl, $lead);
            [$sent] = $this->lam_send_sms((string) $lead->phone, $text, (int) $lead->id);
            school_ia_log_sms((int) $lead->id, (bool) $sent, 'bulk');
            if ($sent) {
                $ok++;
                $this->school_ia_bridge_model->add_activity((int) $lead->id, 'sms', 'SMS (envoi groupé) : ' . mb_substr($text, 0, 100), get_staff_user_id());
            } else {
                $fail++;
            }
        }

        set_alert($fail > 0 ? 'warning' : 'success',
            $ok . ' SMS envoyé(s)' . ($fail > 0 ? ', ' . $fail . ' échec(s).' : '.'));
        redirect(admin_url('school_ia_bridge/bulk'));
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
            'ret_id'    => 'sia_' . $leadId . '_' . time(),
            'priority'  => '2',
            'text'      => $text,
            'to'        => [['sia_' . $leadId => $num]],
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init('https://lamsms.lafricamobile.com/api');
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

        if ($resp === false) {
            return [false, 'Connexion échouée : ' . $cerr];
        }
        $ok = $code >= 200 && $code < 300;
        return [$ok, 'HTTP ' . $code . ' — ' . mb_substr((string) $resp, 0, 180)];
    }

    /** Régénère le secret partagé (à recopier ensuite dans le plugin). */
    public function regenerate_secret()
    {
        $this->need('manage_settings');
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
        ];
        $leads = $this->school_ia_bridge_model->search($filters, 100000);

        $rows = [['ID', 'Nom', 'E-mail', 'Téléphone', 'Formation', 'Score', 'Étape', 'Source', 'Reçu le']];
        foreach ($leads as $l) {
            $rows[] = [
                (int) $l->id,
                (string) $l->name,
                (string) $l->email,
                (string) $l->phone,
                (string) $l->formation,
                (string) $l->score,
                $this->school_ia_bridge_model->stageLabel($l->stage ?? 'nouveau'),
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
            } elseif (strpos($n, 'nom') !== false || strpos($n, 'name') !== false || strpos($n, 'prenom') !== false) {
                $map[$i] = 'name';
            }
        }

        $defProgram = trim((string) $this->input->post('default_program'));
        $defStage = (string) $this->input->post('default_stage') ?: 'nouveau';

        $imported = 0;
        $skipped = 0;
        foreach ($rows as $r) {
            $rec = ['name' => '', 'email' => '', 'phone' => '', 'formation' => '', 'score' => '', 'stage' => ''];
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
        ]);
        $this->school_ia_bridge_model->add_activity($id, 'note', 'Lead créé manuellement.', get_staff_user_id());
        set_alert('success', 'Lead ajouté.');
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
        $data['emailTpls']  = $this->school_ia_bridge_model->templates('email');
        $data['smsTpls']    = $this->school_ia_bridge_model->templates('sms');
        $data['documents']  = $this->school_ia_bridge_model->documents();
        $data['sequences']  = $this->school_ia_bridge_model->active_sequences();
        $data['enrollments'] = $this->school_ia_bridge_model->enrollments_for_lead((int) $lead->id);
        $data['chatMessages'] = $this->school_ia_bridge_model->chat_messages((int) $lead->id);
        $data['model']      = $this->school_ia_bridge_model;
        $this->load->view('school_ia_bridge/lead', $data);
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

    /** Page Tâches : toutes les relances à faire, échéances en tête. */
    public function tasks()
    {
        $data['title'] = 'School IA — Tâches';
        $data['tasks'] = $this->school_ia_bridge_model->pending_tasks();
        $this->load->view('school_ia_bridge/tasks', $data);
    }

    /** Ajoute une tâche à un lead (form Perfex → CSRF). */
    public function task_add($leadId = 0)
    {
        $this->need('manage_leads');
        $leadId = (int) $leadId;
        $title = trim((string) $this->input->post('title'));
        $due = trim((string) $this->input->post('due_at'));
        // <input type="datetime-local"> renvoie "Y-m-d\TH:i" → format MySQL.
        $dueAt = $due !== '' ? date('Y-m-d H:i:s', strtotime($due)) : null;
        if ($this->school_ia_bridge_model->get_lead($leadId) && $title !== '') {
            $this->school_ia_bridge_model->add_task($leadId, $title, $dueAt, get_staff_user_id());
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
        redirect($this->input->get('back') === 'tasks'
            ? admin_url('school_ia_bridge/tasks')
            : admin_url('school_ia_bridge/lead/' . ($task ? (int) $task->lead_id : 0)));
    }

    /** Supprime une tâche (lien GET). */
    public function task_delete($taskId = 0)
    {
        $this->need('manage_leads');
        $task = $this->school_ia_bridge_model->get_task((int) $taskId);
        if ($task) {
            $this->school_ia_bridge_model->delete_task((int) $taskId);
        }
        redirect(admin_url('school_ia_bridge/lead/' . ($task ? (int) $task->lead_id : 0)));
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
            $this->school_ia_bridge_model->set_stage($id, $stage);
            $this->school_ia_bridge_model->add_activity(
                $id,
                'stage_change',
                'Étape → ' . $this->school_ia_bridge_model->stageLabel($stage),
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
        if ($this->school_ia_bridge_model->get_lead($id)) {
            $this->school_ia_bridge_model->set_owner($id, $staffId);
            $this->school_ia_bridge_model->add_activity($id, 'assignment',
                'Responsable : ' . ($staffId ? get_staff_full_name($staffId) : '—'), get_staff_user_id());
            set_alert('success', 'Responsable mis à jour.');
        }
        redirect(admin_url('school_ia_bridge/lead/' . $id));
    }
}

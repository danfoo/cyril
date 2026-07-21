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
    }

    /** Tableau de bord : indicateurs + entonnoir. */
    public function dashboard()
    {
        $data['title']    = 'School IA — Tableau de bord';
        $data['stats']    = $this->school_ia_bridge_model->stats();
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

    /** Réglages : point d'entrée + secret + identifiants SMS LAfricaMobile. */
    public function settings()
    {
        $data['title']       = 'School IA — Réglages';
        $data['secret']      = get_option('school_ia_bridge_secret');
        $data['endpoint']    = site_url('school_ia_bridge/api/receive');
        $data['sms_account'] = get_option('sia_sms_accountid');
        $data['sms_sender']  = get_option('sia_sms_sender');
        $data['sms_has_pwd'] = get_option('sia_sms_password') !== '';
        $this->load->view('school_ia_bridge/settings', $data);
    }

    /** Enregistre les identifiants SMS (form Perfex → CSRF). */
    public function save_settings()
    {
        update_option('sia_sms_accountid', trim((string) $this->input->post('sms_accountid')));
        update_option('sia_sms_sender', trim((string) $this->input->post('sms_sender')));
        $pwd = (string) $this->input->post('sms_password');
        if ($pwd !== '') { // ne pas écraser si laissé vide
            update_option('sia_sms_password', $pwd);
        }
        set_alert('success', 'Réglages SMS enregistrés.');
        redirect(admin_url('school_ia_bridge/settings'));
    }

    /** Envoie un e-mail au lead (moteur d'e-mail de Perfex). */
    public function send_email($id = 0)
    {
        $id = (int) $id;
        $lead = $this->school_ia_bridge_model->get_lead($id);
        $subject = trim((string) $this->input->post('subject'));
        $message = trim((string) $this->input->post('message'));

        if (!$lead || !$lead->email) {
            set_alert('warning', 'Ce lead n\'a pas d\'adresse e-mail.');
            redirect(admin_url('school_ia_bridge/lead/' . $id));
        }

        $this->load->library('email');
        $this->email->from(get_option('smtp_email') ?: get_option('companyname'), get_option('companyname'));
        $this->email->to($lead->email);
        $this->email->subject($subject);
        $this->email->message(nl2br($message));
        $this->email->set_mailtype('html');

        if ($this->email->send(false)) {
            $this->school_ia_bridge_model->add_activity($id, 'email', 'E-mail envoyé : ' . $subject, get_staff_user_id());
            set_alert('success', 'E-mail envoyé.');
        } else {
            set_alert('danger', 'Échec de l\'envoi. Vérifiez la configuration SMTP de Perfex (Réglages → E-mail).');
        }
        redirect(admin_url('school_ia_bridge/lead/' . $id));
    }

    /** Envoie un SMS au lead via LAfricaMobile. */
    public function send_sms($id = 0)
    {
        $id = (int) $id;
        $lead = $this->school_ia_bridge_model->get_lead($id);
        $text = trim((string) $this->input->post('text'));

        if (!$lead || !$lead->phone) {
            set_alert('warning', 'Ce lead n\'a pas de numéro de téléphone.');
            redirect(admin_url('school_ia_bridge/lead/' . $id));
        }

        [$ok, $info] = $this->lam_send_sms((string) $lead->phone, $text, $id);
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
        $data['model']      = $this->school_ia_bridge_model;
        $this->load->view('school_ia_bridge/lead', $data);
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
        $task = $this->school_ia_bridge_model->get_task((int) $taskId);
        if ($task) {
            $this->school_ia_bridge_model->delete_task((int) $taskId);
        }
        redirect(admin_url('school_ia_bridge/lead/' . ($task ? (int) $task->lead_id : 0)));
    }

    /** Change l'étape du pipeline (lien GET → pas de blocage CSRF). */
    public function move($id = 0)
    {
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

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
        $data['title'] = 'School IA — Tableau de bord';
        $data['stats'] = $this->school_ia_bridge_model->stats();
        $data['model'] = $this->school_ia_bridge_model;
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

    /** Réglages : URL du point d'entrée + secret partagé (pour le plugin). */
    public function settings()
    {
        $data['title']    = 'School IA — Réglages';
        $data['secret']   = get_option('school_ia_bridge_secret');
        $data['endpoint'] = site_url('school_ia_bridge/api/receive');
        $this->load->view('school_ia_bridge/settings', $data);
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
        $data['staff']      = $this->db->where('active', 1)->get(db_prefix() . 'staff')->result();
        $data['model']      = $this->school_ia_bridge_model;
        $this->load->view('school_ia_bridge/lead', $data);
    }

    /** Change l'étape du pipeline (lien GET → pas de blocage CSRF). */
    public function move($id = 0)
    {
        $id = (int) $id;
        $stage = $this->input->get('stage');
        $lead = $this->school_ia_bridge_model->get_lead($id);
        if ($lead && $stage) {
            $this->school_ia_bridge_model->set_stage($id, $stage);
            $this->school_ia_bridge_model->add_activity(
                $id,
                'stage_change',
                'Étape → ' . $this->school_ia_bridge_model->stageLabel($stage),
                get_staff_user_id()
            );
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

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

    /** Boîte de réception : tous les leads reçus. */
    public function index()
    {
        $data['title']    = 'School IA — Leads';
        $data['leads']    = $this->school_ia_bridge_model->get_leads();
        $data['secret']   = get_option('school_ia_bridge_secret');
        $data['endpoint'] = site_url('school_ia_bridge/api/receive');
        $data['model']    = $this->school_ia_bridge_model;
        $this->load->view('school_ia_bridge/leads', $data);
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

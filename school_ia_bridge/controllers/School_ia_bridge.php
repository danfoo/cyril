<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Page admin « School IA — Leads » : affiche les leads reçus depuis le plugin.
 * URL : {perfex}/admin/school_ia_bridge
 */
class School_ia_bridge extends AdminController
{
    public function index()
    {
        $this->load->model('school_ia_bridge/school_ia_bridge_model');
        $this->school_ia_bridge_model->ensure_schema();

        $data['title']    = 'School IA — Leads';
        $data['leads']    = $this->school_ia_bridge_model->get_leads();
        $data['secret']   = get_option('school_ia_bridge_secret');
        $data['endpoint'] = site_url('school_ia_bridge/api/receive');

        $this->load->view('school_ia_bridge/leads', $data);
    }

    /** Convertit les leads reçus non encore convertis en leads natifs Perfex. */
    public function convert()
    {
        $this->load->model('school_ia_bridge/school_ia_bridge_model');

        $ok = 0;
        $fail = 0;
        foreach ($this->school_ia_bridge_model->unconverted() as $row) {
            $this->school_ia_bridge_model->convert_to_perfex($row) > 0 ? $ok++ : $fail++;
        }

        set_alert($fail > 0 ? 'warning' : 'success',
            $ok . ' lead(s) converti(s) en leads Perfex' . ($fail > 0 ? ', ' . $fail . ' échec(s).' : '.'));
        redirect(admin_url('school_ia_bridge'));
    }
}

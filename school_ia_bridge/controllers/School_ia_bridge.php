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

        $data['title']    = 'School IA — Leads';
        $data['leads']    = $this->school_ia_bridge_model->get_leads();
        $data['secret']   = get_option('school_ia_bridge_secret');
        $data['endpoint'] = site_url('school_ia_bridge/api/receive');

        $this->load->view('school_ia_bridge/leads', $data);
    }
}

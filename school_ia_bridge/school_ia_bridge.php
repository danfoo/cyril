<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: School IA Bridge
Description: Reçoit les leads envoyés par le plugin WordPress « School IA » et les affiche dans Perfex. Le traitement (conversion en lead Perfex, relances…) se fait ensuite.
Version: 1.0.0
Requires at least: 2.3.*
Author: Maestro Dan
Author URI: https://maestrodan.art
*/

define('SCHOOL_IA_BRIDGE_MODULE', 'school_ia_bridge');

/**
 * À l'activation : crée la table de stockage et un secret partagé par défaut.
 */
register_activation_hook(SCHOOL_IA_BRIDGE_MODULE, 'school_ia_bridge_activate');
function school_ia_bridge_activate()
{
    require_once __DIR__ . '/install.php';
}

/**
 * Rappels automatiques : à chaque passage du cron Perfex, on envoie un e-mail
 * au responsable pour chaque tâche due dont le rappel n'a pas encore été émis.
 */
hooks()->add_action('after_cron_run', 'school_ia_bridge_reminders_cron');
function school_ia_bridge_reminders_cron()
{
    if (get_option('sia_reminders_enabled') === '0') {
        return; // désactivé (activé par défaut)
    }
    $CI = &get_instance();
    $CI->load->model('school_ia_bridge/school_ia_bridge_model');
    $tasks = $CI->school_ia_bridge_model->due_reminders();
    if (!$tasks) {
        return;
    }

    $CI->load->library('email');
    foreach ($tasks as $t) {
        $staffId = (int) ($t->lead_owner ?: $t->staff_id);
        $CI->school_ia_bridge_model->mark_reminded((int) $t->id);
        if (!$staffId) {
            continue;
        }
        $staff = $CI->db->where('staffid', $staffId)->get(db_prefix() . 'staff')->row();
        if (!$staff || empty($staff->email)) {
            continue;
        }

        $link = admin_url('school_ia_bridge/lead/' . (int) $t->lead_id);
        $body = '<p>Bonjour ' . htmlspecialchars($staff->firstname) . ',</p>'
            . '<p>Rappel d\'une tâche à effectuer :</p>'
            . '<ul>'
            . '<li><strong>' . htmlspecialchars($t->title) . '</strong></li>'
            . '<li>Lead : ' . htmlspecialchars((string) ($t->lead_name ?: ('#' . $t->lead_id))) . '</li>'
            . '<li>Échéance : ' . htmlspecialchars((string) $t->due_at) . '</li>'
            . '</ul>'
            . '<p><a href="' . $link . '">Ouvrir la fiche du lead</a></p>';

        $CI->email->clear(true);
        $CI->email->from(get_option('smtp_email') ?: get_option('companyname'), get_option('companyname'));
        $CI->email->to($staff->email);
        $CI->email->subject('Rappel de tâche — ' . $t->title);
        $CI->email->message($body);
        $CI->email->set_mailtype('html');
        $CI->email->send(false);
    }
}

/**
 * Entrée de menu dans la barre latérale de l'admin Perfex.
 */
hooks()->add_action('admin_init', 'school_ia_bridge_admin_menu');
function school_ia_bridge_admin_menu()
{
    $CI = &get_instance();
    $CI->app_menu->add_sidebar_menu_item('school_ia_bridge', [
        'name'     => 'School IA CRM',
        'icon'     => 'fa fa-graduation-cap',
        'position' => 30,
    ]);
    $CI->app_menu->add_sidebar_children_item('school_ia_bridge', [
        'slug'     => 'school_ia_bridge_dashboard',
        'name'     => 'Tableau de bord',
        'href'     => admin_url('school_ia_bridge/dashboard'),
        'position' => 1,
    ]);
    $CI->app_menu->add_sidebar_children_item('school_ia_bridge', [
        'slug'     => 'school_ia_bridge_pipeline',
        'name'     => 'Pipeline',
        'href'     => admin_url('school_ia_bridge/pipeline'),
        'position' => 2,
    ]);
    $CI->app_menu->add_sidebar_children_item('school_ia_bridge', [
        'slug'     => 'school_ia_bridge_bulk',
        'name'     => 'Envoi groupé',
        'href'     => admin_url('school_ia_bridge/bulk'),
        'position' => 3,
    ]);
    $CI->app_menu->add_sidebar_children_item('school_ia_bridge', [
        'slug'     => 'school_ia_bridge_tasks',
        'name'     => 'Tâches',
        'href'     => admin_url('school_ia_bridge/tasks'),
        'position' => 4,
    ]);
    $CI->app_menu->add_sidebar_children_item('school_ia_bridge', [
        'slug'     => 'school_ia_bridge_inbox',
        'name'     => 'Boîte de réception',
        'href'     => admin_url('school_ia_bridge'),
        'position' => 5,
    ]);
    $CI->app_menu->add_sidebar_children_item('school_ia_bridge', [
        'slug'     => 'school_ia_bridge_templates',
        'name'     => 'Modèles',
        'href'     => admin_url('school_ia_bridge/templates'),
        'position' => 6,
    ]);
    $CI->app_menu->add_sidebar_children_item('school_ia_bridge', [
        'slug'     => 'school_ia_bridge_documents',
        'name'     => 'Documents',
        'href'     => admin_url('school_ia_bridge/documents'),
        'position' => 7,
    ]);
    $CI->app_menu->add_sidebar_children_item('school_ia_bridge', [
        'slug'     => 'school_ia_bridge_settings',
        'name'     => 'Réglages',
        'href'     => admin_url('school_ia_bridge/settings'),
        'position' => 8,
    ]);
}

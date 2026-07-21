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
        'slug'     => 'school_ia_bridge_tasks',
        'name'     => 'Tâches',
        'href'     => admin_url('school_ia_bridge/tasks'),
        'position' => 3,
    ]);
    $CI->app_menu->add_sidebar_children_item('school_ia_bridge', [
        'slug'     => 'school_ia_bridge_inbox',
        'name'     => 'Boîte de réception',
        'href'     => admin_url('school_ia_bridge'),
        'position' => 4,
    ]);
    $CI->app_menu->add_sidebar_children_item('school_ia_bridge', [
        'slug'     => 'school_ia_bridge_templates',
        'name'     => 'Modèles',
        'href'     => admin_url('school_ia_bridge/templates'),
        'position' => 5,
    ]);
    $CI->app_menu->add_sidebar_children_item('school_ia_bridge', [
        'slug'     => 'school_ia_bridge_documents',
        'name'     => 'Documents',
        'href'     => admin_url('school_ia_bridge/documents'),
        'position' => 6,
    ]);
    $CI->app_menu->add_sidebar_children_item('school_ia_bridge', [
        'slug'     => 'school_ia_bridge_settings',
        'name'     => 'Réglages',
        'href'     => admin_url('school_ia_bridge/settings'),
        'position' => 7,
    ]);
}

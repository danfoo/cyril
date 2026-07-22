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

// ---- Utilitaires d'envoi partagés (utilisés par le cron des séquences) ----

function school_ia_personalize(string $text, object $lead): string
{
    $prenom = trim(explode('#', (string) $lead->name)[0]);
    return strtr($text, [
        '{prenom}'    => $prenom !== '' ? $prenom : 'bonjour',
        '{formation}' => $lead->formation ?: 'votre formation',
    ]);
}

function school_ia_send_email_raw(string $toEmail, string $subject, string $htmlMessage): bool
{
    $CI = &get_instance();
    $CI->load->library('email');
    $CI->email->clear(true);
    $CI->email->from(get_option('smtp_email') ?: get_option('companyname'), get_option('companyname'));
    $CI->email->to($toEmail);
    $CI->email->subject($subject);
    $CI->email->message($htmlMessage);
    $CI->email->set_mailtype('html');
    return $CI->email->send(false);
}

function school_ia_send_sms_raw(string $phone, string $text, int $leadId): bool
{
    $accountid = (string) get_option('sia_sms_accountid');
    $password  = (string) get_option('sia_sms_password');
    $sender    = (string) (get_option('sia_sms_sender') ?: 'SchoolIA');
    $num = preg_replace('/\D+/', '', $phone);
    if ($accountid === '' || $password === '' || $num === '') {
        return false;
    }
    $body = json_encode([
        'accountid' => $accountid, 'password' => $password, 'sender' => $sender,
        'ret_id' => 'sia_seq_' . $leadId . '_' . time(), 'priority' => '2',
        'text' => $text, 'to' => [['sia_' . $leadId => $num]],
    ], JSON_UNESCAPED_UNICODE);
    $ch = curl_init('https://lamsms.lafricamobile.com/api');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_TIMEOUT => 30,
    ]);
    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $resp !== false && $code >= 200 && $code < 300;
}

/**
 * Cron des séquences de relance : envoie les étapes dues et fait avancer
 * chaque inscription. S'arrête si le lead est inscrit ou perdu.
 */
hooks()->add_action('after_cron_run', 'school_ia_bridge_sequences_cron');
function school_ia_bridge_sequences_cron()
{
    $CI = &get_instance();
    $CI->load->model('school_ia_bridge/school_ia_bridge_model');
    $m = $CI->school_ia_bridge_model;

    foreach ($m->due_enrollments() as $en) {
        $lead = $m->get_lead((int) $en->lead_id);
        if (!$lead) {
            $m->stop_enrollment((int) $en->id);
            continue;
        }
        if (in_array($lead->stage, ['inscrit', 'perdu'], true)) {
            $m->stop_enrollment((int) $en->id);
            continue;
        }

        $step = $m->step_by_order((int) $en->sequence_id, (int) $en->next_step_order);
        if (!$step) {
            $m->complete_enrollment((int) $en->id);
            continue;
        }

        $tpl = $step->template_id ? $m->get_template((int) $step->template_id) : null;
        if ($tpl) {
            if ($step->channel === 'email' && $lead->email) {
                $subject = school_ia_personalize((string) $tpl->subject, $lead);
                $bodyTxt = school_ia_personalize((string) $tpl->body, $lead);
                if (school_ia_send_email_raw($lead->email, $subject, nl2br($bodyTxt))) {
                    $m->add_activity((int) $lead->id, 'email', 'Séquence : ' . $subject, null);
                }
            } elseif ($step->channel === 'sms' && $lead->phone) {
                $text = school_ia_personalize((string) $tpl->body, $lead);
                if (school_ia_send_sms_raw((string) $lead->phone, $text, (int) $lead->id)) {
                    $m->add_activity((int) $lead->id, 'sms', 'Séquence : ' . mb_substr($text, 0, 100), null);
                }
            }
        }

        $next = $m->step_by_order((int) $en->sequence_id, (int) $en->next_step_order + 1);
        if ($next) {
            $runAt = date('Y-m-d H:i:s', time() + $next->delay_days * 86400 + $next->delay_hours * 3600);
            $m->advance_enrollment((int) $en->id, (int) $next->step_order, $runAt);
        } else {
            $m->complete_enrollment((int) $en->id);
        }
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
        'slug'     => 'school_ia_bridge_sequences',
        'name'     => 'Séquences',
        'href'     => admin_url('school_ia_bridge/sequences'),
        'position' => 4,
    ]);
    $CI->app_menu->add_sidebar_children_item('school_ia_bridge', [
        'slug'     => 'school_ia_bridge_tasks',
        'name'     => 'Tâches',
        'href'     => admin_url('school_ia_bridge/tasks'),
        'position' => 5,
    ]);
    $CI->app_menu->add_sidebar_children_item('school_ia_bridge', [
        'slug'     => 'school_ia_bridge_inbox',
        'name'     => 'Boîte de réception',
        'href'     => admin_url('school_ia_bridge'),
        'position' => 6,
    ]);
    $CI->app_menu->add_sidebar_children_item('school_ia_bridge', [
        'slug'     => 'school_ia_bridge_templates',
        'name'     => 'Modèles',
        'href'     => admin_url('school_ia_bridge/templates'),
        'position' => 7,
    ]);
    $CI->app_menu->add_sidebar_children_item('school_ia_bridge', [
        'slug'     => 'school_ia_bridge_documents',
        'name'     => 'Documents',
        'href'     => admin_url('school_ia_bridge/documents'),
        'position' => 8,
    ]);
    $CI->app_menu->add_sidebar_children_item('school_ia_bridge', [
        'slug'     => 'school_ia_bridge_settings',
        'name'     => 'Réglages',
        'href'     => admin_url('school_ia_bridge/settings'),
        'position' => 9,
    ]);
}

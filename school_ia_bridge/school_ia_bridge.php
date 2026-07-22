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
 * Aide contextuelle : bouton « ? » + pop-up (modal Bootstrap) expliquant
 * la fonctionnalité et sa configuration. Utilisé dans les en-têtes de pages.
 */
function sia_help(string $key): string
{
    $help = [
        'dashboard' => ['Tableau de bord',
            '<p>Vue d\'ensemble de votre activité d\'admission.</p>
             <ul>
               <li><strong>Leads au total</strong> : tous les leads reçus (plugin, saisie, import).</li>
               <li><strong>Leads chauds</strong> : score ≥ 60 (prospects les plus engagés).</li>
               <li><strong>Inscrits</strong> et <strong>taux de conversion</strong> : leads en étape « Inscrit » / total.</li>
               <li><strong>Entonnoir</strong> : répartition des leads par étape du pipeline.</li>
               <li><strong>Relances à faire</strong> : vos prochaines tâches dues.</li>
             </ul>
             <p><em>Configuration :</em> rien à régler, tout se met à jour automatiquement.</p>'],
        'pipeline' => ['Pipeline',
            '<p>Suivez la progression des leads par étape (Nouveau → … → Inscrit / Perdu).</p>
             <ul>
               <li><strong>Glisser-déposer</strong> : attrapez une carte et déposez-la dans une autre colonne pour changer son étape (enregistré automatiquement).</li>
               <li>Le menu <strong>« Déplacer »</strong> d\'une carte fait la même chose au clic.</li>
               <li>Cliquez le nom d\'un lead pour ouvrir sa <strong>fiche</strong>.</li>
             </ul>
             <p><em>Astuce :</em> passer un lead en « Inscrit » ou « Perdu » arrête ses séquences de relance automatiques.</p>'],
        'lead' => ['Fiche lead',
            '<p>Le centre de contrôle d\'un lead.</p>
             <ul>
               <li><strong>Étape</strong> : faites avancer le lead dans le pipeline.</li>
               <li><strong>Contacter</strong> : envoyez un e-mail ou un SMS (onglets), avec modèles et pièces jointes.</li>
               <li><strong>Séquences</strong> : inscrivez le lead à une relance automatique.</li>
               <li><strong>Tâches</strong> : planifiez des relances datées (avec heure).</li>
               <li><strong>Responsable</strong> : assignez un conseiller.</li>
               <li><strong>Historique</strong> : tout ce qui a été fait sur le lead.</li>
             </ul>'],
        'tasks' => ['Tâches & relances',
            '<p>Vos relances à faire, les plus urgentes en tête.</p>
             <ul>
               <li>Créez une tâche depuis une <strong>fiche lead</strong> (titre + échéance date/heure).</li>
               <li>Les échéances <strong>dépassées</strong> apparaissent en rouge.</li>
               <li>Cochez la case pour marquer une tâche <strong>faite</strong>.</li>
             </ul>
             <p><em>Rappels automatiques :</em> activez-les dans <strong>Réglages</strong>. Un e-mail est alors envoyé au responsable à l\'échéance (nécessite le cron Perfex).</p>'],
        'sequences' => ['Séquences de relance',
            '<p>Une suite d\'étapes envoyées automatiquement dans le temps.</p>
             <ol>
               <li>Créez une <strong>séquence</strong> (ex. « Relance admission »).</li>
               <li>Ajoutez des <strong>étapes</strong> : canal (e-mail/SMS), <strong>modèle</strong>, et <strong>délai</strong> (jours + heures) d\'attente avant l\'envoi.</li>
               <li>Inscrivez un lead à la séquence depuis sa <strong>fiche</strong>.</li>
             </ol>
             <p>Le <strong>cron Perfex</strong> envoie les étapes dues et fait avancer chaque inscription. La séquence s\'arrête si le lead devient « Inscrit » ou « Perdu ».</p>
             <p><em>Prérequis :</em> configurer le cron (Setup → Settings → Cron Job).</p>'],
        'templates' => ['Modèles e-mail / SMS',
            '<p>Des messages réutilisables pour gagner du temps.</p>
             <ul>
               <li>Choisissez le <strong>type</strong> (E-mail = objet + corps ; SMS = corps seul).</li>
               <li>Utilisez les variables <code>{prenom}</code> et <code>{formation}</code> : elles sont remplacées par les infos du lead à l\'envoi.</li>
             </ul>
             <p>Les modèles apparaissent ensuite dans les menus déroulants de la fiche lead, de l\'envoi groupé et des séquences.</p>'],
        'documents' => ['Documents',
            '<p>Votre bibliothèque de documents classés par <strong>programme</strong>.</p>
             <ol>
               <li>Définissez d\'abord vos <strong>programmes</strong> dans <strong>Réglages</strong>.</li>
               <li>Téléversez un fichier (PDF, Word, Excel, image… max 20 Mo) en choisissant son programme.</li>
               <li>Depuis la fiche lead (onglet E-mail) ou l\'envoi groupé, cochez un document pour le <strong>joindre</strong> à l\'e-mail.</li>
             </ol>'],
        'bulk' => ['Envoi groupé',
            '<p>Contactez plusieurs leads d\'un coup (e-mail ou SMS).</p>
             <ol>
               <li><strong>Cibler</strong> : filtrez par étape, programme et score minimum.</li>
               <li><strong>Rédiger</strong> : choisissez un modèle ou écrivez ; <code>{prenom}</code>/<code>{formation}</code> sont personnalisés pour chaque destinataire.</li>
               <li>(E-mail) cochez des <strong>pièces jointes</strong> si besoin, puis envoyez.</li>
             </ol>
             <p>Seuls les leads ayant l\'e-mail (ou le téléphone pour les SMS) sont contactés.</p>'],
        'import' => ['Import CSV / Excel',
            '<p>Importez une liste de leads existante.</p>
             <ul>
               <li>La <strong>1ʳᵉ ligne</strong> du fichier doit contenir les en-têtes ; les colonnes sont reconnues automatiquement (nom, e-mail, téléphone, formation, score, étape).</li>
               <li>Téléchargez le <strong>modèle CSV</strong> pour partir sur de bonnes bases.</li>
               <li>Un <strong>programme</strong> et une <strong>étape</strong> par défaut s\'appliquent aux lignes qui ne les précisent pas.</li>
               <li>Les e-mails déjà présents sont <strong>ignorés</strong> (pas de doublon).</li>
             </ul>'],
        'inbox' => ['Boîte de réception',
            '<p>La liste de tous les leads reçus.</p>
             <ul>
               <li><strong>Rechercher / filtrer</strong> par nom, e-mail, étape ou score.</li>
               <li><strong>Ajouter un lead</strong> manuellement, ou <strong>Importer</strong> depuis un fichier CSV/Excel.</li>
               <li><strong>Exporter</strong> la liste filtrée en CSV ou Excel.</li>
               <li>Cliquez un lead pour ouvrir sa fiche.</li>
             </ul>'],
        'new_lead' => ['Ajouter un lead',
            '<p>Saisie manuelle d\'un lead (salon, appel, recommandation…).</p>
             <p>Renseignez au moins un <strong>nom, e-mail ou téléphone</strong>. Le champ formation propose vos programmes. Le lead ouvre ensuite sa fiche, prêt pour tâches, e-mail/SMS et séquences.</p>'],
        'settings' => ['Réglages',
            '<p>La configuration du module.</p>
             <ul>
               <li><strong>Connexion du plugin</strong> : URL du point d\'entrée + secret à recopier dans le plugin School IA (WordPress → Réglages → CRM) pour recevoir les leads.</li>
               <li><strong>Rappels automatiques</strong> : e-mail au responsable des tâches dues (nécessite le cron).</li>
               <li><strong>Programmes</strong> : la liste servant à classer les documents.</li>
               <li><strong>SMS (LAfricaMobile)</strong> : Account ID, mot de passe API et expéditeur pour l\'envoi de SMS.</li>
             </ul>'],
    ];

    if (!isset($help[$key])) {
        return '';
    }
    $title = $help[$key][0];
    $body = $help[$key][1];
    $id = 'siaHelp_' . preg_replace('/[^a-z0-9_]/', '', $key);

    return '<button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#' . $id . '" title="Aide"><i class="fa fa-question-circle"></i> Aide</button>'
        . '<div class="modal fade" id="' . $id . '" tabindex="-1" role="dialog"><div class="modal-dialog"><div class="modal-content">'
        . '<div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button>'
        . '<h4 class="modal-title"><i class="fa fa-question-circle"></i> ' . $title . '</h4></div>'
        . '<div class="modal-body">' . $body . '</div>'
        . '<div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Fermer</button></div>'
        . '</div></div></div>';
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

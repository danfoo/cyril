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
               <li><strong>Par conseiller</strong> : leads assignés, inscrits et taux de conversion de chaque agent.</li>
               <li><strong>Par source</strong> : d\'où viennent les leads (site plugin, saisie manuelle, import).</li>
             </ul>
             <p><em>Période :</em> les boutons en haut (Tout / 7 / 30 / 90 jours) filtrent tous les indicateurs.</p>'],
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
        'reporting' => ['Reporting IA',
            '<p>Génère un <strong>rapport d\'activité rédigé par l\'IA</strong> (Claude) pour la période choisie.</p>
             <ol>
               <li>Choisissez le <strong>type</strong> (journalier, hebdomadaire, mensuel, annuel) et une date de référence.</li>
               <li>Vérifiez l\'aperçu des chiffres, puis cliquez <strong>« Générer avec l\'IA »</strong>.</li>
               <li>L\'IA analyse les données (leads, conversions, sources, campagnes…) et produit une synthèse avec points forts, points de vigilance et <strong>recommandations concrètes</strong>.</li>
             </ol>
             <p>Chaque rapport est <strong>archivé</strong> et consultable à tout moment.</p>
             <p><em>Prérequis :</em> une clé API Anthropic dans <strong>Réglages → Rapports IA</strong>.</p>'],
        'campaigns' => ['Statistiques des campagnes',
            '<p>Mesurez l\'efficacité de vos envois.</p>
             <ul>
               <li><strong>Taux d\'ouverture</strong> (e-mail) : un pixel invisible détecte l\'ouverture du message par le destinataire.</li>
               <li><strong>Taux de clic</strong> (e-mail) : les liens <em>cliquables (HTML)</em> de vos messages sont tracés.</li>
               <li><strong>SMS</strong> : nombre d\'envois réussis / échoués.</li>
             </ul>
             <p><em>Note :</em> certains logiciels de messagerie bloquent les images ; le taux d\'ouverture réel peut être légèrement supérieur à l\'affiché. La période (haut de page) filtre les statistiques.</p>'],
        'activity' => ['Journal d\'activité',
            '<p>Le flux central de <strong>tout ce qui se passe</strong> sur l\'ensemble des leads : notes, changements d\'étape, tâches, e-mails, SMS, assignations.</p>
             <ul>
               <li>Filtrez par <strong>type</strong> d\'activité avec les boutons du haut.</li>
               <li>Chaque ligne indique <strong>qui</strong> a fait l\'action et <strong>quand</strong>, avec un lien vers le lead.</li>
             </ul>
             <p><em>Droits :</em> l\'accès aux fonctions du CRM se règle par rôle dans <strong>Setup → Rôles → School IA CRM</strong>.</p>'],
        'inbox' => ['Contacts',
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

/** Formate un tableau associatif en "clé: valeur, clé: valeur". */
function school_ia_kv(array $arr): string
{
    $parts = [];
    foreach ($arr as $k => $v) {
        $parts[] = $k . ': ' . $v;
    }
    return implode(', ', $parts);
}

/**
 * Calcule l'intervalle de dates d'une période. Renvoie [from, to, label].
 */
function school_ia_period_range(string $period, string $date = ''): array
{
    $ref = $date !== '' ? strtotime($date) : time();
    switch ($period) {
        case 'day':
            $from = date('Y-m-d 00:00:00', $ref);
            $to   = date('Y-m-d 23:59:59', $ref);
            $label = 'Rapport journalier — ' . date('d/m/Y', $ref);
            break;
        case 'week':
            $mon = strtotime('monday this week', $ref);
            $sun = strtotime('sunday this week', $ref);
            $from = date('Y-m-d 00:00:00', $mon);
            $to   = date('Y-m-d 23:59:59', $sun);
            $label = 'Rapport hebdomadaire — semaine du ' . date('d/m/Y', $mon);
            break;
        case 'year':
            $from = date('Y-01-01 00:00:00', $ref);
            $to   = date('Y-12-31 23:59:59', $ref);
            $label = 'Rapport annuel — ' . date('Y', $ref);
            break;
        case 'month':
        default:
            $from = date('Y-m-01 00:00:00', $ref);
            $to   = date('Y-m-t 23:59:59', $ref);
            $label = 'Rapport mensuel — ' . date('m/Y', $ref);
            break;
    }
    return [$from, $to, $label];
}

/**
 * Appelle l'API Claude (Anthropic) pour rédiger le rapport. Renvoie [ok, texte].
 * Raw HTTPS via cURL (Perfex = PHP sans le SDK Anthropic).
 */
function school_ia_ai_generate(string $system, string $prompt): array
{
    $key = trim((string) get_option('sia_ai_api_key'));
    if ($key === '') {
        return [false, 'Clé API IA non configurée (Réglages → Rapports IA).'];
    }
    $model = trim((string) get_option('sia_ai_model')) ?: 'claude-opus-4-8';

    $payload = json_encode([
        'model'      => $model,
        'max_tokens' => 3000,
        'system'     => $system,
        'messages'   => [['role' => 'user', 'content' => $prompt]],
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_HTTPHEADER     => [
            'content-type: application/json',
            'x-api-key: ' . $key,
            'anthropic-version: 2023-06-01',
        ],
    ]);
    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        return [false, 'Connexion à l\'IA échouée : ' . $cerr];
    }
    $data = json_decode((string) $resp, true);
    if ($code >= 400) {
        return [false, 'Erreur API (' . $code . ') : ' . ($data['error']['message'] ?? mb_substr((string) $resp, 0, 200))];
    }
    $text = '';
    foreach (($data['content'] ?? []) as $block) {
        if (($block['type'] ?? '') === 'text') {
            $text .= $block['text'];
        }
    }
    if (trim($text) === '') {
        return [false, 'Réponse de l\'IA vide.'];
    }
    return [true, $text];
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

/**
 * Envoie un e-mail à un lead AVEC suivi : enregistre le message, insère un
 * pixel d'ouverture invisible et réécrit les liens pour tracer les clics.
 */
function school_ia_send_tracked_email(object $lead, string $subject, string $bodyText, string $campaign, array $attachPaths = []): bool
{
    $CI = &get_instance();
    $CI->load->model('school_ia_bridge/school_ia_bridge_model');
    $token = $CI->school_ia_bridge_model->log_message([
        'lead_id'  => (int) $lead->id,
        'channel'  => 'email',
        'campaign' => $campaign,
        'subject'  => $subject,
        'status'   => 'sent',
        'staff_id' => function_exists('get_staff_user_id') ? (get_staff_user_id() ?: null) : null,
    ]);

    $html = nl2br($bodyText);
    // Réécrit les liens <a href="http..."> vers le traceur de clics.
    $click = site_url('school_ia_bridge/api/track_click/' . $token);
    $html = preg_replace_callback('/href="(https?:\/\/[^"]+)"/i', function ($m) use ($click) {
        return 'href="' . $click . '?u=' . rawurlencode($m[1]) . '"';
    }, $html);
    // Pixel d'ouverture.
    $html .= '<img src="' . site_url('school_ia_bridge/api/track_open/' . $token) . '" width="1" height="1" alt="" style="display:none">';

    $CI->load->library('email');
    $CI->email->clear(true);
    $CI->email->from(get_option('smtp_email') ?: get_option('companyname'), get_option('companyname'));
    $CI->email->to($lead->email);
    $CI->email->subject($subject);
    $CI->email->message($html);
    $CI->email->set_mailtype('html');
    foreach ($attachPaths as $p) {
        if (is_file($p)) {
            $CI->email->attach($p);
        }
    }
    return $CI->email->send(false);
}

/** Journalise un SMS envoyé (pour les statistiques). */
function school_ia_log_sms(int $leadId, bool $ok, string $campaign): void
{
    $CI = &get_instance();
    $CI->load->model('school_ia_bridge/school_ia_bridge_model');
    $CI->school_ia_bridge_model->log_message([
        'lead_id'  => $leadId,
        'channel'  => 'sms',
        'campaign' => $campaign,
        'status'   => $ok ? 'sent' : 'failed',
        'staff_id' => function_exists('get_staff_user_id') ? (get_staff_user_id() ?: null) : null,
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
                if (school_ia_send_tracked_email($lead, $subject, $bodyTxt, 'sequence')) {
                    $m->add_activity((int) $lead->id, 'email', 'Séquence : ' . $subject, null);
                }
            } elseif ($step->channel === 'sms' && $lead->phone) {
                $text = school_ia_personalize((string) $tpl->body, $lead);
                $okSms = school_ia_send_sms_raw((string) $lead->phone, $text, (int) $lead->id);
                school_ia_log_sms((int) $lead->id, $okSms, 'sequence');
                if ($okSms) {
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
 * Feuille de style premium — chargée UNIQUEMENT sur les pages du module
 * (aucun impact sur le reste de Perfex).
 */
hooks()->add_action('app_admin_head', 'school_ia_bridge_head_css');
function school_ia_bridge_head_css()
{
    if (strpos((string) ($_SERVER['REQUEST_URI'] ?? ''), 'school_ia_bridge') === false) {
        return;
    }
    echo '<link rel="stylesheet" href="' . module_dir_url(SCHOOL_IA_BRIDGE_MODULE, 'assets/school_ia_admin.css') . '?v=7">';
}

/**
 * Enregistre les permissions du module (Setup → Rôles) et construit le menu
 * en fonction des droits du membre connecté.
 */
hooks()->add_action('admin_init', 'school_ia_bridge_admin_init');
function school_ia_bridge_admin_init()
{
    // --- Permissions (visibles dans Setup → Rôles) ---
    register_staff_capabilities('school_ia_bridge', [
        'capabilities' => [
            'view'            => _l('Accéder au CRM School IA'),
            'manage_leads'    => _l('Gérer les leads (ajout, import, étapes, tâches, notes)'),
            'send'            => _l('Envoyer e-mails / SMS (individuels et groupés)'),
            'manage_settings' => _l('Configurer (réglages, modèles, documents, séquences)'),
        ],
    ], _l('School IA CRM'));

    school_ia_bridge_admin_menu();
}

/**
 * Menu latéral, filtré selon les permissions.
 */
function school_ia_bridge_admin_menu()
{
    $CI = &get_instance();
    if (!staff_can('view', 'school_ia_bridge')) {
        return;
    }

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
        'name'     => 'Contacts',
        'href'     => admin_url('school_ia_bridge'),
        'position' => 4,
    ]);
    $CI->app_menu->add_sidebar_children_item('school_ia_bridge', [
        'slug'     => 'school_ia_bridge_activity',
        'name'     => 'Journal',
        'href'     => admin_url('school_ia_bridge/activity'),
        'position' => 5,
    ]);
    $CI->app_menu->add_sidebar_children_item('school_ia_bridge', [
        'slug'     => 'school_ia_bridge_campaigns',
        'name'     => 'Statistiques',
        'href'     => admin_url('school_ia_bridge/campaigns'),
        'position' => 6,
    ]);
    $CI->app_menu->add_sidebar_children_item('school_ia_bridge', [
        'slug'     => 'school_ia_bridge_reporting',
        'name'     => 'Reporting',
        'href'     => admin_url('school_ia_bridge/reporting'),
        'position' => 7,
    ]);

    if (staff_can('send', 'school_ia_bridge')) {
        $CI->app_menu->add_sidebar_children_item('school_ia_bridge', [
            'slug'     => 'school_ia_bridge_bulk',
            'name'     => 'Envoi groupé',
            'href'     => admin_url('school_ia_bridge/bulk'),
            'position' => 6,
        ]);
    }

    if (staff_can('manage_settings', 'school_ia_bridge')) {
        $CI->app_menu->add_sidebar_children_item('school_ia_bridge', [
            'slug'     => 'school_ia_bridge_sequences',
            'name'     => 'Séquences',
            'href'     => admin_url('school_ia_bridge/sequences'),
            'position' => 7,
        ]);
        $CI->app_menu->add_sidebar_children_item('school_ia_bridge', [
            'slug'     => 'school_ia_bridge_templates',
            'name'     => 'Modèles',
            'href'     => admin_url('school_ia_bridge/templates'),
            'position' => 8,
        ]);
        $CI->app_menu->add_sidebar_children_item('school_ia_bridge', [
            'slug'     => 'school_ia_bridge_documents',
            'name'     => 'Documents',
            'href'     => admin_url('school_ia_bridge/documents'),
            'position' => 9,
        ]);
        $CI->app_menu->add_sidebar_children_item('school_ia_bridge', [
            'slug'     => 'school_ia_bridge_settings',
            'name'     => 'Réglages',
            'href'     => admin_url('school_ia_bridge/settings'),
            'position' => 10,
        ]);
    }
}

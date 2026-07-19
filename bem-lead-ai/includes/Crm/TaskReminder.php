<?php

namespace BemLeadAi\Crm;

use BemLeadAi\Core\Options;

defined('ABSPATH') || exit;

/**
 * Rappel quotidien des tâches de suivi à échéance : chaque membre de l'équipe
 * reçoit par email la liste de ses tâches dues (ou en retard). À défaut de
 * responsable assigné, la tâche est adressée à l'email admissions.
 */
final class TaskReminder
{
    public static function run(): void
    {
        // Respecte l'interrupteur « rappels de tâches » des Notifications.
        if (!\BemLeadAi\Notifications\Mailer::eventEnabled('task_reminder')) {
            return;
        }
        $tasks = (new CrmRepository())->dueTasks(0);
        if (!$tasks) {
            return;
        }

        // Regroupe par destinataire (responsable de la tâche, sinon admissions).
        $fallback = (string) Options::get('admissions_email') ?: get_option('admin_email');
        $byEmail = [];
        foreach ($tasks as $t) {
            $email = $fallback;
            if (!empty($t->author_id)) {
                $user = get_userdata((int) $t->author_id);
                if ($user && is_email($user->user_email)) {
                    $email = $user->user_email;
                }
            }
            $byEmail[$email][] = $t;
        }

        foreach ($byEmail as $email => $list) {
            $items = '';
            foreach ($list as $t) {
                $who = $t->prenom ?: ($t->email ?: ($t->phone ?: 'Lead #' . (int) $t->lead_id));
                $url = admin_url('admin.php?page=bem-lead-ai-leads&lead_id=' . (int) $t->lead_id);
                $items .= '<tr>'
                    . '<td style="padding:6px 10px 6px 0;color:#8a93a6;font-size:13px;white-space:nowrap;vertical-align:top;">'
                    . esc_html(mysql2date('d/m/Y', $t->due_at)) . '</td>'
                    . '<td style="padding:6px 0;color:#1f2430;font-size:13px;">'
                    . '<strong>' . esc_html($who) . '</strong> — ' . esc_html(wp_strip_all_tags((string) $t->content))
                    . ' · <a href="' . esc_url($url) . '">' . esc_html__('ouvrir', 'bem-lead-ai') . '</a></td></tr>';
            }
            $bodyHtml = '<p>' . esc_html(sprintf(__('Vous avez %d tâche(s) de suivi à traiter aujourd\'hui :', 'bem-lead-ai'), count($list))) . '</p>'
                . '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;">' . $items . '</table>';
            \BemLeadAi\Notifications\Mailer::send(
                $email,
                sprintf(__('%d tâche(s) de suivi à traiter', 'bem-lead-ai'), count($list)),
                $bodyHtml,
                admin_url('admin.php?page=bem-lead-ai-leads'),
                __('Voir mes leads', 'bem-lead-ai')
            );
        }
    }
}

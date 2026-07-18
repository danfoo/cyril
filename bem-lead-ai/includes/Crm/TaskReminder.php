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

        $school = trim((string) Options::get('school_name')) ?: 'BEM Conakry';
        foreach ($byEmail as $email => $list) {
            $lines = [];
            foreach ($list as $t) {
                $who = $t->prenom ?: ($t->email ?: ($t->phone ?: 'Lead #' . (int) $t->lead_id));
                $url = admin_url('admin.php?page=bem-lead-ai-leads&lead_id=' . (int) $t->lead_id);
                $lines[] = sprintf(
                    "• [%s] %s — %s\n  %s",
                    mysql2date('d/m/Y', $t->due_at),
                    $who,
                    wp_strip_all_tags((string) $t->content),
                    $url
                );
            }
            $body = sprintf(
                "Bonjour,\n\nVous avez %d tâche(s) de suivi à traiter aujourd'hui :\n\n%s\n\n— CRM %s",
                count($list),
                implode("\n\n", $lines),
                $school
            );
            wp_mail(
                $email,
                sprintf(
                    /* translators: %d = nombre de tâches */
                    __('%d tâche(s) de suivi à traiter — CRM', 'bem-lead-ai'),
                    count($list)
                ),
                $body
            );
        }
    }
}

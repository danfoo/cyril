<?php

namespace BemLeadAi\Core;

use BemLeadAi\Notifications\Mailer;

defined('ABSPATH') || exit;

/**
 * Surveillance des tâches planifiées (heartbeat).
 *
 * Le WP-cron est fragile : il ne se déclenche que s'il y a du trafic (sauf vraie
 * tâche système sur wp-cron.php). Si une tâche cale, personne n'est prévenu et
 * campagnes, relances de désengagement ou rappels CRM s'arrêtent en silence.
 *
 * Chaque cron surveillé horodate son passage (recordSuccess). Une vérification
 * légère au chargement de l'admin (throttlée) détecte les tâches en retard et
 * alerte via l'infrastructure existante (e-mail brandé + Slack).
 */
final class CronHealth
{
    private const OPT_RUNS       = 'bem_lead_ai_cron_last_runs';    // array hook => timestamp
    private const OPT_INSTALLED  = 'bem_lead_ai_cron_installed_at';  // int timestamp
    private const OPT_LAST_CHECK = 'bem_lead_ai_cron_last_check';    // int timestamp
    private const OPT_LAST_ALERT = 'bem_lead_ai_cron_last_alert';    // int timestamp

    /** On ne vérifie qu'une fois par heure au plus (léger). */
    private const CHECK_INTERVAL = 3600;
    /** On ne ré-alerte pas avant 6 h, pour ne pas spammer. */
    private const ALERT_COOLDOWN = 21600;

    /**
     * Tâche => âge maximal toléré (secondes). ~3× l'intervalle de planification
     * pour absorber la gigue du WP-cron sans fausse alerte.
     */
    private const MONITORED = [
        'bem_lead_ai_cron_disengagement' => 10800,  // horaire → alerte après 3 h
        'bem_lead_ai_cron_bandit'        => 10800,  // horaire → alerte après 3 h
        'bem_lead_ai_cron_crm_tasks'     => 108000, // quotidien → alerte après 30 h
        'bem_lead_ai_cron_license'       => 108000, // quotidien → alerte après 30 h
    ];

    private const LABELS = [
        'bem_lead_ai_cron_disengagement' => 'Détection de désengagement',
        'bem_lead_ai_cron_bandit'        => 'Optimisation des relances',
        'bem_lead_ai_cron_crm_tasks'     => 'Rappels de tâches CRM',
        'bem_lead_ai_cron_license'       => 'Validation de licence',
    ];

    /** @return string[] Les hooks surveillés (pour brancher les enregistreurs). */
    public static function monitoredHooks(): array
    {
        return array_keys(self::MONITORED);
    }

    /** Enregistre un passage réussi (branché en priorité tardive sur chaque hook). */
    public static function recordSuccess(string $hook): void
    {
        $runs = self::runs();
        $runs[$hook] = time();
        update_option(self::OPT_RUNS, $runs, false);
    }

    /**
     * Cœur PUR : renvoie les tâches en retard d'après leurs derniers passages.
     * Une tâche sans passage connu est jugée d'après la date d'installation, ce
     * qui laisse une période de grâce juste après l'activation du plugin.
     *
     * @param array<string,int> $lastRuns hook => timestamp du dernier passage
     * @return array<string,array{label:string,age:int,last_run:?int,max_age:int}>
     */
    public static function staleHooks(array $lastRuns, int $installedAt, int $now): array
    {
        $stale = [];
        foreach (self::MONITORED as $hook => $maxAge) {
            $reference = $lastRuns[$hook] ?? $installedAt;
            $age = $now - $reference;
            if ($age > $maxAge) {
                $stale[$hook] = [
                    'label'    => self::LABELS[$hook] ?? $hook,
                    'age'      => $age,
                    'last_run' => $lastRuns[$hook] ?? null,
                    'max_age'  => $maxAge,
                ];
            }
        }
        return $stale;
    }

    /** Vérification throttlée (branchée sur admin_init). Alerte si nécessaire. */
    public static function maybeRun(): void
    {
        $now = time();
        if ($now - (int) get_option(self::OPT_LAST_CHECK, 0) < self::CHECK_INTERVAL) {
            return;
        }
        update_option(self::OPT_LAST_CHECK, $now, false);

        $stale = self::staleHooks(self::runs(), self::installedAt(), $now);
        if (!$stale) {
            return;
        }
        // Anti-spam : on respecte le délai de refroidissement entre deux alertes.
        if ($now - (int) get_option(self::OPT_LAST_ALERT, 0) < self::ALERT_COOLDOWN) {
            return;
        }
        update_option(self::OPT_LAST_ALERT, $now, false);
        self::alert($stale);
    }

    /**
     * État lisible pour l'admin.
     * @return array<int,array{label:string,last_run:?int,late:bool}>
     */
    public static function status(): array
    {
        $runs = self::runs();
        $stale = self::staleHooks($runs, self::installedAt(), time());
        $rows = [];
        foreach (self::MONITORED as $hook => $maxAge) {
            $rows[] = [
                'label'    => self::LABELS[$hook] ?? $hook,
                'last_run' => $runs[$hook] ?? null,
                'late'     => isset($stale[$hook]),
            ];
        }
        return $rows;
    }

    /** Âge lisible : « 5 h », « 2 j ». */
    public static function humanAge(int $seconds): string
    {
        $hours = (int) floor($seconds / 3600);
        if ($hours < 1) {
            return (int) floor($seconds / 60) . ' min';
        }
        if ($hours < 24) {
            return $hours . ' h';
        }
        return (int) floor($hours / 24) . ' j';
    }

    private static function alert(array $stale): void
    {
        $lines = array_map(fn($s) => '• ' . $s['label'] . ' — en retard de ' . self::humanAge($s['age']), $stale);

        $items = implode('', array_map(
            fn($s) => '<li><strong>' . esc_html($s['label']) . '</strong> — '
                . esc_html(__('en retard de', 'bem-lead-ai') . ' ' . self::humanAge($s['age'])) . '</li>',
            $stale
        ));
        $bodyHtml = '<p>' . esc_html__('Certaines tâches planifiées ne se sont pas exécutées à temps :', 'bem-lead-ai')
            . '</p><ul>' . $items . '</ul><p>'
            . esc_html__('Vérifiez que le WP-cron du site se déclenche (trafic régulier, ou une tâche système appelant wp-cron.php).', 'bem-lead-ai')
            . '</p>';
        Mailer::sendEvent(
            'cron_health',
            __('Tâches planifiées en retard', 'bem-lead-ai'),
            $bodyHtml,
            admin_url('admin.php?page=bem-lead-ai-settings'),
            __('Ouvrir les réglages', 'bem-lead-ai')
        );

        $slack = (string) Options::get('slack_webhook_url');
        if ($slack !== '') {
            $host = wp_parse_url(home_url(), PHP_URL_HOST) ?: 'School IA';
            wp_remote_post($slack, [
                'timeout' => 10,
                'headers' => ['Content-Type' => 'application/json'],
                'body'    => wp_json_encode(['text' => "⚠️ *{$host}* — tâches planifiées en retard :\n" . implode("\n", $lines)]),
            ]);
        }
    }

    private static function runs(): array
    {
        $runs = get_option(self::OPT_RUNS, []);
        return is_array($runs) ? $runs : [];
    }

    /** Date d'installation (posée paresseusement au premier appel). */
    public static function installedAt(): int
    {
        $ts = (int) get_option(self::OPT_INSTALLED, 0);
        if ($ts === 0) {
            $ts = time();
            update_option(self::OPT_INSTALLED, $ts, false);
        }
        return $ts;
    }
}

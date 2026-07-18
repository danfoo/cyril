<?php

namespace BemLeadAi\Core;

defined('ABSPATH') || exit;

final class Deactivator
{
    public static function deactivate(): void
    {
        wp_clear_scheduled_hook('bem_lead_ai_cron_disengagement');
        wp_clear_scheduled_hook('bem_lead_ai_cron_reindex');
        wp_clear_scheduled_hook('bem_lead_ai_cron_bandit');
        flush_rewrite_rules();
    }
}

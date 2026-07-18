<?php

namespace BemLeadAi\Core;

defined('ABSPATH') || exit;

final class Deactivator
{
    public static function deactivate(): void
    {
        wp_clear_scheduled_hook('bem_lead_ai_cron_disengagement');
        wp_clear_scheduled_hook('bem_lead_ai_cron_rebuild_kb');
        wp_clear_scheduled_hook('bem_lead_ai_cron_bandit');
        wp_clear_scheduled_hook('bem_lead_ai_cron_crm_tasks');
        flush_rewrite_rules();
    }
}

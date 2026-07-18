<?php

namespace BemLeadAi\Core;

defined('ABSPATH') || exit;

/**
 * File asynchrone : Action Scheduler si présent (retries, priorités),
 * sinon repli sur WP-Cron. L'escalade humaine passe en priorité haute.
 */
final class Queue
{
    private const GROUP = 'bem-lead-ai';

    public static function dispatch(string $hook, array $args = [], bool $highPriority = false): void
    {
        if (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action($hook, $args, self::GROUP, false, $highPriority ? 5 : 10);
            return;
        }
        // Repli WP-Cron : exécution au prochain tick.
        if (!wp_next_scheduled($hook, $args)) {
            wp_schedule_single_event(time() + ($highPriority ? 0 : 5), $hook, $args);
        }
    }

    public static function dispatchIn(int $delaySeconds, string $hook, array $args = []): void
    {
        if (function_exists('as_schedule_single_action')) {
            as_schedule_single_action(time() + $delaySeconds, $hook, $args, self::GROUP);
            return;
        }
        wp_schedule_single_event(time() + $delaySeconds, $hook, $args);
    }
}

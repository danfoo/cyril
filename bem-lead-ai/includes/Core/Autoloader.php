<?php

namespace BemLeadAi\Core;

defined('ABSPATH') || exit;

/**
 * Autoloader PSR-4 minimal : BemLeadAi\ => includes/.
 * Évite une dépendance à `composer install` en production.
 */
final class Autoloader
{
    public static function register(): void
    {
        spl_autoload_register(static function (string $class): void {
            if (!str_starts_with($class, 'BemLeadAi\\')) {
                return;
            }
            $relative = substr($class, strlen('BemLeadAi\\'));
            $path = BEM_LEAD_AI_DIR . 'includes/' . str_replace('\\', '/', $relative) . '.php';
            if (is_file($path)) {
                require_once $path;
            }
        });
    }
}

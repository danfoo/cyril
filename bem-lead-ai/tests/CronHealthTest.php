<?php

namespace BemLeadAi\Tests;

use BemLeadAi\Core\CronHealth;
use PHPUnit\Framework\TestCase;

/**
 * Surveillance des tâches planifiées : détection des retards (le WP-cron étant
 * fragile) et période de grâce après installation pour éviter les fausses alertes.
 */
final class CronHealthTest extends TestCase
{
    private const DISENGAGEMENT = 'bem_lead_ai_cron_disengagement';
    private const BANDIT        = 'bem_lead_ai_cron_bandit';
    private const CRM_TASKS     = 'bem_lead_ai_cron_crm_tasks';
    private const LICENSE       = 'bem_lead_ai_cron_license';

    protected function setUp(): void
    {
        \FakeLeadStore::reset();
    }

    /* ---------- staleHooks() : cœur de détection (pur) ---------- */

    public function testDetectsOverdueHourlyHook(): void
    {
        $now = 1_700_000_000;
        $lastRuns = [
            self::DISENGAGEMENT => $now - 4 * 3600, // horaire, toléré 3 h → EN RETARD
            self::BANDIT        => $now - 1 * 3600, // à l'heure
            self::CRM_TASKS     => $now - 2 * 3600, // quotidien → OK
            self::LICENSE       => $now - 2 * 3600,
        ];

        $stale = CronHealth::staleHooks($lastRuns, $now - 30 * 86400, $now);

        $this->assertArrayHasKey(self::DISENGAGEMENT, $stale);
        $this->assertArrayNotHasKey(self::BANDIT, $stale);
        $this->assertArrayNotHasKey(self::CRM_TASKS, $stale);
        $this->assertSame(4 * 3600, $stale[self::DISENGAGEMENT]['age']);
    }

    public function testDailyHookOverdue(): void
    {
        $now = 1_700_000_000;
        // Quotidien toléré 30 h : à 31 h il est en retard.
        $lastRuns = [self::CRM_TASKS => $now - 31 * 3600];

        $stale = CronHealth::staleHooks($lastRuns, $now - 30 * 86400, $now);

        $this->assertArrayHasKey(self::CRM_TASKS, $stale);
    }

    public function testNeverRunHookJudgedFromInstallDate(): void
    {
        $now = 1_700_000_000;
        // Aucun passage enregistré, installé il y a 5 h → les tâches horaires
        // sont en retard, mais pas les quotidiennes (tolérance 30 h).
        $stale = CronHealth::staleHooks([], $now - 5 * 3600, $now);

        $this->assertArrayHasKey(self::DISENGAGEMENT, $stale);
        $this->assertArrayHasKey(self::BANDIT, $stale);
        $this->assertArrayNotHasKey(self::CRM_TASKS, $stale);
        $this->assertNull($stale[self::DISENGAGEMENT]['last_run']);
    }

    public function testFreshInstallHasGracePeriod(): void
    {
        $now = 1_700_000_000;
        // Installé il y a 1 h, aucun passage encore : rien ne doit être signalé.
        $stale = CronHealth::staleHooks([], $now - 3600, $now);

        $this->assertSame([], $stale);
    }

    /* ---------- humanAge() : formatage ---------- */

    public function testHumanAgeFormatting(): void
    {
        $this->assertSame('30 min', CronHealth::humanAge(30 * 60));
        $this->assertSame('1 h', CronHealth::humanAge(90 * 60));
        $this->assertSame('5 h', CronHealth::humanAge(5 * 3600));
        $this->assertSame('2 j', CronHealth::humanAge(50 * 3600));
    }

    /* ---------- État persistant : record + status ---------- */

    public function testRecordSuccessMakesHookHealthy(): void
    {
        CronHealth::recordSuccess(self::BANDIT);

        $status = self::rowFor(CronHealth::status(), 'Optimisation des relances');
        $this->assertFalse($status['late']);
        $this->assertNotNull($status['last_run']);
    }

    public function testStatusFlagsStaleHookFromStoredRuns(): void
    {
        // Un passage ancien enregistré → la tâche apparaît « en retard ».
        update_option('bem_lead_ai_cron_last_runs', [self::DISENGAGEMENT => time() - 5 * 3600]);

        $status = self::rowFor(CronHealth::status(), 'Détection de désengagement');
        $this->assertTrue($status['late']);
    }

    private static function rowFor(array $rows, string $label): array
    {
        foreach ($rows as $r) {
            if ($r['label'] === $label) {
                return $r;
            }
        }
        self::fail("Ligne de statut introuvable : {$label}");
    }
}

<?php

namespace BemLeadAi\Tests;

use BemLeadAi\Leads\EventRepository;
use BemLeadAi\Scoring\DisengagementDetector;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Détection de désengagement par vélocité : un lead qui retombe APRÈS un pic
 * d'activité est le moment idéal pour une relance. On teste le calcul de pic
 * (fenêtre glissante 72 h) et la décision d'émettre l'événement `disengagement`.
 *
 * Note : le filtrage SQL des candidats (statut, score, last_seen) n'est pas
 * reproductible hors base ; on teste la LOGIQUE PHP qui consomme les lignes.
 */
final class DisengagementTest extends TestCase
{
    private const NOW = '2026-01-15 12:00:00';

    private DisengagementDetector $detector;

    protected function setUp(): void
    {
        \FakeLeadStore::reset();
        \FakeLeadStore::$now = strtotime(self::NOW);
        $this->detector = new DisengagementDetector();
    }

    private function at(int $secondsAgo): string
    {
        return date('Y-m-d H:i:s', strtotime(self::NOW) - $secondsAgo);
    }

    /** Appelle la méthode privée activityWindows(). @return array{0:int,1:int} */
    private function windows(int $leadId): array
    {
        $m = new ReflectionMethod($this->detector, 'activityWindows');
        $m->setAccessible(true);
        return $m->invoke($this->detector, $leadId);
    }

    /* ---------- activityWindows() : pic et activité récente ---------- */

    public function testNoEventsGivesZeroWindows(): void
    {
        $this->assertSame([0, 0], $this->windows(1));
    }

    public function testPeakAndRecentAreCounted(): void
    {
        // Pic : 5 événements groupés il y a 10 jours (dans la même fenêtre 72 h).
        for ($i = 0; $i < 5; $i++) {
            \FakeLeadStore::addRawEvent($this->at(10 * DAY_IN_SECONDS - $i * 60));
        }
        // Récent : 1 seul événement il y a 1 h.
        \FakeLeadStore::addRawEvent($this->at(HOUR_IN_SECONDS));

        [$peak, $recent] = $this->windows(1);
        $this->assertSame(5, $peak, 'pic = la grappe la plus dense sur 72 h');
        $this->assertSame(1, $recent, 'activité des 72 dernières heures');
    }

    public function testSlidingWindowPicksDensestCluster(): void
    {
        // Grappe A (il y a 10 j) : 3 événements en 2 h → fenêtre de 3.
        \FakeLeadStore::addRawEvent($this->at(10 * DAY_IN_SECONDS));
        \FakeLeadStore::addRawEvent($this->at(10 * DAY_IN_SECONDS - HOUR_IN_SECONDS));
        \FakeLeadStore::addRawEvent($this->at(10 * DAY_IN_SECONDS - 2 * HOUR_IN_SECONDS));
        // Grappe B (récente) : 2 événements → fenêtre de 2.
        \FakeLeadStore::addRawEvent($this->at(HOUR_IN_SECONDS));
        \FakeLeadStore::addRawEvent($this->at(2 * HOUR_IN_SECONDS));

        [$peak, $recent] = $this->windows(1);
        $this->assertSame(3, $peak, 'la fenêtre la plus dense l\'emporte');
        $this->assertSame(2, $recent, 'les deux événements récents comptent');
    }

    /* ---------- run() : décision d'émission ---------- */

    private function seedPeakThenDrop(int $recentCount, int $peakCount = 5): void
    {
        \FakeLeadStore::addCandidate(1);
        for ($i = 0; $i < $peakCount; $i++) {
            \FakeLeadStore::addRawEvent($this->at(10 * DAY_IN_SECONDS - $i * 60));
        }
        for ($i = 0; $i < $recentCount; $i++) {
            \FakeLeadStore::addRawEvent($this->at(HOUR_IN_SECONDS + $i * HOUR_IN_SECONDS));
        }
    }

    public function testEmitsDisengagementOnSharpDrop(): void
    {
        // Pic 5, récent 1 → chute ≥ 70 % (seuil : recent ≤ 5 × 0.3 = 1.5).
        $this->seedPeakThenDrop(1);
        $this->detector->run();

        $this->assertSame(1, EventRepository::countRecorded('disengagement'));
        $rec = array_values(array_filter(EventRepository::$records, fn($r) => $r['type'] === 'disengagement'))[0];
        $this->assertSame(5, $rec['payload']['peak_activity']);
        $this->assertSame(1, $rec['payload']['recent_activity']);
    }

    public function testNoEmitWhenPeakTooLow(): void
    {
        // Pic de 2 seulement (< 3) : pas assez d'engagement initial.
        $this->seedPeakThenDrop(1, 2);
        $this->detector->run();

        $this->assertSame(0, EventRepository::countRecorded('disengagement'));
    }

    public function testNoEmitWhenRecentActivityStillHigh(): void
    {
        // Pic 5 mais 4 événements récents : pas de vraie chute (4 > 1.5).
        $this->seedPeakThenDrop(4);
        $this->detector->run();

        $this->assertSame(0, EventRepository::countRecorded('disengagement'));
    }

    public function testDoesNotRepeatRecentDisengagement(): void
    {
        // Un désengagement a déjà été signalé il y a 2 jours → on ne répète pas.
        \FakeLeadStore::$lastOfType = (object) ['created_at' => $this->at(2 * DAY_IN_SECONDS)];
        $this->seedPeakThenDrop(1);
        $this->detector->run();

        $this->assertSame(0, EventRepository::countRecorded('disengagement'));
    }

    public function testEmitsAgainWhenLastDisengagementIsOld(): void
    {
        // Dernier signalement il y a 20 jours (> lookback 14 j) → on peut relancer.
        \FakeLeadStore::$lastOfType = (object) ['created_at' => $this->at(20 * DAY_IN_SECONDS)];
        $this->seedPeakThenDrop(1);
        $this->detector->run();

        $this->assertSame(1, EventRepository::countRecorded('disengagement'));
    }
}

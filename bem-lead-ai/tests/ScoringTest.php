<?php

namespace BemLeadAi\Tests;

use BemLeadAi\Scoring\RulesRepository;
use BemLeadAi\Scoring\ScoringEngine;
use PHPUnit\Framework\TestCase;

/**
 * Cœur de la valeur du module : le score comportemental (règles pondérées ×
 * décroissance de récence) et son mélange avec l'intention conversationnelle.
 */
final class ScoringTest extends TestCase
{
    private ScoringEngine $engine;

    protected function setUp(): void
    {
        \FakeLeadStore::reset();
        $this->engine = new ScoringEngine();
    }

    /* ---------- band() : classification marketing ---------- */

    public function testBandThresholds(): void
    {
        // Seuils par défaut : tiède ≥ 30, chaud ≥ 60, très chaud ≥ 80.
        $this->assertSame('froid', ScoringEngine::band(10));
        $this->assertSame('tiede', ScoringEngine::band(30));
        $this->assertSame('tiede', ScoringEngine::band(45));
        $this->assertSame('chaud', ScoringEngine::band(60));
        $this->assertSame('chaud', ScoringEngine::band(72));
        $this->assertSame('tres_chaud', ScoringEngine::band(80));
        $this->assertSame('tres_chaud', ScoringEngine::band(95));
    }

    /* ---------- ruleMatchesEvent() : logique de correspondance ---------- */

    public function testRuleMatchesOnEventType(): void
    {
        $rules = new RulesRepository();
        $event = (object) ['type' => 'cta_click'];

        $this->assertTrue($rules->ruleMatchesEvent(['event_type' => 'cta_click'], $event, []));
        $this->assertFalse($rules->ruleMatchesEvent(['event_type' => 'page_view'], $event, []));
    }

    public function testRuleMatchesOnPayloadEqualityAndThreshold(): void
    {
        $rules = new RulesRepository();
        $event = (object) ['type' => 'time_on_page'];

        // Égalité stricte sur une clé de payload.
        $this->assertTrue($rules->ruleMatchesEvent(['payload.page_kind' => 'pricing'], $event, ['page_kind' => 'pricing']));
        $this->assertFalse($rules->ruleMatchesEvent(['payload.page_kind' => 'pricing'], $event, ['page_kind' => 'generic']));

        // Seuil « _gte » : la valeur doit être supérieure ou égale.
        $this->assertTrue($rules->ruleMatchesEvent(['payload.seconds_gte' => 30], $event, ['seconds' => 45]));
        $this->assertFalse($rules->ruleMatchesEvent(['payload.seconds_gte' => 30], $event, ['seconds' => 10]));
        $this->assertFalse($rules->ruleMatchesEvent(['payload.seconds_gte' => 30], $event, []));
    }

    /* ---------- behavioralScore() : pondération × récence ---------- */

    public function testNoRulesOrNoEventsScoresZero(): void
    {
        \FakeLeadStore::insert(['session_id' => 'web_1']);
        // Aucune règle déclarée.
        $this->assertSame(0.0, $this->engine->behavioralScore(1));

        // Une règle mais aucun événement.
        \FakeLeadStore::addRule('event', ['event_type' => 'cta_click'], 20);
        $this->assertSame(0.0, $this->engine->behavioralScore(1));
    }

    public function testFreshEventCountsAtFullWeight(): void
    {
        $now = strtotime('2026-01-15 12:00:00');
        \FakeLeadStore::$now = $now;
        \FakeLeadStore::insert(['session_id' => 'web_1']);
        \FakeLeadStore::addRule('event', ['event_type' => 'cta_click'], 20);
        \FakeLeadStore::addEvent(1, 'cta_click', [], date('Y-m-d H:i:s', $now)); // âge 0

        // Décroissance = 1 (0.5^0) → poids plein.
        $this->assertEqualsWithDelta(20.0, $this->engine->behavioralScore(1), 0.001);
    }

    public function testEventDecaysByHalfAtOneHalfLife(): void
    {
        $now = strtotime('2026-01-15 12:00:00');
        \FakeLeadStore::$now = $now;
        \FakeLeadStore::insert(['session_id' => 'web_1']);
        \FakeLeadStore::addRule('event', ['event_type' => 'cta_click'], 20);
        // Demi-vie par défaut = 7 jours → un événement de 7 jours vaut la moitié.
        \FakeLeadStore::addEvent(1, 'cta_click', [], date('Y-m-d H:i:s', $now - 7 * DAY_IN_SECONDS));

        $this->assertEqualsWithDelta(10.0, $this->engine->behavioralScore(1), 0.001);
    }

    public function testScoreIsCappedAt100(): void
    {
        $now = strtotime('2026-01-15 12:00:00');
        \FakeLeadStore::$now = $now;
        \FakeLeadStore::insert(['session_id' => 'web_1']);
        \FakeLeadStore::addRule('event', ['event_type' => 'cta_click'], 60);
        // Deux événements frais à 60 = 120 → plafonné à 100.
        \FakeLeadStore::addEvent(1, 'cta_click', [], date('Y-m-d H:i:s', $now));
        \FakeLeadStore::addEvent(1, 'cta_click', [], date('Y-m-d H:i:s', $now));

        $this->assertSame(100.0, $this->engine->behavioralScore(1));
    }

    public function testOnlyFirstMatchingRuleCountsPerEvent(): void
    {
        $now = strtotime('2026-01-15 12:00:00');
        \FakeLeadStore::$now = $now;
        \FakeLeadStore::insert(['session_id' => 'web_1']);
        // Deux règles pourraient matcher le même événement ; une seule compte.
        \FakeLeadStore::addRule('event', ['event_type' => 'cta_click'], 20);
        \FakeLeadStore::addRule('event', ['event_type' => 'cta_click'], 999);
        \FakeLeadStore::addEvent(1, 'cta_click', [], date('Y-m-d H:i:s', $now));

        $this->assertEqualsWithDelta(20.0, $this->engine->behavioralScore(1), 0.001);
    }

    /* ---------- recalculate() : mélange comportement / intention ---------- */

    public function testRecalculateWithoutIntentUsesBehavioralOnly(): void
    {
        $now = strtotime('2026-01-15 12:00:00');
        \FakeLeadStore::$now = $now;
        $lead = \FakeLeadStore::insert(['session_id' => 'web_1', 'score_intention' => 0]);
        \FakeLeadStore::addRule('event', ['event_type' => 'cta_click'], 40);
        \FakeLeadStore::addEvent(1, 'cta_click', [], date('Y-m-d H:i:s', $now));

        $this->engine->recalculate(1);

        $this->assertSame(40.0, \FakeLeadStore::$leads[$lead->id]->score_comportemental);
        $this->assertSame(40.0, \FakeLeadStore::$leads[$lead->id]->score_final);
    }

    public function testRecalculateBlendsIntentAboveBehavior(): void
    {
        $now = strtotime('2026-01-15 12:00:00');
        \FakeLeadStore::$now = $now;
        $lead = \FakeLeadStore::insert(['session_id' => 'web_1', 'score_intention' => 80]);
        \FakeLeadStore::addRule('event', ['event_type' => 'cta_click'], 40);
        \FakeLeadStore::addEvent(1, 'cta_click', [], date('Y-m-d H:i:s', $now));

        $this->engine->recalculate(1);

        // final = 40 × (1 − 0.55) + 80 × 0.55 = 18 + 44 = 62.
        $this->assertEqualsWithDelta(62.0, \FakeLeadStore::$leads[$lead->id]->score_final, 0.001);
        // L'intention pèse plus lourd : le final dépasse le comportement seul.
        $this->assertGreaterThan(
            \FakeLeadStore::$leads[$lead->id]->score_comportemental,
            \FakeLeadStore::$leads[$lead->id]->score_final
        );
    }

    public function testRecalculateFinalIsCappedAt100(): void
    {
        $now = strtotime('2026-01-15 12:00:00');
        \FakeLeadStore::$now = $now;
        $lead = \FakeLeadStore::insert(['session_id' => 'web_1', 'score_intention' => 100]);
        \FakeLeadStore::addRule('event', ['event_type' => 'cta_click'], 100);
        \FakeLeadStore::addEvent(1, 'cta_click', [], date('Y-m-d H:i:s', $now));

        $this->engine->recalculate(1);

        $this->assertSame(100.0, \FakeLeadStore::$leads[$lead->id]->score_final);
    }
}

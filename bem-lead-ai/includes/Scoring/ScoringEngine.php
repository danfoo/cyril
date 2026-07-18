<?php

namespace BemLeadAi\Scoring;

use BemLeadAi\Core\Options;
use BemLeadAi\Leads\EventRepository;
use BemLeadAi\Leads\LeadRepository;

defined('ABSPATH') || exit;

/**
 * Score comportemental : règles pondérées × décroissance exponentielle.
 *
 * Logique marketing :
 *  - la RÉCENCE compte — un pic d'activité il y a 3 semaines vaut moins
 *    qu'une visite hier (demi-vie configurable, 7 jours par défaut) ;
 *  - l'INTENTION conversationnelle (extraite par le LLM) pèse plus lourd que
 *    le comportement de navigation dans le score final : dire « comment
 *    candidater ? » est un signal plus fort que 10 pages vues ;
 *  - tant qu'aucune conversation n'a eu lieu, le score final = comportement
 *    seul (on ne pénalise pas les leads silencieux mais actifs).
 */
final class ScoringEngine
{
    public function recalculate(int $leadId): void
    {
        $leads = new LeadRepository();
        $lead = $leads->findById($leadId);
        if (!$lead) {
            return;
        }

        $behavioral = $this->behavioralScore($leadId);
        $intent = (float) $lead->score_intention;

        $intentWeight = (float) Options::get('score_blend_intent_weight');
        $final = $intent > 0
            ? ($behavioral * (1 - $intentWeight)) + ($intent * $intentWeight)
            : $behavioral;

        $leads->update($leadId, [
            'score_comportemental' => round($behavioral, 2),
            'score_final' => round(min(100, $final), 2),
        ]);
    }

    public function behavioralScore(int $leadId): float
    {
        $rules = new RulesRepository();
        $activeRules = array_filter($rules->activeRules(), fn($r) => $r->type === 'event');
        if (!$activeRules) {
            return 0.0;
        }

        $events = (new EventRepository())->forLead($leadId, 90);
        if (!$events) {
            return 0.0;
        }

        $halfLifeDays = max(1, (float) Options::get('score_decay_half_life_days'));
        $now = current_time('timestamp');
        $score = 0.0;

        foreach ($events as $event) {
            $payload = $event->payload ? (json_decode($event->payload, true) ?: []) : [];
            foreach ($activeRules as $rule) {
                if (!$rules->ruleMatchesEvent($rule->condition, $event, $payload)) {
                    continue;
                }
                $ageDays = max(0, ($now - strtotime($event->created_at)) / DAY_IN_SECONDS);
                $decay = pow(0.5, $ageDays / $halfLifeDays);
                $score += (float) $rule->poids * $decay;
                break; // une seule règle (la première qui matche) par événement
            }
        }

        return min(100.0, $score);
    }

    /** Bande marketing du score : froid / tiède / chaud / très chaud. */
    public static function band(float $score): string
    {
        if ($score >= (float) Options::get('threshold_very_hot')) {
            return 'tres_chaud';
        }
        if ($score >= (float) Options::get('threshold_hot')) {
            return 'chaud';
        }
        if ($score >= (float) Options::get('threshold_warm')) {
            return 'tiede';
        }
        return 'froid';
    }
}

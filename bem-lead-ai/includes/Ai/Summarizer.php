<?php

namespace BemLeadAi\Ai;

use BemLeadAi\Chat\ConversationRepository;
use BemLeadAi\Core\Options;
use BemLeadAi\Leads\LeadRepository;

defined('ABSPATH') || exit;

/**
 * Résumé automatique de conversation + recommandation d'approche commerciale,
 * poussé vers le CRM à chaque escalade ou franchissement de seuil.
 */
final class Summarizer
{
    public function summarize(int $leadId): ?string
    {
        $lead = (new LeadRepository())->findById($leadId);
        $history = (new ConversationRepository())->history($leadId, 40);
        if (!$lead || !$history) {
            return null;
        }

        $transcript = implode("\n", array_map(
            fn($m) => sprintf('[%s] %s: %s', $m->canal, $m->role === 'user' ? 'Prospect' : ($m->role === 'agent' ? 'Conseiller humain' : 'IA'), $m->contenu),
            $history
        ));

        $system = "Tu prépares une fiche de passation pour l'équipe admissions de BEM Dakar. À partir de la conversation, rédige en français, en 6 lignes maximum :\n"
            . "1. PROFIL : qui est le prospect (prénom, formation visée, situation).\n"
            . "2. SIGNAUX : intention, urgence, freins (prix, hésitations), concurrents évoqués.\n"
            . "3. APPROCHE RECOMMANDÉE : le meilleur angle pour le prochain contact humain (argument à mettre en avant, objection à lever, canal et délai conseillés).\n"
            . "Sois concret et actionnable, pas de généralités.";

        $summary = (new ClaudeClient())->complete(
            (string) Options::get('chat_model'),
            $system,
            [['role' => 'user', 'content' => "Score final: {$lead->score_final}/100. Conversation:\n" . $transcript]],
            600
        );

        return is_wp_error($summary) ? null : $summary;
    }
}

<?php

namespace BemLeadAi\Ai;

use BemLeadAi\Chat\ConversationRepository;
use BemLeadAi\Core\Options;
use BemLeadAi\Leads\EventRepository;
use BemLeadAi\Leads\LeadRepository;

defined('ABSPATH') || exit;

/**
 * Classification multi-signaux : UN SEUL appel LLM structuré (modèle léger)
 * remonte à la fois intention, urgence, mention concurrent et sensibilité
 * prix — mutualise le coût au lieu d'un appel par signal.
 *
 * Exécutée en asynchrone, par lot de messages, jamais dans le fil de la
 * conversation.
 */
final class SignalClassifier
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
Tu analyses des messages envoyés par un prospect au conseiller d'orientation virtuel de BEM Dakar (école de management, Sénégal). Réponds UNIQUEMENT avec un objet JSON strict, sans aucun texte autour, avec exactement ces clés :

{
  "intent_level": <entier 0-100 : probabilité que ce prospect candidate réellement. 0-20 curiosité vague, 21-45 intérêt réel mais exploratoire, 46-70 projet concret (questions sur admission, dossier, dates), 71-100 décision imminente (veut candidater, demande les étapes, parle de délais)>,
  "formation": <string|null : formation visée si identifiable (ex: "Master Finance", "Bachelor Marketing")>,
  "urgency": <"none"|"low"|"high" : "high" si le prospect exprime une échéance pressante, une détresse, une hésitation critique de dernière minute, ou demande explicitement à parler à un humain>,
  "price_sensitivity": <true|false : questions ou inquiétudes sur les frais, le coût, les bourses, les facilités de paiement>,
  "competitors": [<liste des écoles/universités concurrentes explicitement mentionnées, avec pour chacune {"name": string, "context": string extrait court}>],
  "prenom": <string|null : prénom du prospect s'il s'est présenté>,
  "email": <string|null : adresse email si le prospect en a communiqué une>,
  "phone": <string|null : numéro de téléphone si le prospect en a communiqué un>
}

Sois factuel : n'invente aucun signal absent des messages.
PROMPT;

    public function classifyPendingMessages(int $leadId): void
    {
        $conversations = new ConversationRepository();
        $messages = $conversations->unclassifiedUserMessages($leadId);
        if (!$messages) {
            return;
        }

        $text = implode("\n---\n", array_map(fn($m) => $m->contenu, $messages));
        $client = new ClaudeClient();
        $result = $client->completeJson(
            (string) Options::get('classifier_model'),
            self::SYSTEM_PROMPT,
            [['role' => 'user', 'content' => "Messages du prospect :\n" . $text]]
        );

        // On marque comme traités même en cas d'erreur pour ne pas boucler ;
        // le prochain lot de messages relancera une classification.
        $conversations->markClassified(array_map(fn($m) => (int) $m->id, $messages));

        if (is_wp_error($result)) {
            error_log('[bem-lead-ai] Classification échouée: ' . $result->get_error_message());
            return;
        }

        $leads = new LeadRepository();
        $lead = $leads->findById($leadId);
        if (!$lead) {
            return;
        }

        $updates = [];
        $intent = isset($result['intent_level']) ? max(0, min(100, (int) $result['intent_level'])) : null;
        if ($intent !== null) {
            // L'intention ne fait que monter (ratchet) : un message banal après
            // un message très engagé ne doit pas faire retomber le score.
            $updates['score_intention'] = max((float) $lead->score_intention, (float) $intent);
        }
        if (!empty($result['formation'])) {
            $updates['formation_interet'] = sanitize_text_field((string) $result['formation']);
        }
        if (!empty($result['prenom']) && empty($lead->prenom)) {
            $updates['prenom'] = sanitize_text_field((string) $result['prenom']);
        }
        if ($updates) {
            $leads->update($leadId, $updates);
        }

        // Coordonnées communiquées en conversation → rattachées au lead (events
        // email_captured/phone_captured = signaux de forte intention).
        $email = !empty($result['email']) && is_email((string) $result['email']) ? sanitize_email((string) $result['email']) : null;
        $phone = !empty($result['phone']) ? preg_replace('/[^0-9+\s]/', '', (string) $result['phone']) : null;
        if (($email && empty($lead->email)) || ($phone && empty($lead->phone))) {
            (new \BemLeadAi\Chat\ChannelAdapter())->attachIdentity(
                $leads->findById($leadId),
                empty($lead->email) ? $email : null,
                empty($lead->phone) ? $phone : null
            );
        }

        $signals = [
            'urgency' => in_array($result['urgency'] ?? 'none', ['none', 'low', 'high'], true) ? $result['urgency'] : 'none',
            'price_sensitivity' => !empty($result['price_sensitivity']),
            'last_classified_at' => current_time('mysql'),
        ];
        $leads->mergeSignals($leadId, $signals);

        // Veille concurrentielle : capitalisée sans appel LLM supplémentaire.
        foreach ((array) ($result['competitors'] ?? []) as $competitor) {
            if (empty($competitor['name'])) {
                continue;
            }
            global $wpdb;
            $wpdb->insert($wpdb->prefix . 'bem_competitor_mentions', [
                'lead_id' => $leadId,
                'nom_concurrent' => sanitize_text_field((string) $competitor['name']),
                'extrait_contexte' => sanitize_textarea_field((string) ($competitor['context'] ?? '')),
                'created_at' => current_time('mysql'),
            ]);
        }

        // L'événement signal_update relance scoring + triggers (urgence, prix…).
        (new EventRepository())->record($leadId, 'signal_update', [
            'intent_level' => $intent,
            'urgency' => $signals['urgency'],
            'price_sensitivity' => $signals['price_sensitivity'],
            'competitors' => array_values(array_filter(array_map(fn($c) => $c['name'] ?? null, (array) ($result['competitors'] ?? [])))),
        ], 'system');
    }
}

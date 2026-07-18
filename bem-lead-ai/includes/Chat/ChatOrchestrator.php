<?php

namespace BemLeadAi\Chat;

use BemLeadAi\Ai\ClaudeClient;
use BemLeadAi\Ai\EmbeddingsClient;
use BemLeadAi\Core\Options;
use BemLeadAi\Core\Queue;
use BemLeadAi\Leads\EventRepository;
use BemLeadAi\Rag\QdrantClient;

defined('ABSPATH') || exit;

/**
 * Cœur conversationnel : RAG (réponses ancrées dans le contenu réel des
 * formations, pas de réponses inventées) + gestion du handoff humain.
 *
 * Même orchestrateur pour le web et WhatsApp — le canal n'est qu'un
 * paramètre. La classification multi-signaux part en asynchrone pour ne
 * pas dégrader la latence perçue (< 3 s visés).
 */
final class ChatOrchestrator
{
    /**
     * @return array{reply: ?string, handoff: bool, message_id: int}
     */
    public function handleMessage(object $lead, string $message, string $canal = 'web'): array
    {
        $conversations = new ConversationRepository();
        $conversations->add((int) $lead->id, 'user', $message, $canal);
        (new EventRepository())->record((int) $lead->id, 'chat_message', ['length' => mb_strlen($message)], $canal);

        // Classification multi-signaux en asynchrone (jamais dans le fil).
        Queue::dispatch('bem_lead_ai_job_classify', [(int) $lead->id]);

        // Conversation prise en main par un humain : l'IA reste en pause.
        if ((int) $lead->handoff_active === 1) {
            return ['reply' => null, 'handoff' => true, 'message_id' => 0];
        }

        $reply = $this->generateReply($lead, $message, $canal);
        $messageId = $conversations->add((int) $lead->id, 'assistant', $reply, $canal);

        return ['reply' => $reply, 'handoff' => false, 'message_id' => $messageId];
    }

    private function generateReply(object $lead, string $message, string $canal): string
    {
        $fallback = __("Je rencontre un souci technique momentané. Laissez-moi votre question et votre email : un conseiller de BEM Dakar vous répondra très vite.", 'bem-lead-ai');

        $context = $this->retrieveContext($lead, $message);

        $history = (new ConversationRepository())->history((int) $lead->id, 16);
        $messages = [];
        foreach ($history as $m) {
            if (!in_array($m->role, ['user', 'assistant'], true)) {
                continue;
            }
            $messages[] = ['role' => $m->role, 'content' => $m->contenu];
        }
        // Compacte les tours successifs de même rôle (exigence API Messages).
        $messages = $this->normalizeTurns($messages);
        if (!$messages || end($messages)['role'] !== 'user') {
            $messages[] = ['role' => 'user', 'content' => $message];
        }

        $reply = (new ClaudeClient())->complete(
            (string) Options::get('chat_model'),
            $this->systemPrompt($lead, $context, $canal),
            $messages,
            $canal === 'whatsapp' ? 500 : 800,
            0.5
        );

        return is_wp_error($reply) || trim((string) $reply) === '' ? $fallback : trim($reply);
    }

    private function retrieveContext(object $lead, string $query): string
    {
        $vector = (new EmbeddingsClient())->embedOne($query, 'query');
        if (is_wp_error($vector)) {
            return '';
        }
        $kb = $lead->kb_mode === 'onboarding' ? 'onboarding' : 'formations';
        $hits = (new QdrantClient())->search($vector, $kb, 5);
        $blocks = [];
        foreach ($hits as $hit) {
            $payload = $hit['payload'] ?? [];
            if (!empty($payload['text'])) {
                $blocks[] = sprintf("[Source: %s — %s]\n%s", $payload['title'] ?? '', $payload['url'] ?? '', $payload['text']);
            }
        }
        return implode("\n\n", $blocks);
    }

    private function systemPrompt(object $lead, string $context, string $canal): string
    {
        $isOnboarding = $lead->kb_mode === 'onboarding';

        $persona = $isOnboarding
            ? "Tu es l'assistant d'accueil de BEM Dakar. Ton interlocuteur est un étudiant INSCRIT : tu l'accompagnes dans ses démarches administratives d'onboarding (documents, inscription pédagogique, rentrée, vie de campus)."
            : "Tu es le conseiller d'orientation virtuel de BEM Dakar (école de management à Dakar, Sénégal). Ton interlocuteur est un prospect qui s'informe sur les formations. Ton rôle : comprendre son projet, répondre précisément, et faire progresser naturellement son parcours vers la candidature.";

        $rules = "Règles impératives :\n"
            . "- Réponds UNIQUEMENT à partir des extraits de contenu fournis ci-dessous. Si l'information n'y figure pas, dis-le honnêtement et propose de mettre le prospect en contact avec l'équipe admissions — n'invente JAMAIS de frais, de dates ou de conditions d'admission.\n"
            . "- Français naturel et chaleureux, vouvoiement, phrases courtes. " . ($canal === 'whatsapp' ? "Format WhatsApp : messages brefs, pas de Markdown complexe.\n" : "\n")
            . ($isOnboarding ? '' :
              "- Conduite conversationnelle (conseiller, pas robot FAQ) : une seule question de relance pertinente à la fois pour qualifier le projet (formation visée, niveau actuel, échéance). Quand l'intérêt est manifeste, propose l'étape suivante concrète : candidature en ligne, brochure, ou échange avec un conseiller admissions.\n"
            . "- Si le prospect s'inquiète du coût, mentionne qu'il existe des facilités de paiement et des bourses, et propose d'en parler.\n"
            . "- Ne donne jamais ton avis sur les écoles concurrentes ; recentre sur les forces de BEM Dakar (accréditations, insertion professionnelle, réseau).\n");

        $profile = '';
        if (!empty($lead->prenom)) {
            $profile .= "Prénom du contact : {$lead->prenom}. ";
        }
        if (!empty($lead->formation_interet)) {
            $profile .= "Formation d'intérêt détectée : {$lead->formation_interet}.";
        }

        return $persona . "\n\n" . $rules
            . ($profile ? "\nContexte du contact : " . $profile . "\n" : '')
            . "\n=== EXTRAITS DU CONTENU OFFICIEL ===\n"
            . ($context !== '' ? $context : "(aucun extrait pertinent trouvé — réponds prudemment et propose le contact humain)")
            . "\n=== FIN DES EXTRAITS ===";
    }

    /** Fusionne les tours consécutifs de même rôle et garantit l'alternance. */
    private function normalizeTurns(array $messages): array
    {
        $out = [];
        foreach ($messages as $m) {
            $last = $out ? count($out) - 1 : -1;
            if ($last >= 0 && $out[$last]['role'] === $m['role']) {
                $out[$last]['content'] .= "\n" . $m['content'];
            } else {
                $out[] = $m;
            }
        }
        // La conversation doit commencer par un tour utilisateur.
        while ($out && $out[0]['role'] !== 'user') {
            array_shift($out);
        }
        return $out;
    }
}

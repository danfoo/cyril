<?php

namespace BemLeadAi\Chat;

use BemLeadAi\Ai\ClaudeClient;
use BemLeadAi\Channels\WhatsAppHandoff;
use BemLeadAi\Core\Options;
use BemLeadAi\Core\Queue;
use BemLeadAi\Knowledge\KnowledgeBaseBuilder;
use BemLeadAi\Leads\EventRepository;

defined('ABSPATH') || exit;

/**
 * Cœur conversationnel : le conseiller IA répond à partir du contenu réel des
 * formations (catalogue complet injecté dans le prompt et mis en cache côté
 * LLM), jamais de réponses inventées. Gère aussi la mise en pause de l'IA
 * pendant une prise en main humaine.
 *
 * La classification multi-signaux part en asynchrone pour ne pas dégrader la
 * latence perçue (< 3 s visés).
 */
final class ChatOrchestrator
{
    /**
     * @return array{reply: ?string, handoff: bool, message_id: int, whatsapp: ?array}
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
            return ['reply' => null, 'handoff' => true, 'message_id' => 0, 'whatsapp' => null];
        }

        $reply = $this->generateReply($lead, $message);
        $messageId = $conversations->add((int) $lead->id, 'assistant', $reply, $canal);

        return [
            'reply' => $reply,
            'handoff' => false,
            'message_id' => $messageId,
            'whatsapp' => $this->maybeWhatsApp($lead),
        ];
    }

    private function generateReply(object $lead, string $message): string
    {
        $fallback = __("Je rencontre un souci technique momentané. Laissez-moi votre question et votre email : un conseiller de BEM Dakar vous répondra très vite.", 'bem-lead-ai');

        $history = (new ConversationRepository())->history((int) $lead->id, 16);
        $messages = [];
        foreach ($history as $m) {
            if (!in_array($m->role, ['user', 'assistant'], true)) {
                continue;
            }
            $messages[] = ['role' => $m->role, 'content' => $m->contenu];
        }
        $messages = $this->normalizeTurns($messages);
        if (!$messages || end($messages)['role'] !== 'user') {
            $messages[] = ['role' => 'user', 'content' => $message];
        }

        $reply = (new ClaudeClient())->complete(
            (string) Options::get('chat_model'),
            $this->systemBlocks($lead),
            $messages,
            800
        );

        if (is_wp_error($reply)) {
            error_log('[bem-lead-ai] Échec réponse chat : ' . $reply->get_error_message());
            return $fallback;
        }
        return trim((string) $reply) === '' ? $fallback : trim($reply);
    }

    /**
     * Deux blocs system :
     *  1. Catalogue + règles (figé par base de connaissance) → mis en cache LLM.
     *  2. Contexte du contact (prénom, formation) → volatil, hors cache.
     */
    private function systemBlocks(object $lead): array
    {
        $kb = $lead->kb_mode === 'onboarding' ? 'onboarding' : 'formations';
        $catalogue = (new KnowledgeBaseBuilder())->block($kb);

        $ttl = Options::get('kb_cache_ttl') === '5m' ? '5m' : '1h';

        $blocks = [[
            'type' => 'text',
            'text' => $this->cachedSystem($kb, $catalogue),
            'cache_control' => ['type' => 'ephemeral', 'ttl' => $ttl],
        ]];

        $profile = $this->volatileSystem($lead);
        if ($profile !== '') {
            $blocks[] = ['type' => 'text', 'text' => $profile];
        }
        return $blocks;
    }

    private function cachedSystem(string $kb, string $catalogue): string
    {
        $isOnboarding = $kb === 'onboarding';

        $persona = $isOnboarding
            ? "Tu es l'assistant d'accueil de BEM Dakar. Ton interlocuteur est un étudiant INSCRIT : tu l'accompagnes dans ses démarches administratives d'onboarding (documents, inscription pédagogique, rentrée, vie de campus)."
            : "Tu es le conseiller d'orientation virtuel de BEM Dakar (école de management à Dakar, Sénégal). Ton interlocuteur est un prospect qui s'informe sur les formations. Ton rôle : comprendre son projet, répondre précisément, et faire progresser naturellement son parcours vers la candidature.";

        $rules = "Règles impératives :\n"
            . "- Réponds UNIQUEMENT à partir du contenu officiel fourni ci-dessous. Si l'information n'y figure pas, dis-le honnêtement et propose de mettre le prospect en contact avec l'équipe admissions — n'invente JAMAIS de frais, de dates ou de conditions d'admission.\n"
            . ($isOnboarding ? '' :
              "- PARTAGE DES LIENS (important) : chaque programme du contenu ci-dessous a un « Lien officiel ». Quand tu présentes ou recommandes un programme, ajoute TOUJOURS son lien officiel au format Markdown cliquable, ex. [Master Finance](https://…). Quand le prospect veut candidater ou en savoir plus, partage aussi le lien de la page de candidature/admission correspondante. N'invente jamais d'URL : n'utilise que celles présentes ci-dessous.\n")
            . "- Français naturel et chaleureux, vouvoiement, phrases courtes.\n"
            . ($isOnboarding ? '' :
              "- Conduite conversationnelle (conseiller, pas robot FAQ) : une seule question de relance pertinente à la fois pour qualifier le projet (formation visée, niveau actuel, échéance). Quand l'intérêt est manifeste, propose l'étape suivante concrète : candidature en ligne, brochure, ou échange avec un conseiller.\n"
            . "- COLLECTE DU PRÉNOM : dès les tout premiers échanges, demande naturellement le prénom du prospect pour personnaliser l'accompagnement (ex. « Avec plaisir ! Au fait, comment vous appelez-vous ? »). Une fois obtenu, utilise-le de temps en temps.\n"
            . "- COLLECTE DES COORDONNÉES : à mesure que l'intérêt se confirme (le prospect pose des questions précises, parle de candidature ou de délais), propose de recueillir son email et/ou son numéro de téléphone — présenté comme un service (« Voulez-vous que je vous envoie la brochure / que l'équipe admissions vous rappelle ? Laissez-moi votre email ou téléphone »). Reste subtil et jamais insistant ; une seule demande à la fois, au bon moment.\n"
            . "- Si le prospect s'inquiète du coût, mentionne qu'il existe des facilités de paiement et des bourses, et propose d'en parler.\n"
            . "- Ne donne jamais ton avis sur les écoles concurrentes ; recentre sur les forces de BEM Dakar (accréditations, insertion professionnelle, réseau).\n"
            . "- Si le prospect souhaite parler à un conseiller humain, ou hésite sur une décision importante, invite-le à utiliser le bouton « WhatsApp » sous la conversation pour échanger de vive voix avec l'équipe admissions.\n");

        return $persona . "\n\n" . $rules
            . $this->programLinksSection($isOnboarding)
            . "\n=== CONTENU OFFICIEL BEM DAKAR ===\n"
            . ($catalogue !== '' ? $catalogue : "(catalogue non encore indexé — réponds prudemment et propose le contact humain)")
            . "\n=== FIN DU CONTENU OFFICIEL ===";
    }

    /** Liens officiels des programmes, que le conseiller doit partager. */
    private function programLinksSection(bool $isOnboarding): string
    {
        if ($isOnboarding) {
            return '';
        }
        $links = Options::programLinks();
        if (!$links) {
            return '';
        }
        $lines = ["\n=== LIENS OFFICIELS DES PROGRAMMES ==="];
        foreach ($links as $l) {
            $lines[] = '- ' . $l['label'] . ' : ' . $l['url'];
        }
        $lines[] = "Quand tu présentes ou recommandes un programme figurant ci-dessus, partage son lien officiel exact (format Markdown [nom](url)). N'invente jamais d'URL.";
        $lines[] = "=== FIN DES LIENS ===\n";
        return implode("\n", $lines);
    }

    private function volatileSystem(object $lead): string
    {
        $profile = '';
        if (!empty($lead->prenom)) {
            $profile .= "Prénom du contact : {$lead->prenom}. ";
        }
        if (!empty($lead->formation_interet)) {
            $profile .= "Formation d'intérêt détectée : {$lead->formation_interet}.";
        }
        return $profile !== '' ? "Contexte du contact : " . $profile : '';
    }

    /** Lien WhatsApp à proposer au widget (si activé). */
    private function maybeWhatsApp(object $lead): ?array
    {
        $handoff = new WhatsAppHandoff();
        return $handoff->isEnabled() ? $handoff->buildLink($lead) : null;
    }

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
        while ($out && $out[0]['role'] !== 'user') {
            array_shift($out);
        }
        return $out;
    }
}

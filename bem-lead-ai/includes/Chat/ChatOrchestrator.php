<?php

namespace BemLeadAi\Chat;

use BemLeadAi\Ai\ClaudeClient;
use BemLeadAi\Channels\WhatsAppHandoff;
use BemLeadAi\Core\Options;
use BemLeadAi\Core\Queue;
use BemLeadAi\Crm\PerfexBridgeConnector;
use BemLeadAi\Knowledge\KnowledgeBaseBuilder;
use BemLeadAi\Leads\EventRepository;
use BemLeadAi\Leads\LeadRepository;

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
        $userMessageId = $conversations->add((int) $lead->id, 'user', $message, $canal);
        (new EventRepository())->record((int) $lead->id, 'chat_message', ['length' => mb_strlen($message)], $canal);
        $this->syncMessageToCrm((int) $lead->id, $userMessageId, 'user', $message, $canal);

        // Capture déterministe et immédiate : si le message cite mot pour mot
        // un programme configuré (côté Perfex), pas besoin d'attendre la
        // classification IA (asynchrone, dépendante de Claude + WP-Cron).
        $this->captureFormationKeyword($lead, $message);

        // Classification multi-signaux en asynchrone (jamais dans le fil) —
        // complémentaire : couvre les formulations qui ne citent pas le
        // libellé exact (« je pense entrer en sixième » plutôt que « 6e »).
        Queue::dispatch('bem_lead_ai_job_classify', [(int) $lead->id]);

        // Conversation prise en main par un humain : l'IA reste en pause.
        if ((int) $lead->handoff_active === 1) {
            return ['reply' => null, 'handoff' => true, 'message_id' => 0, 'whatsapp' => null];
        }

        $reply = $this->generateReply($lead, $message);
        $messageId = $conversations->add((int) $lead->id, 'assistant', $reply, $canal);
        $this->syncMessageToCrm((int) $lead->id, $messageId, 'assistant', $reply, $canal);

        return [
            'reply' => $reply,
            'handoff' => false,
            'message_id' => $messageId,
            'whatsapp' => $this->maybeWhatsApp($lead),
        ];
    }

    /**
     * Filet de sécurité synchrone : si le message cite mot pour mot un
     * programme configuré côté Perfex, on l'attribue tout de suite au lead —
     * sans attendre le classificateur IA. Ne remplace jamais une formation
     * déjà connue (l'IA reste la source la plus fine pour les reformulations).
     */
    private function captureFormationKeyword(object $lead, string $message): void
    {
        if (!empty($lead->formation_interet)) {
            return;
        }
        $programs = (new PerfexBridgeConnector())->fetchProgramList();
        if (!$programs) {
            return;
        }
        $norm = $this->normalizeForKeywordMatch($message);
        foreach ($programs as $program) {
            if ($program !== '' && str_contains($norm, $this->normalizeForKeywordMatch($program))) {
                (new LeadRepository())->update((int) $lead->id, ['formation_interet' => $program]);
                return;
            }
        }
    }

    /**
     * Minuscules, sans accents ni exposants d'ordinaux (« 6ᵉ » → « 6e »), sans
     * ponctuation — même logique que fee_norm() côté Perfex (School_ia_bridge_model),
     * pour que ce qui matche ici matche aussi côté valorisation du pipeline.
     */
    private function normalizeForKeywordMatch(string $s): string
    {
        $s = function_exists('mb_strtolower') ? mb_strtolower(trim($s), 'UTF-8') : strtolower(trim($s));
        $map = [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
            'ᵉ' => 'e', 'ʳ' => 'r', 'ᵈ' => 'd', 'ᵒ' => 'o', 'ⁿ' => 'n', 'ᵗ' => 't', 'ᵉʳ' => 'er',
        ];
        $s = strtr($s, $map);
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
        return trim(preg_replace('/\s+/', ' ', (string) $s));
    }

    /** Envoi CRM du message hors du flux de chat (file asynchrone, aucun impact sur la latence perçue). */
    private function syncMessageToCrm(int $leadId, int $messageId, string $role, string $content, string $canal): void
    {
        Queue::dispatch('bem_lead_ai_job_action', ['sync_chat_message', $leadId, [
            'message_id' => $messageId,
            'role'       => $role,
            'content'    => $content,
            'canal'      => $canal,
        ]]);
    }

    private function generateReply(object $lead, string $message): string
    {
        $school = trim((string) Options::get('school_name')) ?: 'BEM Conakry';
        $fallback = sprintf(
            /* translators: %s = nom de l'école */
            __("Je rencontre un souci technique momentané. Laissez-moi votre question et votre email : un conseiller de %s vous répondra très vite.", 'bem-lead-ai'),
            $school
        );

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
            1400 // marge suffisante pour ne pas couper les réponses détaillées en plein milieu
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

        $school = trim((string) Options::get('school_name')) ?: 'BEM Conakry';
        $location = trim((string) Options::get('school_location'));
        $locationSuffix = $location !== '' ? " (école de management à {$location})" : '';

        $persona = $isOnboarding
            ? "Tu es l'assistant d'accueil de {$school}. Ton interlocuteur est un étudiant INSCRIT : tu l'accompagnes dans ses démarches administratives d'onboarding (documents, inscription pédagogique, rentrée, vie de campus)."
            : "Tu es le conseiller d'orientation virtuel de {$school}{$locationSuffix}. Ton interlocuteur est un prospect qui s'informe sur les formations. Ton rôle : comprendre son projet, répondre précisément, et faire progresser naturellement son parcours vers la candidature. IMPORTANT : tu représentes {$school} et uniquement {$school} — n'emploie jamais un autre nom de ville ou de campus.";

        $rules = "Règles impératives :\n"
            . "- Réponds UNIQUEMENT à partir du contenu officiel fourni ci-dessous. Si l'information n'y figure pas, dis-le honnêtement et propose de mettre le prospect en contact avec l'équipe admissions — n'invente JAMAIS de frais, de dates ou de conditions d'admission. (Exception : pour les FRAIS DE SCOLARITÉ, applique la règle dédiée plus bas — ne formule jamais cela comme un manque d'information.)\n"
            . ($isOnboarding ? '' :
              "- PARTAGE DES LIENS (important) : chaque programme du contenu ci-dessous a un « Lien officiel ». Quand tu présentes ou recommandes un programme, ajoute TOUJOURS son lien officiel au format Markdown cliquable, ex. [Master Finance](https://…). Quand le prospect veut candidater ou en savoir plus, partage aussi le lien de la page de candidature/admission correspondante. N'invente jamais d'URL : n'utilise que celles présentes ci-dessous.\n")
            . "- Français naturel et chaleureux, vouvoiement, phrases courtes.\n"
            . ($isOnboarding ? '' :
              "- Conduite conversationnelle (conseiller, pas robot FAQ) : une seule question de relance pertinente à la fois pour qualifier le projet (formation visée, niveau actuel, échéance). Quand l'intérêt est manifeste, propose l'étape suivante concrète : candidature en ligne, brochure, ou échange avec un conseiller.\n"
            . "- COLLECTE DU PRÉNOM : dès les tout premiers échanges, demande naturellement le prénom du prospect pour personnaliser l'accompagnement (ex. « Avec plaisir ! Au fait, comment vous appelez-vous ? »). Une fois obtenu, utilise-le de temps en temps.\n"
            . "- COLLECTE DES COORDONNÉES : à mesure que l'intérêt se confirme (le prospect pose des questions précises, parle de candidature ou de délais), propose de recueillir son email et/ou son numéro de téléphone — présenté comme un service (« Voulez-vous que je vous envoie la brochure / que l'équipe admissions vous rappelle ? Laissez-moi votre email ou téléphone »). Reste subtil et jamais insistant ; une seule demande à la fois, au bon moment.\n"
            . "- FRAIS DE SCOLARITÉ — approche diplomatique et subtile (important) : ne donne JAMAIS de montant, et ne dis JAMAIS que tu « n'as pas l'information », que tu « ne disposes pas du détail » ou un équivalent — cela donne l'impression d'esquiver. Présente-le au contraire comme une étape NORMALE et PERSONNALISÉE du parcours : les frais sont établis au cas par cas lors de l'entretien d'admission avec un conseiller, en fonction du programme retenu et du profil du candidat — c'est précisément l'un des objectifs de cet échange. Enchaîne positivement : rappelle brièvement qu'il existe des facilités de paiement et des bourses d'excellence, puis propose de convenir d'un échange avec l'équipe admissions (en laissant email/téléphone, ou via WhatsApp) pour finaliser les modalités. Ton chaleureux et rassurant, jamais celui d'un robot qui bute sur une donnée manquante.\n"
            . "- Ne donne jamais ton avis sur les écoles concurrentes ; recentre sur les forces de {$school} (accréditations, insertion professionnelle, réseau).\n"
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

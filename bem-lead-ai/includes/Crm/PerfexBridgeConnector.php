<?php

namespace BemLeadAi\Crm;

use BemLeadAi\Core\Options;
use BemLeadAi\Scoring\ScoringEngine;

defined('ABSPATH') || exit;

/**
 * Connecteur « Pont School IA » — envoie le lead au module Perfex maison
 * (school_ia_bridge) via son point d'entrée public, authentifié par un secret
 * partagé. Aucune dépendance au module REST API payant de Perfex.
 */
final class PerfexBridgeConnector implements CrmConnectorInterface
{
    /** Résultat du dernier envoi (diagnostic). */
    public int $lastCode = 0;
    public string $lastError = '';

    private const PROGRAM_LIST_TRANSIENT = 'bem_perfex_program_list';

    /** base64url (sans +, /, = qui déclenchent parfois un pare-feu applicatif). */
    private static function b64urlEncode(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    public function isConfigured(): bool
    {
        return Options::get('perfex_url') !== '' && Options::hasSecret('perfex_bridge_secret');
    }

    public function upsertLead(object $lead): void
    {
        $base = rtrim((string) Options::get('perfex_url'), '/');
        $band = ScoringEngine::band((float) $lead->score_final);

        $prenom = trim((string) ($lead->prenom ?? ''));
        $payload = [
            'external_id' => (string) $lead->id,
            'source_site' => home_url(),
            // Nom réel du prospect si connu, sinon « Anonyme » — jamais « Lead »,
            // qui prêtait à confusion avec d'anciens leads de test.
            'name'        => ($prenom !== '' ? $prenom : 'Anonyme') . ' #' . $lead->id,
            'email'       => (string) ($lead->email ?? ''),
            'phone'       => (string) ($lead->phone ?? ''),
            'formation'   => (string) ($lead->formation_interet ?? ''),
            'source_form' => (string) ($lead->source_form ?? ''),
            'utm_source'   => (string) ($lead->utm_source ?? ''),
            'utm_medium'   => (string) ($lead->utm_medium ?? ''),
            'utm_campaign' => (string) ($lead->utm_campaign ?? ''),
            'score'       => (float) $lead->score_final,
            'band'        => $band,
            // Vraie date d'arrivée du lead (première visite), pour que Perfex
            // affiche « reçu le » à la bonne date et non à l'heure de synchro.
            'received_at' => (string) ($lead->first_seen ?? ''),
            'last_activity' => (string) ($lead->last_seen ?? ''),
            'description' => sprintf(
                "Score: %s/100 (%s)\nFormation d'intérêt: %s\nFormulaire d'origine: %s\nCanaux: %s\nDernière activité: %s\nProvenance: %s",
                $lead->score_final,
                str_replace('_', ' ', $band),
                $lead->formation_interet ?: '—',
                trim((string) ($lead->source_form ?? '')) !== '' ? $lead->source_form : '—',
                $lead->channels,
                $lead->last_seen,
                defined('BEM_LEAD_AI_BRAND') ? BEM_LEAD_AI_BRAND : 'School IA'
            ),
        ];

        // Envoi en GET : la protection CSRF de Perfex ne bloque que le POST
        // (erreur 419). Le secret voyage dans l'en-tête ET en paramètre (repli
        // si l'hébergeur filtre les en-têtes personnalisés).
        $secret = (string) Options::get('perfex_bridge_secret');
        // http_build_query encode correctement clés ET valeurs (retours à la
        // ligne, accents…), ce que add_query_arg ne fait pas → URL valide.
        $query = http_build_query(array_merge($payload, ['secret' => $secret]));
        $url = $base . '/school_ia_bridge/api/receive?' . $query;

        $response = wp_remote_get($url, [
            'timeout' => 30,
            'headers' => [
                'X-SIA-Secret' => $secret,
                'Accept'       => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            $this->lastCode = 0;
            $this->lastError = $response->get_error_message();
            error_log('[bem-lead-ai] Pont Perfex injoignable: ' . $this->lastError);
            return;
        }

        $this->lastCode = (int) wp_remote_retrieve_response_code($response);
        if ($this->lastCode < 200 || $this->lastCode >= 300) {
            $this->lastError = wp_strip_all_tags((string) wp_remote_retrieve_body($response));
            error_log('[bem-lead-ai] Pont Perfex a répondu ' . $this->lastCode . ': ' . $this->lastError);
        } else {
            $this->lastError = '';
        }
    }

    /**
     * Liste des programmes configurés côté Perfex (réglages → Programmes),
     * mise en cache 1h — appelée à chaque classification IA, pas question de
     * faire un aller-retour réseau à chaque message. Contraint le
     * classificateur à un libellé EXACT plutôt que du texte libre, pour que
     * resolve_fee() (côté Perfex) retrouve toujours le tarif correspondant.
     * Retourne [] si le pont n'est pas configuré ou en cas d'échec réseau —
     * le classificateur retombe alors sur du texte libre.
     *
     * @return string[]
     */
    public function fetchProgramList(): array
    {
        if (!$this->isConfigured()) {
            return [];
        }
        $cached = get_transient(self::PROGRAM_LIST_TRANSIENT);
        if (is_array($cached)) {
            return $cached;
        }

        $base = rtrim((string) Options::get('perfex_url'), '/');
        $secret = (string) Options::get('perfex_bridge_secret');
        $url = $base . '/school_ia_bridge/api/programs?' . http_build_query(['secret' => $secret]);

        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => [
                'X-SIA-Secret' => $secret,
                'Accept'       => 'application/json',
            ],
        ]);

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            // Échec réseau : cache court pour ne pas marteler Perfex à chaque
            // classification tant que le problème n'est pas résolu.
            set_transient(self::PROGRAM_LIST_TRANSIENT, [], 5 * MINUTE_IN_SECONDS);
            return [];
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        $programs = is_array($body['programs'] ?? null)
            ? array_values(array_filter(array_map('strval', $body['programs'])))
            : [];

        set_transient(self::PROGRAM_LIST_TRANSIENT, $programs, HOUR_IN_SECONDS);
        return $programs;
    }

    /**
     * Envoie un message de la conversation IA vers la fiche lead Perfex.
     * Le lead doit déjà exister côté Perfex (envoyé par upsertLead au préalable) ;
     * il est retrouvé par external_id + source_site.
     *
     * Le contenu est tronqué : le transport se fait en GET (la protection CSRF
     * de Perfex ne bloque que le POST), et une réponse IA très longue ferait
     * dépasser la longueur d'URL acceptée par certains hébergeurs.
     */
    public function sendChatMessage(int $leadId, int $messageId, string $role, string $content, string $canal = 'web'): void
    {
        $base = rtrim((string) Options::get('perfex_url'), '/');
        $secret = (string) Options::get('perfex_bridge_secret');

        // Compression pour tenir dans l'URL (GET) : une réponse IA longue, une
        // fois url-encodée (accents, markdown → %XX), dépassait la longueur d'URL
        // acceptée et le message était perdu côté Perfex. Le codec gzip + base64url
        // (uniquement [A-Za-z0-9-_], jamais bloqué par un pare-feu) est décodé par
        // le module Perfex (school_ia_bridge → unpack_content).
        $encoded = PayloadCodec::encode($content);

        $payload = [
            'external_id'         => (string) $leadId,
            'source_site'         => home_url(),
            'external_message_id' => (string) $messageId,
            'role'                => $role,
            'content'             => $encoded,
            'canal'               => $canal,
        ];
        $query = http_build_query(array_merge($payload, ['secret' => $secret]));
        $url = $base . '/school_ia_bridge/api/receive_message?' . $query;

        $response = wp_remote_get($url, [
            'timeout' => 20,
            'headers' => [
                'X-SIA-Secret' => $secret,
                'Accept'       => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            $this->lastCode = 0;
            $this->lastError = $response->get_error_message();
            error_log('[bem-lead-ai] Pont Perfex (message) injoignable: ' . $this->lastError);
            return;
        }

        $this->lastCode = (int) wp_remote_retrieve_response_code($response);
        if ($this->lastCode < 200 || $this->lastCode >= 300) {
            $this->lastError = wp_strip_all_tags((string) wp_remote_retrieve_body($response));
            error_log('[bem-lead-ai] Pont Perfex (message) a répondu ' . $this->lastCode . ': ' . $this->lastError);
        } else {
            $this->lastError = '';
        }
    }

    /**
     * Répercute l'état de la prise en main humaine (escalade IA → conseiller,
     * ou clôture) vers la fiche lead Perfex, pour que l'inbox du module y
     * affiche les conversations à prendre en charge et notifie les conseillers.
     */
    public function syncHandoffStatus(int $leadId, bool $active, string $motif = ''): void
    {
        $base = rtrim((string) Options::get('perfex_url'), '/');
        $secret = (string) Options::get('perfex_bridge_secret');

        $payload = [
            'external_id' => (string) $leadId,
            'source_site' => home_url(),
            'active'      => $active ? '1' : '0',
            'motif'       => $motif,
        ];
        $query = http_build_query(array_merge($payload, ['secret' => $secret]));
        $url = $base . '/school_ia_bridge/api/receive_handoff?' . $query;

        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'X-SIA-Secret' => $secret,
                'Accept'       => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            $this->lastCode = 0;
            $this->lastError = $response->get_error_message();
            error_log('[bem-lead-ai] Pont Perfex (handoff) injoignable: ' . $this->lastError);
            return;
        }

        $this->lastCode = (int) wp_remote_retrieve_response_code($response);
        if ($this->lastCode < 200 || $this->lastCode >= 300) {
            $this->lastError = wp_strip_all_tags((string) wp_remote_retrieve_body($response));
            error_log('[bem-lead-ai] Pont Perfex (handoff) a répondu ' . $this->lastCode . ': ' . $this->lastError);
        } else {
            $this->lastError = '';
        }
    }

    /**
     * Envoie une mention de concurrent (veille concurrentielle) vers la fiche
     * lead Perfex. Le lead doit déjà exister côté Perfex.
     */
    public function sendCompetitorMention(int $leadId, int $mentionId, string $name, string $context = ''): void
    {
        $base = rtrim((string) Options::get('perfex_url'), '/');
        $secret = (string) Options::get('perfex_bridge_secret');

        // Requête STRICTEMENT identique à un vrai message de chat (qui passe le
        // pare-feu) : mêmes paramètres, canal=web. La mention est cachée dans le
        // contenu derrière un préfixe « SIACMP1: » que Perfex reconnaît. Aucun
        // paramètre spécial (name/context/kind/canal) que le pare-feu bloquait.
        $data = 'SIACMP1:' . self::b64urlEncode(wp_json_encode([
            'n' => $name,
            'c' => mb_substr($context, 0, 1500),
        ]));
        $payload = [
            'external_id'         => (string) $leadId,
            'source_site'         => home_url(),
            'role'                => 'assistant',
            'canal'               => 'web',
            'content'             => $data,
            'external_message_id' => 'c' . $mentionId,
        ];
        $query = http_build_query(array_merge($payload, ['secret' => $secret]));
        $url = $base . '/school_ia_bridge/api/receive_message?' . $query;

        $response = wp_remote_get($url, [
            'timeout' => 20,
            'headers' => [
                'X-SIA-Secret' => $secret,
                'Accept'       => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            $this->lastCode = 0;
            $this->lastError = $response->get_error_message();
            error_log('[bem-lead-ai] Pont Perfex (concurrent) injoignable: ' . $this->lastError);
            return;
        }

        $this->lastCode = (int) wp_remote_retrieve_response_code($response);
        if ($this->lastCode < 200 || $this->lastCode >= 300) {
            $this->lastError = wp_strip_all_tags((string) wp_remote_retrieve_body($response));
            error_log('[bem-lead-ai] Pont Perfex (concurrent) a répondu ' . $this->lastCode . ': ' . $this->lastError);
        } else {
            $this->lastError = '';
        }
    }
}

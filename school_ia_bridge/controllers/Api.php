<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Point d'entrée public appelé par le plugin WordPress School IA.
 * URL : {perfex}/school_ia_bridge/api/receive
 *
 * Authentification : en-tête « X-SIA-Secret », ou paramètre « secret »
 * (GET ou POST), comparé au secret partagé stocké dans les options.
 *
 * Accepte GET comme POST : la protection CSRF de Perfex ne s'applique qu'au
 * POST, donc le plugin envoie en GET pour ne jamais être bloqué (erreur 419).
 */
class Api extends App_Controller
{
    public function __construct()
    {
        parent::__construct();
        // Garantit que les tables du CRM (messages de chat, concurrents…)
        // existent AVANT toute insertion : le plugin peut appeler ces points
        // d'entrée sans qu'un admin ait ouvert les pages du module, or c'est
        // seulement là que le schéma était créé. Sans ça, les insertions
        // échouaient en silence tout en renvoyant « ok » (2xx).
        $this->load->model('school_ia_bridge/school_ia_bridge_model');
        $this->school_ia_bridge_model->ensure_schema();
    }

    public function receive()
    {
        header('Content-Type: application/json; charset=utf-8');

        $secret   = (string) get_option('school_ia_bridge_secret');
        $provided = $this->input->get_request_header('X-SIA-Secret', true);
        if ($provided === null || $provided === '') {
            // Repli : paramètre « secret » en GET ou POST.
            $provided = (string) ($this->input->get('secret') ?: $this->input->post('secret'));
        }

        if ($secret === '' || !hash_equals($secret, (string) $provided)) {
            $this->respond(['ok' => false, 'error' => 'unauthorized'], 401);
            return;
        }

        // Données : JSON brut prioritaire, puis fusion des paramètres GET + POST.
        $raw  = file_get_contents('php://input');
        $body = json_decode((string) $raw, true);
        if (!is_array($body)) {
            $get  = (array) $this->input->get(null);
            $post = (array) $this->input->post(null);
            $body = array_merge($get, $post);
            unset($body['secret']); // ne pas stocker le secret dans le lead
        }
        if (!is_array($body) || $body === []) {
            $this->respond(['ok' => false, 'error' => 'invalid_payload'], 400);
            return;
        }

        $this->load->model('school_ia_bridge/school_ia_bridge_model');

        // Filet de sécurité : si un message de conversation atterrit ici par
        // erreur (payload role+content sans données de lead), on le range comme
        // message au lieu de créer une fiche vide qui écraserait le vrai lead.
        $looksLikeChat = !empty($body['content'])
            && in_array((string) ($body['role'] ?? ''), ['user', 'assistant'], true)
            && empty($body['name']) && empty($body['email']) && empty($body['phone']) && !isset($body['score']);
        if ($looksLikeChat) {
            $lead = $this->school_ia_bridge_model->find_by_external(
                (string) ($body['external_id'] ?? ''),
                (string) ($body['source_site'] ?? '')
            );
            if (!$lead) {
                $this->respond(['ok' => false, 'error' => 'lead_not_found'], 404);
                return;
            }
            $this->school_ia_bridge_model->add_chat_message(
                (int) $lead->id,
                (string) $body['role'],
                (string) $body['content'],
                (string) ($body['canal'] ?? 'web'),
                !empty($body['external_message_id']) ? (string) $body['external_message_id'] : null
            );
            $this->respond(['ok' => true, 'chat' => true]);
            return;
        }

        $id = $this->school_ia_bridge_model->save_lead($body);
        if ($id === 0) {
            $this->respond(['ok' => false, 'error' => 'empty_lead_ignored'], 422);
            return;
        }

        $this->respond(['ok' => true, 'id' => $id]);
    }

    /**
     * Reçoit un message de la conversation IA (widget WordPress) pour un lead
     * déjà connu (identifié par external_id + source_site). Même authentification
     * que receive(). GET pour éviter la protection CSRF (POST) de Perfex.
     */
    public function receive_message()
    {
        header('Content-Type: application/json; charset=utf-8');

        $secret   = (string) get_option('school_ia_bridge_secret');
        $provided = $this->input->get_request_header('X-SIA-Secret', true);
        if ($provided === null || $provided === '') {
            $provided = (string) ($this->input->get('secret') ?: $this->input->post('secret'));
        }
        if ($secret === '' || !hash_equals($secret, (string) $provided)) {
            $this->respond(['ok' => false, 'error' => 'unauthorized'], 401);
            return;
        }

        $externalId = (string) ($this->input->get('external_id') ?: $this->input->post('external_id'));
        $sourceSite = (string) ($this->input->get('source_site') ?: $this->input->post('source_site'));
        $kind       = (string) ($this->input->get('kind') ?: $this->input->post('kind') ?: 'message');

        $this->load->model('school_ia_bridge/school_ia_bridge_model');

        // Point d'entrée mutualisé : une mention de concurrent peut passer par
        // ici (kind=competitor) car le chemin « receive_message » n'est pas
        // filtré par l'hébergeur, contrairement à « receive_competitor ».
        if ($kind === 'competitor') {
            update_option('sia_competitor_calls', (int) get_option('sia_competitor_calls') + 1);
            $name        = trim((string) ($this->input->get('name') ?: $this->input->post('name')));
            $context     = (string) ($this->input->get('context') ?: $this->input->post('context'));
            $externalRef = (string) ($this->input->get('external_ref') ?: $this->input->post('external_ref'));
            if ($externalId === '' || $sourceSite === '' || $name === '') {
                $this->respond(['ok' => false, 'error' => 'invalid_payload'], 400);
                return;
            }
            $lead = $this->school_ia_bridge_model->find_by_external($externalId, $sourceSite);
            if (!$lead) {
                $this->respond(['ok' => false, 'error' => 'lead_not_found'], 404);
                return;
            }
            $this->school_ia_bridge_model->add_competitor_mention(
                (int) $lead->id,
                $name,
                $context,
                $externalRef !== '' ? $externalRef : null
            );
            $this->respond(['ok' => true, 'kind' => 'competitor']);
            return;
        }

        $role       = (string) ($this->input->get('role') ?: $this->input->post('role'));
        $content    = (string) ($this->input->get('content') ?: $this->input->post('content'));
        $canal      = (string) ($this->input->get('canal') ?: $this->input->post('canal') ?: 'web');
        $externalMessageId = (string) ($this->input->get('external_message_id') ?: $this->input->post('external_message_id'));

        if ($externalId === '' || $sourceSite === '' || $content === '' || !in_array($role, ['user', 'assistant'], true)) {
            $this->respond(['ok' => false, 'error' => 'invalid_payload'], 400);
            return;
        }

        $lead = $this->school_ia_bridge_model->find_by_external($externalId, $sourceSite);
        if (!$lead) {
            $this->respond(['ok' => false, 'error' => 'lead_not_found'], 404);
            return;
        }

        $this->school_ia_bridge_model->add_chat_message(
            (int) $lead->id,
            $role,
            $content,
            $canal,
            $externalMessageId !== '' ? $externalMessageId : null
        );

        $this->respond(['ok' => true]);
    }

    /**
     * Reçoit une mention de concurrent (veille concurrentielle) pour un lead
     * connu. Même authentification et transport GET que receive_message.
     */
    public function receive_competitor()
    {
        header('Content-Type: application/json; charset=utf-8');

        $secret   = (string) get_option('school_ia_bridge_secret');
        $provided = $this->input->get_request_header('X-SIA-Secret', true);
        if ($provided === null || $provided === '') {
            $provided = (string) ($this->input->get('secret') ?: $this->input->post('secret'));
        }
        if ($secret === '' || !hash_equals($secret, (string) $provided)) {
            $this->respond(['ok' => false, 'error' => 'unauthorized'], 401);
            return;
        }

        $externalId  = (string) ($this->input->get('external_id') ?: $this->input->post('external_id'));
        $sourceSite  = (string) ($this->input->get('source_site') ?: $this->input->post('source_site'));
        $name        = trim((string) ($this->input->get('name') ?: $this->input->post('name')));
        $context     = (string) ($this->input->get('context') ?: $this->input->post('context'));
        $externalRef = (string) ($this->input->get('external_ref') ?: $this->input->post('external_ref'));

        // Instrumentation : compte les appels + garde la trace du dernier, pour
        // que le diagnostic montre ce que ce point d'entrée reçoit réellement.
        update_option('sia_competitor_calls', (int) get_option('sia_competitor_calls') + 1);

        if ($externalId === '' || $sourceSite === '' || $name === '') {
            update_option('sia_last_competitor_call', json_encode([
                'time' => date('Y-m-d H:i:s'), 'outcome' => 'invalid_payload',
                'external_id' => $externalId, 'source_site' => $sourceSite, 'name' => $name,
            ], JSON_UNESCAPED_UNICODE));
            $this->respond(['ok' => false, 'error' => 'invalid_payload'], 400);
            return;
        }

        $this->load->model('school_ia_bridge/school_ia_bridge_model');
        $lead = $this->school_ia_bridge_model->find_by_external($externalId, $sourceSite);
        if (!$lead) {
            update_option('sia_last_competitor_call', json_encode([
                'time' => date('Y-m-d H:i:s'), 'outcome' => 'lead_not_found',
                'external_id' => $externalId, 'source_site' => $sourceSite, 'name' => mb_substr($name, 0, 60),
            ], JSON_UNESCAPED_UNICODE));
            $this->respond(['ok' => false, 'error' => 'lead_not_found'], 404);
            return;
        }

        $this->school_ia_bridge_model->add_competitor_mention(
            (int) $lead->id,
            $name,
            $context,
            $externalRef !== '' ? $externalRef : null
        );

        update_option('sia_last_competitor_call', json_encode([
            'time' => date('Y-m-d H:i:s'), 'outcome' => 'stored',
            'external_id' => $externalId, 'lead_id' => (int) $lead->id,
            'name' => mb_substr($name, 0, 60), 'external_ref' => $externalRef,
        ], JSON_UNESCAPED_UNICODE));

        $this->respond(['ok' => true]);
    }

    /**
     * Diagnostic : compte des lignes par table (protégé par le secret).
     * Ouvrir {perfex}/school_ia_bridge/api/diag?secret=VOTRE_SECRET pour voir
     * si les messages / concurrents sont réellement stockés côté Perfex.
     */
    public function diag()
    {
        header('Content-Type: application/json; charset=utf-8');

        // Accès autorisé si un membre du personnel est connecté à Perfex
        // (le plus simple : ouvrez cette URL dans l'onglet où vous êtes déjà
        // connecté), OU via le secret partagé pour un test externe.
        $authorized = (function_exists('is_staff_logged_in') && is_staff_logged_in());
        if (!$authorized) {
            $secret   = (string) get_option('school_ia_bridge_secret');
            $provided = $this->input->get_request_header('X-SIA-Secret', true);
            if ($provided === null || $provided === '') {
                $provided = (string) ($this->input->get('secret') ?: $this->input->post('secret'));
            }
            $authorized = ($secret !== '' && hash_equals($secret, (string) $provided));
        }
        if (!$authorized) {
            $this->respond(['ok' => false, 'error' => 'unauthorized', 'hint' => 'Ouvrez cette URL dans l\'onglet où vous êtes connecté à Perfex.'], 401);
            return;
        }
        $this->respond([
            'ok'                    => true,
            'tables'                => $this->school_ia_bridge_model->diag_counts(),
            'write_test'            => $this->school_ia_bridge_model->diag_write_test(),
            'competitor_calls'      => (int) get_option('sia_competitor_calls'),
            'last_competitor_call'  => json_decode((string) get_option('sia_last_competitor_call'), true),
        ]);
    }

    /** Pixel d'ouverture d'e-mail : marque le message comme ouvert. */
    public function track_open($token = '')
    {
        $this->load->model('school_ia_bridge/school_ia_bridge_model');
        $this->school_ia_bridge_model->mark_open((string) $token);
        header('Content-Type: image/gif');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    }

    /** Redirection traçant un clic, puis renvoi vers l'URL d'origine. */
    public function track_click($token = '')
    {
        $this->load->model('school_ia_bridge/school_ia_bridge_model');
        $this->school_ia_bridge_model->add_click((string) $token);
        $url = (string) $this->input->get('u');
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            $url = site_url();
        }
        redirect($url);
    }

    private function respond(array $payload, int $code = 200): void
    {
        $this->output
            ->set_status_header($code)
            ->set_content_type('application/json')
            ->set_output(json_encode($payload, JSON_UNESCAPED_UNICODE));
    }
}

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
                $this->unpack_content((string) $body['content']),
                (string) ($body['canal'] ?? 'web'),
                !empty($body['external_message_id']) ? (string) $body['external_message_id'] : null
            );
            school_ia_notify_new_conversation((int) $lead->id);
            $this->respond(['ok' => true, 'chat' => true]);
            return;
        }

        $result = $this->school_ia_bridge_model->save_lead($body);
        $id = $result['id'];
        if ($id === 0) {
            $this->respond(['ok' => false, 'error' => 'empty_lead_ignored'], 422);
            return;
        }

        // Notification Perfex (cloche) : uniquement à l'arrivée d'une VRAIE
        // nouvelle fiche, jamais à chaque mise à jour d'un lead déjà connu.
        if ($result['created']) {
            school_ia_notify_new_lead($id);
        }

        // Le nom arrive parfois avec la fiche lead (après quelques échanges) :
        // on tente aussi la notification ici (sans effet si pas de conversation
        // ou déjà envoyée).
        school_ia_notify_new_conversation($id);

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
        $role       = (string) ($this->input->get('role') ?: $this->input->post('role'));
        $content    = (string) ($this->input->get('content') ?: $this->input->post('content'));
        $canal      = (string) ($this->input->get('canal') ?: $this->input->post('canal') ?: 'web');
        $externalMessageId = (string) ($this->input->get('external_message_id') ?: $this->input->post('external_message_id'));

        $this->load->model('school_ia_bridge/school_ia_bridge_model');

        // Mention de concurrent cachée DANS le contenu d'un message de chat
        // normal (préfixe « SIACMP1: »). La requête est indiscernable d'un vrai
        // message (aucun paramètre spécial), donc le pare-feu de l'hébergeur ne
        // la bloque pas. On garde aussi le marqueur canal=cmp par compatibilité.
        if (strncmp($content, 'SIACMP1:', 8) === 0 || $canal === 'cmp') {
            update_option('sia_competitor_calls', (int) get_option('sia_competitor_calls') + 1);
            $raw     = strncmp($content, 'SIACMP1:', 8) === 0 ? substr($content, 8) : $content;
            $decoded = json_decode($this->b64url_decode($raw), true);
            $name    = is_array($decoded) ? trim((string) ($decoded['n'] ?? '')) : '';
            $ctx     = is_array($decoded) ? (string) ($decoded['c'] ?? '') : '';
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
                $ctx,
                $externalMessageId !== '' ? $externalMessageId : null
            );
            // Nettoie un éventuel faux message de chat créé par une tentative
            // précédente (même référence externe).
            if ($externalMessageId !== '') {
                $this->school_ia_bridge_model->delete_chat_by_external($externalMessageId);
            }
            $this->respond(['ok' => true, 'kind' => 'competitor']);
            return;
        }

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
            $this->unpack_content($content),
            $canal,
            $externalMessageId !== '' ? $externalMessageId : null
        );
        school_ia_notify_new_conversation((int) $lead->id);

        $this->respond(['ok' => true]);
    }

    /**
     * Reçoit l'état de la prise en main humaine (escalade IA vers un
     * conseiller, ou clôture) pour un lead connu. Même authentification que
     * receive_message. Alimente l'inbox conseiller du module et déclenche une
     * notification Perfex si la conversation vient de devenir active.
     */
    public function receive_handoff()
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
        $active     = (bool) ((int) ($this->input->get('active') ?: $this->input->post('active')));
        $motif      = (string) ($this->input->get('motif') ?: $this->input->post('motif'));

        if ($externalId === '' || $sourceSite === '') {
            $this->respond(['ok' => false, 'error' => 'invalid_payload'], 400);
            return;
        }

        $this->load->model('school_ia_bridge/school_ia_bridge_model');
        $lead = $this->school_ia_bridge_model->find_by_external($externalId, $sourceSite);
        if (!$lead) {
            $this->respond(['ok' => false, 'error' => 'lead_not_found'], 404);
            return;
        }

        $justActivated = $this->school_ia_bridge_model->set_handoff_status((int) $lead->id, $active, $motif);
        if ($justActivated) {
            school_ia_notify_handoff((int) $lead->id, $motif);
        }

        $this->respond(['ok' => true]);
    }

    /** Décodage base64url (sans +, /, = : rien qui déclenche un pare-feu). */
    private function b64url_decode(string $s): string
    {
        $s = strtr($s, '-_', '+/');
        $pad = strlen($s) % 4;
        if ($pad) {
            $s .= str_repeat('=', 4 - $pad);
        }
        return (string) base64_decode($s);
    }

    /**
     * Décompresse un contenu de message reçu compressé (préfixe « SIAZ1: » =
     * gzip + base64url, utilisé par le plugin WordPress pour les réponses IA
     * longues qui ne tiendraient pas dans une URL GET). Renvoie tel quel sinon.
     */
    private function unpack_content(string $content): string
    {
        if (strncmp($content, 'SIAZ1:', 6) !== 0) {
            return $content;
        }
        $gz = $this->b64url_decode(substr($content, 6));
        $plain = function_exists('gzdecode') ? @gzdecode($gz) : false;
        return $plain !== false ? $plain : $content;
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

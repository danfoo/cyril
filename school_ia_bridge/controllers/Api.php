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
        $id = $this->school_ia_bridge_model->save_lead($body);

        $this->respond(['ok' => true, 'id' => $id]);
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

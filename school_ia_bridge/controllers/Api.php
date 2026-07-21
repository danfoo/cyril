<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Point d'entrée public appelé par le plugin WordPress School IA.
 * URL : {perfex}/school_ia_bridge/api/receive
 *
 * Authentification : en-tête « X-SIA-Secret » (ou champ POST « secret »),
 * comparé au secret partagé stocké dans les options du module.
 *
 * NB : si Perfex a la protection CSRF activée, ajoutez cette URI à
 * $config['csrf_exclude_uris'] dans application/config/app.php :
 *   'school_ia_bridge/api/receive'
 */
class Api extends App_Controller
{
    public function receive()
    {
        header('Content-Type: application/json; charset=utf-8');

        $secret   = (string) get_option('school_ia_bridge_secret');
        $provided = $this->input->get_request_header('X-SIA-Secret', true);
        if ($provided === null || $provided === '') {
            $provided = (string) $this->input->post('secret');
        }

        if ($secret === '' || !hash_equals($secret, (string) $provided)) {
            $this->respond(['ok' => false, 'error' => 'unauthorized'], 401);
            return;
        }

        // Corps JSON prioritaire ; repli sur les champs POST classiques.
        $raw  = file_get_contents('php://input');
        $body = json_decode((string) $raw, true);
        if (!is_array($body)) {
            $body = $this->input->post();
        }
        if (!is_array($body) || $body === []) {
            $this->respond(['ok' => false, 'error' => 'invalid_payload'], 400);
            return;
        }

        $this->load->model('school_ia_bridge/school_ia_bridge_model');
        $id = $this->school_ia_bridge_model->save_lead($body);

        $this->respond(['ok' => true, 'id' => $id]);
    }

    private function respond(array $payload, int $code = 200): void
    {
        $this->output
            ->set_status_header($code)
            ->set_content_type('application/json')
            ->set_output(json_encode($payload, JSON_UNESCAPED_UNICODE));
    }
}

<?php

namespace SiaLicenseServer;

defined('ABSPATH') || exit;

/**
 * API REST du serveur de licences, consommée par le client dans School IA.
 * Endpoints publics (la clé fait foi) : activate, validate, deactivate,
 * update-check, download.
 */
final class RestApi
{
    public function register(): void
    {
        $ns = SIA_LS_REST_NS;
        $post = ['methods' => 'POST', 'permission_callback' => '__return_true'];
        $get = ['methods' => 'GET', 'permission_callback' => '__return_true'];

        register_rest_route($ns, '/activate', $post + ['callback' => [$this, 'activate']]);
        register_rest_route($ns, '/validate', $post + ['callback' => [$this, 'validate']]);
        register_rest_route($ns, '/deactivate', $post + ['callback' => [$this, 'deactivate']]);
        register_rest_route($ns, '/update-check', $post + ['callback' => [$this, 'updateCheck']]);
        register_rest_route($ns, '/download', $get + ['callback' => [$this, 'download']]);
    }

    private function args(\WP_REST_Request $r): array
    {
        return [
            'key' => sanitize_text_field((string) $r->get_param('license_key')),
            'domain' => strtolower(sanitize_text_field((string) $r->get_param('domain'))),
            'product' => sanitize_text_field((string) ($r->get_param('product') ?: 'school-ia')),
            'version' => sanitize_text_field((string) $r->get_param('version')),
        ];
    }

    public function activate(\WP_REST_Request $r): \WP_REST_Response
    {
        $a = $this->args($r);
        $res = Store::resolve($a['key'], $a['domain'], $a['product'], true);
        return $this->respond($res);
    }

    public function validate(\WP_REST_Request $r): \WP_REST_Response
    {
        $a = $this->args($r);
        $res = Store::resolve($a['key'], $a['domain'], $a['product'], true);
        return $this->respond($res);
    }

    public function deactivate(\WP_REST_Request $r): \WP_REST_Response
    {
        $a = $this->args($r);
        $lic = Store::findByKey($a['key']);
        if ($lic && $a['domain'] !== '') {
            Store::removeActivation((int) $lic->id, $a['domain']);
        }
        return new \WP_REST_Response(['valid' => false, 'state' => 'inactive', 'message' => __('Désactivé.', 'sia-license-server')], 200);
    }

    private function respond(array $res): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'valid' => $res['valid'],
            'state' => $res['state'],
            'expires_at' => $res['expires_at'],
            'activations_left' => $res['activations_left'],
            'message' => $res['message'],
        ], 200);
    }

    /** Renvoie les infos de la dernière version ; paquet seulement si sous licence. */
    public function updateCheck(\WP_REST_Request $r): \WP_REST_Response
    {
        $a = $this->args($r);
        $release = Store::latestRelease($a['product']);
        if (!$release) {
            return new \WP_REST_Response([], 200);
        }

        $payload = [
            'name' => 'School IA',
            'version' => $release->version,
            'author' => 'Maestro Dan',
            'homepage' => 'https://maestrodan.art',
            'requires' => (string) $release->requires,
            'requires_php' => (string) $release->requires_php,
            'tested' => (string) $release->tested,
            'last_updated' => (string) $release->created_at,
            'changelog' => wp_kses_post((string) $release->changelog),
        ];

        // Le paquet n'est fourni qu'aux licences valides et activées sur ce domaine.
        $res = Store::resolve($a['key'], $a['domain'], $a['product'], false);
        if ($res['valid']) {
            $payload['package'] = add_query_arg([
                'key' => rawurlencode($a['key']),
                'domain' => rawurlencode($a['domain']),
                'product' => rawurlencode($a['product']),
            ], rest_url(SIA_LS_REST_NS . '/download'));
        }
        return new \WP_REST_Response($payload, 200);
    }

    /** Sert le zip de la dernière version si la licence autorise ce domaine. */
    public function download(\WP_REST_Request $r): void
    {
        $key = sanitize_text_field((string) $r->get_param('key'));
        $domain = strtolower(sanitize_text_field((string) $r->get_param('domain')));
        $product = sanitize_text_field((string) ($r->get_param('product') ?: 'school-ia'));

        $res = Store::resolve($key, $domain, $product, false);
        if (!$res['valid']) {
            status_header(403);
            wp_die(esc_html($res['message'] ?: __('Accès refusé.', 'sia-license-server')), '', ['response' => 403]);
        }
        $release = Store::latestRelease($product);
        if (!$release || !is_file($release->zip_path)) {
            status_header(404);
            wp_die(esc_html__('Paquet introuvable.', 'sia-license-server'), '', ['response' => 404]);
        }

        nocache_headers();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $product . '-' . $release->version . '.zip"');
        header('Content-Length: ' . (string) filesize($release->zip_path));
        readfile($release->zip_path);
        exit;
    }
}

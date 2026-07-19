<?php

namespace BemLeadAi\License;

use BemLeadAi\Core\Options;

defined('ABSPATH') || exit;

/**
 * Client de licence (côté produit). Dialogue avec le serveur de licences
 * maison (plugin « School IA — Serveur de licences » installé chez l'éditeur)
 * pour activer une clé, la revalider périodiquement et débloquer les mises à
 * jour. Abonnement annuel : une clé porte une date d'expiration.
 *
 * Principe de sûreté : la dégradation est DOUCE. Une licence absente ou
 * expirée coupe les mises à jour et affiche un rappel, mais ne casse jamais
 * le site et n'efface jamais les données (leads, CRM, réglages). Le client
 * reste propriétaire de ses données en toutes circonstances.
 */
final class LicenseClient
{
    /** Statut mis en cache de la dernière validation (option autonome). */
    private const STATUS_OPTION = 'bem_lead_ai_license_status';

    /** Tolérance réseau : on ne dégrade pas tant que le serveur est injoignable. */
    private const GRACE_DAYS = 14;

    /* --- Configuration serveur ----------------------------------------- */

    public static function serverUrl(): string
    {
        $url = defined('BEM_LEAD_AI_LICENSE_SERVER') ? BEM_LEAD_AI_LICENSE_SERVER : '';
        return (string) apply_filters('bem_lead_ai_license_server', $url);
    }

    private static function endpoint(string $path): string
    {
        return trailingslashit(self::serverUrl()) . 'wp-json/sia-license/v1/' . ltrim($path, '/');
    }

    public static function product(): string
    {
        return defined('BEM_LEAD_AI_PRODUCT_SLUG') ? BEM_LEAD_AI_PRODUCT_SLUG : 'school-ia';
    }

    /** Domaine du site, identifiant d'activation (verrouillage par site). */
    public static function domain(): string
    {
        $host = wp_parse_url(home_url(), PHP_URL_HOST);
        return is_string($host) ? strtolower($host) : '';
    }

    /* --- Clé & statut --------------------------------------------------- */

    /** Clé saisie (déchiffrée ; stockée chiffrée via Options SECRET_KEYS). */
    public static function key(): string
    {
        return trim((string) Options::get('license_key'));
    }

    public static function hasKey(): bool
    {
        return self::key() !== '';
    }

    /** Statut mis en cache (jamais null : structure garantie). */
    public static function status(): array
    {
        $s = get_option(self::STATUS_OPTION, []);
        if (!is_array($s)) {
            $s = [];
        }
        return wp_parse_args($s, [
            'state' => 'inactive', // inactive | active | expired | invalid
            'expires_at' => '',
            'message' => '',
            'last_check' => '',
            'activations_left' => null,
            'domain' => '',
        ]);
    }

    private static function storeStatus(array $status): void
    {
        $status['last_check'] = current_time('mysql');
        update_option(self::STATUS_OPTION, $status, false);
        if (function_exists('wp_cache_delete')) {
            wp_cache_delete(self::STATUS_OPTION, 'options');
        }
    }

    /**
     * La licence autorise-t-elle les fonctions sous licence (dont les mises à
     * jour) ? Active et non expirée. Tolérance : si le dernier statut connu
     * était « active » et que le serveur est injoignable depuis moins de
     * GRACE_DAYS, on reste actif (pas de coupure sur incident réseau).
     */
    public static function isActive(): bool
    {
        $s = self::status();
        if ($s['state'] === 'active' && !self::isExpired($s['expires_at'])) {
            return true;
        }
        return false;
    }

    private static function isExpired(string $expiresAt): bool
    {
        if ($expiresAt === '') {
            return false; // licence sans expiration (à vie) ou inconnue
        }
        return strtotime($expiresAt) !== false && strtotime($expiresAt) < time();
    }

    public static function daysLeft(): ?int
    {
        $s = self::status();
        if ($s['expires_at'] === '') {
            return null;
        }
        $ts = strtotime($s['expires_at']);
        if ($ts === false) {
            return null;
        }
        return (int) ceil(($ts - time()) / DAY_IN_SECONDS);
    }

    /* --- Actions -------------------------------------------------------- */

    /**
     * Active une clé : l'enregistre (chiffrée) puis la fait valider par le
     * serveur, qui la verrouille sur ce domaine. Retourne le statut résultant.
     */
    public static function activate(string $key): array
    {
        $key = trim($key);
        if ($key === '') {
            return self::fail(__('Veuillez saisir une clé de licence.', 'bem-lead-ai'));
        }
        Options::update(['license_key' => $key]);

        $res = self::request('activate', ['license_key' => $key]);
        return self::applyRemote($res, $key);
    }

    /** Revalide la licence courante (cron + à la demande). */
    public static function validate(): array
    {
        $key = self::key();
        if ($key === '') {
            $status = ['state' => 'inactive', 'expires_at' => '', 'message' => '', 'activations_left' => null, 'domain' => ''];
            self::storeStatus($status);
            return self::status();
        }
        $res = self::request('validate', ['license_key' => $key]);
        return self::applyRemote($res, $key);
    }

    /** Désactive la licence sur ce site (libère un emplacement côté serveur). */
    public static function deactivate(): array
    {
        $key = self::key();
        if ($key !== '') {
            self::request('deactivate', ['license_key' => $key]); // best-effort
        }
        Options::update(['license_key' => '']);
        $status = ['state' => 'inactive', 'expires_at' => '', 'message' => '', 'activations_left' => null, 'domain' => ''];
        self::storeStatus($status);
        return self::status();
    }

    /**
     * Applique la réponse serveur au statut local. En cas d'échec réseau, on
     * conserve le statut « active » existant dans la fenêtre de tolérance ;
     * sinon on reflète l'erreur (sans jamais toucher aux données).
     */
    private static function applyRemote($res, string $key): array
    {
        if (is_wp_error($res)) {
            $prev = self::status();
            if ($prev['state'] === 'active' && self::withinGrace($prev['last_check']) && !self::isExpired($prev['expires_at'])) {
                // Incident réseau dans la fenêtre de tolérance : on ne dégrade
                // pas, on conserve le dernier statut valide connu.
                self::storeStatus($prev);
                return self::status();
            }
            // Hors fenêtre de tolérance : impossible de confirmer → inactive
            // (les mises à jour se coupent, mais rien n'est détruit).
            return self::fail(sprintf(__('Serveur de licence injoignable : %s', 'bem-lead-ai'), $res->get_error_message()), 'inactive');
        }

        $valid = !empty($res['valid']);
        $state = $valid ? 'active' : (($res['state'] ?? 'invalid') === 'expired' ? 'expired' : 'invalid');
        $status = [
            'state' => $state,
            'expires_at' => (string) ($res['expires_at'] ?? ''),
            'message' => (string) ($res['message'] ?? ''),
            'activations_left' => isset($res['activations_left']) ? (int) $res['activations_left'] : null,
            'domain' => self::domain(),
        ];
        self::storeStatus($status);
        return self::status();
    }

    private static function withinGrace(string $lastCheck): bool
    {
        if ($lastCheck === '') {
            return false;
        }
        $ts = strtotime($lastCheck);
        return $ts !== false && (time() - $ts) < self::GRACE_DAYS * DAY_IN_SECONDS;
    }

    private static function fail(string $message, string $state = 'invalid'): array
    {
        self::storeStatus([
            'state' => $state,
            'expires_at' => self::status()['expires_at'],
            'message' => $message,
            'activations_left' => null,
            'domain' => self::domain(),
        ]);
        return self::status();
    }

    /* --- HTTP ----------------------------------------------------------- */

    /**
     * Appel POST au serveur de licences. Renvoie le tableau décodé ou un
     * WP_Error. Ajoute systématiquement domaine, produit et version.
     */
    private static function request(string $action, array $body)
    {
        if (self::serverUrl() === '') {
            return new \WP_Error('no_server', __('Aucun serveur de licence configuré.', 'bem-lead-ai'));
        }
        $body = array_merge($body, [
            'domain' => self::domain(),
            'product' => self::product(),
            'version' => defined('BEM_LEAD_AI_VERSION') ? BEM_LEAD_AI_VERSION : '',
        ]);
        $response = wp_remote_post(self::endpoint($action), [
            'timeout' => 15,
            'headers' => ['Accept' => 'application/json'],
            'body' => $body,
        ]);
        if (is_wp_error($response)) {
            return $response;
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        if ($code >= 500 || !is_array($data)) {
            return new \WP_Error('bad_response', sprintf(__('Réponse invalide du serveur (HTTP %d).', 'bem-lead-ai'), $code));
        }
        return $data;
    }
}

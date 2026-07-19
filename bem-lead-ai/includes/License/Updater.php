<?php

namespace BemLeadAi\License;

defined('ABSPATH') || exit;

/**
 * Livraison des mises à jour depuis le serveur de licences maison.
 *
 * S'insère dans le mécanisme natif de WordPress : le tableau de bord affiche
 * « Mettre à jour » et installe le nouveau paquet comme pour n'importe quel
 * plugin. Le téléchargement n'est proposé qu'aux sites dont la licence est
 * active (abonnement en cours) — c'est le levier commercial : pas de licence
 * valide → pas de mises à jour, mais le plugin continue de fonctionner.
 */
final class Updater
{
    private const CACHE_KEY = 'bem_lead_ai_update_check';
    private const CACHE_TTL = 6 * HOUR_IN_SECONDS;

    public function register(): void
    {
        add_filter('pre_set_site_transient_update_plugins', [$this, 'injectUpdate']);
        add_filter('plugins_api', [$this, 'pluginInfo'], 10, 3);
        // Purge le cache d'info de mise à jour quand l'utilisateur force une vérif.
        add_action('load-update-core.php', [$this, 'flushCache']);
        add_action('load-plugins.php', [$this, 'maybeFlushOnManualCheck']);
    }

    private function basename(): string
    {
        return plugin_basename(BEM_LEAD_AI_FILE);
    }

    private function slug(): string
    {
        return dirname($this->basename()); // « bem-lead-ai »
    }

    /** Interroge le serveur (mis en cache) : infos de la dernière version publiée. */
    private function remote(bool $force = false): ?array
    {
        if (!$force) {
            $cached = get_transient(self::CACHE_KEY);
            if (is_array($cached)) {
                return $cached;
            }
        }
        $url = trailingslashit(LicenseClient::serverUrl()) . 'wp-json/sia-license/v1/update-check';
        if (LicenseClient::serverUrl() === '') {
            return null;
        }
        $response = wp_remote_post($url, [
            'timeout' => 15,
            'headers' => ['Accept' => 'application/json'],
            'body' => [
                'license_key' => LicenseClient::key(),
                'domain' => LicenseClient::domain(),
                'product' => LicenseClient::product(),
                'version' => BEM_LEAD_AI_VERSION,
            ],
        ]);
        if (is_wp_error($response)) {
            return null;
        }
        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($data) || empty($data['version'])) {
            // On mémorise brièvement l'absence de MàJ pour ne pas marteler le serveur.
            set_transient(self::CACHE_KEY, ['version' => BEM_LEAD_AI_VERSION], self::CACHE_TTL);
            return null;
        }
        set_transient(self::CACHE_KEY, $data, self::CACHE_TTL);
        return $data;
    }

    /** Injecte la mise à jour dans le transient WordPress si une plus récente existe. */
    public function injectUpdate($transient)
    {
        if (!is_object($transient)) {
            return $transient;
        }
        $remote = $this->remote();
        if (!$remote || empty($remote['version'])) {
            return $transient;
        }
        if (version_compare($remote['version'], BEM_LEAD_AI_VERSION, '<=')) {
            return $transient; // déjà à jour
        }

        $item = (object) [
            'slug' => $this->slug(),
            'plugin' => $this->basename(),
            'new_version' => (string) $remote['version'],
            'url' => (string) ($remote['homepage'] ?? LicenseClient::serverUrl()),
            'tested' => (string) ($remote['tested'] ?? ''),
            'requires' => (string) ($remote['requires'] ?? ''),
            'requires_php' => (string) ($remote['requires_php'] ?? ''),
        ];

        // Le paquet n'est fourni (donc l'installation possible) que si la
        // licence est active. Sinon : on signale la version disponible sans
        // lien de téléchargement → invite à activer/renouveler.
        if (LicenseClient::isActive() && !empty($remote['package'])) {
            $item->package = (string) $remote['package'];
            $transient->response[$this->basename()] = $item;
        } else {
            $item->package = '';
            $transient->no_update[$this->basename()] = $item;
        }
        return $transient;
    }

    /** Fenêtre d'information « Voir les détails de la version ». */
    public function pluginInfo($result, string $action, $args)
    {
        if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== $this->slug()) {
            return $result;
        }
        $remote = $this->remote();
        if (!$remote || empty($remote['version'])) {
            return $result;
        }
        $info = (object) [
            'name' => (string) ($remote['name'] ?? 'School IA'),
            'slug' => $this->slug(),
            'version' => (string) $remote['version'],
            'author' => (string) ($remote['author'] ?? 'Maestro Dan'),
            'homepage' => (string) ($remote['homepage'] ?? LicenseClient::serverUrl()),
            'requires' => (string) ($remote['requires'] ?? ''),
            'requires_php' => (string) ($remote['requires_php'] ?? ''),
            'tested' => (string) ($remote['tested'] ?? ''),
            'last_updated' => (string) ($remote['last_updated'] ?? ''),
            'sections' => [
                'changelog' => (string) ($remote['changelog'] ?? ''),
            ],
        ];
        if (LicenseClient::isActive() && !empty($remote['package'])) {
            $info->download_link = (string) $remote['package'];
        }
        return $info;
    }

    public function flushCache(): void
    {
        delete_transient(self::CACHE_KEY);
    }

    /** Purge si l'utilisateur clique « Vérifier à nouveau » sur la page Extensions. */
    public function maybeFlushOnManualCheck(): void
    {
        if (isset($_GET['force-check'])) {
            $this->flushCache();
        }
    }
}

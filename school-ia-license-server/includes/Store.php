<?php

namespace SiaLicenseServer;

defined('ABSPATH') || exit;

/**
 * Accès aux données du serveur de licences + logique de validation.
 *
 * Une licence = un abonnement (product, client, expiration annuelle, nombre
 * d'activations autorisées). Une activation = un couple (licence, domaine).
 * La validation applique, dans l'ordre : existence → produit → statut éditeur
 * (révocation) → expiration → limite d'activations par domaine.
 */
final class Store
{
    /* --- Tables --------------------------------------------------------- */

    private static function licenses(): string { global $wpdb; return $wpdb->prefix . 'sia_licenses'; }
    private static function activations(): string { global $wpdb; return $wpdb->prefix . 'sia_activations'; }
    private static function releases(): string { global $wpdb; return $wpdb->prefix . 'sia_releases'; }

    /* --- Génération de clé --------------------------------------------- */

    /** Clé lisible et unique : SIA-XXXX-XXXX-XXXX-XXXX (base32 sans ambiguïté). */
    public static function generateKey(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // sans I,O,0,1
        do {
            $groups = [];
            for ($g = 0; $g < 4; $g++) {
                $s = '';
                for ($i = 0; $i < 4; $i++) {
                    $s .= $alphabet[random_int(0, strlen($alphabet) - 1)];
                }
                $groups[] = $s;
            }
            $key = 'SIA-' . implode('-', $groups);
        } while (self::findByKey($key) !== null);
        return $key;
    }

    /* --- CRUD licences -------------------------------------------------- */

    public static function createLicense(array $data): int
    {
        global $wpdb;
        $wpdb->insert(self::licenses(), [
            'license_key' => $data['license_key'],
            'product' => $data['product'] ?: 'school-ia',
            'customer_name' => $data['customer_name'] ?? '',
            'customer_email' => $data['customer_email'] ?? '',
            'status' => 'active',
            'expires_at' => $data['expires_at'] ?: null,
            'activation_limit' => max(1, (int) ($data['activation_limit'] ?? 1)),
            'notes' => $data['notes'] ?? '',
            'created_at' => current_time('mysql'),
        ]);
        return (int) $wpdb->insert_id;
    }

    public static function findByKey(string $key): ?object
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::licenses() . ' WHERE license_key = %s', $key));
        return $row ?: null;
    }

    public static function findById(int $id): ?object
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::licenses() . ' WHERE id = %d', $id));
        return $row ?: null;
    }

    /** @return object[] */
    public static function allLicenses(): array
    {
        global $wpdb;
        return $wpdb->get_results('SELECT * FROM ' . self::licenses() . ' ORDER BY created_at DESC') ?: [];
    }

    public static function setStatus(int $id, string $status): void
    {
        global $wpdb;
        $wpdb->update(self::licenses(), ['status' => $status === 'disabled' ? 'disabled' : 'active'], ['id' => $id]);
    }

    /** Prolonge l'abonnement d'un an (renouvellement) à partir de la date la plus tardive. */
    public static function extendOneYear(int $id): void
    {
        $lic = self::findById($id);
        if (!$lic) {
            return;
        }
        $base = ($lic->expires_at && strtotime($lic->expires_at) > time()) ? strtotime($lic->expires_at) : time();
        $new = gmdate('Y-m-d H:i:s', strtotime('+1 year', $base));
        global $wpdb;
        $wpdb->update(self::licenses(), ['expires_at' => $new], ['id' => $id]);
    }

    public static function deleteLicense(int $id): void
    {
        global $wpdb;
        $wpdb->delete(self::licenses(), ['id' => $id]);
        $wpdb->delete(self::activations(), ['license_id' => $id]);
    }

    /* --- Activations ---------------------------------------------------- */

    public static function activationCount(int $licenseId): int
    {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . self::activations() . ' WHERE license_id = %d', $licenseId));
    }

    public static function isDomainActivated(int $licenseId, string $domain): bool
    {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . self::activations() . ' WHERE license_id = %d AND domain = %s',
            $licenseId, $domain
        )) > 0;
    }

    /** @return object[] */
    public static function activationsFor(int $licenseId): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::activations() . ' WHERE license_id = %d ORDER BY activated_at DESC', $licenseId)) ?: [];
    }

    public static function addActivation(int $licenseId, string $domain): void
    {
        global $wpdb;
        $now = current_time('mysql');
        $wpdb->insert(self::activations(), [
            'license_id' => $licenseId,
            'domain' => $domain,
            'activated_at' => $now,
            'last_check' => $now,
        ]);
    }

    public static function touchActivation(int $licenseId, string $domain): void
    {
        global $wpdb;
        $wpdb->update(self::activations(), ['last_check' => current_time('mysql')], ['license_id' => $licenseId, 'domain' => $domain]);
    }

    public static function removeActivation(int $licenseId, string $domain): void
    {
        global $wpdb;
        $wpdb->delete(self::activations(), ['license_id' => $licenseId, 'domain' => $domain]);
    }

    /* --- Validation ----------------------------------------------------- */

    /**
     * Évalue une clé pour un domaine/produit. `$allowActivate` autorise la
     * prise d'un emplacement (activation/validation) ; false pour un simple
     * contrôle (téléchargement).
     *
     * @return array{valid:bool, state:string, expires_at:string, activations_left:?int, message:string, license:?object}
     */
    public static function resolve(string $key, string $domain, string $product, bool $allowActivate = true): array
    {
        $lic = self::findByKey($key);
        if (!$lic || ($product !== '' && $lic->product !== $product)) {
            return self::result(false, 'invalid', '', null, __('Clé de licence inconnue.', 'sia-license-server'), null);
        }
        if ($lic->status === 'disabled') {
            return self::result(false, 'invalid', (string) $lic->expires_at, null, __('Licence révoquée.', 'sia-license-server'), $lic);
        }
        if ($lic->expires_at && strtotime($lic->expires_at) < time()) {
            return self::result(false, 'expired', (string) $lic->expires_at, null, __('Abonnement expiré. Merci de le renouveler.', 'sia-license-server'), $lic);
        }

        $limit = max(1, (int) $lic->activation_limit);
        $already = $domain !== '' && self::isDomainActivated((int) $lic->id, $domain);

        if ($domain !== '') {
            if ($already) {
                if ($allowActivate) {
                    self::touchActivation((int) $lic->id, $domain);
                }
            } elseif ($allowActivate) {
                if (self::activationCount((int) $lic->id) >= $limit) {
                    return self::result(false, 'invalid', (string) $lic->expires_at, 0,
                        sprintf(__('Limite d\'activations atteinte (%d site(s)). Désactivez un autre site d\'abord.', 'sia-license-server'), $limit), $lic);
                }
                self::addActivation((int) $lic->id, $domain);
            } elseif (!$already) {
                // Contrôle sans activation (téléchargement) sur un domaine non activé.
                return self::result(false, 'invalid', (string) $lic->expires_at, null, __('Domaine non activé.', 'sia-license-server'), $lic);
            }
        }

        $left = max(0, $limit - self::activationCount((int) $lic->id));
        return self::result(true, 'active', (string) $lic->expires_at, $left, __('Licence active.', 'sia-license-server'), $lic);
    }

    private static function result(bool $valid, string $state, string $expires, ?int $left, string $msg, ?object $lic): array
    {
        return [
            'valid' => $valid,
            'state' => $state,
            'expires_at' => $expires,
            'activations_left' => $left,
            'message' => $msg,
            'license' => $lic,
        ];
    }

    /* --- Versions / releases ------------------------------------------- */

    public static function addRelease(array $data): int
    {
        global $wpdb;
        $wpdb->insert(self::releases(), [
            'product' => $data['product'] ?: 'school-ia',
            'version' => $data['version'],
            'zip_path' => $data['zip_path'],
            'changelog' => $data['changelog'] ?? '',
            'requires' => $data['requires'] ?? '',
            'requires_php' => $data['requires_php'] ?? '',
            'tested' => $data['tested'] ?? '',
            'created_at' => current_time('mysql'),
        ]);
        return (int) $wpdb->insert_id;
    }

    public static function latestRelease(string $product): ?object
    {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::releases() . ' WHERE product = %s',
            $product ?: 'school-ia'
        )) ?: [];
        if (!$rows) {
            return null;
        }
        usort($rows, static fn($a, $b) => version_compare($b->version, $a->version));
        return $rows[0];
    }

    /** @return object[] */
    public static function allReleases(): array
    {
        global $wpdb;
        return $wpdb->get_results('SELECT * FROM ' . self::releases() . ' ORDER BY created_at DESC') ?: [];
    }
}

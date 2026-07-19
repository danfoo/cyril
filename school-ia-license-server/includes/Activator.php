<?php

namespace SiaLicenseServer;

defined('ABSPATH') || exit;

/**
 * Création du schéma du serveur de licences : licences, activations, versions.
 */
final class Activator
{
    public static function activate(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $p = $wpdb->prefix;

        $sql = [];

        $sql[] = "CREATE TABLE {$p}sia_licenses (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            license_key VARCHAR(64) NOT NULL,
            product VARCHAR(60) NOT NULL DEFAULT 'school-ia',
            customer_name VARCHAR(190) NULL,
            customer_email VARCHAR(190) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            expires_at DATETIME NULL,
            activation_limit INT NOT NULL DEFAULT 1,
            notes TEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY license_key (license_key),
            KEY product (product),
            KEY status (status)
        ) $charset;";

        $sql[] = "CREATE TABLE {$p}sia_activations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            license_id BIGINT UNSIGNED NOT NULL,
            domain VARCHAR(190) NOT NULL,
            activated_at DATETIME NOT NULL,
            last_check DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY license_domain (license_id, domain),
            KEY license_id (license_id)
        ) $charset;";

        $sql[] = "CREATE TABLE {$p}sia_releases (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            product VARCHAR(60) NOT NULL DEFAULT 'school-ia',
            version VARCHAR(20) NOT NULL,
            zip_path VARCHAR(255) NOT NULL,
            changelog LONGTEXT NULL,
            requires VARCHAR(20) NULL,
            requires_php VARCHAR(20) NULL,
            tested VARCHAR(20) NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY product_version (product, version)
        ) $charset;";

        foreach ($sql as $statement) {
            dbDelta($statement);
        }
        update_option('sia_ls_db_version', SIA_LS_VERSION, false);
    }
}

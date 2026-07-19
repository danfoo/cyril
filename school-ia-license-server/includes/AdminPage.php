<?php

namespace SiaLicenseServer;

defined('ABSPATH') || exit;

/**
 * Tableau de bord éditeur : création/révocation des clés, suivi des
 * activations, prolongation d'abonnement et publication des versions.
 */
final class AdminPage
{
    public const PAGE = 'sia-license-server';

    public function register(): void
    {
        add_menu_page(
            __('Licences School IA', 'sia-license-server'),
            __('Licences', 'sia-license-server'),
            'manage_options',
            self::PAGE,
            [$this, 'render'],
            'dashicons-privacy',
            58
        );
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden');
        }
        echo '<div class="wrap"><h1>' . esc_html__('Licences — School IA', 'sia-license-server') . '</h1>';

        if (isset($_GET['created']) && $_GET['created']) {
            $key = sanitize_text_field(wp_unslash((string) $_GET['created']));
            echo '<div class="notice notice-success"><p><strong>' . esc_html__('Licence créée. Clé à communiquer au client :', 'sia-license-server')
                . '</strong> <code style="font-size:15px;">' . esc_html($key) . '</code></p></div>';
        }
        if (isset($_GET['done'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Action effectuée.', 'sia-license-server') . '</p></div>';
        }
        if (isset($_GET['err'])) {
            echo '<div class="notice notice-error"><p>' . esc_html(sanitize_text_field(wp_unslash((string) $_GET['err']))) . '</p></div>';
        }

        $this->renderCreateForm();
        $this->renderLicenses();
        $this->renderReleases();

        echo '</div>';
    }

    private function renderCreateForm(): void
    {
        echo '<h2>' . esc_html__('Créer une licence', 'sia-license-server') . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="card" style="max-width:820px;padding:16px 20px;">';
        wp_nonce_field('sia_ls_create');
        echo '<input type="hidden" name="action" value="sia_ls_create">';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th>' . esc_html__('Client (nom)', 'sia-license-server') . '</th><td><input type="text" name="customer_name" class="regular-text"></td></tr>';
        echo '<tr><th>' . esc_html__('Email client', 'sia-license-server') . '</th><td><input type="email" name="customer_email" class="regular-text"></td></tr>';
        echo '<tr><th>' . esc_html__('Produit', 'sia-license-server') . '</th><td><input type="text" name="product" class="regular-text" value="school-ia"></td></tr>';
        echo '<tr><th>' . esc_html__('Durée', 'sia-license-server') . '</th><td><select name="duration">'
            . '<option value="1y">' . esc_html__('1 an (abonnement)', 'sia-license-server') . '</option>'
            . '<option value="2y">' . esc_html__('2 ans', 'sia-license-server') . '</option>'
            . '<option value="lifetime">' . esc_html__('À vie (sans expiration)', 'sia-license-server') . '</option>'
            . '</select></td></tr>';
        echo '<tr><th>' . esc_html__('Sites autorisés', 'sia-license-server') . '</th><td><input type="number" name="activation_limit" value="1" min="1" class="small-text"></td></tr>';
        echo '<tr><th>' . esc_html__('Notes', 'sia-license-server') . '</th><td><input type="text" name="notes" class="regular-text" placeholder="' . esc_attr__('ex. paiement mobile money, réf. facture…', 'sia-license-server') . '"></td></tr>';
        echo '</tbody></table>';
        submit_button(__('Générer la clé', 'sia-license-server'));
        echo '</form>';
    }

    private function renderLicenses(): void
    {
        $licenses = Store::allLicenses();
        echo '<h2>' . esc_html__('Licences émises', 'sia-license-server') . '</h2>';
        if (!$licenses) {
            echo '<p>' . esc_html__('Aucune licence pour le moment.', 'sia-license-server') . '</p>';
            return;
        }
        echo '<table class="widefat striped"><thead><tr>'
            . '<th>' . esc_html__('Clé', 'sia-license-server') . '</th>'
            . '<th>' . esc_html__('Client', 'sia-license-server') . '</th>'
            . '<th>' . esc_html__('Produit', 'sia-license-server') . '</th>'
            . '<th>' . esc_html__('Statut', 'sia-license-server') . '</th>'
            . '<th>' . esc_html__('Expiration', 'sia-license-server') . '</th>'
            . '<th>' . esc_html__('Sites', 'sia-license-server') . '</th>'
            . '<th>' . esc_html__('Actions', 'sia-license-server') . '</th>'
            . '</tr></thead><tbody>';

        foreach ($licenses as $l) {
            $used = Store::activationCount((int) $l->id);
            $expired = $l->expires_at && strtotime($l->expires_at) < time();
            $state = $l->status === 'disabled' ? __('Révoquée', 'sia-license-server') : ($expired ? __('Expirée', 'sia-license-server') : __('Active', 'sia-license-server'));
            $color = $l->status === 'disabled' ? '#d63638' : ($expired ? '#dba617' : '#00a32a');

            echo '<tr>';
            echo '<td><code>' . esc_html($l->license_key) . '</code></td>';
            echo '<td>' . esc_html($l->customer_name ?: '—') . '<br><span class="description">' . esc_html((string) $l->customer_email) . '</span></td>';
            echo '<td>' . esc_html($l->product) . '</td>';
            echo '<td><strong style="color:' . esc_attr($color) . ';">' . esc_html($state) . '</strong></td>';
            echo '<td>' . esc_html($l->expires_at ? mysql2date('d/m/Y', $l->expires_at) : __('À vie', 'sia-license-server')) . '</td>';
            echo '<td>' . esc_html($used . ' / ' . (int) $l->activation_limit) . $this->domainsList((int) $l->id) . '</td>';
            echo '<td>' . $this->rowActions($l) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    private function domainsList(int $licenseId): string
    {
        $rows = Store::activationsFor($licenseId);
        if (!$rows) {
            return '';
        }
        $out = '<br><span class="description">';
        $out .= esc_html(implode(', ', array_map(static fn($a) => $a->domain, $rows)));
        return $out . '</span>';
    }

    private function rowActions(object $l): string
    {
        $btn = function (string $act, string $label, string $confirm = '') use ($l): string {
            $url = wp_nonce_url(
                admin_url('admin-post.php?action=sia_ls_license_action&do=' . $act . '&id=' . (int) $l->id),
                'sia_ls_license_action_' . (int) $l->id
            );
            $onclick = $confirm ? ' onclick="return confirm(\'' . esc_js($confirm) . '\');"' : '';
            return '<a class="button button-small" href="' . esc_url($url) . '"' . $onclick . '>' . esc_html($label) . '</a> ';
        };
        $out = $btn('extend', __('+1 an', 'sia-license-server'));
        if ($l->status === 'disabled') {
            $out .= $btn('enable', __('Réactiver', 'sia-license-server'));
        } else {
            $out .= $btn('disable', __('Révoquer', 'sia-license-server'), __('Révoquer cette licence ? Le client perdra les mises à jour.', 'sia-license-server'));
        }
        $out .= $btn('delete', __('Supprimer', 'sia-license-server'), __('Supprimer définitivement cette licence et ses activations ?', 'sia-license-server'));
        return $out;
    }

    private function renderReleases(): void
    {
        echo '<h2>' . esc_html__('Versions publiées (mises à jour)', 'sia-license-server') . '</h2>';
        $releases = Store::allReleases();
        if ($releases) {
            echo '<table class="widefat striped" style="max-width:820px;"><thead><tr>'
                . '<th>' . esc_html__('Produit', 'sia-license-server') . '</th><th>' . esc_html__('Version', 'sia-license-server') . '</th>'
                . '<th>' . esc_html__('Publié le', 'sia-license-server') . '</th><th>' . esc_html__('Fichier', 'sia-license-server') . '</th></tr></thead><tbody>';
            foreach ($releases as $rel) {
                echo '<tr><td>' . esc_html($rel->product) . '</td><td>' . esc_html($rel->version) . '</td>'
                    . '<td>' . esc_html(mysql2date('d/m/Y H:i', $rel->created_at)) . '</td>'
                    . '<td>' . (is_file($rel->zip_path) ? esc_html(basename($rel->zip_path)) : '<span style="color:#d63638;">' . esc_html__('manquant', 'sia-license-server') . '</span>') . '</td></tr>';
            }
            echo '</tbody></table>';
        }

        echo '<h3>' . esc_html__('Publier une nouvelle version', 'sia-license-server') . '</h3>';
        echo '<form method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '" class="card" style="max-width:820px;padding:16px 20px;">';
        wp_nonce_field('sia_ls_release');
        echo '<input type="hidden" name="action" value="sia_ls_release">';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th>' . esc_html__('Produit', 'sia-license-server') . '</th><td><input type="text" name="product" class="regular-text" value="school-ia"></td></tr>';
        echo '<tr><th>' . esc_html__('Version', 'sia-license-server') . '</th><td><input type="text" name="version" class="regular-text" placeholder="2.12.0" required></td></tr>';
        echo '<tr><th>' . esc_html__('Paquet .zip', 'sia-license-server') . '</th><td><input type="file" name="package" accept=".zip" required></td></tr>';
        echo '<tr><th>' . esc_html__('Compatible WP (requires)', 'sia-license-server') . '</th><td><input type="text" name="requires" class="small-text" value="6.0"></td></tr>';
        echo '<tr><th>' . esc_html__('PHP min (requires_php)', 'sia-license-server') . '</th><td><input type="text" name="requires_php" class="small-text" value="8.0"></td></tr>';
        echo '<tr><th>' . esc_html__('Testé jusqu\'à (tested)', 'sia-license-server') . '</th><td><input type="text" name="tested" class="small-text" value="6.7"></td></tr>';
        echo '<tr><th>' . esc_html__('Notes de version', 'sia-license-server') . '</th><td><textarea name="changelog" rows="4" class="large-text" placeholder="' . esc_attr__('Nouveautés et corrections…', 'sia-license-server') . '"></textarea></td></tr>';
        echo '</tbody></table>';
        submit_button(__('Publier la version', 'sia-license-server'));
        echo '</form>';
    }

    /* --- Handlers ------------------------------------------------------- */

    public static function handleCreate(): void
    {
        self::guard('sia_ls_create');
        $duration = sanitize_text_field(wp_unslash((string) ($_POST['duration'] ?? '1y')));
        $expires = match ($duration) {
            'lifetime' => '',
            '2y' => gmdate('Y-m-d H:i:s', strtotime('+2 years')),
            default => gmdate('Y-m-d H:i:s', strtotime('+1 year')),
        };
        $key = Store::generateKey();
        Store::createLicense([
            'license_key' => $key,
            'product' => sanitize_text_field(wp_unslash((string) ($_POST['product'] ?? 'school-ia'))),
            'customer_name' => sanitize_text_field(wp_unslash((string) ($_POST['customer_name'] ?? ''))),
            'customer_email' => sanitize_email(wp_unslash((string) ($_POST['customer_email'] ?? ''))),
            'expires_at' => $expires,
            'activation_limit' => (int) ($_POST['activation_limit'] ?? 1),
            'notes' => sanitize_text_field(wp_unslash((string) ($_POST['notes'] ?? ''))),
        ]);
        wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE . '&created=' . rawurlencode($key)));
        exit;
    }

    public static function handleLicenseAction(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        self::guardGet('sia_ls_license_action_' . $id);
        $do = sanitize_key((string) ($_GET['do'] ?? ''));
        match ($do) {
            'disable' => Store::setStatus($id, 'disabled'),
            'enable' => Store::setStatus($id, 'active'),
            'extend' => Store::extendOneYear($id),
            'delete' => Store::deleteLicense($id),
            default => null,
        };
        wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE . '&done=1'));
        exit;
    }

    public static function handleRelease(): void
    {
        self::guard('sia_ls_release');
        require_once ABSPATH . 'wp-admin/includes/file.php';

        $version = sanitize_text_field(wp_unslash((string) ($_POST['version'] ?? '')));
        if ($version === '' || empty($_FILES['package']['name'])) {
            self::redirectErr(__('Version ou fichier manquant.', 'sia-license-server'));
        }

        // Stockage dans un sous-dossier dédié des uploads (hors zone publique indexée).
        $upload = wp_upload_dir();
        $dir = trailingslashit($upload['basedir']) . 'sia-licenses';
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        // Protège le dossier d'un listing direct.
        if (!is_file($dir . '/.htaccess')) {
            @file_put_contents($dir . '/.htaccess', "Options -Indexes\n");
        }

        $overrides = ['test_form' => false, 'mimes' => ['zip' => 'application/zip']];
        $moved = wp_handle_upload($_FILES['package'], $overrides);
        if (!is_array($moved) || !empty($moved['error'])) {
            self::redirectErr(sprintf(__('Échec de l\'envoi : %s', 'sia-license-server'), is_array($moved) ? ($moved['error'] ?? '') : ''));
        }

        $product = sanitize_text_field(wp_unslash((string) ($_POST['product'] ?? 'school-ia')));
        $dest = trailingslashit($dir) . $product . '-' . $version . '.zip';
        @rename($moved['file'], $dest);
        if (!is_file($dest)) {
            $dest = $moved['file']; // repli : garde l'emplacement d'upload
        }

        Store::addRelease([
            'product' => $product,
            'version' => $version,
            'zip_path' => $dest,
            'changelog' => wp_kses_post(wp_unslash((string) ($_POST['changelog'] ?? ''))),
            'requires' => sanitize_text_field(wp_unslash((string) ($_POST['requires'] ?? ''))),
            'requires_php' => sanitize_text_field(wp_unslash((string) ($_POST['requires_php'] ?? ''))),
            'tested' => sanitize_text_field(wp_unslash((string) ($_POST['tested'] ?? ''))),
        ]);
        wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE . '&done=1'));
        exit;
    }

    private static function guard(string $nonce): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden');
        }
        check_admin_referer($nonce);
    }

    private static function guardGet(string $nonce): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden');
        }
        check_admin_referer($nonce);
    }

    private static function redirectErr(string $msg): void
    {
        wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE . '&err=' . rawurlencode($msg)));
        exit;
    }
}

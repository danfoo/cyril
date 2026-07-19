<?php

namespace BemLeadAi\Admin;

defined('ABSPATH') || exit;

/**
 * CRUD générique pour les tables configurables : règles de scoring, triggers,
 * options de financement, variantes de message. Les champs JSON sont édités
 * en clair (textarea) avec validation.
 */
final class CrudPage
{
    /** @return array<string, array> */
    public static function entities(): array
    {
        return [
            'rules' => [
                'title' => __('Règles de scoring', 'bem-lead-ai'),
                'table' => 'bem_scoring_rules',
                'fields' => [
                    'nom' => ['label' => 'Nom', 'type' => 'text'],
                    'type' => ['label' => 'Type', 'type' => 'text', 'default' => 'event'],
                    'condition_json' => ['label' => 'Condition (JSON)', 'type' => 'json'],
                    'poids' => ['label' => 'Poids', 'type' => 'number'],
                    'actif' => ['label' => 'Actif', 'type' => 'bool'],
                ],
                'columns' => ['nom', 'type', 'poids', 'actif'],
            ],
            'triggers' => [
                'title' => __('Triggers', 'bem-lead-ai'),
                'table' => 'bem_triggers',
                'fields' => [
                    'nom' => ['label' => 'Nom', 'type' => 'text'],
                    'condition_json' => ['label' => 'Condition (JSON)', 'type' => 'json'],
                    'actions_json' => ['label' => 'Actions (JSON)', 'type' => 'json'],
                    'cooldown_hours' => ['label' => 'Cooldown (heures)', 'type' => 'number', 'default' => 24],
                    'actif' => ['label' => 'Actif', 'type' => 'bool'],
                ],
                'columns' => ['nom', 'cooldown_hours', 'actif'],
            ],
            'financing' => [
                'title' => __('Options de financement', 'bem-lead-ai'),
                'table' => 'bem_financing_options',
                'fields' => [
                    'formation_label' => ['label' => 'Formation', 'type' => 'text'],
                    'frais_total' => ['label' => 'Frais total', 'type' => 'number'],
                    'devise' => ['label' => 'Devise', 'type' => 'text', 'default' => 'XOF'],
                    'options_paiement' => ['label' => 'Plans de paiement (JSON: [{"label","detail"}])', 'type' => 'json'],
                    'bourses' => ['label' => 'Bourses (JSON: [{"label","detail"}])', 'type' => 'json'],
                    'actif' => ['label' => 'Actif', 'type' => 'bool'],
                ],
                'columns' => ['formation_label', 'frais_total', 'devise', 'actif'],
            ],
            'variants' => [
                'title' => __('Variantes de message', 'bem-lead-ai'),
                'table' => 'bem_message_variants',
                'fields' => [
                    'trigger_key' => ['label' => 'Clé de groupe', 'type' => 'text', 'default' => 'relance_desengagement'],
                    'texte_variante' => ['label' => 'Texte ({prenom}, {formation})', 'type' => 'textarea'],
                    'actif' => ['label' => 'Actif', 'type' => 'bool'],
                ],
                'columns' => ['trigger_key', 'texte_variante', 'impressions', 'conversions', 'actif'],
                'readonly' => ['impressions', 'conversions'],
            ],
        ];
    }

    public function render(string $slug): void
    {
        $config = self::entities()[$slug] ?? null;
        if (!$config) {
            echo '<div class="wrap"><p>Entité inconnue.</p></div>';
            return;
        }
        global $wpdb;
        $table = $wpdb->prefix . $config['table'];
        $editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
        $editRow = $editId ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $editId)) : null;

        $cleanUrl = admin_url('admin.php?page=bem-lead-ai-' . $slug);

        echo '<div class="wrap"><h1>' . esc_html($config['title']) . '</h1>';

        if ($slug === 'variants') {
            echo '<p>' . esc_html__('Le bandit teste ces variantes en continu et privilégie automatiquement celles qui reconvertissent le mieux. Impressions et conversions sont mises à jour automatiquement.', 'bem-lead-ai') . '</p>';
        }

        // Barre d'action : bouton d'ouverture de la modale d'ajout.
        echo '<div class="bem-section-head" style="margin:8px 0 12px;">';
        echo '<h2 style="margin:0;">' . esc_html__('Existants', 'bem-lead-ai') . '</h2>';
        if ($editRow) {
            // En mode édition : repartir d'un ajout vierge = revenir à la page propre.
            echo '<a class="button button-primary" href="' . esc_url($cleanUrl) . '">＋ ' . esc_html__('Ajouter', 'bem-lead-ai') . '</a>';
        } else {
            echo '<button type="button" class="button button-primary" data-bem-modal-open="bem-crud-modal">＋ ' . esc_html__('Ajouter', 'bem-lead-ai') . '</button>';
        }
        echo '</div>';

        // --- Modale de création / édition ---
        $open = $editRow ? ' is-open' : '';
        echo '<div class="bem-modal' . $open . '" id="bem-crud-modal" role="dialog" aria-modal="true" aria-labelledby="bem-crud-modal-title">';
        echo '<div class="bem-modal-overlay" data-bem-modal-close></div>';
        echo '<div class="bem-modal-box">';
        echo '<div class="bem-modal-head"><h2 id="bem-crud-modal-title" style="margin:0;">'
            . ($editRow ? esc_html__('Modifier', 'bem-lead-ai') : esc_html__('Ajouter', 'bem-lead-ai'))
            . '</h2><button type="button" class="bem-modal-close" data-bem-modal-close aria-label="' . esc_attr__('Fermer', 'bem-lead-ai') . '">&times;</button></div>';
        echo '<div class="bem-modal-body">';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('bem_crud_' . $slug);
        echo '<input type="hidden" name="action" value="bem_crud_save"><input type="hidden" name="entity" value="' . esc_attr($slug) . '">';
        echo '<input type="hidden" name="id" value="' . (int) $editId . '"><table class="form-table">';
        foreach ($config['fields'] as $field => $meta) {
            if (in_array($field, $config['readonly'] ?? [], true)) {
                continue;
            }
            $value = $editRow->{$field} ?? ($meta['default'] ?? '');
            echo '<tr><th scope="row"><label>' . esc_html($meta['label']) . '</label></th><td>';
            switch ($meta['type']) {
                case 'json':
                case 'textarea':
                    echo '<textarea name="f[' . esc_attr($field) . ']" rows="' . ($meta['type'] === 'json' ? 4 : 3) . '" class="large-text code">' . esc_textarea((string) $value) . '</textarea>';
                    break;
                case 'bool':
                    echo '<input type="checkbox" name="f[' . esc_attr($field) . ']" value="1" ' . checked(1, (int) $value, false) . '>';
                    break;
                case 'number':
                    echo '<input type="number" step="any" name="f[' . esc_attr($field) . ']" value="' . esc_attr((string) $value) . '" class="regular-text">';
                    break;
                default:
                    echo '<input type="text" name="f[' . esc_attr($field) . ']" value="' . esc_attr((string) $value) . '" class="regular-text">';
            }
            echo '</td></tr>';
        }
        echo '</table>';
        echo '<div class="bem-modal-actions">';
        echo '<button type="button" class="button" data-bem-modal-close>' . esc_html__('Annuler', 'bem-lead-ai') . '</button> ';
        submit_button($editRow ? __('Enregistrer', 'bem-lead-ai') : __('Ajouter', 'bem-lead-ai'), 'primary', 'submit', false);
        echo '</div>';
        echo '</form>';
        echo '</div></div></div>'; // body, box, modal

        $this->modalScript();

        // Liste.
        $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id ASC") ?: [];
        echo '<table class="widefat striped"><thead><tr><th>ID</th>';
        foreach ($config['columns'] as $col) {
            echo '<th>' . esc_html($config['fields'][$col]['label'] ?? $col) . '</th>';
        }
        echo '<th>' . esc_html__('Actions', 'bem-lead-ai') . '</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr><td>' . (int) $row->id . '</td>';
            foreach ($config['columns'] as $col) {
                $val = $row->{$col} ?? '';
                if (($config['fields'][$col]['type'] ?? '') === 'bool') {
                    $val = (int) $val ? '✅' : '—';
                }
                echo '<td>' . esc_html(mb_strimwidth((string) $val, 0, 80, '…')) . '</td>';
            }
            echo '<td><a href="' . esc_url(admin_url('admin.php?page=bem-lead-ai-' . $slug . '&edit=' . (int) $row->id)) . '">' . esc_html__('Modifier', 'bem-lead-ai') . '</a> | ';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline;" onsubmit="return confirm(\'Supprimer ?\');">';
            wp_nonce_field('bem_crud_delete_' . $slug);
            echo '<input type="hidden" name="action" value="bem_crud_delete"><input type="hidden" name="entity" value="' . esc_attr($slug) . '"><input type="hidden" name="id" value="' . (int) $row->id . '">';
            echo '<button type="submit" class="button-link delete" style="color:#b32d2e;">' . esc_html__('Supprimer', 'bem-lead-ai') . '</button></form></td></tr>';
        }
        echo '</tbody></table></div>';
    }

    /** Ouverture/fermeture de la modale (clic bouton, overlay, croix, Échap). */
    private function modalScript(): void
    {
        ?>
        <script>
        (function () {
            var modal = document.getElementById('bem-crud-modal');
            if (!modal) { return; }
            function open() { modal.classList.add('is-open'); document.body.style.overflow = 'hidden'; }
            function close() { modal.classList.remove('is-open'); document.body.style.overflow = ''; }
            document.querySelectorAll('[data-bem-modal-open="bem-crud-modal"]').forEach(function (b) {
                b.addEventListener('click', open);
            });
            modal.querySelectorAll('[data-bem-modal-close]').forEach(function (b) {
                b.addEventListener('click', close);
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal.classList.contains('is-open')) { close(); }
            });
            // Si la modale est déjà ouverte (mode édition), verrouille le défilement.
            if (modal.classList.contains('is-open')) { document.body.style.overflow = 'hidden'; }
        })();
        </script>
        <?php
    }

    public static function handleSave(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden');
        }
        $slug = sanitize_key((string) ($_POST['entity'] ?? ''));
        check_admin_referer('bem_crud_' . $slug);
        $config = self::entities()[$slug] ?? null;
        if (!$config) {
            wp_die('Entité inconnue');
        }

        global $wpdb;
        $table = $wpdb->prefix . $config['table'];
        $input = (array) ($_POST['f'] ?? []);
        $data = [];
        foreach ($config['fields'] as $field => $meta) {
            if (in_array($field, $config['readonly'] ?? [], true)) {
                continue;
            }
            switch ($meta['type']) {
                case 'bool':
                    $data[$field] = isset($input[$field]) ? 1 : 0;
                    break;
                case 'number':
                    $data[$field] = (float) ($input[$field] ?? 0);
                    break;
                case 'json':
                    $raw = trim((string) ($input[$field] ?? ''));
                    $decoded = $raw === '' ? [] : json_decode($raw, true);
                    if ($raw !== '' && $decoded === null) {
                        wp_die('JSON invalide pour le champ ' . esc_html($field));
                    }
                    $data[$field] = wp_json_encode($decoded);
                    break;
                case 'textarea':
                    $data[$field] = sanitize_textarea_field((string) ($input[$field] ?? ''));
                    break;
                default:
                    $data[$field] = sanitize_text_field((string) ($input[$field] ?? ''));
            }
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $wpdb->update($table, $data, ['id' => $id]);
        } else {
            $wpdb->insert($table, $data);
        }
        wp_safe_redirect(admin_url('admin.php?page=bem-lead-ai-' . $slug));
        exit;
    }

    public static function handleDelete(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden');
        }
        $slug = sanitize_key((string) ($_POST['entity'] ?? ''));
        check_admin_referer('bem_crud_delete_' . $slug);
        $config = self::entities()[$slug] ?? null;
        if ($config) {
            global $wpdb;
            $wpdb->delete($wpdb->prefix . $config['table'], ['id' => (int) ($_POST['id'] ?? 0)]);
        }
        wp_safe_redirect(admin_url('admin.php?page=bem-lead-ai-' . $slug));
        exit;
    }
}

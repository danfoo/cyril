<?php

namespace BemLeadAi\Admin;

defined('ABSPATH') || exit;

final class AdminMenu
{
    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenus']);
        add_action('admin_post_bem_save_settings', [SettingsPage::class, 'handleSave']);
        add_action('admin_post_bem_crud_save', [CrudPage::class, 'handleSave']);
        add_action('admin_post_bem_crud_delete', [CrudPage::class, 'handleDelete']);
        add_action('admin_post_bem_handoff_reply', [InboxPage::class, 'handleReply']);
        add_action('admin_post_bem_handoff_close', [InboxPage::class, 'handleClose']);
        add_action('admin_post_bem_rebuild_kb', [SettingsPage::class, 'handleRebuildKb']);
        add_action('admin_post_bem_test_claude', [SettingsPage::class, 'handleTestClaude']);
        add_action('admin_post_bem_lead_delete', [DashboardPage::class, 'handleLeadDelete']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdmin']);
    }

    /** Charge le sélecteur média WordPress sur l'écran de réglages (avatar/logo). */
    public function enqueueAdmin(string $hook): void
    {
        if (str_contains($hook, 'bem-lead-ai-settings')) {
            wp_enqueue_media();
        }
    }

    public function addMenus(): void
    {
        add_menu_page(
            'BEM Lead AI',
            'BEM Lead AI',
            'edit_posts',
            'bem-lead-ai',
            [new DashboardPage(), 'render'],
            'dashicons-networking',
            26
        );
        add_submenu_page('bem-lead-ai', __('Tableau de bord', 'bem-lead-ai'), __('Tableau de bord', 'bem-lead-ai'), 'edit_posts', 'bem-lead-ai', [new DashboardPage(), 'render']);
        add_submenu_page('bem-lead-ai', __('Leads', 'bem-lead-ai'), __('Leads', 'bem-lead-ai'), 'edit_posts', 'bem-lead-ai-leads', [new DashboardPage(), 'renderLeads']);
        add_submenu_page('bem-lead-ai', __('Inbox conseiller', 'bem-lead-ai'), __('Inbox conseiller', 'bem-lead-ai'), 'edit_posts', 'bem-lead-ai-inbox', [new InboxPage(), 'render']);
        add_submenu_page('bem-lead-ai', __('Veille concurrentielle', 'bem-lead-ai'), __('Veille concurrentielle', 'bem-lead-ai'), 'edit_posts', 'bem-lead-ai-competitors', [new CompetitorsPage(), 'render']);

        foreach (CrudPage::entities() as $slug => $config) {
            add_submenu_page('bem-lead-ai', $config['title'], $config['title'], 'manage_options', 'bem-lead-ai-' . $slug, fn() => (new CrudPage())->render($slug));
        }

        add_submenu_page('bem-lead-ai', __('Réglages', 'bem-lead-ai'), __('Réglages', 'bem-lead-ai'), 'manage_options', 'bem-lead-ai-settings', [new SettingsPage(), 'render']);
    }
}

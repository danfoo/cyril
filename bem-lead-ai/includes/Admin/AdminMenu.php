<?php

namespace BemLeadAi\Admin;

defined('ABSPATH') || exit;

final class AdminMenu
{
    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenus']);
        add_action('in_admin_header', [$this, 'brandBar']);
        add_action('admin_footer', [$this, 'brandFooter']);
        // Assistant de configuration (premier lancement + réexécutable).
        add_action('admin_init', [SetupWizard::class, 'maybeRedirect']);
        add_action('admin_notices', [SetupWizard::class, 'notice']);
        add_action('admin_post_bem_setup_save', [SetupWizard::class, 'handleSave']);
        add_action('admin_post_bem_setup_finish', [SetupWizard::class, 'handleFinish']);
        add_action('admin_post_bem_setup_skip', [SetupWizard::class, 'handleSkip']);
        add_action('admin_post_bem_save_settings', [SettingsPage::class, 'handleSave']);
        add_action('admin_post_bem_crud_save', [CrudPage::class, 'handleSave']);
        add_action('admin_post_bem_crud_delete', [CrudPage::class, 'handleDelete']);
        add_action('admin_post_bem_handoff_reply', [InboxPage::class, 'handleReply']);
        add_action('admin_post_bem_handoff_close', [InboxPage::class, 'handleClose']);
        add_action('admin_post_bem_rebuild_kb', [SettingsPage::class, 'handleRebuildKb']);
        add_action('admin_post_bem_test_claude', [SettingsPage::class, 'handleTestClaude']);
        add_action('admin_post_bem_test_email', [SettingsPage::class, 'handleTestEmail']);
        add_action('admin_post_bem_lead_delete', [DashboardPage::class, 'handleLeadDelete']);
        add_action('admin_post_bem_purge_leads', [DashboardPage::class, 'handlePurgeLeads']);
        add_action('admin_post_bem_purge_anon', [DashboardPage::class, 'handlePurgeAnonymous']);
        // Export de la liste des leads (CSV / Excel), filtres conservés.
        add_action('admin_post_bem_export_leads', [LeadExporter::class, 'handleExport']);
        // Licence (abonnement annuel) : activation, désactivation, revalidation.
        add_action('admin_notices', ['\BemLeadAi\License\LicensePage', 'notice']);
        add_action('admin_post_bem_license_activate', ['\BemLeadAi\License\LicensePage', 'handleActivate']);
        add_action('admin_post_bem_license_deactivate', ['\BemLeadAi\License\LicensePage', 'handleDeactivate']);
        add_action('admin_post_bem_license_refresh', ['\BemLeadAi\License\LicensePage', 'handleRefresh']);
        // CRM natif : actions de suivi sur la fiche lead.
        add_action('admin_post_bem_crm_stage', [DashboardPage::class, 'handleCrmStage']);
        add_action('admin_post_bem_crm_assign', [DashboardPage::class, 'handleCrmAssign']);
        add_action('admin_post_bem_crm_note', [DashboardPage::class, 'handleCrmNote']);
        add_action('admin_post_bem_crm_task', [DashboardPage::class, 'handleCrmTask']);
        add_action('admin_post_bem_crm_task_toggle', [DashboardPage::class, 'handleCrmTaskToggle']);
        add_action('admin_post_bem_crm_activity_delete', [DashboardPage::class, 'handleCrmActivityDelete']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdmin']);
    }

    /** Assets d'admin : habillage des pages du plugin + sélecteur média sur les réglages. */
    public function enqueueAdmin(string $hook): void
    {
        if (!str_contains($hook, 'bem-lead-ai')) {
            return;
        }
        $file = BEM_LEAD_AI_DIR . 'assets/css/admin.css';
        $ver = is_file($file) ? (string) filemtime($file) : BEM_LEAD_AI_VERSION;
        wp_enqueue_style('bem-lead-ai-admin', BEM_LEAD_AI_URL . 'assets/css/admin.css', [], $ver);
        add_filter('admin_body_class', static fn($c) => $c . ' bem-admin-page');

        if (str_contains($hook, 'bem-lead-ai-settings') || str_contains($hook, SetupWizard::PAGE)) {
            wp_enqueue_media();
        }
    }

    /** Barre de marque (School IA) en haut des pages du plugin uniquement. */
    public function brandBar(): void
    {
        if ($this->onPluginPage()) {
            Branding::bar();
        }
    }

    /** Pied de page « Version du CRM » sur les pages du plugin. */
    public function brandFooter(): void
    {
        if ($this->onPluginPage()) {
            Branding::footer();
        }
    }

    private function onPluginPage(): bool
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        return $screen && str_contains((string) $screen->id, 'bem-lead-ai');
    }

    public function addMenus(): void
    {
        add_menu_page(
            Branding::name(),
            Branding::name(),
            'edit_posts',
            'bem-lead-ai',
            [new DashboardPage(), 'render'],
            Branding::menuIcon(),
            26
        );
        // Pas de callback ici : ce sous-menu partage le slug du menu principal.
        // En repasser un enregistrerait le rendu DEUX fois sur le même hook
        // (d'où le tableau de bord affiché en double).
        add_submenu_page('bem-lead-ai', __('Tableau de bord', 'bem-lead-ai'), __('Tableau de bord', 'bem-lead-ai'), 'edit_posts', 'bem-lead-ai');
        add_submenu_page('bem-lead-ai', __('Leads', 'bem-lead-ai'), __('Leads', 'bem-lead-ai'), 'edit_posts', 'bem-lead-ai-leads', [new DashboardPage(), 'renderLeads']);
        add_submenu_page('bem-lead-ai', __('Inbox conseiller', 'bem-lead-ai'), __('Inbox conseiller', 'bem-lead-ai'), 'edit_posts', 'bem-lead-ai-inbox', [new InboxPage(), 'render']);
        add_submenu_page('bem-lead-ai', __('Veille concurrentielle', 'bem-lead-ai'), __('Veille concurrentielle', 'bem-lead-ai'), 'edit_posts', 'bem-lead-ai-competitors', [new CompetitorsPage(), 'render']);

        foreach (CrudPage::entities() as $slug => $config) {
            add_submenu_page('bem-lead-ai', $config['title'], $config['title'], 'manage_options', 'bem-lead-ai-' . $slug, fn() => (new CrudPage())->render($slug));
        }

        add_submenu_page('bem-lead-ai', __('Réglages', 'bem-lead-ai'), __('Réglages', 'bem-lead-ai'), 'manage_options', 'bem-lead-ai-settings', [new SettingsPage(), 'render']);
        add_submenu_page('bem-lead-ai', __('Assistant de configuration', 'bem-lead-ai'), __('Assistant de configuration', 'bem-lead-ai'), 'manage_options', SetupWizard::PAGE, [new SetupWizard(), 'render']);
        add_submenu_page('bem-lead-ai', __('Licence', 'bem-lead-ai'), __('Licence', 'bem-lead-ai'), 'manage_options', \BemLeadAi\License\LicensePage::PAGE, [new \BemLeadAi\License\LicensePage(), 'render']);
    }
}

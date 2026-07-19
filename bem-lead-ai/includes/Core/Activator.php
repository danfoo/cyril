<?php

namespace BemLeadAi\Core;

defined('ABSPATH') || exit;

/**
 * Création du schéma et amorçage des règles/triggers/variantes par défaut.
 *
 * Les valeurs par défaut encodent la logique marketing du funnel admissions :
 * les actions à forte intention (frais, candidature, brochure, simulateur)
 * pèsent beaucoup plus lourd qu'une simple page vue.
 */
final class Activator
{
    /** Activation du plugin (hook register_activation_hook). */
    public static function activate(): void
    {
        self::runMigrations();
        flush_rewrite_rules();
    }

    /**
     * Migration idempotente exécutée à l'activation ET automatiquement lorsque
     * la version stockée diffère de la version du code (mise à jour par simple
     * remplacement des fichiers, sans désactivation/réactivation manuelle).
     * `dbDelta` aligne le schéma, les seeds ne se dupliquent pas, les crons ne
     * se re-planifient pas s'ils existent déjà.
     */
    public static function runMigrations(): void
    {
        self::createTables(); // dbDelta : idempotent
        self::seedDefaults(); // insère seulement si vide
        Options::ensureDefaults();
        self::migrateSchoolName();

        if (!wp_next_scheduled('bem_lead_ai_cron_disengagement')) {
            wp_schedule_event(time() + 300, 'hourly', 'bem_lead_ai_cron_disengagement');
        }
        self::syncKbCron();
        if (!wp_next_scheduled('bem_lead_ai_cron_bandit')) {
            wp_schedule_event(time() + 900, 'hourly', 'bem_lead_ai_cron_bandit');
        }
        if (!wp_next_scheduled('bem_lead_ai_cron_crm_tasks')) {
            // Rappels quotidiens des tâches de suivi à échéance.
            wp_schedule_event(time() + 1200, 'daily', 'bem_lead_ai_cron_crm_tasks');
        }

        // (Re)construction du catalogue différée : à `plugins_loaded` les CPT
        // (ex. « formation ») ne sont pas encore enregistrés. On la planifie ;
        // KnowledgeBaseBuilder reconstruit aussi paresseusement au 1er accès.
        Queue::dispatchIn(15, 'bem_lead_ai_job_rebuild_kb');

        // Écriture cache-safe de la version, sinon needsUpgrade() pourrait
        // rester vrai (cache d'objet périmé) et relancer la migration à chaque requête.
        update_option('bem_lead_ai_db_version', BEM_LEAD_AI_VERSION, false);
        if (function_exists('wp_cache_delete')) {
            wp_cache_delete('bem_lead_ai_db_version', 'options');
            wp_cache_delete('alloptions', 'options');
        }
    }

    /**
     * Corrige l'ancien nom d'école « BEM Dakar » resté figé dans les réglages
     * enregistrés (message d'accueil, message WhatsApp pré-rempli) sur les
     * installations créées avant que le nom devienne configurable.
     *
     * Sûr et ciblé : on ne remplace QUE la sous-chaîne exacte « BEM Dakar » par
     * le nom d'école configuré — les textes personnalisés par l'utilisateur ne
     * sont pas touchés. Idempotent (plus de « BEM Dakar » → aucune écriture).
     */
    private static function migrateSchoolName(): void
    {
        $school = trim((string) Options::get('school_name')) ?: 'BEM Conakry';
        if ($school === 'BEM Dakar') {
            return;
        }
        $patch = [];
        foreach (['widget_greeting', 'whatsapp_prefill'] as $key) {
            $value = (string) Options::get($key);
            if (str_contains($value, 'BEM Dakar')) {
                $patch[$key] = str_replace('BEM Dakar', $school, $value);
            }
        }
        if ($patch) {
            Options::update($patch);
        }
    }

    /**
     * (Re)planifie la reconstruction périodique du catalogue selon le réglage
     * `kb_rebuild_frequency` (manual | weekly | daily). À appeler après tout
     * changement de ce réglage. « manual » = aucune tâche planifiée (le
     * catalogue reste reconstruit à chaque modification de contenu + bouton).
     */
    public static function syncKbCron(): void
    {
        $freq = (string) Options::get('kb_rebuild_frequency');
        $recurrence = in_array($freq, ['daily', 'weekly'], true) ? $freq : '';
        $current = wp_get_schedule('bem_lead_ai_cron_rebuild_kb'); // false si non planifié

        if ($recurrence === '') {
            if ($current !== false) {
                wp_clear_scheduled_hook('bem_lead_ai_cron_rebuild_kb');
            }
            return;
        }
        if ($current !== $recurrence) {
            wp_clear_scheduled_hook('bem_lead_ai_cron_rebuild_kb');
            wp_schedule_event(time() + 600, $recurrence, 'bem_lead_ai_cron_rebuild_kb');
        }
    }

    /** True si le code est plus récent que ce qui a été migré en base. */
    public static function needsUpgrade(): bool
    {
        return get_option('bem_lead_ai_db_version') !== BEM_LEAD_AI_VERSION;
    }

    private static function createTables(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $p = $wpdb->prefix;

        $sql = [];

        $sql[] = "CREATE TABLE {$p}bem_leads (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id VARCHAR(64) NOT NULL,
            channels VARCHAR(40) NOT NULL DEFAULT 'web',
            email VARCHAR(190) NULL,
            phone VARCHAR(40) NULL,
            prenom VARCHAR(100) NULL,
            formation_interet VARCHAR(190) NULL,
            score_comportemental FLOAT NOT NULL DEFAULT 0,
            score_intention FLOAT NOT NULL DEFAULT 0,
            score_final FLOAT NOT NULL DEFAULT 0,
            statut VARCHAR(20) NOT NULL DEFAULT 'prospect',
            pipeline_stage VARCHAR(20) NOT NULL DEFAULT 'nouveau',
            owner_id BIGINT UNSIGNED NULL,
            next_action_at DATETIME NULL,
            kb_mode VARCHAR(20) NOT NULL DEFAULT 'formations',
            consent TINYINT(1) NOT NULL DEFAULT 0,
            handoff_active TINYINT(1) NOT NULL DEFAULT 0,
            signals LONGTEXT NULL,
            crm_id_perfex VARCHAR(64) NULL,
            crm_id_hubspot VARCHAR(64) NULL,
            first_seen DATETIME NOT NULL,
            last_seen DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY session_id (session_id),
            KEY email (email),
            KEY score_final (score_final),
            KEY statut (statut),
            KEY pipeline_stage (pipeline_stage),
            KEY owner_id (owner_id)
        ) $charset;";

        $sql[] = "CREATE TABLE {$p}bem_crm_activities (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            lead_id BIGINT UNSIGNED NOT NULL,
            type VARCHAR(20) NOT NULL,
            content LONGTEXT NULL,
            author_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            due_at DATETIME NULL,
            done TINYINT(1) NOT NULL DEFAULT 0,
            meta LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY lead_created (lead_id, created_at),
            KEY task_due (type, done, due_at)
        ) $charset;";

        $sql[] = "CREATE TABLE {$p}bem_events (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            lead_id BIGINT UNSIGNED NOT NULL,
            type VARCHAR(40) NOT NULL,
            canal VARCHAR(12) NOT NULL DEFAULT 'web',
            payload LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY lead_created (lead_id, created_at),
            KEY type (type)
        ) $charset;";

        $sql[] = "CREATE TABLE {$p}bem_chat_messages (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            lead_id BIGINT UNSIGNED NOT NULL,
            canal VARCHAR(12) NOT NULL DEFAULT 'web',
            role VARCHAR(12) NOT NULL,
            contenu LONGTEXT NOT NULL,
            classified TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY lead_created (lead_id, created_at)
        ) $charset;";

        $sql[] = "CREATE TABLE {$p}bem_scoring_rules (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            nom VARCHAR(190) NOT NULL,
            type VARCHAR(20) NOT NULL DEFAULT 'event',
            condition_json LONGTEXT NOT NULL,
            poids FLOAT NOT NULL DEFAULT 1,
            actif TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY  (id)
        ) $charset;";

        $sql[] = "CREATE TABLE {$p}bem_triggers (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            nom VARCHAR(190) NOT NULL,
            condition_json LONGTEXT NOT NULL,
            actions_json LONGTEXT NOT NULL,
            cooldown_hours INT NOT NULL DEFAULT 24,
            actif TINYINT(1) NOT NULL DEFAULT 1,
            last_run DATETIME NULL,
            PRIMARY KEY  (id)
        ) $charset;";

        $sql[] = "CREATE TABLE {$p}bem_competitor_mentions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            lead_id BIGINT UNSIGNED NOT NULL,
            nom_concurrent VARCHAR(190) NOT NULL,
            extrait_contexte TEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY nom_concurrent (nom_concurrent)
        ) $charset;";

        $sql[] = "CREATE TABLE {$p}bem_financing_options (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            formation_id BIGINT UNSIGNED NULL,
            formation_label VARCHAR(190) NOT NULL,
            frais_total BIGINT UNSIGNED NOT NULL DEFAULT 0,
            devise VARCHAR(8) NOT NULL DEFAULT 'XOF',
            options_paiement LONGTEXT NULL,
            bourses LONGTEXT NULL,
            actif TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY  (id)
        ) $charset;";

        $sql[] = "CREATE TABLE {$p}bem_message_variants (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            trigger_key VARCHAR(60) NOT NULL,
            texte_variante TEXT NOT NULL,
            impressions INT UNSIGNED NOT NULL DEFAULT 0,
            conversions INT UNSIGNED NOT NULL DEFAULT 0,
            actif TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY  (id),
            KEY trigger_key (trigger_key)
        ) $charset;";

        $sql[] = "CREATE TABLE {$p}bem_handoffs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            lead_id BIGINT UNSIGNED NOT NULL,
            motif VARCHAR(190) NOT NULL,
            conseiller_id BIGINT UNSIGNED NULL,
            statut VARCHAR(20) NOT NULL DEFAULT 'open',
            opened_at DATETIME NOT NULL,
            closed_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY statut (statut),
            KEY lead_id (lead_id)
        ) $charset;";

        foreach ($sql as $statement) {
            dbDelta($statement);
        }
        // La version est écrite (cache-safe) par runMigrations().
    }

    /**
     * Règles, triggers et variantes par défaut.
     * Pondérations = intensité du signal d'achat dans un funnel admissions :
     * consulter les frais ou candidater vaut bien plus qu'une visite de page.
     */
    private static function seedDefaults(): void
    {
        global $wpdb;
        $p = $wpdb->prefix;

        if ((int) $wpdb->get_var("SELECT COUNT(*) FROM {$p}bem_scoring_rules") === 0) {
            $rules = [
                // [nom, type, condition, poids]
                ['Page vue (générique)', 'event', ['event_type' => 'page_view'], 1],
                ['Page formation consultée', 'event', ['event_type' => 'page_view', 'payload.page_kind' => 'formation'], 4],
                ['Page frais / tarifs consultée', 'event', ['event_type' => 'page_view', 'payload.page_kind' => 'pricing'], 8],
                ['Page admission / candidature consultée', 'event', ['event_type' => 'page_view', 'payload.page_kind' => 'admission'], 10],
                ['Temps long sur une formation (≥ 2 min)', 'event', ['event_type' => 'time_on_page', 'payload.page_kind' => 'formation', 'payload.seconds_gte' => 120], 5],
                ['Clic CTA candidature', 'event', ['event_type' => 'cta_click'], 12],
                ['Téléchargement de brochure', 'event', ['event_type' => 'brochure_download'], 15],
                ['Simulation de financement effectuée', 'event', ['event_type' => 'financing_simulated'], 12],
                ['Visite de retour (revient sur le site)', 'event', ['event_type' => 'return_visit'], 6],
                ['Message envoyé au conseiller IA', 'event', ['event_type' => 'chat_message'], 3],
                ['Email capturé', 'event', ['event_type' => 'email_captured'], 20],
                ['Téléphone capturé', 'event', ['event_type' => 'phone_captured'], 15],
                ['Formulaire soumis (Gravity Forms, CF7…)', 'event', ['event_type' => 'form_submitted'], 18],
                ['Bascule vers WhatsApp (forte intention)', 'event', ['event_type' => 'whatsapp_handoff_clicked'], 14],
            ];
            foreach ($rules as [$nom, $type, $cond, $poids]) {
                $wpdb->insert("{$p}bem_scoring_rules", [
                    'nom' => $nom,
                    'type' => $type,
                    'condition_json' => wp_json_encode($cond),
                    'poids' => $poids,
                    'actif' => 1,
                ]);
            }
        }

        if ((int) $wpdb->get_var("SELECT COUNT(*) FROM {$p}bem_triggers") === 0) {
            $triggers = [
                [
                    'Lead chaud → CRM + notification admissions',
                    ['all' => [['field' => 'score_final', 'op' => '>=', 'value' => 60], ['field' => 'statut', 'op' => '=', 'value' => 'prospect']]],
                    [['type' => 'crm_sync'], ['type' => 'notify', 'subject' => 'Lead chaud détecté']],
                    24,
                ],
                [
                    'Lead très chaud → alerte prioritaire + résumé CRM',
                    ['all' => [['field' => 'score_final', 'op' => '>=', 'value' => 80], ['field' => 'statut', 'op' => '=', 'value' => 'prospect']]],
                    [['type' => 'crm_sync'], ['type' => 'summarize'], ['type' => 'notify', 'subject' => '🔥 Lead très chaud — contacter sous 24 h']],
                    24,
                ],
                [
                    'Urgence détectée → escalade humaine immédiate',
                    ['all' => [['field' => 'signal.urgency', 'op' => '=', 'value' => 'high']]],
                    [['type' => 'escalate', 'motif' => 'Urgence détectée dans la conversation'], ['type' => 'summarize']],
                    1,
                ],
                [
                    'Sensibilité prix → proposer le simulateur de financement',
                    ['all' => [['field' => 'signal.price_sensitivity', 'op' => '=', 'value' => true]]],
                    [['type' => 'financing_offer']],
                    48,
                ],
                [
                    'Désengagement → relance automatique (bandit)',
                    ['all' => [['field' => 'event.type', 'op' => '=', 'value' => 'disengagement']]],
                    [['type' => 'followup', 'variant_group' => 'relance_desengagement']],
                    72,
                ],
                [
                    'Inscription CRM → bascule base de connaissance onboarding',
                    ['all' => [['field' => 'event.type', 'op' => '=', 'value' => 'crm_status_inscrit']]],
                    [['type' => 'switch_kb', 'kb' => 'onboarding'], ['type' => 'notify', 'subject' => 'Lead converti — passage en onboarding']],
                    0,
                ],
            ];
            foreach ($triggers as [$nom, $cond, $actions, $cooldown]) {
                $wpdb->insert("{$p}bem_triggers", [
                    'nom' => $nom,
                    'condition_json' => wp_json_encode($cond),
                    'actions_json' => wp_json_encode($actions),
                    'cooldown_hours' => $cooldown,
                    'actif' => 1,
                ]);
            }
        }

        if ((int) $wpdb->get_var("SELECT COUNT(*) FROM {$p}bem_message_variants") === 0) {
            // Trois angles marketing classiques : aide/valeur, urgence douce, preuve sociale.
            $variants = [
                ['relance_desengagement', "Bonjour {prenom} 👋 Vous vous étiez renseigné·e sur {formation} à BEM Dakar. Avez-vous des questions restées sans réponse ? Je peux vous aider sur le programme, les débouchés ou le financement — il suffit de répondre ici."],
                ['relance_desengagement', "Bonjour {prenom}, les candidatures pour {formation} avancent vite et les places sont limitées. Si le programme vous intéresse toujours, c'est le bon moment pour finaliser votre dossier — je peux vous guider étape par étape."],
                ['relance_desengagement', "Bonjour {prenom} ! Chaque année, des centaines d'étudiants rejoignent BEM Conakry après s'être posé les mêmes questions que vous sur {formation}. Voulez-vous que je vous mette en relation avec l'équipe admissions pour un échange rapide ?"],
            ];
            foreach ($variants as [$key, $texte]) {
                $wpdb->insert("{$p}bem_message_variants", [
                    'trigger_key' => $key,
                    'texte_variante' => $texte,
                    'actif' => 1,
                ]);
            }
        }
    }
}

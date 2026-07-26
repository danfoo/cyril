<?php

defined('BASEPATH') or exit('No direct script access allowed');

class School_ia_bridge_model extends App_Model
{
    private function table(): string
    {
        return db_prefix() . 'school_ia_leads';
    }

    private function activityTable(): string
    {
        return db_prefix() . 'school_ia_activities';
    }

    private function chatTable(): string
    {
        return db_prefix() . 'school_ia_chat_messages';
    }

    private function competitorTable(): string
    {
        return db_prefix() . 'school_ia_competitors';
    }

    private function battlecardTable(): string
    {
        return db_prefix() . 'school_ia_battlecards';
    }

    /** Normalise une URL de site (schéma + slash final) pour comparer http/https sans faux négatif. */
    private function normalizeSite(string $url): string
    {
        $url = trim((string) preg_replace('#^https?://#i', '', trim($url)));
        return rtrim($url, '/');
    }

    /** Comparaison source_site insensible au schéma http(s) et au slash final, en SQL. */
    private function siteMatchSql(): string
    {
        return "REPLACE(REPLACE(TRIM(TRAILING '/' FROM source_site), 'https://', ''), 'http://', '')";
    }

    /** Étapes du pipeline d'admission (slug => [label, couleur]). */
    public function stages(): array
    {
        return [
            'nouveau'     => ['Nouveau', '#2e6ff2'],
            'contacte'    => ['Contacté', '#4f86f5'],
            'qualifie'    => ['Qualifié', '#63a4ff'],
            'relance'     => ['Relance', '#d6a63a'],
            'candidature' => ['Candidature', '#8a63d2'],
            'inscrit'     => ['Inscrit', '#0a8f5b'],
            'perdu'       => ['Perdu', '#d64545'],
        ];
    }

    public function stageLabel(string $slug): string
    {
        $s = $this->stages();
        return $s[$slug][0] ?? ucfirst($slug);
    }

    public function stageColor(string $slug): string
    {
        $s = $this->stages();
        return $s[$slug][1] ?? '#888';
    }

    /**
     * Ajoute les colonnes/tables CRM si besoin (sans migration : ALTER/CREATE
     * conditionnels exécutés à la volée).
     */
    public function ensure_schema(): void
    {
        // Une seule exécution par requête : évite un double ALTER (et l'erreur
        // « Duplicate column ») dû au cache des noms de colonnes de CodeIgniter.
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        if (!$this->db->field_exists('stage', $this->table())) {
            $this->db->query('ALTER TABLE `' . $this->table() . "` ADD `stage` VARCHAR(32) NOT NULL DEFAULT 'nouveau'");
        }
        if (!$this->db->field_exists('owner_id', $this->table())) {
            $this->db->query('ALTER TABLE `' . $this->table() . '` ADD `owner_id` INT NULL DEFAULT NULL');
        }
        if (!$this->db->field_exists('rentree', $this->table())) {
            $this->db->query('ALTER TABLE `' . $this->table() . '` ADD `rentree` VARCHAR(32) NULL DEFAULT NULL');
        }
        // Drapeau : conseillers déjà prévenus du démarrage de la conversation
        // (évite de renvoyer un e-mail à chaque message).
        if (!$this->db->field_exists('conseiller_notified', $this->table())) {
            $this->db->query('ALTER TABLE `' . $this->table() . '` ADD `conseiller_notified` TINYINT(1) NOT NULL DEFAULT 0');
        }
        // Nom du formulaire d'origine (pour le suivi : d'où vient le lead).
        if (!$this->db->field_exists('source_form', $this->table())) {
            $this->db->query('ALTER TABLE `' . $this->table() . '` ADD `source_form` VARCHAR(191) NULL DEFAULT NULL');
        }
        // Motif de perte (renseigné au passage en « Perdu ») pour l'analyse.
        if (!$this->db->field_exists('lost_reason', $this->table())) {
            $this->db->query('ALTER TABLE `' . $this->table() . '` ADD `lost_reason` VARCHAR(191) NULL DEFAULT NULL');
        }
        // Date de conversion (passage en « Inscrit ») pour le délai de conversion.
        if (!$this->db->field_exists('converted_at', $this->table())) {
            $this->db->query('ALTER TABLE `' . $this->table() . '` ADD `converted_at` DATETIME NULL DEFAULT NULL');
        }
        // Attribution de campagne (UTM) : canal marketing d'où vient le lead.
        foreach (['utm_source' => 100, 'utm_medium' => 100, 'utm_campaign' => 150] as $col => $len) {
            if (!$this->db->field_exists($col, $this->table())) {
                $this->db->query('ALTER TABLE `' . $this->table() . '` ADD `' . $col . '` VARCHAR(' . $len . ') NULL DEFAULT NULL');
            }
        }
        if (!$this->db->table_exists(db_prefix() . 'school_ia_tasks')) {
            $this->db->query('CREATE TABLE `' . db_prefix() . "school_ia_tasks` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `lead_id` int(11) NOT NULL,
                `title` varchar(255) NOT NULL,
                `due_at` datetime DEFAULT NULL,
                `done` tinyint(1) NOT NULL DEFAULT 0,
                `staff_id` int(11) DEFAULT NULL,
                `created_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `lead_id` (`lead_id`),
                KEY `done` (`done`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        }
        // Tables « tâches » créées avant l'ajout de l'heure : migrer due_date → due_at.
        if ($this->db->table_exists(db_prefix() . 'school_ia_tasks')
            && !$this->db->field_exists('due_at', db_prefix() . 'school_ia_tasks')) {
            $this->db->query('ALTER TABLE `' . db_prefix() . 'school_ia_tasks` ADD `due_at` DATETIME NULL DEFAULT NULL');
            if ($this->db->field_exists('due_date', db_prefix() . 'school_ia_tasks')) {
                $this->db->query('UPDATE `' . db_prefix() . 'school_ia_tasks` SET `due_at` = `due_date` WHERE `due_at` IS NULL AND `due_date` IS NOT NULL');
            }
        }
        // Colonne « rappelé » (rappels automatiques).
        if ($this->db->table_exists(db_prefix() . 'school_ia_tasks')
            && !$this->db->field_exists('reminded', db_prefix() . 'school_ia_tasks')) {
            $this->db->query('ALTER TABLE `' . db_prefix() . 'school_ia_tasks` ADD `reminded` TINYINT(1) NOT NULL DEFAULT 0');
        }
        // Colonne « priorité » (haute / moyenne / basse).
        if ($this->db->table_exists(db_prefix() . 'school_ia_tasks')
            && !$this->db->field_exists('priority', db_prefix() . 'school_ia_tasks')) {
            $this->db->query('ALTER TABLE `' . db_prefix() . "school_ia_tasks` ADD `priority` VARCHAR(10) NOT NULL DEFAULT 'moyenne'");
        }
        if (!$this->db->table_exists(db_prefix() . 'school_ia_documents')) {
            $this->db->query('CREATE TABLE `' . db_prefix() . "school_ia_documents` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `program` varchar(191) DEFAULT NULL,
                `title` varchar(255) NOT NULL,
                `orig_name` varchar(255) DEFAULT NULL,
                `stored_name` varchar(255) NOT NULL,
                `mime` varchar(128) DEFAULT NULL,
                `filesize` int(11) DEFAULT 0,
                `staff_id` int(11) DEFAULT NULL,
                `uploaded_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `program` (`program`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        }
        if (!$this->db->table_exists(db_prefix() . 'school_ia_reports')) {
            $this->db->query('CREATE TABLE `' . db_prefix() . "school_ia_reports` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `period` varchar(10) NOT NULL DEFAULT 'month',
                `label` varchar(120) DEFAULT NULL,
                `date_from` datetime DEFAULT NULL,
                `date_to` datetime DEFAULT NULL,
                `content` longtext DEFAULT NULL,
                `staff_id` int(11) DEFAULT NULL,
                `created_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        }
        if (!$this->db->table_exists(db_prefix() . 'school_ia_messages')) {
            $this->db->query('CREATE TABLE `' . db_prefix() . "school_ia_messages` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `lead_id` int(11) DEFAULT NULL,
                `channel` varchar(10) NOT NULL DEFAULT 'email',
                `campaign` varchar(20) NOT NULL DEFAULT 'single',
                `subject` varchar(255) DEFAULT NULL,
                `token` varchar(32) DEFAULT NULL,
                `status` varchar(12) NOT NULL DEFAULT 'sent',
                `opened_at` datetime DEFAULT NULL,
                `clicks` int(11) NOT NULL DEFAULT 0,
                `staff_id` int(11) DEFAULT NULL,
                `sent_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `token` (`token`),
                KEY `channel` (`channel`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        }
        if (!$this->db->table_exists(db_prefix() . 'school_ia_sequences')) {
            $this->db->query('CREATE TABLE `' . db_prefix() . "school_ia_sequences` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `name` varchar(191) NOT NULL,
                `active` tinyint(1) NOT NULL DEFAULT 1,
                `created_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        }
        if (!$this->db->table_exists(db_prefix() . 'school_ia_sequence_steps')) {
            $this->db->query('CREATE TABLE `' . db_prefix() . "school_ia_sequence_steps` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `sequence_id` int(11) NOT NULL,
                `step_order` int(11) NOT NULL DEFAULT 1,
                `channel` varchar(10) NOT NULL DEFAULT 'email',
                `template_id` int(11) DEFAULT NULL,
                `delay_days` int(11) NOT NULL DEFAULT 0,
                `delay_hours` int(11) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `sequence_id` (`sequence_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        }
        if (!$this->db->table_exists(db_prefix() . 'school_ia_enrollments')) {
            $this->db->query('CREATE TABLE `' . db_prefix() . "school_ia_enrollments` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `sequence_id` int(11) NOT NULL,
                `lead_id` int(11) NOT NULL,
                `status` varchar(12) NOT NULL DEFAULT 'active',
                `next_step_order` int(11) NOT NULL DEFAULT 1,
                `next_run_at` datetime DEFAULT NULL,
                `enrolled_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `status` (`status`),
                KEY `lead_id` (`lead_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        }
        if (!$this->db->table_exists(db_prefix() . 'school_ia_templates')) {
            $this->db->query('CREATE TABLE `' . db_prefix() . "school_ia_templates` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `type` varchar(10) NOT NULL DEFAULT 'email',
                `name` varchar(191) NOT NULL,
                `subject` varchar(255) DEFAULT NULL,
                `body` text DEFAULT NULL,
                `created_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `type` (`type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        }
        if (!$this->db->table_exists($this->activityTable())) {
            $this->db->query('CREATE TABLE `' . $this->activityTable() . "` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `lead_id` int(11) NOT NULL,
                `type` varchar(32) NOT NULL DEFAULT 'note',
                `content` text DEFAULT NULL,
                `staff_id` int(11) DEFAULT NULL,
                `created_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `lead_id` (`lead_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        }
        // Référence optionnelle vers un objet lié (ex. id de tâche) pour les
        // actions rapides depuis le journal.
        if ($this->db->table_exists($this->activityTable())
            && !$this->db->field_exists('ref_id', $this->activityTable())) {
            $this->db->query('ALTER TABLE `' . $this->activityTable() . '` ADD `ref_id` INT NULL DEFAULT NULL');
        }
        if (!$this->db->table_exists($this->chatTable())) {
            $this->db->query('CREATE TABLE `' . $this->chatTable() . "` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `lead_id` int(11) NOT NULL,
                `external_message_id` varchar(32) DEFAULT NULL,
                `role` varchar(12) NOT NULL DEFAULT 'user',
                `canal` varchar(12) NOT NULL DEFAULT 'web',
                `content` longtext DEFAULT NULL,
                `created_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `lead_id` (`lead_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        }
        if (!$this->db->table_exists($this->competitorTable())) {
            $this->db->query('CREATE TABLE `' . $this->competitorTable() . "` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `lead_id` int(11) NOT NULL,
                `external_ref` varchar(40) DEFAULT NULL,
                `name` varchar(191) NOT NULL,
                `context` text DEFAULT NULL,
                `created_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `lead_id` (`lead_id`),
                KEY `name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        }
        // État de traitement d'une mention de concurrent (à traiter / contré).
        if ($this->db->table_exists($this->competitorTable())
            && !$this->db->field_exists('handled', $this->competitorTable())) {
            $this->db->query('ALTER TABLE `' . $this->competitorTable() . '` ADD `handled` TINYINT(1) NOT NULL DEFAULT 0');
        }
        // Argumentaires de contre (« battle cards ») par concurrent.
        if (!$this->db->table_exists($this->battlecardTable())) {
            $this->db->query('CREATE TABLE `' . $this->battlecardTable() . "` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `name` varchar(191) NOT NULL,
                `argument` text DEFAULT NULL,
                `staff_id` int(11) DEFAULT NULL,
                `updated_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        }
        // Campagnes nommées (envois de masse persistés).
        if (!$this->db->table_exists($this->campaignsTable())) {
            $this->db->query('CREATE TABLE `' . $this->campaignsTable() . "` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `name` varchar(191) NOT NULL,
                `channel` varchar(10) NOT NULL DEFAULT 'email',
                `subject` varchar(255) DEFAULT NULL,
                `body` text DEFAULT NULL,
                `filters` text DEFAULT NULL,
                `attachments` varchar(255) DEFAULT NULL,
                `volume` int(11) NOT NULL DEFAULT 0,
                `status` varchar(12) NOT NULL DEFAULT 'sent',
                `staff_id` int(11) DEFAULT NULL,
                `created_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `channel` (`channel`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        }
        // Statut d'envoi de la campagne (queued | sending | sent) — pour l'envoi
        // en arrière-plan par lots.
        if ($this->db->table_exists($this->campaignsTable())
            && !$this->db->field_exists('status', $this->campaignsTable())) {
            $this->db->query('ALTER TABLE `' . $this->campaignsTable() . "` ADD `status` VARCHAR(12) NOT NULL DEFAULT 'sent'");
        }
        // File d'attente des destinataires d'une campagne (envoi par lots via cron).
        if (!$this->db->table_exists($this->queueTable())) {
            $this->db->query('CREATE TABLE `' . $this->queueTable() . "` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `campaign_id` int(11) NOT NULL,
                `lead_id` int(11) NOT NULL,
                `status` varchar(10) NOT NULL DEFAULT 'pending',
                `attempts` tinyint(4) NOT NULL DEFAULT 0,
                `processed_at` datetime DEFAULT NULL,
                `created_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `campaign_id` (`campaign_id`),
                KEY `status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        }
        // Lien message → campagne nommée.
        if ($this->db->table_exists($this->messagesTable())
            && !$this->db->field_exists('campaign_id', $this->messagesTable())) {
            $this->db->query('ALTER TABLE `' . $this->messagesTable() . '` ADD `campaign_id` INT NULL DEFAULT NULL, ADD KEY `campaign_id` (`campaign_id`)');
        }

        $this->seed_default_templates();
    }

    /**
     * Insère une bibliothèque de modèles e-mail/SMS prêts à l'emploi, une seule
     * fois, et seulement si aucun modèle n'existe encore (n'écrase jamais ceux
     * créés par l'utilisateur).
     */
    public function seed_default_templates(): void
    {
        if (get_option('sia_templates_seeded') === '1') {
            return;
        }
        // Une seule fois : ajoute les modèles incontournables sans écraser ni
        // dupliquer ceux déjà présents (comparaison par nom).
        {
            $existing = [];
            foreach ($this->db->select('name')->get($this->templatesTable())->result() as $r) {
                $existing[mb_strtolower(trim((string) $r->name))] = true;
            }
            $ecole = get_option('companyname') ?: 'notre école';
            $defaults = [
                ['email', 'Premier contact — Bienvenue',
                    'Bienvenue {prenom} — votre intérêt pour {formation}',
                    "Bonjour {prenom},\n\nMerci de l'intérêt que vous portez à la formation {formation}. Je suis votre conseiller(ère) d'admission et je vous accompagne à chaque étape de votre projet.\n\nQuand seriez-vous disponible pour un court échange (téléphone ou visio) afin de répondre à vos questions ?\n\nBien cordialement,\nL'équipe Admissions — {$ecole}"],
                ['email', 'Relance — informations formation',
                    'Des informations sur la formation {formation} ?',
                    "Bonjour {prenom},\n\nJe reviens vers vous concernant la formation {formation}. Je peux vous transmettre le programme détaillé, les débouchés et les modalités d'inscription.\n\nSouhaitez-vous que je vous envoie la brochure complète ou que l'on planifie un rendez-vous ?\n\nBien cordialement,\nL'équipe Admissions — {$ecole}"],
                ['email', 'Invitation — Journée Portes Ouvertes',
                    "Invitation : découvrez {$ecole} lors de notre Journée Portes Ouvertes",
                    "Bonjour {prenom},\n\nNous serions ravis de vous accueillir à notre prochaine Journée Portes Ouvertes pour vous présenter la formation {formation}, rencontrer les enseignants et visiter le campus.\n\nConfirmez-nous votre présence en répondant à cet e-mail et nous vous communiquerons le programme détaillé.\n\nÀ très bientôt,\nL'équipe Admissions — {$ecole}"],
                ['email', 'Candidature — documents à fournir',
                    'Votre candidature en {formation} : pièces à fournir',
                    "Bonjour {prenom},\n\nPour finaliser votre candidature en {formation}, voici les pièces à nous transmettre :\n- Pièce d'identité\n- Relevés de notes / diplômes\n- CV et lettre de motivation\n\nVous pouvez répondre à cet e-mail en y joignant vos documents. Je reste à votre disposition pour tout renseignement.\n\nBien cordialement,\nL'équipe Admissions — {$ecole}"],
                ['email', 'Relance — dossier incomplet',
                    'Votre dossier {formation} est presque complet',
                    "Bonjour {prenom},\n\nVotre dossier de candidature pour la formation {formation} est bien avancé, mais il manque encore quelques pièces pour le valider.\n\nPourriez-vous nous les faire parvenir dans les meilleurs délais ? Les places pour la prochaine rentrée sont limitées.\n\nMerci et bien cordialement,\nL'équipe Admissions — {$ecole}"],
                ['email', 'Financement & bourses',
                    'Financer votre formation {formation} : nos solutions',
                    "Bonjour {prenom},\n\nSachez qu'il existe plusieurs solutions pour financer votre formation {formation} : facilités de paiement, bourses et aides.\n\nJe peux vous présenter les options adaptées à votre situation lors d'un court entretien. Quand cela vous conviendrait-il ?\n\nBien cordialement,\nL'équipe Admissions — {$ecole}"],
                ['email', 'Dernière relance — sans réponse',
                    'Toujours intéressé(e) par la formation {formation} ?',
                    "Bonjour {prenom},\n\nJe n'ai pas eu de retour de votre part concernant la formation {formation}. Votre projet est-il toujours d'actualité ?\n\nUn simple mot suffit pour que je reprenne contact et vous accompagne. Sans réponse, je me permettrai de vous rappeler.\n\nBien cordialement,\nL'équipe Admissions — {$ecole}"],
                ['sms', 'Relance courte',
                    null,
                    "Bonjour {prenom}, merci pour votre intérêt pour {formation}. Un conseiller reste a votre disposition. Souhaitez-vous etre rappele(e) ? — {$ecole}"],
                ['sms', 'Rappel de rendez-vous',
                    null,
                    "Bonjour {prenom}, rappel de votre rendez-vous d'admission pour {formation}. A tres bientot ! — {$ecole}"],
                ['sms', 'Dossier — pieces manquantes',
                    null,
                    "Bonjour {prenom}, il manque quelques pieces a votre dossier {formation}. Merci de nous les transmettre rapidement. — {$ecole}"],
            ];
            foreach ($defaults as $d) {
                if (isset($existing[mb_strtolower(trim((string) $d[1]))])) {
                    continue; // un modèle du même nom existe déjà
                }
                $this->db->insert($this->templatesTable(), [
                    'type'       => $d[0],
                    'name'       => $d[1],
                    'subject'    => $d[2],
                    'body'       => $d[3],
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
        update_option('sia_templates_seeded', '1');
    }

    /** Liste les leads reçus (les plus récents d'abord). */
    public function get_leads(int $limit = 300): array
    {
        return $this->db
            ->order_by('received_at', 'desc')
            ->limit($limit)
            ->get($this->table())
            ->result();
    }

    /** Recherche + filtres (boîte de réception), avec le conseiller assigné. */
    public function search(array $f, int $limit = 300): array
    {
        $this->ensure_schema();
        $this->db
            ->select('l.*, CONCAT(s.firstname, " ", s.lastname) AS owner_name')
            ->from($this->table() . ' l')
            ->join(db_prefix() . 'staff s', 's.staffid = l.owner_id', 'left');

        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $this->db->group_start()
                ->like('l.name', $q)->or_like('l.email', $q)
                ->or_like('l.formation', $q)->or_like('l.phone', $q)
                ->group_end();
        }
        if (!empty($f['stage'])) {
            $this->db->where('l.stage', $f['stage']);
        }
        if (isset($f['min_score']) && $f['min_score'] !== '') {
            $this->db->where('l.score >=', (float) $f['min_score']);
        }
        if (!empty($f['rentree'])) {
            $this->db->where('l.rentree', $f['rentree']);
        }
        if (!empty($f['unassigned'])) {
            $this->db->where('l.owner_id IS NULL', null, false);
        }
        return $this->db
            ->order_by('l.received_at', 'desc')
            ->limit($limit)
            ->get()
            ->result();
    }

    /** Filtres communs des envois groupés (étape, programme, score, responsable, dates). */
    private function applyBulkFilters(array $f): void
    {
        if (!empty($f['stage'])) {
            $this->db->where('stage', $f['stage']);
        }
        if (!empty($f['program'])) {
            $this->db->like('formation', $f['program']);
        }
        if (isset($f['min_score']) && $f['min_score'] !== '') {
            $this->db->where('score >=', (float) $f['min_score']);
        }
        if (!empty($f['owner'])) {
            if ($f['owner'] === 'none') {
                $this->db->where('owner_id IS NULL', null, false);
            } else {
                $this->db->where('owner_id', (int) $f['owner']);
            }
        }
        if (!empty($f['date_from'])) {
            $this->db->where('received_at >=', $f['date_from'] . ' 00:00:00');
        }
        if (!empty($f['date_to'])) {
            $this->db->where('received_at <=', $f['date_to'] . ' 23:59:59');
        }
    }

    /** Destinataires d'un envoi groupé e-mail (leads avec e-mail + filtres). */
    public function email_recipients(array $f): array
    {
        $this->ensure_schema();
        $this->db->where('email IS NOT NULL', null, false)->where('email !=', '');
        $this->applyBulkFilters($f);
        return $this->db->order_by('score', 'desc')->get($this->table())->result();
    }

    /** Destinataires d'un envoi groupé SMS (leads avec téléphone + filtres). */
    public function sms_recipients(array $f): array
    {
        $this->ensure_schema();
        $this->db->where('phone IS NOT NULL', null, false)->where('phone !=', '');
        $this->applyBulkFilters($f);
        return $this->db->order_by('score', 'desc')->get($this->table())->result();
    }

    /** Compte les destinataires e-mail / SMS pour des filtres (pastille dynamique). */
    public function count_recipients(array $f): array
    {
        $this->ensure_schema();
        $this->db->where('email IS NOT NULL', null, false)->where('email !=', '');
        $this->applyBulkFilters($f);
        $email = (int) $this->db->count_all_results($this->table());

        $this->db->where('phone IS NOT NULL', null, false)->where('phone !=', '');
        $this->applyBulkFilters($f);
        $sms = (int) $this->db->count_all_results($this->table());

        return ['email' => $email, 'sms' => $sms];
    }

    /** Seuil de date pour une période en jours (0 = tout l'historique). */
    private function since(int $sinceDays): ?string
    {
        return $sinceDays > 0 ? date('Y-m-d H:i:s', time() - $sinceDays * 86400) : null;
    }

    /**
     * Résout la borne [from, to] d'un filtre de période : une plage de dates
     * personnalisée (date_from/date_to) prime sur les boutons prédéfinis (period).
     */
    private function dateRange(array $f): array
    {
        $from = !empty($f['date_from']) ? $f['date_from'] . ' 00:00:00' : null;
        $to   = !empty($f['date_to']) ? $f['date_to'] . ' 23:59:59' : null;
        if ($from === null && $to === null && !empty($f['period'])) {
            $from = $this->since((int) $f['period']);
        }
        return [$from, $to];
    }

    /** Applique période + rentrée sur la requête en cours (leads, alias optionnel). */
    private function applyLeadFilters(array $f, string $alias = ''): void
    {
        [$from, $to] = $this->dateRange($f);
        $p = $alias !== '' ? $alias . '.' : '';
        if ($from) { $this->db->where($p . 'received_at >=', $from); }
        if ($to)   { $this->db->where($p . 'received_at <=', $to); }
        if (!empty($f['rentree'])) { $this->db->where($p . 'rentree', $f['rentree']); }
        // Cadrage par conseiller : ne renvoyer que les leads dont il est responsable.
        if (isset($f['owner_id']) && $f['owner_id'] !== '' && $f['owner_id'] !== null) {
            $this->db->where($p . 'owner_id', (int) $f['owner_id']);
        }
    }

    /** Valeurs de rentrée déjà utilisées (pour le filtre du dashboard / datalist). */
    public function rentrees(): array
    {
        $this->ensure_schema();
        $rows = $this->db->query(
            'SELECT DISTINCT rentree FROM `' . $this->table() . "`
             WHERE rentree IS NOT NULL AND rentree <> ''
             ORDER BY rentree DESC"
        )->result();
        return array_map(static fn($r) => $r->rentree, $rows);
    }

    public function set_rentree(int $id, string $rentree): void
    {
        $this->db->where('id', $id)->update($this->table(), ['rentree' => substr(trim($rentree), 0, 32) ?: null]);
    }

    /** Indicateurs pour le tableau de bord (optionnellement filtrés). */
    public function stats(int $hotThreshold = 60, array $filters = []): array
    {
        $this->ensure_schema();
        $t = $this->table();

        $this->applyLeadFilters($filters);
        $total = (int) $this->db->count_all_results($t);

        $this->applyLeadFilters($filters);
        $this->db->where('score >=', $hotThreshold);
        $hot = (int) $this->db->count_all_results($t);

        $this->applyLeadFilters($filters);
        $this->db->where('score <', 40);
        $cold = (int) $this->db->count_all_results($t);

        $warm = max(0, $total - $hot - $cold);

        $byStage = [];
        foreach (array_keys($this->stages()) as $slug) {
            $byStage[$slug] = 0;
        }
        $this->applyLeadFilters($filters);
        foreach ($this->db->select('stage, COUNT(*) AS n')->group_by('stage')->get($t)->result() as $r) {
            $byStage[$r->stage] = (int) $r->n;
        }

        $inscrits = $byStage['inscrit'] ?? 0;
        $conversion = $total > 0 ? round($inscrits * 100 / $total, 1) : 0.0;
        $scoreDist = ['froid' => $cold, 'tiede' => $warm, 'chaud' => $hot];

        return compact('total', 'hot', 'inscrits', 'conversion', 'byStage', 'scoreDist');
    }

    /** Répartition des leads par source (site plugin, saisie, import). */
    public function by_source(array $filters = []): array
    {
        $this->applyLeadFilters($filters);
        return $this->db
            ->select("COALESCE(NULLIF(source_site,''),'—') AS src, COUNT(*) AS n")
            ->group_by('source_site')
            ->order_by('n', 'desc')
            ->get($this->table())
            ->result();
    }

    /** Répartition des leads par formation / programme visé. */
    public function by_formation(array $filters = [], int $limit = 8): array
    {
        $this->applyLeadFilters($filters);
        return $this->db
            ->select("COALESCE(NULLIF(formation,''),'—') AS formation, COUNT(*) AS n")
            ->group_by('formation')
            ->order_by('n', 'desc')
            ->limit($limit)
            ->get($this->table())
            ->result();
    }

    /** Performance par conseiller (leads assignés + inscrits). */
    public function by_staff(array $filters = []): array
    {
        $this->db
            ->select('s.staffid, CONCAT(s.firstname, " ", s.lastname) AS name, COUNT(l.id) AS total, '
                . 'SUM(CASE WHEN l.stage = "inscrit" THEN 1 ELSE 0 END) AS inscrits')
            ->from($this->table() . ' l')
            ->join(db_prefix() . 'staff s', 's.staffid = l.owner_id', 'inner')
            ->group_by('l.owner_id')
            ->order_by('total', 'desc');
        $this->applyLeadFilters($filters, 'l');
        return $this->db->get()->result();
    }

    /** Nombre de leads sans responsable assigné. */
    public function unassigned_count(array $filters = []): int
    {
        $this->applyLeadFilters($filters);
        return (int) $this->db->where('owner_id IS NULL', null, false)->count_all_results($this->table());
    }

    /** Derniers leads non assignés (pour le widget du dashboard). */
    public function unassigned_leads(array $filters = [], int $limit = 6): array
    {
        $this->applyLeadFilters($filters);
        return $this->db
            ->where('owner_id IS NULL', null, false)
            ->order_by('received_at', 'desc')
            ->limit($limit)
            ->get($this->table())
            ->result();
    }

    /** Derniers leads reçus (aperçu sur le dashboard). */
    public function recent_leads(array $filters = [], int $limit = 8): array
    {
        $this->applyLeadFilters($filters);
        return $this->db
            ->order_by('received_at', 'desc')
            ->limit($limit)
            ->get($this->table())
            ->result();
    }

    /**
     * Délai moyen (en heures) entre la réception d'un lead et le premier
     * contact réel (note, e-mail, SMS ou changement d'étape enregistré).
     * Renvoie null si aucune donnée exploitable sur la période.
     */
    public function avg_first_contact_hours(array $filters = []): ?float
    {
        [$from, $to] = $this->dateRange($filters);
        $params = [];
        $where = '1=1';
        if ($from) { $where .= ' AND l.received_at >= ?'; $params[] = $from; }
        if ($to)   { $where .= ' AND l.received_at <= ?'; $params[] = $to; }
        if (!empty($filters['rentree'])) { $where .= ' AND l.rentree = ?'; $params[] = $filters['rentree']; }
        if (isset($filters['owner_id']) && $filters['owner_id'] !== '' && $filters['owner_id'] !== null) {
            $where .= ' AND l.owner_id = ?'; $params[] = (int) $filters['owner_id'];
        }

        $sql = 'SELECT AVG(TIMESTAMPDIFF(MINUTE, l.received_at, fc.first_contact)) AS avg_minutes, COUNT(*) AS n
                FROM `' . $this->table() . '` l
                INNER JOIN (
                    SELECT lead_id, MIN(created_at) AS first_contact
                    FROM `' . $this->activityTable() . "`
                    WHERE type IN ('note','email','sms','stage_change')
                    GROUP BY lead_id
                ) fc ON fc.lead_id = l.id
                WHERE {$where}";
        $row = $this->db->query($sql, $params)->row();
        if (!$row || (int) $row->n === 0 || $row->avg_minutes === null) {
            return null;
        }
        return round(((float) $row->avg_minutes) / 60, 1);
    }

    /** Parse les réglages "Formation:Montant" (un par ligne) en tarifs. */
    public function program_fees(): array
    {
        $raw = (string) get_option('sia_program_fees');
        $fees = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, ':') === false) {
                continue;
            }
            [$name, $amount] = array_map('trim', explode(':', $line, 2));
            if ($name !== '') {
                $fees[$name] = (float) str_replace([' ', ','], ['', '.'], $amount);
            }
        }
        return $fees;
    }

    /** Normalise un libellé de formation : minuscules, sans accents ni ponctuation. */
    public function fee_norm(string $s): string
    {
        $s = function_exists('mb_strtolower') ? mb_strtolower(trim($s), 'UTF-8') : strtolower(trim($s));
        $map = [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ];
        $s = strtr($s, $map);
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
        return trim(preg_replace('/\s+/', ' ', (string) $s));
    }

    /** Tokens significatifs d'un libellé normalisé (mots vides et bruit écartés). */
    private function fee_tokens(string $norm): array
    {
        $stop = ['de', 'et', 'en', 'du', 'des', 'la', 'le', 'les', 'au', 'aux', 'the', 'of', 'and', 'avec'];
        return array_values(array_unique(array_filter(
            explode(' ', $norm),
            fn($t) => strlen($t) >= 2 && !in_array($t, $stop, true)
        )));
    }

    /**
     * Retrouve le tarif d'une formation avec tolérance : match exact après
     * normalisation (accents/casse/ponctuation), sinon repli flou par tokens
     * (« Master Supply Chain » ≈ « Master 2 Logistique – Supply Chain »).
     * Renvoie null si aucune correspondance suffisamment fiable — mieux vaut
     * sous-estimer que d'attribuer un tarif au mauvais programme.
     */
    public function resolve_fee(string $formation, array $index): ?float
    {
        $norm = $this->fee_norm($formation);
        if ($norm === '') {
            return null;
        }
        if (isset($index['exact'][$norm])) {
            return $index['exact'][$norm];
        }
        $tokens = $this->fee_tokens($norm);
        if (count($tokens) < 2) {
            return null; // trop générique (ex. « Master » seul) → pas d'attribution
        }
        $best = null;
        $bestScore = 0.0;
        foreach ($index['list'] as $entry) {
            $shared = count(array_intersect($tokens, $entry['tokens']));
            if ($shared < 2) {
                continue;
            }
            $union = count(array_unique(array_merge($tokens, $entry['tokens'])));
            $jaccard = $union > 0 ? $shared / $union : 0.0;
            // Contenance : tous les tokens de l'un sont dans l'autre (variante étendue).
            $contained = ($shared === count($tokens) || $shared === count($entry['tokens']));
            $score = $contained ? max($jaccard, 0.6) : $jaccard;
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $entry['fee'];
            }
        }
        return $bestScore >= 0.6 ? $best : null;
    }

    /** Index des tarifs : table de correspondance exacte + liste tokenisée. */
    public function fees_index(): array
    {
        $index = ['exact' => [], 'list' => []];
        foreach ($this->program_fees() as $name => $fee) {
            $norm = $this->fee_norm($name);
            if ($norm === '') {
                continue;
            }
            $index['exact'][$norm] = $fee;
            $index['list'][] = ['norm' => $norm, 'tokens' => $this->fee_tokens($norm), 'fee' => $fee];
        }
        return $index;
    }

    /**
     * Valeur financière du pipeline : somme des frais (par formation, définis
     * dans les réglages) des leads actifs, séparée du montant déjà « réalisé »
     * (leads inscrits). Les leads « perdu » ne comptent pas.
     *
     * La correspondance formation → tarif est tolérante (accents, casse, tirets,
     * variantes) : un lead « Bachelor Prépa Ingénieur » est bien rattaché au
     * tarif « Bachelor PRÉPA-INGÉNIEUR ».
     */
    public function finance_summary(array $filters = []): array
    {
        $index = $this->fees_index();
        if (!$index['list']) {
            return ['has_fees' => false, 'pipeline' => 0.0, 'realized' => 0.0];
        }

        $this->applyLeadFilters($filters);
        $rows = $this->db
            ->select("COALESCE(NULLIF(formation,''),'—') AS formation, stage, COUNT(*) AS n")
            ->group_by('formation, stage')
            ->get($this->table())
            ->result();

        $pipeline = 0.0;
        $realized = 0.0;
        foreach ($rows as $r) {
            $fee = $this->resolve_fee((string) $r->formation, $index);
            if ($fee === null) {
                continue;
            }
            if ($r->stage === 'inscrit') {
                $realized += $fee * (int) $r->n;
            } elseif ($r->stage !== 'perdu') {
                $pipeline += $fee * (int) $r->n;
            }
        }
        return ['has_fees' => true, 'pipeline' => $pipeline, 'realized' => $realized];
    }

    /**
     * Revenu prévisionnel : valeur actuelle du pipeline (tous les leads actifs,
     * tous temps confondus) multipliée par le taux de conversion HISTORIQUE réel
     * de l'école (inscrits / total des leads jamais reçus) — une estimation
     * réaliste, plutôt qu'un pipeline brut qui supposerait 100 % de conversion.
     */
    public function finance_forecast(): array
    {
        $index = $this->fees_index();
        if (!$index['list']) {
            return ['has_fees' => false, 'pipeline' => 0.0, 'conversion_rate' => 0.0, 'projected' => 0.0];
        }
        $t = $this->table();

        $totalEver = (int) $this->db->count_all_results($t);
        $inscritsEver = (int) $this->db->where('stage', 'inscrit')->count_all_results($t);
        $rate = $totalEver > 0 ? $inscritsEver / $totalEver : 0.0;

        $pipeline = 0.0;
        $this->db->where('stage !=', 'perdu')->where('stage !=', 'inscrit');
        foreach ($this->db->select("COALESCE(NULLIF(formation,''),'Non renseignée') formation, COUNT(*) n")->group_by('formation')->get($t)->result() as $r) {
            $fee = $this->resolve_fee((string) $r->formation, $index);
            if ($fee === null) {
                continue;
            }
            $pipeline += $fee * (int) $r->n;
        }

        return [
            'has_fees' => true,
            'pipeline' => $pipeline,
            'conversion_rate' => round($rate * 100, 1),
            'projected' => $pipeline * $rate,
        ];
    }

    public function get_lead(int $id)
    {
        return $this->db->where('id', $id)->get($this->table())->row();
    }

    /**
     * Retrouve un lead par son identifiant externe (id WordPress + site source).
     * La comparaison du site ignore http(s) et le slash final : un site passé de
     * http à https ne doit pas empêcher de retrouver ses leads déjà connus.
     */
    public function find_by_external(string $externalId, string $sourceSite)
    {
        if ($externalId === '' || $sourceSite === '') {
            return null;
        }
        $sql = 'SELECT * FROM `' . $this->table() . '`
                WHERE external_id = ? AND ' . $this->siteMatchSql() . ' = ?
                LIMIT 1';
        return $this->db->query($sql, [$externalId, $this->normalizeSite($sourceSite)])->row();
    }

    /** Un lead avec cet e-mail existe-t-il déjà ? (dédoublonnage à l'import) */
    public function email_exists(string $email): bool
    {
        $email = trim($email);
        if ($email === '') {
            return false;
        }
        return (bool) $this->db->where('email', $email)->count_all_results($this->table());
    }

    /** Création manuelle d'un lead (saisie depuis le CRM). */
    public function create_lead(array $d): int
    {
        $this->ensure_schema();
        $stage = in_array($d['stage'] ?? '', array_keys($this->stages()), true) ? $d['stage'] : 'nouveau';
        $this->db->insert($this->table(), [
            'name'        => substr(trim((string) ($d['name'] ?? '')), 0, 191) ?: null,
            'email'       => substr(trim((string) ($d['email'] ?? '')), 0, 191) ?: null,
            'phone'       => substr(trim((string) ($d['phone'] ?? '')), 0, 64) ?: null,
            'formation'   => substr(trim((string) ($d['formation'] ?? '')), 0, 191) ?: null,
            'score'       => isset($d['score']) && $d['score'] !== '' ? (float) $d['score'] : 0,
            'stage'       => $stage,
            'rentree'     => substr(trim((string) ($d['rentree'] ?? '')), 0, 32) ?: null,
            'source_site' => !empty($d['source_site']) ? substr((string) $d['source_site'], 0, 191) : 'Saisie manuelle',
            'payload'     => json_encode($d, JSON_UNESCAPED_UNICODE),
            'received_at' => date('Y-m-d H:i:s'),
        ]);
        return (int) $this->db->insert_id();
    }

    /** Met à jour les informations éditables d'un lead (saisie manuelle). */
    public function update_lead(int $id, array $d): void
    {
        $this->ensure_schema();
        $fields = [
            'name'      => substr(trim((string) ($d['name'] ?? '')), 0, 191) ?: null,
            'email'     => substr(trim((string) ($d['email'] ?? '')), 0, 191) ?: null,
            'phone'     => substr(trim((string) ($d['phone'] ?? '')), 0, 64) ?: null,
            'formation' => substr(trim((string) ($d['formation'] ?? '')), 0, 191) ?: null,
            'rentree'   => substr(trim((string) ($d['rentree'] ?? '')), 0, 32) ?: null,
        ];
        // Le score n'est écrasé que s'il est explicitement fourni (0–100).
        if (isset($d['score']) && $d['score'] !== '') {
            $fields['score'] = min(100, max(0, (float) $d['score']));
        }
        // L'étape n'est mise à jour que si elle est valide.
        if (isset($d['stage']) && array_key_exists($d['stage'], $this->stages())) {
            $fields['stage'] = $d['stage'];
        }
        $this->db->where('id', $id)->update($this->table(), $fields);
    }

    /** Leads groupés par étape du pipeline (pour le Kanban). */
    public function by_stage(): array
    {
        $this->ensure_schema();
        $grouped = [];
        foreach (array_keys($this->stages()) as $slug) {
            $grouped[$slug] = [];
        }
        $rows = $this->db->order_by('score', 'desc')->get($this->table())->result();
        foreach ($rows as $row) {
            $stage = $row->stage ?? 'nouveau';
            if (!isset($grouped[$stage])) {
                $grouped[$stage] = [];
            }
            $grouped[$stage][] = $row;
        }
        return $grouped;
    }

    public function set_stage(int $id, string $stage): void
    {
        if (!array_key_exists($stage, $this->stages())) {
            return;
        }
        $this->ensure_schema();
        $fields = ['stage' => $stage];
        // Horodate la première conversion (passage en « Inscrit ») pour mesurer
        // le délai de conversion ; ne pas l'écraser si déjà inscrit auparavant.
        if ($stage === 'inscrit') {
            $lead = $this->get_lead($id);
            if (!$lead || empty($lead->converted_at)) {
                $fields['converted_at'] = date('Y-m-d H:i:s');
            }
        }
        $this->db->where('id', $id)->update($this->table(), $fields);
    }

    /** Enregistre le motif de perte d'un lead (renseigné au passage en « Perdu »). */
    public function set_lost_reason(int $id, string $reason): void
    {
        $this->ensure_schema();
        $reason = substr(trim($reason), 0, 191);
        $this->db->where('id', $id)->update($this->table(), ['lost_reason' => $reason !== '' ? $reason : null]);
    }

    public function set_owner(int $id, int $staffId): void
    {
        $this->db->where('id', $id)->update($this->table(), ['owner_id' => $staffId ?: null]);
    }

    /**
     * Prise en charge « premier arrivé, premier servi » : n'assigne le lead que
     * s'il n'a PAS encore de responsable. Renvoie true si ce membre devient le
     * responsable, false si un autre l'avait déjà pris entre-temps. Atomique au
     * niveau SQL (WHERE owner_id IS NULL) : sans risque de course même si
     * plusieurs conseillers cliquent depuis la même alerte e-mail.
     */
    public function claim_owner(int $id, int $staffId): bool
    {
        if ($staffId <= 0) {
            return false;
        }
        $this->db->where('id', $id)->where('owner_id IS NULL', null, false)
            ->update($this->table(), ['owner_id' => $staffId]);
        return $this->db->affected_rows() > 0;
    }

    /** Historique / activités d'un lead (récent en premier). */
    public function activities(int $leadId): array
    {
        return $this->db
            ->where('lead_id', $leadId)
            ->order_by('created_at', 'desc')
            ->get($this->activityTable())
            ->result();
    }

    // ---------- Reporting (agrégats période + rapports IA) ----------

    /** Agrège les données du CRM entre deux dates (pour le rapport). */
    public function report_data(string $from, string $to): array
    {
        $this->ensure_schema();
        $t = $this->table();

        $leadsTotal = (int) $this->db->where('received_at >=', $from)->where('received_at <=', $to)->count_all_results($t);

        $byStage = [];
        foreach (array_keys($this->stages()) as $s) { $byStage[$this->stageLabel($s)] = 0; }
        $this->db->where('received_at >=', $from)->where('received_at <=', $to);
        foreach ($this->db->select('stage, COUNT(*) n')->group_by('stage')->get($t)->result() as $r) {
            $byStage[$this->stageLabel((string) $r->stage)] = (int) $r->n;
        }

        $bySource = [];
        $this->db->where('received_at >=', $from)->where('received_at <=', $to);
        foreach ($this->db->select("COALESCE(NULLIF(source_site,''),'—') src, COUNT(*) n")->group_by('source_site')->order_by('n', 'desc')->get($t)->result() as $r) {
            $bySource[(string) $r->src] = (int) $r->n;
        }

        // Canal d'acquisition (UTM) : source marketing d'où vient le lead.
        $byChannel = [];
        $this->db->where('received_at >=', $from)->where('received_at <=', $to);
        foreach ($this->db->select("COALESCE(NULLIF(TRIM(utm_source),''),'Direct / inconnu') ch, COUNT(*) n", false)->group_by('ch')->order_by('n', 'desc')->get($t)->result() as $r) {
            $byChannel[(string) $r->ch] = (int) $r->n;
        }

        // Campagnes (UTM) — uniquement les leads réellement issus d'une campagne.
        $byCampaign = [];
        $this->db->where('received_at >=', $from)->where('received_at <=', $to)->where('utm_campaign IS NOT NULL', null, false)->where('utm_campaign !=', '');
        foreach ($this->db->select('utm_campaign camp, COUNT(*) n')->group_by('utm_campaign')->order_by('n', 'desc')->limit(8)->get($t)->result() as $r) {
            $byCampaign[(string) $r->camp] = (int) $r->n;
        }

        // Formations : on compte TOUS les leads (les vides regroupés en « Non
        // renseignée »), puis top 7 + « Autres » → la somme égale le total des
        // leads (fini l'écart « 12 vs 26 » entre le camembert et le compteur).
        $formCounts = [];
        $this->db->where('received_at >=', $from)->where('received_at <=', $to);
        foreach ($this->db->select('formation, COUNT(*) n')->group_by('formation')->get($t)->result() as $r) {
            $label = trim((string) $r->formation);
            $label = $label !== '' ? $label : 'Non renseignée';
            $formCounts[$label] = ($formCounts[$label] ?? 0) + (int) $r->n;
        }
        $nonRens = $formCounts['Non renseignée'] ?? 0;
        unset($formCounts['Non renseignée']);
        arsort($formCounts);
        $topFormations = array_slice($formCounts, 0, 7, true);
        $autres = array_sum($formCounts) - array_sum($topFormations);
        if ($autres > 0) { $topFormations['Autres'] = $autres; }
        if ($nonRens > 0) { $topFormations['Non renseignée'] = $nonRens; }

        $inscrits = (int) $this->db->where('received_at >=', $from)->where('received_at <=', $to)->where('stage', 'inscrit')->count_all_results($t);

        // Délai moyen de première réponse (réception → 1re action du conseiller).
        $frRow = $this->db->query(
            'SELECT AVG(TIMESTAMPDIFF(MINUTE, l.received_at, fc.first_contact)) AS avg_minutes
             FROM `' . $t . '` l
             INNER JOIN (
                 SELECT lead_id, MIN(created_at) AS first_contact
                 FROM `' . $this->activityTable() . "`
                 WHERE type IN ('note','email','sms','stage_change')
                 GROUP BY lead_id
             ) fc ON fc.lead_id = l.id
             WHERE l.received_at >= ? AND l.received_at <= ?",
            [$from, $to]
        )->row();
        $firstResponseHours = ($frRow && $frRow->avg_minutes !== null) ? round(((float) $frRow->avg_minutes) / 60, 1) : null;

        // Motifs de perte des leads passés en « Perdu » sur la période.
        $lossReasons = [];
        $this->db->where('received_at >=', $from)->where('received_at <=', $to)->where('stage', 'perdu');
        foreach ($this->db->select("COALESCE(NULLIF(TRIM(lost_reason),''),'Non précisé') r, COUNT(*) n", false)->group_by('r')->order_by('n', 'desc')->get($t)->result() as $r) {
            $lossReasons[(string) $r->r] = (int) $r->n;
        }

        // Délai moyen de conversion (réception → passage en « Inscrit »), en jours.
        $convRow = $this->db->query(
            'SELECT AVG(TIMESTAMPDIFF(HOUR, received_at, converted_at)) AS avg_hours
             FROM `' . $t . '`
             WHERE stage = "inscrit" AND converted_at IS NOT NULL
               AND received_at >= ? AND received_at <= ?',
            [$from, $to]
        )->row();
        $conversionDays = ($convRow && $convRow->avg_hours !== null) ? round(((float) $convRow->avg_hours) / 24, 1) : null;

        // Financier : réutilise la correspondance tolérante formation → tarif.
        // - Valeur ajoutée au pipeline : leads REÇUS pendant la période, encore
        //   actifs (hors perdu/inscrit) — « combien de business est arrivé ».
        // - CA réalisé : basé sur la date de CONVERSION (converted_at), pas de
        //   réception — reflète l'argent effectivement encaissé pendant la
        //   période, et non les leads arrivés ce mois-ci qui n'ont pas encore eu
        //   le temps de convertir (qui seraient artificiellement sous-estimés).
        // - Revenu par formation : valeur totale (hors perdu) des leads reçus
        //   pendant la période, triée par montant — une formation à faible
        //   volume mais chère peut peser plus qu'une formation à fort volume.
        $financeIndex = $this->fees_index();
        $hasFees = (bool) $financeIndex['list'];
        $financePipeline = 0.0;
        $revenueByFormation = [];
        if ($hasFees) {
            $this->db->where('received_at >=', $from)->where('received_at <=', $to)->where('stage !=', 'perdu');
            foreach ($this->db->select("COALESCE(NULLIF(formation,''),'Non renseignée') formation, stage, COUNT(*) n")->group_by('formation, stage')->get($t)->result() as $r) {
                $fee = $this->resolve_fee((string) $r->formation, $financeIndex);
                if ($fee === null) {
                    continue;
                }
                $value = $fee * (int) $r->n;
                if ($r->stage !== 'inscrit') {
                    $financePipeline += $value;
                }
                $label = (string) $r->formation;
                $revenueByFormation[$label] = ($revenueByFormation[$label] ?? 0) + $value;
            }
            arsort($revenueByFormation);
            $revenueByFormation = array_slice($revenueByFormation, 0, 7, true);
        }

        $financeRealized = 0.0;
        if ($hasFees) {
            $this->db->where('converted_at >=', $from)->where('converted_at <=', $to)->where('stage', 'inscrit');
            foreach ($this->db->select("COALESCE(NULLIF(formation,''),'Non renseignée') formation, COUNT(*) n")->group_by('formation')->get($t)->result() as $r) {
                $fee = $this->resolve_fee((string) $r->formation, $financeIndex);
                if ($fee === null) {
                    continue;
                }
                $financeRealized += $fee * (int) $r->n;
            }
        }

        // Messages (e-mails / SMS) envoyés dans la période
        $mt = $this->messagesTable();
        $emailSent = (int) $this->db->where('sent_at >=', $from)->where('sent_at <=', $to)->where('channel', 'email')->count_all_results($mt);
        $emailOpened = (int) $this->db->where('sent_at >=', $from)->where('sent_at <=', $to)->where('channel', 'email')->where('opened_at IS NOT NULL', null, false)->count_all_results($mt);
        $emailClicked = (int) $this->db->where('sent_at >=', $from)->where('sent_at <=', $to)->where('channel', 'email')->where('clicks >', 0)->count_all_results($mt);
        $smsSent = (int) $this->db->where('sent_at >=', $from)->where('sent_at <=', $to)->where('channel', 'sms')->where('status', 'sent')->count_all_results($mt);
        $smsFailed = (int) $this->db->where('sent_at >=', $from)->where('sent_at <=', $to)->where('channel', 'sms')->where('status', 'failed')->count_all_results($mt);

        // Tâches terminées dans la période
        $tasksDone = (int) $this->db->where('created_at >=', $from)->where('created_at <=', $to)->where('type', 'task')->count_all_results($this->activityTable());

        return [
            'leads_total'    => $leadsTotal,
            'inscrits'       => $inscrits,
            'conversion'     => $leadsTotal > 0 ? round($inscrits * 100 / $leadsTotal, 1) : 0,
            'by_stage'       => $byStage,
            'by_source'      => $bySource,
            'by_channel'     => $byChannel,
            'by_campaign'    => $byCampaign,
            'top_formations' => $topFormations,
            'first_response_hours' => $firstResponseHours,
            'loss_reasons'   => $lossReasons,
            'conversion_days' => $conversionDays,
            'has_fees'          => $hasFees,
            'finance_pipeline'  => $financePipeline,
            'finance_realized'  => $financeRealized,
            'revenue_by_formation' => $revenueByFormation,
            'email_sent'     => $emailSent,
            'email_opened'   => $emailOpened,
            'email_clicked'  => $emailClicked,
            'open_rate'      => $emailSent > 0 ? round($emailOpened * 100 / $emailSent, 1) : 0,
            'click_rate'     => $emailSent > 0 ? round($emailClicked * 100 / $emailSent, 1) : 0,
            'sms_sent'       => $smsSent,
            'sms_failed'     => $smsFailed,
            'tasks'          => $tasksDone,
        ];
    }

    /** Performance par conseiller sur un intervalle de dates (pour le reporting). */
    public function by_staff_range(string $from, string $to): array
    {
        return $this->db
            ->select('CONCAT(s.firstname, " ", s.lastname) AS name, COUNT(l.id) AS total, '
                . 'SUM(CASE WHEN l.stage = "inscrit" THEN 1 ELSE 0 END) AS inscrits')
            ->from($this->table() . ' l')
            ->join(db_prefix() . 'staff s', 's.staffid = l.owner_id', 'inner')
            ->where('l.received_at >=', $from)
            ->where('l.received_at <=', $to)
            ->group_by('l.owner_id')
            ->order_by('total', 'desc')
            ->get()
            ->result();
    }

    /** Répartition des activités (interactions) par type sur un intervalle. */
    public function activity_breakdown(string $from, string $to): array
    {
        $out = [];
        foreach ($this->db
            ->select('type, COUNT(*) AS n')
            ->where('created_at >=', $from)->where('created_at <=', $to)
            ->group_by('type')
            ->get($this->activityTable())->result() as $r) {
            $out[(string) $r->type] = (int) $r->n;
        }
        return $out;
    }

    /**
     * Série temporelle des nouveaux leads sur un intervalle, en buckets continus
     * (zéros inclus). $granularity : 'hour', 'day' ou 'month'.
     * @return array<int,array{label:string,value:int}>
     */
    public function leads_series(string $from, string $to, string $granularity = 'day'): array
    {
        $conf = [
            'hour'  => ['%Y-%m-%d %H', 'H\h', '+1 hour'],
            'day'   => ['%Y-%m-%d', 'd/m', '+1 day'],
            'month' => ['%Y-%m', 'M', '+1 month'],
        ];
        [$sqlFmt, $phpFmt, $step] = $conf[$granularity] ?? $conf['day'];

        $rows = $this->db->query(
            'SELECT DATE_FORMAT(received_at, ?) AS k, COUNT(*) AS n FROM `' . $this->table() . '`
             WHERE received_at >= ? AND received_at <= ? GROUP BY k',
            [$sqlFmt, $from, $to]
        )->result();
        $map = [];
        foreach ($rows as $r) { $map[$r->k] = (int) $r->n; }

        // Correspondance clé PHP → clé SQL (mêmes composantes date).
        $keyFmt = ['%Y-%m-%d %H' => 'Y-m-d H', '%Y-%m-%d' => 'Y-m-d', '%Y-%m' => 'Y-m'][$sqlFmt];

        $series = [];
        $cur = strtotime($from);
        $end = strtotime($to);
        $guard = 0;
        while ($cur <= $end && $guard++ < 1000) {
            $k = date($keyFmt, $cur);
            $series[] = ['label' => date($phpFmt, $cur), 'value' => $map[$k] ?? 0];
            $cur = strtotime($step, $cur);
        }
        return $series;
    }

    public function save_report(array $d): int
    {
        $this->db->insert(db_prefix() . 'school_ia_reports', [
            'period'     => substr((string) ($d['period'] ?? 'month'), 0, 10),
            'label'      => substr((string) ($d['label'] ?? ''), 0, 120),
            'date_from'  => $d['date_from'] ?? null,
            'date_to'    => $d['date_to'] ?? null,
            'content'    => (string) ($d['content'] ?? ''),
            'staff_id'   => $d['staff_id'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return (int) $this->db->insert_id();
    }

    public function list_reports(int $limit = 30): array
    {
        return $this->db->order_by('created_at', 'desc')->limit($limit)->get(db_prefix() . 'school_ia_reports')->result();
    }

    public function get_report(int $id)
    {
        return $this->db->where('id', $id)->get(db_prefix() . 'school_ia_reports')->row();
    }

    /** Dernier rapport IA enregistré pour exactement cette période (ou null). */
    public function latest_report(string $period, string $from, string $to)
    {
        return $this->db
            ->where('period', $period)
            ->where('date_from', $from)
            ->where('date_to', $to)
            ->order_by('created_at', 'desc')
            ->limit(1)
            ->get(db_prefix() . 'school_ia_reports')
            ->row();
    }

    public function delete_report(int $id): void
    {
        $this->db->where('id', $id)->delete(db_prefix() . 'school_ia_reports');
    }

    // ---------- Messages & statistiques de campagne ----------

    private function messagesTable(): string
    {
        return db_prefix() . 'school_ia_messages';
    }

    private function campaignsTable(): string
    {
        return db_prefix() . 'school_ia_campaigns';
    }

    /** Enregistre un message envoyé et renvoie son jeton de suivi. */
    public function log_message(array $d): string
    {
        $token = bin2hex(random_bytes(8));
        $this->db->insert($this->messagesTable(), [
            'lead_id'     => (int) ($d['lead_id'] ?? 0) ?: null,
            'channel'     => in_array($d['channel'] ?? 'email', ['email', 'sms'], true) ? $d['channel'] : 'email',
            'campaign'    => substr((string) ($d['campaign'] ?? 'single'), 0, 20),
            'campaign_id' => (int) ($d['campaign_id'] ?? 0) ?: null,
            'subject'     => isset($d['subject']) ? substr((string) $d['subject'], 0, 255) : null,
            'token'       => $token,
            'status'      => substr((string) ($d['status'] ?? 'sent'), 0, 12),
            'clicks'      => 0,
            'staff_id'    => $d['staff_id'] ?? null,
            'sent_at'     => date('Y-m-d H:i:s'),
        ]);
        return $token;
    }

    // ---------- Campagnes nommées (liste + détail) ----------

    /** Crée une campagne et renvoie son id. */
    public function create_campaign(array $d): int
    {
        $this->ensure_schema();
        $this->db->insert($this->campaignsTable(), [
            'name'        => substr(trim((string) ($d['name'] ?? '')), 0, 191) ?: ('Campagne du ' . date('d/m/Y H:i')),
            'channel'     => in_array($d['channel'] ?? 'email', ['email', 'sms'], true) ? $d['channel'] : 'email',
            'subject'     => isset($d['subject']) ? substr((string) $d['subject'], 0, 255) : null,
            'body'        => (string) ($d['body'] ?? ''),
            'filters'     => json_encode($d['filters'] ?? [], JSON_UNESCAPED_UNICODE),
            'attachments' => !empty($d['attachments']) ? substr(implode(',', array_map('intval', (array) $d['attachments'])), 0, 255) : null,
            'volume'      => (int) ($d['volume'] ?? 0),
            'status'      => substr((string) ($d['status'] ?? 'sent'), 0, 12),
            'staff_id'    => $d['staff_id'] ?? null,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
        return (int) $this->db->insert_id();
    }

    private function queueTable(): string
    {
        return db_prefix() . 'school_ia_campaign_queue';
    }

    /** Met en file les destinataires d'une campagne (envoi par lots). */
    public function enqueue_campaign(int $campaignId, array $leadIds): void
    {
        $now = date('Y-m-d H:i:s');
        $batch = [];
        foreach (array_unique(array_map('intval', $leadIds)) as $lid) {
            if ($lid > 0) {
                $batch[] = ['campaign_id' => $campaignId, 'lead_id' => $lid, 'status' => 'pending', 'created_at' => $now];
            }
        }
        if ($batch) {
            $this->db->insert_batch($this->queueTable(), $batch);
        }
    }

    /**
     * Prochain lot à traiter : destinataires « pending », joints au lead et à la
     * campagne (pour disposer du canal, du contenu et des coordonnées).
     */
    public function campaign_queue_batch(int $limit): array
    {
        $q = $this->queueTable();
        $c = $this->campaignsTable();
        $l = $this->table();
        return $this->db->query(
            'SELECT qq.id AS queue_id, qq.campaign_id, qq.lead_id,
                    c.channel, c.subject, c.body, c.attachments, c.staff_id,
                    l.name, l.email, l.phone, l.formation
             FROM `' . $q . '` qq
             JOIN `' . $c . '` c ON c.id = qq.campaign_id
             JOIN `' . $l . '` l ON l.id = qq.lead_id
             WHERE qq.status = "pending"
             ORDER BY qq.campaign_id ASC, qq.id ASC
             LIMIT ' . (int) $limit
        )->result();
    }

    /** Marque une entrée de la file comme traitée (sent | failed). */
    public function mark_queue(int $queueId, string $status): void
    {
        $this->db->where('id', $queueId)->update($this->queueTable(), [
            'status'       => in_array($status, ['sent', 'failed'], true) ? $status : 'failed',
            'attempts'     => 1,
            'processed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** Destinataires encore en attente pour une campagne. */
    public function campaign_pending_count(int $campaignId): int
    {
        return (int) $this->db->where('campaign_id', $campaignId)->where('status', 'pending')
            ->count_all_results($this->queueTable());
    }

    public function set_campaign_status(int $id, string $status): void
    {
        $this->db->where('id', $id)->update($this->campaignsTable(),
            ['status' => substr($status, 0, 12)]);
    }

    public function get_campaign(int $id)
    {
        return $this->db->where('id', $id)->get($this->campaignsTable())->row();
    }

    public function set_campaign_volume(int $id, int $volume): void
    {
        $this->db->where('id', $id)->update($this->campaignsTable(), ['volume' => $volume]);
    }

    /** Liste des campagnes avec agrégats (volume réel, ouvertures, clics, conversions). */
    public function campaigns_list(int $limit = 200): array
    {
        $this->ensure_schema();
        $c = $this->campaignsTable();
        $m = $this->messagesTable();
        return $this->db->query(
            'SELECT c.*,
                    COUNT(msg.id) AS sent,
                    SUM(msg.opened_at IS NOT NULL) AS opened,
                    SUM(msg.clicks > 0) AS clicked,
                    COUNT(DISTINCT CASE WHEN msg.clicks > 0 AND l.stage = "inscrit" THEN msg.lead_id END) AS conversions
             FROM `' . $c . '` c
             LEFT JOIN `' . $m . '` msg ON msg.campaign_id = c.id
             LEFT JOIN `' . $this->table() . '` l ON l.id = msg.lead_id
             GROUP BY c.id
             ORDER BY c.created_at DESC
             LIMIT ' . (int) $limit
        )->result();
    }

    /** Agrégats d'une campagne précise. */
    public function campaign_kpis(int $id): array
    {
        $m = $this->messagesTable();
        $row = $this->db->query(
            'SELECT COUNT(msg.id) AS sent,
                    SUM(msg.opened_at IS NOT NULL) AS opened,
                    SUM(msg.clicks > 0) AS clicked,
                    SUM(msg.status = "failed") AS failed,
                    COUNT(DISTINCT CASE WHEN msg.clicks > 0 AND l.stage = "inscrit" THEN msg.lead_id END) AS conversions,
                    COUNT(DISTINCT CASE WHEN msg.clicks > 0 THEN msg.lead_id END) AS clickers
             FROM `' . $m . '` msg
             LEFT JOIN `' . $this->table() . '` l ON l.id = msg.lead_id
             WHERE msg.campaign_id = ?',
            [$id]
        )->row();
        $sent = (int) ($row->sent ?? 0);
        $opened = (int) ($row->opened ?? 0);
        $clicked = (int) ($row->clicked ?? 0);
        $clickers = (int) ($row->clickers ?? 0);
        return [
            'sent'        => $sent,
            'opened'      => $opened,
            'clicked'     => $clicked,
            'failed'      => (int) ($row->failed ?? 0),
            'conversions' => (int) ($row->conversions ?? 0),
            'clickers'    => $clickers,
            'open_rate'   => $sent > 0 ? round($opened * 100 / $sent, 1) : 0.0,
            'click_rate'  => $sent > 0 ? round($clicked * 100 / $sent, 1) : 0.0,
            'conv_rate'   => $clickers > 0 ? round((int) ($row->conversions ?? 0) * 100 / $clickers, 1) : 0.0,
        ];
    }

    /**
     * Destinataires d'une campagne, filtrables : all | opened | clicked |
     * unopened | converted.
     */
    public function campaign_recipients_by_id(int $id, string $filter = 'all', int $limit = 1000): array
    {
        $m = $this->messagesTable();
        $cond = 'msg.campaign_id = ?';
        switch ($filter) {
            case 'opened':    $cond .= ' AND msg.opened_at IS NOT NULL'; break;
            case 'unopened':  $cond .= ' AND msg.opened_at IS NULL'; break;
            case 'clicked':   $cond .= ' AND msg.clicks > 0'; break;
            case 'converted': $cond .= ' AND msg.clicks > 0 AND l.stage = "inscrit"'; break;
        }
        return $this->db->query(
            'SELECT msg.lead_id, l.name AS lead_name, l.email, l.phone, l.stage AS lead_stage, l.score AS lead_score,
                    msg.opened_at, msg.clicks, msg.status, msg.sent_at
             FROM `' . $m . '` msg
             LEFT JOIN `' . $this->table() . '` l ON l.id = msg.lead_id
             WHERE ' . $cond . '
             ORDER BY (msg.clicks > 0) DESC, (msg.opened_at IS NOT NULL) DESC, msg.sent_at DESC
             LIMIT ' . (int) $limit,
            [$id]
        )->result();
    }

    /** Leads d'une campagne e-mail qui n'ont pas ouvert (pour relance). */
    public function campaign_non_openers(int $id): array
    {
        $m = $this->messagesTable();
        return $this->db->query(
            'SELECT DISTINCT l.*
             FROM `' . $m . '` msg
             JOIN `' . $this->table() . '` l ON l.id = msg.lead_id
             WHERE msg.campaign_id = ? AND msg.channel = "email" AND msg.opened_at IS NULL
               AND l.email IS NOT NULL AND l.email <> ""',
            [$id]
        )->result();
    }

    public function delete_campaign(int $id): void
    {
        $this->db->where('id', $id)->delete($this->campaignsTable());
        // Les messages restent (historique) mais perdent le rattachement.
        $this->db->where('campaign_id', $id)->update($this->messagesTable(), ['campaign_id' => null]);
    }

    public function mark_open(string $token): void
    {
        if ($token === '') {
            return;
        }
        $this->db->where('token', $token)->where('opened_at IS NULL', null, false)
            ->update($this->messagesTable(), ['opened_at' => date('Y-m-d H:i:s')]);
    }

    public function add_click(string $token): void
    {
        if ($token === '') {
            return;
        }
        $this->db->set('clicks', 'clicks+1', false)->where('token', $token)->update($this->messagesTable());
        // Un clic implique une ouverture.
        $this->mark_open($token);
    }

    /** Types de campagne reconnus (tag stocké dans messages.campaign). */
    private function campaignTypes(string $type): ?array
    {
        $map = ['bulk' => ['bulk'], 'sequence' => ['sequence'], 'single' => ['single']];
        return $map[$type] ?? null;
    }

    /** Agrégats pour la page Statistiques (période + type de campagne). */
    public function message_stats(int $sinceDays = 0, string $type = ''): array
    {
        $t = $this->messagesTable();
        $since = $this->since($sinceDays);
        $tf = $this->campaignTypes($type);
        $w = function () use ($since, $tf) {
            if ($since) { $this->db->where('sent_at >=', $since); }
            if ($tf) { $this->db->where_in('campaign', $tf); }
        };

        $w(); $this->db->where('channel', 'email'); $emailSent = (int) $this->db->count_all_results($t);
        $w(); $this->db->where('channel', 'email')->where('opened_at IS NOT NULL', null, false); $emailOpened = (int) $this->db->count_all_results($t);
        $w(); $this->db->where('channel', 'email')->where('clicks >', 0); $emailClicked = (int) $this->db->count_all_results($t);

        $w(); $this->db->where('channel', 'sms'); $smsTotal = (int) $this->db->count_all_results($t);
        $w(); $this->db->where('channel', 'sms')->where('status', 'sent'); $smsSent = (int) $this->db->count_all_results($t);
        $w(); $this->db->where('channel', 'sms')->where('status', 'failed'); $smsFailed = (int) $this->db->count_all_results($t);

        // Conversion : prospects ayant cliqué et désormais « inscrit ».
        $params = [];
        $where = 'm.clicks > 0 AND m.lead_id IS NOT NULL';
        if ($since) { $where .= ' AND m.sent_at >= ?'; $params[] = $since; }
        if ($tf) { $where .= ' AND m.campaign IN (' . implode(',', array_fill(0, count($tf), '?')) . ')'; $params = array_merge($params, $tf); }
        $clickers = (int) $this->db->query(
            'SELECT COUNT(DISTINCT m.lead_id) AS n FROM `' . $t . '` m WHERE ' . $where, $params
        )->row()->n;
        $conversions = (int) $this->db->query(
            'SELECT COUNT(DISTINCT m.lead_id) AS n FROM `' . $t . '` m
             JOIN `' . $this->table() . "` l ON l.id = m.lead_id
             WHERE " . $where . " AND l.stage = 'inscrit'", $params
        )->row()->n;

        return [
            'email_sent'    => $emailSent,
            'email_opened'  => $emailOpened,
            'email_clicked' => $emailClicked,
            'open_rate'     => $emailSent > 0 ? round($emailOpened * 100 / $emailSent, 1) : 0.0,
            'click_rate'    => $emailSent > 0 ? round($emailClicked * 100 / $emailSent, 1) : 0.0,
            'sms_total'     => $smsTotal,
            'sms_sent'      => $smsSent,
            'sms_failed'    => $smsFailed,
            'clickers'      => $clickers,
            'conversions'   => $conversions,
            'conv_rate'     => $clickers > 0 ? round($conversions * 100 / $clickers, 1) : 0.0,
        ];
    }

    /**
     * Campagnes agrégées (une ligne = un lot d'envoi), au lieu d'un message par
     * individu. Groupé par canal + type + objet + jour d'envoi.
     */
    public function campaign_groups(int $sinceDays = 0, string $type = '', int $limit = 100): array
    {
        $t = $this->messagesTable();
        $since = $this->since($sinceDays);
        $tf = $this->campaignTypes($type);

        $params = [];
        $where = '1=1';
        if ($since) { $where .= ' AND m.sent_at >= ?'; $params[] = $since; }
        if ($tf) { $where .= ' AND m.campaign IN (' . implode(',', array_fill(0, count($tf), '?')) . ')'; $params = array_merge($params, $tf); }

        return $this->db->query(
            'SELECT m.channel, m.campaign, COALESCE(m.subject, "") AS subject, DATE(m.sent_at) AS day,
                    COUNT(*) AS volume,
                    SUM(m.opened_at IS NOT NULL) AS opened,
                    SUM(m.clicks > 0) AS clicked,
                    COUNT(DISTINCT CASE WHEN m.clicks > 0 AND l.stage = "inscrit" THEN m.lead_id END) AS conversions,
                    MIN(m.sent_at) AS first_sent
             FROM `' . $t . '` m
             LEFT JOIN `' . $this->table() . '` l ON l.id = m.lead_id
             WHERE ' . $where . '
             GROUP BY m.channel, m.campaign, DATE(m.sent_at), COALESCE(m.subject, "")
             ORDER BY first_sent DESC
             LIMIT ' . (int) $limit,
            $params
        )->result();
    }

    /** Destinataires détaillés d'un lot de campagne (sous-vue « qui a cliqué »). */
    public function campaign_recipients(string $channel, string $campaign, string $subject, string $day, int $limit = 500): array
    {
        return $this->db->query(
            'SELECT m.lead_id, l.name AS lead_name, l.stage AS lead_stage, l.score AS lead_score,
                    m.opened_at, m.clicks, m.status, m.sent_at
             FROM `' . $this->messagesTable() . '` m
             LEFT JOIN `' . $this->table() . '` l ON l.id = m.lead_id
             WHERE m.channel = ? AND m.campaign = ? AND COALESCE(m.subject, "") = ? AND DATE(m.sent_at) = ?
             ORDER BY (m.clicks > 0) DESC, (m.opened_at IS NOT NULL) DESC, m.sent_at DESC
             LIMIT ' . (int) $limit,
            [$channel, $campaign, $subject, $day]
        )->result();
    }

    /** Journal global : toutes les activités, avec le nom du lead (filtrable). */
    /**
     * Journal global, filtrable par type, conseiller et plage de dates.
     * Joint la tâche liée (ref_id) pour connaître son statut « fait » et
     * permettre l'action rapide « Terminer » depuis le journal.
     */
    public function global_activities(array $filters = [], int $limit = 400): array
    {
        $this->db->select('a.*, l.name AS lead_name, t.done AS task_done, t.id AS task_id')
            ->from($this->activityTable() . ' a')
            ->join($this->table() . ' l', 'l.id = a.lead_id', 'left')
            ->join($this->tasksTable() . ' t', "t.id = a.ref_id AND a.type = 'task'", 'left')
            ->order_by('a.created_at', 'desc')
            ->limit($limit);
        if (!empty($filters['type'])) {
            $this->db->where('a.type', $filters['type']);
        }
        if (!empty($filters['staff_id'])) {
            $this->db->where('a.staff_id', (int) $filters['staff_id']);
        }
        if (!empty($filters['from'])) {
            $this->db->where('a.created_at >=', $filters['from'] . ' 00:00:00');
        }
        if (!empty($filters['to'])) {
            $this->db->where('a.created_at <=', $filters['to'] . ' 23:59:59');
        }
        return $this->db->get()->result();
    }

    /** Classement des conseillers les plus actifs sur une plage de dates. */
    public function activity_leaderboard(?string $from = null, ?string $to = null, int $limit = 8): array
    {
        $this->db->select('a.staff_id, COUNT(*) AS n')
            ->from($this->activityTable() . ' a')
            ->where('a.staff_id IS NOT NULL', null, false)
            ->group_by('a.staff_id')
            ->order_by('n', 'desc')
            ->limit($limit);
        if ($from) { $this->db->where('a.created_at >=', $from . ' 00:00:00'); }
        if ($to)   { $this->db->where('a.created_at <=', $to . ' 23:59:59'); }
        return $this->db->get()->result();
    }

    public function add_activity(int $leadId, string $type, string $content, ?int $staffId = null, ?int $refId = null): void
    {
        $this->db->insert($this->activityTable(), [
            'lead_id'    => $leadId,
            'type'       => $type,
            'content'    => $content,
            'staff_id'   => $staffId,
            'ref_id'     => $refId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    // ---------- Programmes & documents ----------

    private function documentsTable(): string
    {
        return db_prefix() . 'school_ia_documents';
    }

    /** Liste des programmes paramétrés (une par ligne dans les réglages). */
    public function programs(): array
    {
        $raw = (string) get_option('sia_programs');
        $list = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw)));
        return array_values(array_unique($list));
    }

    public function documents(?string $program = null): array
    {
        if ($program !== null && $program !== '') {
            $this->db->where('program', $program);
        }
        return $this->db->order_by('uploaded_at', 'desc')->get($this->documentsTable())->result();
    }

    /** Documents groupés par programme (pour l'affichage). */
    public function documents_grouped(): array
    {
        $grouped = [];
        foreach ($this->documents() as $doc) {
            $key = $doc->program ?: 'Sans programme';
            $grouped[$key][] = $doc;
        }
        return $grouped;
    }

    public function get_document(int $id)
    {
        return $this->db->where('id', $id)->get($this->documentsTable())->row();
    }

    public function add_document(array $d): int
    {
        $this->db->insert($this->documentsTable(), [
            'program'     => $d['program'] ?: null,
            'title'       => substr((string) $d['title'], 0, 255),
            'orig_name'   => substr((string) ($d['orig_name'] ?? ''), 0, 255),
            'stored_name' => (string) $d['stored_name'],
            'mime'        => substr((string) ($d['mime'] ?? ''), 0, 128),
            'filesize'    => (int) ($d['filesize'] ?? 0),
            'staff_id'    => $d['staff_id'] ?? null,
            'uploaded_at' => date('Y-m-d H:i:s'),
        ]);
        return (int) $this->db->insert_id();
    }

    public function delete_document(int $id): void
    {
        $this->db->where('id', $id)->delete($this->documentsTable());
    }

    // ---------- Séquences de relance ----------

    public function sequences(): array
    {
        return $this->db->order_by('name', 'asc')->get(db_prefix() . 'school_ia_sequences')->result();
    }

    public function active_sequences(): array
    {
        return $this->db->where('active', 1)->order_by('name', 'asc')->get(db_prefix() . 'school_ia_sequences')->result();
    }

    public function get_sequence(int $id)
    {
        return $this->db->where('id', $id)->get(db_prefix() . 'school_ia_sequences')->row();
    }

    public function save_sequence(array $d): int
    {
        if (!empty($d['id'])) {
            $this->db->where('id', (int) $d['id'])->update(db_prefix() . 'school_ia_sequences', [
                'name'   => substr(trim((string) $d['name']), 0, 191),
                'active' => !empty($d['active']) ? 1 : 0,
            ]);
            return (int) $d['id'];
        }
        $this->db->insert(db_prefix() . 'school_ia_sequences', [
            'name'       => substr(trim((string) $d['name']), 0, 191),
            'active'     => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return (int) $this->db->insert_id();
    }

    public function delete_sequence(int $id): void
    {
        $this->db->where('id', $id)->delete(db_prefix() . 'school_ia_sequences');
        $this->db->where('sequence_id', $id)->delete(db_prefix() . 'school_ia_sequence_steps');
        $this->db->where('sequence_id', $id)->delete(db_prefix() . 'school_ia_enrollments');
    }

    public function sequence_steps(int $seqId): array
    {
        return $this->db->where('sequence_id', $seqId)->order_by('step_order', 'asc')
            ->get(db_prefix() . 'school_ia_sequence_steps')->result();
    }

    public function step_by_order(int $seqId, int $order)
    {
        return $this->db->where('sequence_id', $seqId)->where('step_order', $order)
            ->get(db_prefix() . 'school_ia_sequence_steps')->row();
    }

    public function add_step(array $d): void
    {
        $next = (int) $this->db->where('sequence_id', (int) $d['sequence_id'])
            ->select_max('step_order')->get(db_prefix() . 'school_ia_sequence_steps')->row()->step_order;
        $this->db->insert(db_prefix() . 'school_ia_sequence_steps', [
            'sequence_id' => (int) $d['sequence_id'],
            'step_order'  => $next + 1,
            'channel'     => in_array($d['channel'] ?? 'email', ['email', 'sms'], true) ? $d['channel'] : 'email',
            'template_id' => (int) ($d['template_id'] ?? 0) ?: null,
            'delay_days'  => max(0, (int) ($d['delay_days'] ?? 0)),
            'delay_hours' => max(0, (int) ($d['delay_hours'] ?? 0)),
        ]);
    }

    public function delete_step(int $id): void
    {
        $this->db->where('id', $id)->delete(db_prefix() . 'school_ia_sequence_steps');
    }

    /** Inscrit un lead à une séquence (si pas déjà actif dessus). */
    public function enroll(int $seqId, int $leadId): bool
    {
        $steps = $this->sequence_steps($seqId);
        if (!$steps) {
            return false;
        }
        $already = (int) $this->db->where('sequence_id', $seqId)->where('lead_id', $leadId)
            ->where('status', 'active')->count_all_results(db_prefix() . 'school_ia_enrollments');
        if ($already) {
            return false;
        }
        $first = $steps[0];
        $runAt = date('Y-m-d H:i:s', time() + $first->delay_days * 86400 + $first->delay_hours * 3600);
        $this->db->insert(db_prefix() . 'school_ia_enrollments', [
            'sequence_id'     => $seqId,
            'lead_id'         => $leadId,
            'status'          => 'active',
            'next_step_order' => (int) $first->step_order,
            'next_run_at'     => $runAt,
            'enrolled_at'     => date('Y-m-d H:i:s'),
        ]);
        return true;
    }

    public function enrollments_for_lead(int $leadId): array
    {
        return $this->db->select('e.*, s.name AS sequence_name')
            ->from(db_prefix() . 'school_ia_enrollments e')
            ->join(db_prefix() . 'school_ia_sequences s', 's.id = e.sequence_id', 'left')
            ->where('e.lead_id', $leadId)
            ->order_by('e.enrolled_at', 'desc')
            ->get()->result();
    }

    public function stop_enrollment(int $id): void
    {
        $this->db->where('id', $id)->update(db_prefix() . 'school_ia_enrollments', ['status' => 'stopped']);
    }

    /** Inscriptions dont l'étape est due (pour le cron). */
    public function due_enrollments(): array
    {
        return $this->db->where('status', 'active')
            ->where('next_run_at IS NOT NULL', null, false)
            ->where('next_run_at <=', date('Y-m-d H:i:s'))
            ->get(db_prefix() . 'school_ia_enrollments')->result();
    }

    public function advance_enrollment(int $id, int $nextOrder, string $nextRunAt): void
    {
        $this->db->where('id', $id)->update(db_prefix() . 'school_ia_enrollments', [
            'next_step_order' => $nextOrder,
            'next_run_at'     => $nextRunAt,
        ]);
    }

    public function complete_enrollment(int $id): void
    {
        $this->db->where('id', $id)->update(db_prefix() . 'school_ia_enrollments', [
            'status'      => 'done',
            'next_run_at' => null,
        ]);
    }

    // ---------- Modèles e-mail / SMS ----------

    private function templatesTable(): string
    {
        return db_prefix() . 'school_ia_templates';
    }

    public function templates(?string $type = null): array
    {
        if ($type) {
            $this->db->where('type', $type);
        }
        return $this->db->order_by('name', 'asc')->get($this->templatesTable())->result();
    }

    public function get_template(int $id)
    {
        return $this->db->where('id', $id)->get($this->templatesTable())->row();
    }

    public function save_template(array $d): void
    {
        $row = [
            'type'    => in_array($d['type'] ?? 'email', ['email', 'sms'], true) ? $d['type'] : 'email',
            'name'    => substr(trim((string) ($d['name'] ?? '')), 0, 191),
            'subject' => isset($d['subject']) ? substr((string) $d['subject'], 0, 255) : null,
            'body'    => (string) ($d['body'] ?? ''),
        ];
        if (!empty($d['id'])) {
            $this->db->where('id', (int) $d['id'])->update($this->templatesTable(), $row);
        } else {
            $row['created_at'] = date('Y-m-d H:i:s');
            $this->db->insert($this->templatesTable(), $row);
        }
    }

    public function delete_template(int $id): void
    {
        $this->db->where('id', $id)->delete($this->templatesTable());
    }

    // ---------- Tâches & rappels ----------

    private function tasksTable(): string
    {
        return db_prefix() . 'school_ia_tasks';
    }

    private function normPriority(string $p): string
    {
        return in_array($p, ['haute', 'moyenne', 'basse'], true) ? $p : 'moyenne';
    }

    public function add_task(int $leadId, string $title, ?string $dueAt, ?int $staffId = null, string $priority = 'moyenne'): void
    {
        $this->ensure_schema();
        $this->db->insert($this->tasksTable(), [
            'lead_id'    => $leadId,
            'title'      => substr($title, 0, 255),
            'due_at'     => $dueAt ?: null,
            'done'       => 0,
            'priority'   => $this->normPriority($priority),
            'staff_id'   => $staffId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $taskId = (int) $this->db->insert_id();
        // Journalise sur la fiche uniquement si la tâche est rattachée à un lead.
        // ref_id = id de la tâche → action rapide « Terminer » depuis le journal.
        if ($leadId > 0) {
            $label = $dueAt ? ' (échéance ' . date('d/m/Y H:i', strtotime($dueAt)) . ')' : '';
            $this->add_activity($leadId, 'task', 'Tâche : ' . $title . $label, $staffId, $taskId);
        }
    }

    public function set_task_priority(int $id, string $priority): void
    {
        $this->db->where('id', $id)->update($this->tasksTable(), ['priority' => $this->normPriority($priority)]);
    }

    /**
     * Liste des tâches pour la page dédiée, filtrée par statut/échéance et priorité.
     * $filter : todo | overdue | today | upcoming | done. Jointures lead + responsable.
     */
    public function task_list(string $filter = 'todo', string $priority = ''): array
    {
        $this->ensure_schema();
        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd   = date('Y-m-d 23:59:59');

        $this->db
            ->select('t.*, l.name AS lead_name, CONCAT(s.firstname, " ", s.lastname) AS staff_name')
            ->from($this->tasksTable() . ' t')
            ->join($this->table() . ' l', 'l.id = t.lead_id', 'left')
            ->join(db_prefix() . 'staff s', 's.staffid = t.staff_id', 'left');

        switch ($filter) {
            case 'overdue':
                $this->db->where('t.done', 0)->where('t.due_at IS NOT NULL', null, false)->where('t.due_at <', $todayStart);
                break;
            case 'today':
                $this->db->where('t.done', 0)->where('t.due_at >=', $todayStart)->where('t.due_at <=', $todayEnd);
                break;
            case 'upcoming':
                $this->db->where('t.done', 0)->where('t.due_at >', $todayEnd);
                break;
            case 'done':
                $this->db->where('t.done', 1);
                break;
            case 'todo':
            default:
                $this->db->where('t.done', 0);
                break;
        }
        if (in_array($priority, ['haute', 'moyenne', 'basse'], true)) {
            $this->db->where('t.priority', $priority);
        }

        return $this->db
            ->order_by('t.done', 'asc')
            ->order_by('t.due_at IS NULL', 'asc', false)
            ->order_by('t.due_at', 'asc')
            ->order_by("FIELD(t.priority,'haute','moyenne','basse')", '', false)
            ->get()
            ->result();
    }

    /** Compteurs par statut pour les onglets de la page Tâches. */
    public function task_counts(): array
    {
        $this->ensure_schema();
        $t = $this->tasksTable();
        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd   = date('Y-m-d 23:59:59');

        $this->db->where('done', 0);
        $todo = (int) $this->db->count_all_results($t);

        $this->db->where('done', 0)->where('due_at IS NOT NULL', null, false)->where('due_at <', $todayStart);
        $overdue = (int) $this->db->count_all_results($t);

        $this->db->where('done', 0)->where('due_at >=', $todayStart)->where('due_at <=', $todayEnd);
        $today = (int) $this->db->count_all_results($t);

        $this->db->where('done', 0)->where('due_at >', $todayEnd);
        $upcoming = (int) $this->db->count_all_results($t);

        $this->db->where('done', 1);
        $done = (int) $this->db->count_all_results($t);

        return compact('todo', 'overdue', 'today', 'upcoming', 'done');
    }

    /** Actions groupées : marque terminé / reporte / réassigne les tâches choisies. */
    public function bulk_tasks(array $ids, string $action, ?int $staffId = null): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) {
            return 0;
        }
        switch ($action) {
            case 'complete':
                $this->db->where_in('id', $ids)->update($this->tasksTable(), ['done' => 1]);
                break;
            case 'reopen':
                $this->db->where_in('id', $ids)->update($this->tasksTable(), ['done' => 0]);
                break;
            case 'postpone':
                // Reporte de 7 jours (à partir de l'échéance existante ou de maintenant).
                $this->db->query(
                    'UPDATE `' . $this->tasksTable() . '` SET due_at = DATE_ADD(COALESCE(due_at, NOW()), INTERVAL 7 DAY), reminded = 0
                     WHERE id IN (' . implode(',', $ids) . ')'
                );
                break;
            case 'reassign':
                $this->db->where_in('id', $ids)->update($this->tasksTable(), ['staff_id' => $staffId ?: null]);
                break;
            case 'delete':
                $this->db->where_in('id', $ids)->delete($this->tasksTable());
                break;
        }
        return count($ids);
    }

    public function get_task(int $id)
    {
        return $this->db->where('id', $id)->get($this->tasksTable())->row();
    }

    public function toggle_task(int $id): void
    {
        $task = $this->get_task($id);
        if ($task) {
            $this->db->where('id', $id)->update($this->tasksTable(), ['done' => $task->done ? 0 : 1]);
        }
    }

    public function delete_task(int $id): void
    {
        $this->db->where('id', $id)->delete($this->tasksTable());
    }

    /** Tâches d'un lead (à faire d'abord, par échéance). */
    public function tasks_for_lead(int $leadId): array
    {
        return $this->db
            ->where('lead_id', $leadId)
            ->order_by('done', 'asc')
            ->order_by('due_at', 'asc')
            ->get($this->tasksTable())
            ->result();
    }

    /** Toutes les tâches à faire, avec le nom du lead (page Tâches / widget). */
    public function pending_tasks(int $limit = 200, ?int $staffId = null): array
    {
        $this->db
            ->select('t.*, l.name AS lead_name')
            ->from($this->tasksTable() . ' t')
            ->join($this->table() . ' l', 'l.id = t.lead_id', 'left')
            ->where('t.done', 0);
        // Cadrage par conseiller : uniquement les tâches qui lui sont assignées.
        if ($staffId !== null) {
            $this->db->where('t.staff_id', (int) $staffId);
        }
        return $this->db
            ->order_by('t.due_at IS NULL', 'asc', false)
            ->order_by('t.due_at', 'asc')
            ->limit($limit)
            ->get()
            ->result();
    }

    /** Tâches dues dont le rappel automatique n'a pas encore été envoyé. */
    public function due_reminders(): array
    {
        return $this->db
            ->select('t.*, l.name AS lead_name, l.owner_id AS lead_owner')
            ->from($this->tasksTable() . ' t')
            ->join($this->table() . ' l', 'l.id = t.lead_id', 'left')
            ->where('t.done', 0)
            ->where('t.reminded', 0)
            ->where('t.due_at IS NOT NULL', null, false)
            ->where('t.due_at <=', date('Y-m-d H:i:s'))
            ->get()
            ->result();
    }

    public function mark_reminded(int $id): void
    {
        $this->db->where('id', $id)->update($this->tasksTable(), ['reminded' => 1]);
    }

    /** Nombre de tâches en retard ou dues aujourd'hui. */
    public function due_count(): int
    {
        return (int) $this->db
            ->from($this->tasksTable())
            ->where('done', 0)
            ->where('due_at <=', date('Y-m-d H:i:s'))
            ->where('due_at IS NOT NULL', null, false)
            ->count_all_results();
    }

    /**
     * Enregistre (ou met à jour) un lead reçu.
     * La déduplication se fait sur external_id + source_site quand ils existent.
     *
     * Deux garde-fous importants :
     *  - on ne met à jour QUE les champs réellement fournis dans le payload :
     *    une requête partielle ne doit jamais écraser un nom/e-mail existant
     *    avec du vide ;
     *  - on refuse de créer une fiche « fantôme » sans aucune donnée utile
     *    (retourne 0), pour ne pas polluer la liste avec des « Lead #N » vides.
     */
    public function save_lead(array $p): int
    {
        // Ne mappe que les champs présents dans le payload (clé existante).
        $data = [];
        if (array_key_exists('name', $p))        { $data['name']        = ($v = substr((string) $p['name'], 0, 191)) !== '' ? $v : null; }
        if (array_key_exists('email', $p))       { $data['email']       = ($v = substr((string) $p['email'], 0, 191)) !== '' ? $v : null; }
        if (array_key_exists('phone', $p))       { $data['phone']       = ($v = substr((string) $p['phone'], 0, 64)) !== '' ? $v : null; }
        if (array_key_exists('formation', $p))   { $data['formation']   = ($v = substr((string) $p['formation'], 0, 191)) !== '' ? $v : null; }
        if (array_key_exists('source_form', $p)) { $data['source_form'] = ($v = substr((string) $p['source_form'], 0, 191)) !== '' ? $v : null; }
        if (array_key_exists('utm_source', $p))   { $data['utm_source']   = ($v = substr((string) $p['utm_source'], 0, 100)) !== '' ? $v : null; }
        if (array_key_exists('utm_medium', $p))   { $data['utm_medium']   = ($v = substr((string) $p['utm_medium'], 0, 100)) !== '' ? $v : null; }
        if (array_key_exists('utm_campaign', $p)) { $data['utm_campaign'] = ($v = substr((string) $p['utm_campaign'], 0, 150)) !== '' ? $v : null; }
        if (array_key_exists('score', $p))       { $data['score']       = (float) $p['score']; }
        if (array_key_exists('band', $p))        { $data['band']        = ($v = substr((string) $p['band'], 0, 32)) !== '' ? $v : null; }
        if (array_key_exists('source_site', $p)) { $data['source_site'] = ($v = substr((string) $p['source_site'], 0, 191)) !== '' ? $v : null; }
        if (array_key_exists('external_id', $p)) { $data['external_id'] = ($v = substr((string) $p['external_id'], 0, 64)) !== '' ? $v : null; }
        if (array_key_exists('description', $p)) { $data['description'] = (string) $p['description']; }

        $externalId = isset($p['external_id']) ? substr((string) $p['external_id'], 0, 64) : '';
        $sourceSite = isset($p['source_site']) ? (string) $p['source_site'] : '';

        // Mise à jour si on a déjà reçu ce lead (même external_id + site, schéma
        // http(s) ignoré pour ne pas dupliquer un lead après un passage en SSL).
        if ($externalId !== '' && $sourceSite !== '') {
            $sql = 'SELECT * FROM `' . $this->table() . '`
                    WHERE external_id = ? AND ' . $this->siteMatchSql() . ' = ?
                    LIMIT 1';
            $existing = $this->db->query($sql, [$externalId, $this->normalizeSite($sourceSite)])->row();
            if ($existing) {
                $data['payload'] = json_encode($p, JSON_UNESCAPED_UNICODE);
                if (!empty($p['received_at'])) {
                    $data['received_at'] = substr((string) $p['received_at'], 0, 19);
                }
                // N'écrase jamais des champs existants avec du vide.
                $data = array_filter($data, static fn($v) => $v !== null && $v !== '');
                if ($data) {
                    $this->db->where('id', $existing->id)->update($this->table(), $data);
                }
                return (int) $existing->id;
            }
        }

        // Création : refuse les fiches vides (payload sans identité ni contact).
        $meaningful = !empty($data['name']) || !empty($data['email']) || !empty($data['phone'])
            || !empty($data['formation']) || (!empty($data['score']) && (float) $data['score'] > 0);
        if (!$meaningful) {
            return 0;
        }

        $data['payload']     = json_encode($p, JSON_UNESCAPED_UNICODE);
        $data['received_at'] = !empty($p['received_at']) ? substr((string) $p['received_at'], 0, 19) : date('Y-m-d H:i:s');
        $this->db->insert($this->table(), $data);
        return (int) $this->db->insert_id();
    }

    /**
     * Enregistre un message de la conversation IA (widget WordPress) pour un lead.
     * Idempotent quand un external_message_id est fourni (le renvoi d'historique
     * ne crée pas de doublons).
     */
    public function add_chat_message(int $leadId, string $role, string $content, string $canal = 'web', ?string $externalMessageId = null): void
    {
        if ($externalMessageId !== null && $externalMessageId !== '') {
            $exists = $this->db
                ->where('lead_id', $leadId)
                ->where('external_message_id', $externalMessageId)
                ->count_all_results($this->chatTable());
            if ($exists) {
                return;
            }
        }
        $this->db->insert($this->chatTable(), [
            'lead_id'             => $leadId,
            'external_message_id' => $externalMessageId,
            'role'                => substr($role, 0, 12),
            'canal'               => substr($canal, 0, 12),
            'content'             => $content,
            'created_at'          => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Historique de la conversation IA d'un lead, en VRAI ordre chronologique.
     * On trie par external_message_id (l'id de message WordPress, monotone) et
     * non par l'ordre d'ARRIVÉE côté Perfex : la synchro est asynchrone et peut
     * livrer les messages dans le désordre (created_at = heure de réception).
     */
    public function chat_messages(int $leadId): array
    {
        return $this->db
            ->where('lead_id', $leadId)
            ->order_by('CAST(external_message_id AS UNSIGNED) ASC, id ASC', '', false)
            ->get($this->chatTable())
            ->result();
    }

    /** Nombre de messages de conversation d'un lead (une conversation existe si > 0). */
    public function chat_count(int $leadId): int
    {
        return (int) $this->db->where('lead_id', $leadId)->count_all_results($this->chatTable());
    }

    /** Premier message « user » du lead (pour l'extrait dans l'alerte). */
    public function first_user_message(int $leadId): string
    {
        $row = $this->db
            ->where('lead_id', $leadId)
            ->where('role', 'user')
            ->order_by('id', 'asc')
            ->limit(1)
            ->get($this->chatTable())
            ->row();
        return $row ? (string) $row->content : '';
    }

    /** Marque les conseillers comme prévenus du démarrage de la conversation. */
    public function mark_conseiller_notified(int $leadId): void
    {
        $this->db->where('id', $leadId)->update($this->table(), ['conseiller_notified' => 1]);
    }

    /** Supprime les messages de chat portant une référence externe donnée
     *  (nettoyage des faux messages créés par d'anciennes tentatives de veille). */
    public function delete_chat_by_external(string $ref): void
    {
        if ($ref === '') {
            return;
        }
        $this->db->where('external_message_id', $ref)->delete($this->chatTable());
    }

    /** Nettoie tous les faux messages de veille (préfixe SIACMP1: ou ref cN). */
    public function purge_competitor_chat_noise(): int
    {
        $this->db->group_start()
            ->like('content', 'SIACMP1:', 'after')
            ->or_like('external_message_id', 'c', 'after')
            ->group_end()
            ->delete($this->chatTable());
        return (int) $this->db->affected_rows();
    }

    /** Derniers messages de chat, pour le diagnostic (aperçu du contenu). */
    public function recent_chat(int $limit = 8): array
    {
        return $this->db
            ->select('id, lead_id, canal, external_message_id, LEFT(content, 40) AS preview, created_at')
            ->order_by('id', 'desc')
            ->limit($limit)
            ->get($this->chatTable())
            ->result();
    }

    /** Supprime un lead et tout ce qui s'y rattache (activités, tâches, messages, inscriptions). */
    public function delete_lead(int $id): void
    {
        $this->db->where('lead_id', $id)->delete($this->activityTable());
        $this->db->where('lead_id', $id)->delete($this->tasksTable());
        $this->db->where('lead_id', $id)->delete($this->chatTable());
        $this->db->where('lead_id', $id)->delete($this->competitorTable());
        $this->db->where('lead_id', $id)->delete(db_prefix() . 'school_ia_enrollments');
        $this->db->where('id', $id)->delete($this->table());
    }

    /**
     * Diagnostic : tente une insertion réelle dans la table des concurrents et
     * renvoie l'erreur SQL exacte si elle échoue (puis nettoie la ligne test).
     */
    public function diag_write_test(): array
    {
        $ref = '__diag_' . uniqid();
        // Exerce EXACTEMENT le vrai chemin (dédoublonnage count + insert).
        $this->add_competitor_mention(0, '__diag_test__', 'test', $ref);
        $error     = $this->db->error();
        $lastQuery = $this->db->last_query();
        $stored    = (int) $this->db->where('external_ref', $ref)->count_all_results($this->competitorTable());
        // Nettoyage.
        $this->db->where('external_ref', $ref)->delete($this->competitorTable());

        return [
            'stored_via_method' => $stored > 0,
            'db_error'          => $error,
            'last_query'        => $lastQuery,
        ];
    }

    /** Diagnostic : existence + nombre de lignes des tables clés. */
    public function diag_counts(): array
    {
        $tables = [
            'leads'         => $this->table(),
            'activities'    => $this->activityTable(),
            'chat_messages' => $this->chatTable(),
            'competitors'   => $this->competitorTable(),
        ];
        $out = [];
        foreach ($tables as $key => $t) {
            $exists = $this->db->table_exists($t);
            $out[$key] = [
                'exists' => $exists,
                'rows'   => $exists ? (int) $this->db->count_all_results($t) : 0,
            ];
        }

        // Rattachement : combien de messages/mentions pointent vers un lead qui
        // existe encore (« linked ») vs orphelin (lead supprimé puis recréé).
        if (!empty($out['chat_messages']['exists'])) {
            $out['chat_messages']['linked'] = (int) ($this->db->query(
                'SELECT COUNT(*) AS n FROM `' . $this->chatTable() . '` c
                 JOIN `' . $this->table() . '` l ON l.id = c.lead_id'
            )->row()->n ?? 0);
            $out['chat_messages']['orphaned'] = $out['chat_messages']['rows'] - $out['chat_messages']['linked'];
        }
        if (!empty($out['competitors']['exists'])) {
            $out['competitors']['linked'] = (int) ($this->db->query(
                'SELECT COUNT(*) AS n FROM `' . $this->competitorTable() . '` c
                 JOIN `' . $this->table() . '` l ON l.id = c.lead_id'
            )->row()->n ?? 0);
            $out['competitors']['orphaned'] = $out['competitors']['rows'] - $out['competitors']['linked'];
        }
        return $out;
    }

    // ---------- Veille concurrentielle ----------

    /** Enregistre une mention de concurrent (idempotent via external_ref). */
    public function add_competitor_mention(int $leadId, string $name, string $context = '', ?string $externalRef = null): void
    {
        $name = trim($name);
        if ($name === '') {
            return;
        }
        if ($externalRef !== null && $externalRef !== '') {
            $exists = $this->db
                ->where('external_ref', $externalRef)
                ->count_all_results($this->competitorTable());
            if ($exists) {
                return;
            }
        }
        $this->db->insert($this->competitorTable(), [
            'lead_id'      => $leadId,
            'external_ref' => $externalRef,
            'name'         => substr($name, 0, 191),
            'context'      => $context !== '' ? $context : null,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    /** Clause WHERE + params communs aux requêtes de veille (période, programme, statut). */
    private function competitorWhere(array $f): array
    {
        $w = ' WHERE 1=1';
        $p = [];
        if (!empty($f['from'])) { $w .= ' AND c.created_at >= ?'; $p[] = $f['from']; }
        if (!empty($f['to']))   { $w .= ' AND c.created_at <= ?'; $p[] = $f['to']; }
        if (!empty($f['program'])) { $w .= ' AND l.formation LIKE ?'; $p[] = '%' . $f['program'] . '%'; }
        if (!empty($f['status']))  { $w .= ' AND l.stage = ?'; $p[] = $f['status']; }
        return [$w, $p];
    }

    /**
     * Classement analytique des concurrents (avec filtres) : mentions, prospects,
     * taux de perte face à l'école et programme le plus ciblé.
     */
    public function competitor_ranking(array $f = [], int $limit = 50): array
    {
        $this->ensure_schema();
        [$where, $params] = $this->competitorWhere($f);
        $rows = $this->db->query(
            'SELECT c.name,
                    COUNT(*) AS mentions,
                    COUNT(DISTINCT c.lead_id) AS leads,
                    COUNT(DISTINCT CASE WHEN l.stage = "perdu" THEN c.lead_id END) AS lost_leads,
                    MAX(c.created_at) AS derniere
             FROM `' . $this->competitorTable() . '` c
             LEFT JOIN `' . $this->table() . '` l ON l.id = c.lead_id'
             . $where .
            ' GROUP BY c.name ORDER BY mentions DESC LIMIT ' . (int) $limit,
            $params
        )->result();

        // Programme le plus ciblé par concurrent (2ᵉ requête, agrégée en PHP).
        $topByName = [];
        foreach ($this->db->query(
            'SELECT c.name, l.formation, COUNT(*) AS n
             FROM `' . $this->competitorTable() . '` c
             JOIN `' . $this->table() . '` l ON l.id = c.lead_id'
             . $where . ' AND l.formation IS NOT NULL AND l.formation <> ""'
             . ' GROUP BY c.name, l.formation ORDER BY n DESC',
            $params
        )->result() as $r) {
            if (!isset($topByName[$r->name])) { $topByName[$r->name] = (string) $r->formation; }
        }

        foreach ($rows as $row) {
            $row->loss_rate = $row->leads > 0 ? round($row->lost_leads * 100 / $row->leads) : 0;
            $row->top_program = $topByName[$row->name] ?? '';
        }
        return $rows;
    }

    /** Derniers extraits (filtres) avec score/étape du prospect et état de traitement. */
    public function recent_competitor_mentions(array $f = [], int $limit = 40): array
    {
        $this->ensure_schema();
        [$where, $params] = $this->competitorWhere($f);
        return $this->db->query(
            'SELECT c.id, c.name, c.context, c.lead_id, c.created_at, c.handled,
                    l.name AS lead_name, l.score AS lead_score, l.stage AS lead_stage
             FROM `' . $this->competitorTable() . '` c
             LEFT JOIN `' . $this->table() . '` l ON l.id = c.lead_id'
             . $where . ' AND c.context IS NOT NULL AND c.context <> ""'
             . ' ORDER BY c.created_at DESC LIMIT ' . (int) $limit,
            $params
        )->result();
    }

    /** Tendance des mentions : mois en cours vs mois précédent (avec filtres non temporels). */
    public function competitor_trend(array $f = []): array
    {
        $this->ensure_schema();
        $thisFrom = date('Y-m-01 00:00:00');
        $lastFrom = date('Y-m-01 00:00:00', strtotime('first day of last month'));
        $count = function ($from, $to) use ($f) {
            $ff = $f; $ff['from'] = $from; $ff['to'] = $to;
            [$where, $params] = $this->competitorWhere($ff);
            return (int) $this->db->query(
                'SELECT COUNT(*) AS n FROM `' . $this->competitorTable() . '` c
                 LEFT JOIN `' . $this->table() . '` l ON l.id = c.lead_id' . $where,
                $params
            )->row()->n;
        };
        $current  = $count($thisFrom, date('Y-m-d H:i:s'));
        $previous = $count($lastFrom, $thisFrom);
        $pct = $previous > 0 ? (int) round(($current - $previous) * 100 / $previous) : ($current > 0 ? null : 0);
        return ['current' => $current, 'previous' => $previous, 'pct' => $pct];
    }

    /** Bascule l'état « traité » d'une mention de concurrent. */
    public function toggle_mention_handled(int $id): void
    {
        $row = $this->db->where('id', $id)->get($this->competitorTable())->row();
        if ($row) {
            $this->db->where('id', $id)->update($this->competitorTable(), ['handled' => $row->handled ? 0 : 1]);
        }
    }

    /** Argumentaires de contre indexés par nom de concurrent (minuscule). */
    public function battlecards(): array
    {
        $this->ensure_schema();
        $out = [];
        foreach ($this->db->get($this->battlecardTable())->result() as $r) {
            $out[mb_strtolower(trim((string) $r->name))] = $r;
        }
        return $out;
    }

    public function get_battlecard(string $name)
    {
        return $this->db->where('name', $name)->get($this->battlecardTable())->row();
    }

    /** Crée ou met à jour l'argumentaire de contre d'un concurrent. */
    public function save_battlecard(string $name, string $argument, ?int $staffId = null): void
    {
        $name = trim($name);
        if ($name === '') { return; }
        $existing = $this->db->where('name', $name)->get($this->battlecardTable())->row();
        $data = [
            'name'       => substr($name, 0, 191),
            'argument'   => $argument,
            'staff_id'   => $staffId,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($existing) {
            $this->db->where('id', $existing->id)->update($this->battlecardTable(), $data);
        } else {
            $this->db->insert($this->battlecardTable(), $data);
        }
    }

    /** Concurrents cités par un lead précis. */
    public function competitors_for_lead(int $leadId): array
    {
        return $this->db
            ->where('lead_id', $leadId)
            ->order_by('created_at', 'desc')
            ->get($this->competitorTable())
            ->result();
    }

    /**
     * Leads ayant une conversation, avec le texte concaténé des messages du
     * prospect (rôle « user »), pour analyse concurrentielle par l'IA.
     * On ignore les faux messages de veille (préfixe SIACMP1:).
     * @return array<int,array{lead_id:int,name:string,text:string,last_id:int}>
     */
    public function conversations_for_scan(int $limit = 200): array
    {
        $rows = $this->db
            ->select('c.lead_id, c.id, c.role, c.content, l.name AS lead_name')
            ->from($this->chatTable() . ' c')
            ->join($this->table() . ' l', 'l.id = c.lead_id', 'inner')
            ->where("c.content NOT LIKE 'SIACMP1:%'", null, false)
            ->order_by('c.lead_id', 'asc')
            ->order_by('c.id', 'asc')
            ->get()
            ->result();

        $convos = [];
        foreach ($rows as $r) {
            $lid = (int) $r->lead_id;
            if (!isset($convos[$lid])) {
                $convos[$lid] = ['lead_id' => $lid, 'name' => (string) $r->lead_name, 'text' => '', 'last_id' => 0];
            }
            $who = $r->role === 'user' ? 'Prospect' : 'Conseiller';
            $convos[$lid]['text'] .= $who . ' : ' . trim((string) $r->content) . "\n";
            $convos[$lid]['last_id'] = max($convos[$lid]['last_id'], (int) $r->id);
        }
        return array_slice(array_values($convos), 0, $limit);
    }

    /** Dernier message de chat analysé pour un lead (pour ne pas retraiter). */
    public function competitor_scan_marker(int $leadId): int
    {
        return (int) get_option('sia_comp_scan_' . $leadId);
    }

    public function set_competitor_scan_marker(int $leadId, int $lastId): void
    {
        update_option('sia_comp_scan_' . $leadId, (string) $lastId);
    }

    /** Renseigne la formation d'intérêt d'un lead si elle est encore vide. */
    public function set_formation_if_empty(int $leadId, string $formation): void
    {
        $formation = trim($formation);
        if ($formation === '') {
            return;
        }
        $this->db->where('id', $leadId)
            ->group_start()->where('formation', null)->or_where('formation', '')->group_end()
            ->update($this->table(), ['formation' => substr($formation, 0, 191)]);
    }

    /** Totaux pour les indicateurs de la page veille (avec filtres). */
    public function competitor_totals(array $f = []): array
    {
        $this->ensure_schema();
        [$where, $params] = $this->competitorWhere($f);
        $row = $this->db->query(
            'SELECT COUNT(*) AS mentions, COUNT(DISTINCT c.name) AS concurrents, COUNT(DISTINCT c.lead_id) AS leads
             FROM `' . $this->competitorTable() . '` c
             LEFT JOIN `' . $this->table() . '` l ON l.id = c.lead_id' . $where,
            $params
        )->row();
        return [
            'mentions'    => (int) ($row->mentions ?? 0),
            'concurrents' => (int) ($row->concurrents ?? 0),
            'leads'       => (int) ($row->leads ?? 0),
        ];
    }
}

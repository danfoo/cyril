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

    /** Recherche + filtres (boîte de réception). */
    public function search(array $f, int $limit = 300): array
    {
        $this->ensure_schema();
        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $this->db->group_start()
                ->like('name', $q)->or_like('email', $q)
                ->or_like('formation', $q)->or_like('phone', $q)
                ->group_end();
        }
        if (!empty($f['stage'])) {
            $this->db->where('stage', $f['stage']);
        }
        if (isset($f['min_score']) && $f['min_score'] !== '') {
            $this->db->where('score >=', (float) $f['min_score']);
        }
        return $this->db
            ->order_by('received_at', 'desc')
            ->limit($limit)
            ->get($this->table())
            ->result();
    }

    /** Destinataires d'un envoi groupé e-mail (leads avec e-mail + filtres). */
    public function email_recipients(array $f): array
    {
        $this->ensure_schema();
        $this->db->where('email IS NOT NULL', null, false)->where('email !=', '');
        if (!empty($f['stage'])) {
            $this->db->where('stage', $f['stage']);
        }
        if (!empty($f['program'])) {
            $this->db->like('formation', $f['program']);
        }
        if (isset($f['min_score']) && $f['min_score'] !== '') {
            $this->db->where('score >=', (float) $f['min_score']);
        }
        return $this->db->order_by('score', 'desc')->get($this->table())->result();
    }

    /** Destinataires d'un envoi groupé SMS (leads avec téléphone + filtres). */
    public function sms_recipients(array $f): array
    {
        $this->ensure_schema();
        $this->db->where('phone IS NOT NULL', null, false)->where('phone !=', '');
        if (!empty($f['stage'])) {
            $this->db->where('stage', $f['stage']);
        }
        if (!empty($f['program'])) {
            $this->db->like('formation', $f['program']);
        }
        if (isset($f['min_score']) && $f['min_score'] !== '') {
            $this->db->where('score >=', (float) $f['min_score']);
        }
        return $this->db->order_by('score', 'desc')->get($this->table())->result();
    }

    /** Seuil de date pour une période en jours (0 = tout l'historique). */
    private function since(int $sinceDays): ?string
    {
        return $sinceDays > 0 ? date('Y-m-d H:i:s', time() - $sinceDays * 86400) : null;
    }

    /** Indicateurs pour le tableau de bord (optionnellement sur une période). */
    public function stats(int $hotThreshold = 60, int $sinceDays = 0): array
    {
        $this->ensure_schema();
        $t = $this->table();
        $since = $this->since($sinceDays);

        if ($since) { $this->db->where('received_at >=', $since); }
        $total = (int) $this->db->count_all_results($t);

        if ($since) { $this->db->where('received_at >=', $since); }
        $this->db->where('score >=', $hotThreshold);
        $hot = (int) $this->db->count_all_results($t);

        $byStage = [];
        foreach (array_keys($this->stages()) as $slug) {
            $byStage[$slug] = 0;
        }
        if ($since) { $this->db->where('received_at >=', $since); }
        foreach ($this->db->select('stage, COUNT(*) AS n')->group_by('stage')->get($t)->result() as $r) {
            $byStage[$r->stage] = (int) $r->n;
        }

        $inscrits = $byStage['inscrit'] ?? 0;
        $conversion = $total > 0 ? round($inscrits * 100 / $total, 1) : 0.0;

        return compact('total', 'hot', 'inscrits', 'conversion', 'byStage');
    }

    /** Répartition des leads par source (site plugin, saisie, import). */
    public function by_source(int $sinceDays = 0): array
    {
        $since = $this->since($sinceDays);
        if ($since) { $this->db->where('received_at >=', $since); }
        return $this->db
            ->select("COALESCE(NULLIF(source_site,''),'—') AS src, COUNT(*) AS n")
            ->group_by('source_site')
            ->order_by('n', 'desc')
            ->get($this->table())
            ->result();
    }

    /** Performance par conseiller (leads assignés + inscrits). */
    public function by_staff(int $sinceDays = 0): array
    {
        $since = $this->since($sinceDays);
        $this->db
            ->select('s.staffid, CONCAT(s.firstname, " ", s.lastname) AS name, COUNT(l.id) AS total, '
                . 'SUM(CASE WHEN l.stage = "inscrit" THEN 1 ELSE 0 END) AS inscrits')
            ->from($this->table() . ' l')
            ->join(db_prefix() . 'staff s', 's.staffid = l.owner_id', 'inner')
            ->group_by('l.owner_id')
            ->order_by('total', 'desc');
        if ($since) { $this->db->where('l.received_at >=', $since); }
        return $this->db->get()->result();
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
            'source_site' => !empty($d['source_site']) ? substr((string) $d['source_site'], 0, 191) : 'Saisie manuelle',
            'payload'     => json_encode($d, JSON_UNESCAPED_UNICODE),
            'received_at' => date('Y-m-d H:i:s'),
        ]);
        return (int) $this->db->insert_id();
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
        $this->db->where('id', $id)->update($this->table(), ['stage' => $stage]);
    }

    public function set_owner(int $id, int $staffId): void
    {
        $this->db->where('id', $id)->update($this->table(), ['owner_id' => $staffId ?: null]);
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

        $topFormations = [];
        $this->db->where('received_at >=', $from)->where('received_at <=', $to)->where('formation IS NOT NULL', null, false)->where('formation !=', '');
        foreach ($this->db->select('formation, COUNT(*) n')->group_by('formation')->order_by('n', 'desc')->limit(8)->get($t)->result() as $r) {
            $topFormations[(string) $r->formation] = (int) $r->n;
        }

        $inscrits = (int) $this->db->where('received_at >=', $from)->where('received_at <=', $to)->where('stage', 'inscrit')->count_all_results($t);

        // Messages (e-mails / SMS) envoyés dans la période
        $mt = $this->messagesTable();
        $emailSent = (int) $this->db->where('sent_at >=', $from)->where('sent_at <=', $to)->where('channel', 'email')->count_all_results($mt);
        $emailOpened = (int) $this->db->where('sent_at >=', $from)->where('sent_at <=', $to)->where('channel', 'email')->where('opened_at IS NOT NULL', null, false)->count_all_results($mt);
        $smsSent = (int) $this->db->where('sent_at >=', $from)->where('sent_at <=', $to)->where('channel', 'sms')->where('status', 'sent')->count_all_results($mt);

        // Tâches terminées dans la période
        $tasksDone = (int) $this->db->where('created_at >=', $from)->where('created_at <=', $to)->where('type', 'task')->count_all_results($this->activityTable());

        return [
            'leads_total'    => $leadsTotal,
            'inscrits'       => $inscrits,
            'conversion'     => $leadsTotal > 0 ? round($inscrits * 100 / $leadsTotal, 1) : 0,
            'by_stage'       => $byStage,
            'by_source'      => $bySource,
            'top_formations' => $topFormations,
            'email_sent'     => $emailSent,
            'email_opened'   => $emailOpened,
            'open_rate'      => $emailSent > 0 ? round($emailOpened * 100 / $emailSent, 1) : 0,
            'sms_sent'       => $smsSent,
            'tasks'          => $tasksDone,
        ];
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

    public function delete_report(int $id): void
    {
        $this->db->where('id', $id)->delete(db_prefix() . 'school_ia_reports');
    }

    // ---------- Messages & statistiques de campagne ----------

    private function messagesTable(): string
    {
        return db_prefix() . 'school_ia_messages';
    }

    /** Enregistre un message envoyé et renvoie son jeton de suivi. */
    public function log_message(array $d): string
    {
        $token = bin2hex(random_bytes(8));
        $this->db->insert($this->messagesTable(), [
            'lead_id'  => (int) ($d['lead_id'] ?? 0) ?: null,
            'channel'  => in_array($d['channel'] ?? 'email', ['email', 'sms'], true) ? $d['channel'] : 'email',
            'campaign' => substr((string) ($d['campaign'] ?? 'single'), 0, 20),
            'subject'  => isset($d['subject']) ? substr((string) $d['subject'], 0, 255) : null,
            'token'    => $token,
            'status'   => substr((string) ($d['status'] ?? 'sent'), 0, 12),
            'clicks'   => 0,
            'staff_id' => $d['staff_id'] ?? null,
            'sent_at'  => date('Y-m-d H:i:s'),
        ]);
        return $token;
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

    /** Agrégats pour la page Statistiques (sur une période optionnelle). */
    public function message_stats(int $sinceDays = 0): array
    {
        $t = $this->messagesTable();
        $since = $this->since($sinceDays);
        $w = function () use ($since) { if ($since) { $this->db->where('sent_at >=', $since); } };

        $w(); $this->db->where('channel', 'email'); $emailSent = (int) $this->db->count_all_results($t);
        $w(); $this->db->where('channel', 'email')->where('opened_at IS NOT NULL', null, false); $emailOpened = (int) $this->db->count_all_results($t);
        $w(); $this->db->where('channel', 'email')->where('clicks >', 0); $emailClicked = (int) $this->db->count_all_results($t);

        $w(); $this->db->where('channel', 'sms'); $smsTotal = (int) $this->db->count_all_results($t);
        $w(); $this->db->where('channel', 'sms')->where('status', 'sent'); $smsSent = (int) $this->db->count_all_results($t);
        $w(); $this->db->where('channel', 'sms')->where('status', 'failed'); $smsFailed = (int) $this->db->count_all_results($t);

        return [
            'email_sent'    => $emailSent,
            'email_opened'  => $emailOpened,
            'email_clicked' => $emailClicked,
            'open_rate'     => $emailSent > 0 ? round($emailOpened * 100 / $emailSent, 1) : 0.0,
            'click_rate'    => $emailSent > 0 ? round($emailClicked * 100 / $emailSent, 1) : 0.0,
            'sms_total'     => $smsTotal,
            'sms_sent'      => $smsSent,
            'sms_failed'    => $smsFailed,
        ];
    }

    /** Derniers messages (pour le détail de la page Statistiques). */
    public function recent_messages(int $limit = 50): array
    {
        return $this->db->select('m.*, l.name AS lead_name')
            ->from($this->messagesTable() . ' m')
            ->join($this->table() . ' l', 'l.id = m.lead_id', 'left')
            ->order_by('m.sent_at', 'desc')->limit($limit)->get()->result();
    }

    /** Journal global : toutes les activités, avec le nom du lead (filtrable). */
    public function global_activities(?string $type = null, int $limit = 300): array
    {
        $this->db->select('a.*, l.name AS lead_name')
            ->from($this->activityTable() . ' a')
            ->join($this->table() . ' l', 'l.id = a.lead_id', 'left')
            ->order_by('a.created_at', 'desc')
            ->limit($limit);
        if ($type) {
            $this->db->where('a.type', $type);
        }
        return $this->db->get()->result();
    }

    public function add_activity(int $leadId, string $type, string $content, ?int $staffId = null): void
    {
        $this->db->insert($this->activityTable(), [
            'lead_id'    => $leadId,
            'type'       => $type,
            'content'    => $content,
            'staff_id'   => $staffId,
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

    public function add_task(int $leadId, string $title, ?string $dueAt, ?int $staffId = null): void
    {
        $this->db->insert($this->tasksTable(), [
            'lead_id'    => $leadId,
            'title'      => substr($title, 0, 255),
            'due_at'     => $dueAt ?: null,
            'done'       => 0,
            'staff_id'   => $staffId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $label = $dueAt ? ' (échéance ' . date('d/m/Y H:i', strtotime($dueAt)) . ')' : '';
        $this->add_activity($leadId, 'task', 'Tâche : ' . $title . $label, $staffId);
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
    public function pending_tasks(int $limit = 200): array
    {
        return $this->db
            ->select('t.*, l.name AS lead_name')
            ->from($this->tasksTable() . ' t')
            ->join($this->table() . ' l', 'l.id = t.lead_id', 'left')
            ->where('t.done', 0)
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

    /** Historique de la conversation IA d'un lead, ordre chronologique. */
    public function chat_messages(int $leadId): array
    {
        return $this->db
            ->where('lead_id', $leadId)
            ->order_by('id', 'asc')
            ->get($this->chatTable())
            ->result();
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

    /** Classement des concurrents : mentions + nombre de prospects concernés. */
    public function competitor_ranking(int $limit = 50): array
    {
        return $this->db->query(
            'SELECT name, COUNT(*) AS mentions, COUNT(DISTINCT lead_id) AS leads, MAX(created_at) AS derniere
             FROM `' . $this->competitorTable() . '`
             GROUP BY name ORDER BY mentions DESC LIMIT ' . (int) $limit
        )->result();
    }

    /** Derniers extraits de contexte (avec le nom du lead pour le lien). */
    public function recent_competitor_mentions(int $limit = 30): array
    {
        return $this->db->query(
            'SELECT c.name, c.context, c.lead_id, c.created_at, l.name AS lead_name
             FROM `' . $this->competitorTable() . '` c
             LEFT JOIN `' . $this->table() . "` l ON l.id = c.lead_id
             WHERE c.context IS NOT NULL AND c.context <> ''
             ORDER BY c.created_at DESC LIMIT " . (int) $limit
        )->result();
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

    /** Totaux pour les indicateurs de la page veille. */
    public function competitor_totals(): array
    {
        $row = $this->db->query(
            'SELECT COUNT(*) AS mentions, COUNT(DISTINCT name) AS concurrents, COUNT(DISTINCT lead_id) AS leads
             FROM `' . $this->competitorTable() . '`'
        )->row();
        return [
            'mentions'    => (int) ($row->mentions ?? 0),
            'concurrents' => (int) ($row->concurrents ?? 0),
            'leads'       => (int) ($row->leads ?? 0),
        ];
    }
}

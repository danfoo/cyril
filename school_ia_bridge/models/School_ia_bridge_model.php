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
     */
    public function save_lead(array $p): int
    {
        $data = [
            'name'        => isset($p['name']) ? substr((string) $p['name'], 0, 191) : null,
            'email'       => isset($p['email']) ? substr((string) $p['email'], 0, 191) : null,
            'phone'       => isset($p['phone']) ? substr((string) $p['phone'], 0, 64) : null,
            'formation'   => isset($p['formation']) ? substr((string) $p['formation'], 0, 191) : null,
            'score'       => isset($p['score']) ? (float) $p['score'] : 0,
            'band'        => isset($p['band']) ? substr((string) $p['band'], 0, 32) : null,
            'source_site' => isset($p['source_site']) ? substr((string) $p['source_site'], 0, 191) : null,
            'external_id' => isset($p['external_id']) ? substr((string) $p['external_id'], 0, 64) : null,
            'description' => isset($p['description']) ? (string) $p['description'] : null,
            'payload'     => json_encode($p, JSON_UNESCAPED_UNICODE),
            'received_at' => date('Y-m-d H:i:s'),
        ];

        // Mise à jour si on a déjà reçu ce lead (même external_id + site).
        if (!empty($data['external_id']) && !empty($data['source_site'])) {
            $existing = $this->db
                ->where('external_id', $data['external_id'])
                ->where('source_site', $data['source_site'])
                ->get($this->table())
                ->row();
            if ($existing) {
                $this->db->where('id', $existing->id)->update($this->table(), $data);
                return (int) $existing->id;
            }
        }

        $this->db->insert($this->table(), $data);
        return (int) $this->db->insert_id();
    }
}

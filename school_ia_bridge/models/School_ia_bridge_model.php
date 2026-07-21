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

    /** Indicateurs pour le tableau de bord. */
    public function stats(int $hotThreshold = 60): array
    {
        $this->ensure_schema();
        $t = $this->table();

        $total = (int) $this->db->count_all($t);
        $hot = (int) $this->db->from($t)->where('score >=', $hotThreshold)->count_all_results();

        $byStage = [];
        foreach (array_keys($this->stages()) as $slug) {
            $byStage[$slug] = 0;
        }
        foreach ($this->db->select('stage, COUNT(*) AS n')->group_by('stage')->get($t)->result() as $r) {
            $byStage[$r->stage] = (int) $r->n;
        }

        $inscrits = $byStage['inscrit'] ?? 0;
        $conversion = $total > 0 ? round($inscrits * 100 / $total, 1) : 0.0;

        return [
            'total'      => $total,
            'hot'        => $hot,
            'inscrits'   => $inscrits,
            'conversion' => $conversion,
            'byStage'    => $byStage,
        ];
    }

    public function get_lead(int $id)
    {
        return $this->db->where('id', $id)->get($this->table())->row();
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

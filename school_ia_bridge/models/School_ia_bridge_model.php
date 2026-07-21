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
        if (!$this->db->field_exists('stage', $this->table())) {
            $this->db->query('ALTER TABLE `' . $this->table() . "` ADD `stage` VARCHAR(32) NOT NULL DEFAULT 'nouveau'");
        }
        if (!$this->db->field_exists('owner_id', $this->table())) {
            $this->db->query('ALTER TABLE `' . $this->table() . '` ADD `owner_id` INT NULL DEFAULT NULL');
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

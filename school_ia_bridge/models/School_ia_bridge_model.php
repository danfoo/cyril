<?php

defined('BASEPATH') or exit('No direct script access allowed');

class School_ia_bridge_model extends App_Model
{
    private function table(): string
    {
        return db_prefix() . 'school_ia_leads';
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

    /**
     * Ajoute la colonne perfex_lead_id si besoin (sans migration : simple
     * ALTER conditionnel, exécuté à la volée).
     */
    public function ensure_schema(): void
    {
        if (!$this->db->field_exists('perfex_lead_id', $this->table())) {
            $this->db->query('ALTER TABLE `' . $this->table() . '` ADD `perfex_lead_id` INT NULL DEFAULT NULL');
        }
    }

    /** Source Perfex « School IA » (créée si absente). */
    private function source_id(): int
    {
        $row = $this->db->where('name', 'School IA')->get(db_prefix() . 'leads_sources')->row();
        if ($row) {
            return (int) $row->id;
        }
        $this->db->insert(db_prefix() . 'leads_sources', ['name' => 'School IA']);
        return (int) $this->db->insert_id();
    }

    /** Statut de lead par défaut (le premier dans l'ordre d'affichage). */
    private function default_status_id(): int
    {
        $row = $this->db->order_by('statusorder', 'asc')->limit(1)->get(db_prefix() . 'leads_status')->row();
        return $row ? (int) $row->id : 1;
    }

    /** Leads reçus pas encore convertis en leads Perfex. */
    public function unconverted(int $limit = 500): array
    {
        $this->ensure_schema();
        return $this->db
            ->group_start()->where('perfex_lead_id', null)->or_where('perfex_lead_id', 0)->group_end()
            ->order_by('received_at', 'desc')
            ->limit($limit)
            ->get($this->table())
            ->result();
    }

    /**
     * Convertit un lead reçu en lead natif Perfex (via leads_model), et mémorise
     * l'id Perfex pour éviter les doublons. Renvoie l'id Perfex, ou 0 en échec.
     */
    public function convert_to_perfex(object $row): int
    {
        $this->ensure_schema();
        if (!empty($row->perfex_lead_id)) {
            return (int) $row->perfex_lead_id;
        }

        $CI = &get_instance();
        $CI->load->model('leads_model');

        $data = [
            'name'        => $row->name ?: ('Lead ' . $row->external_id),
            'email'       => (string) $row->email,
            'phonenumber' => (string) $row->phone,
            'source'      => $this->source_id(),
            'status'      => $this->default_status_id(),
            'description' => (string) $row->description,
            'assigned'    => 0,
            'dateadded'   => date('Y-m-d H:i:s'),
        ];

        try {
            $perfexId = (int) $CI->leads_model->add($data);
        } catch (\Throwable $e) {
            log_message('error', '[school_ia_bridge] Conversion lead échouée: ' . $e->getMessage());
            return 0;
        }

        if ($perfexId > 0) {
            $this->db->where('id', $row->id)->update($this->table(), ['perfex_lead_id' => $perfexId]);
        }
        return $perfexId;
    }
}

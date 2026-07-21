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
}

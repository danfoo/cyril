<?php

defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();

// Table de stockage des leads reçus depuis le plugin School IA.
if (!$CI->db->table_exists(db_prefix() . 'school_ia_leads')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "school_ia_leads` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(191) DEFAULT NULL,
        `email` varchar(191) DEFAULT NULL,
        `phone` varchar(64) DEFAULT NULL,
        `formation` varchar(191) DEFAULT NULL,
        `score` decimal(6,2) NOT NULL DEFAULT '0.00',
        `band` varchar(32) DEFAULT NULL,
        `source_site` varchar(191) DEFAULT NULL,
        `external_id` varchar(64) DEFAULT NULL,
        `description` text DEFAULT NULL,
        `payload` longtext DEFAULT NULL,
        `received_at` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `external_id` (`external_id`),
        KEY `email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

// Secret partagé (à recopier dans les réglages du plugin School IA).
if (get_option('school_ia_bridge_secret') == '') {
    add_option('school_ia_bridge_secret', bin2hex(random_bytes(16)), 1);
}

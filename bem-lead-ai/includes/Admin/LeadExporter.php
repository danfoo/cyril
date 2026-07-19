<?php

namespace BemLeadAi\Admin;

use BemLeadAi\Core\Options;
use BemLeadAi\Crm\CrmRepository;

defined('ABSPATH') || exit;

/**
 * Export de la liste des leads en CSV (Excel-compatible) ou XLSX natif.
 *
 * Respecte les filtres actifs de la page Leads (recherche, étape, température,
 * responsable) transmis dans l'URL. Le CSV est encodé UTF-8 avec BOM et
 * séparateur « ; » pour s'ouvrir proprement dans Excel francophone ; le XLSX
 * est un vrai classeur Office Open XML généré sans dépendance (ZipArchive).
 */
final class LeadExporter
{
    /** En-têtes de colonnes du fichier exporté. */
    private static function columns(): array
    {
        return [
            __('ID', 'bem-lead-ai'),
            __('Créé le', 'bem-lead-ai'),
            __('Dernière activité', 'bem-lead-ai'),
            __('Prénom', 'bem-lead-ai'),
            __('Email', 'bem-lead-ai'),
            __('Téléphone', 'bem-lead-ai'),
            __('Formation d\'intérêt', 'bem-lead-ai'),
            __('Score final', 'bem-lead-ai'),
            __('Score comportemental', 'bem-lead-ai'),
            __('Score intention', 'bem-lead-ai'),
            __('Température', 'bem-lead-ai'),
            __('Statut', 'bem-lead-ai'),
            __('Étape pipeline', 'bem-lead-ai'),
            __('Responsable', 'bem-lead-ai'),
            __('Consentement', 'bem-lead-ai'),
            __('Canaux', 'bem-lead-ai'),
        ];
    }

    /** URL d'export (bouton), nonce + filtres courants conservés. */
    public static function url(string $format, array $filters): string
    {
        $args = array_filter([
            'action' => 'bem_export_leads',
            'format' => $format === 'xlsx' ? 'xlsx' : 'csv',
            's' => $filters['s'] ?? '',
            'stage' => $filters['stage'] ?? '',
            'band' => $filters['band'] ?? '',
            'owner' => (int) ($filters['owner'] ?? 0),
        ], static fn($v) => $v !== '' && $v !== 0 && $v !== null);
        $url = add_query_arg($args, admin_url('admin-post.php'));
        return wp_nonce_url($url, 'bem_export_leads');
    }

    /* --- Handler -------------------------------------------------------- */

    public static function handleExport(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die('Forbidden');
        }
        check_admin_referer('bem_export_leads');

        $format = (isset($_GET['format']) && $_GET['format'] === 'xlsx') ? 'xlsx' : 'csv';
        // XLSX exige ZipArchive ; à défaut on bascule proprement sur CSV.
        if ($format === 'xlsx' && !class_exists('ZipArchive')) {
            $format = 'csv';
        }

        $rows = self::rows();
        $headers = self::columns();
        $filename = 'leads-' . date_i18n('Ymd-Hi');

        if (function_exists('nocache_headers')) {
            nocache_headers();
        }

        if ($format === 'xlsx') {
            self::streamXlsx($headers, $rows, $filename . '.xlsx');
        } else {
            self::streamCsv($headers, $rows, $filename . '.csv');
        }
        exit;
    }

    /* --- Données -------------------------------------------------------- */

    /** Lignes de données (mêmes filtres que la page Leads). */
    private static function rows(): array
    {
        global $wpdb;
        $p = $wpdb->prefix;

        [$whereSql, $args] = self::buildWhere();
        $sql = "SELECT * FROM {$p}bem_leads WHERE {$whereSql} ORDER BY score_final DESC, last_seen DESC";
        $leads = $args ? $wpdb->get_results($wpdb->prepare($sql, $args)) : $wpdb->get_results($sql);
        $leads = $leads ?: [];

        $stages = CrmRepository::stages();
        $rows = [];
        $ownerCache = [];
        foreach ($leads as $l) {
            $ownerId = (int) ($l->owner_id ?? 0);
            if ($ownerId && !isset($ownerCache[$ownerId])) {
                $u = get_userdata($ownerId);
                $ownerCache[$ownerId] = $u ? $u->display_name : '';
            }
            $stageLabel = isset($stages[$l->pipeline_stage]) ? $stages[$l->pipeline_stage]['label'] : (string) $l->pipeline_stage;

            $rows[] = [
                (int) $l->id,
                self::date($l->first_seen),
                self::date($l->last_seen),
                (string) ($l->prenom ?? ''),
                (string) ($l->email ?? ''),
                (string) ($l->phone ?? ''),
                (string) ($l->formation_interet ?? ''),
                self::num($l->score_final),
                self::num($l->score_comportemental),
                self::num($l->score_intention),
                self::band((float) $l->score_final),
                (string) ($l->statut ?? ''),
                $stageLabel,
                $ownerId ? ($ownerCache[$ownerId] ?? '') : '',
                ((int) $l->consent) === 1 ? __('Oui', 'bem-lead-ai') : __('Non', 'bem-lead-ai'),
                (string) ($l->channels ?? ''),
            ];
        }
        return $rows;
    }

    /** Reconstruit la clause WHERE à partir des filtres GET (comme la liste). */
    private static function buildWhere(): array
    {
        global $wpdb;
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash((string) $_GET['s'])) : '';
        $fStage = isset($_GET['stage']) ? sanitize_key((string) $_GET['stage']) : '';
        $fBand = isset($_GET['band']) ? sanitize_key((string) $_GET['band']) : '';
        $fOwner = isset($_GET['owner']) ? (int) $_GET['owner'] : 0;

        $where = ['1=1'];
        $args = [];
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(prenom LIKE %s OR email LIKE %s OR phone LIKE %s OR formation_interet LIKE %s)';
            array_push($args, $like, $like, $like, $like);
        }
        if (CrmRepository::isStage($fStage)) {
            $where[] = 'pipeline_stage = %s';
            $args[] = $fStage;
        }
        if ($fOwner > 0) {
            $where[] = 'owner_id = %d';
            $args[] = $fOwner;
        }
        foreach (self::bandRange($fBand) as $clause) {
            [$sql, $val] = $clause;
            $where[] = $sql;
            $args[] = $val;
        }
        return [implode(' AND ', $where), $args];
    }

    /** Bornes de score pour une température donnée. */
    private static function bandRange(string $band): array
    {
        $warm = (float) Options::get('threshold_warm');
        $hot = (float) Options::get('threshold_hot');
        $vhot = (float) Options::get('threshold_very_hot');
        $map = [
            'tres_chaud' => [$vhot, null],
            'chaud' => [$hot, $vhot],
            'tiede' => [$warm, $hot],
            'froid' => [null, $warm],
        ];
        if (!isset($map[$band])) {
            return [];
        }
        [$min, $max] = $map[$band];
        $out = [];
        if ($min !== null) { $out[] = ['score_final >= %f', $min]; }
        if ($max !== null) { $out[] = ['score_final < %f', $max]; }
        return $out;
    }

    /** Étiquette de température lisible à partir du score final. */
    private static function band(float $score): string
    {
        $warm = (float) Options::get('threshold_warm');
        $hot = (float) Options::get('threshold_hot');
        $vhot = (float) Options::get('threshold_very_hot');
        if ($score >= $vhot) { return __('Très chaud', 'bem-lead-ai'); }
        if ($score >= $hot) { return __('Chaud', 'bem-lead-ai'); }
        if ($score >= $warm) { return __('Tiède', 'bem-lead-ai'); }
        return __('Froid', 'bem-lead-ai');
    }

    private static function date(?string $mysql): string
    {
        return $mysql ? mysql2date('d/m/Y H:i', $mysql) : '';
    }

    private static function num($v): float
    {
        return round((float) $v, 1);
    }

    /* --- CSV ------------------------------------------------------------ */

    private static function streamCsv(array $headers, array $rows, string $filename): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        // BOM UTF-8 → Excel reconnaît l'encodage et les accents s'affichent bien.
        fwrite($out, "\xEF\xBB\xBF");
        // Séparateur « ; » : attendu par Excel en configuration francophone.
        fputcsv($out, $headers, ';');
        foreach ($rows as $row) {
            fputcsv($out, $row, ';');
        }
        fclose($out);
    }

    /* --- XLSX (Office Open XML, sans dépendance) ------------------------ */

    private static function streamXlsx(array $headers, array $rows, string $filename): void
    {
        $tmp = self::buildXlsxFile($headers, $rows);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . (string) filesize($tmp));
        readfile($tmp);
        @unlink($tmp);
    }

    /** Assemble un classeur XLSX minimal dans un fichier temporaire, renvoie son chemin. */
    private static function buildXlsxFile(array $headers, array $rows): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'bemxlsx');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '</Types>');

        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');

        $zip->addFromString('xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Leads" sheetId="1" r:id="rId1"/></sheets></workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '</Relationships>');

        $zip->addFromString('xl/worksheets/sheet1.xml', self::sheetXml($headers, $rows));

        $zip->close();
        return $tmp;
    }

    /** Feuille de calcul : en-tête + lignes, chaînes en inlineStr, nombres en n. */
    private static function sheetXml(array $headers, array $rows): string
    {
        $all = array_merge([$headers], $rows);
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ($all as $r => $cells) {
            $rowNum = $r + 1;
            $xml .= '<row r="' . $rowNum . '">';
            foreach (array_values($cells) as $c => $value) {
                $ref = self::colLetter($c) . $rowNum;
                if ($r > 0 && is_numeric($value) && !is_string($value)) {
                    $xml .= '<c r="' . $ref . '" t="n"><v>' . $value . '</v></c>';
                } else {
                    $xml .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">'
                        . self::esc((string) $value) . '</t></is></c>';
                }
            }
            $xml .= '</row>';
        }
        return $xml . '</sheetData></worksheet>';
    }

    /** Index de colonne 0-based → lettre(s) Excel (0→A, 26→AA). */
    private static function colLetter(int $index): string
    {
        $letter = '';
        $index++;
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letter = chr(65 + $mod) . $letter;
            $index = (int) (($index - $mod) / 26);
        }
        return $letter;
    }

    private static function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}

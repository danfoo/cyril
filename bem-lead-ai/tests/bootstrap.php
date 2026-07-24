<?php
/**
 * Amorçage des tests : le plugin dépend de WordPress, indisponible en CLI.
 * On fournit donc des stubs minimalistes des fonctions WP et de faux
 * collaborateurs (store de leads en mémoire) partageant les namespaces du code
 * réel, puis on charge les VRAIES classes testées (PhoneNumber, FormCapture).
 */

namespace {
    if (!defined('ABSPATH')) {
        define('ABSPATH', sys_get_temp_dir() . '/');
    }

    if (!defined('DAY_IN_SECONDS')) {
        define('DAY_IN_SECONDS', 86400);
    }
    if (!defined('HOUR_IN_SECONDS')) {
        define('HOUR_IN_SECONDS', 3600);
    }

    /* ---------- Stubs de fonctions WordPress ---------- */
    if (!function_exists('is_email'))              { function is_email($e){ return is_string($e) && strpos($e, '@') !== false ? $e : false; } }
    if (!function_exists('sanitize_email'))        { function sanitize_email($e){ return trim((string) $e); } }
    if (!function_exists('sanitize_text_field'))   { function sanitize_text_field($s){ return trim(preg_replace('/\s+/', ' ', (string) $s)); } }
    if (!function_exists('sanitize_textarea_field')){ function sanitize_textarea_field($s){ return trim((string) $s); } }
    if (!function_exists('wp_unslash'))            { function wp_unslash($s){ return $s; } }
    if (!function_exists('wp_salt'))               { function wp_salt($s = 'auth'){ return 'test-salt'; } }
    if (!function_exists('wp_json_encode'))        { function wp_json_encode($d){ return json_encode($d); } }
    if (!function_exists('do_action'))             { function do_action($h, ...$a){ /* no-op */ } }

    // Options WordPress (état runtime) — store mémoire, distinct des réglages
    // du plugin (gérés par le faux BemLeadAi\Core\Options).
    $GLOBALS['__wp_options'] = [];
    if (!function_exists('get_option'))    { function get_option($k, $default = false){ return $GLOBALS['__wp_options'][$k] ?? $default; } }
    if (!function_exists('update_option')) { function update_option($k, $v, $autoload = null){ $GLOBALS['__wp_options'][$k] = $v; return true; } }
    if (!function_exists('delete_option')) { function delete_option($k){ unset($GLOBALS['__wp_options'][$k]); return true; } }
    // Horloge contrôlable : FakeLeadStore::$now fige le temps pour tester la
    // décroissance du score de façon déterministe (null = temps réel).
    if (!function_exists('current_time')) {
        function current_time($type){
            $ts = \FakeLeadStore::$now ?? time();
            return $type === 'timestamp' ? $ts : date('Y-m-d H:i:s', $ts);
        }
    }

    /**
     * $wpdb minimal : route get_results() selon la table visée (le code ne teste
     * pas le filtrage SQL — non reproductible sans base — mais la logique PHP
     * qui consomme les lignes renvoyées).
     */
    final class FakeWpdb
    {
        public string $prefix = 'wp_';
        /** @var object[] Règles de scoring (RulesRepository::activeRules). */
        public array $rules = [];
        /** @var object[] Leads candidats au désengagement. */
        public array $candidates = [];
        /** @var object[] Lignes d'événements (DisengagementDetector::activityWindows). */
        public array $eventRows = [];

        public function prepare($query, ...$args){ return $query; }

        public function get_results($sql)
        {
            if (str_contains($sql, 'bem_scoring_rules')) return $this->rules;
            if (str_contains($sql, 'bem_leads'))         return $this->candidates;
            if (str_contains($sql, 'bem_events'))        return $this->eventRows;
            return [];
        }
    }
    $GLOBALS['wpdb'] = new FakeWpdb();

    /**
     * Store de leads en mémoire + configuration, partagé par les faux
     * collaborateurs. Réinitialisé entre chaque test.
     */
    final class FakeLeadStore
    {
        /** @var array<int,object> */
        public static array $leads = [];
        public static int $seq = 0;
        /** @var array<int,object> Événements par lead (pour EventRepository::forLead). */
        public static array $events = [];
        /** Horloge figée pour tester la décroissance (null = temps réel). */
        public static ?int $now = null;
        /** Dernier événement d'un type donné (EventRepository::lastOfType). */
        public static ?object $lastOfType = null;
        /** @var array<string,mixed> */
        public static array $options = [];

        private const DEFAULT_OPTIONS = [
            'default_dial_code' => '+224',
            'score_blend_intent_weight' => 0.55,
            'score_decay_half_life_days' => 7,
            'threshold_warm' => 30,
            'threshold_hot' => 60,
            'threshold_very_hot' => 80,
            'disengagement_min_score' => 30,
            'disengagement_drop_ratio' => 0.7,
        ];

        public static function reset(): void
        {
            self::$leads = [];
            self::$events = [];
            self::$seq = 0;
            self::$now = null;
            self::$lastOfType = null;
            self::$options = self::DEFAULT_OPTIONS;
            $GLOBALS['wpdb']->rules = [];
            $GLOBALS['wpdb']->candidates = [];
            $GLOBALS['wpdb']->eventRows = [];
            $GLOBALS['__wp_options'] = [];
            \BemLeadAi\Leads\EventRepository::$records = [];
        }

        /** Déclare un lead candidat au désengagement (id servi par le faux $wpdb). */
        public static function addCandidate(int $id): void
        {
            $GLOBALS['wpdb']->candidates[] = (object) ['id' => $id];
        }

        /** Ajoute une ligne d'événement brute (created_at) pour activityWindows(). */
        public static function addRawEvent(string $createdAt): void
        {
            $GLOBALS['wpdb']->eventRows[] = (object) ['created_at' => $createdAt];
        }

        /** Ajoute un événement pour un lead (created_at au format mysql). */
        public static function addEvent(int $leadId, string $type, array $payload, string $createdAt): void
        {
            self::$events[] = (object) [
                'lead_id' => $leadId,
                'type' => $type,
                'payload' => json_encode($payload),
                'created_at' => $createdAt,
            ];
        }

        /** Déclare une règle de scoring active (servie via le faux $wpdb). */
        public static function addRule(string $type, array $condition, float $weight): void
        {
            $GLOBALS['wpdb']->rules[] = (object) [
                'type' => $type,
                'condition_json' => json_encode($condition),
                'poids' => $weight,
                'actif' => 1,
            ];
        }

        public static function insert(array $data): object
        {
            $id = ++self::$seq;
            $lead = (object) array_merge([
                'id' => $id, 'session_id' => null, 'email' => null, 'phone' => null,
                'prenom' => null, 'formation_interet' => null, 'source_form' => null,
                'consent' => 0, 'canal' => 'web',
            ], $data, ['id' => $id]);
            self::$leads[$id] = $lead;
            return $lead;
        }
    }
}

/* ---------- Faux collaborateurs (mêmes namespaces que le code réel) ---------- */
namespace BemLeadAi\Core {
    class Options
    {
        public static function get($key)
        {
            return \FakeLeadStore::$options[$key] ?? '';
        }
    }
}

namespace BemLeadAi\Knowledge {
    class KnowledgeBaseBuilder
    {
        public function __construct() {}
        public function programTitles(): array { return []; }
    }
}

namespace BemLeadAi\Leads {
    class LeadRepository
    {
        public function findByEmail($email)
        {
            foreach (\FakeLeadStore::$leads as $l) {
                if ($email && $l->email === $email) return $l;
            }
            return null;
        }

        public function findBySessionId($sid)
        {
            foreach (\FakeLeadStore::$leads as $l) {
                if ($sid !== '' && $l->session_id === $sid) return $l;
            }
            return null;
        }

        public function findById($id) { return \FakeLeadStore::$leads[$id] ?? null; }

        public function findOrCreate($sid, $canal = 'web')
        {
            $existing = $this->findBySessionId($sid);
            if ($existing) return $existing;
            return \FakeLeadStore::insert(['session_id' => $sid, 'canal' => $canal]);
        }

        public function update($id, array $updates): void
        {
            if (!isset(\FakeLeadStore::$leads[$id])) return;
            foreach ($updates as $k => $v) {
                \FakeLeadStore::$leads[$id]->$k = $v;
            }
        }

        public function delete($id): void { unset(\FakeLeadStore::$leads[$id]); }
    }

    class EventRepository
    {
        /** @var array<int,array{lead:int,type:string,payload:array}> */
        public static array $records = [];
        public function record($leadId, $type, $payload = [], $canal = ''): int
        {
            self::$records[] = ['lead' => (int) $leadId, 'type' => (string) $type, 'payload' => (array) $payload];
            return count(self::$records);
        }

        /** Événements du lead, ordre chronologique (comme le vrai forLead). */
        public function forLead($leadId, $sinceDays = 90): array
        {
            $rows = array_filter(\FakeLeadStore::$events, fn($e) => (int) $e->lead_id === (int) $leadId);
            usort($rows, fn($a, $b) => strcmp($a->created_at, $b->created_at));
            return array_values($rows);
        }

        /** Dernier événement d'un type (pour la dédup de relance). */
        public function lastOfType($leadId, $type)
        {
            return \FakeLeadStore::$lastOfType;
        }

        /** Nombre d'événements « disengagement » enregistrés (aide aux assertions). */
        public static function countRecorded(string $type): int
        {
            return count(array_filter(self::$records, fn($r) => $r['type'] === $type));
        }
    }
}

namespace BemLeadAi\Chat {
    /**
     * Réplique fidèle de la vraie logique de rattachement d'identité : une
     * collision d'email fusionne sur la fiche la plus ancienne (migration du
     * session_id + suppression du doublon).
     */
    class ChannelAdapter
    {
        private $leads;
        public function __construct() { $this->leads = new \BemLeadAi\Leads\LeadRepository(); }

        public function attachIdentity(object $lead, ?string $email = null, ?string $phone = null): object
        {
            $updates = [];
            if ($email && \is_email($email)) {
                $existing = $this->leads->findByEmail($email);
                if ($existing && (int) $existing->id !== (int) $lead->id) {
                    $this->leads->update((int) $existing->id, ['session_id' => $lead->session_id]);
                    $this->leads->delete((int) $lead->id);
                    $lead = $this->leads->findById((int) $existing->id);
                } else {
                    $updates['email'] = \sanitize_email($email);
                }
            }
            if ($phone) {
                $updates['phone'] = \sanitize_text_field($phone);
            }
            if ($updates) {
                $this->leads->update((int) $lead->id, $updates);
            }
            return $this->leads->findById((int) $lead->id);
        }
    }
}

/* ---------- Chargement des VRAIES classes testées ---------- */
namespace {
    require __DIR__ . '/../includes/Support/PhoneNumber.php';
    require __DIR__ . '/../includes/Integrations/FormCapture.php';
    require __DIR__ . '/../includes/Scoring/RulesRepository.php';
    require __DIR__ . '/../includes/Scoring/ScoringEngine.php';
    require __DIR__ . '/../includes/Scoring/DisengagementDetector.php';
    require __DIR__ . '/../includes/Crm/PayloadCodec.php';
    require __DIR__ . '/../includes/Core/CronHealth.php';
}

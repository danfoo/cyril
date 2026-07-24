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

    /* ---------- Stubs de fonctions WordPress ---------- */
    if (!function_exists('is_email'))              { function is_email($e){ return is_string($e) && strpos($e, '@') !== false ? $e : false; } }
    if (!function_exists('sanitize_email'))        { function sanitize_email($e){ return trim((string) $e); } }
    if (!function_exists('sanitize_text_field'))   { function sanitize_text_field($s){ return trim(preg_replace('/\s+/', ' ', (string) $s)); } }
    if (!function_exists('sanitize_textarea_field')){ function sanitize_textarea_field($s){ return trim((string) $s); } }
    if (!function_exists('wp_unslash'))            { function wp_unslash($s){ return $s; } }
    if (!function_exists('wp_salt'))               { function wp_salt($s = 'auth'){ return 'test-salt'; } }

    /**
     * Store de leads en mémoire + configuration, partagé par les faux
     * collaborateurs. Réinitialisé entre chaque test.
     */
    final class FakeLeadStore
    {
        /** @var array<int,object> */
        public static array $leads = [];
        public static int $seq = 0;
        /** @var array<string,mixed> */
        public static array $options = ['default_dial_code' => '+224'];

        public static function reset(): void
        {
            self::$leads = [];
            self::$seq = 0;
            self::$options = ['default_dial_code' => '+224'];
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
        /** @var array<int,array{lead:int,type:string}> */
        public static array $events = [];
        public function record($leadId, $type, $payload = [], $canal = ''): void
        {
            self::$events[] = ['lead' => (int) $leadId, 'type' => (string) $type];
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
}

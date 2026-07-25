<?php

namespace BemLeadAi\Integrations;

use BemLeadAi\Chat\ChannelAdapter;
use BemLeadAi\Core\Options;
use BemLeadAi\Knowledge\KnowledgeBaseBuilder;
use BemLeadAi\Leads\EventRepository;
use BemLeadAi\Leads\LeadRepository;

defined('ABSPATH') || exit;

/**
 * Capture de leads depuis les plugins de formulaires (Gravity Forms,
 * Contact Form 7, WPForms). À chaque soumission, on extrait email / téléphone /
 * prénom / formation et on crée (ou complète) un lead — une soumission de
 * formulaire est un opt-in explicite à forte intention.
 *
 * Les hooks ne se déclenchent que si le plugin concerné est actif : aucune
 * dépendance dure. Désactivable via le réglage « capture_forms ».
 */
final class FormCapture
{
    public function register(): void
    {
        if ((int) Options::get('capture_forms') !== 1) {
            return;
        }
        add_action('gform_after_submission', [$this, 'gravityForms'], 20, 2);
        add_action('wpcf7_mail_sent', [$this, 'contactForm7'], 20, 1);
        add_action('wpforms_process_complete', [$this, 'wpForms'], 20, 4);
        add_action('ninja_forms_after_submission', [$this, 'ninjaForms'], 20, 1);
    }

    /* ---------------- Gravity Forms ---------------- */

    /** @param array $entry @param array $form */
    public function gravityForms($entry, $form): void
    {
        $email = $phone = $name = $formation = null;
        $values = [];
        foreach ((array) ($form['fields'] ?? []) as $field) {
            $type = strtolower((string) $this->prop($field, 'type'));
            $id = (string) $this->prop($field, 'id');
            $label = strtolower((string) $this->prop($field, 'label'));
            $val = trim((string) ($entry[$id] ?? ''));

            if ($type === 'email' && $val !== '') {
                $email = $val;
            } elseif ($type === 'phone' && $val !== '') {
                $phone = $val;
            } elseif ($type === 'name') {
                $first = trim((string) ($entry[$id . '.3'] ?? ''));
                $name = $first !== '' ? $first : ($val !== '' ? $val : $name);
            } elseif ($val !== '') {
                // Libellé complet de l'option choisie (les listes stockent souvent
                // un code court).
                $resolved = $this->gfChoiceLabel($field, $val);
                $values[] = $resolved; // candidat pour la reconnaissance par catalogue
                if ($this->looksLikeFormation($label)) {
                    // Repli heuristique : dernière valeur de la cascade.
                    $formation = $resolved;
                } else {
                    [$email, $phone, $name] = $this->guessByLabel($label, $val, $email, $phone, $name);
                }
            }
        }
        // Reconnaissance par le CATALOGUE (prioritaire, agnostique au secteur) :
        // on rattache la valeur qui correspond à un programme réel, sinon on garde
        // le résultat de l'heuristique par libellé.
        $formation = $this->bestCatalogMatch($values) ?? $formation;
        $this->ingest($email, $phone, $name, $formation, 'gravityforms', (string) $this->prop($form, 'title'));
    }

    /* ---------------- Contact Form 7 ---------------- */

    public function contactForm7($contactForm): void
    {
        if (!class_exists('WPCF7_Submission')) {
            return;
        }
        $submission = \WPCF7_Submission::get_instance();
        if (!$submission) {
            return;
        }
        [$email, $phone, $name, $formation] = $this->scanAssoc((array) $submission->get_posted_data());
        $title = (is_object($contactForm) && method_exists($contactForm, 'title')) ? (string) $contactForm->title() : '';
        $this->ingest($email, $phone, $name, $formation, 'contactform7', $title);
    }

    /* ---------------- WPForms ---------------- */

    /** @param array $fields @param array $entry @param array $formData */
    public function wpForms($fields, $entry, $formData, $entryId): void
    {
        $email = $phone = $name = $formation = null;
        $values = [];
        foreach ((array) $fields as $f) {
            $type = strtolower((string) ($f['type'] ?? ''));
            $val = trim((string) ($f['value'] ?? ''));
            $label = strtolower((string) ($f['name'] ?? ''));
            if ($val === '') {
                continue;
            }
            if ($type === 'email') {
                $email = $val;
            } elseif ($type === 'phone') {
                $phone = $val;
            } elseif ($type === 'name') {
                $name = $val;
            } else {
                $values[] = $val;
                if ($this->looksLikeFormation($label)) {
                    $formation = $val; // repli : dernière valeur de la cascade
                } else {
                    [$email, $phone, $name] = $this->guessByLabel($label, $val, $email, $phone, $name);
                }
            }
        }
        $formation = $this->bestCatalogMatch($values) ?? $formation;
        $title = (string) ($formData['settings']['form_title'] ?? ($formData['title'] ?? ''));
        $this->ingest($email, $phone, $name, $formation, 'wpforms', $title);
    }

    /* ---------------- Ninja Forms ---------------- */

    public function ninjaForms($data): void
    {
        $fields = (array) ($data['fields'] ?? []);
        $assoc = [];
        foreach ($fields as $f) {
            $key = strtolower((string) ($f['key'] ?? $f['label'] ?? ''));
            $assoc[$key] = $f['value'] ?? '';
        }
        [$email, $phone, $name, $formation] = $this->scanAssoc($assoc);
        $title = (string) ($data['settings']['title'] ?? '');
        $this->ingest($email, $phone, $name, $formation, 'ninjaforms', $title);
    }

    /* ---------------- Cœur : création / complétion du lead ---------------- */

    /** Soumission d'un formulaire WordPress : session + UTM viennent des cookies du widget. */
    private function ingest(?string $email, ?string $phone, ?string $name, ?string $formation, string $source, string $formTitle = ''): void
    {
        $chatSid = isset($_COOKIE['bem_lead_session'])
            ? sanitize_text_field(wp_unslash($_COOKIE['bem_lead_session']))
            : null;
        $this->captureLead($email, $phone, $name, $formation, $source, $formTitle, $chatSid, self::utmFromCookie());
    }

    /** Lit l'attribution UTM (first-touch) posée par le widget dans un cookie. */
    public static function utmFromCookie(): array
    {
        if (empty($_COOKIE['bem_lead_utm'])) {
            return [];
        }
        $raw = json_decode(stripslashes((string) wp_unslash($_COOKIE['bem_lead_utm'])), true);
        if (!is_array($raw)) {
            return [];
        }
        return [
            'source'   => sanitize_text_field((string) ($raw['source'] ?? '')),
            'medium'   => sanitize_text_field((string) ($raw['medium'] ?? '')),
            'campaign' => sanitize_text_field((string) ($raw['campaign'] ?? '')),
        ];
    }

    /**
     * Cœur de la capture, réutilisable hors WordPress (endpoint REST pour les sites
     * non-WordPress). La session du chat est fournie explicitement (elle vient du
     * cookie côté WP, ou du session_id du widget côté site externe).
     *
     * @return int|null Identifiant du lead créé/mis à jour, ou null si rien d'exploitable.
     */
    public function captureLead(?string $email, ?string $phone, ?string $name, ?string $formation, string $source, string $formTitle = '', ?string $sessionId = null, array $utm = []): ?int
    {
        $email = $email && is_email($email) ? sanitize_email($email) : null;
        // Normalise le numéro à l'indicatif par défaut de l'école (ex. +224) :
        // un formulaire ne demande souvent que le numéro local.
        $phone = $phone ? \BemLeadAi\Support\PhoneNumber::normalize($phone, (string) Options::get('default_dial_code')) : '';
        $phone = $phone !== '' ? sanitize_text_field($phone) : null;
        if (!$email && !$phone) {
            return null; // sans coordonnée, pas de lead exploitable
        }

        $formTitle = trim($formTitle);

        $leads = new LeadRepository();
        $lead = $email ? $leads->findByEmail($email) : null;
        // Même personne, même navigateur : si un chat est déjà en cours, on rattache
        // le formulaire à ce lead anonyme (via le session_id du widget) au lieu de
        // créer un doublon (chat démarré → puis formulaire soumis).
        if (!$lead && $sessionId !== null && $sessionId !== '') {
            $lead = $leads->findBySessionId($sessionId);
        }
        if (!$lead) {
            $sid = 'form_' . substr(md5(($email ?: $phone) . '|' . $source . '|' . wp_salt()), 0, 24);
            $lead = $leads->findOrCreate($sid, $source);
        }

        $lead = (new ChannelAdapter())->attachIdentity($lead, $email, $phone);

        // Une soumission de formulaire vaut opt-in explicite.
        $updates = ['consent' => 1];
        if ($name) {
            $updates['prenom'] = sanitize_text_field($name);
        }
        if ($formation) {
            $updates['formation_interet'] = sanitize_text_field($formation);
        }
        // Nom du formulaire d'origine (ex. « Candidature Master ») pour tracer d'où
        // vient le lead — visible dans le CRM.
        if ($formTitle !== '') {
            $updates['source_form'] = sanitize_text_field(mb_substr($formTitle, 0, 190));
        }
        $leads->update((int) $lead->id, $updates);

        // Attribution de campagne (first-touch) : rattache le lead à la campagne
        // qui l'a fait arriver (Facebook Ads, recherche, e-mail…).
        if ($utm) {
            $leads->applyUtm((int) $lead->id, $utm);
        }

        (new EventRepository())->record((int) $lead->id, 'form_submitted', ['source' => $source, 'form' => $formTitle], 'form');

        return (int) $lead->id;
    }

    /**
     * Un libellé désigne-t-il le PROGRAMME/la formation visée, et non son TYPE
     * ou sa modalité ? On capte « Formation souhaitée », « Programme », « Filière »,
     * mais on écarte « Type de formation », « Régime », « Modalité », « Rythme »
     * (ex. « Formation continue (cours du soir) ») qui polluaient la donnée.
     */
    private function looksLikeFormation(string $label): bool
    {
        return (bool) (
            preg_match('/formation|programme|fili[eè]re|cursus|parcours|sp[eé]cialit|dipl[oô]me|bachelor|master|mast[eè]re|\bmba\b|\bmsc\b|licence|doctorat|\bbts\b|\bdut\b|pr[eé]pa/', $label)
            && !preg_match('/type|r[eé]gime|modalit|rythme|niveau|statut|cycle|temps/', $label)
        );
    }

    /**
     * Reconnaissance par le CATALOGUE : parmi les valeurs saisies, retourne le
     * nom canonique du programme si l'une correspond à un titre du catalogue
     * (base de connaissance) ou à un alias configuré. Value-based et agnostique
     * au secteur : quand le catalogue change, la détection suit sans toucher au
     * code. Renvoie null si aucune correspondance fiable (→ repli heuristique).
     *
     * @param array<int,string> $values
     */
    private function bestCatalogMatch(array $values): ?string
    {
        $aliases = $this->catalogAliases();
        $catalog = $this->catalogEntries();
        if (!$aliases && !$catalog) {
            return null;
        }

        $best = null;
        $bestScore = 0.0;
        foreach ($values as $raw) {
            $v = $this->norm((string) $raw);
            if ($v === '' || mb_strlen($v) < 3) {
                continue;
            }
            // 1) Alias exact (« mage » → « Master Grande École ») — prioritaire.
            if (isset($aliases[$v])) {
                return $aliases[$v];
            }
            foreach ($catalog as $nTitle => $title) {
                if ($nTitle === '') {
                    continue;
                }
                // 2) Correspondance exacte.
                if ($v === $nTitle) {
                    return $title;
                }
                // 3) Sous-chaîne (garde-fou de longueur contre les faux positifs).
                $short = mb_strlen($v) <= mb_strlen($nTitle) ? $v : $nTitle;
                if (mb_strlen($short) >= 5 && (mb_strpos($v, $nTitle) !== false || mb_strpos($nTitle, $v) !== false)) {
                    $score = 0.92;
                } else {
                    // 4) Recouvrement de mots significatifs (Jaccard).
                    $score = $this->tokenScore($v, $nTitle);
                }
                if ($score > $bestScore && $score >= 0.5) {
                    $bestScore = $score;
                    $best = $title;
                }
            }
        }
        return $best;
    }

    /** Titres du catalogue (base de connaissance), normalisés => titre affiché. */
    private function catalogEntries(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = [];
        try {
            $titles = (new KnowledgeBaseBuilder())->indexedTitles();
        } catch (\Throwable $e) {
            return $cache;
        }
        foreach (['formations', 'onboarding'] as $k) {
            foreach ((array) ($titles[$k] ?? []) as $t) {
                $n = $this->norm((string) $t);
                if ($n !== '') {
                    $cache[$n] = (string) $t;
                }
            }
        }
        return $cache;
    }

    /** Alias configurés, normalisés => nom canonique. */
    private function catalogAliases(): array
    {
        $out = [];
        foreach (Options::programAliases() as $alias => $canonical) {
            $n = $this->norm((string) $alias);
            if ($n !== '') {
                $out[$n] = (string) $canonical;
            }
        }
        return $out;
    }

    /** Normalise une chaîne pour la comparaison : minuscules, sans accents. */
    private function norm(string $s): string
    {
        $s = mb_strtolower(trim($s));
        if (function_exists('remove_accents')) {
            $s = remove_accents($s);
        }
        return trim((string) preg_replace('/\s+/', ' ', $s));
    }

    /** Mots significatifs d'une chaîne normalisée (≥ 3 lettres, hors mots vides). */
    private function tokens(string $s): array
    {
        $stop = ['de', 'des', 'du', 'la', 'le', 'les', 'et', 'en', 'un', 'une', 'aux', 'pour', 'sur', 'the', 'of', 'and'];
        $out = [];
        foreach (preg_split('/[^a-z0-9]+/', $s) as $t) {
            if ($t !== '' && mb_strlen($t) >= 3 && !in_array($t, $stop, true)) {
                $out[] = $t;
            }
        }
        return array_values(array_unique($out));
    }

    /**
     * Score de recouvrement (Jaccard) entre deux chaînes normalisées, exigeant
     * au moins 2 mots significatifs communs pour éviter les faux positifs
     * (« master » seul ne doit pas matcher « Master Finance »).
     */
    private function tokenScore(string $a, string $b): float
    {
        $ta = $this->tokens($a);
        $tb = $this->tokens($b);
        if (!$ta || !$tb) {
            return 0.0;
        }
        $inter = array_intersect($ta, $tb);
        if (count($inter) < 2) {
            return 0.0;
        }
        $union = array_unique(array_merge($ta, $tb));
        return count($inter) / max(1, count($union));
    }

    /**
     * Libellé complet de l'option choisie dans un champ liste/radio Gravity Forms.
     * GF stocke la VALEUR de l'option dans l'entrée (souvent un code court) ; on
     * remonte le TEXTE affiché correspondant depuis les choix du champ.
     */
    private function gfChoiceLabel($field, string $val): string
    {
        $choices = $this->prop($field, 'choices');
        if (is_array($choices)) {
            foreach ($choices as $c) {
                $cVal = (string) ($c['value'] ?? '');
                $cTxt = (string) ($c['text'] ?? '');
                // Si l'option n'a pas de valeur distincte, GF utilise le texte comme valeur.
                if (($cVal !== '' && $cVal === $val) || ($cVal === '' && $cTxt === $val)) {
                    return $cTxt !== '' ? $cTxt : $val;
                }
            }
        }
        return $val;
    }

    /* ---------------- Utilitaires ---------------- */

    /** Accès à une propriété d'un champ, qu'il soit objet (GF) ou tableau. */
    private function prop($field, string $key)
    {
        if (is_object($field)) {
            return $field->$key ?? '';
        }
        return is_array($field) ? ($field[$key] ?? '') : '';
    }

    /**
     * Devine email/téléphone/nom d'après le libellé d'un champ générique.
     * @return array{0:?string,1:?string,2:?string}
     */
    private function guessByLabel(string $label, string $val, ?string $email, ?string $phone, ?string $name): array
    {
        if ($email === null && is_email($val)) {
            $email = $val;
        } elseif ($phone === null && preg_match('/t[eé]l|phone|whatsapp|mobile|num[eé]ro/', $label)) {
            $phone = $val;
        } elseif ($name === null && preg_match('/nom|name|pr[eé]nom/', $label)) {
            $name = $val;
        }
        return [$email, $phone, $name];
    }

    /**
     * Parcourt un tableau clé => valeur et devine les champs par le nom de clé.
     * @return array{0:?string,1:?string,2:?string,3:?string}
     */
    private function scanAssoc(array $data): array
    {
        $email = $phone = $name = $formation = null;
        $values = [];
        foreach ($data as $key => $val) {
            if (is_array($val)) {
                $val = implode(', ', array_filter($val, 'is_scalar'));
            }
            $val = trim((string) $val);
            if ($val === '') {
                continue;
            }
            $k = strtolower((string) $key);
            if ($email === null && is_email($val)) {
                $email = $val;
            } elseif ($phone === null && preg_match('/t[eé]l|phone|whatsapp|mobile|num[eé]ro/', $k)) {
                $phone = $val;
            } elseif ($this->looksLikeFormation($k)) {
                $formation = $val; // repli : dernière valeur (la plus précise)
                $values[] = $val;
            } elseif ($name === null && preg_match('/nom|name|pr[eé]nom/', $k)) {
                $name = $val;
            } else {
                $values[] = $val;
            }
        }
        $formation = $this->bestCatalogMatch($values) ?? $formation;
        return [$email, $phone, $name, $formation];
    }
}

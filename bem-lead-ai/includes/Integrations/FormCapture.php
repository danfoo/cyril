<?php

namespace BemLeadAi\Integrations;

use BemLeadAi\Chat\ChannelAdapter;
use BemLeadAi\Core\Options;
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
            } elseif ($val !== '' && $formation === null && preg_match('/formation|programme|fili|cursus/', $label)) {
                $formation = $val;
            } elseif ($val !== '') {
                [$email, $phone, $name] = $this->guessByLabel($label, $val, $email, $phone, $name);
            }
        }
        $this->ingest($email, $phone, $name, $formation, 'gravityforms');
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
        $this->ingest($email, $phone, $name, $formation, 'contactform7');
    }

    /* ---------------- WPForms ---------------- */

    /** @param array $fields @param array $entry @param array $formData */
    public function wpForms($fields, $entry, $formData, $entryId): void
    {
        $email = $phone = $name = $formation = null;
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
            } elseif ($formation === null && preg_match('/formation|programme|fili|cursus/', $label)) {
                $formation = $val;
            } else {
                [$email, $phone, $name] = $this->guessByLabel($label, $val, $email, $phone, $name);
            }
        }
        $this->ingest($email, $phone, $name, $formation, 'wpforms');
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
        $this->ingest($email, $phone, $name, $formation, 'ninjaforms');
    }

    /* ---------------- Cœur : création / complétion du lead ---------------- */

    private function ingest(?string $email, ?string $phone, ?string $name, ?string $formation, string $source): void
    {
        $email = $email && is_email($email) ? sanitize_email($email) : null;
        $phone = $phone ? sanitize_text_field($phone) : null;
        if (!$email && !$phone) {
            return; // sans coordonnée, pas de lead exploitable
        }

        $leads = new LeadRepository();
        $lead = $email ? $leads->findByEmail($email) : null;
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
        $leads->update((int) $lead->id, $updates);

        (new EventRepository())->record((int) $lead->id, 'form_submitted', ['source' => $source], 'form');
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
            } elseif ($formation === null && preg_match('/formation|programme|fili|cursus/', $k)) {
                $formation = $val;
            } elseif ($name === null && preg_match('/nom|name|pr[eé]nom/', $k)) {
                $name = $val;
            }
        }
        return [$email, $phone, $name, $formation];
    }
}

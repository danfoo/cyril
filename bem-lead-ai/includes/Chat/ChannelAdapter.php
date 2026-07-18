<?php

namespace BemLeadAi\Chat;

use BemLeadAi\Leads\EventRepository;
use BemLeadAi\Leads\LeadRepository;

defined('ABSPATH') || exit;

/**
 * Unifie web et WhatsApp en un seul flux : quel que soit le canal,
 * on retrouve ou crée le même profil de lead (session_id unifié).
 */
final class ChannelAdapter
{
    private LeadRepository $leads;

    public function __construct()
    {
        $this->leads = new LeadRepository();
    }

    /** Session web : identifiant généré côté widget, stocké en localStorage. */
    public function resolveWebLead(string $sessionId): object
    {
        return $this->leads->findOrCreate(sanitize_text_field($sessionId), 'web');
    }

    /**
     * Rattachement d'un profil anonyme dès qu'un email/téléphone est capturé
     * (formulaire, chat) — si un lead existe déjà avec cet email, on fusionne
     * les identifiants sur le profil le plus ancien.
     */
    public function attachIdentity(object $lead, ?string $email = null, ?string $phone = null): object
    {
        $updates = [];
        if ($email && is_email($email)) {
            $existing = $this->leads->findByEmail($email);
            if ($existing && (int) $existing->id !== (int) $lead->id) {
                // Profil déjà connu : on continue sur celui-ci.
                $this->leads->update((int) $existing->id, ['session_id' => $lead->session_id]);
                $this->leads->delete((int) $lead->id);
                $lead = $this->leads->findById((int) $existing->id);
            } else {
                $updates['email'] = sanitize_email($email);
                (new EventRepository())->record((int) $lead->id, 'email_captured', [], 'web');
            }
        }
        if ($phone) {
            $updates['phone'] = sanitize_text_field($phone);
            (new EventRepository())->record((int) $lead->id, 'phone_captured', [], 'web');
        }
        if ($updates) {
            $this->leads->update((int) $lead->id, $updates);
        }
        return $this->leads->findById((int) $lead->id);
    }
}

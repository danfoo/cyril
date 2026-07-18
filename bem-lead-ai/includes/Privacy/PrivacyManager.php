<?php

namespace BemLeadAi\Privacy;

use BemLeadAi\Leads\LeadRepository;

defined('ABSPATH') || exit;

/**
 * Conformité loi n°2008-12 (CDP, Sénégal) : consentement avant tracking,
 * droit à l'oubli (suppression du lead et de tout son historique,
 * web + WhatsApp), minimisation des données.
 */
final class PrivacyManager
{
    /** Eraser branché sur l'outil natif WordPress « Effacer les données personnelles ». */
    public static function eraseByEmail(string $email, int $page = 1): array
    {
        $repo = new LeadRepository();
        $removed = false;
        $lead = $repo->findByEmail($email);
        if ($lead) {
            $repo->delete((int) $lead->id);
            $removed = true;
        }
        return [
            'items_removed' => $removed,
            'items_retained' => false,
            'messages' => $removed ? [__('Profil lead BEM et historique complet supprimés.', 'bem-lead-ai')] : [],
            'done' => true,
        ];
    }

    public static function eraseLead(int $leadId): void
    {
        (new LeadRepository())->delete($leadId);
    }
}

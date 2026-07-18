<?php

namespace BemLeadAi\Crm;

defined('ABSPATH') || exit;

interface CrmConnectorInterface
{
    public function isConfigured(): bool;

    /** Crée ou met à jour le lead côté CRM et mémorise l'id distant. */
    public function upsertLead(object $lead): void;
}

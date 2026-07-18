<?php

namespace BemLeadAi\Channels;

use BemLeadAi\Core\Options;

defined('ABSPATH') || exit;

/**
 * Passerelle WhatsApp « click-to-chat » : au lieu de l'API WhatsApp Business
 * (validation Meta, templates, fenêtre 24 h…), on propose au prospect de
 * continuer la conversation sur un de NOS numéros WhatsApp via un lien wa.me
 * pré-rempli. Zéro dépendance externe, activable immédiatement.
 *
 * Sélection du numéro : si un numéro est associé à la formation d'intérêt du
 * lead, on le privilégie ; sinon rotation équitable (round-robin) entre les
 * numéros configurés pour répartir la charge entre conseillers.
 */
final class WhatsAppHandoff
{
    public function isEnabled(): bool
    {
        return (int) Options::get('whatsapp_enabled') === 1 && !empty(Options::whatsappNumbers());
    }

    /** @return array{label:string, number:string}|null */
    public function pickNumber(?string $formationInterest): ?array
    {
        $numbers = Options::whatsappNumbers();
        if (!$numbers) {
            return null;
        }

        // 1) Numéro dédié à la formation d'intérêt.
        if ($formationInterest) {
            foreach ($numbers as $entry) {
                if ($entry['formation'] !== ''
                    && (str_contains(strtolower($formationInterest), strtolower($entry['formation']))
                        || str_contains(strtolower($entry['formation']), strtolower($formationInterest)))) {
                    return ['label' => $entry['label'], 'number' => $entry['number']];
                }
            }
        }

        // 2) Round-robin sur les numéros génériques (sans formation associée),
        //    sinon sur l'ensemble.
        $generic = array_values(array_filter($numbers, fn($n) => $n['formation'] === ''));
        $pool = $generic ?: $numbers;
        $cursor = (int) get_option('bem_lead_ai_wa_cursor', 0);
        $entry = $pool[$cursor % count($pool)];
        update_option('bem_lead_ai_wa_cursor', $cursor + 1, false);

        return ['label' => $entry['label'], 'number' => $entry['number']];
    }

    /** Construit le lien wa.me pré-rempli pour un lead. */
    public function buildLink(object $lead): ?array
    {
        $entry = $this->pickNumber($lead->formation_interet ?? null);
        if (!$entry) {
            return null;
        }
        $template = (string) Options::get('whatsapp_prefill');
        $text = strtr($template, [
            '{prenom}' => $lead->prenom ?: '',
            '{formation}' => $lead->formation_interet ?: 'vos formations',
        ]);
        $url = 'https://wa.me/' . $entry['number'] . '?text=' . rawurlencode(trim($text));

        return [
            'url' => $url,
            'label' => $entry['label'],
            'number' => '+' . $entry['number'],
        ];
    }
}

<?php

namespace BemLeadAi\Financing;

defined('ABSPATH') || exit;

/**
 * Simulateur de financement : plans de paiement et bourses proposés
 * directement dans le chat quand une sensibilité au prix est détectée.
 * Les données (frais, échéanciers, bourses) sont structurées côté admin —
 * jamais inventées par le LLM.
 */
final class FinancingSimulator
{
    /** @return object[] */
    public function activeOptions(): array
    {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}bem_financing_options WHERE actif = 1") ?: [];
    }

    public function forFormation(?string $formationLabel): ?object
    {
        $options = $this->activeOptions();
        if (!$options) {
            return null;
        }
        if ($formationLabel) {
            foreach ($options as $option) {
                if (str_contains(strtolower($formationLabel), strtolower($option->formation_label))
                    || str_contains(strtolower($option->formation_label), strtolower($formationLabel))) {
                    return $option;
                }
            }
        }
        return null;
    }

    /**
     * Message proactif de proposition de financement — ton orienté solution
     * (le prix est un frein : on répond par des options, pas par un rappel
     * du montant).
     */
    public function buildOfferMessage(object $lead): ?string
    {
        $option = $this->forFormation($lead->formation_interet ?: null);

        $intro = ($lead->prenom ? $lead->prenom . ', b' : 'B') . "onne nouvelle : le coût ne doit pas être un obstacle à votre projet. 💡\n\n";

        if (!$option) {
            if (!$this->activeOptions()) {
                return null;
            }
            return $intro . "BEM Dakar propose des facilités de paiement en plusieurs échéances et des bourses selon votre profil. Dites-moi la formation qui vous intéresse et je vous détaille les options concrètes.";
        }

        $lines = [$intro . sprintf("Pour %s, voici les possibilités :", $option->formation_label)];

        $plans = json_decode((string) $option->options_paiement, true) ?: [];
        foreach ($plans as $plan) {
            if (!empty($plan['label'])) {
                $lines[] = '• ' . $plan['label'] . (!empty($plan['detail']) ? ' — ' . $plan['detail'] : '');
            }
        }
        $bourses = json_decode((string) $option->bourses, true) ?: [];
        foreach ($bourses as $bourse) {
            if (!empty($bourse['label'])) {
                $lines[] = '🎓 ' . $bourse['label'] . (!empty($bourse['detail']) ? ' — ' . $bourse['detail'] : '');
            }
        }

        $lines[] = "\nVoulez-vous que je fasse une simulation adaptée à votre situation, ou que je vous mette en contact avec l'équipe admissions pour un plan personnalisé ?";
        return implode("\n", $lines);
    }

    /** Simulation par mensualités, utilisée par l'endpoint REST du widget. */
    public function simulate(int $optionId, int $months): ?array
    {
        global $wpdb;
        $option = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bem_financing_options WHERE id = %d AND actif = 1",
            $optionId
        ));
        if (!$option || $months < 1 || $months > 36) {
            return null;
        }
        $monthly = (int) ceil((int) $option->frais_total / $months);
        return [
            'formation' => $option->formation_label,
            'frais_total' => (int) $option->frais_total,
            'devise' => $option->devise,
            'mensualites' => $months,
            'montant_mensuel' => $monthly,
        ];
    }
}

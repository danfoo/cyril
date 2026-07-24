<?php

namespace BemLeadAi\Support;

defined('ABSPATH') || exit;

/**
 * Détection et normalisation de numéros de téléphone.
 *
 * L'extraction en conversation reposait uniquement sur le LLM (SignalClassifier),
 * qui laisse parfois filer un numéro pourtant présent (nombre « nu » de 9 chiffres
 * noyé dans une phrase). Ce filet déterministe capte le numéro par motif, en repli
 * du LLM, puis le normalise au format international à l'aide de l'« indicatif par
 * défaut » de l'école — bien plus fiable qu'une géolocalisation IP (VPN, routage
 * opérateur mobile) puisque chaque établissement connaît son marché.
 */
final class PhoneNumber
{
    /**
     * Repère un numéro de téléphone dans un texte libre. Renvoie la sous-chaîne
     * brute (indicatif « + » ou « 00 » conservé) ou null. À passer ensuite à
     * normalize() pour obtenir un format propre.
     */
    public static function extract(string $text): ?string
    {
        if (trim($text) === '') {
            return null;
        }
        // Uniformise les espaces insécables (souvent présents dans les numéros
        // copiés-collés) en espaces simples pour que le motif les traverse.
        $text = preg_replace('/[\x{00A0}\x{202F}\x{2007}]/u', ' ', $text) ?? $text;

        // Suite ressemblant à un numéro : « + » ou « 00 » optionnel, puis une
        // série de 8 à ~17 caractères mêlant chiffres et séparateurs usuels.
        if (!preg_match_all('/(?<![\w+])((?:\+|00)?\d(?:[\d\s.()\-]{6,16})\d)/u', $text, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        // Devises usuelles (Guinée, zone CFA, euro, dollar) : un montant adjacent
        // n'est pas un téléphone (« 15 000 000 GNF » ≠ numéro).
        $currency = '/(gnf|fcfa|cfa|xof|xaf|eur|usd|franc|euro|dollar|€|\$)/iu';

        foreach ($matches[1] as $match) {
            $candidate = trim($match[0]);
            $offset = (int) $match[1];
            $digits = preg_replace('/\D+/', '', $candidate) ?? '';
            $len = strlen($digits);
            // Un numéro national/international fait entre 8 et 15 chiffres.
            if ($len < 8 || $len > 15) {
                continue;
            }
            // Rejette une suite d'un seul chiffre répété (00000000, 11111111…).
            if (preg_match('/^(\d)\1+$/', $digits)) {
                continue;
            }
            // Rejette un montant : devise juste avant ou juste après la suite.
            $before = substr($text, max(0, $offset - 8), min(8, $offset));
            $after = substr($text, $offset + strlen($match[0]), 8);
            if (preg_match($currency, $before) || preg_match($currency, $after)) {
                continue;
            }
            return $candidate;
        }

        return null;
    }

    /**
     * Nettoie un numéro (issu du LLM ou de extract()) et applique l'indicatif
     * par défaut aux numéros locaux dépourvus d'indicatif international.
     *
     * @param string $raw             Numéro brut.
     * @param string $defaultDialCode Indicatif par défaut, ex: « +224 » ou « 224 ».
     */
    public static function normalize(string $raw, string $defaultDialCode = ''): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        $hasPlus = str_starts_with($raw, '+');
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') {
            return '';
        }

        // Déjà au format international explicite (« + » ou « 00 »).
        if ($hasPlus) {
            return '+' . $digits;
        }
        if (str_starts_with($digits, '00')) {
            return '+' . ltrim(substr($digits, 2), '0');
        }

        $dial = preg_replace('/\D+/', '', $defaultDialCode) ?? '';
        if ($dial === '') {
            // Aucun indicatif configuré : on conserve le numéro local tel quel
            // plutôt que d'inventer un préfixe.
            return $digits;
        }

        // Numéro déjà préfixé de l'indicatif mais sans le « + » (ex: 224613…).
        if (str_starts_with($digits, $dial) && strlen($digits) >= strlen($dial) + 6) {
            return '+' . $digits;
        }

        // Numéro local : on retire un éventuel préfixe national « 0 » (France,
        // etc. — sans effet sur la Guinée qui n'en a pas) avant l'indicatif.
        $local = ltrim($digits, '0');
        if ($local === '') {
            $local = $digits;
        }

        return '+' . $dial . $local;
    }

    /**
     * Extrait un numéro d'un texte ET le normalise en une seule étape.
     * Renvoie null si aucun numéro plausible n'est trouvé.
     */
    public static function extractAndNormalize(string $text, string $defaultDialCode = ''): ?string
    {
        $found = self::extract($text);
        if ($found === null) {
            return null;
        }
        $normalized = self::normalize($found, $defaultDialCode);
        return $normalized !== '' ? $normalized : null;
    }
}

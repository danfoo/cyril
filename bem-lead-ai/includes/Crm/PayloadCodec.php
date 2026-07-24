<?php

namespace BemLeadAi\Crm;

defined('ABSPATH') || exit;

/**
 * Encodage du contenu des messages transportés vers Perfex en GET.
 *
 * Une réponse IA longue, une fois url-encodée (accents, markdown → %XX),
 * dépassait la longueur d'URL acceptée par certains hébergeurs et le message
 * était perdu. On compresse donc (gzip) et on encode en base64url — uniquement
 * [A-Za-z0-9-_], jamais bloqué par un pare-feu applicatif — derrière le préfixe
 * « SIAZ1: ». Le module Perfex (school_ia_bridge) applique l'opération inverse.
 *
 * Source unique de vérité du format : encode() et decode() DOIVENT rester le
 * miroir l'un de l'autre, et de unpack_content() côté Perfex.
 */
final class PayloadCodec
{
    private const PREFIX = 'SIAZ1:';
    /** Garde-fou : au-delà, on tronque (une réponse IA reste raisonnable). */
    private const MAX_CHARS = 6000;
    /** En deçà, la compression ne vaut pas son overhead : on transmet en clair. */
    private const COMPRESS_THRESHOLD = 400;

    public static function encode(string $content): string
    {
        $content = mb_substr($content, 0, self::MAX_CHARS);
        if (function_exists('gzencode') && strlen($content) > self::COMPRESS_THRESHOLD) {
            $gz = gzencode($content, 6);
            if ($gz !== false) {
                return self::PREFIX . rtrim(strtr(base64_encode($gz), '+/', '-_'), '=');
            }
        }
        return $content;
    }

    public static function decode(string $content): string
    {
        if (strncmp($content, self::PREFIX, 6) !== 0) {
            return $content;
        }
        $gz = self::b64urlDecode(substr($content, 6));
        $plain = function_exists('gzdecode') ? @gzdecode($gz) : false;
        return $plain !== false ? $plain : $content;
    }

    private static function b64urlDecode(string $s): string
    {
        $s = strtr($s, '-_', '+/');
        $pad = strlen($s) % 4;
        if ($pad) {
            $s .= str_repeat('=', 4 - $pad);
        }
        return (string) base64_decode($s);
    }
}

<?php

namespace BemLeadAi\Rag;

defined('ABSPATH') || exit;

/**
 * Découpe un contenu en chunks d'environ 1200 caractères avec chevauchement,
 * en respectant les frontières de paragraphes.
 */
final class Chunker
{
    private const MAX_CHARS = 1200;
    private const OVERLAP = 150;

    /** @return string[] */
    public function chunk(string $text): array
    {
        $text = trim(preg_replace('/\n{3,}/', "\n\n", $text));
        if ($text === '') {
            return [];
        }
        if (mb_strlen($text) <= self::MAX_CHARS) {
            return [$text];
        }

        $paragraphs = preg_split('/\n\n+/', $text) ?: [$text];
        $chunks = [];
        $current = '';
        foreach ($paragraphs as $paragraph) {
            if (mb_strlen($paragraph) > self::MAX_CHARS) {
                // Paragraphe trop long : découpe brute avec chevauchement.
                if ($current !== '') {
                    $chunks[] = $current;
                    $current = '';
                }
                $offset = 0;
                $len = mb_strlen($paragraph);
                while ($offset < $len) {
                    $chunks[] = mb_substr($paragraph, $offset, self::MAX_CHARS);
                    $offset += self::MAX_CHARS - self::OVERLAP;
                }
                continue;
            }
            if (mb_strlen($current . "\n\n" . $paragraph) > self::MAX_CHARS && $current !== '') {
                $chunks[] = $current;
                $current = $paragraph;
            } else {
                $current = $current === '' ? $paragraph : $current . "\n\n" . $paragraph;
            }
        }
        if ($current !== '') {
            $chunks[] = $current;
        }
        return $chunks;
    }
}

<?php

namespace BemLeadAi\Knowledge;

use BemLeadAi\Core\Options;

defined('ABSPATH') || exit;

/**
 * Assemble le contenu réel des formations (et de l'onboarding) en un bloc
 * texte unique, servi tel quel au conseiller IA et mis en cache côté LLM
 * (prompt caching : préfixe figé → lecture à ~10 % du coût).
 *
 * Remplace l'ancien pipeline RAG (embeddings + vector store) : pas d'infra
 * externe, toujours à jour, le modèle voit tout le catalogue d'un coup.
 * Le bloc est mémorisé dans une option et reconstruit à chaque modification
 * de contenu — indispensable pour que le préfixe reste byte-identique et que
 * le cache LLM soit réellement réutilisé.
 */
final class KnowledgeBaseBuilder
{
    private const OPTION = 'bem_lead_ai_kb';

    /** Bloc de connaissance pour une base donnée ('formations' | 'onboarding'). */
    public function block(string $kb): string
    {
        $store = $this->read();
        if (empty($store[$kb])) {
            $this->rebuild();
            $store = $this->read();
        }
        return (string) ($store[$kb] ?? '');
    }

    public function builtAt(): ?string
    {
        return $this->read()['built_at'] ?? null;
    }

    /** Lecture décodée (stockage JSON ASCII, formats hérités tolérés). */
    private function read(): array
    {
        $stored = get_option(self::OPTION, null);
        if (is_array($stored)) {
            return $stored;
        }
        if (is_string($stored) && $stored !== '') {
            $json = json_decode($stored, true);
            if (is_array($json)) {
                return $json;
            }
        }
        return [];
    }

    /** Reconstruit les deux bases et les mémorise. Retourne des stats. */
    public function rebuild(): array
    {
        $postTypes = array_filter(array_map('trim', explode(',', (string) Options::get('indexed_post_types'))));
        if (!$postTypes) {
            $postTypes = ['page'];
        }
        $maxChars = max(500, (int) Options::get('kb_max_chars_per_post'));

        $posts = get_posts([
            'post_type' => $postTypes,
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        $blocks = ['formations' => [], 'onboarding' => []];
        foreach ($posts as $post) {
            $kb = $this->knowledgeBaseFor($post);
            $blocks[$kb][] = $this->renderPost($post, $maxChars);
        }

        $store = [
            'formations' => $this->wrap('FORMATIONS BEM DAKAR', $blocks['formations']),
            'onboarding' => $this->wrap('ONBOARDING / VIE ADMINISTRATIVE — ÉTUDIANTS INSCRITS', $blocks['onboarding']),
            'built_at' => current_time('mysql'),
        ];
        // Stockage JSON ASCII : robuste même si un contenu contient un emoji
        // (table wp_options en utf8 non-utf8mb4).
        update_option(self::OPTION, wp_json_encode($store), false);
        if (function_exists('wp_cache_delete')) {
            wp_cache_delete(self::OPTION, 'options');
            wp_cache_delete('alloptions', 'options');
        }

        return [
            'formations' => count($blocks['formations']),
            'onboarding' => count($blocks['onboarding']),
            'chars' => strlen($store['formations']) + strlen($store['onboarding']),
        ];
    }

    private function wrap(string $title, array $parts): string
    {
        if (!$parts) {
            return '';
        }
        return "=== {$title} ===\n\n" . implode("\n\n----------\n\n", $parts);
    }

    private function renderPost(\WP_Post $post, int $maxChars): string
    {
        $content = wp_strip_all_tags(strip_shortcodes($post->post_content));
        $content = trim(preg_replace('/\s+\n/', "\n", preg_replace('/[ \t]{2,}/', ' ', $content)));
        if (mb_strlen($content) > $maxChars) {
            $content = mb_substr($content, 0, $maxChars) . '…';
        }
        return sprintf(
            "# %s\nLien officiel (à partager avec le prospect) : %s\n\n%s",
            $post->post_title,
            get_permalink($post),
            $content
        );
    }

    private function knowledgeBaseFor(\WP_Post $post): string
    {
        $slug = (string) Options::get('onboarding_category');
        if ($slug !== '' && (has_category($slug, $post) || has_tag($slug, $post))) {
            return 'onboarding';
        }
        return 'formations';
    }
}

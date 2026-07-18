<?php

namespace BemLeadAi\Rag;

use BemLeadAi\Ai\EmbeddingsClient;
use BemLeadAi\Core\Options;

defined('ABSPATH') || exit;

/**
 * Indexe le contenu des formations (et de l'onboarding) dans le vector store.
 * Tourne en WP-Cron quotidien + réindexation manuelle via POST /reindex.
 * Le hash de contenu évite de ré-embedder ce qui n'a pas changé.
 */
final class ContentIndexer
{
    public function reindexAll(): array
    {
        $postTypes = array_filter(array_map('trim', explode(',', (string) Options::get('indexed_post_types'))));
        if (!$postTypes) {
            $postTypes = ['page'];
        }

        $posts = get_posts([
            'post_type' => $postTypes,
            'post_status' => 'publish',
            'numberposts' => -1,
        ]);

        $stats = ['indexed' => 0, 'skipped' => 0, 'errors' => 0];
        foreach ($posts as $post) {
            $result = $this->indexPost($post->ID);
            $stats[$result]++;
        }
        $this->purgeDeleted($posts);
        update_option('bem_lead_ai_last_reindex', current_time('mysql'));
        return $stats;
    }

    /** @return 'indexed'|'skipped'|'errors' */
    public function indexPost(int $postId): string
    {
        global $wpdb;
        $post = get_post($postId);
        if (!$post || $post->post_status !== 'publish') {
            return 'skipped';
        }

        $content = wp_strip_all_tags($post->post_title . "\n\n" . strip_shortcodes($post->post_content));
        $hash = sha1($content);
        $kb = $this->knowledgeBaseFor($post);

        $table = $wpdb->prefix . 'bem_content_index';
        $existingHash = $wpdb->get_var($wpdb->prepare(
            "SELECT content_hash FROM {$table} WHERE post_id = %d LIMIT 1",
            $postId
        ));
        if ($existingHash === $hash) {
            return 'skipped';
        }

        $chunks = (new Chunker())->chunk($content);
        if (!$chunks) {
            return 'skipped';
        }

        $vectors = (new EmbeddingsClient())->embed($chunks, 'document');
        if (is_wp_error($vectors)) {
            error_log('[bem-lead-ai] Indexation échouée pour post ' . $postId . ': ' . $vectors->get_error_message());
            return 'errors';
        }

        $qdrant = new QdrantClient();
        $qdrant->ensureCollection(count($vectors[0]));

        // Supprime les anciens points de ce post avant réinsertion.
        $oldIds = $wpdb->get_col($wpdb->prepare("SELECT vector_id FROM {$table} WHERE post_id = %d", $postId));
        $qdrant->deletePoints($oldIds);
        $wpdb->delete($table, ['post_id' => $postId]);

        $points = [];
        $now = current_time('mysql');
        foreach ($chunks as $i => $chunk) {
            $vectorId = wp_generate_uuid4();
            $points[] = [
                'id' => $vectorId,
                'vector' => $vectors[$i],
                'payload' => [
                    'post_id' => $postId,
                    'title' => $post->post_title,
                    'url' => get_permalink($postId),
                    'kb' => $kb,
                    'text' => $chunk,
                ],
            ];
            $wpdb->insert($table, [
                'post_id' => $postId,
                'chunk_index' => $i,
                'vector_id' => $vectorId,
                'kb' => $kb,
                'content_hash' => $hash,
                'updated_at' => $now,
            ]);
        }

        $result = $qdrant->upsert($points);
        return is_wp_error($result) ? 'errors' : 'indexed';
    }

    /**
     * Le contenu taggé "onboarding" (catégorie ou tag) alimente la base servie
     * aux inscrits ; tout le reste alimente la base "formations".
     */
    private function knowledgeBaseFor(\WP_Post $post): string
    {
        $slug = (string) Options::get('onboarding_category');
        if ($slug !== '' && (has_category($slug, $post) || has_tag($slug, $post))) {
            return 'onboarding';
        }
        return 'formations';
    }

    private function purgeDeleted(array $livePosts): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bem_content_index';
        $liveIds = array_map(fn($p) => (int) $p->ID, $livePosts);
        $indexed = $wpdb->get_col("SELECT DISTINCT post_id FROM {$table}");
        $stale = array_diff(array_map('intval', $indexed), $liveIds);
        if (!$stale) {
            return;
        }
        $qdrant = new QdrantClient();
        foreach ($stale as $postId) {
            $ids = $wpdb->get_col($wpdb->prepare("SELECT vector_id FROM {$table} WHERE post_id = %d", $postId));
            $qdrant->deletePoints($ids);
            $wpdb->delete($table, ['post_id' => $postId]);
        }
    }
}

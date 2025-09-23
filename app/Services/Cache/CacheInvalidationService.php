<?php

namespace App\Services\Cache;

use App\Models\Workstream;
use App\Models\User;
use App\Models\Feedback;
use App\Models\Output;
use App\Models\Input;
use App\Services\Cache\DistributedCacheManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

/**
 * CacheInvalidationService - Handles hierarchy-aware cache invalidation strategies
 *
 * This service provides intelligent cache invalidation that understands entity
 * relationships and cascades invalidation appropriately across the hierarchy.
 */
class CacheInvalidationService
{
    private DistributedCacheManager $cacheManager;
    private array $config;

    public function __construct(DistributedCacheManager $cacheManager)
    {
        $this->cacheManager = $cacheManager;
        $this->config = config('cache_distributed', []);
    }

    /**
     * Invalidate workstream-related caches when hierarchy changes
     *
     * @param Workstream $workstream
     * @param array $changes What changed (moved, updated, etc.)
     * @return int Number of cache keys invalidated
     */
    public function invalidateWorkstreamHierarchy(Workstream $workstream, array $changes = []): int
    {
        $invalidatedCount = 0;

        try {
            // Get affected workstreams (self, ancestors, descendants)
            $affectedWorkstreams = $this->getAffectedWorkstreams($workstream, $changes);

            // Build invalidation tags
            $tags = $this->buildWorkstreamInvalidationTags($affectedWorkstreams, $changes);

            // Invalidate cache tags
            $invalidatedCount = $this->cacheManager->invalidateByTags($tags);

            // Log the invalidation for monitoring
            Log::info('Workstream hierarchy cache invalidation completed', [
                'workstream_id' => $workstream->id,
                'changes' => $changes,
                'affected_workstreams' => count($affectedWorkstreams),
                'tags_invalidated' => count($tags),
                'keys_invalidated' => $invalidatedCount
            ]);

            return $invalidatedCount;

        } catch (\Exception $e) {
            Log::error('Workstream hierarchy cache invalidation failed', [
                'workstream_id' => $workstream->id,
                'changes' => $changes,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Invalidate user permission caches
     *
     * @param int $userId
     * @param array $workstreamIds Specific workstreams affected
     * @return int Number of cache keys invalidated
     */
    public function invalidateUserPermissions(int $userId, array $workstreamIds = []): int
    {
        $tags = [
            "user_permissions:{$userId}",
            "permission_checks:{$userId}"
        ];

        // Add specific workstream permission tags if provided
        foreach ($workstreamIds as $workstreamId) {
            $tags[] = "workstream_permissions:{$workstreamId}";
            $tags[] = "user_workstream_permissions:{$userId}:{$workstreamId}";
        }

        return $this->cacheManager->invalidateByTags($tags);
    }

    /**
     * Invalidate feedback and RAG-related caches
     *
     * @param Feedback $feedback
     * @return int Number of cache keys invalidated
     */
    public function invalidateFeedbackCaches(Feedback $feedback): int
    {
        $output = $feedback->output;
        $input = $output->input ?? null;

        $tags = [
            "feedback:{$feedback->id}",
            "output_feedback:{$output->id}",
            "user_feedback_patterns:{$feedback->user_id}",
            "user_preferences:{$feedback->user_id}",
            "feedback_analytics",
            "feedback_quality_scores"
        ];

        // Invalidate RAG similarity caches if input exists
        if ($input) {
            $tags[] = "similar_feedback";
            $tags[] = "rag_similarity:{$input->id}";
            $tags[] = "input_similarity:{$input->id}";
        }

        // Invalidate aggregated feedback patterns
        $tags[] = "feedback_aggregations";
        $tags[] = "feedback_trends";

        return $this->cacheManager->invalidateByTags($tags);
    }

    /**
     * Invalidate caches when embeddings are updated
     *
     * @param string $contentType
     * @param int $contentId
     * @return int Number of cache keys invalidated
     */
    public function invalidateEmbeddingCaches(string $contentType, int $contentId): int
    {
        $tags = [
            "embedding:{$contentType}:{$contentId}",
            "similar_feedback", // All similarity searches might be affected
            "rag_similarity",
            "vector_search"
        ];

        // If it's an input embedding, invalidate related similarity caches
        if ($contentType === 'App\Models\Input') {
            $tags[] = "input_similarity:{$contentId}";
        }

        return $this->cacheManager->invalidateByTags($tags);
    }

    /**
     * Invalidate rate limiting caches
     *
     * @param int $userId
     * @param string $type
     * @return bool
     */
    public function invalidateRateLimit(int $userId, string $type = 'feedback'): bool
    {
        $key = $this->buildCacheKey('rate_limit', [
            'type' => $type,
            'user_id' => $userId
        ]);

        return $this->cacheManager->forget($key);
    }

    /**
     * Bulk invalidation for major changes (use sparingly)
     *
     * @param array $entityTypes Types of entities to invalidate
     * @return int Number of cache keys invalidated
     */
    public function bulkInvalidate(array $entityTypes): int
    {
        $tags = [];

        foreach ($entityTypes as $type) {
            $tags = array_merge($tags, $this->getEntityTypeInvalidationTags($type));
        }

        return $this->cacheManager->invalidateByTags(array_unique($tags));
    }

    /**
     * Selective cache warming after invalidation
     *
     * @param array $warmingSpecs
     * @return array Warming results
     */
    public function warmAfterInvalidation(array $warmingSpecs): array
    {
        // Add delay to allow any ongoing operations to complete
        usleep(100000); // 100ms

        return $this->cacheManager->warm($warmingSpecs);
    }

    /**
     * Invalidate user session-specific caches
     *
     * @param int $userId
     * @return int Number of cache keys invalidated
     */
    public function invalidateUserSession(int $userId): int
    {
        $tags = [
            "user_session:{$userId}",
            "user_preferences:{$userId}",
            "user_feedback_patterns:{$userId}",
            "user_permissions:{$userId}"
        ];

        return $this->cacheManager->invalidateByTags($tags);
    }

    /**
     * Time-based cache invalidation for stale data cleanup
     *
     * @param int $olderThanSeconds
     * @return int Number of cache keys invalidated
     */
    public function invalidateStaleData(int $olderThanSeconds = 86400): int
    {
        // This would require metadata tracking in the cache manager
        // For now, we'll implement a simplified version

        $tags = [
            'stale_feedback_patterns',
            'stale_similarity_cache',
            'stale_aggregations'
        ];

        return $this->cacheManager->invalidateByTags($tags);
    }

    /**
     * Get workstreams affected by hierarchy changes
     *
     * @param Workstream $workstream
     * @param array $changes
     * @return Collection
     */
    private function getAffectedWorkstreams(Workstream $workstream, array $changes): Collection
    {
        $affected = collect([$workstream]);

        // Always include ancestors and descendants for hierarchy changes
        if (in_array('hierarchy', $changes) || in_array('moved', $changes)) {
            $ancestors = $workstream->getAllAncestorsOptimized();
            $descendants = $workstream->getAllDescendantsOptimized();
            $affected = $affected->merge($ancestors)->merge($descendants);
        }

        // For parent changes, include old parent hierarchy
        if (in_array('parent_changed', $changes) && isset($changes['old_parent_id'])) {
            $oldParent = Workstream::find($changes['old_parent_id']);
            if ($oldParent) {
                $oldParentAncestors = $oldParent->getAllAncestorsOptimized();
                $affected = $affected->merge($oldParentAncestors)->push($oldParent);
            }
        }

        return $affected->unique('id');
    }

    /**
     * Build invalidation tags for workstream changes
     *
     * @param Collection $workstreams
     * @param array $changes
     * @return array
     */
    private function buildWorkstreamInvalidationTags(Collection $workstreams, array $changes): array
    {
        $tags = [];

        foreach ($workstreams as $workstream) {
            $workstreamId = $workstream->id;

            // Core workstream tags
            $tags[] = "workstream:{$workstreamId}";
            $tags[] = "workstream_hierarchy:{$workstreamId}";
            $tags[] = "workstream_descendants:{$workstreamId}";
            $tags[] = "workstream_ancestors:{$workstreamId}";
            $tags[] = "workstream_tree:{$workstreamId}";
            $tags[] = "workstream_rollup:{$workstreamId}";

            // Permission-related tags
            $tags[] = "workstream_permissions:{$workstreamId}";
            $tags[] = "permission_inheritance:{$workstreamId}";
        }

        // Add change-specific tags
        if (in_array('status_changed', $changes)) {
            $tags[] = 'workstream_status_aggregations';
        }

        if (in_array('hierarchy', $changes) || in_array('moved', $changes)) {
            $tags[] = 'hierarchy_aggregations';
            $tags[] = 'rollup_reports';
        }

        return array_unique($tags);
    }

    /**
     * Get invalidation tags for entity types
     *
     * @param string $entityType
     * @return array
     */
    private function getEntityTypeInvalidationTags(string $entityType): array
    {
        return match ($entityType) {
            'workstream' => [
                'workstream_hierarchy',
                'workstream_permissions',
                'workstream_rollups',
                'hierarchy_aggregations'
            ],
            'feedback' => [
                'feedback_patterns',
                'feedback_analytics',
                'feedback_quality_scores',
                'similar_feedback',
                'rag_similarity',
                'user_feedback_patterns'
            ],
            'permissions' => [
                'user_permissions',
                'workstream_permissions',
                'permission_checks',
                'permission_inheritance'
            ],
            'embeddings' => [
                'similar_feedback',
                'rag_similarity',
                'vector_search',
                'embedding_cache'
            ],
            default => []
        };
    }

    /**
     * Build cache key using standardized patterns
     *
     * @param string $pattern
     * @param array $params
     * @return string
     */
    private function buildCacheKey(string $pattern, array $params): string
    {
        $patterns = $this->config['key_patterns'] ?? [];
        $template = $patterns[$pattern] ?? $pattern;

        // Replace placeholders with actual values
        foreach ($params as $key => $value) {
            $template = str_replace("{{$key}}", $value, $template);
        }

        return $template;
    }

    /**
     * Get cache invalidation priority based on change type
     *
     * @param array $changes
     * @return string
     */
    private function getInvalidationPriority(array $changes): string
    {
        // High priority changes that affect many caches
        $highPriorityChanges = ['hierarchy', 'moved', 'deleted', 'permissions_changed'];

        foreach ($highPriorityChanges as $highPriorityChange) {
            if (in_array($highPriorityChange, $changes)) {
                return 'high';
            }
        }

        // Medium priority changes
        $mediumPriorityChanges = ['status_changed', 'updated', 'feedback_added'];

        foreach ($mediumPriorityChanges as $mediumPriorityChange) {
            if (in_array($mediumPriorityChange, $changes)) {
                return 'medium';
            }
        }

        return 'low';
    }

    /**
     * Schedule cache warming based on invalidation priority
     *
     * @param string $priority
     * @param array $affectedEntities
     * @return array
     */
    private function scheduleWarmingByPriority(string $priority, array $affectedEntities): array
    {
        $warmingSpecs = [];

        if ($priority === 'high') {
            // Immediately warm critical caches
            foreach ($affectedEntities as $entity) {
                if ($entity instanceof Workstream) {
                    $warmingSpecs[] = [
                        'key' => $this->buildCacheKey('workstream_tree', ['id' => $entity->id]),
                        'callback' => fn() => $entity->buildHierarchyTreeOptimized(),
                        'ttl' => $this->config['ttl_presets']['long'] ?? 3600,
                        'tags' => ["workstream:{$entity->id}", "workstream_hierarchy:{$entity->id}"]
                    ];
                }
            }
        }

        return $warmingSpecs;
    }
}
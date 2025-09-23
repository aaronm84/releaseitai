<?php

namespace App\Traits;

use App\Services\Cache\DistributedCacheManager;
use Illuminate\Support\Facades\App;

/**
 * DistributedCacheable trait - Provides distributed caching methods for models and services
 *
 * This trait replaces Laravel's built-in Cache facade calls with distributed cache
 * operations that work across multiple servers and provide better scalability.
 */
trait DistributedCacheable
{
    /**
     * Get the distributed cache manager instance
     *
     * @return DistributedCacheManager
     */
    protected function getDistributedCache(): DistributedCacheManager
    {
        return App::make(DistributedCacheManager::class);
    }

    /**
     * Store data in distributed cache with tags
     *
     * @param string $key
     * @param mixed $data
     * @param int|null $ttl
     * @param array $tags
     * @return bool
     */
    protected function distributedCachePut(string $key, mixed $data, ?int $ttl = null, array $tags = []): bool
    {
        return $this->getDistributedCache()->put($key, $data, $ttl, $tags);
    }

    /**
     * Retrieve data from distributed cache
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function distributedCacheGet(string $key, mixed $default = null): mixed
    {
        return $this->getDistributedCache()->get($key, $default);
    }

    /**
     * Remember pattern with distributed locking
     *
     * @param string $key
     * @param callable $callback
     * @param int|null $ttl
     * @param array $tags
     * @return mixed
     */
    protected function distributedCacheRemember(string $key, callable $callback, ?int $ttl = null, array $tags = []): mixed
    {
        return $this->getDistributedCache()->remember($key, $callback, $ttl, $tags);
    }

    /**
     * Forget a cache key
     *
     * @param string $key
     * @return bool
     */
    protected function distributedCacheForget(string $key): bool
    {
        return $this->getDistributedCache()->forget($key);
    }

    /**
     * Build cache key using standardized patterns
     *
     * @param string $pattern
     * @param array $params
     * @return string
     */
    protected function buildDistributedCacheKey(string $pattern, array $params = []): string
    {
        $keyPatterns = config('cache_distributed.key_patterns', []);
        $template = $keyPatterns[$pattern] ?? $pattern;

        // Replace placeholders with actual values
        foreach ($params as $key => $value) {
            $template = str_replace("{{$key}}", $value, $template);
        }

        return $template;
    }

    /**
     * Get cache TTL based on preset or custom value
     *
     * @param string|int $ttlOrPreset
     * @return int
     */
    protected function getDistributedCacheTtl(string|int $ttlOrPreset): int
    {
        if (is_int($ttlOrPreset)) {
            return $ttlOrPreset;
        }

        $presets = config('cache_distributed.ttl_presets', []);
        return $presets[$ttlOrPreset] ?? 3600; // Default 1 hour
    }

    /**
     * Get cache tags for entity
     *
     * @param string $entityType
     * @param int|null $entityId
     * @return array
     */
    protected function getDistributedCacheTags(string $entityType, ?int $entityId = null): array
    {
        $tags = [$entityType];

        if ($entityId !== null) {
            $tags[] = "{$entityType}:{$entityId}";
        }

        // Add model-specific tags if this is a model
        if (method_exists($this, 'getTable')) {
            $tags[] = $this->getTable();
            if (isset($this->id)) {
                $tags[] = $this->getTable() . ':' . $this->id;
            }
        }

        return $tags;
    }

    /**
     * Cache hierarchy-related data with appropriate tags
     *
     * @param string $key
     * @param callable $callback
     * @param array $additionalTags
     * @return mixed
     */
    protected function cacheHierarchyData(string $key, callable $callback, array $additionalTags = []): mixed
    {
        $ttl = $this->getDistributedCacheTtl('long');
        $tags = array_merge(['hierarchy'], $additionalTags);

        // Add model-specific hierarchy tags
        if (isset($this->id)) {
            $modelName = class_basename($this);
            $tags[] = strtolower($modelName) . '_hierarchy';
            $tags[] = strtolower($modelName) . '_hierarchy:' . $this->id;
        }

        return $this->distributedCacheRemember($key, $callback, $ttl, $tags);
    }

    /**
     * Cache permission-related data with appropriate tags
     *
     * @param string $key
     * @param callable $callback
     * @param int|null $userId
     * @param array $additionalTags
     * @return mixed
     */
    protected function cachePermissionData(string $key, callable $callback, ?int $userId = null, array $additionalTags = []): mixed
    {
        $ttl = $this->getDistributedCacheTtl('medium');
        $tags = array_merge(['permissions'], $additionalTags);

        if ($userId !== null) {
            $tags[] = "user_permissions:{$userId}";
        }

        if (isset($this->id)) {
            $modelName = class_basename($this);
            $tags[] = strtolower($modelName) . '_permissions:' . $this->id;
        }

        return $this->distributedCacheRemember($key, $callback, $ttl, $tags);
    }

    /**
     * Cache feedback-related data with appropriate tags
     *
     * @param string $key
     * @param callable $callback
     * @param array $additionalTags
     * @return mixed
     */
    protected function cacheFeedbackData(string $key, callable $callback, array $additionalTags = []): mixed
    {
        $ttl = $this->getDistributedCacheTtl('medium');
        $tags = array_merge(['feedback'], $additionalTags);

        return $this->distributedCacheRemember($key, $callback, $ttl, $tags);
    }

    /**
     * Cache similarity/RAG data with appropriate tags
     *
     * @param string $key
     * @param callable $callback
     * @param array $additionalTags
     * @return mixed
     */
    protected function cacheSimilarityData(string $key, callable $callback, array $additionalTags = []): mixed
    {
        $ttl = $this->getDistributedCacheTtl('medium');
        $tags = array_merge(['similarity', 'rag'], $additionalTags);

        return $this->distributedCacheRemember($key, $callback, $ttl, $tags);
    }

    /**
     * Cache aggregation data with appropriate tags
     *
     * @param string $key
     * @param callable $callback
     * @param array $additionalTags
     * @return mixed
     */
    protected function cacheAggregationData(string $key, callable $callback, array $additionalTags = []): mixed
    {
        $ttl = $this->getDistributedCacheTtl('long');
        $tags = array_merge(['aggregations'], $additionalTags);

        return $this->distributedCacheRemember($key, $callback, $ttl, $tags);
    }

    /**
     * Bulk cache operations for better performance
     *
     * @param array $operations
     * @return array
     */
    protected function distributedCacheBulk(array $operations): array
    {
        return $this->getDistributedCache()->bulk($operations);
    }

    /**
     * Check if distributed caching is enabled
     *
     * @return bool
     */
    protected function isDistributedCachingEnabled(): bool
    {
        return config('cache_distributed.enabled', true) &&
               !app()->environment('testing') &&
               config('cache.default') !== 'array';
    }

    /**
     * Fallback to regular caching if distributed caching is not available
     *
     * @param string $key
     * @param callable $callback
     * @param int|null $ttl
     * @return mixed
     */
    protected function cacheWithFallback(string $key, callable $callback, ?int $ttl = null): mixed
    {
        if ($this->isDistributedCachingEnabled()) {
            return $this->distributedCacheRemember($key, $callback, $ttl);
        }

        // Fallback to Laravel's built-in cache
        return \Cache::remember($key, $ttl ?? 3600, $callback);
    }

    /**
     * Skip caching during testing to avoid transaction issues
     *
     * @param callable $callback
     * @return mixed
     */
    protected function skipCacheInTesting(callable $callback): mixed
    {
        if (app()->environment('testing')) {
            return $callback();
        }

        return null; // Cache should be used in non-testing environments
    }
}
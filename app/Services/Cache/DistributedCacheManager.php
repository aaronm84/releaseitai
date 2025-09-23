<?php

namespace App\Services\Cache;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Exception;

/**
 * DistributedCacheManager - Provides scalable distributed caching with hierarchy-aware invalidation
 *
 * This service replaces in-memory caching patterns with a Redis-based distributed solution
 * that works across multiple servers and provides consistent cache invalidation.
 */
class DistributedCacheManager
{
    private string $defaultStore;
    private array $config;
    private array $metrics = [];

    public function __construct()
    {
        $this->defaultStore = config('cache.distributed.default_store', 'redis');
        $this->config = config('cache.distributed', []);
        $this->initializeMetrics();

        // Fallback to database if Redis is not available
        if ($this->defaultStore === 'redis' && !$this->isRedisAvailable()) {
            Log::warning('Redis not available, falling back to database cache');
            $this->defaultStore = 'database';
        }
    }

    /**
     * Store data in distributed cache with tags for organized invalidation
     *
     * @param string $key
     * @param mixed $data
     * @param int|null $ttl Time to live in seconds
     * @param array $tags Array of tags for invalidation groups
     * @return bool
     */
    public function put(string $key, mixed $data, ?int $ttl = null, array $tags = []): bool
    {
        try {
            $startTime = microtime(true);
            $normalizedKey = $this->normalizeKey($key);
            $ttl = $ttl ?? $this->getDefaultTtl();

            // Serialize data with compression for better network performance
            $serializedData = $this->serialize($data);

            // Store main data
            $success = Cache::store($this->defaultStore)->put($normalizedKey, $serializedData, $ttl);

            // Store tags for invalidation if provided
            if (!empty($tags)) {
                $this->storeTags($normalizedKey, $tags, $ttl);
            }

            // Store metadata for monitoring
            $this->storeMetadata($normalizedKey, $ttl, strlen($serializedData), $tags);

            $this->recordMetric('cache_write', microtime(true) - $startTime);
            return $success;

        } catch (Exception $e) {
            Log::error('Distributed cache write failed', [
                'key' => $key,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->recordMetric('cache_write_error', 1);
            return false;
        }
    }

    /**
     * Retrieve data from distributed cache
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        try {
            $startTime = microtime(true);
            $normalizedKey = $this->normalizeKey($key);

            $data = Cache::store($this->defaultStore)->get($normalizedKey);

            if ($data === null) {
                $this->recordMetric('cache_miss', 1);
                return $default;
            }

            $result = $this->unserialize($data);
            $this->recordMetric('cache_hit', 1);
            $this->recordMetric('cache_read', microtime(true) - $startTime);

            return $result;

        } catch (Exception $e) {
            Log::error('Distributed cache read failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            $this->recordMetric('cache_read_error', 1);
            return $default;
        }
    }

    /**
     * Remember pattern with distributed locking for cache consistency
     *
     * @param string $key
     * @param callable $callback
     * @param int|null $ttl
     * @param array $tags
     * @return mixed
     */
    public function remember(string $key, callable $callback, ?int $ttl = null, array $tags = []): mixed
    {
        $cached = $this->get($key);
        if ($cached !== null) {
            return $cached;
        }

        // Use distributed lock to prevent cache stampede
        $lockKey = "lock:" . $this->normalizeKey($key);
        $lockTtl = min(30, ($ttl ?? $this->getDefaultTtl()) / 10); // Lock for max 30 seconds

        return $this->withLock($lockKey, $lockTtl, function () use ($key, $callback, $ttl, $tags) {
            // Double-check pattern: verify cache is still empty after acquiring lock
            $cached = $this->get($key);
            if ($cached !== null) {
                return $cached;
            }

            $data = $callback();
            $this->put($key, $data, $ttl, $tags);
            return $data;
        });
    }

    /**
     * Forget a cache key
     *
     * @param string $key
     * @return bool
     */
    public function forget(string $key): bool
    {
        try {
            $normalizedKey = $this->normalizeKey($key);

            // Remove main data
            $success = Cache::store($this->defaultStore)->forget($normalizedKey);

            // Clean up tags and metadata
            $this->cleanupKeyReferences($normalizedKey);

            return $success;

        } catch (Exception $e) {
            Log::error('Distributed cache forget failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Invalidate all cache entries with specific tags
     *
     * @param array $tags
     * @return int Number of keys invalidated
     */
    public function invalidateByTags(array $tags): int
    {
        try {
            $startTime = microtime(true);
            $invalidatedCount = 0;

            foreach ($tags as $tag) {
                $tagKey = $this->getTagKey($tag);
                $keys = Cache::store($this->defaultStore)->get($tagKey, []);

                foreach ($keys as $key) {
                    if ($this->forget($key)) {
                        $invalidatedCount++;
                    }
                }

                // Remove the tag key itself
                Cache::store($this->defaultStore)->forget($tagKey);
            }

            $this->recordMetric('cache_tag_invalidation', microtime(true) - $startTime);
            $this->recordMetric('cache_keys_invalidated', $invalidatedCount);

            Log::info('Cache invalidation by tags completed', [
                'tags' => $tags,
                'keys_invalidated' => $invalidatedCount
            ]);

            return $invalidatedCount;

        } catch (Exception $e) {
            Log::error('Cache tag invalidation failed', [
                'tags' => $tags,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Invalidate hierarchical cache entries (workstream-specific)
     *
     * @param string $type Type of hierarchy (workstream, user, etc.)
     * @param int $id Entity ID
     * @param array $relationships Related entity types to invalidate
     * @return int Number of keys invalidated
     */
    public function invalidateHierarchy(string $type, int $id, array $relationships = []): int
    {
        $tags = [
            "{$type}:{$id}",
            "{$type}_hierarchy:{$id}",
            "{$type}_permissions:{$id}"
        ];

        // Add relationship tags
        foreach ($relationships as $relation => $relatedIds) {
            if (is_array($relatedIds)) {
                foreach ($relatedIds as $relatedId) {
                    $tags[] = "{$relation}:{$relatedId}";
                }
            } else {
                $tags[] = "{$relation}:{$relatedIds}";
            }
        }

        return $this->invalidateByTags($tags);
    }

    /**
     * Bulk cache operations for better performance
     *
     * @param array $operations Array of operations: [['put', $key, $data, $ttl, $tags], ['get', $key], ...]
     * @return array Results of operations
     */
    public function bulk(array $operations): array
    {
        $results = [];
        $startTime = microtime(true);

        try {
            // Group operations by type for optimization
            $gets = [];
            $puts = [];

            foreach ($operations as $index => $operation) {
                $type = $operation[0] ?? '';

                switch ($type) {
                    case 'get':
                        $gets[$index] = $operation[1] ?? '';
                        break;
                    case 'put':
                        $puts[$index] = $operation;
                        break;
                    default:
                        $results[$index] = ['error' => 'Unknown operation type'];
                }
            }

            // Batch get operations
            if (!empty($gets)) {
                $getResults = $this->bulkGet(array_values($gets));
                foreach ($gets as $index => $key) {
                    $results[$index] = $getResults[array_search($key, array_values($gets))] ?? null;
                }
            }

            // Execute put operations
            foreach ($puts as $index => $operation) {
                [, $key, $data, $ttl, $tags] = array_pad($operation, 5, []);
                $results[$index] = $this->put($key, $data, $ttl, $tags);
            }

            $this->recordMetric('cache_bulk_operation', microtime(true) - $startTime);
            return $results;

        } catch (Exception $e) {
            Log::error('Bulk cache operation failed', [
                'operations_count' => count($operations),
                'error' => $e->getMessage()
            ]);
            return array_fill(0, count($operations), false);
        }
    }

    /**
     * Implement cache warming for frequently accessed data
     *
     * @param array $warmingSpecs Array of warming specifications
     * @return array Warming results
     */
    public function warm(array $warmingSpecs): array
    {
        $results = [];
        $startTime = microtime(true);

        try {
            foreach ($warmingSpecs as $spec) {
                $key = $spec['key'];
                $callback = $spec['callback'];
                $ttl = $spec['ttl'] ?? null;
                $tags = $spec['tags'] ?? [];
                $force = $spec['force'] ?? false;

                // Skip if already cached unless forced
                if (!$force && $this->get($key) !== null) {
                    $results[$key] = ['status' => 'skipped', 'reason' => 'already_cached'];
                    continue;
                }

                try {
                    $data = $callback();
                    $success = $this->put($key, $data, $ttl, $tags);
                    $results[$key] = ['status' => $success ? 'warmed' : 'failed'];
                } catch (Exception $e) {
                    $results[$key] = ['status' => 'failed', 'error' => $e->getMessage()];
                }
            }

            $this->recordMetric('cache_warming', microtime(true) - $startTime);
            $this->recordMetric('cache_warming_specs', count($warmingSpecs));

            return $results;

        } catch (Exception $e) {
            Log::error('Cache warming failed', [
                'specs_count' => count($warmingSpecs),
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get cache health metrics
     *
     * @return array
     */
    public function getHealthMetrics(): array
    {
        try {
            $redis = Redis::connection($this->config['redis_connection'] ?? 'cache');
            $info = $redis->info();

            return [
                'cache_store' => $this->defaultStore,
                'redis_connected' => true,
                'redis_memory_used' => $info['used_memory_human'] ?? 'N/A',
                'redis_total_commands' => $info['total_commands_processed'] ?? 0,
                'cache_hits' => $this->getMetric('cache_hit'),
                'cache_misses' => $this->getMetric('cache_miss'),
                'hit_ratio' => $this->calculateHitRatio(),
                'avg_read_time' => $this->getMetric('cache_read', 'avg'),
                'avg_write_time' => $this->getMetric('cache_write', 'avg'),
                'errors' => [
                    'read_errors' => $this->getMetric('cache_read_error'),
                    'write_errors' => $this->getMetric('cache_write_error')
                ]
            ];

        } catch (Exception $e) {
            return [
                'cache_store' => $this->defaultStore,
                'redis_connected' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Flush all cache (use with caution)
     *
     * @param bool $confirm Safety confirmation
     * @return bool
     */
    public function flush(bool $confirm = false): bool
    {
        if (!$confirm) {
            throw new InvalidArgumentException('Cache flush requires explicit confirmation');
        }

        try {
            $result = Cache::store($this->defaultStore)->flush();
            Log::warning('Distributed cache flushed completely');
            return $result;
        } catch (Exception $e) {
            Log::error('Cache flush failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Execute callback with distributed lock
     *
     * @param string $lockKey
     * @param int $ttl
     * @param callable $callback
     * @return mixed
     */
    private function withLock(string $lockKey, int $ttl, callable $callback): mixed
    {
        $redis = Redis::connection($this->config['redis_connection'] ?? 'cache');
        $lockValue = uniqid();

        // Acquire lock with NX (set if not exists) and EX (expire)
        $acquired = $redis->set($lockKey, $lockValue, 'EX', $ttl, 'NX');

        if (!$acquired) {
            // Wait briefly and try to get cached value
            usleep(100000); // 100ms
            return $this->get(str_replace('lock:', '', $lockKey));
        }

        try {
            return $callback();
        } finally {
            // Release lock only if we still own it
            $script = "
                if redis.call('get', KEYS[1]) == ARGV[1] then
                    return redis.call('del', KEYS[1])
                else
                    return 0
                end
            ";
            $redis->eval($script, 1, $lockKey, $lockValue);
        }
    }

    /**
     * Bulk get operations with pipelining
     *
     * @param array $keys
     * @return array
     */
    private function bulkGet(array $keys): array
    {
        try {
            $normalizedKeys = array_map([$this, 'normalizeKey'], $keys);
            $redis = Redis::connection($this->config['redis_connection'] ?? 'cache');

            // Use pipeline for better performance
            $pipeline = $redis->pipeline();
            foreach ($normalizedKeys as $key) {
                $pipeline->get($this->getCacheKey($key));
            }
            $results = $pipeline->execute();

            return array_map(function ($data) {
                return $data ? $this->unserialize($data) : null;
            }, $results);

        } catch (Exception $e) {
            Log::error('Bulk get operation failed', [
                'keys_count' => count($keys),
                'error' => $e->getMessage()
            ]);
            return array_fill(0, count($keys), null);
        }
    }

    /**
     * Store cache tags for invalidation
     *
     * @param string $key
     * @param array $tags
     * @param int $ttl
     */
    private function storeTags(string $key, array $tags, int $ttl): void
    {
        foreach ($tags as $tag) {
            $tagKey = $this->getTagKey($tag);
            $keys = Cache::store($this->defaultStore)->get($tagKey, []);
            $keys[] = $key;
            $keys = array_unique($keys);

            // Store with longer TTL to ensure tag cleanup
            Cache::store($this->defaultStore)->put($tagKey, $keys, $ttl + 300);
        }
    }

    /**
     * Store cache metadata for monitoring
     *
     * @param string $key
     * @param int $ttl
     * @param int $size
     * @param array $tags
     */
    private function storeMetadata(string $key, int $ttl, int $size, array $tags): void
    {
        if (!($this->config['store_metadata'] ?? true)) {
            return;
        }

        $metadata = [
            'created_at' => time(),
            'ttl' => $ttl,
            'size' => $size,
            'tags' => $tags
        ];

        $metadataKey = "meta:" . $key;
        Cache::store($this->defaultStore)->put($metadataKey, $metadata, $ttl);
    }

    /**
     * Clean up tag and metadata references for a key
     *
     * @param string $key
     */
    private function cleanupKeyReferences(string $key): void
    {
        // Remove metadata
        $metadataKey = "meta:" . $key;
        Cache::store($this->defaultStore)->forget($metadataKey);

        // Note: Tag cleanup is handled during tag invalidation to avoid expensive operations
    }

    /**
     * Normalize cache key for consistency
     *
     * @param string $key
     * @return string
     */
    private function normalizeKey(string $key): string
    {
        // Remove any existing prefix and add our distributed cache prefix
        $cleanKey = preg_replace('/^[^:]*:/', '', $key);
        return 'dist:' . $cleanKey;
    }

    /**
     * Get full cache key with store prefix
     *
     * @param string $normalizedKey
     * @return string
     */
    private function getCacheKey(string $normalizedKey): string
    {
        $prefix = config('cache.prefix', '');
        return $prefix . $normalizedKey;
    }

    /**
     * Get tag key for tag-based invalidation
     *
     * @param string $tag
     * @return string
     */
    private function getTagKey(string $tag): string
    {
        return "tag:" . $tag;
    }

    /**
     * Serialize data with compression
     *
     * @param mixed $data
     * @return string
     */
    private function serialize(mixed $data): string
    {
        $serialized = serialize($data);

        // Compress large payloads
        if (strlen($serialized) > ($this->config['compression_threshold'] ?? 1024)) {
            return 'gz:' . gzcompress($serialized, $this->config['compression_level'] ?? 6);
        }

        return $serialized;
    }

    /**
     * Unserialize data with decompression
     *
     * @param string $data
     * @return mixed
     */
    private function unserialize(string $data): mixed
    {
        // Check if data is compressed
        if (str_starts_with($data, 'gz:')) {
            $data = gzuncompress(substr($data, 3));
        }

        return unserialize($data);
    }

    /**
     * Get default TTL from config
     *
     * @return int
     */
    private function getDefaultTtl(): int
    {
        return $this->config['default_ttl'] ?? 3600; // 1 hour default
    }

    /**
     * Initialize metrics tracking
     */
    private function initializeMetrics(): void
    {
        $this->metrics = [
            'cache_hit' => ['count' => 0],
            'cache_miss' => ['count' => 0],
            'cache_read' => ['times' => [], 'count' => 0],
            'cache_write' => ['times' => [], 'count' => 0],
            'cache_read_error' => ['count' => 0],
            'cache_write_error' => ['count' => 0]
        ];
    }

    /**
     * Record metric
     *
     * @param string $metric
     * @param mixed $value
     */
    private function recordMetric(string $metric, mixed $value): void
    {
        if (!isset($this->metrics[$metric])) {
            $this->metrics[$metric] = ['count' => 0, 'times' => []];
        }

        if (is_numeric($value) && str_ends_with($metric, '_time') || in_array($metric, ['cache_read', 'cache_write'])) {
            $this->metrics[$metric]['times'][] = $value;
        } else {
            $this->metrics[$metric]['count'] += $value;
        }
    }

    /**
     * Get metric value
     *
     * @param string $metric
     * @param string $type
     * @return mixed
     */
    private function getMetric(string $metric, string $type = 'count'): mixed
    {
        if (!isset($this->metrics[$metric])) {
            return 0;
        }

        $data = $this->metrics[$metric];

        return match ($type) {
            'count' => $data['count'] ?? 0,
            'avg' => !empty($data['times']) ? array_sum($data['times']) / count($data['times']) : 0,
            'total' => !empty($data['times']) ? array_sum($data['times']) : 0,
            default => $data
        };
    }

    /**
     * Calculate hit ratio
     *
     * @return float
     */
    private function calculateHitRatio(): float
    {
        $hits = $this->getMetric('cache_hit');
        $misses = $this->getMetric('cache_miss');
        $total = $hits + $misses;

        return $total > 0 ? round(($hits / $total) * 100, 2) : 0.0;
    }

    /**
     * Check if Redis is available
     *
     * @return bool
     */
    private function isRedisAvailable(): bool
    {
        try {
            $redis = Redis::connection($this->config['redis_connection'] ?? 'cache');
            $redis->ping();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
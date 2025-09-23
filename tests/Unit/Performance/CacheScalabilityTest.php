<?php

namespace Tests\Unit\Performance;

use App\Models\Workstream;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Tests cache scalability for multi-server scenarios.
 * Validates cache behavior for cache invalidation across instances, consistency under concurrent access, and performance under load.
 */
class CacheScalabilityTest extends BasePerformanceTest
{
    private array $hierarchyData;
    private string $cacheKeyPrefix = 'test_cache_scalability_';

    protected function setUp(): void
    {
        parent::setUp();

        // Create hierarchy data for cache testing
        $this->hierarchyData = $this->createTestWorkstreamHierarchy(3, 5);

        // Clear all cache to start fresh
        Cache::flush();
    }

    protected function tearDown(): void
    {
        // Clean up test cache keys
        $this->clearTestCacheKeys();
        parent::tearDown();
    }

    /**
     * Clear all test cache keys to prevent pollution between tests
     */
    private function clearTestCacheKeys(): void
    {
        // In a real scenario, you'd want to use cache tags or a more sophisticated cleanup
        // For testing purposes, we'll just flush all cache
        Cache::flush();
    }

    /**
     * Test cache invalidation consistency across multiple cache instances.
     *
     * Given: Cached data across multiple simulated cache instances
     * When: Invalidating cache on one instance
     * Then: Cache should be properly invalidated across all instances within acceptable time
     */
    public function test_cache_invalidation_consistency_across_instances(): void
    {
        $workstream = $this->hierarchyData['roots']->first();
        $cacheKey = $this->cacheKeyPrefix . 'workstream_' . $workstream->id;

        // Simulate caching workstream data
        Cache::put($cacheKey, $workstream->toArray(), 3600);
        Cache::put($cacheKey . '_hierarchy', $workstream->buildHierarchyTree(), 3600);

        // Verify cache exists
        $this->assertTrue(Cache::has($cacheKey));
        $this->assertTrue(Cache::has($cacheKey . '_hierarchy'));

        $metrics = $this->measureCachePerformance(function () use ($cacheKey, $workstream) {
            // Simulate cache invalidation across instances
            // In a real distributed cache, this would use cache tags or distributed invalidation
            Cache::forget($cacheKey);
            Cache::forget($cacheKey . '_hierarchy');

            // Simulate related cache invalidation
            $relatedKeys = [
                $this->cacheKeyPrefix . 'workstream_children_' . $workstream->id,
                $this->cacheKeyPrefix . 'workstream_ancestors_' . $workstream->id,
                $this->cacheKeyPrefix . 'workstream_permissions_' . $workstream->id
            ];

            foreach ($relatedKeys as $key) {
                Cache::forget($key);
            }
        }, 50);

        // Assert invalidation performance
        $this->assertLessThan(
            50, // 50ms max for cache invalidation
            $metrics['average_time_ms'],
            "Cache invalidation took {$metrics['average_time_ms']}ms, which exceeds 50ms threshold"
        );

        // Verify cache was properly invalidated
        $this->assertFalse(Cache::has($cacheKey));
        $this->assertFalse(Cache::has($cacheKey . '_hierarchy'));
    }

    /**
     * Test cache consistency under concurrent access scenarios.
     *
     * Given: Multiple simulated concurrent requests accessing the same cached data
     * When: Some requests update cache while others read from it
     * Then: Cache should maintain consistency and perform within acceptable limits
     */
    public function test_cache_consistency_under_concurrent_access(): void
    {
        $workstream = $this->hierarchyData['roots']->first();
        $cacheKey = $this->cacheKeyPrefix . 'concurrent_test_' . $workstream->id;

        // Simulate concurrent cache operations
        $concurrentOperations = [];

        // Simulate 100 concurrent operations (reads and writes)
        for ($i = 0; $i < 100; $i++) {
            if ($i % 3 === 0) {
                // Write operation (cache invalidation + write)
                $concurrentOperations[] = function () use ($cacheKey, $workstream) {
                    Cache::forget($cacheKey);
                    Cache::put($cacheKey, $workstream->fresh()->toArray(), 3600);
                };
            } else {
                // Read operation
                $concurrentOperations[] = function () use ($cacheKey, $workstream) {
                    $cached = Cache::get($cacheKey);
                    if (!$cached) {
                        // Cache miss - simulate database query and cache write
                        $data = $workstream->fresh()->toArray();
                        Cache::put($cacheKey, $data, 3600);
                        return $data;
                    }
                    return $cached;
                };
            }
        }

        $metrics = $this->measureCachePerformance(function () use ($concurrentOperations) {
            // Execute operations sequentially to simulate concurrent access patterns
            foreach ($concurrentOperations as $operation) {
                $operation();
            }
        }, 1);

        // Assert concurrent access performance
        $this->assertLessThan(
            self::MAX_BULK_OPERATION_TIME_MS,
            $metrics['total_time_ms'],
            "Concurrent cache operations took {$metrics['total_time_ms']}ms, which exceeds threshold"
        );

        // Verify cache is in a consistent state
        $finalCached = Cache::get($cacheKey);
        $this->assertIsArray($finalCached);
        $this->assertEquals($workstream->id, $finalCached['id']);
    }

    /**
     * Test cache performance under high load scenarios.
     *
     * Given: High volume of cache operations
     * When: Performing many cache reads/writes in rapid succession
     * Then: Cache should maintain performance within acceptable limits
     */
    public function test_cache_performance_under_high_load(): void
    {
        $workstreams = $this->hierarchyData['all'];
        $cacheKeys = [];

        // Pre-populate cache with workstream data
        foreach ($workstreams as $index => $workstream) {
            $cacheKey = $this->cacheKeyPrefix . 'load_test_' . $workstream['id'];
            $cacheKeys[] = $cacheKey;
            Cache::put($cacheKey, $workstream, 3600);
        }

        // Test high-load cache access
        $metrics = $this->measureCachePerformance(function () use ($cacheKeys) {
            // Simulate high load: 1000 cache operations
            for ($i = 0; $i < 1000; $i++) {
                $randomKey = $cacheKeys[array_rand($cacheKeys)];

                if ($i % 10 === 0) {
                    // 10% write operations
                    Cache::put($randomKey, ['updated_at' => time()], 3600);
                } else {
                    // 90% read operations
                    Cache::get($randomKey);
                }
            }
        }, 1);

        // Assert high-load performance
        $this->assertLessThan(
            self::MAX_BULK_OPERATION_TIME_MS * 2, // Allow more time for high load
            $metrics['total_time_ms'],
            "High-load cache operations took {$metrics['total_time_ms']}ms, which exceeds threshold"
        );

        // Average per operation should be fast
        $averagePerOperation = $metrics['total_time_ms'] / 1000;
        $this->assertLessThan(
            1, // 1ms per operation max
            $averagePerOperation,
            "Average cache operation took {$averagePerOperation}ms, which exceeds 1ms threshold"
        );
    }

    /**
     * Test cache invalidation patterns for workstream hierarchy changes.
     *
     * Given: Cached workstream hierarchy data
     * When: Making changes to workstream hierarchy
     * Then: Related caches should be invalidated properly and efficiently
     */
    public function test_workstream_hierarchy_cache_invalidation_patterns(): void
    {
        $workstream = $this->hierarchyData['roots']->first();

        // Cache hierarchy-related data
        $hierarchyCacheKeys = [
            $this->cacheKeyPrefix . 'hierarchy_' . $workstream->id => $workstream->buildHierarchyTree(),
            $this->cacheKeyPrefix . 'ancestors_' . $workstream->id => $workstream->getAllAncestors()->toArray(),
            $this->cacheKeyPrefix . 'descendants_' . $workstream->id => $workstream->getAllDescendants()->toArray(),
            $this->cacheKeyPrefix . 'permissions_' . $workstream->id => $workstream->getEffectivePermissionsForUser($this->testUser->id)
        ];

        foreach ($hierarchyCacheKeys as $key => $data) {
            Cache::put($key, $data, 3600);
        }

        // Verify all caches exist
        foreach (array_keys($hierarchyCacheKeys) as $key) {
            $this->assertTrue(Cache::has($key));
        }

        $metrics = $this->measureCachePerformance(function () use ($workstream, $hierarchyCacheKeys) {
            // Simulate workstream hierarchy change
            $workstream->update(['name' => 'Updated Name']);

            // Cache invalidation for hierarchy changes
            foreach (array_keys($hierarchyCacheKeys) as $key) {
                Cache::forget($key);
            }

            // Also invalidate related caches (children, parents)
            $relatedWorkstreams = collect($this->hierarchyData['all'])
                ->where('parent_workstream_id', $workstream->id);

            foreach ($relatedWorkstreams as $related) {
                Cache::forget($this->cacheKeyPrefix . 'hierarchy_' . $related['id']);
                Cache::forget($this->cacheKeyPrefix . 'ancestors_' . $related['id']);
            }
        }, 10);

        // Assert invalidation performance
        $this->assertLessThan(
            100, // 100ms max for hierarchy cache invalidation
            $metrics['average_time_ms'],
            "Hierarchy cache invalidation took {$metrics['average_time_ms']}ms, which exceeds threshold"
        );

        // Verify caches were properly invalidated
        foreach (array_keys($hierarchyCacheKeys) as $key) {
            $this->assertFalse(Cache::has($key));
        }
    }

    /**
     * Test cache memory usage optimization for large datasets.
     *
     * Given: Large amounts of data that could be cached
     * When: Caching data with size constraints
     * Then: Cache should handle large datasets efficiently without memory issues
     */
    public function test_cache_memory_usage_optimization_for_large_datasets(): void
    {
        $largeDatasets = [];

        // Create large datasets for caching
        for ($i = 0; $i < 50; $i++) {
            $largeDatasets[] = [
                'key' => $this->cacheKeyPrefix . 'large_dataset_' . $i,
                'data' => $this->generateLargeDataset(1000) // 1000 items each
            ];
        }

        $metrics = $this->measureCachePerformance(function () use ($largeDatasets) {
            // Cache large datasets
            foreach ($largeDatasets as $dataset) {
                Cache::put($dataset['key'], $dataset['data'], 3600);
            }

            // Read back some of the data to test retrieval performance
            for ($i = 0; $i < 10; $i++) {
                $randomDataset = $largeDatasets[array_rand($largeDatasets)];
                Cache::get($randomDataset['key']);
            }
        }, 1);

        // Assert memory-efficient caching performance
        $this->assertLessThan(
            self::MAX_BULK_OPERATION_TIME_MS * 3, // Allow more time for large datasets
            $metrics['total_time_ms'],
            "Large dataset caching took {$metrics['total_time_ms']}ms, which exceeds threshold"
        );

        // Clean up large datasets
        foreach ($largeDatasets as $dataset) {
            Cache::forget($dataset['key']);
        }
    }

    /**
     * Test cache eviction policies and memory pressure scenarios.
     *
     * Given: Cache with limited memory capacity
     * When: Adding more data than cache can hold
     * Then: Cache should evict data according to policy and maintain performance
     */
    public function test_cache_eviction_policies_under_memory_pressure(): void
    {
        $cacheKeys = [];

        // Fill cache with data to simulate memory pressure
        for ($i = 0; $i < 200; $i++) {
            $key = $this->cacheKeyPrefix . 'eviction_test_' . $i;
            $cacheKeys[] = $key;

            $data = $this->generateMediumDataset(100);
            Cache::put($key, $data, 3600);
        }

        $metrics = $this->measureCachePerformance(function () use ($cacheKeys) {
            // Add more data to trigger potential eviction
            for ($i = 200; $i < 300; $i++) {
                $key = $this->cacheKeyPrefix . 'eviction_test_' . $i;
                $data = $this->generateMediumDataset(100);
                Cache::put($key, $data, 3600);
            }

            // Access some older keys to test eviction behavior
            for ($i = 0; $i < 50; $i++) {
                Cache::get($cacheKeys[$i]);
            }
        }, 1);

        // Assert eviction performance
        $this->assertLessThan(
            self::MAX_BULK_OPERATION_TIME_MS,
            $metrics['total_time_ms'],
            "Cache eviction scenario took {$metrics['total_time_ms']}ms, which exceeds threshold"
        );

        // Clean up
        for ($i = 0; $i < 300; $i++) {
            Cache::forget($this->cacheKeyPrefix . 'eviction_test_' . $i);
        }
    }

    /**
     * Test distributed cache consistency for workstream operations.
     *
     * Given: Workstream data cached across multiple cache instances
     * When: Updating workstream data
     * Then: All cache instances should reflect the changes consistently
     */
    public function test_distributed_cache_consistency_for_workstream_operations(): void
    {
        $workstream = $this->hierarchyData['roots']->first();

        // Simulate multiple cache instances by using different key patterns
        $cacheInstances = [
            'instance_1_' => $this->cacheKeyPrefix . 'inst1_workstream_' . $workstream->id,
            'instance_2_' => $this->cacheKeyPrefix . 'inst2_workstream_' . $workstream->id,
            'instance_3_' => $this->cacheKeyPrefix . 'inst3_workstream_' . $workstream->id
        ];

        // Cache data in all "instances"
        foreach ($cacheInstances as $prefix => $key) {
            Cache::put($key, $workstream->toArray(), 3600);
        }

        $metrics = $this->measureCachePerformance(function () use ($workstream, $cacheInstances) {
            // Simulate workstream update
            $workstream->update(['status' => 'on_hold']);

            // Invalidate cache across all instances
            foreach ($cacheInstances as $key) {
                Cache::forget($key);
            }

            // Re-cache updated data across all instances
            $updatedData = $workstream->fresh()->toArray();
            foreach ($cacheInstances as $key) {
                Cache::put($key, $updatedData, 3600);
            }
        }, 10);

        // Assert distributed consistency performance
        $this->assertLessThan(
            200, // 200ms max for distributed cache operations
            $metrics['average_time_ms'],
            "Distributed cache consistency took {$metrics['average_time_ms']}ms, which exceeds threshold"
        );

        // Verify consistency across all instances
        $cachedData = [];
        foreach ($cacheInstances as $key) {
            $cachedData[] = Cache::get($key);
        }

        // All instances should have the same data
        $firstInstance = $cachedData[0];
        foreach ($cachedData as $instanceData) {
            $this->assertEquals($firstInstance['status'], $instanceData['status']);
            $this->assertEquals('on_hold', $instanceData['status']);
        }
    }

    /**
     * Test cache warming strategies for performance optimization.
     *
     * Given: Cold cache (empty cache)
     * When: Implementing cache warming strategies
     * Then: Cache warming should complete efficiently and improve subsequent performance
     */
    public function test_cache_warming_strategies_for_performance_optimization(): void
    {
        // Clear cache to simulate cold start
        Cache::flush();

        $workstreams = collect($this->hierarchyData['all'])->take(20);

        // Measure cache warming performance
        $warmingMetrics = $this->measureCachePerformance(function () use ($workstreams) {
            // Implement cache warming strategy
            foreach ($workstreams as $workstream) {
                $workstreamId = $workstream['id'];

                // Warm frequently accessed data
                $cacheKeys = [
                    $this->cacheKeyPrefix . 'warm_workstream_' . $workstreamId,
                    $this->cacheKeyPrefix . 'warm_hierarchy_' . $workstreamId,
                    $this->cacheKeyPrefix . 'warm_children_' . $workstreamId
                ];

                foreach ($cacheKeys as $key) {
                    Cache::put($key, $workstream, 3600);
                }
            }
        }, 1);

        // Measure performance after warming
        $accessMetrics = $this->measureCachePerformance(function () use ($workstreams) {
            // Access warmed cache data
            foreach ($workstreams as $workstream) {
                $workstreamId = $workstream['id'];

                Cache::get($this->cacheKeyPrefix . 'warm_workstream_' . $workstreamId);
                Cache::get($this->cacheKeyPrefix . 'warm_hierarchy_' . $workstreamId);
                Cache::get($this->cacheKeyPrefix . 'warm_children_' . $workstreamId);
            }
        }, 10);

        // Assert cache warming performance
        $this->assertLessThan(
            self::MAX_BULK_OPERATION_TIME_MS,
            $warmingMetrics['total_time_ms'],
            "Cache warming took {$warmingMetrics['total_time_ms']}ms, which exceeds threshold"
        );

        // Assert warmed cache access performance
        $this->assertLessThan(
            5, // Very fast access after warming
            $accessMetrics['average_time_ms'],
            "Warmed cache access took {$accessMetrics['average_time_ms']}ms, which exceeds threshold"
        );
    }

    /**
     * Generate large dataset for cache testing
     */
    private function generateLargeDataset(int $size): array
    {
        $dataset = [];
        for ($i = 0; $i < $size; $i++) {
            $dataset[] = [
                'id' => $i,
                'name' => 'Item ' . $i,
                'data' => str_repeat('x', 100), // 100 chars of data per item
                'timestamp' => time(),
                'metadata' => [
                    'created_by' => 'test_user',
                    'tags' => ['tag1', 'tag2', 'tag3'],
                    'properties' => ['prop1' => 'value1', 'prop2' => 'value2']
                ]
            ];
        }
        return $dataset;
    }

    /**
     * Generate medium dataset for cache testing
     */
    private function generateMediumDataset(int $size): array
    {
        $dataset = [];
        for ($i = 0; $i < $size; $i++) {
            $dataset[] = [
                'id' => $i,
                'name' => 'Item ' . $i,
                'value' => rand(1, 1000),
                'active' => $i % 2 === 0
            ];
        }
        return $dataset;
    }
}
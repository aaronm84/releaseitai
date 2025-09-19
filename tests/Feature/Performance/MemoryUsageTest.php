<?php

namespace Tests\Feature\Performance;

use App\Models\Workstream;
use App\Models\Release;
use App\Models\User;
use App\Models\Communication;
use App\Models\CommunicationParticipant;
use App\Models\ChecklistItemAssignment;
use App\Models\StakeholderRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Tests\TestCase;

class MemoryUsageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Reset memory tracking
        gc_collect_cycles();
    }

    protected function getMemoryUsage(): int
    {
        return memory_get_usage();
    }

    protected function getPeakMemoryUsage(): int
    {
        return memory_get_peak_usage();
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        return number_format($bytes / pow(1024, $power), 2, '.', ',') . ' ' . $units[$power];
    }

    protected function assertMemoryUsageUnder(int $maxBytes, callable $operation, string $message = ''): void
    {
        $initialMemory = $this->getMemoryUsage();

        $result = $operation();

        $finalMemory = $this->getMemoryUsage();
        $memoryUsed = $finalMemory - $initialMemory;

        $this->assertLessThan(
            $maxBytes,
            $memoryUsed,
            $message . " Expected memory usage under {$this->formatBytes($maxBytes)}, but used {$this->formatBytes($memoryUsed)}"
        );

        // Clean up to prevent memory leaks in subsequent tests
        unset($result);
        gc_collect_cycles();
    }

    protected function assertPeakMemoryUnder(int $maxBytes, callable $operation, string $message = ''): void
    {
        gc_collect_cycles();
        $initialPeak = $this->getPeakMemoryUsage();

        $result = $operation();

        $finalPeak = $this->getPeakMemoryUsage();
        $peakIncrease = $finalPeak - $initialPeak;

        $this->assertLessThan(
            $maxBytes,
            $peakIncrease,
            $message . " Expected peak memory increase under {$this->formatBytes($maxBytes)}, but peak increased by {$this->formatBytes($peakIncrease)}"
        );

        unset($result);
        gc_collect_cycles();
    }

    /** @test */
    public function loading_1000_workstreams_should_not_exceed_50mb_memory()
    {
        // Given: 1000 workstreams with relationships
        $rootWorkstreams = Workstream::factory()->count(100)->create(['parent_workstream_id' => null]);

        foreach ($rootWorkstreams as $root) {
            Workstream::factory()->count(9)->create(['parent_workstream_id' => $root->id]);
        }

        // When & Then: Loading all workstreams should be memory efficient
        $this->assertMemoryUsageUnder(50 * 1024 * 1024, function () {
            // This will FAIL initially if not using chunking or lazy loading
            $workstreams = Workstream::with('owner')->get();
            $this->assertCount(1000, $workstreams);
            return $workstreams;
        }, 'Loading 1000 workstreams');

        // Test chunked loading for better memory efficiency
        $this->assertMemoryUsageUnder(10 * 1024 * 1024, function () {
            $processedCount = 0;
            Workstream::with('owner')->chunk(100, function ($workstreams) use (&$processedCount) {
                $processedCount += $workstreams->count();
                // Process each chunk without storing them all in memory
            });
            $this->assertEquals(1000, $processedCount);
            return $processedCount;
        }, 'Chunked loading of 1000 workstreams should use minimal memory');
    }

    /** @test */
    public function processing_large_communication_dataset_should_manage_memory_efficiently()
    {
        // Given: 2000 communications with participants
        $releases = Release::factory()->count(20)->create();
        $users = User::factory()->count(50)->create();

        foreach ($releases as $release) {
            $communications = Communication::factory()->count(100)->create([
                'release_id' => $release->id
            ]);

            foreach ($communications as $communication) {
                foreach ($users->random(3) as $user) {
                    CommunicationParticipant::factory()->create([
                        'communication_id' => $communication->id,
                        'user_id' => $user->id
                    ]);
                }
            }
        }

        // When & Then: Processing all communications should not use excessive memory
        $this->assertPeakMemoryUnder(75 * 1024 * 1024, function () {
            // This will FAIL initially without proper memory management
            $allCommunications = Communication::with(['participants.user', 'release'])->get();

            // Simulate processing each communication
            $processedData = $allCommunications->map(function ($communication) {
                return [
                    'id' => $communication->id,
                    'subject' => $communication->subject,
                    'participant_count' => $communication->participants->count(),
                    'release_name' => $communication->release->name
                ];
            });

            $this->assertCount(2000, $processedData);
            return $processedData;
        }, 'Processing 2000 communications with relationships');

        // Test streaming approach for better memory efficiency
        $this->assertMemoryUsageUnder(20 * 1024 * 1024, function () {
            $processedCount = 0;

            // Use lazy collection for streaming
            Communication::with(['participants.user', 'release'])
                ->lazy()
                ->chunk(50)
                ->each(function ($communications) use (&$processedCount) {
                    foreach ($communications as $communication) {
                        // Process each communication individually
                        $processedData = [
                            'id' => $communication->id,
                            'participant_count' => $communication->participants->count()
                        ];
                        $processedCount++;
                    }
                });

            $this->assertEquals(2000, $processedCount);
            return $processedCount;
        }, 'Streaming communication processing should use minimal memory');
    }

    /** @test */
    public function hierarchy_traversal_should_not_cause_memory_leaks()
    {
        // Given: Deep hierarchy with potential for memory leaks
        $root = Workstream::factory()->create(['parent_workstream_id' => null]);
        $current = $root;

        // Create a 10-level deep hierarchy
        for ($i = 0; $i < 10; $i++) {
            $child = Workstream::factory()->create(['parent_workstream_id' => $current->id]);
            $current = $child;
        }

        // When & Then: Multiple hierarchy traversals should not accumulate memory
        $this->assertMemoryUsageUnder(5 * 1024 * 1024, function () use ($root) {
            $memoryUsages = [];

            // Perform 100 hierarchy traversals
            for ($i = 0; $i < 100; $i++) {
                $hierarchy = $root->buildHierarchyTree();
                $descendants = $root->getAllDescendants();
                $memoryUsages[] = memory_get_usage();

                // Explicitly unset to help garbage collection
                unset($hierarchy, $descendants);

                if ($i % 10 === 0) {
                    gc_collect_cycles();
                }
            }

            // Memory usage should not continuously increase
            $firstMemory = $memoryUsages[10]; // Skip initial allocations
            $lastMemory = end($memoryUsages);
            $memoryIncrease = $lastMemory - $firstMemory;

            $this->assertLessThan(
                2 * 1024 * 1024, // 2MB max increase
                $memoryIncrease,
                'Memory usage should not continuously increase during repeated hierarchy traversals'
            );

            return $memoryUsages;
        }, 'Repeated hierarchy traversals');
    }

    /** @test */
    public function large_result_set_processing_should_use_streaming()
    {
        // Given: Large dataset that could cause memory issues
        $workstreams = Workstream::factory()->count(50)->create();

        foreach ($workstreams as $workstream) {
            $releases = Release::factory()->count(40)->create(['workstream_id' => $workstream->id]);

            foreach ($releases as $release) {
                ChecklistItemAssignment::factory()->count(25)->create([
                    'release_id' => $release->id
                ]);
            }
        }
        // Total: 50,000 checklist items

        // When & Then: Processing large result sets should use streaming
        $this->assertMemoryUsageUnder(30 * 1024 * 1024, function () {
            $processedCount = 0;

            // This will FAIL initially without streaming/chunking
            ChecklistItemAssignment::with(['release.workstream'])
                ->lazy(1000) // Process 1000 at a time
                ->each(function ($item) use (&$processedCount) {
                    // Process individual item
                    $processedData = [
                        'id' => $item->id,
                        'workstream_name' => $item->release->workstream->name,
                        'status' => $item->status
                    ];
                    $processedCount++;

                    // Simulate some processing work
                    unset($processedData);
                });

            $this->assertEquals(50000, $processedCount);
            return $processedCount;
        }, 'Processing 50,000 checklist items with streaming');
    }

    /** @test */
    public function report_generation_should_manage_memory_for_large_datasets()
    {
        // Given: Complex data structure for reporting
        $rootWorkstreams = Workstream::factory()->count(10)->create(['parent_workstream_id' => null]);

        foreach ($rootWorkstreams as $root) {
            $children = Workstream::factory()->count(10)->create(['parent_workstream_id' => $root->id]);

            foreach ($children as $child) {
                $releases = Release::factory()->count(20)->create(['workstream_id' => $child->id]);

                foreach ($releases as $release) {
                    ChecklistItemAssignment::factory()->count(15)->create([
                        'release_id' => $release->id,
                        'status' => fake()->randomElement(['pending', 'in_progress', 'completed'])
                    ]);
                }
            }
        }
        // Total: 10 roots * 10 children * 20 releases * 15 tasks = 30,000 tasks

        // When & Then: Generating comprehensive reports should be memory efficient
        $this->assertPeakMemoryUnder(100 * 1024 * 1024, function () use ($rootWorkstreams) {
            $reports = [];

            foreach ($rootWorkstreams as $root) {
                // This will FAIL initially due to inefficient rollup calculation
                $rollupReport = $root->getRollupReport();

                // Process and store only essential data
                $reports[] = [
                    'workstream_id' => $rollupReport['workstream_id'],
                    'total_releases' => $rollupReport['summary']['total_releases'],
                    'total_tasks' => $rollupReport['summary']['total_tasks'],
                    'completion_percentage' => $rollupReport['summary']['completion_percentage']
                ];

                // Clear the large report object
                unset($rollupReport);

                if (count($reports) % 3 === 0) {
                    gc_collect_cycles();
                }
            }

            $this->assertCount(10, $reports);
            return $reports;
        }, 'Generating rollup reports for complex hierarchy');
    }

    /** @test */
    public function database_result_caching_should_prevent_memory_leaks()
    {
        // Given: Queries that might be repeated
        Workstream::factory()->count(200)->create();

        // When & Then: Repeated queries should not accumulate memory
        $this->assertMemoryUsageUnder(10 * 1024 * 1024, function () {
            $results = [];

            // Simulate repeated queries that might be cached
            for ($i = 0; $i < 50; $i++) {
                // Same query repeated - should benefit from query result caching
                $workstreams = Workstream::where('status', Workstream::STATUS_ACTIVE)->get();
                $results[] = $workstreams->count();

                // Clear reference to allow garbage collection
                unset($workstreams);

                if ($i % 10 === 0) {
                    gc_collect_cycles();
                }
            }

            return $results;
        }, 'Repeated queries with caching');
    }

    /** @test */
    public function bulk_operations_should_not_load_entire_dataset_into_memory()
    {
        // Given: Large dataset for bulk operations
        Workstream::factory()->count(5000)->create(['status' => Workstream::STATUS_DRAFT]);

        // When & Then: Bulk updates should be memory efficient
        $this->assertMemoryUsageUnder(15 * 1024 * 1024, function () {
            // This should use a single UPDATE query, not load all records
            $updatedCount = Workstream::where('status', Workstream::STATUS_DRAFT)
                                   ->update(['status' => Workstream::STATUS_ACTIVE]);

            $this->assertEquals(5000, $updatedCount);
            return $updatedCount;
        }, 'Bulk update of 5000 workstreams');

        // Test chunked updates for even better memory management
        Workstream::factory()->count(3000)->create(['status' => Workstream::STATUS_ACTIVE]);

        $this->assertMemoryUsageUnder(10 * 1024 * 1024, function () {
            $totalUpdated = 0;

            // Update in chunks to maintain low memory usage
            Workstream::where('status', Workstream::STATUS_ACTIVE)
                     ->chunkById(500, function ($workstreams) use (&$totalUpdated) {
                         $ids = $workstreams->pluck('id');
                         $updated = Workstream::whereIn('id', $ids)
                                            ->update(['status' => Workstream::STATUS_ON_HOLD]);
                         $totalUpdated += $updated;
                     });

            $this->assertEquals(3000, $totalUpdated);
            return $totalUpdated;
        }, 'Chunked bulk update of 3000 workstreams');
    }

    /** @test */
    public function concurrent_request_simulation_should_not_cause_memory_exhaustion()
    {
        // Given: Data that might be accessed concurrently
        $workstreams = Workstream::factory()->count(100)->create();
        $releases = Release::factory()->count(500)->create();

        // When & Then: Simulating concurrent access patterns
        $this->assertPeakMemoryUnder(60 * 1024 * 1024, function () use ($workstreams, $releases) {
            $results = [];

            // Simulate 20 concurrent requests doing different operations
            for ($i = 0; $i < 20; $i++) {
                // Simulate different types of requests
                switch ($i % 4) {
                    case 0:
                        // Workstream listing
                        $result = Workstream::with('owner')->paginate(20);
                        break;
                    case 1:
                        // Release search
                        $result = Release::with('workstream')->where('status', 'in_progress')->get();
                        break;
                    case 2:
                        // Hierarchy traversal
                        $rootWorkstream = $workstreams->where('parent_workstream_id', null)->first();
                        $result = $rootWorkstream ? $rootWorkstream->getAllDescendants() : collect();
                        break;
                    case 3:
                        // Aggregation query
                        $result = Release::selectRaw('status, COUNT(*) as count')->groupBy('status')->get();
                        break;
                }

                $results[] = $result;

                // Simulate request completion and cleanup
                if ($i % 5 === 0) {
                    // Cleanup every 5 requests
                    gc_collect_cycles();
                }
            }

            $this->assertCount(20, $results);
            return $results;
        }, 'Simulated concurrent request processing');
    }

    /** @test */
    public function resource_cleanup_should_be_automatic()
    {
        // Given: Operations that create temporary resources
        $workstreams = Workstream::factory()->count(100)->create();

        // When & Then: Resources should be cleaned up automatically
        $initialMemory = $this->getMemoryUsage();

        for ($i = 0; $i < 10; $i++) {
            // Create large temporary objects
            $largeDataSet = Workstream::with(['childWorkstreams', 'releases', 'permissions'])->get();

            // Process the data
            $processedData = $largeDataSet->map(function ($workstream) {
                return [
                    'id' => $workstream->id,
                    'name' => $workstream->name,
                    'children_count' => $workstream->childWorkstreams->count(),
                    'releases_count' => $workstream->releases->count()
                ];
            });

            // Explicitly unset large objects
            unset($largeDataSet, $processedData);
        }

        // Force garbage collection
        gc_collect_cycles();

        $finalMemory = $this->getMemoryUsage();
        $memoryIncrease = $finalMemory - $initialMemory;

        // Memory should not have increased significantly
        $this->assertLessThan(
            5 * 1024 * 1024, // 5MB
            $memoryIncrease,
            "Memory should be cleaned up after operations. Memory increased by {$this->formatBytes($memoryIncrease)}"
        );
    }
}
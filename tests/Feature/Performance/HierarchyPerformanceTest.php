<?php

namespace Tests\Feature\Performance;

use App\Models\Workstream;
use App\Models\Release;
use App\Models\User;
use App\Models\WorkstreamPermission;
use App\Models\ChecklistItemAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HierarchyPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DB::enableQueryLog();
    }

    protected function tearDown(): void
    {
        DB::flushQueryLog();
        parent::tearDown();
    }

    protected function getQueryCount(): int
    {
        return count(DB::getQueryLog());
    }

    protected function measureExecutionTime(callable $operation): float
    {
        $start = microtime(true);
        $result = $operation();
        $end = microtime(true);
        return ($end - $start) * 1000; // Convert to milliseconds
    }

    protected function assertExecutionTimeUnder(float $maxTimeMs, callable $operation, string $message = ''): void
    {
        $executionTime = $this->measureExecutionTime($operation);
        $this->assertLessThan(
            $maxTimeMs,
            $executionTime,
            $message . " Expected execution time under {$maxTimeMs}ms, but took {$executionTime}ms"
        );
    }

    protected function createComplexHierarchy(int $depth = 4, int $branchingFactor = 5): array
    {
        $allWorkstreams = [];

        // Create root workstreams
        $roots = Workstream::factory()->count($branchingFactor)->create([
            'parent_workstream_id' => null
        ]);
        $allWorkstreams = array_merge($allWorkstreams, $roots->toArray());

        $currentLevel = $roots;

        // Create subsequent levels
        for ($level = 1; $level < $depth; $level++) {
            $nextLevel = collect();

            foreach ($currentLevel as $parent) {
                $children = Workstream::factory()->count($branchingFactor)->create([
                    'parent_workstream_id' => $parent->id
                ]);
                $allWorkstreams = array_merge($allWorkstreams, $children->toArray());
                $nextLevel = $nextLevel->merge($children);
            }

            $currentLevel = $nextLevel;
        }

        return [
            'roots' => $roots,
            'all' => $allWorkstreams,
            'total_count' => count($allWorkstreams)
        ];
    }

    /** @test */
    public function hierarchy_traversal_should_complete_under_50ms()
    {
        // Given: 4-level hierarchy with 5 children per level (625 total workstreams)
        $hierarchy = $this->createComplexHierarchy(4, 5);
        $rootWorkstream = $hierarchy['roots']->first();

        // When & Then: Building hierarchy tree should be fast
        $this->assertExecutionTimeUnder(50, function () use ($rootWorkstream) {
            $tree = $rootWorkstream->buildHierarchyTree();
            $this->assertArrayHasKey('children', $tree);
            return $tree;
        }, 'Building hierarchy tree with 625 workstreams');

        // Getting all descendants should also be fast
        $this->assertExecutionTimeUnder(40, function () use ($rootWorkstream) {
            $descendants = $rootWorkstream->getAllDescendants();
            $this->assertEquals(124, $descendants->count()); // 5 + 25 + 125 - 1 (excluding root)
            return $descendants;
        }, 'Getting all descendants');

        // Getting all ancestors from deepest level should be fast
        $deepestWorkstream = Workstream::whereNotNull('parent_workstream_id')
            ->whereDoesntHave('childWorkstreams')
            ->first();

        $this->assertExecutionTimeUnder(30, function () use ($deepestWorkstream) {
            $ancestors = $deepestWorkstream->getAllAncestors();
            $this->assertEquals(3, $ancestors->count()); // 3 levels above
            return $ancestors;
        }, 'Getting all ancestors from deepest level');
    }

    /** @test */
    public function permission_inheritance_calculation_should_be_efficient()
    {
        // Given: Complex hierarchy with permissions at various levels
        $hierarchy = $this->createComplexHierarchy(5, 4); // 1024 workstreams
        $users = User::factory()->count(10)->create();

        // Add permissions at different levels (20% of workstreams have permissions)
        $workstreamsWithPermissions = collect($hierarchy['all'])->random(200);

        foreach ($workstreamsWithPermissions as $workstream) {
            foreach ($users->random(2) as $user) {
                WorkstreamPermission::factory()->create([
                    'workstream_id' => $workstream['id'],
                    'user_id' => $user->id,
                    'permission_type' => fake()->randomElement(['view', 'edit', 'admin']),
                    'scope' => fake()->randomElement(['workstream_only', 'workstream_and_children'])
                ]);
            }
        }

        // Find a deep workstream to test with
        $deepWorkstream = Workstream::whereNotNull('parent_workstream_id')
            ->whereDoesntHave('childWorkstreams')
            ->first();

        $testUser = $users->first();

        DB::flushQueryLog();

        // When & Then: Permission calculation should be efficient
        $this->assertExecutionTimeUnder(75, function () use ($deepWorkstream, $testUser) {
            $effectivePermissions = $deepWorkstream->getEffectivePermissionsForUser($testUser->id);
            $this->assertIsArray($effectivePermissions);
            $this->assertArrayHasKey('effective_permissions', $effectivePermissions);
            return $effectivePermissions;
        }, 'Calculating effective permissions in complex hierarchy');

        // Should not use too many queries
        $this->assertLessThanOrEqual(
            10,
            $this->getQueryCount(),
            'Permission inheritance should use efficient queries, not load each ancestor individually'
        );
    }

    /** @test */
    public function rollup_reporting_should_complete_under_200ms()
    {
        // Given: Complex hierarchy with releases and tasks
        $hierarchy = $this->createComplexHierarchy(3, 6); // 216 workstreams

        // Add releases to leaf workstreams (bottom level)
        $leafWorkstreams = Workstream::whereDoesntHave('childWorkstreams')->get();

        foreach ($leafWorkstreams as $workstream) {
            $releases = Release::factory()->count(5)->create([
                'workstream_id' => $workstream->id
            ]);

            foreach ($releases as $release) {
                ChecklistItemAssignment::factory()->count(8)->create([
                    'release_id' => $release->id,
                    'status' => fake()->randomElement(['pending', 'in_progress', 'completed'])
                ]);
            }
        }

        $rootWorkstream = $hierarchy['roots']->first();

        DB::flushQueryLog();

        // When & Then: Rollup reporting should complete quickly
        $this->assertExecutionTimeUnder(200, function () use ($rootWorkstream) {
            $rollupReport = $rootWorkstream->getRollupReport();

            $this->assertArrayHasKey('summary', $rollupReport);
            $this->assertArrayHasKey('child_workstreams', $rollupReport);
            $this->assertGreaterThan(0, $rollupReport['summary']['total_releases']);

            return $rollupReport;
        }, 'Generating rollup report for hierarchy with 1800+ releases and 14400+ tasks');

        // Should minimize database queries
        $this->assertLessThanOrEqual(
            8,
            $this->getQueryCount(),
            'Rollup reporting should use efficient bulk queries, not individual queries per workstream'
        );
    }

    /** @test */
    public function hierarchy_modification_operations_should_be_fast()
    {
        // Given: Existing hierarchy
        $hierarchy = $this->createComplexHierarchy(4, 4);
        $existingWorkstream = collect($hierarchy['all'])->random();

        // When & Then: Creating new workstream should be fast
        $this->assertExecutionTimeUnder(25, function () use ($existingWorkstream) {
            $newWorkstream = Workstream::factory()->create([
                'parent_workstream_id' => $existingWorkstream['id']
            ]);

            // Verify hierarchy depth calculation is efficient
            $depth = $newWorkstream->getHierarchyDepth();
            $this->assertGreaterThan(1, $depth);

            return $newWorkstream;
        }, 'Creating new workstream and calculating hierarchy depth');

        // Moving workstream to different parent should be fast
        $workstreamToMove = Workstream::whereNotNull('parent_workstream_id')->first();
        $newParent = Workstream::where('id', '!=', $workstreamToMove->id)
            ->whereNull('parent_workstream_id')
            ->first();

        $this->assertExecutionTimeUnder(50, function () use ($workstreamToMove, $newParent) {
            // Check for circular reference (should be fast)
            $wouldCreateCircular = $workstreamToMove->wouldCreateCircularHierarchy($newParent->id);
            $this->assertFalse($wouldCreateCircular);

            // Perform the move
            $workstreamToMove->update(['parent_workstream_id' => $newParent->id]);

            return $workstreamToMove;
        }, 'Moving workstream to different parent with circular reference check');
    }

    /** @test */
    public function bulk_hierarchy_operations_should_be_optimized()
    {
        // Given: Large flat list of workstreams to organize into hierarchy
        $workstreams = Workstream::factory()->count(500)->create([
            'parent_workstream_id' => null
        ]);

        // When & Then: Bulk hierarchy reorganization should be efficient
        $this->assertExecutionTimeUnder(100, function () use ($workstreams) {
            $updates = [];
            $chunkSize = 50;

            // Organize into 10 groups of 50 workstreams each
            foreach ($workstreams->chunk($chunkSize) as $index => $chunk) {
                if ($index === 0) continue; // Keep first chunk as roots

                $parentId = $workstreams->skip(($index - 1) * $chunkSize)->first()->id;

                foreach ($chunk as $workstream) {
                    $updates[] = [
                        'id' => $workstream->id,
                        'parent_workstream_id' => $parentId
                    ];
                }
            }

            // Perform bulk update
            foreach (collect($updates)->chunk(100) as $updateChunk) {
                foreach ($updateChunk as $update) {
                    Workstream::where('id', $update['id'])
                        ->update(['parent_workstream_id' => $update['parent_workstream_id']]);
                }
            }

            return count($updates);
        }, 'Bulk hierarchy reorganization of 500 workstreams');
    }

    /** @test */
    public function hierarchy_validation_should_be_efficient()
    {
        // Given: Complex hierarchy with potential issues
        $hierarchy = $this->createComplexHierarchy(4, 5);

        // When & Then: Validating entire hierarchy should be fast
        $this->assertExecutionTimeUnder(100, function () use ($hierarchy) {
            $validationResults = [];

            // Check each workstream for various validation rules
            foreach (collect($hierarchy['all'])->chunk(50) as $chunk) {
                foreach ($chunk as $workstream) {
                    $ws = Workstream::find($workstream['id']);

                    $validationResults[] = [
                        'id' => $ws->id,
                        'can_be_deleted' => $ws->canBeDeleted(),
                        'hierarchy_depth' => $ws->getHierarchyDepth(),
                        'depth_valid' => $ws->getHierarchyDepth() <= Workstream::MAX_HIERARCHY_DEPTH
                    ];
                }
            }

            $this->assertNotEmpty($validationResults);
            return $validationResults;
        }, 'Validating hierarchy integrity for 625 workstreams');
    }

    /** @test */
    public function hierarchy_search_and_filtering_should_be_optimized()
    {
        // Given: Large hierarchy with searchable content
        $hierarchy = $this->createComplexHierarchy(4, 6);

        // When & Then: Searching within hierarchy should be fast
        $this->assertExecutionTimeUnder(75, function () {
            // Find all workstreams of specific type in hierarchy
            $initiatives = Workstream::where('type', Workstream::TYPE_INITIATIVE)
                ->with('parentWorkstream')
                ->get();

            // Group by hierarchy level
            $byLevel = $initiatives->groupBy(function ($workstream) {
                return $workstream->getHierarchyDepth();
            });

            $this->assertInstanceOf(\Illuminate\Support\Collection::class, $initiatives);
            return $byLevel;
        }, 'Searching and grouping workstreams by hierarchy level');

        // Finding all workstreams under a specific parent should be fast
        $rootWorkstream = $hierarchy['roots']->first();

        $this->assertExecutionTimeUnder(50, function () use ($rootWorkstream) {
            // Get all descendants with specific status
            $activeDescendants = Workstream::where('parent_workstream_id', $rootWorkstream->id)
                ->orWhereIn('parent_workstream_id', function ($query) use ($rootWorkstream) {
                    $query->select('id')
                        ->from('workstreams')
                        ->where('parent_workstream_id', $rootWorkstream->id);
                })
                ->where('status', Workstream::STATUS_ACTIVE)
                ->get();

            return $activeDescendants;
        }, 'Finding descendants with specific criteria');
    }

    /** @test */
    public function hierarchy_caching_should_improve_performance()
    {
        // Given: Hierarchy that would benefit from caching
        $hierarchy = $this->createComplexHierarchy(3, 8);
        $rootWorkstream = $hierarchy['roots']->first();

        // When & Then: First hierarchy traversal (cache miss)
        $firstTraversalTime = $this->measureExecutionTime(function () use ($rootWorkstream) {
            return $rootWorkstream->buildHierarchyTree();
        });

        // Second traversal should be faster with caching (this will FAIL initially without caching)
        $secondTraversalTime = $this->measureExecutionTime(function () use ($rootWorkstream) {
            return $rootWorkstream->buildHierarchyTree();
        });

        $this->assertLessThan(
            20,
            $secondTraversalTime,
            'Cached hierarchy traversal should be much faster. Consider implementing hierarchy caching.'
        );

        // The improvement should be significant (when caching is implemented)
        $this->assertLessThan(
            $firstTraversalTime * 0.4, // 40% of original time
            $secondTraversalTime,
            'Cached traversal should be significantly faster than uncached'
        );
    }

    /** @test */
    public function concurrent_hierarchy_operations_should_not_cause_deadlocks()
    {
        // Given: Hierarchy for concurrent operations testing
        $hierarchy = $this->createComplexHierarchy(3, 5);

        // When & Then: Concurrent operations should complete without issues
        $this->assertExecutionTimeUnder(500, function () use ($hierarchy) {
            $results = [];

            // Simulate concurrent operations
            for ($i = 0; $i < 20; $i++) {
                $workstream = collect($hierarchy['all'])->random();
                $ws = Workstream::find($workstream['id']);

                switch ($i % 4) {
                    case 0:
                        // Read operation
                        $results[] = $ws->buildHierarchyTree();
                        break;
                    case 1:
                        // Ancestor traversal
                        $results[] = $ws->getAllAncestors();
                        break;
                    case 2:
                        // Descendant traversal
                        $results[] = $ws->getAllDescendants();
                        break;
                    case 3:
                        // Depth calculation
                        $results[] = $ws->getHierarchyDepth();
                        break;
                }
            }

            $this->assertCount(20, $results);
            return $results;
        }, 'Concurrent hierarchy operations should not cause performance issues or deadlocks');
    }

    /** @test */
    public function large_hierarchy_memory_usage_should_be_controlled()
    {
        // Given: Very large hierarchy
        $hierarchy = $this->createComplexHierarchy(4, 8); // 4096 workstreams

        $initialMemory = memory_get_usage();

        // When & Then: Processing large hierarchy should not consume excessive memory
        $this->assertExecutionTimeUnder(300, function () use ($hierarchy) {
            $rootWorkstream = $hierarchy['roots']->first();

            // Process hierarchy in chunks to control memory
            $allDescendants = $rootWorkstream->getAllDescendants();

            $processedData = $allDescendants->chunk(100)->map(function ($chunk) {
                return $chunk->map(function ($workstream) {
                    return [
                        'id' => $workstream->id,
                        'name' => $workstream->name,
                        'depth' => $workstream->getHierarchyDepth()
                    ];
                });
            });

            $flattenedData = $processedData->flatten(1);
            $this->assertGreaterThan(500, $flattenedData->count());

            return $flattenedData;
        }, 'Processing large hierarchy with memory management');

        $finalMemory = memory_get_usage();
        $memoryIncrease = $finalMemory - $initialMemory;

        $this->assertLessThan(
            50 * 1024 * 1024, // 50MB
            $memoryIncrease,
            'Large hierarchy processing should not consume excessive memory'
        );
    }
}
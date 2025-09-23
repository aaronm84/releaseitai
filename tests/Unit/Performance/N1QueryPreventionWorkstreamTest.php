<?php

namespace Tests\Unit\Performance;

use App\Models\Workstream;
use App\Models\User;
use App\Models\Release;
use Illuminate\Support\Facades\DB;

/**
 * Tests N+1 query prevention for workstream hierarchy operations.
 * Validates that workstream hierarchy operations don't trigger N+1 queries and use proper eager loading.
 */
class N1QueryPreventionWorkstreamTest extends BasePerformanceTest
{
    private array $hierarchyData;

    protected function setUp(): void
    {
        parent::setUp();

        // Create simple test data directly using Eloquent
        $this->hierarchyData = [
            'roots' => collect([
                Workstream::factory()->create([
                    'name' => 'Root Workstream',
                    'type' => 'product_line',
                    'status' => 'active',
                    'owner_id' => $this->testUser->id,
                    'hierarchy_depth' => 1,
                ])
            ]),
            'all' => [],
            'total_count' => 0
        ];

        // Create children for the root
        $root = $this->hierarchyData['roots']->first();
        for ($i = 0; $i < 3; $i++) {
            Workstream::factory()->create([
                'name' => "Child Workstream {$i}",
                'type' => 'initiative',
                'status' => 'active',
                'owner_id' => $this->testUser->id,
                'parent_workstream_id' => $root->id,
                'hierarchy_depth' => 2,
            ]);
        }
    }

    /**
     * Test that loading workstream with all children doesn't trigger N+1 queries.
     *
     * Given: A workstream hierarchy with multiple levels
     * When: Loading a workstream with all its children
     * Then: Should use single query with proper eager loading, not N+1 queries
     */
    public function test_loading_workstream_with_children_prevents_n1_queries(): void
    {
        $rootWorkstream = $this->hierarchyData['roots']->first();

        $metrics = $this->simulateN1Scenario(function () use ($rootWorkstream) {
            // Load workstream with children using optimized eager loading
            $workstreamWithChildren = Workstream::withCompleteHierarchy()
                ->find($rootWorkstream->id);

            // Access children - should not trigger additional queries
            $children = $workstreamWithChildren->childWorkstreams;

            // Access each child's properties - should not trigger additional queries
            foreach ($children as $child) {
                $name = $child->name;
                $type = $child->type;
                $status = $child->status;
                // Access owner without triggering N+1
                $owner = $child->owner;
                if ($owner) {
                    $ownerName = $owner->name;
                }
            }

            return [
                'workstream' => $workstreamWithChildren,
                'children_count' => $children->count()
            ];
        });

        // Should only execute 2-3 queries (optimized bulk loading)
        $this->assertNoN1Queries($metrics, 3);

        // Verify we got results
        $this->assertGreaterThan(0, $metrics['result']['children_count']);
    }

    /**
     * Test that loading workstream with full hierarchy path doesn't trigger N+1 queries.
     *
     * Given: A deep workstream hierarchy
     * When: Loading a workstream with its full hierarchy path (ancestors)
     * Then: Should use optimized queries, not N+1 queries
     */
    public function test_loading_workstream_hierarchy_path_prevents_n1_queries(): void
    {
        // Get a deep workstream (level 4)
        $deepWorkstream = Workstream::where('hierarchy_depth', 4)->first();

        $metrics = $this->simulateN1Scenario(function () use ($deepWorkstream) {
            // Load ancestors using optimized method
            $ancestors = $deepWorkstream->getAllAncestors();

            // Access each ancestor's properties - should not trigger additional queries
            foreach ($ancestors as $ancestor) {
                $name = $ancestor->name;
                $type = $ancestor->type;
                $depth = $ancestor->hierarchy_depth;
            }

            return [
                'workstream' => $deepWorkstream,
                'ancestors_count' => $ancestors->count()
            ];
        });

        // Should use optimized queries (max 5 queries for 4-level hierarchy)
        $this->assertNoN1Queries($metrics, 5);

        // Verify we got the expected number of ancestors
        $this->assertEquals(3, $metrics['result']['ancestors_count']);
    }

    /**
     * Test that loading multiple workstreams with their children doesn't trigger N+1 queries.
     *
     * Given: Multiple workstreams each with children
     * When: Loading workstreams with their children in batch
     * Then: Should use proper eager loading, not separate queries for each workstream's children
     */
    public function test_loading_multiple_workstreams_with_children_prevents_n1_queries(): void
    {
        $metrics = $this->simulateN1Scenario(function () {
            // Load multiple workstreams with children using optimized eager loading
            $workstreamsWithChildren = Workstream::withCompleteHierarchy()
                ->where('hierarchy_depth', 2)
                ->limit(20)
                ->get();

            $totalChildren = 0;
            // Access children for each workstream - should not trigger additional queries
            foreach ($workstreamsWithChildren as $workstream) {
                $children = $workstream->childWorkstreams;
                $totalChildren += $children->count();

                // Access child properties including owners
                foreach ($children as $child) {
                    $name = $child->name;
                    $type = $child->type;
                    $owner = $child->owner;
                    if ($owner) {
                        $ownerName = $owner->name;
                    }
                }
            }

            return [
                'workstreams_count' => $workstreamsWithChildren->count(),
                'total_children' => $totalChildren
            ];
        });

        // Should execute minimal queries with optimized bulk loading
        $this->assertNoN1Queries($metrics, 5);

        // Verify we got results
        $this->assertGreaterThan(0, $metrics['result']['workstreams_count']);
        $this->assertGreaterThan(0, $metrics['result']['total_children']);
    }

    /**
     * Test that loading workstreams with their owners doesn't trigger N+1 queries.
     *
     * Given: Multiple workstreams with different owners
     * When: Loading workstreams with their owner information
     * Then: Should use proper eager loading for owners, not N+1 queries
     */
    public function test_loading_workstreams_with_owners_prevents_n1_queries(): void
    {
        $metrics = $this->simulateN1Scenario(function () {
            // Load workstreams with owners using optimized eager loading
            $workstreamsWithOwners = Workstream::with(['owner:id,name,email'])
                ->where('hierarchy_depth', '<=', 3)
                ->limit(25)
                ->get();

            // Access owner information - should not trigger additional queries
            foreach ($workstreamsWithOwners as $workstream) {
                $owner = $workstream->owner;
                if ($owner) {
                    $ownerName = $owner->name;
                    $ownerEmail = $owner->email;
                }
            }

            return [
                'workstreams_count' => $workstreamsWithOwners->count()
            ];
        });

        // Should only execute 2-3 queries (optimized bulk loading)
        $this->assertNoN1Queries($metrics, 3);

        // Verify we got results
        $this->assertGreaterThan(0, $metrics['result']['workstreams_count']);
    }

    /**
     * Test that building complete hierarchy trees doesn't trigger N+1 queries.
     *
     * Given: Root workstreams with deep hierarchies
     * When: Building complete hierarchy trees
     * Then: Should use optimized recursive queries, not N+1 queries
     */
    public function test_building_hierarchy_trees_prevents_n1_queries(): void
    {
        $rootWorkstream = $this->hierarchyData['roots']->first();

        $metrics = $this->simulateN1Scenario(function () use ($rootWorkstream) {
            // Build complete hierarchy tree using optimized method
            $hierarchyTree = $rootWorkstream->buildHierarchyTree();

            return [
                'tree_structure' => $hierarchyTree,
                'root_children_count' => count($hierarchyTree['children'] ?? [])
            ];
        });

        // Should use optimized queries (max 10 for complex hierarchy tree)
        $this->assertNoN1Queries($metrics, 10);

        // Verify tree structure
        $this->assertIsArray($metrics['result']['tree_structure']);
        $this->assertArrayHasKey('id', $metrics['result']['tree_structure']);
        $this->assertArrayHasKey('children', $metrics['result']['tree_structure']);
    }

    /**
     * Test that bulk descendant operations don't trigger N+1 queries.
     *
     * Given: Root workstreams with multiple descendant levels
     * When: Getting all descendants for multiple root workstreams
     * Then: Should use optimized bulk queries, not N+1 queries
     */
    public function test_bulk_descendant_operations_prevent_n1_queries(): void
    {
        $rootWorkstreams = $this->hierarchyData['roots']->take(3);

        $metrics = $this->simulateN1Scenario(function () use ($rootWorkstreams) {
            $allDescendants = [];

            foreach ($rootWorkstreams as $root) {
                // Get descendants using optimized method
                $descendants = $root->getAllDescendants();
                $allDescendants[] = [
                    'root_id' => $root->id,
                    'descendants_count' => $descendants->count(),
                    'descendants' => $descendants
                ];
            }

            return [
                'roots_processed' => count($allDescendants),
                'total_descendants' => array_sum(array_column($allDescendants, 'descendants_count'))
            ];
        });

        // Should use optimized queries (max 15 for 3 root workstreams with optimized descendant loading)
        $this->assertNoN1Queries($metrics, 15);

        // Verify we got descendants
        $this->assertEquals(3, $metrics['result']['roots_processed']);
        $this->assertGreaterThan(0, $metrics['result']['total_descendants']);
    }

    /**
     * Test that workstream permission checking doesn't trigger N+1 queries.
     *
     * Given: Workstreams with inherited permissions
     * When: Checking permissions for multiple workstreams
     * Then: Should use optimized permission queries, not N+1 queries
     */
    public function test_workstream_permission_checking_prevents_n1_queries(): void
    {
        $workstreamIds = Workstream::where('hierarchy_depth', 3)->limit(10)->pluck('id')->toArray();

        $metrics = $this->simulateN1Scenario(function () use ($workstreamIds) {
            // Load workstreams with permission context using optimized eager loading
            $workstreams = Workstream::with([
                    'permissions' => function ($query) {
                        $query->where('user_id', $this->testUser->id);
                    },
                    'parentWorkstream.permissions' => function ($query) {
                        $query->where('user_id', $this->testUser->id)->where('scope', 'workstream_and_children');
                    }
                ])
                ->whereIn('id', $workstreamIds)
                ->get();

            $permissionResults = [];
            foreach ($workstreams as $workstream) {
                // Check effective permissions using optimized method
                $permissions = $workstream->getEffectivePermissionsForUser($this->testUser->id);
                $permissionResults[] = [
                    'workstream_id' => $workstream->id,
                    'permissions' => $permissions
                ];
            }

            return [
                'workstreams_checked' => count($permissionResults),
                'permissions_found' => count(array_filter($permissionResults, fn($result) => !empty($result['permissions']['effective_permissions'])))
            ];
        });

        // Should use optimized permission checking with bulk loading
        $this->assertNoN1Queries($metrics, 15);

        // Verify permissions were checked
        $this->assertEquals(10, $metrics['result']['workstreams_checked']);
    }

    /**
     * Test that loading workstreams with releases doesn't trigger N+1 queries.
     *
     * Given: Workstreams with multiple releases
     * When: Loading workstreams with their releases
     * Then: Should use proper eager loading for releases, not N+1 queries
     */
    public function test_loading_workstreams_with_releases_prevents_n1_queries(): void
    {
        // Create some releases for workstreams
        $workstreams = Workstream::where('hierarchy_depth', 2)->limit(5)->get();
        foreach ($workstreams as $workstream) {
            Release::factory(3)->create(['workstream_id' => $workstream->id]);
        }

        $metrics = $this->simulateN1Scenario(function () {
            // Load workstreams with releases using eager loading
            $workstreamsWithReleases = Workstream::with(['releases'])
                ->where('hierarchy_depth', 2)
                ->limit(5)
                ->get();

            $totalReleases = 0;
            // Access releases - should not trigger additional queries
            foreach ($workstreamsWithReleases as $workstream) {
                $releases = $workstream->releases;
                $totalReleases += $releases->count();

                // Access release properties
                foreach ($releases as $release) {
                    $name = $release->name;
                    $status = $release->status;
                }
            }

            return [
                'workstreams_count' => $workstreamsWithReleases->count(),
                'total_releases' => $totalReleases
            ];
        });

        // Should only execute 2 queries (main query + eager loaded releases)
        $this->assertNoN1Queries($metrics, 2);

        // Verify we got results
        $this->assertEquals(5, $metrics['result']['workstreams_count']);
        $this->assertEquals(15, $metrics['result']['total_releases']); // 5 workstreams × 3 releases
    }

    /**
     * Test that complex hierarchy reporting doesn't trigger N+1 queries.
     *
     * Given: Workstreams with complex hierarchy relationships
     * When: Generating rollup reports for multiple workstreams
     * Then: Should use optimized rollup queries, not N+1 queries
     */
    public function test_complex_hierarchy_reporting_prevents_n1_queries(): void
    {
        $rootWorkstreams = $this->hierarchyData['roots']->take(2);

        $metrics = $this->simulateN1Scenario(function () use ($rootWorkstreams) {
            $rollupReports = [];

            foreach ($rootWorkstreams as $root) {
                // Generate rollup report using optimized method
                $rollupReport = $root->getRollupReport();
                $rollupReports[] = [
                    'root_id' => $root->id,
                    'report' => $rollupReport
                ];
            }

            return [
                'reports_generated' => count($rollupReports),
                'sample_report' => $rollupReports[0]['report'] ?? []
            ];
        });

        // Should use optimized rollup queries (max 20 for 2 complex reports)
        $this->assertNoN1Queries($metrics, 20);

        // Verify reports were generated
        $this->assertEquals(2, $metrics['result']['reports_generated']);
        $this->assertIsArray($metrics['result']['sample_report']);
    }

    /**
     * Test that circular hierarchy detection doesn't trigger N+1 queries.
     *
     * Given: Multiple workstreams that need circular hierarchy validation
     * When: Checking for circular relationships in batch
     * Then: Should use optimized validation queries, not N+1 queries
     */
    public function test_circular_hierarchy_detection_prevents_n1_queries(): void
    {
        $workstreams = Workstream::where('hierarchy_depth', 3)->limit(5)->get();
        $potentialParents = Workstream::where('hierarchy_depth', 2)->limit(5)->get();

        $metrics = $this->simulateN1Scenario(function () use ($workstreams, $potentialParents) {
            $validationResults = [];

            foreach ($workstreams as $workstream) {
                foreach ($potentialParents as $potentialParent) {
                    // Check for circular hierarchy using optimized method
                    $wouldCreateCircular = $workstream->wouldCreateCircularHierarchy($potentialParent->id);
                    $validationResults[] = [
                        'workstream_id' => $workstream->id,
                        'potential_parent_id' => $potentialParent->id,
                        'would_create_circular' => $wouldCreateCircular
                    ];
                }
            }

            return [
                'validations_performed' => count($validationResults),
                'circular_detected' => count(array_filter($validationResults, fn($result) => $result['would_create_circular']))
            ];
        });

        // Should use optimized validation (max 30 queries for 25 validations)
        $this->assertNoN1Queries($metrics, 30);

        // Verify validations were performed
        $this->assertEquals(25, $metrics['result']['validations_performed']); // 5 × 5 combinations
    }

    /**
     * Test that workstream search with hierarchy context doesn't trigger N+1 queries.
     *
     * Given: Workstreams in a hierarchy with search requirements
     * When: Searching workstreams and loading their hierarchy context
     * Then: Should use optimized search with proper eager loading, not N+1 queries
     */
    public function test_workstream_search_with_hierarchy_context_prevents_n1_queries(): void
    {
        $metrics = $this->simulateN1Scenario(function () {
            // Search workstreams with hierarchy context using optimized method
            $searchResults = Workstream::searchWithHierarchyContext('Child', [
                'status' => 'active'
            ])->take(15);

            $contextData = [];
            // Access hierarchy context - should not trigger additional queries
            foreach ($searchResults as $workstream) {
                $parent = $workstream->parentWorkstream;
                $children = $workstream->childWorkstreams;
                $owner = $workstream->owner;

                $contextData[] = [
                    'workstream_id' => $workstream->id,
                    'has_parent' => $parent !== null,
                    'children_count' => $children->count(),
                    'has_owner' => $owner !== null
                ];
            }

            return [
                'results_count' => $searchResults->count(),
                'context_data' => $contextData
            ];
        });

        // Should execute optimized queries with the new search method
        $this->assertNoN1Queries($metrics, 5);

        // Verify search results
        $this->assertGreaterThan(0, $metrics['result']['results_count']);
        $this->assertCount($metrics['result']['results_count'], $metrics['result']['context_data']);
    }
}
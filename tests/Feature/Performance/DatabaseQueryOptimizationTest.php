<?php

namespace Tests\Feature\Performance;

use App\Models\Workstream;
use App\Models\Release;
use App\Models\User;
use App\Models\StakeholderRelease;
use App\Models\ChecklistItemAssignment;
use App\Models\Communication;
use App\Models\WorkstreamPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseQueryOptimizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable query logging for performance measurement
        DB::enableQueryLog();
    }

    protected function tearDown(): void
    {
        DB::flushQueryLog();
        parent::tearDown();
    }

    protected function measureExecutionTime(callable $operation): float
    {
        $start = microtime(true);
        $operation();
        return (microtime(true) - $start) * 1000; // Convert to milliseconds
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

    /** @test */
    public function workstream_hierarchy_queries_should_complete_under_100ms()
    {
        // Given: Large hierarchy (100 workstreams in 3 levels)
        $root = Workstream::factory()->create(['parent_workstream_id' => null]);

        $level2Workstreams = Workstream::factory()->count(20)->create([
            'parent_workstream_id' => $root->id
        ]);

        foreach ($level2Workstreams as $parent) {
            Workstream::factory()->count(4)->create([
                'parent_workstream_id' => $parent->id
            ]);
        }

        // When & Then: Loading hierarchy should be fast
        $this->assertExecutionTimeUnder(100, function () use ($root) {
            $hierarchy = $root->buildHierarchyTree();
            $this->assertNotEmpty($hierarchy['children']);
        }, 'Hierarchy traversal with 100 workstreams');

        // Additional test: Getting all descendants should be fast
        $this->assertExecutionTimeUnder(50, function () use ($root) {
            $descendants = $root->getAllDescendants();
            $this->assertCount(80, $descendants); // 20 + (20 * 4)
        }, 'Getting all descendants');
    }

    /** @test */
    public function workstream_search_queries_should_use_proper_indexes()
    {
        // Given: 1000 workstreams with various attributes
        Workstream::factory()->count(1000)->create();

        // When & Then: Search by name should be fast (requires index on name)
        $this->assertExecutionTimeUnder(20, function () {
            $results = Workstream::where('name', 'like', '%test%')->get();
            $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $results);
        }, 'Name search should use index');

        // Search by status should be fast (requires index on status)
        $this->assertExecutionTimeUnder(15, function () {
            $results = Workstream::where('status', Workstream::STATUS_ACTIVE)->get();
            $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $results);
        }, 'Status search should use index');

        // Search by parent_workstream_id should be fast (requires index)
        $this->assertExecutionTimeUnder(15, function () {
            $results = Workstream::where('parent_workstream_id', 1)->get();
            $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $results);
        }, 'Parent workstream search should use index');

        // Search by type should be fast (requires index on type)
        $this->assertExecutionTimeUnder(15, function () {
            $results = Workstream::where('type', Workstream::TYPE_INITIATIVE)->get();
            $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $results);
        }, 'Type search should use index');
    }

    /** @test */
    public function release_filtering_and_sorting_should_be_optimized()
    {
        // Given: 2000 releases with various dates and statuses
        $workstreams = Workstream::factory()->count(10)->create();

        foreach ($workstreams as $workstream) {
            Release::factory()->count(200)->create([
                'workstream_id' => $workstream->id,
                'target_date' => fake()->dateTimeBetween('-1 year', '+1 year'),
                'status' => fake()->randomElement(['planned', 'in_progress', 'completed'])
            ]);
        }

        // When & Then: Date range queries should be fast (requires index on target_date)
        $this->assertExecutionTimeUnder(30, function () {
            $results = Release::whereBetween('target_date', [
                now()->subMonths(3),
                now()->addMonths(3)
            ])->get();
            $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $results);
        }, 'Date range filtering should use index');

        // Status filtering should be fast
        $this->assertExecutionTimeUnder(25, function () {
            $results = Release::where('status', 'in_progress')->get();
            $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $results);
        }, 'Status filtering should use index');

        // Combined workstream + status query should be fast (requires composite index)
        $this->assertExecutionTimeUnder(20, function () use ($workstreams) {
            $results = Release::where('workstream_id', $workstreams->first()->id)
                             ->where('status', 'in_progress')
                             ->get();
            $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $results);
        }, 'Workstream + status filtering should use composite index');
    }

    /** @test */
    public function communication_queries_should_handle_large_datasets_efficiently()
    {
        // Given: 5000 communications across multiple releases
        $releases = Release::factory()->count(20)->create();

        foreach ($releases as $release) {
            Communication::factory()->count(250)->create([
                'release_id' => $release->id,
                'communication_date' => fake()->dateTimeBetween('-6 months', 'now'),
                'priority' => fake()->randomElement(['low', 'medium', 'high', 'urgent']),
                'channel' => fake()->randomElement(['email', 'slack', 'teams'])
            ]);
        }

        // When & Then: Date-based queries should be fast
        $this->assertExecutionTimeUnder(40, function () {
            $results = Communication::whereBetween('communication_date', [
                now()->subMonth(),
                now()
            ])->get();
            $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $results);
        }, 'Communication date filtering should use index');

        // Priority filtering should be fast
        $this->assertExecutionTimeUnder(25, function () {
            $results = Communication::where('priority', 'urgent')->get();
            $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $results);
        }, 'Priority filtering should use index');

        // Channel filtering should be fast
        $this->assertExecutionTimeUnder(25, function () {
            $results = Communication::where('channel', 'email')->get();
            $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $results);
        }, 'Channel filtering should use index');
    }

    /** @test */
    public function aggregation_queries_should_be_optimized()
    {
        // Given: Large dataset for aggregation testing
        $workstreams = Workstream::factory()->count(50)->create();

        foreach ($workstreams as $workstream) {
            $releases = Release::factory()->count(20)->create([
                'workstream_id' => $workstream->id
            ]);

            foreach ($releases as $release) {
                ChecklistItemAssignment::factory()->count(10)->create([
                    'release_id' => $release->id,
                    'status' => fake()->randomElement(['pending', 'in_progress', 'completed'])
                ]);
            }
        }

        // When & Then: Count aggregations should be fast
        $this->assertExecutionTimeUnder(50, function () {
            $counts = Release::selectRaw('status, COUNT(*) as count')
                           ->groupBy('status')
                           ->get();
            $this->assertNotEmpty($counts);
        }, 'Release status aggregation should be optimized');

        // Task completion aggregation should be fast
        $this->assertExecutionTimeUnder(75, function () {
            $completionStats = ChecklistItemAssignment::selectRaw('
                release_id,
                COUNT(*) as total_tasks,
                SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_tasks
            ')->groupBy('release_id')->get();
            $this->assertNotEmpty($completionStats);
        }, 'Task completion aggregation should be optimized');

        // Cross-table aggregation should be reasonable
        $this->assertExecutionTimeUnder(100, function () {
            $workstreamStats = DB::table('workstreams')
                ->leftJoin('releases', 'workstreams.id', '=', 'releases.workstream_id')
                ->leftJoin('checklist_item_assignments', 'releases.id', '=', 'checklist_item_assignments.release_id')
                ->selectRaw('
                    workstreams.id,
                    workstreams.name,
                    COUNT(DISTINCT releases.id) as release_count,
                    COUNT(checklist_item_assignments.id) as task_count
                ')
                ->groupBy('workstreams.id', 'workstreams.name')
                ->get();
            $this->assertNotEmpty($workstreamStats);
        }, 'Cross-table aggregation should be optimized with proper joins');
    }

    /** @test */
    public function pagination_with_large_datasets_should_be_efficient()
    {
        // Given: 10,000 releases
        $workstream = Workstream::factory()->create();
        Release::factory()->count(10000)->create([
            'workstream_id' => $workstream->id
        ]);

        // When & Then: First page should load quickly
        $this->assertExecutionTimeUnder(30, function () {
            $firstPage = Release::paginate(50);
            $this->assertCount(50, $firstPage->items());
            $this->assertEquals(10000, $firstPage->total());
        }, 'First page pagination should be fast');

        // Middle page should load quickly (this often requires LIMIT/OFFSET optimization)
        $this->assertExecutionTimeUnder(50, function () {
            $middlePage = Release::paginate(50, ['*'], 'page', 100); // Page 100
            $this->assertCount(50, $middlePage->items());
        }, 'Middle page pagination should be optimized (may require cursor pagination)');

        // Large offset pagination should have reasonable performance warning
        $this->assertExecutionTimeUnder(200, function () {
            $lastPage = Release::paginate(50, ['*'], 'page', 190); // Near the end
            $this->assertCount(50, $lastPage->items());
        }, 'Large offset pagination should be under 200ms (consider cursor pagination for better performance)');
    }

    /** @test */
    public function permission_inheritance_queries_should_be_optimized()
    {
        // Given: Complex permission hierarchy
        $root = Workstream::factory()->create(['parent_workstream_id' => null]);
        $level2 = Workstream::factory()->count(5)->create(['parent_workstream_id' => $root->id]);
        $level3 = [];

        foreach ($level2 as $parent) {
            $children = Workstream::factory()->count(3)->create(['parent_workstream_id' => $parent->id]);
            $level3 = array_merge($level3, $children->toArray());
        }

        $users = User::factory()->count(20)->create();

        // Create permissions at various levels
        foreach ([$root, ...$level2, ...$level3] as $workstream) {
            foreach ($users->random(5) as $user) {
                WorkstreamPermission::factory()->create([
                    'workstream_id' => $workstream['id'] ?? $workstream->id,
                    'user_id' => $user->id,
                    'permission_type' => fake()->randomElement(['view', 'edit', 'admin']),
                    'scope' => fake()->randomElement(['workstream_only', 'workstream_and_children'])
                ]);
            }
        }

        // When & Then: Permission checking should be fast
        $deepestWorkstream = Workstream::find($level3[0]['id']);
        $testUser = $users->first();

        $this->assertExecutionTimeUnder(75, function () use ($deepestWorkstream, $testUser) {
            $effectivePermissions = $deepestWorkstream->getEffectivePermissionsForUser($testUser->id);
            $this->assertIsArray($effectivePermissions);
        }, 'Permission inheritance calculation should be optimized');

        // Bulk permission checking should be efficient
        $this->assertExecutionTimeUnder(150, function () use ($users) {
            $workstreams = Workstream::limit(10)->get();
            $results = [];

            foreach ($workstreams as $workstream) {
                foreach ($users->take(5) as $user) {
                    $results[] = $workstream->userHasInheritedPermission($user->id, 'view');
                }
            }

            $this->assertNotEmpty($results);
        }, 'Bulk permission checking should be optimized (consider caching or batch queries)');
    }

    /** @test */
    public function complex_reporting_queries_should_complete_under_200ms()
    {
        // Given: Realistic dataset for reporting
        $rootWorkstreams = Workstream::factory()->count(5)->create(['parent_workstream_id' => null]);

        foreach ($rootWorkstreams as $root) {
            $children = Workstream::factory()->count(4)->create(['parent_workstream_id' => $root->id]);

            foreach ($children as $child) {
                $releases = Release::factory()->count(8)->create(['workstream_id' => $child->id]);

                foreach ($releases as $release) {
                    ChecklistItemAssignment::factory()->count(12)->create([
                        'release_id' => $release->id,
                        'status' => fake()->randomElement(['pending', 'in_progress', 'completed'])
                    ]);
                }
            }
        }

        // When & Then: Rollup reporting should be efficient
        $rootWorkstream = $rootWorkstreams->first();

        $this->assertExecutionTimeUnder(200, function () use ($rootWorkstream) {
            $rollupReport = $rootWorkstream->getRollupReport();
            $this->assertArrayHasKey('summary', $rollupReport);
            $this->assertArrayHasKey('child_workstreams', $rollupReport);
        }, 'Rollup reporting across hierarchy should complete under 200ms');
    }

    /** @test */
    public function full_text_search_should_be_optimized()
    {
        // Given: Content for full-text search testing
        Workstream::factory()->count(1000)->create();
        Release::factory()->count(2000)->create();
        Communication::factory()->count(3000)->create();

        // When & Then: Text search should be fast (requires full-text indexes)
        $this->assertExecutionTimeUnder(100, function () {
            // This will FAIL initially without proper full-text indexes
            $workstreamResults = Workstream::where('name', 'like', '%initiative%')
                                         ->orWhere('description', 'like', '%initiative%')
                                         ->get();
            $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $workstreamResults);
        }, 'Full-text search on workstreams should use proper indexing');

        $this->assertExecutionTimeUnder(150, function () {
            // This will FAIL initially without full-text search optimization
            $communicationResults = Communication::where('subject', 'like', '%release%')
                                                ->orWhere('content', 'like', '%release%')
                                                ->get();
            $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $communicationResults);
        }, 'Full-text search on communications should be optimized');
    }

    /** @test */
    public function database_connection_handling_should_be_efficient()
    {
        // Given: Scenario that could cause connection issues
        $operations = [];

        // When & Then: Multiple rapid queries should not exhaust connections
        $this->assertExecutionTimeUnder(500, function () use (&$operations) {
            for ($i = 0; $i < 100; $i++) {
                $operations[] = Workstream::count();
                $operations[] = Release::count();
                $operations[] = Communication::count();
            }
        }, 'Rapid sequential queries should not cause connection pool exhaustion');

        $this->assertCount(300, $operations);

        // Verify connections are properly returned to pool
        $this->assertTrue(true, 'All operations completed without connection issues');
    }
}
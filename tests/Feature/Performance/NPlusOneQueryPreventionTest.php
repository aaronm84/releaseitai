<?php

namespace Tests\Feature\Performance;

use App\Models\Workstream;
use App\Models\Release;
use App\Models\User;
use App\Models\StakeholderRelease;
use App\Models\ChecklistItemAssignment;
use App\Models\Communication;
use App\Models\CommunicationParticipant;
use App\Models\WorkstreamPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NPlusOneQueryPreventionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable query logging for all tests
        DB::enableQueryLog();
    }

    protected function tearDown(): void
    {
        // Clear query log after each test
        DB::flushQueryLog();
        parent::tearDown();
    }

    protected function getQueryCount(): int
    {
        return count(DB::getQueryLog());
    }

    protected function assertQueryCountLessThanOrEqual(int $expectedMax, string $message = ''): void
    {
        $actual = $this->getQueryCount();
        $this->assertLessThanOrEqual(
            $expectedMax,
            $actual,
            $message . " Expected at most {$expectedMax} queries, but {$actual} were executed."
        );
    }

    /** @test */
    public function hierarchy_traversal_with_50_child_workstreams_should_use_only_3_queries()
    {
        // Given: A root workstream with 50 child workstreams
        $rootWorkstream = Workstream::factory()->create(['parent_workstream_id' => null]);

        $childWorkstreams = Workstream::factory()->count(50)->create([
            'parent_workstream_id' => $rootWorkstream->id
        ]);

        DB::flushQueryLog();

        // When: Loading the complete hierarchy tree
        $hierarchyTree = $rootWorkstream->buildHierarchyTree();

        // Then: Should use only 3 queries maximum
        // Query 1: Load root workstream with owner
        // Query 2: Load all child workstreams with owners in one query
        // Query 3: Any additional optimization query
        $this->assertQueryCountLessThanOrEqual(
            3,
            'Loading hierarchy with 50 children should use eager loading to prevent N+1 queries'
        );

        // Verify we got all the data
        $this->assertCount(50, $hierarchyTree['children']);
        $this->assertEquals($rootWorkstream->name, $hierarchyTree['name']);
    }

    /** @test */
    public function loading_workstream_with_all_descendants_should_not_cause_n_plus_one()
    {
        // Given: A 3-level hierarchy with multiple children at each level
        $root = Workstream::factory()->create(['parent_workstream_id' => null]);

        // Create 10 second-level workstreams
        $secondLevel = Workstream::factory()->count(10)->create([
            'parent_workstream_id' => $root->id
        ]);

        // Create 5 third-level workstreams for each second-level (50 total)
        foreach ($secondLevel as $parent) {
            Workstream::factory()->count(5)->create([
                'parent_workstream_id' => $parent->id
            ]);
        }

        DB::flushQueryLog();

        // When: Getting all descendants
        $descendants = $root->getAllDescendants();

        // Then: Should load all descendants efficiently
        // This will FAIL initially because getAllDescendants() uses recursive queries
        $this->assertQueryCountLessThanOrEqual(
            2,
            'Loading all descendants should use a single recursive query or CTE, not individual queries per level'
        );

        // Verify we got all descendants (10 + 50 = 60)
        $this->assertCount(60, $descendants);
    }

    /** @test */
    public function loading_release_with_all_stakeholders_should_not_cause_n_plus_one()
    {
        // Given: A release with 25 stakeholders
        $release = Release::factory()->create();
        $users = User::factory()->count(25)->create();

        foreach ($users as $user) {
            StakeholderRelease::factory()->create([
                'release_id' => $release->id,
                'user_id' => $user->id,
                'role' => 'stakeholder'
            ]);
        }

        DB::flushQueryLog();

        // When: Loading release with all stakeholders
        $releaseWithStakeholders = Release::with('stakeholders')->find($release->id);
        $stakeholders = $releaseWithStakeholders->stakeholders;

        // Then: Should use only 2 queries (release + stakeholders in one join)
        $this->assertQueryCountLessThanOrEqual(
            2,
            'Loading release with stakeholders should use eager loading'
        );

        $this->assertCount(25, $stakeholders);
    }

    /** @test */
    public function workstream_permissions_inheritance_calculation_should_be_efficient()
    {
        // Given: A 4-level hierarchy with permissions at various levels
        $level1 = Workstream::factory()->create(['parent_workstream_id' => null]);
        $level2 = Workstream::factory()->create(['parent_workstream_id' => $level1->id]);
        $level3 = Workstream::factory()->create(['parent_workstream_id' => $level2->id]);
        $level4 = Workstream::factory()->create(['parent_workstream_id' => $level3->id]);

        $user = User::factory()->create();

        // Create permissions at different levels
        WorkstreamPermission::factory()->create([
            'workstream_id' => $level1->id,
            'user_id' => $user->id,
            'permission_type' => 'admin',
            'scope' => 'workstream_and_children'
        ]);

        WorkstreamPermission::factory()->create([
            'workstream_id' => $level2->id,
            'user_id' => $user->id,
            'permission_type' => 'edit',
            'scope' => 'workstream_only'
        ]);

        DB::flushQueryLog();

        // When: Calculating effective permissions for the deepest workstream
        $effectivePermissions = $level4->getEffectivePermissionsForUser($user->id);

        // Then: Should traverse ancestry efficiently
        // This will FAIL initially because getEffectivePermissionsForUser() loads ancestors individually
        $this->assertQueryCountLessThanOrEqual(
            3,
            'Permission inheritance calculation should load all ancestors and their permissions in batched queries'
        );

        $this->assertContains('admin', $effectivePermissions['effective_permissions']);
    }

    /** @test */
    public function rollup_reporting_across_100_workstreams_should_minimize_queries()
    {
        // Given: A workstream with 20 children, each with 5 releases, each with 10 tasks
        $rootWorkstream = Workstream::factory()->create(['parent_workstream_id' => null]);

        $childWorkstreams = Workstream::factory()->count(20)->create([
            'parent_workstream_id' => $rootWorkstream->id
        ]);

        foreach ($childWorkstreams as $workstream) {
            $releases = Release::factory()->count(5)->create([
                'workstream_id' => $workstream->id
            ]);

            foreach ($releases as $release) {
                ChecklistItemAssignment::factory()->count(10)->create([
                    'release_id' => $release->id,
                    'status' => 'completed'
                ]);
            }
        }

        DB::flushQueryLog();

        // When: Generating rollup report
        $rollupReport = $rootWorkstream->getRollupReport();

        // Then: Should use efficient bulk queries
        // This will FAIL initially because getRollupReport() has nested loops causing N+1 queries
        $this->assertQueryCountLessThanOrEqual(
            5,
            'Rollup reporting should use eager loading and bulk operations, not individual queries per workstream/release'
        );

        // Verify report accuracy
        $this->assertEquals(100, $rollupReport['summary']['total_releases']); // 20 * 5
        $this->assertEquals(1000, $rollupReport['summary']['total_tasks']); // 20 * 5 * 10
        $this->assertEquals(100.0, $rollupReport['summary']['completion_percentage']);
    }

    /** @test */
    public function loading_communications_with_participants_should_prevent_n_plus_one()
    {
        // Given: A release with 50 communications, each with 5 participants
        $release = Release::factory()->create();
        $users = User::factory()->count(25)->create();

        $communications = Communication::factory()->count(50)->create([
            'release_id' => $release->id
        ]);

        foreach ($communications as $communication) {
            foreach ($users->random(5) as $user) {
                CommunicationParticipant::factory()->create([
                    'communication_id' => $communication->id,
                    'user_id' => $user->id,
                    'participant_type' => 'to'
                ]);
            }
        }

        DB::flushQueryLog();

        // When: Loading all communications with participants for a release
        $communicationsWithParticipants = Communication::with(['participants.user'])
            ->where('release_id', $release->id)
            ->get();

        // Then: Should use eager loading
        $this->assertQueryCountLessThanOrEqual(
            3,
            'Loading communications with participants should use eager loading to prevent N+1 queries'
        );

        $this->assertCount(50, $communicationsWithParticipants);
        $this->assertCount(5, $communicationsWithParticipants->first()->participants);
    }

    /** @test */
    public function bulk_workstream_operations_should_use_single_queries()
    {
        // Given: 100 workstreams that need status updates
        $workstreams = Workstream::factory()->count(100)->create([
            'status' => Workstream::STATUS_DRAFT
        ]);

        DB::flushQueryLog();

        // When: Updating all workstreams to active status
        Workstream::whereIn('id', $workstreams->pluck('id'))
            ->update(['status' => Workstream::STATUS_ACTIVE]);

        // Then: Should use only 1 query for bulk update
        $this->assertQueryCountLessThanOrEqual(
            1,
            'Bulk operations should use single UPDATE query, not individual queries per record'
        );

        // Verify all were updated
        $this->assertEquals(
            100,
            Workstream::where('status', Workstream::STATUS_ACTIVE)->count()
        );
    }

    /** @test */
    public function checking_approval_status_across_multiple_releases_should_be_efficient()
    {
        // Given: 50 releases with varying approval requests
        $releases = Release::factory()->count(50)->create();

        foreach ($releases as $release) {
            // Create 3-5 approval requests per release
            \App\Models\ApprovalRequest::factory()->count(rand(3, 5))->create([
                'release_id' => $release->id,
                'status' => 'approved'
            ]);
        }

        DB::flushQueryLog();

        // When: Checking approval status for all releases
        $releasesWithApprovalStatus = Release::with('approvalRequests')
            ->get()
            ->map(function ($release) {
                return [
                    'id' => $release->id,
                    'approval_status' => $release->getApprovalStatus(),
                    'has_all_approvals' => $release->hasAllApprovalsApproved()
                ];
            });

        // Then: Should load all approval requests in one query
        $this->assertQueryCountLessThanOrEqual(
            2,
            'Loading approval status for multiple releases should use eager loading'
        );

        $this->assertCount(50, $releasesWithApprovalStatus);
    }

    /** @test */
    public function loading_workstream_hierarchy_with_permissions_should_minimize_database_hits()
    {
        // Given: Complex hierarchy with permissions at each level
        $root = Workstream::factory()->create(['parent_workstream_id' => null]);
        $children = Workstream::factory()->count(10)->create([
            'parent_workstream_id' => $root->id
        ]);

        $users = User::factory()->count(5)->create();

        // Add permissions to each workstream
        foreach ([$root, ...$children] as $workstream) {
            foreach ($users as $user) {
                WorkstreamPermission::factory()->create([
                    'workstream_id' => $workstream->id,
                    'user_id' => $user->id,
                    'permission_type' => 'view',
                    'scope' => 'workstream_only'
                ]);
            }
        }

        DB::flushQueryLog();

        // When: Loading hierarchy with all permissions
        $hierarchyWithPermissions = Workstream::with(['childWorkstreams.permissions.user', 'permissions.user'])
            ->find($root->id);

        // Then: Should use efficient eager loading
        $this->assertQueryCountLessThanOrEqual(
            4,
            'Loading hierarchy with permissions should use eager loading for all relationships'
        );

        $this->assertCount(10, $hierarchyWithPermissions->childWorkstreams);
        $this->assertCount(5, $hierarchyWithPermissions->permissions);
    }

    /** @test */
    public function paginated_large_dataset_queries_should_not_cause_performance_issues()
    {
        // Given: 1000 releases with associated data
        $workstream = Workstream::factory()->create();
        Release::factory()->count(1000)->create([
            'workstream_id' => $workstream->id
        ]);

        DB::flushQueryLog();

        // When: Loading paginated results with relationships
        $paginatedReleases = Release::with(['workstream', 'stakeholders'])
            ->paginate(50);

        // Then: Should only load the requested page efficiently
        $this->assertQueryCountLessThanOrEqual(
            4,
            'Paginated queries should not load more data than necessary and should use eager loading'
        );

        $this->assertCount(50, $paginatedReleases->items());
        $this->assertEquals(1000, $paginatedReleases->total());
    }
}
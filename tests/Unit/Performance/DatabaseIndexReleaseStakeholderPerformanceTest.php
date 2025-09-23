<?php

namespace Tests\Unit\Performance;

use App\Models\Release;
use App\Models\Stakeholder;
use App\Models\StakeholderRelease;
use App\Models\Workstream;
use App\Models\User;

/**
 * Tests database index performance for release stakeholder queries.
 * Validates that critical release stakeholder queries use proper indexes and perform within acceptable time limits.
 */
class DatabaseIndexReleaseStakeholderPerformanceTest extends BasePerformanceTest
{
    private array $testData;

    protected function setUp(): void
    {
        parent::setUp();

        // Create large dataset for realistic performance testing
        $this->testData = $this->createTestReleaseStakeholderData(
            self::LARGE_DATASET_SIZE / 50, // 100 releases
            self::LARGE_DATASET_SIZE / 100 // 50 stakeholders
        );
    }

    /**
     * Test that stakeholder queries by user_id use proper index and perform well.
     *
     * Given: A large dataset of stakeholders
     * When: Querying stakeholders by user_id
     * Then: Query should use user_id index and complete within time limits
     */
    public function test_stakeholder_queries_by_user_id_use_index_and_perform_well(): void
    {
        $this->startQueryMonitoring();

        // Query stakeholders by user - this should use an index on user_id
        $userStakeholders = Stakeholder::where('user_id', $this->testUser->id)
            ->orderBy('name')
            ->limit(50)
            ->get();

        $metrics = $this->stopQueryMonitoring();

        // Assert performance requirements
        $this->assertQueryPerformance($metrics, [
            'max_time_ms' => self::MAX_INDEX_SCAN_TIME_MS,
            'max_queries' => 1
        ]);

        // Assert no sequential scans
        $this->assertNoSequentialScans($metrics);

        // Verify we got results
        $this->assertGreaterThan(0, $userStakeholders->count());

        // Verify index exists
        $this->assertIndexExists('stakeholders', 'stakeholders_user_id_index');
    }

    /**
     * Test that release stakeholder relationship queries use proper indexes and perform well.
     *
     * Given: A large dataset of release-stakeholder relationships
     * When: Querying stakeholders by release_id
     * Then: Query should use appropriate indexes and complete within time limits
     */
    public function test_release_stakeholder_relationship_queries_use_indexes_and_perform_well(): void
    {
        $release = $this->testData['releases']->first();

        $this->startQueryMonitoring();

        // Query stakeholders for a release - this should use indexes on the pivot table
        $releaseStakeholders = $release->stakeholders()
            ->wherePivot('role', 'owner')
            ->orderBy('stakeholders.name')
            ->get();

        $metrics = $this->stopQueryMonitoring();

        // Assert performance requirements
        $this->assertQueryPerformance($metrics, [
            'max_time_ms' => self::MAX_INDEX_SCAN_TIME_MS,
            'max_queries' => 1
        ]);

        // Assert no sequential scans
        $this->assertNoSequentialScans($metrics);

        // Verify composite index exists on stakeholder_releases table
        $this->assertCompositeIndexExists('stakeholder_releases', ['release_id', 'role']);
    }

    /**
     * Test that stakeholder influence level queries use proper indexes and perform well.
     *
     * Given: Stakeholders with various influence levels
     * When: Querying stakeholders by influence level
     * Then: Query should use influence_level index and complete within time limits
     */
    public function test_stakeholder_influence_level_queries_use_indexes_and_perform_well(): void
    {
        $this->startQueryMonitoring();

        // Query stakeholders by influence level - this should use an index on influence_level
        $highInfluenceStakeholders = Stakeholder::where('user_id', $this->testUser->id)
            ->where('influence_level', 'high')
            ->where('is_available', true)
            ->orderBy('name')
            ->get();

        $metrics = $this->stopQueryMonitoring();

        // Assert performance requirements
        $this->assertQueryPerformance($metrics, [
            'max_time_ms' => self::MAX_INDEX_SCAN_TIME_MS,
            'max_queries' => 1
        ]);

        // Assert no sequential scans
        $this->assertNoSequentialScans($metrics);

        // Verify composite index exists for user_id + influence_level + is_available
        $this->assertCompositeIndexExists('stakeholders', ['user_id', 'influence_level', 'is_available']);
    }

    /**
     * Test that stakeholder support level queries use proper indexes and perform well.
     *
     * Given: Stakeholders with various support levels
     * When: Querying stakeholders by support level and availability
     * Then: Query should use appropriate indexes and complete within time limits
     */
    public function test_stakeholder_support_level_queries_use_indexes_and_perform_well(): void
    {
        $this->startQueryMonitoring();

        // Query stakeholders by support level - this should use an index on support_level
        $supportiveStakeholders = Stakeholder::where('user_id', $this->testUser->id)
            ->where('support_level', 'high')
            ->where('needs_follow_up', false)
            ->orderBy('last_contact_at', 'desc')
            ->limit(20)
            ->get();

        $metrics = $this->stopQueryMonitoring();

        // Assert performance requirements
        $this->assertQueryPerformance($metrics, [
            'max_time_ms' => self::MAX_INDEX_SCAN_TIME_MS,
            'max_queries' => 1
        ]);

        // Assert no sequential scans
        $this->assertNoSequentialScans($metrics);

        // Verify composite index exists for user_id + support_level + needs_follow_up
        $this->assertCompositeIndexExists('stakeholders', ['user_id', 'support_level', 'needs_follow_up']);
    }

    /**
     * Test that release queries by workstream_id use proper indexes and perform well.
     *
     * Given: Releases associated with various workstreams
     * When: Querying releases by workstream_id
     * Then: Query should use workstream_id index and complete within time limits
     */
    public function test_release_queries_by_workstream_use_indexes_and_perform_well(): void
    {
        $workstream = $this->testData['workstreams']->first();

        $this->startQueryMonitoring();

        // Query releases by workstream - this should use an index on workstream_id
        $workstreamReleases = Release::where('workstream_id', $workstream->id)
            ->where('status', '!=', 'completed')
            ->orderBy('target_date')
            ->get();

        $metrics = $this->stopQueryMonitoring();

        // Assert performance requirements
        $this->assertQueryPerformance($metrics, [
            'max_time_ms' => self::MAX_INDEX_SCAN_TIME_MS,
            'max_queries' => 1
        ]);

        // Assert no sequential scans
        $this->assertNoSequentialScans($metrics);

        // Verify composite index exists for workstream_id + status
        $this->assertCompositeIndexExists('releases', ['workstream_id', 'status']);
    }

    /**
     * Test that stakeholder notification preference queries use proper indexes and perform well.
     *
     * Given: Stakeholder-release relationships with various notification preferences
     * When: Querying stakeholders by notification preferences
     * Then: Query should use appropriate indexes and complete within time limits
     */
    public function test_stakeholder_notification_preference_queries_use_indexes_and_perform_well(): void
    {
        $this->startQueryMonitoring();

        // Query stakeholder releases by notification preference
        $emailNotificationStakeholders = StakeholderRelease::where('notification_preference', 'email')
            ->where('role', 'approver')
            ->with(['user', 'release'])
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        $metrics = $this->stopQueryMonitoring();

        // Assert performance requirements
        $this->assertQueryPerformance($metrics, [
            'max_time_ms' => self::MAX_INDEX_SCAN_TIME_MS,
            'max_queries' => 3 // Main query + 2 eager loaded relationships
        ]);

        // Assert no sequential scans
        $this->assertNoSequentialScans($metrics);

        // Verify composite index exists for notification_preference + role
        $this->assertCompositeIndexExists('stakeholder_releases', ['notification_preference', 'role']);
    }

    /**
     * Test that stakeholder search queries use proper indexes and perform well.
     *
     * Given: Stakeholders with various names, emails, and companies
     * When: Searching stakeholders by text patterns
     * Then: Search should use text indexes and complete within time limits
     */
    public function test_stakeholder_search_queries_use_indexes_and_perform_well(): void
    {
        $this->startQueryMonitoring();

        // Search stakeholders by name, email, or company - should use text search indexes
        $searchResults = Stakeholder::where('user_id', $this->testUser->id)
            ->where(function ($query) {
                $query->where('name', 'ILIKE', '%test%')
                      ->orWhere('email', 'ILIKE', '%test%')
                      ->orWhere('company', 'ILIKE', '%test%');
            })
            ->orderBy('name')
            ->limit(50)
            ->get();

        $metrics = $this->stopQueryMonitoring();

        // Assert performance requirements (text search may be slower)
        $this->assertQueryPerformance($metrics, [
            'max_time_ms' => self::MAX_BULK_OPERATION_TIME_MS,
            'max_queries' => 1
        ]);

        // Verify text search indexes exist
        $textIndexes = \DB::select("
            SELECT indexname, indexdef
            FROM pg_indexes
            WHERE tablename = 'stakeholders'
            AND (indexdef LIKE '%gin%' OR indexdef LIKE '%gist%')
            AND (indexdef LIKE '%name%' OR indexdef LIKE '%email%' OR indexdef LIKE '%company%')
        ");

        $this->assertNotEmpty(
            $textIndexes,
            'Text search indexes on name, email, or company columns do not exist'
        );
    }

    /**
     * Test that complex release stakeholder analytics queries use proper indexes and perform well.
     *
     * Given: A large dataset of release-stakeholder relationships
     * When: Running complex analytics queries with aggregations and joins
     * Then: Queries should use proper indexes and complete within time limits
     */
    public function test_complex_release_stakeholder_analytics_use_indexes_and_perform_well(): void
    {
        $this->startQueryMonitoring();

        // Complex analytics query with multiple joins and aggregations
        $analytics = \DB::table('releases')
            ->select([
                'releases.status',
                'stakeholder_releases.role',
                \DB::raw('COUNT(DISTINCT releases.id) as release_count'),
                \DB::raw('COUNT(DISTINCT stakeholder_releases.user_id) as stakeholder_count'),
                \DB::raw('AVG(EXTRACT(DAY FROM (releases.target_date - releases.created_at))) as avg_duration_days')
            ])
            ->join('stakeholder_releases', 'releases.id', '=', 'stakeholder_releases.release_id')
            ->join('workstreams', 'releases.workstream_id', '=', 'workstreams.id')
            ->where('workstreams.owner_id', $this->testUser->id)
            ->where('releases.created_at', '>=', now()->subDays(90))
            ->groupBy(['releases.status', 'stakeholder_releases.role'])
            ->orderBy('release_count', 'desc')
            ->get();

        $metrics = $this->stopQueryMonitoring();

        // Assert performance requirements for complex query
        $this->assertQueryPerformance($metrics, [
            'max_time_ms' => self::MAX_BULK_OPERATION_TIME_MS,
            'max_queries' => 1
        ]);

        // Assert no sequential scans
        $this->assertNoSequentialScans($metrics);

        // Verify required indexes exist for the joins and filters
        $this->assertIndexExists('stakeholder_releases', 'stakeholder_releases_release_id_index');
        $this->assertIndexExists('releases', 'releases_workstream_id_index');
        $this->assertIndexExists('workstreams', 'workstreams_owner_id_index');
        $this->assertCompositeIndexExists('releases', ['created_at', 'status']);
    }

    /**
     * Test that stakeholder availability queries use proper indexes and perform well.
     *
     * Given: Stakeholders with various availability statuses and dates
     * When: Querying available stakeholders with time constraints
     * Then: Queries should use appropriate indexes and complete within time limits
     */
    public function test_stakeholder_availability_queries_use_indexes_and_perform_well(): void
    {
        $this->startQueryMonitoring();

        // Query available stakeholders with time constraints
        $availableStakeholders = Stakeholder::where('user_id', $this->testUser->id)
            ->where('is_available', true)
            ->where(function ($query) {
                $query->whereNull('unavailable_until')
                      ->orWhere('unavailable_until', '<=', now());
            })
            ->where('needs_follow_up', false)
            ->orderBy('last_contact_at', 'desc')
            ->limit(25)
            ->get();

        $metrics = $this->stopQueryMonitoring();

        // Assert performance requirements
        $this->assertQueryPerformance($metrics, [
            'max_time_ms' => self::MAX_INDEX_SCAN_TIME_MS,
            'max_queries' => 1
        ]);

        // Assert no sequential scans
        $this->assertNoSequentialScans($metrics);

        // Verify composite index exists for availability fields
        $this->assertCompositeIndexExists('stakeholders', [
            'user_id', 'is_available', 'unavailable_until', 'needs_follow_up'
        ]);
    }

    /**
     * Test that release target date queries use proper indexes and perform well.
     *
     * Given: Releases with various target dates
     * When: Querying releases by date ranges
     * Then: Queries should use date indexes and complete within time limits
     */
    public function test_release_target_date_queries_use_indexes_and_perform_well(): void
    {
        $this->startQueryMonitoring();

        // Query releases by target date range
        $upcomingReleases = Release::whereBetween('target_date', [
                now()->startOfDay(),
                now()->addDays(30)->endOfDay()
            ])
            ->where('status', '!=', 'completed')
            ->with(['workstream:id,name,owner_id', 'stakeholders:id,name,email'])
            ->orderBy('target_date')
            ->get();

        $metrics = $this->stopQueryMonitoring();

        // Assert performance requirements
        $this->assertQueryPerformance($metrics, [
            'max_time_ms' => self::MAX_BULK_OPERATION_TIME_MS,
            'max_queries' => 3 // Main query + 2 eager loaded relationships
        ]);

        // Assert no sequential scans
        $this->assertNoSequentialScans($metrics);

        // Verify composite index exists for target_date + status
        $this->assertCompositeIndexExists('releases', ['target_date', 'status']);
    }

    /**
     * Test that bulk stakeholder operations maintain performance.
     *
     * Given: A need to process stakeholder data in bulk
     * When: Running bulk operations (inserts, updates, relationship changes)
     * Then: Operations should complete within acceptable time limits
     */
    public function test_bulk_stakeholder_operations_maintain_performance(): void
    {
        $this->startQueryMonitoring();

        // Simulate bulk stakeholder updates
        $stakeholdersToUpdate = Stakeholder::where('user_id', $this->testUser->id)
            ->limit(50)
            ->get();

        foreach ($stakeholdersToUpdate as $stakeholder) {
            $stakeholder->update([
                'last_contact_at' => now(),
                'last_contact_channel' => 'email',
                'needs_follow_up' => false
            ]);
        }

        $metrics = $this->stopQueryMonitoring();

        // Assert bulk operation performance
        $this->assertQueryPerformance($metrics, [
            'max_time_ms' => self::MAX_BULK_OPERATION_TIME_MS,
            'max_queries' => 101 // 1 select + 50 updates
        ]);
    }

    /**
     * Test that stakeholder role hierarchy queries use proper indexes and perform well.
     *
     * Given: Complex role hierarchies in release stakeholder relationships
     * When: Querying stakeholders by role hierarchies and permissions
     * Then: Queries should use appropriate indexes and complete within time limits
     */
    public function test_stakeholder_role_hierarchy_queries_use_indexes_and_perform_well(): void
    {
        $this->startQueryMonitoring();

        // Complex role hierarchy query
        $roleHierarchy = StakeholderRelease::select([
                'stakeholder_releases.role',
                'releases.status',
                \DB::raw('COUNT(*) as assignment_count'),
                \DB::raw('COUNT(DISTINCT stakeholder_releases.user_id) as unique_stakeholders'),
                \DB::raw('COUNT(DISTINCT releases.id) as unique_releases')
            ])
            ->join('releases', 'stakeholder_releases.release_id', '=', 'releases.id')
            ->join('workstreams', 'releases.workstream_id', '=', 'workstreams.id')
            ->where('workstreams.owner_id', $this->testUser->id)
            ->whereIn('stakeholder_releases.role', ['owner', 'approver', 'reviewer'])
            ->groupBy(['stakeholder_releases.role', 'releases.status'])
            ->orderBy('assignment_count', 'desc')
            ->get();

        $metrics = $this->stopQueryMonitoring();

        // Assert performance requirements for complex aggregation
        $this->assertQueryPerformance($metrics, [
            'max_time_ms' => self::MAX_BULK_OPERATION_TIME_MS,
            'max_queries' => 1
        ]);

        // Assert no sequential scans
        $this->assertNoSequentialScans($metrics);

        // Verify required indexes exist
        $this->assertCompositeIndexExists('stakeholder_releases', ['role', 'release_id']);
        $this->assertCompositeIndexExists('releases', ['workstream_id', 'status']);
    }

    /**
     * Test that stakeholder communication tracking queries use proper indexes and perform well.
     *
     * Given: Stakeholders with various communication tracking data
     * When: Querying stakeholders by communication patterns and follow-up needs
     * Then: Queries should use appropriate indexes and complete within time limits
     */
    public function test_stakeholder_communication_tracking_uses_indexes_and_performs_well(): void
    {
        $this->startQueryMonitoring();

        // Query stakeholders needing follow-up based on communication patterns
        $followUpNeeded = Stakeholder::where('user_id', $this->testUser->id)
            ->where(function ($query) {
                $query->where('needs_follow_up', true)
                      ->orWhere(function ($subQuery) {
                          $subQuery->whereNotNull('last_contact_at')
                                   ->where('last_contact_at', '<', now()->subDays(14));
                      })
                      ->orWhereNull('last_contact_at');
            })
            ->where('is_available', true)
            ->orderBy('last_contact_at', 'asc')
            ->limit(30)
            ->get();

        $metrics = $this->stopQueryMonitoring();

        // Assert performance requirements
        $this->assertQueryPerformance($metrics, [
            'max_time_ms' => self::MAX_INDEX_SCAN_TIME_MS,
            'max_queries' => 1
        ]);

        // Assert no sequential scans
        $this->assertNoSequentialScans($metrics);

        // Verify composite index exists for communication tracking fields
        $this->assertCompositeIndexExists('stakeholders', [
            'user_id', 'needs_follow_up', 'last_contact_at', 'is_available'
        ]);
    }
}
<?php

namespace Tests\Unit\Performance;

use App\Models\Workstream;
use App\Models\User;

/**
 * Tests database index performance for workstream hierarchy queries.
 * Validates that critical workstream hierarchy queries use proper indexes and perform within acceptable time limits.
 */
class DatabaseIndexWorkstreamPerformanceTest extends BasePerformanceTest
{
    private array $hierarchyData;

    protected function setUp(): void
    {
        parent::setUp();

        // Create large workstream hierarchy for realistic performance testing
        $this->hierarchyData = $this->createTestWorkstreamHierarchy(5, 10); // 5 levels deep, 10 children per level
    }

    /**
     * Test that parent-child relationship queries use proper indexes and perform well.
     *
     * Given: A large workstream hierarchy
     * When: Querying workstreams by parent_workstream_id
     * Then: Query should use parent_workstream_id index and complete within time limits
     */
    public function test_parent_child_queries_use_index_and_perform_well(): void
    {
        $parentWorkstream = $this->hierarchyData['roots']->first();

        $this->startQueryMonitoring();

        // Query children by parent - this should use an index on parent_workstream_id
        $children = Workstream::where('parent_workstream_id', $parentWorkstream->id)
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

        // Verify we got results
        $this->assertGreaterThan(0, $children->count());

        // Verify index exists
        $this->assertIndexExists('workstreams', 'workstreams_parent_workstream_id_index');
    }

    /**
     * Test that hierarchy depth queries use proper indexes and perform well.
     *
     * Given: A large workstream hierarchy with various depths
     * When: Querying workstreams by hierarchy_depth
     * Then: Query should use hierarchy_depth index and complete within time limits
     */
    public function test_hierarchy_depth_queries_use_index_and_perform_well(): void
    {
        $this->startQueryMonitoring();

        // Query workstreams by depth - this should use an index on hierarchy_depth
        $depthTwoWorkstreams = Workstream::where('hierarchy_depth', 2)
            ->where('status', 'active')
            ->orderBy('name')
            ->limit(100)
            ->get();

        $metrics = $this->stopQueryMonitoring();

        // Assert performance requirements
        $this->assertQueryPerformance($metrics, [
            'max_time_ms' => self::MAX_INDEX_SCAN_TIME_MS,
            'max_queries' => 1
        ]);

        // Assert no sequential scans
        $this->assertNoSequentialScans($metrics);

        // Verify results
        $this->assertGreaterThan(0, $depthTwoWorkstreams->count());

        // Verify composite index exists for hierarchy_depth + status
        $this->assertCompositeIndexExists('workstreams', ['hierarchy_depth', 'status']);
    }

    /**
     * Test that root workstream queries use proper indexes and perform well.
     *
     * Given: A large workstream hierarchy
     * When: Querying root workstreams (parent_workstream_id IS NULL)
     * Then: Query should use appropriate index and complete within time limits
     */
    public function test_root_workstream_queries_use_index_and_perform_well(): void
    {
        $this->startQueryMonitoring();

        // Query root workstreams - this should use a partial index on parent_workstream_id IS NULL
        $rootWorkstreams = Workstream::whereNull('parent_workstream_id')
            ->where('status', 'active')
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

        // Verify results
        $this->assertGreaterThan(0, $rootWorkstreams->count());

        // Verify partial index exists for NULL parent_workstream_id
        $partialIndexes = \DB::select("
            SELECT indexname, indexdef
            FROM pg_indexes
            WHERE tablename = 'workstreams'
            AND indexdef LIKE '%parent_workstream_id%'
            AND indexdef LIKE '%IS NULL%'
        ");

        $this->assertNotEmpty(
            $partialIndexes,
            'Partial index for NULL parent_workstream_id does not exist'
        );
    }

    /**
     * Test that workstream hierarchy path queries use proper indexes and perform well.
     *
     * Given: A deep workstream hierarchy
     * When: Querying full hierarchy paths (ancestors/descendants)
     * Then: Queries should use appropriate indexes and complete within time limits
     */
    public function test_hierarchy_path_queries_use_indexes_and_perform_well(): void
    {
        // Get a leaf workstream to test ancestor queries
        $leafWorkstream = Workstream::where('hierarchy_depth', 5)->first();

        $this->startQueryMonitoring();

        // This should use the optimized hierarchy methods that leverage indexes
        $ancestors = $leafWorkstream->getAllAncestors();

        $metrics = $this->stopQueryMonitoring();

        // Assert performance requirements for hierarchy traversal
        $this->assertQueryPerformance($metrics, [
            'max_time_ms' => self::MAX_BULK_OPERATION_TIME_MS,
            'max_queries' => 5 // Should be efficient with proper indexing
        ]);

        // Assert no sequential scans
        $this->assertNoSequentialScans($metrics);

        // Verify we got the expected number of ancestors
        $this->assertEquals(4, $ancestors->count()); // 4 levels above the leaf

        // Verify the hierarchy path index exists
        $this->assertCompositeIndexExists('workstreams', ['parent_workstream_id', 'hierarchy_depth']);
    }

    /**
     * Test that descendant queries use proper indexes and perform well.
     *
     * Given: A large workstream hierarchy
     * When: Querying all descendants of a root workstream
     * Then: Query should use appropriate indexes and complete within time limits
     */
    public function test_descendant_queries_use_indexes_and_perform_well(): void
    {
        $rootWorkstream = $this->hierarchyData['roots']->first();

        $this->startQueryMonitoring();

        // This should use the optimized hierarchy methods
        $descendants = $rootWorkstream->getAllDescendants();

        $metrics = $this->stopQueryMonitoring();

        // Assert performance requirements for large hierarchy traversal
        $this->assertQueryPerformance($metrics, [
            'max_time_ms' => self::MAX_BULK_OPERATION_TIME_MS,
            'max_queries' => 10 // Should be efficient with proper indexing
        ]);

        // Assert no sequential scans
        $this->assertNoSequentialScans($metrics);

        // Verify we got many descendants
        $this->assertGreaterThan(100, $descendants->count());
    }

    /**
     * Test that workstream permission queries use proper indexes and perform well.
     *
     * Given: Workstreams with various permission configurations
     * When: Querying workstreams by owner_id and permission inheritance
     * Then: Queries should use appropriate indexes and complete within time limits
     */
    public function test_workstream_permission_queries_use_indexes_and_perform_well(): void
    {
        $this->startQueryMonitoring();

        // Query workstreams by owner - this should use an index on owner_id
        $ownedWorkstreams = Workstream::where('owner_id', $this->testUser->id)
            ->where('status', 'active')
            ->whereNotNull('parent_workstream_id')
            ->orderBy('hierarchy_depth')
            ->get();

        $metrics = $this->stopQueryMonitoring();

        // Assert performance requirements
        $this->assertQueryPerformance($metrics, [
            'max_time_ms' => self::MAX_INDEX_SCAN_TIME_MS,
            'max_queries' => 1
        ]);

        // Assert no sequential scans
        $this->assertNoSequentialScans($metrics);

        // Verify composite index exists for owner_id + status
        $this->assertCompositeIndexExists('workstreams', ['owner_id', 'status']);
    }

    /**
     * Test that workstream type filtering queries use proper indexes and perform well.
     *
     * Given: Workstreams of various types
     * When: Querying workstreams by type with hierarchy constraints
     * Then: Queries should use appropriate indexes and complete within time limits
     */
    public function test_workstream_type_filtering_uses_indexes_and_performs_well(): void
    {
        $this->startQueryMonitoring();

        // Complex query filtering by type, depth, and status
        $initiatives = Workstream::where('type', 'initiative')
            ->where('hierarchy_depth', '<=', 3)
            ->where('status', 'active')
            ->whereNotNull('parent_workstream_id')
            ->orderBy('hierarchy_depth')
            ->orderBy('name')
            ->limit(100)
            ->get();

        $metrics = $this->stopQueryMonitoring();

        // Assert performance requirements
        $this->assertQueryPerformance($metrics, [
            'max_time_ms' => self::MAX_INDEX_SCAN_TIME_MS,
            'max_queries' => 1
        ]);

        // Assert no sequential scans
        $this->assertNoSequentialScans($metrics);

        // Verify composite index exists for type + hierarchy_depth + status
        $this->assertCompositeIndexExists('workstreams', ['type', 'hierarchy_depth', 'status']);
    }

    /**
     * Test that workstream hierarchy rollup queries use proper indexes and perform well.
     *
     * Given: A large workstream hierarchy with various statuses
     * When: Running rollup aggregation queries across hierarchy levels
     * Then: Queries should use appropriate indexes and complete within time limits
     */
    public function test_workstream_hierarchy_rollup_queries_use_indexes_and_perform_well(): void
    {
        $rootWorkstream = $this->hierarchyData['roots']->first();

        $this->startQueryMonitoring();

        // Complex rollup query using the optimized method
        $rollupData = $rootWorkstream->getRollupReport();

        $metrics = $this->stopQueryMonitoring();

        // Assert performance requirements for complex aggregation
        $this->assertQueryPerformance($metrics, [
            'max_time_ms' => self::MAX_BULK_OPERATION_TIME_MS,
            'max_queries' => 15 // Complex rollup may require multiple queries
        ]);

        // Assert no sequential scans
        $this->assertNoSequentialScans($metrics);

        // Verify rollup data structure
        $this->assertIsArray($rollupData);
        $this->assertArrayHasKey('total_workstreams', $rollupData);
        $this->assertArrayHasKey('by_status', $rollupData);
        $this->assertArrayHasKey('by_type', $rollupData);
    }

    /**
     * Test that circular hierarchy detection queries use proper indexes and perform well.
     *
     * Given: A large workstream hierarchy
     * When: Checking for circular hierarchy relationships
     * Then: Detection should use appropriate indexes and complete quickly
     */
    public function test_circular_hierarchy_detection_uses_indexes_and_performs_well(): void
    {
        $workstream = Workstream::where('hierarchy_depth', 3)->first();
        $potentialParent = Workstream::where('hierarchy_depth', 2)->first();

        $this->startQueryMonitoring();

        // Check for circular hierarchy - this should use optimized detection
        $wouldCreateCircular = $workstream->wouldCreateCircularHierarchy($potentialParent->id);

        $metrics = $this->stopQueryMonitoring();

        // Assert performance requirements for circular detection
        $this->assertQueryPerformance($metrics, [
            'max_time_ms' => self::MAX_INDEX_SCAN_TIME_MS,
            'max_queries' => 5 // Should be efficient with proper indexing
        ]);

        // Assert no sequential scans
        $this->assertNoSequentialScans($metrics);

        // Verify result (should not create circular relationship)
        $this->assertFalse($wouldCreateCircular);
    }

    /**
     * Test that workstream search queries use proper indexes and perform well.
     *
     * Given: A large number of workstreams with various names
     * When: Searching workstreams by name pattern
     * Then: Search should use text indexes and complete within time limits
     */
    public function test_workstream_search_queries_use_indexes_and_perform_well(): void
    {
        $this->startQueryMonitoring();

        // Search workstreams by name pattern - this should use a text index
        $searchResults = Workstream::where('name', 'ILIKE', '%Child 1-%')
            ->where('status', 'active')
            ->orderBy('hierarchy_depth')
            ->orderBy('name')
            ->limit(50)
            ->get();

        $metrics = $this->stopQueryMonitoring();

        // Assert performance requirements
        $this->assertQueryPerformance($metrics, [
            'max_time_ms' => self::MAX_BULK_OPERATION_TIME_MS, // Text search may be slower
            'max_queries' => 1
        ]);

        // Verify results
        $this->assertGreaterThan(0, $searchResults->count());

        // Verify text search index exists on name
        $textIndexes = \DB::select("
            SELECT indexname, indexdef
            FROM pg_indexes
            WHERE tablename = 'workstreams'
            AND (indexdef LIKE '%gin%' OR indexdef LIKE '%gist%')
            AND indexdef LIKE '%name%'
        ");

        $this->assertNotEmpty(
            $textIndexes,
            'Text search index on name column does not exist'
        );
    }

    /**
     * Test that bulk workstream hierarchy operations maintain performance.
     *
     * Given: A need to process workstream hierarchies in bulk
     * When: Running bulk operations on hierarchy data
     * Then: Operations should complete within acceptable time limits
     */
    public function test_bulk_workstream_hierarchy_operations_maintain_performance(): void
    {
        $this->startQueryMonitoring();

        // Bulk hierarchy depth update simulation
        $workstreamsToUpdate = Workstream::where('hierarchy_depth', 2)->limit(100)->get();

        foreach ($workstreamsToUpdate as $workstream) {
            // Simulate updating hierarchy depth (would normally trigger recalculation)
            $workstream->update(['status' => 'on_hold']);
        }

        $metrics = $this->stopQueryMonitoring();

        // Assert bulk operation performance
        $this->assertQueryPerformance($metrics, [
            'max_time_ms' => self::MAX_BULK_OPERATION_TIME_MS * 2, // Bulk operations may take longer
            'max_queries' => 202 // 1 select + 100 updates + 100 potential hierarchy recalculations
        ]);
    }

    /**
     * Test that workstream with relationships queries use proper eager loading and indexes.
     *
     * Given: Workstreams with various relationships (owner, parent, children)
     * When: Loading workstreams with their relationships
     * Then: Queries should use proper eager loading and indexes
     */
    public function test_workstream_with_relationships_uses_proper_eager_loading(): void
    {
        $this->startQueryMonitoring();

        // Load workstreams with essential relationships - should use eager loading
        $workstreamsWithRelations = Workstream::with([
                'owner:id,name,email',
                'parentWorkstream:id,name,type',
                'childWorkstreams:id,name,type,parent_workstream_id'
            ])
            ->where('hierarchy_depth', 2)
            ->orderBy('name')
            ->limit(50)
            ->get();

        $metrics = $this->stopQueryMonitoring();

        // Assert performance requirements with eager loading
        $this->assertQueryPerformance($metrics, [
            'max_time_ms' => self::MAX_BULK_OPERATION_TIME_MS,
            'max_queries' => 4 // Main query + 3 eager loaded relationships
        ]);

        // Assert no sequential scans
        $this->assertNoSequentialScans($metrics);

        // Verify we got results with relationships loaded
        $this->assertGreaterThan(0, $workstreamsWithRelations->count());

        // Verify first workstream has relationships loaded (no additional queries)
        $firstWorkstream = $workstreamsWithRelations->first();
        $this->assertTrue($firstWorkstream->relationLoaded('owner'));
        $this->assertTrue($firstWorkstream->relationLoaded('parentWorkstream'));
        $this->assertTrue($firstWorkstream->relationLoaded('childWorkstreams'));
    }
}
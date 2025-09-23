<?php

namespace Tests\Unit\Performance;

use App\Models\Feedback;
use App\Models\Output;
use App\Models\Input;
use App\Models\User;

/**
 * Tests database index performance for feedback queries.
 * Validates that critical feedback queries use proper indexes and perform within acceptable time limits.
 */
class DatabaseIndexFeedbackPerformanceTest extends BasePerformanceTest
{
    private array $testData;

    protected function setUp(): void
    {
        parent::setUp();

        // Create large dataset for realistic performance testing
        $this->testData = $this->createTestFeedbackData(self::LARGE_DATASET_SIZE);
    }

    /**
     * Test that feedback queries by user_id use proper index and perform well.
     *
     * Given: A large dataset of feedback records
     * When: Querying feedback by user_id
     * Then: Query should use user_id index and complete within time limits
     */
    public function test_feedback_queries_by_user_id_use_index_and_perform_well(): void
    {
        $this->startQueryMonitoring();

        // Query feedback by user - this should use an index on user_id
        $userFeedback = Feedback::where('user_id', $this->testUser->id)
            ->orderBy('created_at', 'desc')
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
        $this->assertGreaterThan(0, $userFeedback->count());

        // Verify index exists
        $this->assertIndexExists('feedback', 'feedback_user_id_index');
    }

    /**
     * Test that feedback queries by output_id use proper index and perform well.
     *
     * Given: A large dataset of feedback records
     * When: Querying feedback by output_id
     * Then: Query should use output_id index and complete within time limits
     */
    public function test_feedback_queries_by_output_id_use_index_and_perform_well(): void
    {
        $output = $this->testData['outputs']->first();

        $this->startQueryMonitoring();

        // Query feedback by output - this should use an index on output_id
        $outputFeedback = Feedback::where('output_id', $output->id)
            ->with(['user:id,name,email'])
            ->orderBy('created_at', 'desc')
            ->get();

        $metrics = $this->stopQueryMonitoring();

        // Assert performance requirements
        $this->assertQueryPerformance($metrics, [
            'max_time_ms' => self::MAX_INDEX_SCAN_TIME_MS,
            'max_queries' => 2 // Main query + user relation
        ]);

        // Assert no sequential scans
        $this->assertNoSequentialScans($metrics);

        // Verify index exists
        $this->assertIndexExists('feedback', 'feedback_output_id_index');
    }

    /**
     * Test that feedback queries by action type use proper index and perform well.
     *
     * Given: A large dataset of feedback records with various actions
     * When: Querying feedback by action type
     * Then: Query should use action index and complete within time limits
     */
    public function test_feedback_queries_by_action_use_index_and_perform_well(): void
    {
        $this->startQueryMonitoring();

        // Query feedback by action - this should use an index on action
        $actionFeedback = Feedback::where('action', 'thumbs_up')
            ->where('user_id', $this->testUser->id)
            ->orderBy('created_at', 'desc')
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

        // Verify composite index exists for user_id + action
        $this->assertCompositeIndexExists('feedback', ['user_id', 'action']);
    }

    /**
     * Test that feedback queries by confidence range use proper index and perform well.
     *
     * Given: A large dataset of feedback records with various confidence scores
     * When: Querying feedback by confidence range
     * Then: Query should use confidence index and complete within time limits
     */
    public function test_feedback_queries_by_confidence_range_use_index_and_perform_well(): void
    {
        $this->startQueryMonitoring();

        // Query feedback by confidence range - this should use an index on confidence
        $highConfidenceFeedback = Feedback::where('confidence', '>=', 0.8)
            ->where('user_id', $this->testUser->id)
            ->orderBy('confidence', 'desc')
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

        // Verify composite index exists for user_id + confidence
        $this->assertCompositeIndexExists('feedback', ['user_id', 'confidence']);
    }

    /**
     * Test that feedback queries by signal type use proper index and perform well.
     *
     * Given: A large dataset of feedback records with different signal types
     * When: Querying feedback by signal type
     * Then: Query should use signal_type index and complete within time limits
     */
    public function test_feedback_queries_by_signal_type_use_index_and_perform_well(): void
    {
        $this->startQueryMonitoring();

        // Query feedback by signal type - this should use an index on signal_type
        $explicitFeedback = Feedback::where('signal_type', 'explicit')
            ->where('user_id', $this->testUser->id)
            ->where('type', 'inline')
            ->orderBy('created_at', 'desc')
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

        // Verify composite index exists for user_id + signal_type + type
        $this->assertCompositeIndexExists('feedback', ['user_id', 'signal_type', 'type']);
    }

    /**
     * Test that complex feedback analytics queries use proper indexes and perform well.
     *
     * Given: A large dataset of feedback records
     * When: Running complex analytics queries (aggregations, joins)
     * Then: Queries should use proper indexes and complete within time limits
     */
    public function test_complex_feedback_analytics_queries_use_indexes_and_perform_well(): void
    {
        $this->startQueryMonitoring();

        // Complex analytics query with aggregations and joins
        $analytics = Feedback::select([
                'feedback.action',
                'feedback.signal_type',
                \DB::raw('COUNT(*) as count'),
                \DB::raw('AVG(feedback.confidence) as avg_confidence'),
                \DB::raw('MIN(feedback.created_at) as first_feedback'),
                \DB::raw('MAX(feedback.created_at) as last_feedback')
            ])
            ->join('outputs', 'feedback.output_id', '=', 'outputs.id')
            ->join('inputs', 'outputs.input_id', '=', 'inputs.id')
            ->where('feedback.user_id', $this->testUser->id)
            ->where('feedback.created_at', '>=', now()->subDays(30))
            ->groupBy(['feedback.action', 'feedback.signal_type'])
            ->orderBy('count', 'desc')
            ->get();

        $metrics = $this->stopQueryMonitoring();

        // Assert performance requirements for complex query
        $this->assertQueryPerformance($metrics, [
            'max_time_ms' => self::MAX_BULK_OPERATION_TIME_MS,
            'max_queries' => 1
        ]);

        // Assert no sequential scans
        $this->assertNoSequentialScans($metrics);

        // Verify results
        $this->assertGreaterThan(0, $analytics->count());

        // Verify required indexes exist for the joins and filters
        $this->assertIndexExists('feedback', 'feedback_user_id_created_at_index');
        $this->assertIndexExists('outputs', 'outputs_input_id_index');
    }

    /**
     * Test that feedback aggregation queries by date ranges use proper indexes.
     *
     * Given: A large dataset of feedback records across different dates
     * When: Querying feedback aggregations by date ranges
     * Then: Queries should use date-based indexes and complete within time limits
     */
    public function test_feedback_date_range_aggregations_use_indexes_and_perform_well(): void
    {
        $this->startQueryMonitoring();

        // Date range aggregation query
        $dateRangeStats = Feedback::select([
                \DB::raw('DATE(created_at) as feedback_date'),
                \DB::raw('COUNT(*) as total_feedback'),
                \DB::raw('SUM(CASE WHEN action = \'thumbs_up\' THEN 1 ELSE 0 END) as positive_feedback'),
                \DB::raw('SUM(CASE WHEN action = \'thumbs_down\' THEN 1 ELSE 0 END) as negative_feedback'),
                \DB::raw('AVG(confidence) as avg_confidence')
            ])
            ->where('user_id', $this->testUser->id)
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy(\DB::raw('DATE(created_at)'))
            ->orderBy('feedback_date', 'desc')
            ->get();

        $metrics = $this->stopQueryMonitoring();

        // Assert performance requirements
        $this->assertQueryPerformance($metrics, [
            'max_time_ms' => self::MAX_BULK_OPERATION_TIME_MS,
            'max_queries' => 1
        ]);

        // Assert no sequential scans
        $this->assertNoSequentialScans($metrics);

        // Verify composite index exists for user_id + created_at
        $this->assertCompositeIndexExists('feedback', ['user_id', 'created_at']);
    }

    /**
     * Test that bulk feedback operations maintain performance.
     *
     * Given: A need to process feedback in bulk
     * When: Running bulk operations (inserts, updates, deletes)
     * Then: Operations should complete within acceptable time limits
     */
    public function test_bulk_feedback_operations_maintain_performance(): void
    {
        // Test bulk insert performance
        $this->startQueryMonitoring();

        $newOutputs = Output::factory(100)->create([
            'input_id' => Input::factory()->create()->id
        ]);

        $bulkFeedbackData = [];
        foreach ($newOutputs as $output) {
            $bulkFeedbackData[] = [
                'output_id' => $output->id,
                'user_id' => $this->testUser->id,
                'type' => 'inline',
                'action' => 'thumbs_up',
                'signal_type' => 'explicit',
                'confidence' => 0.95,
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

        // Bulk insert
        Feedback::insert($bulkFeedbackData);

        $metrics = $this->stopQueryMonitoring();

        // Assert bulk operation performance
        $this->assertQueryPerformance($metrics, [
            'max_time_ms' => self::MAX_BULK_OPERATION_TIME_MS,
            'max_queries' => 102 // 100 Output creates + 1 Input create + 1 bulk insert
        ]);
    }

    /**
     * Test that feedback queries with metadata filtering use appropriate indexes.
     *
     * Given: Feedback records with various metadata structures
     * When: Querying feedback by metadata fields
     * Then: Queries should use GIN indexes on metadata and perform well
     */
    public function test_feedback_metadata_queries_use_gin_indexes_and_perform_well(): void
    {
        // First create some feedback with metadata
        $outputs = Output::factory(50)->create();
        foreach ($outputs as $output) {
            Feedback::factory()->create([
                'output_id' => $output->id,
                'user_id' => $this->testUser->id,
                'metadata' => [
                    'source' => 'ui_interaction',
                    'session_id' => 'test_session_' . rand(1, 10),
                    'page_url' => 'https://example.com/page/' . rand(1, 5),
                    'user_agent' => 'test_browser'
                ]
            ]);
        }

        $this->startQueryMonitoring();

        // Query feedback by metadata - this should use a GIN index on metadata
        $metadataFeedback = Feedback::where('user_id', $this->testUser->id)
            ->where('metadata->source', 'ui_interaction')
            ->where('metadata->session_id', 'test_session_1')
            ->orderBy('created_at', 'desc')
            ->get();

        $metrics = $this->stopQueryMonitoring();

        // Assert performance requirements
        $this->assertQueryPerformance($metrics, [
            'max_time_ms' => self::MAX_INDEX_SCAN_TIME_MS,
            'max_queries' => 1
        ]);

        // Assert no sequential scans
        $this->assertNoSequentialScans($metrics);

        // Verify GIN index exists on metadata column
        $ginIndexes = \DB::select("
            SELECT indexname, indexdef
            FROM pg_indexes
            WHERE tablename = 'feedback'
            AND indexdef LIKE '%gin%'
            AND indexdef LIKE '%metadata%'
        ");

        $this->assertNotEmpty(
            $ginIndexes,
            'GIN index on metadata column does not exist'
        );
    }
}
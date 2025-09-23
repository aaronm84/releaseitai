<?php

namespace Tests\Unit\Database;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class PerformanceIndexValidationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that critical performance indexes exist and are properly configured
     *
     * Given: Database migrations for performance optimization
     * When: Checking for critical performance indexes
     * Then: Should have all required indexes for feedback learning queries
     */
    public function test_critical_performance_indexes_exist()
    {
        // Feedback table critical indexes
        $this->assertTrue(
            Schema::hasIndex('feedback', 'idx_feedback_user_action_confidence'),
            'Missing critical composite index for user analytics queries'
        );

        $this->assertTrue(
            Schema::hasIndex('feedback', 'idx_feedback_created_action'),
            'Missing index for timeline analysis queries'
        );

        $this->assertTrue(
            Schema::hasIndex('feedback', 'idx_feedback_signal_confidence'),
            'Missing index for signal type filtering'
        );

        // Embeddings performance indexes
        $this->assertTrue(
            Schema::hasIndex('embeddings', 'idx_embeddings_model_dimensions'),
            'Missing index for model-specific embedding queries'
        );

        $this->assertTrue(
            Schema::hasIndex('embeddings', 'idx_embeddings_type_created'),
            'Missing index for content type temporal analysis'
        );

        // Inputs analytics indexes
        $this->assertTrue(
            Schema::hasIndex('inputs', 'idx_inputs_type_source_created'),
            'Missing composite index for input analysis'
        );

        // Outputs performance indexes
        $this->assertTrue(
            Schema::hasIndex('outputs', 'idx_outputs_quality_feedback'),
            'Missing index for quality analysis'
        );

        $this->assertTrue(
            Schema::hasIndex('outputs', 'idx_outputs_parent_version'),
            'Missing index for output evolution tracking'
        );

        $this->assertTrue(
            Schema::hasIndex('outputs', 'idx_outputs_model_quality_created'),
            'Missing composite index for AI model performance analysis'
        );
    }

    /**
     * Test PostgreSQL-specific GIN indexes for JSON metadata
     *
     * Given: PostgreSQL database with JSON columns
     * When: Checking for GIN indexes on metadata columns
     * Then: Should have GIN indexes for fast JSON queries
     */
    public function test_postgresql_gin_indexes_exist()
    {
        // Skip if not using PostgreSQL
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('GIN index tests require PostgreSQL');
        }

        // Check for GIN indexes using PostgreSQL system catalogs
        $ginIndexes = DB::select("
            SELECT indexname, tablename
            FROM pg_indexes
            WHERE indexname IN (
                'idx_feedback_metadata_gin',
                'idx_inputs_metadata_gin',
                'idx_outputs_metadata_gin'
            )
            ORDER BY indexname
        ");

        $this->assertCount(3, $ginIndexes, 'Missing GIN indexes for JSON metadata');

        $indexNames = collect($ginIndexes)->pluck('indexname')->toArray();
        $this->assertContains('idx_feedback_metadata_gin', $indexNames);
        $this->assertContains('idx_inputs_metadata_gin', $indexNames);
        $this->assertContains('idx_outputs_metadata_gin', $indexNames);
    }

    /**
     * Test partial indexes for optimization
     *
     * Given: PostgreSQL database with partial indexes
     * When: Checking for partial index on high confidence feedback
     * Then: Should have partial index for performance optimization
     */
    public function test_partial_indexes_exist()
    {
        // Skip if not using PostgreSQL
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Partial index tests require PostgreSQL');
        }

        // Check for partial index on high confidence feedback
        $partialIndexes = DB::select("
            SELECT indexname, tablename, indexdef
            FROM pg_indexes
            WHERE indexname = 'idx_feedback_high_confidence'
        ");

        $this->assertCount(1, $partialIndexes, 'Missing partial index for high confidence feedback');

        $indexDef = $partialIndexes[0]->indexdef;
        $this->assertStringContainsString('WHERE', $indexDef, 'Partial index should have WHERE clause');
        $this->assertStringContainsString('confidence', $indexDef, 'Partial index should filter on confidence');
    }

    /**
     * Test query performance with indexes
     *
     * Given: Database with performance indexes
     * When: Running common feedback learning queries
     * Then: Should use indexes and avoid sequential scans
     */
    public function test_query_performance_optimization()
    {
        // Skip if not using PostgreSQL (EXPLAIN format is database-specific)
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Query performance tests require PostgreSQL');
        }

        // Create minimal test data to ensure indexes are considered
        $userId = DB::table('users')->insertGetId([
            'name' => 'Performance Test User',
            'email' => 'perf@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $inputId = DB::table('inputs')->insertGetId([
            'content' => 'Performance test input',
            'type' => 'brain_dump',
            'source' => 'manual_entry',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $outputId = DB::table('outputs')->insertGetId([
            'input_id' => $inputId,
            'content' => 'Performance test output',
            'type' => 'checklist',
            'ai_model' => 'claude-3-5-sonnet',
            'quality_score' => 0.85,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Insert some feedback data
        for ($i = 0; $i < 5; $i++) {
            DB::table('feedback')->insert([
                'output_id' => $outputId,
                'user_id' => $userId,
                'type' => 'inline',
                'action' => $i % 2 === 0 ? 'edit' : 'accept',
                'signal_type' => 'explicit',
                'confidence' => 0.8 + ($i * 0.05),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        // Test composite index usage for user analytics
        $explainResult = DB::select("
            EXPLAIN (FORMAT JSON)
            SELECT * FROM feedback
            WHERE user_id = ? AND action = 'edit' AND confidence >= 0.7
        ", [$userId]);

        $explainJson = json_decode($explainResult[0]->{"QUERY PLAN"}, true);
        $executionPlan = $explainJson[0]['Plan'];

        // Should use index scan, not sequential scan for this query pattern
        $this->assertNotEquals('Seq Scan', $executionPlan['Node Type'],
            'Query should use index, not sequential scan');

        // Test that the query actually returns expected results
        $results = DB::table('feedback')
            ->where('user_id', $userId)
            ->where('action', 'edit')
            ->where('confidence', '>=', 0.7)
            ->get();

        $this->assertGreaterThan(0, $results->count(), 'Should find edit feedback with high confidence');
    }

    /**
     * Test workstream hierarchy indexes if they exist
     *
     * Given: Workstreams table with hierarchy support
     * When: Checking for hierarchy optimization indexes
     * Then: Should have indexes for hierarchy traversal
     */
    public function test_workstream_hierarchy_indexes()
    {
        // Only test if workstreams table exists
        if (!Schema::hasTable('workstreams')) {
            $this->markTestSkipped('Workstreams table does not exist');
        }

        // Check for hierarchy depth index
        if (Schema::hasColumn('workstreams', 'hierarchy_depth')) {
            $this->assertTrue(
                Schema::hasIndex('workstreams', 'idx_workstreams_hierarchy_depth'),
                'Missing index for hierarchy depth filtering'
            );
        }

        // Check for parent-depth composite index
        if (Schema::hasColumns('workstreams', ['parent_workstream_id', 'hierarchy_depth'])) {
            $this->assertTrue(
                Schema::hasIndex('workstreams', 'idx_workstreams_parent_depth'),
                'Missing composite index for hierarchy traversal'
            );
        }
    }

    /**
     * Test index naming consistency and standards
     *
     * Given: Performance indexes migration
     * When: Checking index naming patterns
     * Then: Should follow consistent naming conventions
     */
    public function test_index_naming_conventions()
    {
        // Get all our performance indexes
        $performanceIndexes = DB::select("
            SELECT indexname, tablename
            FROM pg_indexes
            WHERE indexname LIKE 'idx_%'
            AND (indexname LIKE '%feedback%' OR indexname LIKE '%inputs%' OR indexname LIKE '%outputs%' OR indexname LIKE '%embeddings%')
            ORDER BY tablename, indexname
        ");

        foreach ($performanceIndexes as $index) {
            // All indexes should start with 'idx_'
            $this->assertStringStartsWith('idx_', $index->indexname,
                "Index {$index->indexname} should follow naming convention");

            // Index name should include table name
            $tableName = $index->tablename;
            $this->assertStringContainsString($tableName, $index->indexname,
                "Index {$index->indexname} should include table name {$tableName}");
        }

        // Ensure we have a reasonable number of performance indexes
        $this->assertGreaterThanOrEqual(10, count($performanceIndexes),
            'Should have sufficient performance indexes for optimization');
    }
}
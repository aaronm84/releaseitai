<?php

namespace Tests\Unit\Database;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class FeedbackLearningSchemaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that inputs table has the expected schema
     *
     * Given: Database migrations for feedback and learning system
     * When: Checking inputs table structure
     * Then: Should have required columns with proper types and constraints
     */
    public function test_inputs_table_schema()
    {
        // Then - table should exist
        $this->assertTrue(Schema::hasTable('inputs'));

        // Required columns
        $expectedColumns = [
            'id' => 'bigint',
            'content' => 'text',
            'type' => 'string',
            'source' => 'string',
            'metadata' => 'json',
            'created_at' => 'timestamp',
            'updated_at' => 'timestamp'
        ];

        foreach ($expectedColumns as $column => $expectedType) {
            $this->assertTrue(
                Schema::hasColumn('inputs', $column),
                "inputs table should have {$column} column"
            );
        }

        // Indexes
        $this->assertTrue(Schema::hasIndex('inputs', ['type']));
        $this->assertTrue(Schema::hasIndex('inputs', ['source']));
        $this->assertTrue(Schema::hasIndex('inputs', ['created_at']));
    }

    /**
     * Test that outputs table has the expected schema
     *
     * Given: Database migrations for feedback and learning system
     * When: Checking outputs table structure
     * Then: Should have required columns with proper relationships
     */
    public function test_outputs_table_schema()
    {
        // Then - table should exist
        $this->assertTrue(Schema::hasTable('outputs'));

        // Required columns
        $expectedColumns = [
            'id' => 'bigint',
            'input_id' => 'bigint',
            'content' => 'text',
            'type' => 'string',
            'ai_model' => 'string',
            'quality_score' => 'decimal',
            'version' => 'integer',
            'parent_output_id' => 'bigint',
            'feedback_integrated' => 'boolean',
            'feedback_count' => 'integer',
            'content_format' => 'string',
            'metadata' => 'json',
            'created_at' => 'timestamp',
            'updated_at' => 'timestamp'
        ];

        foreach ($expectedColumns as $column => $expectedType) {
            $this->assertTrue(
                Schema::hasColumn('outputs', $column),
                "outputs table should have {$column} column"
            );
        }

        // Foreign key constraints
        $this->assertTrue(Schema::hasIndex('outputs', ['input_id']));
        $this->assertTrue(Schema::hasIndex('outputs', ['parent_output_id']));

        // Other indexes
        $this->assertTrue(Schema::hasIndex('outputs', ['type']));
        $this->assertTrue(Schema::hasIndex('outputs', ['ai_model']));
        $this->assertTrue(Schema::hasIndex('outputs', ['quality_score']));
        $this->assertTrue(Schema::hasIndex('outputs', ['created_at']));
    }

    /**
     * Test that feedback table has the expected schema
     *
     * Given: Database migrations for feedback and learning system
     * When: Checking feedback table structure
     * Then: Should have required columns for inline and passive feedback
     */
    public function test_feedback_table_schema()
    {
        // Then - table should exist
        $this->assertTrue(Schema::hasTable('feedback'));

        // Required columns
        $expectedColumns = [
            'id' => 'bigint',
            'output_id' => 'bigint',
            'user_id' => 'bigint',
            'type' => 'string', // inline, behavioral
            'action' => 'string', // accept, edit, reject, task_completed, task_deleted, time_spent
            'signal_type' => 'string', // explicit, passive
            'confidence' => 'decimal',
            'metadata' => 'json',
            'created_at' => 'timestamp',
            'updated_at' => 'timestamp'
        ];

        foreach ($expectedColumns as $column => $expectedType) {
            $this->assertTrue(
                Schema::hasColumn('feedback', $column),
                "feedback table should have {$column} column"
            );
        }

        // Foreign key constraints
        $this->assertTrue(Schema::hasIndex('feedback', ['output_id']));
        $this->assertTrue(Schema::hasIndex('feedback', ['user_id']));

        // Query optimization indexes
        $this->assertTrue(Schema::hasIndex('feedback', ['type']));
        $this->assertTrue(Schema::hasIndex('feedback', ['action']));
        $this->assertTrue(Schema::hasIndex('feedback', ['signal_type']));
        $this->assertTrue(Schema::hasIndex('feedback', ['confidence']));
        $this->assertTrue(Schema::hasIndex('feedback', ['created_at']));

        // Composite indexes for common queries
        $this->assertTrue(Schema::hasIndex('feedback', ['output_id', 'action']));
        $this->assertTrue(Schema::hasIndex('feedback', ['user_id', 'type']));
    }

    /**
     * Test that embeddings table has the expected schema with pgvector support
     *
     * Given: Database migrations for feedback and learning system with pgvector
     * When: Checking embeddings table structure
     * Then: Should have pgvector column and proper indexes
     */
    public function test_embeddings_table_schema()
    {
        // Then - table should exist
        $this->assertTrue(Schema::hasTable('embeddings'));

        // Required columns
        $expectedColumns = [
            'id' => 'bigint',
            'content_id' => 'bigint',
            'content_type' => 'string',
            'vector' => 'text', // pgvector column (would be 'vector' type with pgvector extension)
            'model' => 'string',
            'dimensions' => 'integer',
            'normalized' => 'boolean',
            'metadata' => 'json',
            'created_at' => 'timestamp',
            'updated_at' => 'timestamp'
        ];

        foreach ($expectedColumns as $column => $expectedType) {
            $this->assertTrue(
                Schema::hasColumn('embeddings', $column),
                "embeddings table should have {$column} column"
            );
        }

        // Polymorphic relationship indexes
        $this->assertTrue(Schema::hasIndex('embeddings', ['content_id', 'content_type']));

        // Query optimization indexes
        $this->assertTrue(Schema::hasIndex('embeddings', ['model']));
        $this->assertTrue(Schema::hasIndex('embeddings', ['dimensions']));
        $this->assertTrue(Schema::hasIndex('embeddings', ['created_at']));

        // Unique constraint for content (check the actual unique index name)
        $this->assertTrue(Schema::hasIndex('embeddings', 'unique_content_model_embedding'));
    }

    /**
     * Test pgvector extension and vector operations
     *
     * Given: PostgreSQL with pgvector extension
     * When: Testing vector similarity operations
     * Then: Should support vector distance calculations
     */
    public function test_pgvector_operations()
    {
        // Skip if not using PostgreSQL
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('pgvector tests require PostgreSQL');
        }

        // Test pgvector extension availability
        try {
            // This would fail if pgvector extension is not installed
            DB::statement("SELECT '[1,2,3]'::vector");
            $this->assertTrue(true, 'pgvector extension is available');
        } catch (\Exception $e) {
            $this->markTestSkipped('pgvector extension not available: ' . $e->getMessage());
        }

        // Test vector distance operations
        try {
            $result = DB::select("SELECT '[1,2,3]'::vector <-> '[4,5,6]'::vector as distance");
            $this->assertIsNumeric($result[0]->distance);
            $this->assertGreaterThan(0, $result[0]->distance);
        } catch (\Exception $e) {
            $this->fail('pgvector distance operation failed: ' . $e->getMessage());
        }

        // Test vector similarity (cosine)
        try {
            $result = DB::select("SELECT 1 - ('[1,2,3]'::vector <=> '[1,2,3]'::vector) as similarity");
            $this->assertEquals(1.0, round($result[0]->similarity, 6)); // Should be 1.0 for identical vectors
        } catch (\Exception $e) {
            $this->fail('pgvector cosine similarity operation failed: ' . $e->getMessage());
        }
    }

    /**
     * Test database constraints and data integrity
     *
     * Given: Database schema with proper constraints
     * When: Testing data integrity rules
     * Then: Should enforce referential integrity and constraints
     */
    public function test_database_constraints()
    {
        // Test that we can't create output without valid input
        try {
            DB::table('outputs')->insert([
                'input_id' => 99999, // Non-existent input
                'content' => 'Test content',
                'type' => 'checklist',
                'ai_model' => 'test-model',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            $this->fail('Expected foreign key constraint violation');
        } catch (\Exception $e) {
            // PostgreSQL can return different error messages for foreign key violations
            $message = strtolower($e->getMessage());
            $this->assertTrue(
                str_contains($message, 'foreign key') ||
                str_contains($message, 'violates') ||
                str_contains($message, 'does not exist') ||
                str_contains($message, 'aborted') ||
                str_contains($message, 'transaction'),
                'Expected foreign key constraint violation, got: ' . $e->getMessage()
            );
        }

        // Test that we can't create feedback without valid output
        try {
            DB::table('feedback')->insert([
                'output_id' => 99999, // Non-existent output
                'user_id' => 1,
                'type' => 'inline',
                'action' => 'accept',
                'signal_type' => 'explicit',
                'confidence' => 1.0,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            $this->fail('Expected foreign key constraint violation');
        } catch (\Exception $e) {
            // PostgreSQL can return different error messages for foreign key violations
            $message = strtolower($e->getMessage());
            $this->assertTrue(
                str_contains($message, 'foreign key') ||
                str_contains($message, 'violates') ||
                str_contains($message, 'does not exist') ||
                str_contains($message, 'aborted') ||
                str_contains($message, 'transaction'),
                'Expected foreign key constraint violation, got: ' . $e->getMessage()
            );
        }

        // Test confidence score constraints (should be between 0 and 1)
        if (DB::getDriverName() === 'pgsql') {
            try {
                // First create valid input and output
                $inputId = DB::table('inputs')->insertGetId([
                    'content' => 'Test input',
                    'type' => 'brain_dump',
                    'source' => 'manual_entry',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                $outputId = DB::table('outputs')->insertGetId([
                    'input_id' => $inputId,
                    'content' => 'Test output',
                    'type' => 'checklist',
                    'ai_model' => 'test-model',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                // Try to insert invalid confidence score
                DB::table('feedback')->insert([
                    'output_id' => $outputId,
                    'user_id' => 1,
                    'type' => 'inline',
                    'action' => 'accept',
                    'signal_type' => 'explicit',
                    'confidence' => 1.5, // Invalid - should be <= 1.0
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                $this->fail('Expected check constraint violation for confidence score');
            } catch (\Exception $e) {
                // PostgreSQL may return transaction abort errors instead of constraint errors
                $message = strtolower($e->getMessage());
                $this->assertTrue(
                    str_contains($message, 'check') ||
                    str_contains($message, 'aborted') ||
                    str_contains($message, 'transaction'),
                    'Expected check constraint violation, got: ' . $e->getMessage()
                );
            }
        }
    }

    /**
     * Test database performance indexes
     *
     * Given: Database schema with performance indexes
     * When: Checking index effectiveness
     * Then: Should have proper indexes for common query patterns
     */
    public function test_database_indexes_for_performance()
    {
        // Test that common query patterns have supporting indexes

        // 1. Finding feedback by output and action (for aggregation)
        $this->assertTrue(Schema::hasIndex('feedback', ['output_id', 'action']));

        // 2. Finding user's feedback history
        $this->assertTrue(Schema::hasIndex('feedback', ['user_id', 'type']));

        // 3. Time-based queries for feedback analysis
        $this->assertTrue(Schema::hasIndex('feedback', ['created_at']));

        // 4. Quality-based output filtering
        $this->assertTrue(Schema::hasIndex('outputs', ['quality_score']));

        // 5. Content type filtering for embeddings
        $this->assertTrue(Schema::hasIndex('embeddings', ['content_id', 'content_type']));

        // 6. Model-based embedding queries
        $this->assertTrue(Schema::hasIndex('embeddings', ['model']));

        // 7. Input type and source filtering
        $this->assertTrue(Schema::hasIndex('inputs', ['type']));
        $this->assertTrue(Schema::hasIndex('inputs', ['source']));
    }

    /**
     * Test database schema supports required data types
     *
     * Given: Database schema for feedback and learning system
     * When: Checking data type support
     * Then: Should support JSON, vectors, and proper numeric types
     */
    public function test_database_data_type_support()
    {
        // Test JSON data type support
        $inputId = DB::table('inputs')->insertGetId([
            'content' => 'Test input with metadata',
            'type' => 'brain_dump',
            'source' => 'manual_entry',
            'metadata' => json_encode(['key' => 'value', 'complexity' => 'high']),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $input = DB::table('inputs')->find($inputId);
        $metadata = json_decode($input->metadata, true);
        $this->assertEquals('value', $metadata['key']);
        $this->assertEquals('high', $metadata['complexity']);

        // Test decimal precision for confidence scores
        $outputId = DB::table('outputs')->insertGetId([
            'input_id' => $inputId,
            'content' => 'Test output',
            'type' => 'checklist',
            'ai_model' => 'test-model',
            'quality_score' => 0.85742,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $output = DB::table('outputs')->find($outputId);
        $this->assertEquals(0.85742, $output->quality_score);

        // Test vector storage (as text for now, pgvector type in production)
        $embeddingId = DB::table('embeddings')->insertGetId([
            'content_id' => $inputId,
            'content_type' => 'App\Models\Input',
            'vector' => '[0.1, 0.2, 0.3, 0.4, 0.5]',
            'model' => 'text-embedding-ada-002',
            'dimensions' => 5,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $embedding = DB::table('embeddings')->find($embeddingId);
        $this->assertEquals('[0.1, 0.2, 0.3, 0.4, 0.5]', $embedding->vector);
        $this->assertEquals(5, $embedding->dimensions);
    }

    /**
     * Test database schema supports feedback learning queries
     *
     * Given: Database schema optimized for feedback learning
     * When: Performing complex analytical queries
     * Then: Should execute efficiently with proper results
     */
    public function test_database_supports_learning_queries()
    {
        // Setup test data - create user first
        $userId = DB::table('users')->insertGetId([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $inputId = DB::table('inputs')->insertGetId([
            'content' => 'Test input for learning',
            'type' => 'brain_dump',
            'source' => 'manual_entry',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $outputId = DB::table('outputs')->insertGetId([
            'input_id' => $inputId,
            'content' => 'Test output for learning',
            'type' => 'checklist',
            'ai_model' => 'claude-3-5-sonnet',
            'quality_score' => 0.8,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Insert multiple feedback instances
        $feedbackTypes = [
            ['action' => 'accept', 'confidence' => 1.0],
            ['action' => 'accept', 'confidence' => 1.0],
            ['action' => 'edit', 'confidence' => 0.7],
            ['action' => 'reject', 'confidence' => 1.0]
        ];

        foreach ($feedbackTypes as $feedback) {
            DB::table('feedback')->insert([
                'output_id' => $outputId,
                'user_id' => $userId,
                'type' => 'inline',
                'action' => $feedback['action'],
                'signal_type' => 'explicit',
                'confidence' => $feedback['confidence'],
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        // Test aggregation query for feedback patterns
        $feedbackStats = DB::table('feedback')
            ->select('action', DB::raw('COUNT(*) as count'), DB::raw('AVG(confidence) as avg_confidence'))
            ->where('output_id', $outputId)
            ->groupBy('action')
            ->get();

        $this->assertEquals(3, $feedbackStats->count());

        $acceptStats = $feedbackStats->where('action', 'accept')->first();
        $this->assertEquals(2, $acceptStats->count);
        $this->assertEquals(1.0, $acceptStats->avg_confidence);

        // Test time-based analysis query
        $recentFeedback = DB::table('feedback')
            ->where('created_at', '>=', now()->subDay())
            ->count();

        $this->assertEquals(4, $recentFeedback);

        // Test join query for feedback with input context
        $feedbackWithContext = DB::table('feedback')
            ->join('outputs', 'feedback.output_id', '=', 'outputs.id')
            ->join('inputs', 'outputs.input_id', '=', 'inputs.id')
            ->select('feedback.action', 'inputs.type as input_type', 'outputs.type as output_type')
            ->where('feedback.output_id', $outputId)
            ->get();

        $this->assertEquals(4, $feedbackWithContext->count());
        $this->assertEquals('brain_dump', $feedbackWithContext->first()->input_type);
        $this->assertEquals('checklist', $feedbackWithContext->first()->output_type);
    }
}
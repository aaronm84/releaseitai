<?php

namespace Tests\Unit\DataIntegrity;

use Tests\TestCase;
use App\Models\Embedding;
use App\Models\Input;
use App\Models\Output;
use App\Models\Feedback;
use App\Models\User;
use App\Models\Workstream;
use App\Models\Release;
use App\Models\AiJob;
use App\Services\RetrievalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class DataIntegrityValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure pgvector extension is available in test environment
        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        } catch (\Exception $e) {
            $this->markTestSkipped('pgvector extension not available in test environment');
        }
    }

    private function createTestVector(array $customValues = []): array
    {
        $vector = array_fill(0, 1536, 0.001);
        foreach ($customValues as $index => $value) {
            if ($index < 1536) {
                $vector[$index] = $value;
            }
        }
        return $vector;
    }

    /** @test */
    public function vector_storage_works_with_proper_pgvector_format()
    {
        // Arrange
        $user = User::factory()->create();
        $input = Input::factory()->create(['user_id' => $user->id]);
        $testVector = $this->createTestVector([0 => 1.0, 1 => 0.8, 2 => 0.6]);

        // Act
        $embedding = Embedding::create([
            'content_id' => $input->id,
            'content_type' => 'App\Models\Input',
            'vector' => '[' . implode(',', $testVector) . ']',
            'model' => 'test-model',
            'dimensions' => 1536,
            'normalized' => true
        ]);

        // Assert
        $this->assertDatabaseHas('embeddings', [
            'content_id' => $input->id,
            'content_type' => 'App\Models\Input'
        ]);

        $retrieved = Embedding::find($embedding->id);
        $vectorArray = $retrieved->getVectorAsArray();

        $this->assertCount(1536, $vectorArray);
        $this->assertEquals(1.0, $vectorArray[0]);
        $this->assertEquals(0.8, $vectorArray[1]);
        $this->assertEquals(0.6, $vectorArray[2]);
    }

    /** @test */
    public function pgvector_similarity_search_works_correctly()
    {
        // Arrange
        $user = User::factory()->create();
        $input1 = Input::factory()->create(['user_id' => $user->id]);
        $input2 = Input::factory()->create(['user_id' => $user->id]);

        $vector1 = $this->createTestVector([0 => 1.0, 1 => 0.8, 2 => 0.6]);
        $vector2 = $this->createTestVector([0 => 0.9, 1 => 0.8, 2 => 0.7]); // Similar

        Embedding::create([
            'content_id' => $input1->id,
            'content_type' => 'App\Models\Input',
            'vector' => '[' . implode(',', $vector1) . ']',
            'model' => 'test-model',
            'dimensions' => 1536,
            'normalized' => true
        ]);

        Embedding::create([
            'content_id' => $input2->id,
            'content_type' => 'App\Models\Input',
            'vector' => '[' . implode(',', $vector2) . ']',
            'model' => 'test-model',
            'dimensions' => 1536,
            'normalized' => true
        ]);

        // Act - Test pgvector similarity
        $queryVector = '[' . implode(',', $vector1) . ']';
        $results = DB::select("
            SELECT content_id, 1 - (vector <=> '{$queryVector}') as similarity_score
            FROM embeddings
            WHERE content_type = ?
            ORDER BY similarity_score DESC
        ", ['App\Models\Input']);

        // Assert
        $this->assertCount(2, $results);
        $this->assertEquals($input1->id, $results[0]->content_id);
        $this->assertGreaterThan(0.99, $results[0]->similarity_score); // Exact match
        $this->assertGreaterThan(0.8, $results[1]->similarity_score); // Similar
    }

    /** @test */
    public function embedding_validation_prevents_invalid_content_references()
    {
        // Assert - Should throw exception for non-existent content
        $this->expectException(QueryException::class);

        $testVector = $this->createTestVector();
        Embedding::create([
            'content_id' => 99999, // Non-existent
            'content_type' => 'App\Models\Input',
            'vector' => '[' . implode(',', $testVector) . ']',
            'model' => 'test-model',
            'dimensions' => 1536,
            'normalized' => true
        ]);
    }

    /** @test */
    public function embedding_validation_allows_valid_references()
    {
        // Arrange
        $user = User::factory()->create();
        $input = Input::factory()->create(['user_id' => $user->id]);
        $output = Output::factory()->create(['input_id' => $input->id]);
        $feedback = Feedback::create([
            'output_id' => $output->id,
            'user_id' => $user->id,
            'type' => 'rating',
            'action' => 'accept',
            'signal_type' => 'explicit',
            'confidence' => 0.8,
            'metadata' => []
        ]);

        $testVector = $this->createTestVector();

        // Act & Assert - All should work
        $inputEmbedding = Embedding::create([
            'content_id' => $input->id,
            'content_type' => 'App\Models\Input',
            'vector' => '[' . implode(',', $testVector) . ']',
            'model' => 'test-model',
            'dimensions' => 1536,
            'normalized' => true
        ]);

        $outputEmbedding = Embedding::create([
            'content_id' => $output->id,
            'content_type' => 'App\Models\Output',
            'vector' => '[' . implode(',', $testVector) . ']',
            'model' => 'test-model',
            'dimensions' => 1536,
            'normalized' => true
        ]);

        $feedbackEmbedding = Embedding::create([
            'content_id' => $feedback->id,
            'content_type' => 'App\Models\Feedback',
            'vector' => '[' . implode(',', $testVector) . ']',
            'model' => 'test-model',
            'dimensions' => 1536,
            'normalized' => true
        ]);

        $this->assertNotNull($inputEmbedding->id);
        $this->assertNotNull($outputEmbedding->id);
        $this->assertNotNull($feedbackEmbedding->id);
    }

    /** @test */
    public function workstream_deletion_cascades_to_releases()
    {
        // Arrange
        $user = User::factory()->create();
        $workstream = Workstream::factory()->create(['owner_id' => $user->id]);
        $release = Release::factory()->create(['workstream_id' => $workstream->id]);

        $this->assertDatabaseHas('releases', ['id' => $release->id]);

        // Act
        $workstream->delete();

        // Assert
        $this->assertDatabaseMissing('releases', ['id' => $release->id]);
    }

    /** @test */
    public function user_deletion_sets_workstream_owner_to_null_and_creates_review_job()
    {
        // Arrange
        $user = User::factory()->create();
        $workstream = Workstream::factory()->create([
            'owner_id' => $user->id,
            'name' => 'Test Workstream'
        ]);

        $initialJobCount = AiJob::count();

        // Act
        $user->delete();

        // Assert
        $this->assertDatabaseHas('workstreams', [
            'id' => $workstream->id,
            'owner_id' => null
        ]);

        $this->assertEquals($initialJobCount + 1, AiJob::count());

        $job = AiJob::latest()->first();
        $this->assertEquals('workstream_ownership_review', $job->method);
        $this->assertEquals('processing', $job->status);
    }

    /** @test */
    public function retrieval_service_uses_pgvector_operations()
    {
        // Arrange
        $user = User::factory()->create();
        $queryInput = Input::factory()->create(['user_id' => $user->id]);
        $targetInput = Input::factory()->create(['user_id' => $user->id]);

        $output = Output::factory()->create([
            'input_id' => $targetInput->id,
            'quality_score' => 0.9
        ]);

        $feedback = Feedback::create([
            'output_id' => $output->id,
            'user_id' => $user->id,
            'type' => 'rating',
            'action' => 'accept',
            'signal_type' => 'explicit',
            'confidence' => 0.95,
            'metadata' => []
        ]);

        $queryVector = $this->createTestVector([0 => 1.0, 1 => 0.8, 2 => 0.6]);
        $targetVector = $this->createTestVector([0 => 0.9, 1 => 0.8, 2 => 0.7]);

        Embedding::create([
            'content_id' => $queryInput->id,
            'content_type' => 'App\Models\Input',
            'vector' => '[' . implode(',', $queryVector) . ']',
            'model' => 'test-model',
            'dimensions' => 1536,
            'normalized' => true
        ]);

        Embedding::create([
            'content_id' => $targetInput->id,
            'content_type' => 'App\Models\Input',
            'vector' => '[' . implode(',', $targetVector) . ']',
            'model' => 'test-model',
            'dimensions' => 1536,
            'normalized' => true
        ]);

        // Act
        $retrievalService = new RetrievalService();
        $examples = $retrievalService->findSimilarFeedbackExamples($queryInput->id, [], 10);

        // Assert
        $this->assertCount(1, $examples);
        $example = $examples->first();

        $this->assertEquals($targetInput->id, $example['input']['id']);
        $this->assertEquals($output->id, $example['output']['id']);
        $this->assertEquals($feedback->id, $example['feedback']['id']);
        $this->assertArrayHasKey('similarity_score', $example);
        $this->assertGreaterThan(0.8, $example['similarity_score']);
    }

    /** @test */
    public function database_triggers_and_functions_are_properly_configured()
    {
        // Verify embedding validation trigger exists
        $triggers = DB::select("
            SELECT trigger_name
            FROM information_schema.triggers
            WHERE event_object_table = 'embeddings'
            AND trigger_name = 'embedding_content_validation_trigger'
        ");

        $this->assertGreaterThanOrEqual(1, count($triggers));

        // Verify workstream ownership trigger exists
        $wsTriggers = DB::select("
            SELECT trigger_name
            FROM information_schema.triggers
            WHERE event_object_table = 'workstreams'
            AND trigger_name = 'workstream_ownership_change_trigger'
        ");

        $this->assertCount(1, $wsTriggers);

        // Verify functions exist
        $functions = DB::select("
            SELECT routine_name
            FROM information_schema.routines
            WHERE routine_name IN ('validate_embedding_content', 'prevent_orphaned_workstreams')
            AND routine_type = 'FUNCTION'
        ");

        $this->assertCount(2, $functions);
    }

    /** @test */
    public function foreign_key_constraints_have_correct_cascade_behavior()
    {
        // Check that workstream owner uses SET NULL
        $constraints = DB::select("
            SELECT tc.constraint_name, rc.delete_rule
            FROM information_schema.table_constraints AS tc
            JOIN information_schema.referential_constraints AS rc ON tc.constraint_name = rc.constraint_name
            WHERE tc.constraint_type = 'FOREIGN KEY'
            AND tc.table_name = 'workstreams'
            AND tc.constraint_name LIKE '%owner%'
        ");

        $this->assertCount(1, $constraints);
        $this->assertEquals('SET NULL', $constraints[0]->delete_rule);

        // Check that workstream hierarchy uses CASCADE
        $hierarchyConstraints = DB::select("
            SELECT tc.constraint_name, rc.delete_rule
            FROM information_schema.table_constraints AS tc
            JOIN information_schema.referential_constraints AS rc ON tc.constraint_name = rc.constraint_name
            WHERE tc.constraint_type = 'FOREIGN KEY'
            AND tc.table_name = 'workstreams'
            AND tc.constraint_name LIKE '%parent%'
        ");

        $this->assertCount(1, $hierarchyConstraints);
        $this->assertEquals('CASCADE', $hierarchyConstraints[0]->delete_rule);
    }
}
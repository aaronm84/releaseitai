<?php

namespace Tests\Unit\DataIntegrity;

use Tests\TestCase;
use App\Models\Embedding;
use App\Models\Input;
use App\Models\Output;
use App\Models\Feedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class EmbeddingValidationTest extends TestCase
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

    /**
     * Create a test vector with proper 1536 dimensions
     */
    private function createTestVector(array $customValues = []): array
    {
        $vector = array_fill(0, 1536, 0.001);

        // Override specific positions with custom values
        foreach ($customValues as $index => $value) {
            if ($index < 1536) {
                $vector[$index] = $value;
            }
        }

        return $vector;
    }

    /** @test */
    public function it_allows_valid_input_content_references()
    {
        // Arrange
        $user = User::factory()->create();
        $input = Input::factory()->create(['user_id' => $user->id]);

        // Create a 1536-dimensional test vector
        $testVector = array_fill(0, 1536, 0.001);
        $testVector[0] = 0.1;
        $testVector[1] = 0.2;
        $testVector[2] = 0.3;

        // Act & Assert - Should not throw exception
        $embedding = Embedding::create([
            'content_id' => $input->id,
            'content_type' => 'App\Models\Input',
            'vector' => '[' . implode(',', $testVector) . ']',
            'model' => 'test-model',
            'dimensions' => 1536,
            'normalized' => true
        ]);

        $this->assertDatabaseHas('embeddings', [
            'id' => $embedding->id,
            'content_id' => $input->id,
            'content_type' => 'App\Models\Input'
        ]);
    }

    /** @test */
    public function it_allows_valid_output_content_references()
    {
        // Arrange
        $user = User::factory()->create();
        $input = Input::factory()->create(['user_id' => $user->id]);
        $output = Output::factory()->create(['input_id' => $input->id]);

        // Act & Assert - Should not throw exception
        $embedding = Embedding::create([
            'content_id' => $output->id,
            'content_type' => 'App\Models\Output',
            'vector' => '[0.1,0.2,0.3]',
            'model' => 'test-model',
            'dimensions' => 3,
            'normalized' => true
        ]);

        $this->assertDatabaseHas('embeddings', [
            'id' => $embedding->id,
            'content_id' => $output->id,
            'content_type' => 'App\Models\Output'
        ]);
    }

    /** @test */
    public function it_allows_valid_feedback_content_references()
    {
        // Arrange
        $user = User::factory()->create();
        $input = Input::factory()->create(['user_id' => $user->id]);
        $output = Output::factory()->create(['input_id' => $input->id]);
        $feedback = Feedback::create([
            'output_id' => $output->id,
            'user_id' => $user->id,
            'action' => 'accept',
            'confidence' => 0.8,
            'metadata' => []
        ]);

        // Act & Assert - Should not throw exception
        $embedding = Embedding::create([
            'content_id' => $feedback->id,
            'content_type' => 'App\Models\Feedback',
            'vector' => '[0.1,0.2,0.3]',
            'model' => 'test-model',
            'dimensions' => 3,
            'normalized' => true
        ]);

        $this->assertDatabaseHas('embeddings', [
            'id' => $embedding->id,
            'content_id' => $feedback->id,
            'content_type' => 'App\Models\Feedback'
        ]);
    }

    /** @test */
    public function it_rejects_invalid_input_content_id()
    {
        // Arrange
        $nonExistentId = 99999;

        // Assert - Should throw exception
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Invalid content_id');

        // Act
        Embedding::create([
            'content_id' => $nonExistentId,
            'content_type' => 'App\Models\Input',
            'vector' => '[0.1,0.2,0.3]',
            'model' => 'test-model',
            'dimensions' => 3,
            'normalized' => true
        ]);
    }

    /** @test */
    public function it_rejects_invalid_output_content_id()
    {
        // Arrange
        $nonExistentId = 99999;

        // Assert - Should throw exception
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Invalid content_id');

        // Act
        Embedding::create([
            'content_id' => $nonExistentId,
            'content_type' => 'App\Models\Output',
            'vector' => '[0.1,0.2,0.3]',
            'model' => 'test-model',
            'dimensions' => 3,
            'normalized' => true
        ]);
    }

    /** @test */
    public function it_rejects_invalid_feedback_content_id()
    {
        // Arrange
        $nonExistentId = 99999;

        // Assert - Should throw exception
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Invalid content_id');

        // Act
        Embedding::create([
            'content_id' => $nonExistentId,
            'content_type' => 'App\Models\Feedback',
            'vector' => '[0.1,0.2,0.3]',
            'model' => 'test-model',
            'dimensions' => 3,
            'normalized' => true
        ]);
    }

    /** @test */
    public function it_rejects_invalid_content_type()
    {
        // Arrange
        $user = User::factory()->create();
        $input = Input::factory()->create(['user_id' => $user->id]);

        // Assert - Should throw exception
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Invalid content_type');

        // Act
        Embedding::create([
            'content_id' => $input->id,
            'content_type' => 'App\Models\InvalidModel',
            'vector' => '[0.1,0.2,0.3]',
            'model' => 'test-model',
            'dimensions' => 3,
            'normalized' => true
        ]);
    }

    /** @test */
    public function it_rejects_mismatched_content_id_and_type()
    {
        // Arrange
        $user = User::factory()->create();
        $input = Input::factory()->create(['user_id' => $user->id]);

        // Assert - Should throw exception (using Input ID with Output type)
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Invalid content_id');

        // Act
        Embedding::create([
            'content_id' => $input->id,
            'content_type' => 'App\Models\Output', // Wrong type
            'vector' => '[0.1,0.2,0.3]',
            'model' => 'test-model',
            'dimensions' => 3,
            'normalized' => true
        ]);
    }

    /** @test */
    public function it_validates_content_references_on_update()
    {
        // Arrange
        $user = User::factory()->create();
        $input = Input::factory()->create(['user_id' => $user->id]);
        $output = Output::factory()->create(['input_id' => $input->id]);

        $embedding = Embedding::create([
            'content_id' => $input->id,
            'content_type' => 'App\Models\Input',
            'vector' => '[0.1,0.2,0.3]',
            'model' => 'test-model',
            'dimensions' => 3,
            'normalized' => true
        ]);

        // Act - Valid update should work
        $embedding->update([
            'content_id' => $output->id,
            'content_type' => 'App\Models\Output'
        ]);

        $this->assertDatabaseHas('embeddings', [
            'id' => $embedding->id,
            'content_id' => $output->id,
            'content_type' => 'App\Models\Output'
        ]);

        // Assert - Invalid update should fail
        $this->expectException(QueryException::class);
        $embedding->update([
            'content_id' => 99999,
            'content_type' => 'App\Models\Input'
        ]);
    }

    /** @test */
    public function it_maintains_referential_integrity_on_content_deletion()
    {
        // Arrange
        $user = User::factory()->create();
        $input = Input::factory()->create(['user_id' => $user->id]);

        $embedding = Embedding::create([
            'content_id' => $input->id,
            'content_type' => 'App\Models\Input',
            'vector' => '[0.1,0.2,0.3]',
            'model' => 'test-model',
            'dimensions' => 3,
            'normalized' => true
        ]);

        $this->assertDatabaseHas('embeddings', ['id' => $embedding->id]);

        // Act - Delete the referenced content
        $input->delete();

        // Assert - Embedding should still exist (no CASCADE on polymorphic)
        // but trying to create new embeddings with the deleted ID should fail
        $this->assertDatabaseHas('embeddings', ['id' => $embedding->id]);

        $this->expectException(QueryException::class);
        Embedding::create([
            'content_id' => $input->id, // Deleted input ID
            'content_type' => 'App\Models\Input',
            'vector' => '[0.4,0.5,0.6]',
            'model' => 'test-model',
            'dimensions' => 3,
            'normalized' => true
        ]);
    }

    /** @test */
    public function it_has_performance_index_for_content_lookups()
    {
        // Verify that the composite index exists for performance
        $indexes = DB::select("
            SELECT indexname
            FROM pg_indexes
            WHERE tablename = 'embeddings'
            AND indexname LIKE '%content%'
        ");

        $indexNames = array_column($indexes, 'indexname');

        $this->assertContains('embeddings_content_id_content_type_index', $indexNames);
    }

    /** @test */
    public function trigger_function_exists_and_is_properly_configured()
    {
        // Verify the trigger function exists
        $functions = DB::select("
            SELECT routine_name
            FROM information_schema.routines
            WHERE routine_name = 'validate_embedding_content'
            AND routine_type = 'FUNCTION'
        ");

        $this->assertCount(1, $functions);

        // Verify the trigger exists
        $triggers = DB::select("
            SELECT trigger_name
            FROM information_schema.triggers
            WHERE event_object_table = 'embeddings'
            AND trigger_name = 'embedding_content_validation_trigger'
        ");

        $this->assertCount(1, $triggers);
    }
}
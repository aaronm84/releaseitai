<?php

namespace Tests\Unit\DataIntegrity;

use Tests\TestCase;
use App\Models\Embedding;
use App\Models\Input;
use App\Models\Output;
use App\Models\User;
use App\Services\RetrievalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class VectorStorageTest extends TestCase
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

    /** @test */
    public function it_stores_vectors_in_proper_pgvector_format()
    {
        // Arrange
        $user = User::factory()->create();
        $input = Input::factory()->create(['user_id' => $user->id]);
        $embedding = new Embedding();

        // Act
        $embedding->content_id = $input->id;
        $embedding->content_type = Input::class;
        $embedding->setVectorFromArray([0.1, 0.2, 0.3, 0.4, 0.5]);
        $embedding->model = 'text-embedding-ada-002';
        $embedding->dimensions = 5;
        $embedding->normalized = true;
        $embedding->save();

        // Assert
        $this->assertDatabaseHas('embeddings', [
            'content_id' => $input->id,
            'content_type' => Input::class,
            'vector' => '[0.1,0.2,0.3,0.4,0.5]'
        ]);

        // Verify retrieval works correctly
        $retrieved = Embedding::find($embedding->id);
        $vectorArray = $retrieved->getVectorAsArray();

        $this->assertEquals([0.1, 0.2, 0.3, 0.4, 0.5], $vectorArray);
    }

    /** @test */
    public function it_handles_1536_dimensional_vectors_correctly()
    {
        // Arrange
        $user = User::factory()->create();
        $input = Input::factory()->create(['user_id' => $user->id]);
        $embedding = new Embedding();

        // Create a 1536-dimensional vector (OpenAI standard)
        $largeVector = array_fill(0, 1536, 0.001);
        $largeVector[0] = 1.0; // First element different for verification

        // Act
        $embedding->content_id = $input->id;
        $embedding->content_type = Input::class;
        $embedding->setVectorFromArray($largeVector);
        $embedding->model = 'text-embedding-ada-002';
        $embedding->dimensions = 1536;
        $embedding->normalized = true;
        $embedding->save();

        // Assert
        $retrieved = Embedding::find($embedding->id);
        $vectorArray = $retrieved->getVectorAsArray();

        $this->assertCount(1536, $vectorArray);
        $this->assertEquals(1.0, $vectorArray[0]);
        $this->assertEquals(0.001, $vectorArray[1]);
        $this->assertEquals(0.001, $vectorArray[1535]);
    }

    /** @test */
    public function it_performs_pgvector_similarity_search_correctly()
    {
        // Arrange
        $user = User::factory()->create();
        $input1 = Input::factory()->create(['user_id' => $user->id, 'content' => 'Test content 1']);
        $input2 = Input::factory()->create(['user_id' => $user->id, 'content' => 'Test content 2']);
        $input3 = Input::factory()->create(['user_id' => $user->id, 'content' => 'Different content']);

        // Create similar vectors for input1 and input2
        $similarVector1 = [1.0, 0.8, 0.6, 0.4, 0.2];
        $similarVector2 = [0.9, 0.8, 0.7, 0.5, 0.3]; // Very similar
        $differentVector = [0.1, 0.2, 0.1, 0.9, 0.8]; // Different

        $embedding1 = Embedding::create([
            'content_id' => $input1->id,
            'content_type' => Input::class,
            'vector' => '[' . implode(',', $similarVector1) . ']',
            'model' => 'test-model',
            'dimensions' => 5,
            'normalized' => true
        ]);

        $embedding2 = Embedding::create([
            'content_id' => $input2->id,
            'content_type' => Input::class,
            'vector' => '[' . implode(',', $similarVector2) . ']',
            'model' => 'test-model',
            'dimensions' => 5,
            'normalized' => true
        ]);

        $embedding3 = Embedding::create([
            'content_id' => $input3->id,
            'content_type' => Input::class,
            'vector' => '[' . implode(',', $differentVector) . ']',
            'model' => 'test-model',
            'dimensions' => 5,
            'normalized' => true
        ]);

        // Act - Test pgvector similarity query
        $queryVector = '[' . implode(',', $similarVector1) . ']';
        $results = DB::select("
            SELECT
                content_id,
                1 - (vector <=> '{$queryVector}') as similarity_score
            FROM embeddings
            WHERE content_type = ?
            ORDER BY similarity_score DESC
        ", [Input::class]);

        // Assert
        $this->assertCount(3, $results);

        // First result should be exact match (input1)
        $this->assertEquals($input1->id, $results[0]->content_id);
        $this->assertGreaterThan(0.99, $results[0]->similarity_score);

        // Second result should be similar (input2)
        $this->assertEquals($input2->id, $results[1]->content_id);
        $this->assertGreaterThan(0.8, $results[1]->similarity_score);

        // Third result should be less similar (input3)
        $this->assertEquals($input3->id, $results[2]->content_id);
        $this->assertLessThan(0.8, $results[2]->similarity_score);
    }

    /** @test */
    public function retrieval_service_uses_pgvector_operations()
    {
        // Arrange
        $user = User::factory()->create();
        $input = Input::factory()->create(['user_id' => $user->id, 'content' => 'Query input']);

        // Create target input with similar content
        $targetInput = Input::factory()->create(['user_id' => $user->id, 'content' => 'Target content']);
        $output = Output::factory()->create([
            'input_id' => $targetInput->id,
            'content' => 'Generated response',
            'type' => 'text',
            'quality_score' => 0.9
        ]);

        $feedback = \App\Models\Feedback::create([
            'output_id' => $output->id,
            'user_id' => $user->id,
            'action' => 'accept',
            'confidence' => 0.95,
            'metadata' => []
        ]);

        // Create embeddings
        $queryVector = [1.0, 0.8, 0.6, 0.4, 0.2];
        $targetVector = [0.9, 0.8, 0.7, 0.5, 0.3]; // Similar

        Embedding::create([
            'content_id' => $input->id,
            'content_type' => Input::class,
            'vector' => '[' . implode(',', $queryVector) . ']',
            'model' => 'test-model',
            'dimensions' => 5,
            'normalized' => true
        ]);

        Embedding::create([
            'content_id' => $targetInput->id,
            'content_type' => Input::class,
            'vector' => '[' . implode(',', $targetVector) . ']',
            'model' => 'test-model',
            'dimensions' => 5,
            'normalized' => true
        ]);

        // Act
        $retrievalService = new RetrievalService();
        $examples = $retrievalService->findSimilarFeedbackExamples($input->id, [], 10);

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
    public function it_handles_vector_format_conversion_correctly()
    {
        // Test that we can handle both old JSON format and new pgvector format
        $embedding = new Embedding();

        // Test setting from array (new format)
        $embedding->setVectorFromArray([0.1, 0.2, 0.3]);
        $this->assertEquals('[0.1,0.2,0.3]', $embedding->vector);

        // Test getting as array (should work with pgvector format)
        $embedding->vector = '[0.4,0.5,0.6]';
        $array = $embedding->getVectorAsArray();
        $this->assertEquals([0.4, 0.5, 0.6], $array);

        // Test with different spacing (should be tolerant)
        $embedding->vector = '[0.7, 0.8, 0.9]';
        $array = $embedding->getVectorAsArray();
        $this->assertEquals([0.7, 0.8, 0.9], $array);
    }
}
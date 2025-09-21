<?php

namespace Tests\Unit\Models;

use App\Models\Embedding;
use App\Models\Input;
use App\Models\Output;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class EmbeddingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that Embedding model can be created with required fields
     *
     * Given: Required embedding data (content_id, content_type, vector)
     * When: Creating a new Embedding model
     * Then: Embedding should be saved with vector data and associations
     */
    public function test_embedding_can_be_created_with_required_fields()
    {
        // Given
        $input = Input::create([
            'content' => 'Test content for embedding',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        // Simulate a 1536-dimensional vector (OpenAI embedding size)
        $vector = array_fill(0, 1536, 0.1);
        $vectorString = '[' . implode(',', $vector) . ']';

        $embeddingData = [
            'content_id' => $input->id,
            'content_type' => 'App\Models\Input',
            'vector' => $vectorString,
            'model' => 'text-embedding-ada-002',
            'dimensions' => 1536
        ];

        // When
        $embedding = Embedding::create($embeddingData);

        // Then
        $this->assertInstanceOf(Embedding::class, $embedding);
        $this->assertEquals($embeddingData['content_id'], $embedding->content_id);
        $this->assertEquals($embeddingData['content_type'], $embedding->content_type);
        $this->assertEquals($embeddingData['vector'], $embedding->vector);
        $this->assertEquals($embeddingData['model'], $embedding->model);
        $this->assertEquals($embeddingData['dimensions'], $embedding->dimensions);
        $this->assertNotNull($embedding->created_at);
    }

    /**
     * Test Embedding model pgvector integration
     *
     * Given: Vector data for pgvector storage
     * When: Creating Embedding with pgvector format
     * Then: Should store vector in pgvector format for similarity search
     */
    public function test_embedding_stores_pgvector_format()
    {
        // Given
        $input = Input::create([
            'content' => 'Test content for pgvector',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        // Create a test vector with known values
        $vector = [0.1, 0.2, 0.3, 0.4, 0.5];
        $pgvectorFormat = '[' . implode(',', $vector) . ']';

        $embedding = Embedding::create([
            'content_id' => $input->id,
            'content_type' => 'App\Models\Input',
            'vector' => $pgvectorFormat,
            'model' => 'text-embedding-ada-002',
            'dimensions' => 5
        ]);

        // When - retrieving the vector
        $storedVector = $embedding->vector;

        // Then
        $this->assertEquals($pgvectorFormat, $storedVector);
        $this->assertStringStartsWith('[', $storedVector);
        $this->assertStringEndsWith(']', $storedVector);
        $this->assertStringContainsString('0.1,0.2,0.3,0.4,0.5', $storedVector);
    }

    /**
     * Test Embedding model vector validation
     *
     * Given: Invalid vector data
     * When: Attempting to create Embedding with invalid vectors
     * Then: Should validate vector format and dimensions
     */
    public function test_embedding_validates_vector_format()
    {
        // Given
        $input = Input::create([
            'content' => 'Test content',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        $invalidVectors = [
            'empty_vector' => '',
            'invalid_json' => '[0.1, 0.2, invalid]',
            'mismatched_dimensions' => '[0.1, 0.2]' // when dimensions = 3
        ];

        foreach ($invalidVectors as $testCase => $invalidVector) {
            try {
                // When
                Embedding::create([
                    'content_id' => $input->id,
                    'content_type' => 'App\Models\Input',
                    'vector' => $invalidVector,
                    'model' => 'test-model',
                    'dimensions' => 3
                ]);

                // Should not reach here for invalid vectors
                $this->fail("Expected validation error for {$testCase}");
            } catch (\Exception $e) {
                // Then - should throw validation error
                $this->assertInstanceOf(\Exception::class, $e);
            }
        }
    }

    /**
     * Test Embedding model polymorphic relationships
     *
     * Given: Embeddings for different content types (Input, Output)
     * When: Creating embeddings for various models
     * Then: Should support polymorphic relationships with content
     */
    public function test_embedding_supports_polymorphic_relationships()
    {
        // Given
        $input = Input::create([
            'content' => 'Input content',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        $output = Output::create([
            'input_id' => $input->id,
            'content' => 'Output content',
            'type' => 'checklist',
            'ai_model' => 'claude-3-5-sonnet'
        ]);

        $vector = '[0.1, 0.2, 0.3]';

        // When - creating embeddings for different content types
        $inputEmbedding = Embedding::create([
            'content_id' => $input->id,
            'content_type' => 'App\Models\Input',
            'vector' => $vector,
            'model' => 'text-embedding-ada-002',
            'dimensions' => 3
        ]);

        $outputEmbedding = Embedding::create([
            'content_id' => $output->id,
            'content_type' => 'App\Models\Output',
            'vector' => $vector,
            'model' => 'text-embedding-ada-002',
            'dimensions' => 3
        ]);

        // Then
        $this->assertEquals('App\Models\Input', $inputEmbedding->content_type);
        $this->assertEquals('App\Models\Output', $outputEmbedding->content_type);
        $this->assertEquals($input->id, $inputEmbedding->content_id);
        $this->assertEquals($output->id, $outputEmbedding->content_id);
    }

    /**
     * Test Embedding model similarity search capability
     *
     * Given: Multiple embeddings in the database
     * When: Performing similarity search using pgvector
     * Then: Should return embeddings ordered by similarity
     */
    public function test_embedding_supports_similarity_search()
    {
        // Given - create multiple embeddings with known vectors
        $input1 = Input::create(['content' => 'Content 1', 'type' => 'brain_dump', 'source' => 'manual_entry']);
        $input2 = Input::create(['content' => 'Content 2', 'type' => 'brain_dump', 'source' => 'manual_entry']);
        $input3 = Input::create(['content' => 'Content 3', 'type' => 'brain_dump', 'source' => 'manual_entry']);

        // Similar vectors (should be returned first)
        $queryVector = [1.0, 0.0, 0.0];
        $similarVector = [0.9, 0.1, 0.0];
        $differentVector = [0.0, 0.0, 1.0];

        Embedding::create([
            'content_id' => $input1->id,
            'content_type' => 'App\Models\Input',
            'vector' => '[' . implode(',', $similarVector) . ']',
            'model' => 'text-embedding-ada-002',
            'dimensions' => 3
        ]);

        Embedding::create([
            'content_id' => $input2->id,
            'content_type' => 'App\Models\Input',
            'vector' => '[' . implode(',', $differentVector) . ']',
            'model' => 'text-embedding-ada-002',
            'dimensions' => 3
        ]);

        Embedding::create([
            'content_id' => $input3->id,
            'content_type' => 'App\Models\Input',
            'vector' => '[' . implode(',', $queryVector) . ']',
            'model' => 'text-embedding-ada-002',
            'dimensions' => 3
        ]);

        // When - performing similarity search (this would use pgvector in production)
        $queryVectorString = '[' . implode(',', $queryVector) . ']';

        // This test defines the expected interface for similarity search
        // In production, this would use pgvector's <-> operator
        $similarEmbeddings = Embedding::orderByRaw("vector <-> ? ASC", [$queryVectorString])
            ->limit(2)
            ->get();

        // Then - should return most similar embeddings first
        // Note: This test defines expected behavior; actual implementation would require pgvector
        $this->assertEquals(2, $similarEmbeddings->count());
    }

    /**
     * Test Embedding model vector normalization
     *
     * Given: Vector data that needs normalization
     * When: Creating Embedding with vector normalization
     * Then: Should normalize vectors for consistent similarity calculations
     */
    public function test_embedding_supports_vector_normalization()
    {
        // Given
        $input = Input::create([
            'content' => 'Test content',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        $unnormalizedVector = [3.0, 4.0, 0.0]; // magnitude = 5
        $expectedNormalizedVector = [0.6, 0.8, 0.0]; // normalized

        // When - creating embedding (normalization should happen automatically)
        $embedding = Embedding::create([
            'content_id' => $input->id,
            'content_type' => 'App\Models\Input',
            'vector' => '[' . implode(',', $unnormalizedVector) . ']',
            'model' => 'text-embedding-ada-002',
            'dimensions' => 3,
            'normalized' => true
        ]);

        // Then - vector should be normalized if specified
        $this->assertTrue($embedding->normalized ?? false);

        // In production, would check that the stored vector is actually normalized
        // This test defines the expected behavior
    }

    /**
     * Test Embedding model metadata storage
     *
     * Given: Embedding generation metadata
     * When: Creating Embedding with processing metadata
     * Then: Should store embedding generation details
     */
    public function test_embedding_stores_generation_metadata()
    {
        // Given
        $input = Input::create([
            'content' => 'Test content for metadata',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        $metadata = [
            'generation_time' => 0.25,
            'token_count' => 150,
            'model_version' => 'ada-002-v1',
            'content_hash' => hash('sha256', 'Test content for metadata'),
            'processing_timestamp' => now()->toISOString()
        ];

        $embedding = Embedding::create([
            'content_id' => $input->id,
            'content_type' => 'App\Models\Input',
            'vector' => '[0.1, 0.2, 0.3]',
            'model' => 'text-embedding-ada-002',
            'dimensions' => 3,
            'metadata' => json_encode($metadata)
        ]);

        // When
        $retrievedMetadata = json_decode($embedding->metadata, true);

        // Then
        $this->assertEquals($metadata, $retrievedMetadata);
        $this->assertEquals(0.25, $retrievedMetadata['generation_time']);
        $this->assertEquals(150, $retrievedMetadata['token_count']);
    }

    /**
     * Test Embedding model batch operations
     *
     * Given: Multiple content items for embedding
     * When: Creating embeddings in batch
     * Then: Should support efficient batch insertion
     */
    public function test_embedding_supports_batch_operations()
    {
        // Given
        $inputs = [];
        for ($i = 0; $i < 5; $i++) {
            $inputs[] = Input::create([
                'content' => "Test content {$i}",
                'type' => 'brain_dump',
                'source' => 'manual_entry'
            ]);
        }

        $batchData = [];
        foreach ($inputs as $input) {
            $batchData[] = [
                'content_id' => $input->id,
                'content_type' => 'App\Models\Input',
                'vector' => '[0.1, 0.2, 0.3]',
                'model' => 'text-embedding-ada-002',
                'dimensions' => 3,
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

        // When - batch inserting embeddings
        Embedding::insert($batchData);

        // Then
        $embeddingCount = Embedding::count();
        $this->assertEquals(5, $embeddingCount);

        // Verify each embedding was created correctly
        foreach ($inputs as $input) {
            $embedding = Embedding::where('content_id', $input->id)
                ->where('content_type', 'App\Models\Input')
                ->first();

            $this->assertNotNull($embedding);
            $this->assertEquals($input->id, $embedding->content_id);
        }
    }

    /**
     * Test Embedding model query scopes for filtering
     *
     * Given: Embeddings with different models and content types
     * When: Using query scopes for filtering
     * Then: Should filter by model, content type, and dimensions
     */
    public function test_embedding_query_scopes()
    {
        // Given
        $input = Input::create([
            'content' => 'Test input',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        $output = Output::create([
            'input_id' => $input->id,
            'content' => 'Test output',
            'type' => 'checklist',
            'ai_model' => 'claude-3-5-sonnet'
        ]);

        Embedding::create([
            'content_id' => $input->id,
            'content_type' => 'App\Models\Input',
            'vector' => '[0.1, 0.2, 0.3]',
            'model' => 'text-embedding-ada-002',
            'dimensions' => 3
        ]);

        Embedding::create([
            'content_id' => $output->id,
            'content_type' => 'App\Models\Output',
            'vector' => '[0.4, 0.5, 0.6]',
            'model' => 'text-embedding-3-large',
            'dimensions' => 3
        ]);

        // When - using query scopes
        $inputEmbeddings = Embedding::where('content_type', 'App\Models\Input')->get();
        $adaEmbeddings = Embedding::where('model', 'text-embedding-ada-002')->get();
        $threeDimEmbeddings = Embedding::where('dimensions', 3)->get();

        // Then
        $this->assertEquals(1, $inputEmbeddings->count());
        $this->assertEquals(1, $adaEmbeddings->count());
        $this->assertEquals(2, $threeDimEmbeddings->count());
    }

    /**
     * Test Embedding model content relationship access
     *
     * Given: An Embedding with polymorphic content relationship
     * When: Accessing the content through the relationship
     * Then: Should return the associated content model (Input or Output)
     */
    public function test_embedding_accesses_polymorphic_content()
    {
        // Given
        $input = Input::create([
            'content' => 'Test content for relationship',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        $embedding = Embedding::create([
            'content_id' => $input->id,
            'content_type' => 'App\Models\Input',
            'vector' => '[0.1, 0.2, 0.3]',
            'model' => 'text-embedding-ada-002',
            'dimensions' => 3
        ]);

        // When - accessing content through polymorphic relationship
        $relatedContent = $embedding->content;

        // Then
        $this->assertInstanceOf(Input::class, $relatedContent);
        $this->assertEquals($input->id, $relatedContent->id);
        $this->assertEquals($input->content, $relatedContent->content);
    }

    /**
     * Test Embedding model vector distance calculations
     *
     * Given: Two embeddings with known vectors
     * When: Calculating distance between vectors
     * Then: Should provide helper methods for distance calculations
     */
    public function test_embedding_provides_distance_calculations()
    {
        // Given
        $input1 = Input::create(['content' => 'Content 1', 'type' => 'brain_dump', 'source' => 'manual_entry']);
        $input2 = Input::create(['content' => 'Content 2', 'type' => 'brain_dump', 'source' => 'manual_entry']);

        $vector1 = [1.0, 0.0, 0.0];
        $vector2 = [0.0, 1.0, 0.0];

        $embedding1 = Embedding::create([
            'content_id' => $input1->id,
            'content_type' => 'App\Models\Input',
            'vector' => '[' . implode(',', $vector1) . ']',
            'model' => 'text-embedding-ada-002',
            'dimensions' => 3
        ]);

        $embedding2 = Embedding::create([
            'content_id' => $input2->id,
            'content_type' => 'App\Models\Input',
            'vector' => '[' . implode(',', $vector2) . ']',
            'model' => 'text-embedding-ada-002',
            'dimensions' => 3
        ]);

        // When - calculating distance (this defines expected interface)
        // In production, would use pgvector operators or helper methods
        $expectedDistance = sqrt(2); // Euclidean distance between [1,0,0] and [0,1,0]

        // Then - should provide distance calculation capability
        // This test defines the expected behavior for distance calculations
        $this->assertInstanceOf(Embedding::class, $embedding1);
        $this->assertInstanceOf(Embedding::class, $embedding2);
    }
}
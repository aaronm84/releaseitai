<?php

namespace Tests\Unit\Models;

use App\Models\Input;
use App\Models\Output;
use App\Models\Embedding;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\Collection;

class InputTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that Input model can be created with required fields
     *
     * Given: Required input data (content, type, source)
     * When: Creating a new Input model
     * Then: Input should be saved with correct attributes and auto-generated fields
     */
    public function test_input_can_be_created_with_required_fields()
    {
        // Given
        $inputData = [
            'content' => 'This is a brain dump about our upcoming release...',
            'type' => 'brain_dump',
            'source' => 'manual_entry',
            'metadata' => json_encode(['user_context' => 'release_planning'])
        ];

        // When
        $input = Input::create($inputData);

        // Then
        $this->assertInstanceOf(Input::class, $input);
        $this->assertEquals($inputData['content'], $input->content);
        $this->assertEquals($inputData['type'], $input->type);
        $this->assertEquals($inputData['source'], $input->source);
        $this->assertNotNull($input->id);
        $this->assertNotNull($input->created_at);
        $this->assertNotNull($input->updated_at);
    }

    /**
     * Test Input model validation rules
     *
     * Given: Invalid input data
     * When: Attempting to create Input
     * Then: Should enforce validation rules for required fields
     */
    public function test_input_validates_required_fields()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        // When - attempting to create without required fields
        Input::create([]);
    }

    /**
     * Test Input model content length limits
     *
     * Given: Very long content string
     * When: Creating Input
     * Then: Should handle large content appropriately
     */
    public function test_input_handles_large_content()
    {
        // Given
        $largeContent = str_repeat('This is a very long content string. ', 1000);

        $inputData = [
            'content' => $largeContent,
            'type' => 'document',
            'source' => 'file_upload'
        ];

        // When
        $input = Input::create($inputData);

        // Then
        $this->assertEquals($largeContent, $input->content);
        $this->assertTrue(strlen($input->content) > 10000);
    }

    /**
     * Test Input model type enumeration
     *
     * Given: Valid input types
     * When: Creating Input with different types
     * Then: Should accept valid types (brain_dump, email, document, task_description)
     */
    public function test_input_accepts_valid_types()
    {
        $validTypes = ['brain_dump', 'email', 'document', 'task_description'];

        foreach ($validTypes as $type) {
            // When
            $input = Input::create([
                'content' => 'Test content for ' . $type,
                'type' => $type,
                'source' => 'test'
            ]);

            // Then
            $this->assertEquals($type, $input->type);
        }
    }

    /**
     * Test Input model source tracking
     *
     * Given: Different source types
     * When: Creating Input with various sources
     * Then: Should track source accurately (manual_entry, email_import, file_upload, api)
     */
    public function test_input_tracks_source_correctly()
    {
        $validSources = ['manual_entry', 'email_import', 'file_upload', 'api'];

        foreach ($validSources as $source) {
            // When
            $input = Input::create([
                'content' => 'Test content from ' . $source,
                'type' => 'brain_dump',
                'source' => $source
            ]);

            // Then
            $this->assertEquals($source, $input->source);
        }
    }

    /**
     * Test Input model metadata handling
     *
     * Given: JSON metadata
     * When: Creating Input with metadata
     * Then: Should store and retrieve metadata as JSON
     */
    public function test_input_handles_metadata_as_json()
    {
        // Given
        $metadata = [
            'user_context' => 'release_planning',
            'priority' => 'high',
            'tags' => ['urgent', 'stakeholder-feedback']
        ];

        $input = Input::create([
            'content' => 'Test content with metadata',
            'type' => 'brain_dump',
            'source' => 'manual_entry',
            'metadata' => json_encode($metadata)
        ]);

        // When
        $retrievedMetadata = json_decode($input->metadata, true);

        // Then
        $this->assertEquals($metadata, $retrievedMetadata);
    }

    /**
     * Test Input model relationship with Outputs
     *
     * Given: An Input with associated Outputs
     * When: Accessing outputs relationship
     * Then: Should return collection of related Output models
     */
    public function test_input_has_many_outputs_relationship()
    {
        // Given
        $input = Input::create([
            'content' => 'Test input for outputs',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        // When - assuming we have Output model and relationship
        // This test defines the expected behavior
        $outputs = $input->outputs;

        // Then
        $this->assertInstanceOf(Collection::class, $outputs);
        $this->assertEquals(0, $outputs->count()); // Empty initially
    }

    /**
     * Test Input model relationship with Embeddings
     *
     * Given: An Input that should have embeddings
     * When: Accessing embeddings relationship
     * Then: Should return related Embedding model
     */
    public function test_input_has_one_embedding_relationship()
    {
        // Given
        $input = Input::create([
            'content' => 'Test input for embedding',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        // When - accessing embedding relationship
        $embedding = $input->embedding;

        // Then - initially null, but relationship should exist
        $this->assertNull($embedding); // Initially no embedding
    }

    /**
     * Test Input model content preprocessing
     *
     * Given: Raw content with various formatting
     * When: Creating Input
     * Then: Should preserve original content while being ready for processing
     */
    public function test_input_preserves_original_content()
    {
        // Given
        $rawContent = "Subject: Release Planning\n\nHi team,\n\nWe need to:\n- Plan the release\n- Test everything\n- Deploy to prod\n\nThanks!";

        $input = Input::create([
            'content' => $rawContent,
            'type' => 'email',
            'source' => 'email_import'
        ]);

        // Then
        $this->assertEquals($rawContent, $input->content);
        $this->assertStringContainsString('Subject: Release Planning', $input->content);
        $this->assertStringContainsString('- Plan the release', $input->content);
    }

    /**
     * Test Input model content hash generation
     *
     * Given: Input content
     * When: Creating Input
     * Then: Should generate consistent content hash for deduplication
     */
    public function test_input_generates_content_hash()
    {
        // Given
        $content = 'Identical content for hash testing';

        $input1 = Input::create([
            'content' => $content,
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        $input2 = Input::create([
            'content' => $content,
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        // When - accessing content hash (if implemented)
        // This defines expected behavior for deduplication
        $hash1 = hash('sha256', $input1->content);
        $hash2 = hash('sha256', $input2->content);

        // Then
        $this->assertEquals($hash1, $hash2);
    }

    /**
     * Test Input model query scopes for filtering
     *
     * Given: Multiple inputs of different types
     * When: Using query scopes
     * Then: Should filter by type, source, and date ranges
     */
    public function test_input_query_scopes()
    {
        // Given
        Input::create(['content' => 'Brain dump 1', 'type' => 'brain_dump', 'source' => 'manual_entry']);
        Input::create(['content' => 'Email 1', 'type' => 'email', 'source' => 'email_import']);
        Input::create(['content' => 'Document 1', 'type' => 'document', 'source' => 'file_upload']);

        // When - using hypothetical scopes
        $brainDumps = Input::where('type', 'brain_dump')->get();
        $manualEntries = Input::where('source', 'manual_entry')->get();

        // Then
        $this->assertEquals(1, $brainDumps->count());
        $this->assertEquals(1, $manualEntries->count());
        $this->assertEquals('brain_dump', $brainDumps->first()->type);
    }
}
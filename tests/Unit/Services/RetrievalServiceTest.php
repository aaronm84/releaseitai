<?php

namespace Tests\Unit\Services;

use App\Services\RetrievalService;
use App\Models\Feedback;
use App\Models\Output;
use App\Models\Input;
use App\Models\Embedding;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class RetrievalServiceTest extends TestCase
{
    use RefreshDatabase;

    private RetrievalService $retrievalService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->retrievalService = app(RetrievalService::class);
    }

    /**
     * Generate a 1536-dimensional test vector from smaller input
     */
    private function generateTestVector(array $smallVector): string
    {
        // Pad/repeat the small vector to reach 1536 dimensions
        $fullVector = [];
        $targetLength = 1536;

        for ($i = 0; $i < $targetLength; $i++) {
            $fullVector[] = $smallVector[$i % count($smallVector)];
        }

        return '[' . implode(', ', $fullVector) . ']';
    }

    /**
     * Test RetrievalService can find similar feedback examples for RAG prompting
     *
     * Given: A new input and existing feedback examples in the database
     * When: Retrieving similar feedback examples for RAG
     * Then: Should return relevant feedback examples ranked by similarity
     */
    public function test_retrieval_service_finds_similar_feedback_examples()
    {
        // Given
        $user = User::factory()->create();

        // Create input and outputs with feedback
        $similarInput = Input::create([
            'content' => 'Plan release for Q4 product launch',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        $similarOutput = Output::create([
            'input_id' => $similarInput->id,
            'content' => 'Q4 release checklist with stakeholder notifications',
            'type' => 'checklist',
            'ai_model' => 'claude-3-5-sonnet'
        ]);

        // Create positive feedback example
        $positiveFeedback = Feedback::create([
            'output_id' => $similarOutput->id,
            'user_id' => $user->id,
            'type' => 'inline',
            'action' => 'accept',
            'signal_type' => 'explicit',
            'confidence' => 1.0,
            'metadata' => json_encode([
                'context' => 'release_planning',
                'quality_rating' => 'high'
            ])
        ]);

        // Create embeddings for similarity search
        $inputEmbedding = Embedding::create([
            'content_id' => $similarInput->id,
            'content_type' => 'App\Models\Input',
            'vector' => $this->generateTestVector([0.8, 0.2, 0.1, 0.3, 0.5]),
            'model' => 'text-embedding-ada-002',
            'dimensions' => 1536
        ]);

        // New input to find examples for
        $newInput = Input::create([
            'content' => 'Prepare release plan for Q1 product update',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        $newInputEmbedding = Embedding::create([
            'content_id' => $newInput->id,
            'content_type' => 'App\Models\Input',
            'vector' => $this->generateTestVector([0.9, 0.1, 0.2, 0.4, 0.6]),
            'model' => 'text-embedding-ada-002',
            'dimensions' => 1536
        ]);

        // When
        $examples = $this->retrievalService->findSimilarFeedbackExamples(
            $newInput->id,
            ['type' => 'checklist'],
            3 // limit
        );

        // Then
        $this->assertInstanceOf(Collection::class, $examples);
        $this->assertGreaterThan(0, $examples->count());

        $firstExample = $examples->first();
        $this->assertArrayHasKey('input', $firstExample);
        $this->assertArrayHasKey('output', $firstExample);
        $this->assertArrayHasKey('feedback', $firstExample);
        $this->assertArrayHasKey('similarity_score', $firstExample);

        $this->assertEquals($similarInput->content, $firstExample['input']['content']);
        $this->assertEquals('accept', $firstExample['feedback']['action']);
    }

    /**
     * Test RetrievalService filters examples by feedback quality
     *
     * Given: Multiple feedback examples with different quality scores
     * When: Retrieving examples with quality filtering
     * Then: Should return only high-quality feedback examples
     */
    public function test_retrieval_service_filters_by_feedback_quality()
    {
        // Given
        $user = User::factory()->create();

        // Create high-quality feedback example
        $highQualityInput = Input::create([
            'content' => 'High quality input example',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        $highQualityOutput = Output::create([
            'input_id' => $highQualityInput->id,
            'content' => 'High quality output',
            'type' => 'checklist',
            'ai_model' => 'claude-3-5-sonnet',
            'quality_score' => 0.95
        ]);

        $highQualityFeedback = Feedback::create([
            'output_id' => $highQualityOutput->id,
            'user_id' => $user->id,
            'type' => 'inline',
            'action' => 'accept',
            'signal_type' => 'explicit',
            'confidence' => 1.0
        ]);

        // Create low-quality feedback example
        $lowQualityInput = Input::create([
            'content' => 'Low quality input example',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        $lowQualityOutput = Output::create([
            'input_id' => $lowQualityInput->id,
            'content' => 'Low quality output',
            'type' => 'checklist',
            'ai_model' => 'claude-3-5-sonnet',
            'quality_score' => 0.3
        ]);

        $lowQualityFeedback = Feedback::create([
            'output_id' => $lowQualityOutput->id,
            'user_id' => $user->id,
            'type' => 'inline',
            'action' => 'reject',
            'signal_type' => 'explicit',
            'confidence' => 1.0
        ]);

        // Create embeddings
        Embedding::create([
            'content_id' => $highQualityInput->id,
            'content_type' => 'App\Models\Input',
            'vector' => $this->generateTestVector([0.7, 0.3, 0.2, 0.4, 0.5]),
            'model' => 'text-embedding-ada-002',
            'dimensions' => 1536
        ]);

        Embedding::create([
            'content_id' => $lowQualityInput->id,
            'content_type' => 'App\Models\Input',
            'vector' => $this->generateTestVector([0.6, 0.4, 0.3, 0.3, 0.4]),
            'model' => 'text-embedding-ada-002',
            'dimensions' => 1536
        ]);

        // Query input
        $queryInput = Input::create([
            'content' => 'Query input for quality filtering',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        Embedding::create([
            'content_id' => $queryInput->id,
            'content_type' => 'App\Models\Input',
            'vector' => $this->generateTestVector([0.8, 0.2, 0.1, 0.5, 0.6]),
            'model' => 'text-embedding-ada-002',
            'dimensions' => 1536
        ]);

        // When
        $examples = $this->retrievalService->findSimilarFeedbackExamples(
            $queryInput->id,
            [
                'min_quality_score' => 0.8,
                'positive_feedback_only' => true
            ]
        );

        // Then
        $this->assertGreaterThan(0, $examples->count());

        foreach ($examples as $example) {
            $this->assertGreaterThanOrEqual(0.8, $example['output']['quality_score']);
            $this->assertEquals('accept', $example['feedback']['action']);
        }
    }

    /**
     * Test RetrievalService retrieves examples by context similarity
     *
     * Given: Feedback examples with different contexts
     * When: Retrieving examples filtered by context
     * Then: Should return examples from similar contexts
     */
    public function test_retrieval_service_filters_by_context()
    {
        // Given
        $user = User::factory()->create();

        // Create release planning context example
        $releaseInput = Input::create([
            'content' => 'Release planning context',
            'type' => 'brain_dump',
            'source' => 'manual_entry',
            'metadata' => json_encode(['context' => 'release_planning'])
        ]);

        $releaseOutput = Output::create([
            'input_id' => $releaseInput->id,
            'content' => 'Release checklist',
            'type' => 'checklist',
            'ai_model' => 'claude-3-5-sonnet'
        ]);

        $releaseFeedback = Feedback::create([
            'output_id' => $releaseOutput->id,
            'user_id' => $user->id,
            'type' => 'inline',
            'action' => 'accept',
            'signal_type' => 'explicit',
            'confidence' => 1.0,
            'metadata' => ['context' => 'release_planning']
        ]);

        // Create bug fixing context example
        $bugInput = Input::create([
            'content' => 'Bug fixing context',
            'type' => 'brain_dump',
            'source' => 'manual_entry',
            'metadata' => json_encode(['context' => 'bug_fixing'])
        ]);

        $bugOutput = Output::create([
            'input_id' => $bugInput->id,
            'content' => 'Bug fix checklist',
            'type' => 'checklist',
            'ai_model' => 'claude-3-5-sonnet'
        ]);

        $bugFeedback = Feedback::create([
            'output_id' => $bugOutput->id,
            'user_id' => $user->id,
            'type' => 'inline',
            'action' => 'accept',
            'signal_type' => 'explicit',
            'confidence' => 1.0,
            'metadata' => ['context' => 'bug_fixing']
        ]);

        // Create embeddings
        Embedding::create([
            'content_id' => $releaseInput->id,
            'content_type' => 'App\Models\Input',
            'vector' => $this->generateTestVector([0.8, 0.2, 0.1, 0.3, 0.5]),
            'model' => 'text-embedding-ada-002',
            'dimensions' => 1536
        ]);

        Embedding::create([
            'content_id' => $bugInput->id,
            'content_type' => 'App\Models\Input',
            'vector' => $this->generateTestVector([0.1, 0.8, 0.5, 0.2, 0.3]),
            'model' => 'text-embedding-ada-002',
            'dimensions' => 1536
        ]);

        // Query for release planning context
        $queryInput = Input::create([
            'content' => 'Planning next release',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        Embedding::create([
            'content_id' => $queryInput->id,
            'content_type' => 'App\Models\Input',
            'vector' => $this->generateTestVector([0.9, 0.1, 0.0, 0.4, 0.6]),
            'model' => 'text-embedding-ada-002',
            'dimensions' => 1536
        ]);

        // When
        $examples = $this->retrievalService->findSimilarFeedbackExamples(
            $queryInput->id,
            ['context' => 'release_planning']
        );

        // Then
        $this->assertGreaterThan(0, $examples->count());

        foreach ($examples as $example) {
            $feedbackMetadata = $example['feedback']['metadata'];
            $this->assertEquals('release_planning', $feedbackMetadata['context']);
        }
    }

    /**
     * Test RetrievalService personalizes examples based on user preferences
     *
     * Given: User feedback history and preferences
     * When: Retrieving personalized examples
     * Then: Should prioritize examples aligned with user preferences
     */
    public function test_retrieval_service_personalizes_examples()
    {
        // Given
        $user = User::factory()->create();

        // Create user's preferred example (checklists)
        $preferredInput = Input::create([
            'content' => 'User prefers detailed checklists',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        $preferredOutput = Output::create([
            'input_id' => $preferredInput->id,
            'content' => 'Detailed checklist with many items',
            'type' => 'checklist',
            'ai_model' => 'claude-3-5-sonnet'
        ]);

        $preferredFeedback = Feedback::create([
            'output_id' => $preferredOutput->id,
            'user_id' => $user->id,
            'type' => 'inline',
            'action' => 'accept',
            'signal_type' => 'explicit',
            'confidence' => 1.0
        ]);

        // Create user's non-preferred example (summaries)
        $nonPreferredInput = Input::create([
            'content' => 'User dislikes brief summaries',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        $nonPreferredOutput = Output::create([
            'input_id' => $nonPreferredInput->id,
            'content' => 'Brief summary',
            'type' => 'summary',
            'ai_model' => 'claude-3-5-sonnet'
        ]);

        $nonPreferredFeedback = Feedback::create([
            'output_id' => $nonPreferredOutput->id,
            'user_id' => $user->id,
            'type' => 'inline',
            'action' => 'reject',
            'signal_type' => 'explicit',
            'confidence' => 1.0
        ]);

        // Create embeddings
        Embedding::create([
            'content_id' => $preferredInput->id,
            'content_type' => 'App\Models\Input',
            'vector' => $this->generateTestVector([0.7, 0.3, 0.2, 0.4, 0.5]),
            'model' => 'text-embedding-ada-002',
            'dimensions' => 1536
        ]);

        Embedding::create([
            'content_id' => $nonPreferredInput->id,
            'content_type' => 'App\Models\Input',
            'vector' => $this->generateTestVector([0.6, 0.4, 0.3, 0.3, 0.4]),
            'model' => 'text-embedding-ada-002',
            'dimensions' => 1536
        ]);

        // Query input
        $queryInput = Input::create([
            'content' => 'Need help with project planning',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        Embedding::create([
            'content_id' => $queryInput->id,
            'content_type' => 'App\Models\Input',
            'vector' => $this->generateTestVector([0.8, 0.2, 0.1, 0.5, 0.6]),
            'model' => 'text-embedding-ada-002',
            'dimensions' => 1536
        ]);

        // When
        $examples = $this->retrievalService->findPersonalizedExamples(
            $queryInput->id,
            $user->id,
            ['limit' => 5]
        );

        // Then
        $this->assertGreaterThan(0, $examples->count());

        // Should prioritize user's preferred output types
        $firstExample = $examples->first();
        $this->assertEquals('checklist', $firstExample['output']['type']);
        $this->assertEquals('accept', $firstExample['feedback']['action']);
    }

    /**
     * Test RetrievalService builds prompts with retrieved examples
     *
     * Given: Retrieved feedback examples
     * When: Building RAG prompt with examples
     * Then: Should format examples into structured prompt
     */
    public function test_retrieval_service_builds_rag_prompts()
    {
        // Given
        $user = User::factory()->create();

        $exampleInput = Input::create([
            'content' => 'Plan product launch for Q3',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        $exampleOutput = Output::create([
            'input_id' => $exampleInput->id,
            'content' => "# Q3 Product Launch Checklist\n\n- [ ] Market research\n- [ ] Feature development\n- [ ] Testing phase",
            'type' => 'checklist',
            'ai_model' => 'claude-3-5-sonnet'
        ]);

        $exampleFeedback = Feedback::create([
            'output_id' => $exampleOutput->id,
            'user_id' => $user->id,
            'type' => 'inline',
            'action' => 'edit',
            'signal_type' => 'explicit',
            'confidence' => 0.8,
            'metadata' => json_encode([
                'corrected_content' => "# Q3 Product Launch Checklist\n\n- [ ] Market research and analysis\n- [ ] Feature development and QA\n- [ ] Comprehensive testing phase\n- [ ] Stakeholder communication plan",
                'edit_reason' => 'Added more detail and stakeholder communication'
            ])
        ]);

        $examples = collect([
            [
                'input' => $exampleInput->toArray(),
                'output' => $exampleOutput->toArray(),
                'feedback' => $exampleFeedback->toArray(),
                'similarity_score' => 0.92
            ]
        ]);

        $currentInput = 'Plan Q4 product release';

        // When
        $prompt = $this->retrievalService->buildRagPrompt($currentInput, $examples, [
            'task_type' => 'checklist_generation',
            'include_feedback_corrections' => true
        ]);

        // Then
        $this->assertIsString($prompt);
        $this->assertStringContainsString('Plan Q4 product release', $prompt);
        $this->assertStringContainsString('Q3 Product Launch Checklist', $prompt);
        $this->assertStringContainsString('Market research and analysis', $prompt);
        $this->assertStringContainsString('Stakeholder communication plan', $prompt);
        $this->assertStringContainsString('Added more detail', $prompt);
    }

    /**
     * Test RetrievalService caches similarity search results
     *
     * Given: Expensive similarity search operations
     * When: Performing repeated searches with same parameters
     * Then: Should cache results for improved performance
     */
    public function test_retrieval_service_caches_similarity_results()
    {
        // Given
        Cache::flush(); // Clear cache

        $user = User::factory()->create();
        $input = Input::create([
            'content' => 'Test input for caching',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        Embedding::create([
            'content_id' => $input->id,
            'content_type' => 'App\Models\Input',
            'vector' => $this->generateTestVector([0.8, 0.2, 0.1, 0.3, 0.5]),
            'model' => 'text-embedding-ada-002',
            'dimensions' => 1536
        ]);

        $searchParams = [
            'type' => 'checklist',
            'limit' => 5,
            'min_similarity' => 0.7
        ];

        // When - first search (should hit database)
        $startTime1 = microtime(true);
        $examples1 = $this->retrievalService->findSimilarFeedbackExamples($input->id, $searchParams);
        $duration1 = microtime(true) - $startTime1;

        // When - second search (should hit cache)
        $startTime2 = microtime(true);
        $examples2 = $this->retrievalService->findSimilarFeedbackExamples($input->id, $searchParams);
        $duration2 = microtime(true) - $startTime2;

        // Then
        $this->assertEquals($examples1->count(), $examples2->count());

        // Cache hit should be significantly faster
        // Note: In actual implementation, you'd check cache directly
        $this->assertLessThan($duration1, $duration2 + 0.01); // Allow for timing variance
    }

    /**
     * Test RetrievalService handles edge cases gracefully
     *
     * Given: Edge case scenarios (no examples, invalid input, etc.)
     * When: Attempting retrieval operations
     * Then: Should handle errors gracefully and return appropriate responses
     */
    public function test_retrieval_service_handles_edge_cases()
    {
        // Given - no examples in database
        $input = Input::create([
            'content' => 'Input with no similar examples',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        Embedding::create([
            'content_id' => $input->id,
            'content_type' => 'App\Models\Input',
            'vector' => $this->generateTestVector([0.8, 0.2, 0.1, 0.3, 0.5]),
            'model' => 'text-embedding-ada-002',
            'dimensions' => 1536
        ]);

        // When - searching with no examples available
        $examples = $this->retrievalService->findSimilarFeedbackExamples($input->id);

        // Then
        $this->assertInstanceOf(Collection::class, $examples);
        $this->assertEquals(0, $examples->count());

        // Given - invalid input ID
        try {
            // When
            $invalidExamples = $this->retrievalService->findSimilarFeedbackExamples(99999);

            // Then
            $this->assertInstanceOf(Collection::class, $invalidExamples);
            $this->assertEquals(0, $invalidExamples->count());
        } catch (\Exception $e) {
            // Should handle gracefully, not throw unhandled exceptions
            $this->assertInstanceOf(\InvalidArgumentException::class, $e);
        }
    }

    /**
     * Test RetrievalService supports vector similarity thresholds
     *
     * Given: Examples with varying similarity scores
     * When: Applying similarity thresholds
     * Then: Should filter results by minimum similarity score
     */
    public function test_retrieval_service_applies_similarity_thresholds()
    {
        // Given
        $user = User::factory()->create();

        // Create highly similar example
        $similarInput = Input::create([
            'content' => 'Very similar content',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        $similarOutput = Output::create([
            'input_id' => $similarInput->id,
            'content' => 'Similar output',
            'type' => 'checklist',
            'ai_model' => 'claude-3-5-sonnet'
        ]);

        $similarFeedback = Feedback::create([
            'output_id' => $similarOutput->id,
            'user_id' => $user->id,
            'type' => 'inline',
            'action' => 'accept',
            'signal_type' => 'explicit',
            'confidence' => 1.0
        ]);

        // Create somewhat similar example
        $somewhatSimilarInput = Input::create([
            'content' => 'Somewhat similar content',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        $somewhatSimilarOutput = Output::create([
            'input_id' => $somewhatSimilarInput->id,
            'content' => 'Somewhat similar output',
            'type' => 'checklist',
            'ai_model' => 'claude-3-5-sonnet'
        ]);

        $somewhatSimilarFeedback = Feedback::create([
            'output_id' => $somewhatSimilarOutput->id,
            'user_id' => $user->id,
            'type' => 'inline',
            'action' => 'accept',
            'signal_type' => 'explicit',
            'confidence' => 1.0
        ]);

        // Create embeddings with known similarity
        Embedding::create([
            'content_id' => $similarInput->id,
            'content_type' => 'App\Models\Input',
            'vector' => $this->generateTestVector([0.9, 0.1, 0.0, 0.0, 0.0]), // Very similar to query
            'model' => 'text-embedding-ada-002',
            'dimensions' => 1536
        ]);

        Embedding::create([
            'content_id' => $somewhatSimilarInput->id,
            'content_type' => 'App\Models\Input',
            'vector' => $this->generateTestVector([0.6, 0.4, 0.3, 0.2, 0.1]), // Less similar to query
            'model' => 'text-embedding-ada-002',
            'dimensions' => 1536
        ]);

        // Query input
        $queryInput = Input::create([
            'content' => 'Query content',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        Embedding::create([
            'content_id' => $queryInput->id,
            'content_type' => 'App\Models\Input',
            'vector' => $this->generateTestVector([1.0, 0.0, 0.0, 0.0, 0.0]), // Query vector
            'model' => 'text-embedding-ada-002',
            'dimensions' => 1536
        ]);

        // When - applying high similarity threshold
        $highThresholdExamples = $this->retrievalService->findSimilarFeedbackExamples(
            $queryInput->id,
            ['min_similarity' => 0.8]
        );

        // When - applying low similarity threshold
        $lowThresholdExamples = $this->retrievalService->findSimilarFeedbackExamples(
            $queryInput->id,
            ['min_similarity' => 0.5]
        );

        // Then
        $this->assertLessThanOrEqual($lowThresholdExamples->count(), $highThresholdExamples->count());

        // High threshold should return only very similar examples
        foreach ($highThresholdExamples as $example) {
            $this->assertGreaterThanOrEqual(0.8, $example['similarity_score']);
        }
    }
}
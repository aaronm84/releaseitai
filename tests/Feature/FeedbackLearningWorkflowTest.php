<?php

namespace Tests\Feature;

use App\Services\FeedbackService;
use App\Services\RetrievalService;
use App\Models\Input;
use App\Models\Output;
use App\Models\Feedback;
use App\Models\Embedding;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Event;

class FeedbackLearningWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private FeedbackService $feedbackService;
    private RetrievalService $retrievalService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->feedbackService = app(FeedbackService::class);
        $this->retrievalService = app(RetrievalService::class);
    }

    /**
     * Test complete end-to-end feedback and learning workflow
     *
     * Given: A user provides input for AI processing
     * When: Going through the complete feedback and learning cycle
     * Then: Should capture feedback, store embeddings, and improve future responses
     */
    public function test_complete_feedback_learning_workflow()
    {
        // Given
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);

        // Step 1: User provides input
        $userInput = Input::create([
            'content' => 'I need to plan a product release for Q1 2024. We have a new feature set including user authentication, dashboard improvements, and mobile app updates. Need to coordinate with engineering, marketing, and support teams.',
            'type' => 'brain_dump',
            'source' => 'manual_entry',
            'metadata' => json_encode([
                'user_context' => 'release_planning',
                'urgency' => 'high',
                'complexity' => 'medium'
            ])
        ]);

        // Step 2: AI generates output
        $aiOutput = Output::create([
            'input_id' => $userInput->id,
            'content' => json_encode([
                'title' => 'Q1 2024 Product Release Plan',
                'checklist' => [
                    ['id' => 1, 'task' => 'Define release scope and timeline', 'assigned_team' => 'product', 'status' => 'pending'],
                    ['id' => 2, 'task' => 'User authentication feature development', 'assigned_team' => 'engineering', 'status' => 'pending'],
                    ['id' => 3, 'task' => 'Dashboard improvements implementation', 'assigned_team' => 'engineering', 'status' => 'pending'],
                    ['id' => 4, 'task' => 'Mobile app updates development', 'assigned_team' => 'engineering', 'status' => 'pending'],
                    ['id' => 5, 'task' => 'Marketing campaign preparation', 'assigned_team' => 'marketing', 'status' => 'pending'],
                    ['id' => 6, 'task' => 'Support documentation updates', 'assigned_team' => 'support', 'status' => 'pending']
                ],
                'timeline' => '12 weeks',
                'key_milestones' => [
                    'Week 4: Feature freeze',
                    'Week 8: Beta testing',
                    'Week 12: Release'
                ]
            ]),
            'type' => 'checklist',
            'ai_model' => 'claude-3-5-sonnet',
            'quality_score' => 0.85,
            'metadata' => json_encode([
                'processing_time' => 2.3,
                'tokens_used' => 1850,
                'confidence' => 0.87
            ])
        ]);

        // Step 3: Generate embeddings for both input and output
        $inputEmbedding = Embedding::create([
            'content_id' => $userInput->id,
            'content_type' => 'App\Models\Input',
            'vector' => '[0.1, 0.3, 0.8, 0.2, 0.4, 0.7, 0.1, 0.9, 0.3, 0.5]', // Release planning vector
            'model' => 'text-embedding-ada-002',
            'dimensions' => 10,
            'metadata' => json_encode([
                'generation_time' => 0.15,
                'content_hash' => hash('sha256', $userInput->content)
            ])
        ]);

        $outputEmbedding = Embedding::create([
            'content_id' => $aiOutput->id,
            'content_type' => 'App\Models\Output',
            'vector' => '[0.2, 0.4, 0.7, 0.3, 0.5, 0.6, 0.2, 0.8, 0.4, 0.6]', // Checklist vector
            'model' => 'text-embedding-ada-002',
            'dimensions' => 10,
            'metadata' => json_encode([
                'generation_time' => 0.18,
                'content_hash' => hash('sha256', $aiOutput->content)
            ])
        ]);

        // Step 4: User provides inline feedback (edit)
        $feedbackData = [
            'output_id' => $aiOutput->id,
            'user_id' => $user->id,
            'action' => 'edit',
            'original_content' => $aiOutput->content,
            'corrected_content' => json_encode([
                'title' => 'Q1 2024 Product Release Plan',
                'checklist' => [
                    ['id' => 1, 'task' => 'Define release scope and timeline with stakeholder input', 'assigned_team' => 'product', 'status' => 'pending'],
                    ['id' => 2, 'task' => 'User authentication feature development', 'assigned_team' => 'engineering', 'status' => 'pending'],
                    ['id' => 3, 'task' => 'Dashboard improvements implementation', 'assigned_team' => 'engineering', 'status' => 'pending'],
                    ['id' => 4, 'task' => 'Mobile app updates development', 'assigned_team' => 'engineering', 'status' => 'pending'],
                    ['id' => 5, 'task' => 'Quality assurance and testing phase', 'assigned_team' => 'qa', 'status' => 'pending'],
                    ['id' => 6, 'task' => 'Marketing campaign preparation', 'assigned_team' => 'marketing', 'status' => 'pending'],
                    ['id' => 7, 'task' => 'Support documentation updates', 'assigned_team' => 'support', 'status' => 'pending'],
                    ['id' => 8, 'task' => 'Legal and compliance review', 'assigned_team' => 'legal', 'status' => 'pending']
                ],
                'timeline' => '14 weeks',
                'key_milestones' => [
                    'Week 2: Stakeholder alignment',
                    'Week 6: Feature freeze',
                    'Week 10: Beta testing',
                    'Week 14: Release'
                ]
            ]),
            'edit_reason' => 'Added stakeholder input, QA phase, legal review, and extended timeline for better planning',
            'context' => [
                'session_id' => 'sess_12345',
                'edit_time_spent' => 180.5,
                'user_experience_level' => 'expert',
                'task_complexity' => 'high'
            ]
        ];

        // When - capturing the feedback
        $feedback = $this->feedbackService->captureInlineFeedback($feedbackData);

        // Then - verify feedback was captured correctly
        $this->assertInstanceOf(Feedback::class, $feedback);
        $this->assertEquals('edit', $feedback->action);
        $this->assertEquals('inline', $feedback->type);
        $this->assertEquals('explicit', $feedback->signal_type);
        $this->assertEquals(0.7, $feedback->confidence);

        $feedbackMetadata = json_decode($feedback->metadata, true);
        $this->assertEquals('Added stakeholder input, QA phase, legal review, and extended timeline for better planning', $feedbackMetadata['edit_reason']);
        $this->assertEquals('expert', $feedbackMetadata['context']['user_experience_level']);

        // Step 5: Simulate passive signals (task completion)
        $passiveSignalData = [
            'output_id' => $aiOutput->id,
            'user_id' => $user->id,
            'signal_type' => 'task_completed',
            'task_id' => 'task_1',
            'completion_time' => 7200, // 2 hours
            'context' => [
                'completion_method' => 'manual_check',
                'task_difficulty' => 'medium',
                'user_satisfaction' => 'high'
            ]
        ];

        $passiveFeedback = $this->feedbackService->capturePassiveSignal($passiveSignalData);

        $this->assertInstanceOf(Feedback::class, $passiveFeedback);
        $this->assertEquals('task_completed', $passiveFeedback->action);
        $this->assertEquals('behavioral', $passiveFeedback->type);
        $this->assertEquals('passive', $passiveFeedback->signal_type);

        // Step 6: Process feedback for learning
        $learningResult = $this->feedbackService->processFeedbackForLearning($feedbackData);

        $this->assertTrue($learningResult['success']);
        $this->assertArrayHasKey('learning_data', $learningResult);
        $this->assertArrayHasKey('pattern_id', $learningResult);

        // Step 7: Test retrieval of similar examples for future prompts
        // Create a new similar input
        $newInput = Input::create([
            'content' => 'Planning Q2 2024 product release with new API features and mobile improvements. Need coordination across teams.',
            'type' => 'brain_dump',
            'source' => 'manual_entry',
            'metadata' => json_encode([
                'user_context' => 'release_planning',
                'urgency' => 'medium',
                'complexity' => 'medium'
            ])
        ]);

        $newInputEmbedding = Embedding::create([
            'content_id' => $newInput->id,
            'content_type' => 'App\Models\Input',
            'vector' => '[0.15, 0.35, 0.75, 0.25, 0.45, 0.65, 0.15, 0.85, 0.35, 0.55]', // Similar to original
            'model' => 'text-embedding-ada-002',
            'dimensions' => 10
        ]);

        // When - retrieving similar examples
        $similarExamples = $this->retrievalService->findSimilarFeedbackExamples(
            $newInput->id,
            [
                'type' => 'checklist',
                'positive_feedback_only' => false,
                'limit' => 3
            ]
        );

        // Then - should find the previous example with feedback
        $this->assertGreaterThan(0, $similarExamples->count());

        $firstExample = $similarExamples->first();
        $this->assertEquals($userInput->id, $firstExample['input']['id']);
        $this->assertEquals($aiOutput->id, $firstExample['output']['id']);
        $this->assertEquals('edit', $firstExample['feedback']['action']);

        // Step 8: Build RAG prompt with feedback examples
        $ragPrompt = $this->retrievalService->buildRagPrompt(
            $newInput->content,
            $similarExamples,
            [
                'task_type' => 'checklist_generation',
                'include_feedback_corrections' => true,
                'context' => 'release_planning'
            ]
        );

        // Then - prompt should include lessons learned from feedback
        $this->assertStringContainsString('Q2 2024 product release', $ragPrompt);
        $this->assertStringContainsString('stakeholder input', $ragPrompt);
        $this->assertStringContainsString('QA phase', $ragPrompt);
        $this->assertStringContainsString('legal review', $ragPrompt);
        $this->assertStringContainsString('14 weeks', $ragPrompt);

        // Step 9: Verify learning system can aggregate patterns
        $feedbackPatterns = $this->feedbackService->aggregateFeedbackPatterns($aiOutput->id);

        $this->assertArrayHasKey('action_distribution', $feedbackPatterns);
        $this->assertArrayHasKey('average_confidence', $feedbackPatterns);
        $this->assertArrayHasKey('total_feedback_count', $feedbackPatterns);

        $this->assertEquals(2, $feedbackPatterns['total_feedback_count']); // edit + task_completed
        $this->assertEquals(1, $feedbackPatterns['action_distribution']['edit']);
        $this->assertEquals(1, $feedbackPatterns['action_distribution']['task_completed']);

        // Step 10: Test user preference learning
        $userPreferences = $this->feedbackService->updateUserPreferences($user->id, [
            ['action' => 'edit', 'output_type' => 'checklist', 'detail_level' => 'high'],
            ['action' => 'task_completed', 'output_type' => 'checklist', 'detail_level' => 'high']
        ]);

        $this->assertArrayHasKey('preferred_output_types', $userPreferences);
        $this->assertArrayHasKey('preferred_detail_level', $userPreferences);
        $this->assertContains('checklist', $userPreferences['preferred_output_types']);
        $this->assertEquals('high', $userPreferences['preferred_detail_level']);
    }

    /**
     * Test feedback workflow with multiple users and personalization
     *
     * Given: Multiple users with different preferences
     * When: Processing feedback from different users
     * Then: Should maintain personalized learning for each user
     */
    public function test_multi_user_personalized_feedback_workflow()
    {
        // Given
        $expertUser = User::factory()->create(['name' => 'Expert User', 'email' => 'expert@example.com']);
        $noviceUser = User::factory()->create(['name' => 'Novice User', 'email' => 'novice@example.com']);

        $sharedInput = Input::create([
            'content' => 'Need help organizing project tasks for upcoming sprint',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        $output = Output::create([
            'input_id' => $sharedInput->id,
            'content' => 'Basic project task list',
            'type' => 'checklist',
            'ai_model' => 'claude-3-5-sonnet'
        ]);

        Embedding::create([
            'content_id' => $sharedInput->id,
            'content_type' => 'App\Models\Input',
            'vector' => '[0.5, 0.5, 0.5, 0.5, 0.5]',
            'model' => 'text-embedding-ada-002',
            'dimensions' => 5
        ]);

        // Expert user wants more detail (edit)
        $expertFeedback = $this->feedbackService->captureInlineFeedback([
            'output_id' => $output->id,
            'user_id' => $expertUser->id,
            'action' => 'edit',
            'corrected_content' => 'Detailed project task list with dependencies and estimates',
            'edit_reason' => 'Added technical details and time estimates',
            'context' => ['user_experience_level' => 'expert']
        ]);

        // Novice user accepts as-is
        $noviceFeedback = $this->feedbackService->captureInlineFeedback([
            'output_id' => $output->id,
            'user_id' => $noviceUser->id,
            'action' => 'accept',
            'context' => ['user_experience_level' => 'novice']
        ]);

        // When - creating new similar input
        $newInput = Input::create([
            'content' => 'Help with sprint planning tasks',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        Embedding::create([
            'content_id' => $newInput->id,
            'content_type' => 'App\Models\Input',
            'vector' => '[0.6, 0.4, 0.5, 0.6, 0.4]',
            'model' => 'text-embedding-ada-002',
            'dimensions' => 5
        ]);

        // Then - personalized retrieval for expert should prefer detailed examples
        $expertExamples = $this->retrievalService->findPersonalizedExamples($newInput->id, $expertUser->id);
        $noviceExamples = $this->retrievalService->findPersonalizedExamples($newInput->id, $noviceUser->id);

        // Expert examples should include edit feedback
        if ($expertExamples->count() > 0) {
            $expertExample = $expertExamples->first();
            $this->assertEquals('edit', $expertExample['feedback']['action']);
        }

        // Novice examples should include accept feedback
        if ($noviceExamples->count() > 0) {
            $noviceExample = $noviceExamples->first();
            $this->assertEquals('accept', $noviceExample['feedback']['action']);
        }

        // Verify user preferences are different
        $expertPrefs = $this->feedbackService->updateUserPreferences($expertUser->id, [
            ['action' => 'edit', 'detail_level' => 'high']
        ]);

        $novicePrefs = $this->feedbackService->updateUserPreferences($noviceUser->id, [
            ['action' => 'accept', 'detail_level' => 'medium']
        ]);

        $this->assertEquals('high', $expertPrefs['preferred_detail_level']);
        $this->assertEquals('medium', $novicePrefs['preferred_detail_level']);
    }

    /**
     * Test feedback workflow handles large scale operations
     *
     * Given: High volume of feedback and retrieval operations
     * When: Processing many feedback instances and retrievals
     * Then: Should handle scale efficiently with proper performance
     */
    public function test_feedback_workflow_handles_scale()
    {
        // Given
        $users = User::factory()->count(10)->create();
        $inputs = [];
        $outputs = [];

        // Create 50 input/output pairs
        for ($i = 0; $i < 50; $i++) {
            $input = Input::create([
                'content' => "Content for test case {$i}",
                'type' => 'brain_dump',
                'source' => 'manual_entry'
            ]);

            $output = Output::create([
                'input_id' => $input->id,
                'content' => "Output for test case {$i}",
                'type' => 'checklist',
                'ai_model' => 'claude-3-5-sonnet'
            ]);

            // Create embeddings
            Embedding::create([
                'content_id' => $input->id,
                'content_type' => 'App\Models\Input',
                'vector' => '[' . implode(',', array_map(fn() => rand(0, 100) / 100, range(1, 5))) . ']',
                'model' => 'text-embedding-ada-002',
                'dimensions' => 5
            ]);

            $inputs[] = $input;
            $outputs[] = $output;
        }

        // When - generating feedback for all outputs
        $startTime = microtime(true);

        foreach ($outputs as $index => $output) {
            $user = $users->random();
            $actions = ['accept', 'edit', 'reject'];

            $this->feedbackService->captureInlineFeedback([
                'output_id' => $output->id,
                'user_id' => $user->id,
                'action' => $actions[array_rand($actions)]
            ]);
        }

        $feedbackTime = microtime(true) - $startTime;

        // Then - should complete feedback capture in reasonable time
        $this->assertLessThan(10.0, $feedbackTime); // Should complete in under 10 seconds

        // When - performing multiple similarity searches
        $searchStartTime = microtime(true);

        for ($i = 0; $i < 10; $i++) {
            $randomInput = $inputs[array_rand($inputs)];
            $examples = $this->retrievalService->findSimilarFeedbackExamples($randomInput->id, ['limit' => 5]);
            $this->assertInstanceOf(\Illuminate\Support\Collection::class, $examples);
        }

        $searchTime = microtime(true) - $searchStartTime;

        // Then - similarity searches should be performant
        $this->assertLessThan(5.0, $searchTime); // Should complete searches in under 5 seconds

        // Verify data integrity
        $totalFeedback = Feedback::count();
        $this->assertEquals(50, $totalFeedback);

        $totalEmbeddings = Embedding::count();
        $this->assertEquals(50, $totalEmbeddings);
    }

    /**
     * Test feedback workflow error handling and recovery
     *
     * Given: Various error conditions during feedback processing
     * When: Encountering errors in the workflow
     * Then: Should handle errors gracefully and maintain data consistency
     */
    public function test_feedback_workflow_error_handling()
    {
        // Given
        $user = User::factory()->create();
        $input = Input::create([
            'content' => 'Test content for error handling',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        $output = Output::create([
            'input_id' => $input->id,
            'content' => 'Test output content',
            'type' => 'checklist',
            'ai_model' => 'claude-3-5-sonnet'
        ]);

        // Test invalid feedback data
        try {
            $this->feedbackService->captureInlineFeedback([
                'output_id' => 99999, // Non-existent output
                'user_id' => $user->id,
                'action' => 'accept'
            ]);

            $this->fail('Expected exception for invalid output_id');
        } catch (\Exception $e) {
            $this->assertInstanceOf(\InvalidArgumentException::class, $e);
        }

        // Test invalid user ID
        try {
            $this->feedbackService->captureInlineFeedback([
                'output_id' => $output->id,
                'user_id' => 99999, // Non-existent user
                'action' => 'accept'
            ]);

            $this->fail('Expected exception for invalid user_id');
        } catch (\Exception $e) {
            $this->assertInstanceOf(\InvalidArgumentException::class, $e);
        }

        // Test invalid action
        try {
            $this->feedbackService->captureInlineFeedback([
                'output_id' => $output->id,
                'user_id' => $user->id,
                'action' => 'invalid_action'
            ]);

            $this->fail('Expected exception for invalid action');
        } catch (\Exception $e) {
            $this->assertInstanceOf(\InvalidArgumentException::class, $e);
        }

        // Test retrieval with invalid input
        $emptyResults = $this->retrievalService->findSimilarFeedbackExamples(99999);
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $emptyResults);
        $this->assertEquals(0, $emptyResults->count());

        // Verify no partial data was created during errors
        $feedbackCount = Feedback::count();
        $this->assertEquals(0, $feedbackCount);
    }
}
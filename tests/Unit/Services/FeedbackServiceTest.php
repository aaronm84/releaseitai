<?php

namespace Tests\Unit\Services;

use App\Services\FeedbackService;
use App\Models\Feedback;
use App\Models\Output;
use App\Models\Input;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;

/**
 * Comprehensive Test-Driven Development (TDD) Test Suite for FeedbackService
 *
 * This test suite defines the complete behavioral specification for the FeedbackService
 * that handles capturing and processing user corrections in the AI feedback and learning system.
 *
 * Test Coverage:
 * 1. INLINE FEEDBACK CAPTURE - Accept/Edit/Reject actions on AI-generated content
 * 2. PASSIVE SIGNAL TRACKING - Task completion, deletion, and time spent metrics
 * 3. BATCH OPERATIONS - Processing multiple feedback items efficiently
 * 4. VALIDATION & ERROR HANDLING - Comprehensive input validation and graceful error handling
 * 5. ANALYTICS & REPORTING - Feedback trends, quality scores, and temporal analysis
 * 6. INTEGRATION - Output model updates and feedback integration status
 * 7. EDGE CASES - Rate limiting, large payloads, concurrent access, and database failures
 *
 * Key Service Requirements:
 * - Follow Laravel service patterns with dependency injection
 * - Use existing Feedback, Output, Input, and User models
 * - Handle both explicit and passive feedback types
 * - Support batch feedback processing
 * - Include proper error handling and validation
 * - Store rich contextual metadata (session info, user agent, timing data)
 * - Calculate confidence levels for feedback reliability
 * - Update output models with feedback integration status
 * - Generate analytics and insights for continuous improvement
 *
 * Usage for Developers:
 * These tests serve as both specification and validation. Implement the FeedbackService
 * to make all tests pass. Each test method defines specific expected behavior and should
 * guide implementation decisions. The test structure follows Given-When-Then format
 * for clarity and includes comprehensive edge case coverage.
 *
 * @package Tests\Unit\Services
 * @author TDD Test Specialist
 * @version 1.0
 */

class FeedbackServiceTest extends TestCase
{
    use RefreshDatabase;

    private FeedbackService $feedbackService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->feedbackService = app(FeedbackService::class);
    }

    /**
     * Test FeedbackService can capture inline accept feedback
     *
     * Given: An output and user acceptance action
     * When: Capturing accept feedback through service
     * Then: Should create explicit feedback record with high confidence
     */
    public function test_feedback_service_captures_inline_accept_feedback()
    {
        // Given
        $user = User::factory()->create();
        $input = Input::create([
            'content' => 'Test input',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        $output = Output::create([
            'input_id' => $input->id,
            'content' => 'AI-generated checklist',
            'type' => 'checklist',
            'ai_model' => 'claude-3-5-sonnet'
        ]);

        $feedbackData = [
            'output_id' => $output->id,
            'user_id' => $user->id,
            'action' => 'accept',
            'context' => [
                'session_id' => 'sess_123',
                'page_url' => '/dashboard',
                'timestamp' => now()->toISOString()
            ]
        ];

        // When
        $feedback = $this->feedbackService->captureInlineFeedback($feedbackData);

        // Then
        $this->assertInstanceOf(Feedback::class, $feedback);
        $this->assertEquals('accept', $feedback->action);
        $this->assertEquals('inline', $feedback->type);
        $this->assertEquals('explicit', $feedback->signal_type);
        $this->assertEquals(1.0, $feedback->confidence);
        $this->assertEquals($output->id, $feedback->output_id);
        $this->assertEquals($user->id, $feedback->user_id);
    }

    /**
     * Test FeedbackService can capture inline edit feedback with corrections
     *
     * Given: An output and user edit action with corrected content
     * When: Capturing edit feedback through service
     * Then: Should create feedback record with edit details and medium confidence
     */
    public function test_feedback_service_captures_inline_edit_feedback()
    {
        // Given
        $user = User::factory()->create();
        $input = Input::create([
            'content' => 'Test input',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        $output = Output::create([
            'input_id' => $input->id,
            'content' => 'Original AI content with error',
            'type' => 'checklist',
            'ai_model' => 'claude-3-5-sonnet'
        ]);

        $feedbackData = [
            'output_id' => $output->id,
            'user_id' => $user->id,
            'action' => 'edit',
            'original_content' => 'Original AI content with error',
            'corrected_content' => 'Corrected AI content without error',
            'edit_reason' => 'Fixed typo in content',
            'context' => [
                'session_id' => 'sess_123',
                'edit_time_spent' => 45.2
            ]
        ];

        // When
        $feedback = $this->feedbackService->captureInlineFeedback($feedbackData);

        // Then
        $this->assertInstanceOf(Feedback::class, $feedback);
        $this->assertEquals('edit', $feedback->action);
        $this->assertEquals('inline', $feedback->type);
        $this->assertEquals('explicit', $feedback->signal_type);
        $this->assertEquals(0.7, $feedback->confidence); // Lower confidence for edits

        $metadata = $feedback->metadata;
        $this->assertEquals('Fixed typo in content', $metadata['edit_reason']);
        $this->assertEquals('Corrected AI content without error', $metadata['corrected_content']);
    }

    /**
     * Test FeedbackService can capture inline reject feedback
     *
     * Given: An output and user rejection action
     * When: Capturing reject feedback through service
     * Then: Should create feedback record with rejection details and high confidence
     */
    public function test_feedback_service_captures_inline_reject_feedback()
    {
        // Given
        $user = User::factory()->create();
        $input = Input::create([
            'content' => 'Test input',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        $output = Output::create([
            'input_id' => $input->id,
            'content' => 'Irrelevant AI content',
            'type' => 'checklist',
            'ai_model' => 'claude-3-5-sonnet'
        ]);

        $feedbackData = [
            'output_id' => $output->id,
            'user_id' => $user->id,
            'action' => 'reject',
            'rejection_reason' => 'Content not relevant to input',
            'context' => [
                'session_id' => 'sess_123',
                'time_before_rejection' => 12.5
            ]
        ];

        // When
        $feedback = $this->feedbackService->captureInlineFeedback($feedbackData);

        // Then
        $this->assertInstanceOf(Feedback::class, $feedback);
        $this->assertEquals('reject', $feedback->action);
        $this->assertEquals('inline', $feedback->type);
        $this->assertEquals('explicit', $feedback->signal_type);
        $this->assertEquals(1.0, $feedback->confidence);

        $metadata = $feedback->metadata;
        $this->assertEquals('Content not relevant to input', $metadata['rejection_reason']);
        $this->assertEquals(12.5, $metadata['context']['time_before_rejection']);
    }

    /**
     * Test FeedbackService can capture passive task completion signals
     *
     * Given: A completed task derived from AI output
     * When: Capturing task completion as passive feedback
     * Then: Should create behavioral feedback with high confidence
     */
    public function test_feedback_service_captures_passive_task_completion()
    {
        // Given
        $user = User::factory()->create();
        $input = Input::create([
            'content' => 'Test input',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        $output = Output::create([
            'input_id' => $input->id,
            'content' => 'Task checklist from AI',
            'type' => 'checklist',
            'ai_model' => 'claude-3-5-sonnet'
        ]);

        $passiveSignalData = [
            'output_id' => $output->id,
            'user_id' => $user->id,
            'signal_type' => 'task_completed',
            'task_id' => 'task_456',
            'completion_time' => 3600, // 1 hour
            'context' => [
                'completion_method' => 'manual_check',
                'task_difficulty' => 'medium'
            ]
        ];

        // When
        $feedback = $this->feedbackService->capturePassiveSignal($passiveSignalData);

        // Then
        $this->assertInstanceOf(Feedback::class, $feedback);
        $this->assertEquals('task_completed', $feedback->action);
        $this->assertEquals('behavioral', $feedback->type);
        $this->assertEquals('passive', $feedback->signal_type);
        $this->assertEquals(0.9, $feedback->confidence); // High confidence for task completion

        $metadata = $feedback->metadata;
        $this->assertEquals('task_456', $metadata['task_id']);
        $this->assertEquals(3600, $metadata['completion_time']);
    }

    /**
     * Test FeedbackService can capture passive task deletion signals
     *
     * Given: A deleted task derived from AI output
     * When: Capturing task deletion as passive feedback
     * Then: Should create behavioral feedback with medium confidence
     */
    public function test_feedback_service_captures_passive_task_deletion()
    {
        // Given
        $user = User::factory()->create();
        $input = Input::create([
            'content' => 'Test input',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        $output = Output::create([
            'input_id' => $input->id,
            'content' => 'Task checklist from AI',
            'type' => 'checklist',
            'ai_model' => 'claude-3-5-sonnet'
        ]);

        $passiveSignalData = [
            'output_id' => $output->id,
            'user_id' => $user->id,
            'signal_type' => 'task_deleted',
            'task_id' => 'task_789',
            'time_before_deletion' => 300, // 5 minutes
            'context' => [
                'deletion_reason' => 'irrelevant',
                'user_engagement_before_deletion' => 'low'
            ]
        ];

        // When
        $feedback = $this->feedbackService->capturePassiveSignal($passiveSignalData);

        // Then
        $this->assertInstanceOf(Feedback::class, $feedback);
        $this->assertEquals('task_deleted', $feedback->action);
        $this->assertEquals('behavioral', $feedback->type);
        $this->assertEquals('passive', $feedback->signal_type);
        $this->assertEquals(0.8, $feedback->confidence); // Medium-high confidence for deletion

        $metadata = $feedback->metadata;
        $this->assertEquals('task_789', $metadata['task_id']);
        $this->assertEquals('irrelevant', $metadata['context']['deletion_reason']);
    }

    /**
     * Test FeedbackService can capture passive time spent signals
     *
     * Given: Time spent interacting with AI output
     * When: Capturing time spent as passive feedback
     * Then: Should create behavioral feedback with variable confidence based on time
     */
    public function test_feedback_service_captures_passive_time_spent()
    {
        // Given
        $user = User::factory()->create();
        $input = Input::create([
            'content' => 'Test input',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        $output = Output::create([
            'input_id' => $input->id,
            'content' => 'AI-generated content',
            'type' => 'summary',
            'ai_model' => 'claude-3-5-sonnet'
        ]);

        $timeSpentData = [
            'output_id' => $output->id,
            'user_id' => $user->id,
            'signal_type' => 'time_spent',
            'time_spent' => 120, // 2 minutes
            'engagement_metrics' => [
                'scroll_depth' => 0.8,
                'clicks' => 3,
                'hover_time' => 45.2
            ]
        ];

        // When
        $feedback = $this->feedbackService->capturePassiveSignal($timeSpentData);

        // Then
        $this->assertInstanceOf(Feedback::class, $feedback);
        $this->assertEquals('time_spent', $feedback->action);
        $this->assertEquals('behavioral', $feedback->type);
        $this->assertEquals('passive', $feedback->signal_type);

        // Confidence should be calculated based on time spent and engagement
        $this->assertGreaterThan(0.0, $feedback->confidence);
        $this->assertLessThanOrEqual(1.0, $feedback->confidence);

        $metadata = $feedback->metadata;
        $this->assertEquals(120, $metadata['time_spent']);
        $this->assertEquals(0.8, $metadata['engagement_metrics']['scroll_depth']);
    }

    /**
     * Test FeedbackService validates feedback data before processing
     *
     * Given: Invalid feedback data
     * When: Attempting to capture feedback
     * Then: Should validate data and throw appropriate exceptions
     */
    public function test_feedback_service_validates_feedback_data()
    {
        // Given - invalid data scenarios
        $invalidDataSets = [
            'missing_output_id' => [
                'user_id' => 1,
                'action' => 'accept'
            ],
            'invalid_action' => [
                'output_id' => 1,
                'user_id' => 1,
                'action' => 'invalid_action'
            ],
            'missing_user_id' => [
                'output_id' => 1,
                'action' => 'accept'
            ]
        ];

        foreach ($invalidDataSets as $testCase => $invalidData) {
            try {
                // When
                $this->feedbackService->captureInlineFeedback($invalidData);

                // Should not reach here
                $this->fail("Expected validation exception for {$testCase}");
            } catch (\Exception $e) {
                // Then
                $this->assertInstanceOf(\InvalidArgumentException::class, $e);
            }
        }
    }

    /**
     * Test FeedbackService processes feedback for learning pipeline
     *
     * Given: Captured feedback
     * When: Processing feedback for ML training
     * Then: Should extract learning patterns and queue for training
     */
    public function test_feedback_service_processes_feedback_for_learning()
    {
        // Given
        Event::fake();

        $user = User::factory()->create();
        $input = Input::create([
            'content' => 'Complex input for learning',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        $output = Output::create([
            'input_id' => $input->id,
            'content' => 'AI-generated content for learning',
            'type' => 'checklist',
            'ai_model' => 'claude-3-5-sonnet'
        ]);

        $feedbackData = [
            'output_id' => $output->id,
            'user_id' => $user->id,
            'action' => 'edit',
            'original_content' => 'Original content',
            'corrected_content' => 'Improved content',
            'context' => [
                'task_complexity' => 'high',
                'user_expertise' => 'expert'
            ]
        ];

        // When
        $result = $this->feedbackService->processFeedbackForLearning($feedbackData);

        // Then
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('learning_data', $result);
        $this->assertArrayHasKey('pattern_id', $result);

        $learningData = $result['learning_data'];
        $this->assertArrayHasKey('input_features', $learningData);
        $this->assertArrayHasKey('output_features', $learningData);
        $this->assertArrayHasKey('feedback_features', $learningData);
    }

    /**
     * Test FeedbackService aggregates feedback patterns for analysis
     *
     * Given: Multiple feedback instances
     * When: Aggregating feedback patterns
     * Then: Should return statistical analysis of feedback trends
     */
    public function test_feedback_service_aggregates_feedback_patterns()
    {
        // Given
        $user = User::factory()->create();
        $input = Input::create([
            'content' => 'Test input',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        $output = Output::create([
            'input_id' => $input->id,
            'content' => 'AI-generated content',
            'type' => 'checklist',
            'ai_model' => 'claude-3-5-sonnet'
        ]);

        // Create multiple feedback instances
        $feedbackInstances = [
            ['action' => 'accept', 'confidence' => 1.0],
            ['action' => 'accept', 'confidence' => 1.0],
            ['action' => 'edit', 'confidence' => 0.7],
            ['action' => 'reject', 'confidence' => 1.0]
        ];

        foreach ($feedbackInstances as $feedbackData) {
            Feedback::create([
                'output_id' => $output->id,
                'user_id' => $user->id,
                'type' => 'inline',
                'action' => $feedbackData['action'],
                'signal_type' => 'explicit',
                'confidence' => $feedbackData['confidence']
            ]);
        }

        // When
        $patterns = $this->feedbackService->aggregateFeedbackPatterns($output->id);

        // Then
        $this->assertArrayHasKey('action_distribution', $patterns);
        $this->assertArrayHasKey('average_confidence', $patterns);
        $this->assertArrayHasKey('total_feedback_count', $patterns);

        $this->assertEquals(4, $patterns['total_feedback_count']);
        $this->assertEquals(2, $patterns['action_distribution']['accept']);
        $this->assertEquals(1, $patterns['action_distribution']['edit']);
        $this->assertEquals(1, $patterns['action_distribution']['reject']);
    }

    /**
     * Test FeedbackService handles concurrent feedback submissions
     *
     * Given: Concurrent feedback submissions for the same output
     * When: Processing multiple feedback requests simultaneously
     * Then: Should handle concurrent access safely without data corruption
     */
    public function test_feedback_service_handles_concurrent_submissions()
    {
        // Given
        $user = User::factory()->create();
        $input = Input::create([
            'content' => 'Test input',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        $output = Output::create([
            'input_id' => $input->id,
            'content' => 'AI-generated content',
            'type' => 'checklist',
            'ai_model' => 'claude-3-5-sonnet'
        ]);

        $feedbackData = [
            'output_id' => $output->id,
            'user_id' => $user->id,
            'action' => 'accept'
        ];

        // When - simulate concurrent submissions
        $results = [];
        for ($i = 0; $i < 3; $i++) {
            $results[] = $this->feedbackService->captureInlineFeedback($feedbackData);
        }

        // Then - all submissions should be processed successfully
        $this->assertCount(3, $results);
        foreach ($results as $feedback) {
            $this->assertInstanceOf(Feedback::class, $feedback);
            $this->assertEquals('accept', $feedback->action);
        }

        // Verify all feedback was stored
        $storedFeedback = Feedback::where('output_id', $output->id)->get();
        $this->assertEquals(3, $storedFeedback->count());
    }

    /**
     * Test FeedbackService calculates feedback confidence scores
     *
     * Given: Feedback with various contextual factors
     * When: Calculating confidence scores
     * Then: Should assign appropriate confidence based on signal strength and context
     */
    public function test_feedback_service_calculates_confidence_scores()
    {
        // Given - different feedback scenarios with varying signal strength
        $scenarios = [
            'explicit_accept' => [
                'action' => 'accept',
                'signal_type' => 'explicit',
                'time_to_action' => 5.0, // Quick decision
                'expected_confidence' => 1.0
            ],
            'quick_reject' => [
                'action' => 'reject',
                'signal_type' => 'explicit',
                'time_to_action' => 2.0, // Very quick decision
                'expected_confidence' => 1.0
            ],
            'slow_edit' => [
                'action' => 'edit',
                'signal_type' => 'explicit',
                'time_to_action' => 60.0, // Slow decision
                'expected_confidence' => 0.7
            ],
            'passive_completion' => [
                'action' => 'task_completed',
                'signal_type' => 'passive',
                'completion_time' => 3600,
                'expected_confidence' => 0.9
            ]
        ];

        foreach ($scenarios as $scenarioName => $scenario) {
            // When
            $confidence = $this->feedbackService->calculateConfidenceScore($scenario);

            // Then
            $this->assertEquals(
                $scenario['expected_confidence'],
                $confidence,
                "Confidence calculation failed for scenario: {$scenarioName}"
            );
        }
    }

    /**
     * Test FeedbackService integrates with user preference learning
     *
     * Given: User feedback history
     * When: Updating user preferences based on feedback
     * Then: Should learn and store user preferences for personalization
     */
    public function test_feedback_service_updates_user_preferences()
    {
        // Given
        $user = User::factory()->create();
        $input = Input::create([
            'content' => 'Test input',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        $output = Output::create([
            'input_id' => $input->id,
            'content' => 'Detailed checklist with many items',
            'type' => 'checklist',
            'ai_model' => 'claude-3-5-sonnet'
        ]);

        $feedbackHistory = [
            ['action' => 'accept', 'output_type' => 'checklist', 'detail_level' => 'high'],
            ['action' => 'reject', 'output_type' => 'summary', 'detail_level' => 'low'],
            ['action' => 'edit', 'output_type' => 'action_items', 'detail_level' => 'medium']
        ];

        // When
        $preferences = $this->feedbackService->updateUserPreferences($user->id, $feedbackHistory);

        // Then
        $this->assertArrayHasKey('preferred_output_types', $preferences);
        $this->assertArrayHasKey('preferred_detail_level', $preferences);
        $this->assertArrayHasKey('confidence_score', $preferences);

        // Should prefer checklists with high detail based on feedback
        $this->assertContains('checklist', $preferences['preferred_output_types']);
        $this->assertEquals('high', $preferences['preferred_detail_level']);
    }

    // ========================================
    // BATCH OPERATIONS TESTS
    // ========================================

    /**
     * Test FeedbackService can process batch feedback submissions
     *
     * Given: Multiple feedback items to process in batch
     * When: Processing batch feedback through service
     * Then: Should create all feedback records atomically with proper validation
     */
    public function test_feedback_service_processes_batch_feedback_submissions()
    {
        // Given
        $user = User::factory()->create();
        $input = Input::create([
            'content' => 'Test input for batch processing',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        $outputs = [];
        for ($i = 1; $i <= 3; $i++) {
            $outputs[] = Output::create([
                'input_id' => $input->id,
                'content' => "AI-generated content {$i}",
                'type' => 'checklist',
                'ai_model' => 'claude-3-5-sonnet'
            ]);
        }

        $batchFeedbackData = [
            [
                'output_id' => $outputs[0]->id,
                'user_id' => $user->id,
                'action' => 'accept',
                'context' => ['batch_id' => 'batch_001', 'position' => 1]
            ],
            [
                'output_id' => $outputs[1]->id,
                'user_id' => $user->id,
                'action' => 'edit',
                'original_content' => 'Original content',
                'corrected_content' => 'Corrected content',
                'context' => ['batch_id' => 'batch_001', 'position' => 2]
            ],
            [
                'output_id' => $outputs[2]->id,
                'user_id' => $user->id,
                'action' => 'reject',
                'rejection_reason' => 'Not relevant',
                'context' => ['batch_id' => 'batch_001', 'position' => 3]
            ]
        ];

        // When
        $result = $this->feedbackService->processBatchFeedback($batchFeedbackData);

        // Then
        $this->assertTrue($result['success']);
        $this->assertEquals(3, $result['processed_count']);
        $this->assertEquals(0, $result['failed_count']);
        $this->assertArrayHasKey('batch_id', $result);
        $this->assertArrayHasKey('feedback_ids', $result);
        $this->assertCount(3, $result['feedback_ids']);

        // Verify all feedback was created
        $createdFeedback = Feedback::whereIn('output_id', [$outputs[0]->id, $outputs[1]->id, $outputs[2]->id])->get();
        $this->assertEquals(3, $createdFeedback->count());

        // Verify each feedback has correct batch metadata
        foreach ($createdFeedback as $feedback) {
            $metadata = $feedback->metadata;
            $this->assertEquals('batch_001', $metadata['context']['batch_id']);
        }
    }

    /**
     * Test FeedbackService handles partial batch failures correctly
     *
     * Given: Batch feedback with some invalid items
     * When: Processing batch with validation failures
     * Then: Should process valid items and report failures without breaking transaction
     */
    public function test_feedback_service_handles_partial_batch_failures()
    {
        // Given
        $user = User::factory()->create();
        $input = Input::create([
            'content' => 'Test input',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        $output = Output::create([
            'input_id' => $input->id,
            'content' => 'AI-generated content',
            'type' => 'checklist',
            'ai_model' => 'claude-3-5-sonnet'
        ]);

        $batchFeedbackData = [
            // Valid feedback
            [
                'output_id' => $output->id,
                'user_id' => $user->id,
                'action' => 'accept'
            ],
            // Invalid feedback - missing output_id
            [
                'user_id' => $user->id,
                'action' => 'accept'
            ],
            // Invalid feedback - invalid action
            [
                'output_id' => $output->id,
                'user_id' => $user->id,
                'action' => 'invalid_action'
            ],
            // Valid feedback
            [
                'output_id' => $output->id,
                'user_id' => $user->id,
                'action' => 'reject',
                'rejection_reason' => 'Not relevant'
            ]
        ];

        // When
        $result = $this->feedbackService->processBatchFeedback($batchFeedbackData);

        // Then
        $this->assertFalse($result['success']); // Overall failure due to some invalid items
        $this->assertEquals(2, $result['processed_count']); // Only valid items processed
        $this->assertEquals(2, $result['failed_count']); // Two invalid items
        $this->assertArrayHasKey('errors', $result);
        $this->assertCount(2, $result['errors']);

        // Verify only valid feedback was created
        $createdFeedback = Feedback::where('output_id', $output->id)->get();
        $this->assertEquals(2, $createdFeedback->count());
    }

    // ========================================
    // ENHANCED VALIDATION TESTS
    // ========================================

    /**
     * Test FeedbackService validates output exists and user has access
     *
     * Given: Feedback for non-existent or inaccessible output
     * When: Attempting to capture feedback
     * Then: Should validate output existence and user permissions
     */
    public function test_feedback_service_validates_output_existence_and_access()
    {
        // Given
        $user = User::factory()->create();
        $nonExistentOutputId = 99999;

        $feedbackData = [
            'output_id' => $nonExistentOutputId,
            'user_id' => $user->id,
            'action' => 'accept'
        ];

        // When & Then
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Output not found or not accessible');
        $this->feedbackService->captureInlineFeedback($feedbackData);
    }

    /**
     * Test FeedbackService validates confidence score ranges
     *
     * Given: Feedback with invalid confidence scores
     * When: Attempting to create feedback with out-of-range confidence
     * Then: Should validate confidence is between 0.0 and 1.0
     */
    public function test_feedback_service_validates_confidence_score_range()
    {
        // Given
        $user = User::factory()->create();
        $input = Input::create([
            'content' => 'Test input',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        $output = Output::create([
            'input_id' => $input->id,
            'content' => 'AI-generated content',
            'type' => 'checklist',
            'ai_model' => 'claude-3-5-sonnet'
        ]);

        $invalidConfidenceScores = [-0.1, 1.1, 2.0, -1.0];

        foreach ($invalidConfidenceScores as $invalidConfidence) {
            // When & Then
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('Confidence score must be between 0.0 and 1.0');

            $this->feedbackService->capturePassiveSignal([
                'output_id' => $output->id,
                'user_id' => $user->id,
                'signal_type' => 'time_spent',
                'time_spent' => 120,
                'confidence' => $invalidConfidence
            ]);
        }
    }

    /**
     * Test FeedbackService validates required metadata for different feedback types
     *
     * Given: Feedback missing required metadata for specific types
     * When: Attempting to capture feedback
     * Then: Should validate required metadata exists for each feedback type
     */
    public function test_feedback_service_validates_required_metadata_by_type()
    {
        // Given
        $user = User::factory()->create();
        $input = Input::create([
            'content' => 'Test input',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        $output = Output::create([
            'input_id' => $input->id,
            'content' => 'AI-generated content',
            'type' => 'checklist',
            'ai_model' => 'claude-3-5-sonnet'
        ]);

        // Test edit feedback without corrected content
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Edit feedback requires corrected_content');

        $this->feedbackService->captureInlineFeedback([
            'output_id' => $output->id,
            'user_id' => $user->id,
            'action' => 'edit',
            'original_content' => 'Original content'
            // Missing corrected_content
        ]);
    }

    // ========================================
    // ANALYTICS AND REPORTING TESTS
    // ========================================

    /**
     * Test FeedbackService generates comprehensive feedback analytics
     *
     * Given: Historical feedback data across multiple outputs and users
     * When: Generating feedback analytics report
     * Then: Should return comprehensive analytics with trends and insights
     */
    public function test_feedback_service_generates_comprehensive_analytics()
    {
        // Given
        $users = User::factory()->count(3)->create();
        $input = Input::create([
            'content' => 'Test input for analytics',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        $outputs = [];
        for ($i = 1; $i <= 5; $i++) {
            $outputs[] = Output::create([
                'input_id' => $input->id,
                'content' => "AI-generated content {$i}",
                'type' => 'checklist',
                'ai_model' => 'claude-3-5-sonnet'
            ]);
        }

        // Create diverse feedback data
        $feedbackData = [
            // High acceptance rate for output 1
            ['output_id' => $outputs[0]->id, 'user_id' => $users[0]->id, 'action' => 'accept', 'confidence' => 1.0],
            ['output_id' => $outputs[0]->id, 'user_id' => $users[1]->id, 'action' => 'accept', 'confidence' => 1.0],
            ['output_id' => $outputs[0]->id, 'user_id' => $users[2]->id, 'action' => 'accept', 'confidence' => 1.0],

            // Mixed feedback for output 2
            ['output_id' => $outputs[1]->id, 'user_id' => $users[0]->id, 'action' => 'edit', 'confidence' => 0.7],
            ['output_id' => $outputs[1]->id, 'user_id' => $users[1]->id, 'action' => 'reject', 'confidence' => 1.0],

            // Low acceptance for output 3
            ['output_id' => $outputs[2]->id, 'user_id' => $users[0]->id, 'action' => 'reject', 'confidence' => 1.0],
            ['output_id' => $outputs[2]->id, 'user_id' => $users[1]->id, 'action' => 'reject', 'confidence' => 1.0]
        ];

        foreach ($feedbackData as $data) {
            Feedback::create([
                'output_id' => $data['output_id'],
                'user_id' => $data['user_id'],
                'type' => 'inline',
                'action' => $data['action'],
                'signal_type' => 'explicit',
                'confidence' => $data['confidence'],
                'metadata' => json_encode(['created_for_analytics' => true])
            ]);
        }

        // When
        $analytics = $this->feedbackService->generateFeedbackAnalytics([
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
            'include_trends' => true,
            'include_quality_scores' => true
        ]);

        // Then
        $this->assertArrayHasKey('summary', $analytics);
        $this->assertArrayHasKey('output_performance', $analytics);
        $this->assertArrayHasKey('user_engagement', $analytics);
        $this->assertArrayHasKey('quality_trends', $analytics);
        $this->assertArrayHasKey('recommendations', $analytics);

        // Verify summary statistics
        $summary = $analytics['summary'];
        $this->assertEquals(7, $summary['total_feedback_count']);
        $this->assertEquals(3, $summary['accept_count']);
        $this->assertEquals(1, $summary['edit_count']);
        $this->assertEquals(3, $summary['reject_count']);
        $this->assertArrayHasKey('average_confidence', $summary);

        // Verify output performance ranking
        $outputPerformance = $analytics['output_performance'];
        $this->assertCount(3, $outputPerformance); // Only outputs with feedback
        $this->assertArrayHasKey('quality_score', $outputPerformance[0]);
        $this->assertArrayHasKey('feedback_distribution', $outputPerformance[0]);
    }

    /**
     * Test FeedbackService calculates feedback quality scores
     *
     * Given: Feedback with different quality indicators
     * When: Calculating feedback quality scores
     * Then: Should return scores based on consistency, confidence, and reliability
     */
    public function test_feedback_service_calculates_feedback_quality_scores()
    {
        // Given
        $user = User::factory()->create();
        $input = Input::create([
            'content' => 'Test input',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        $output = Output::create([
            'input_id' => $input->id,
            'content' => 'AI-generated content',
            'type' => 'checklist',
            'ai_model' => 'claude-3-5-sonnet'
        ]);

        // Create feedback with quality indicators
        $feedbackInstances = [
            // High quality - quick, confident decision
            [
                'action' => 'accept',
                'confidence' => 1.0,
                'metadata' => json_encode([
                    'time_to_decision' => 5.0,
                    'user_certainty' => 'high',
                    'context_completeness' => 1.0
                ])
            ],
            // Medium quality - some hesitation
            [
                'action' => 'edit',
                'confidence' => 0.7,
                'metadata' => json_encode([
                    'time_to_decision' => 30.0,
                    'user_certainty' => 'medium',
                    'context_completeness' => 0.8
                ])
            ],
            // Lower quality - inconsistent with previous feedback
            [
                'action' => 'reject',
                'confidence' => 0.6,
                'metadata' => json_encode([
                    'time_to_decision' => 120.0,
                    'user_certainty' => 'low',
                    'context_completeness' => 0.5
                ])
            ]
        ];

        $feedbackIds = [];
        foreach ($feedbackInstances as $feedbackData) {
            $feedback = Feedback::create([
                'output_id' => $output->id,
                'user_id' => $user->id,
                'type' => 'inline',
                'action' => $feedbackData['action'],
                'signal_type' => 'explicit',
                'confidence' => $feedbackData['confidence'],
                'metadata' => $feedbackData['metadata']
            ]);
            $feedbackIds[] = $feedback->id;
        }

        // When
        $qualityScores = $this->feedbackService->calculateFeedbackQualityScores($feedbackIds);

        // Then
        $this->assertArrayHasKey('individual_scores', $qualityScores);
        $this->assertArrayHasKey('aggregate_score', $qualityScores);
        $this->assertArrayHasKey('quality_factors', $qualityScores);

        $individualScores = $qualityScores['individual_scores'];
        $this->assertCount(3, $individualScores);

        // First feedback should have highest quality score
        $scores = array_values($individualScores);
        $this->assertGreaterThan($scores[1], $scores[0]);
        $this->assertGreaterThan($scores[2], $scores[0]);

        // Aggregate score should be between 0 and 1
        $this->assertGreaterThanOrEqual(0.0, $qualityScores['aggregate_score']);
        $this->assertLessThanOrEqual(1.0, $qualityScores['aggregate_score']);
    }

    /**
     * Test FeedbackService supports temporal analysis of feedback patterns
     *
     * Given: Feedback data across different time periods
     * When: Analyzing temporal feedback patterns
     * Then: Should identify trends and patterns over time
     */
    public function test_feedback_service_supports_temporal_analysis()
    {
        // Given
        $user = User::factory()->create();
        $input = Input::create([
            'content' => 'Test input',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        $output = Output::create([
            'input_id' => $input->id,
            'content' => 'AI-generated content',
            'type' => 'checklist',
            'ai_model' => 'claude-3-5-sonnet'
        ]);

        // Create feedback across different time periods
        $timePeriodsData = [
            'week_1' => ['start' => now()->subWeeks(3), 'feedback' => ['accept', 'accept', 'reject']],
            'week_2' => ['start' => now()->subWeeks(2), 'feedback' => ['accept', 'edit', 'accept']],
            'week_3' => ['start' => now()->subWeeks(1), 'feedback' => ['accept', 'accept', 'accept']]
        ];

        foreach ($timePeriodsData as $period => $data) {
            foreach ($data['feedback'] as $index => $action) {
                Feedback::create([
                    'output_id' => $output->id,
                    'user_id' => $user->id,
                    'type' => 'inline',
                    'action' => $action,
                    'signal_type' => 'explicit',
                    'confidence' => $action === 'accept' ? 1.0 : 0.7,
                    'created_at' => $data['start']->addHours($index),
                    'updated_at' => $data['start']->addHours($index)
                ]);
            }
        }

        // When
        $temporalAnalysis = $this->feedbackService->analyzeFeedbackTrends([
            'start_date' => now()->subWeeks(4),
            'end_date' => now(),
            'granularity' => 'week',
            'include_forecasting' => true
        ]);

        // Then
        $this->assertArrayHasKey('time_series', $temporalAnalysis);
        $this->assertArrayHasKey('trends', $temporalAnalysis);
        $this->assertArrayHasKey('forecast', $temporalAnalysis);
        $this->assertArrayHasKey('anomalies', $temporalAnalysis);

        // Verify time series data
        $timeSeries = $temporalAnalysis['time_series'];
        $this->assertCount(3, $timeSeries); // Three weeks of data

        foreach ($timeSeries as $period) {
            $this->assertArrayHasKey('period', $period);
            $this->assertArrayHasKey('acceptance_rate', $period);
            $this->assertArrayHasKey('feedback_count', $period);
            $this->assertArrayHasKey('average_confidence', $period);
        }

        // Verify trend analysis
        $trends = $temporalAnalysis['trends'];
        $this->assertArrayHasKey('acceptance_rate_trend', $trends);
        $this->assertArrayHasKey('confidence_trend', $trends);
        $this->assertArrayHasKey('volume_trend', $trends);
    }

    // ========================================
    // OUTPUT MODEL INTEGRATION TESTS
    // ========================================

    /**
     * Test FeedbackService updates output model feedback integration status
     *
     * Given: Feedback captured for an output
     * When: Processing feedback integration
     * Then: Should update output feedback_integrated flag and increment feedback_count
     */
    public function test_feedback_service_updates_output_integration_status()
    {
        // Given
        $user = User::factory()->create();
        $input = Input::create([
            'content' => 'Test input',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        $output = Output::create([
            'input_id' => $input->id,
            'content' => 'AI-generated content',
            'type' => 'checklist',
            'ai_model' => 'claude-3-5-sonnet',
            'feedback_integrated' => false,
            'feedback_count' => 0
        ]);

        $feedbackData = [
            'output_id' => $output->id,
            'user_id' => $user->id,
            'action' => 'edit',
            'original_content' => 'Original content',
            'corrected_content' => 'Corrected content'
        ];

        // When
        $feedback = $this->feedbackService->captureInlineFeedback($feedbackData);
        $this->feedbackService->updateOutputIntegrationStatus($output->id, $feedback->id);

        // Then
        $output->refresh();
        $this->assertTrue($output->feedback_integrated);
        $this->assertEquals(1, $output->feedback_count);

        // Test additional feedback increments count
        $additionalFeedback = $this->feedbackService->captureInlineFeedback([
            'output_id' => $output->id,
            'user_id' => $user->id,
            'action' => 'accept'
        ]);
        $this->feedbackService->updateOutputIntegrationStatus($output->id, $additionalFeedback->id);

        $output->refresh();
        $this->assertEquals(2, $output->feedback_count);
    }

    // ========================================
    // EDGE CASES AND ERROR HANDLING TESTS
    // ========================================

    /**
     * Test FeedbackService handles empty or null metadata gracefully
     *
     * Given: Feedback data with empty or null metadata
     * When: Processing feedback
     * Then: Should handle gracefully without errors and store valid empty metadata
     */
    public function test_feedback_service_handles_empty_metadata_gracefully()
    {
        // Given
        $user = User::factory()->create();
        $input = Input::create([
            'content' => 'Test input',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        $output = Output::create([
            'input_id' => $input->id,
            'content' => 'AI-generated content',
            'type' => 'checklist',
            'ai_model' => 'claude-3-5-sonnet'
        ]);

        $feedbackScenarios = [
            'null_metadata' => [
                'output_id' => $output->id,
                'user_id' => $user->id,
                'action' => 'accept',
                'context' => null
            ],
            'empty_metadata' => [
                'output_id' => $output->id,
                'user_id' => $user->id,
                'action' => 'accept',
                'context' => []
            ],
            'missing_metadata' => [
                'output_id' => $output->id,
                'user_id' => $user->id,
                'action' => 'accept'
            ]
        ];

        foreach ($feedbackScenarios as $scenario => $feedbackData) {
            // When
            $feedback = $this->feedbackService->captureInlineFeedback($feedbackData);

            // Then
            $this->assertInstanceOf(Feedback::class, $feedback);
            $this->assertEquals('accept', $feedback->action);

            // Metadata should be valid JSON (empty object or null)
            $metadata = $feedback->metadata;
            $this->assertTrue(is_array($metadata) || is_null($metadata));
        }
    }

    /**
     * Test FeedbackService handles database connection failures gracefully
     *
     * Given: Database connection issues during feedback capture
     * When: Attempting to capture feedback
     * Then: Should handle database errors gracefully and provide meaningful error messages
     */
    public function test_feedback_service_handles_database_failures()
    {
        // Given - Mock database failure
        $mockFeedbackModel = Mockery::mock(Feedback::class);
        $mockFeedbackModel->shouldReceive('create')
            ->andThrow(new \Exception('Database connection failed'));

        $this->app->instance(Feedback::class, $mockFeedbackModel);

        $user = User::factory()->create();
        $input = Input::create([
            'content' => 'Test input',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        $output = Output::create([
            'input_id' => $input->id,
            'content' => 'AI-generated content',
            'type' => 'checklist',
            'ai_model' => 'claude-3-5-sonnet'
        ]);

        $feedbackData = [
            'output_id' => $output->id,
            'user_id' => $user->id,
            'action' => 'accept'
        ];

        // When & Then
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to capture feedback: Database connection failed');

        $this->feedbackService->captureInlineFeedback($feedbackData);
    }

    /**
     * Test FeedbackService handles large metadata payloads
     *
     * Given: Feedback with extremely large metadata
     * When: Attempting to store feedback
     * Then: Should either handle large metadata or provide appropriate validation error
     */
    public function test_feedback_service_handles_large_metadata_payloads()
    {
        // Given
        $user = User::factory()->create();
        $input = Input::create([
            'content' => 'Test input',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        $output = Output::create([
            'input_id' => $input->id,
            'content' => 'AI-generated content',
            'type' => 'checklist',
            'ai_model' => 'claude-3-5-sonnet'
        ]);

        // Create large metadata payload (1MB of data)
        $largeMetadata = [
            'large_data' => str_repeat('x', 1000000),
            'session_data' => array_fill(0, 10000, 'test_data'),
            'context' => 'large_payload_test'
        ];

        $feedbackData = [
            'output_id' => $output->id,
            'user_id' => $user->id,
            'action' => 'accept',
            'context' => $largeMetadata
        ];

        // When & Then
        try {
            $feedback = $this->feedbackService->captureInlineFeedback($feedbackData);

            // If successful, verify metadata was stored
            $this->assertInstanceOf(Feedback::class, $feedback);
            $storedMetadata = $feedback->metadata;
            $this->assertArrayHasKey('context', $storedMetadata);
        } catch (\Exception $e) {
            // Should provide meaningful error for oversized metadata
            $this->assertStringContainsString('metadata', strtolower($e->getMessage()));
        }
    }

    /**
     * Test FeedbackService rate limiting for feedback submissions
     *
     * Given: Multiple rapid feedback submissions from same user
     * When: Attempting to submit feedback in quick succession
     * Then: Should implement rate limiting to prevent spam and abuse
     */
    public function test_feedback_service_implements_rate_limiting()
    {
        // Given
        $user = User::factory()->create();
        $input = Input::create([
            'content' => 'Test input',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        $output = Output::create([
            'input_id' => $input->id,
            'content' => 'AI-generated content',
            'type' => 'checklist',
            'ai_model' => 'claude-3-5-sonnet'
        ]);

        $feedbackData = [
            'output_id' => $output->id,
            'user_id' => $user->id,
            'action' => 'accept'
        ];

        // When - submit feedback multiple times rapidly
        $successCount = 0;
        $rateLimitedCount = 0;

        for ($i = 0; $i < 10; $i++) {
            try {
                $this->feedbackService->captureInlineFeedback($feedbackData);
                $successCount++;
            } catch (\Exception $e) {
                if (strpos($e->getMessage(), 'rate limit') !== false) {
                    $rateLimitedCount++;
                }
            }
        }

        // Then - should have some rate limiting after initial submissions
        $this->assertGreaterThan(0, $successCount);
        $this->assertLessThan(10, $successCount); // Not all should succeed
        $this->assertGreaterThan(0, $rateLimitedCount);
    }
}

/*
 * ========================================
 * FEEDBACKSERVICE METHOD SIGNATURES
 * ========================================
 *
 * Based on the tests above, the FeedbackService should implement these methods:
 *
 * Core Feedback Capture:
 * - captureInlineFeedback(array $feedbackData): Feedback
 * - capturePassiveSignal(array $signalData): Feedback
 *
 * Batch Operations:
 * - processBatchFeedback(array $batchData): array
 *
 * Learning & Processing:
 * - processFeedbackForLearning(array $feedbackData): array
 * - updateUserPreferences(int $userId, array $feedbackHistory): array
 * - updateOutputIntegrationStatus(int $outputId, int $feedbackId): void
 *
 * Analytics & Reporting:
 * - aggregateFeedbackPatterns(int $outputId): array
 * - generateFeedbackAnalytics(array $options): array
 * - calculateFeedbackQualityScores(array $feedbackIds): array
 * - analyzeFeedbackTrends(array $options): array
 *
 * Utility Methods:
 * - calculateConfidenceScore(array $scenario): float
 *
 * Expected Service Structure:
 *
 * namespace App\Services;
 *
 * use App\Models\Feedback;
 * use App\Models\Output;
 * use App\Models\Input;
 * use App\Models\User;
 * use Illuminate\Support\Facades\DB;
 * use Illuminate\Support\Facades\Log;
 * use Illuminate\Support\Facades\Cache;
 *
 * class FeedbackService
 * {
 *     // Implement the methods listed above
 *     // Follow Laravel service patterns
 *     // Include proper validation, error handling, and logging
 *     // Use database transactions for batch operations
 *     // Implement rate limiting for user feedback submissions
 *     // Store rich metadata and calculate confidence scores
 * }
 */
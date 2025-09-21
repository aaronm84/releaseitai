<?php

namespace Tests\Unit\Models;

use App\Models\Feedback;
use App\Models\Output;
use App\Models\Input;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class FeedbackTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that Feedback model can be created with required fields
     *
     * Given: Required feedback data (output_id, type, action)
     * When: Creating a new Feedback model
     * Then: Feedback should be saved with correct attributes and timestamps
     */
    public function test_feedback_can_be_created_with_required_fields()
    {
        // Given
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);

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
            'type' => 'inline',
            'action' => 'accept',
            'signal_type' => 'explicit',
            'confidence' => 1.0
        ];

        // When
        $feedback = Feedback::create($feedbackData);

        // Then
        $this->assertInstanceOf(Feedback::class, $feedback);
        $this->assertEquals($feedbackData['output_id'], $feedback->output_id);
        $this->assertEquals($feedbackData['user_id'], $feedback->user_id);
        $this->assertEquals($feedbackData['type'], $feedback->type);
        $this->assertEquals($feedbackData['action'], $feedback->action);
        $this->assertEquals($feedbackData['signal_type'], $feedback->signal_type);
        $this->assertEquals($feedbackData['confidence'], $feedback->confidence);
        $this->assertNotNull($feedback->created_at);
    }

    /**
     * Test Feedback model inline control actions
     *
     * Given: Different inline feedback actions (✅/✏️/🗑)
     * When: Creating Feedback with inline actions
     * Then: Should accept valid inline actions (accept, edit, reject)
     */
    public function test_feedback_accepts_inline_control_actions()
    {
        // Given
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);

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

        $inlineActions = [
            'accept' => ['action' => 'accept', 'confidence' => 1.0],
            'edit' => ['action' => 'edit', 'confidence' => 0.7],
            'reject' => ['action' => 'reject', 'confidence' => 1.0]
        ];

        foreach ($inlineActions as $actionName => $actionData) {
            // When
            $feedback = Feedback::create([
                'output_id' => $output->id,
                'user_id' => $user->id,
                'type' => 'inline',
                'action' => $actionData['action'],
                'signal_type' => 'explicit',
                'confidence' => $actionData['confidence']
            ]);

            // Then
            $this->assertEquals($actionData['action'], $feedback->action);
            $this->assertEquals($actionData['confidence'], $feedback->confidence);
            $this->assertEquals($user->id, $feedback->user_id);
        }
    }

    /**
     * Test Feedback model edit content tracking
     *
     * Given: Edit feedback with corrected content
     * When: Creating edit Feedback with corrections
     * Then: Should store original and corrected content
     */
    public function test_feedback_tracks_edit_corrections()
    {
        // Given
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);

        $input = Input::create([
            'content' => 'Test input',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        $output = Output::create([
            'input_id' => $input->id,
            'content' => 'Original AI-generated content with error',
            'type' => 'checklist',
            'ai_model' => 'claude-3-5-sonnet'
        ]);

        $editData = [
            'original_content' => 'Original AI-generated content with error',
            'corrected_content' => 'Corrected AI-generated content without error',
            'edit_type' => 'content_correction',
            'edit_reason' => 'Fixed typo and improved clarity'
        ];

        $feedback = Feedback::create([
            'output_id' => $output->id,
            'user_id' => $user->id,
            'type' => 'inline',
            'action' => 'edit',
            'signal_type' => 'explicit',
            'confidence' => 0.8,
            'metadata' => json_encode($editData)
        ]);

        // When
        $retrievedMetadata = json_decode($feedback->metadata, true);

        // Then
        $this->assertEquals($editData, $retrievedMetadata);
        $this->assertEquals('content_correction', $retrievedMetadata['edit_type']);
        $this->assertStringContainsString('Fixed typo', $retrievedMetadata['edit_reason']);
        $this->assertEquals($user->id, $feedback->user_id);
    }

    /**
     * Test Feedback model passive signal tracking
     *
     * Given: Passive user behavior signals
     * When: Creating passive Feedback
     * Then: Should track implicit signals (task_completed, task_deleted, time_spent)
     */
    public function test_feedback_tracks_passive_signals()
    {
        // Given
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);

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

        $passiveSignals = [
            'task_completed' => ['signal_type' => 'passive', 'action' => 'task_completed', 'confidence' => 0.9],
            'task_deleted' => ['signal_type' => 'passive', 'action' => 'task_deleted', 'confidence' => 0.8],
            'time_spent' => ['signal_type' => 'passive', 'action' => 'time_spent', 'confidence' => 0.6]
        ];

        foreach ($passiveSignals as $signalName => $signalData) {
            // When
            $feedback = Feedback::create([
                'output_id' => $output->id,
                'user_id' => $user->id,
                'type' => 'behavioral',
                'action' => $signalData['action'],
                'signal_type' => $signalData['signal_type'],
                'confidence' => $signalData['confidence']
            ]);

            // Then
            $this->assertEquals($signalData['signal_type'], $feedback->signal_type);
            $this->assertEquals($signalData['action'], $feedback->action);
            $this->assertEquals($signalData['confidence'], $feedback->confidence);
            $this->assertEquals($user->id, $feedback->user_id);
        }
    }

    /**
     * Test Feedback model user association
     *
     * Given: Feedback from a specific user
     * When: Creating Feedback with user_id
     * Then: Should associate feedback with user for personalization
     */
    public function test_feedback_associates_with_user()
    {
        // Given
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);

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

        $feedback = Feedback::create([
            'output_id' => $output->id,
            'user_id' => $user->id,
            'type' => 'inline',
            'action' => 'accept',
            'signal_type' => 'explicit',
            'confidence' => 1.0
        ]);

        // When
        $relatedUser = $feedback->user;

        // Then
        $this->assertInstanceOf(User::class, $relatedUser);
        $this->assertEquals($user->id, $relatedUser->id);
        $this->assertEquals($user->email, $relatedUser->email);
    }

    /**
     * Test Feedback model relationship with Output
     *
     * Given: Feedback linked to an Output
     * When: Accessing output relationship
     * Then: Should return the associated Output model
     */
    public function test_feedback_belongs_to_output()
    {
        // Given
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);

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

        $feedback = Feedback::create([
            'output_id' => $output->id,
            'user_id' => $user->id,
            'type' => 'inline',
            'action' => 'accept',
            'signal_type' => 'explicit',
            'confidence' => 1.0
        ]);

        // When
        $relatedOutput = $feedback->output;

        // Then
        $this->assertInstanceOf(Output::class, $relatedOutput);
        $this->assertEquals($output->id, $relatedOutput->id);
        $this->assertEquals($output->content, $relatedOutput->content);
        $this->assertEquals($user->id, $feedback->user_id);
    }

    /**
     * Test Feedback model confidence scoring
     *
     * Given: Different confidence levels for feedback
     * When: Creating Feedback with various confidence scores
     * Then: Should store confidence values for reliability assessment
     */
    public function test_feedback_tracks_confidence_levels()
    {
        // Given
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);

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

        $confidenceLevels = [
            'high_confidence' => 1.0,
            'medium_confidence' => 0.7,
            'low_confidence' => 0.3,
            'uncertain' => 0.1
        ];

        foreach ($confidenceLevels as $level => $confidence) {
            // When
            $feedback = Feedback::create([
                'output_id' => $output->id,
                'user_id' => $user->id,
                'type' => 'behavioral',
                'action' => 'time_spent',
                'signal_type' => 'passive',
                'confidence' => $confidence
            ]);

            // Then
            $this->assertEquals($confidence, $feedback->confidence);
            $this->assertTrue($feedback->confidence >= 0.0 && $feedback->confidence <= 1.0);
            $this->assertEquals($user->id, $feedback->user_id);
        }
    }

    /**
     * Test Feedback model contextual metadata
     *
     * Given: Rich contextual information about feedback
     * When: Creating Feedback with context metadata
     * Then: Should store context for better learning
     */
    public function test_feedback_stores_contextual_metadata()
    {
        // Given
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);

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

        $contextMetadata = [
            'session_id' => 'sess_123456',
            'user_agent' => 'Mozilla/5.0...',
            'time_to_feedback' => 45.2, // seconds
            'previous_actions' => ['view', 'scroll', 'hover'],
            'page_context' => 'release_planning_dashboard',
            'task_complexity' => 'medium',
            'user_experience_level' => 'expert'
        ];

        $feedback = Feedback::create([
            'output_id' => $output->id,
            'user_id' => $user->id,
            'type' => 'inline',
            'action' => 'edit',
            'signal_type' => 'explicit',
            'confidence' => 0.8,
            'metadata' => json_encode($contextMetadata)
        ]);

        // When
        $retrievedMetadata = json_decode($feedback->metadata, true);

        // Then
        $this->assertEquals($contextMetadata, $retrievedMetadata);
        $this->assertEquals(45.2, $retrievedMetadata['time_to_feedback']);
        $this->assertEquals('expert', $retrievedMetadata['user_experience_level']);
        $this->assertEquals($user->id, $feedback->user_id);
    }

    /**
     * Test Feedback model temporal analysis capabilities
     *
     * Given: Feedback created at different times
     * When: Querying feedback by time periods
     * Then: Should enable temporal analysis of feedback patterns
     */
    public function test_feedback_supports_temporal_analysis()
    {
        // Given
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);

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

        // Create feedback at different times
        $oldFeedback = new Feedback([
            'output_id' => $output->id,
            'user_id' => $user->id,
            'type' => 'inline',
            'action' => 'accept',
            'signal_type' => 'explicit',
            'confidence' => 1.0
        ]);
        $oldFeedback->created_at = Carbon::now()->subDays(30);
        $oldFeedback->updated_at = Carbon::now()->subDays(30);
        $oldFeedback->save();

        $recentFeedback = new Feedback([
            'output_id' => $output->id,
            'user_id' => $user->id,
            'type' => 'inline',
            'action' => 'reject',
            'signal_type' => 'explicit',
            'confidence' => 1.0
        ]);
        $recentFeedback->created_at = Carbon::now()->subDays(1);
        $recentFeedback->updated_at = Carbon::now()->subDays(1);
        $recentFeedback->save();

        // When - querying by time ranges (filtered to this test's output)
        $lastWeekFeedback = Feedback::where('output_id', $output->id)
            ->where('created_at', '>=', Carbon::now()->subWeek())->get();
        $lastMonthFeedback = Feedback::where('output_id', $output->id)
            ->where('created_at', '>=', Carbon::now()->subMonth())->get();

        // Then
        $this->assertEquals(1, $lastWeekFeedback->count());
        $this->assertEquals(2, $lastMonthFeedback->count());
        $this->assertEquals('reject', $lastWeekFeedback->first()->action);
        $this->assertEquals($user->id, $oldFeedback->user_id);
        $this->assertEquals($user->id, $recentFeedback->user_id);
    }

    /**
     * Test Feedback model aggregation for learning
     *
     * Given: Multiple feedback instances for learning analysis
     * When: Aggregating feedback data
     * Then: Should support aggregation queries for pattern recognition
     */
    public function test_feedback_supports_aggregation_queries()
    {
        // Given
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);

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
        for ($i = 0; $i < 5; $i++) {
            Feedback::create([
                'output_id' => $output->id,
                'user_id' => $user->id,
                'type' => 'inline',
                'action' => 'accept',
                'signal_type' => 'explicit',
                'confidence' => 1.0
            ]);
        }

        for ($i = 0; $i < 2; $i++) {
            Feedback::create([
                'output_id' => $output->id,
                'user_id' => $user->id,
                'type' => 'inline',
                'action' => 'reject',
                'signal_type' => 'explicit',
                'confidence' => 1.0
            ]);
        }

        // When - performing aggregation queries
        $acceptCount = Feedback::where('action', 'accept')->count();
        $rejectCount = Feedback::where('action', 'reject')->count();
        $averageConfidence = Feedback::avg('confidence');

        // Then
        $this->assertEquals(5, $acceptCount);
        $this->assertEquals(2, $rejectCount);
        $this->assertEquals(1.0, $averageConfidence);

        // Verify all feedback instances have the correct user_id
        $allFeedback = Feedback::all();
        foreach ($allFeedback as $feedback) {
            $this->assertEquals($user->id, $feedback->user_id);
        }
    }

    /**
     * Test Feedback model validation rules
     *
     * Given: Invalid feedback data
     * When: Attempting to create Feedback
     * Then: Should enforce validation rules for required fields and constraints
     */
    public function test_feedback_validates_required_fields_and_constraints()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        // When - attempting to create without required fields
        Feedback::create([]);
    }

    /**
     * Test Feedback model learning pattern extraction
     *
     * Given: Feedback with patterns for ML training
     * When: Extracting learning patterns
     * Then: Should provide data structure suitable for ML model training
     */
    public function test_feedback_enables_learning_pattern_extraction()
    {
        // Given
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);

        $input = Input::create([
            'content' => 'Complex input requiring analysis',
            'type' => 'brain_dump',
            'source' => 'manual_entry'
        ]);

        $output = Output::create([
            'input_id' => $input->id,
            'content' => 'AI-generated complex output',
            'type' => 'checklist',
            'ai_model' => 'claude-3-5-sonnet'
        ]);

        $learningData = [
            'input_features' => ['complexity' => 'high', 'domain' => 'technical'],
            'output_features' => ['completeness' => 0.9, 'accuracy' => 0.85],
            'feedback_context' => ['user_satisfaction' => 'high', 'task_success' => true]
        ];

        $feedback = Feedback::create([
            'output_id' => $output->id,
            'user_id' => $user->id,
            'type' => 'inline',
            'action' => 'accept',
            'signal_type' => 'explicit',
            'confidence' => 1.0,
            'metadata' => json_encode($learningData)
        ]);

        // When - accessing learning data
        $metadata = json_decode($feedback->metadata, true);

        // Then
        $this->assertArrayHasKey('input_features', $metadata);
        $this->assertArrayHasKey('output_features', $metadata);
        $this->assertArrayHasKey('feedback_context', $metadata);
        $this->assertEquals('high', $metadata['input_features']['complexity']);
        $this->assertTrue($metadata['feedback_context']['task_success']);
        $this->assertEquals($user->id, $feedback->user_id);
    }
}
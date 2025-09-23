<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use App\Models\Feedback;
use App\Models\Output;
use App\Models\Input;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedbackAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $feedbackOwner;
    private User $systemUser;
    private User $unrelatedUser;
    private Output $output;
    private Input $input;
    private Feedback $feedback;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->user = User::factory()->create();
        $this->feedbackOwner = User::factory()->create();
        $this->systemUser = User::factory()->create(['email' => 'system@releaseit.com']);
        $this->unrelatedUser = User::factory()->create();

        // Create input and output
        $this->input = Input::factory()->create([
            'user_id' => $this->feedbackOwner->id,
            'type' => 'question',
            'content' => 'Test question',
        ]);

        $this->output = Output::factory()->create([
            'input_id' => $this->input->id,
            'type' => 'answer',
            'content' => 'Test answer',
        ]);

        // Create feedback
        $this->feedback = Feedback::factory()->create([
            'output_id' => $this->output->id,
            'user_id' => $this->feedbackOwner->id,
            'type' => 'inline',
            'action' => 'thumbs_up',
            'signal_type' => 'explicit',
            'confidence' => 0.95,
        ]);
    }

    /** @test */
    public function test_unauthenticated_user_cannot_access_feedback_endpoints()
    {
        // When: Unauthenticated user tries to access feedback endpoints
        $response = $this->getJson('/api/feedback');

        // Then: Should receive 401 Unauthorized
        $response->assertStatus(401);
    }

    /** @test */
    public function test_user_can_view_their_own_feedback_list()
    {
        // Given: A user with their own feedback
        // When: They try to view their feedback list
        $response = $this->actingAs($this->feedbackOwner)
            ->getJson('/api/feedback');

        // Then: Should receive success and see their own feedback
        $response->assertStatus(200);
        $responseData = $response->json();

        // Should contain their feedback
        $feedbackIds = collect($responseData['data'])->pluck('id')->toArray();
        $this->assertContains($this->feedback->id, $feedbackIds);
    }

    /** @test */
    public function test_user_can_view_their_own_specific_feedback()
    {
        // Given: A user who owns the feedback
        // When: They try to view their specific feedback
        $response = $this->actingAs($this->feedbackOwner)
            ->getJson("/api/feedback/{$this->feedback->id}");

        // Then: Should receive success
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $this->feedback->id,
                    'user_id' => $this->feedbackOwner->id,
                ]
            ]);
    }

    /** @test */
    public function test_user_cannot_view_other_users_feedback()
    {
        // Given: A user who does not own the feedback
        // When: They try to view someone else's feedback
        $response = $this->actingAs($this->unrelatedUser)
            ->getJson("/api/feedback/{$this->feedback->id}");

        // Then: Should receive 403 Forbidden
        $response->assertStatus(403);
    }

    /** @test */
    public function test_user_can_create_feedback_on_any_output()
    {
        // Given: Any authenticated user and an output
        $feedbackData = [
            'output_id' => $this->output->id,
            'type' => 'inline',
            'action' => 'thumbs_down',
            'signal_type' => 'explicit',
            'confidence' => 0.8,
        ];

        // When: They try to create feedback
        $response = $this->actingAs($this->user)
            ->postJson('/api/feedback', $feedbackData);

        // Then: Should receive success
        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'output_id' => $this->output->id,
                    'user_id' => $this->user->id,
                    'action' => 'thumbs_down',
                ]
            ]);
    }

    /** @test */
    public function test_user_can_update_their_own_feedback()
    {
        // Given: A user who owns the feedback
        $updateData = [
            'action' => 'thumbs_down',
            'confidence' => 0.75,
        ];

        // When: They try to update their feedback
        $response = $this->actingAs($this->feedbackOwner)
            ->putJson("/api/feedback/{$this->feedback->id}", $updateData);

        // Then: Should receive success
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'action' => 'thumbs_down',
                    'confidence' => 0.75,
                ]
            ]);
    }

    /** @test */
    public function test_user_cannot_update_other_users_feedback()
    {
        // Given: A user who does not own the feedback
        $updateData = [
            'action' => 'thumbs_down',
        ];

        // When: They try to update someone else's feedback
        $response = $this->actingAs($this->unrelatedUser)
            ->putJson("/api/feedback/{$this->feedback->id}", $updateData);

        // Then: Should receive 403 Forbidden
        $response->assertStatus(403);
    }

    /** @test */
    public function test_user_can_delete_their_own_feedback()
    {
        // Given: A user who owns the feedback
        // When: They try to delete their feedback
        $response = $this->actingAs($this->feedbackOwner)
            ->deleteJson("/api/feedback/{$this->feedback->id}");

        // Then: Should receive success
        $response->assertStatus(204);
    }

    /** @test */
    public function test_user_cannot_delete_other_users_feedback()
    {
        // Given: A user who does not own the feedback
        // When: They try to delete someone else's feedback
        $response = $this->actingAs($this->unrelatedUser)
            ->deleteJson("/api/feedback/{$this->feedback->id}");

        // Then: Should receive 403 Forbidden
        $response->assertStatus(403);
    }

    /** @test */
    public function test_user_cannot_create_duplicate_explicit_feedback_on_same_output()
    {
        // Given: A user who already provided explicit feedback on an output
        $existingFeedback = Feedback::factory()->create([
            'output_id' => $this->output->id,
            'user_id' => $this->user->id,
            'type' => 'inline',
            'signal_type' => 'explicit',
            'action' => 'thumbs_up',
        ]);

        $duplicateFeedbackData = [
            'output_id' => $this->output->id,
            'type' => 'inline',
            'signal_type' => 'explicit',
            'action' => 'thumbs_down',
        ];

        // When: They try to create another explicit feedback on the same output
        $response = $this->actingAs($this->user)
            ->postJson('/api/feedback', $duplicateFeedbackData);

        // Then: Should receive 422 Unprocessable Entity (business rule violation)
        $response->assertStatus(422);
    }

    /** @test */
    public function test_user_can_create_multiple_behavioral_feedback_on_same_output()
    {
        // Given: A user who already provided behavioral feedback on an output
        $existingFeedback = Feedback::factory()->create([
            'output_id' => $this->output->id,
            'user_id' => $this->user->id,
            'type' => 'behavioral',
            'signal_type' => 'passive',
            'action' => 'copy_text',
        ]);

        $newBehavioralFeedbackData = [
            'output_id' => $this->output->id,
            'type' => 'behavioral',
            'signal_type' => 'passive',
            'action' => 'view_duration',
            'metadata' => ['duration' => 30],
        ];

        // When: They try to create another behavioral feedback on the same output
        $response = $this->actingAs($this->user)
            ->postJson('/api/feedback', $newBehavioralFeedbackData);

        // Then: Should receive success
        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'action' => 'view_duration',
                    'type' => 'behavioral',
                ]
            ]);
    }

    /** @test */
    public function test_system_user_can_access_aggregated_feedback_data()
    {
        // Given: A system user
        // When: They try to access aggregated feedback data
        $response = $this->actingAs($this->systemUser)
            ->getJson('/api/feedback/aggregated');

        // Then: Should receive success
        $response->assertStatus(200);
    }

    /** @test */
    public function test_regular_user_cannot_access_aggregated_feedback_data()
    {
        // Given: A regular user
        // When: They try to access aggregated feedback data
        $response = $this->actingAs($this->user)
            ->getJson('/api/feedback/aggregated');

        // Then: Should receive 403 Forbidden (privacy protection)
        $response->assertStatus(403);
    }

    /** @test */
    public function test_user_can_view_their_own_feedback_statistics()
    {
        // Given: A user with feedback history
        // When: They try to view their own feedback stats
        $response = $this->actingAs($this->feedbackOwner)
            ->getJson('/api/feedback/my-stats');

        // Then: Should receive success
        $response->assertStatus(200);
    }

    /** @test */
    public function test_user_cannot_view_other_users_feedback_statistics()
    {
        // Given: A user trying to access another user's feedback stats
        // When: They try to view someone else's feedback stats
        $response = $this->actingAs($this->user)
            ->getJson("/api/feedback/user-stats/{$this->feedbackOwner->id}");

        // Then: Should receive 403 Forbidden
        $response->assertStatus(403);
    }

    /** @test */
    public function test_feedback_update_respects_time_limits()
    {
        // Given: Old feedback (created more than 24 hours ago)
        $oldFeedback = Feedback::factory()->create([
            'output_id' => $this->output->id,
            'user_id' => $this->feedbackOwner->id,
            'created_at' => now()->subHours(25),
        ]);

        $updateData = [
            'action' => 'thumbs_down',
        ];

        // When: The owner tries to update their old feedback
        $response = $this->actingAs($this->feedbackOwner)
            ->putJson("/api/feedback/{$oldFeedback->id}", $updateData);

        // Then: Should receive 422 Unprocessable Entity (time limit exceeded)
        $response->assertStatus(422);
    }

    /** @test */
    public function test_feedback_search_only_returns_user_own_feedback()
    {
        // Given: Multiple feedback items from different users
        $userFeedback = Feedback::factory()->create([
            'output_id' => $this->output->id,
            'user_id' => $this->user->id,
            'action' => 'useful_feedback',
        ]);

        $otherUserFeedback = Feedback::factory()->create([
            'output_id' => $this->output->id,
            'user_id' => $this->unrelatedUser->id,
            'action' => 'useful_feedback',
        ]);

        // When: User searches for feedback
        $response = $this->actingAs($this->user)
            ->getJson('/api/feedback/search?q=useful');

        // Then: Should only see their own feedback
        $response->assertStatus(200);
        $responseData = $response->json();

        $foundIds = collect($responseData['data'])->pluck('id')->toArray();
        $this->assertContains($userFeedback->id, $foundIds);
        $this->assertNotContains($otherUserFeedback->id, $foundIds);
    }

    /** @test */
    public function test_bulk_feedback_operations_respect_ownership()
    {
        // Given: Multiple feedback items, some owned by user, some not
        $ownFeedback1 = Feedback::factory()->create([
            'output_id' => $this->output->id,
            'user_id' => $this->user->id,
        ]);

        $ownFeedback2 = Feedback::factory()->create([
            'output_id' => $this->output->id,
            'user_id' => $this->user->id,
        ]);

        $otherFeedback = Feedback::factory()->create([
            'output_id' => $this->output->id,
            'user_id' => $this->unrelatedUser->id,
        ]);

        // When: User tries to bulk update feedback including others' feedback
        $response = $this->actingAs($this->user)
            ->putJson('/api/feedback/bulk-update', [
                'feedback_ids' => [$ownFeedback1->id, $ownFeedback2->id, $otherFeedback->id],
                'action' => 'archive',
            ]);

        // Then: Should receive partial success (only updating their own feedback)
        $response->assertStatus(207); // Multi-status
        $responseData = $response->json();

        // Should indicate which updates succeeded and which failed
        $this->assertArrayHasKey('results', $responseData);
    }

    /** @test */
    public function test_feedback_export_only_includes_user_own_data()
    {
        // Given: A user with their own feedback and other users' feedback exists
        $userFeedback = Feedback::factory()->create([
            'output_id' => $this->output->id,
            'user_id' => $this->user->id,
        ]);

        $otherUserFeedback = Feedback::factory()->create([
            'output_id' => $this->output->id,
            'user_id' => $this->unrelatedUser->id,
        ]);

        // When: User requests feedback export
        $response = $this->actingAs($this->user)
            ->getJson('/api/feedback/export');

        // Then: Should receive success with only their own data
        $response->assertStatus(200);
        $responseData = $response->json();

        $exportedIds = collect($responseData['data'])->pluck('id')->toArray();
        $this->assertContains($userFeedback->id, $exportedIds);
        $this->assertNotContains($otherUserFeedback->id, $exportedIds);
    }

    /** @test */
    public function test_feedback_analytics_respect_privacy_boundaries()
    {
        // Given: A user requesting analytics for an output they created feedback on
        // When: They try to view analytics for that output
        $response = $this->actingAs($this->feedbackOwner)
            ->getJson("/api/outputs/{$this->output->id}/feedback-analytics");

        // Then: Should receive success but with aggregated/anonymized data only
        $response->assertStatus(200);
        $responseData = $response->json();

        // Should not contain individual user feedback details
        $this->assertArrayNotHasKey('individual_feedback', $responseData);
        $this->assertArrayHasKey('aggregated_metrics', $responseData);
    }

    /** @test */
    public function test_feedback_creation_validates_output_existence_and_access()
    {
        // Given: A non-existent output ID
        $feedbackData = [
            'output_id' => 99999,
            'type' => 'inline',
            'action' => 'thumbs_up',
            'signal_type' => 'explicit',
        ];

        // When: User tries to create feedback for non-existent output
        $response = $this->actingAs($this->user)
            ->postJson('/api/feedback', $feedbackData);

        // Then: Should receive 422 Unprocessable Entity (validation error)
        $response->assertStatus(422);
    }

    /** @test */
    public function test_feedback_metadata_privacy_is_protected()
    {
        // Given: Feedback with sensitive metadata
        $sensitiveMetadata = [
            'user_agent' => 'Mozilla/5.0...',
            'ip_address' => '192.168.1.1',
            'session_id' => 'abc123',
        ];

        $feedbackWithMetadata = Feedback::factory()->create([
            'output_id' => $this->output->id,
            'user_id' => $this->feedbackOwner->id,
            'metadata' => $sensitiveMetadata,
        ]);

        // When: The owner views their feedback
        $response = $this->actingAs($this->feedbackOwner)
            ->getJson("/api/feedback/{$feedbackWithMetadata->id}");

        // Then: Should receive success but with sanitized metadata
        $response->assertStatus(200);
        $responseData = $response->json();

        // Sensitive fields should be removed or anonymized
        $metadata = $responseData['data']['metadata'];
        $this->assertArrayNotHasKey('ip_address', $metadata);
        $this->assertArrayNotHasKey('session_id', $metadata);
    }

    /** @test */
    public function test_feedback_deletion_is_permanent_and_logged()
    {
        // Given: A user who owns feedback
        // When: They delete their feedback
        $response = $this->actingAs($this->feedbackOwner)
            ->deleteJson("/api/feedback/{$this->feedback->id}");

        // Then: Should receive success
        $response->assertStatus(204);

        // And: Feedback should be permanently deleted (not soft deleted)
        $this->assertDatabaseMissing('feedback', ['id' => $this->feedback->id]);
    }
}
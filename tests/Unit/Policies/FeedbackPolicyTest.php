<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Models\Feedback;
use App\Models\Output;
use App\Models\Input;
use App\Policies\FeedbackPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedbackPolicyTest extends TestCase
{
    use RefreshDatabase;

    private FeedbackPolicy $policy;
    private User $user;
    private User $feedbackOwner;
    private User $unrelatedUser;
    private User $systemUser;
    private Output $output;
    private Input $input;
    private Feedback $feedback;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new FeedbackPolicy();

        // Create test users
        $this->user = User::factory()->create(['name' => 'Test User']);
        $this->feedbackOwner = User::factory()->create(['name' => 'Feedback Owner']);
        $this->unrelatedUser = User::factory()->create(['name' => 'Unrelated User']);
        $this->systemUser = User::factory()->create(['name' => 'System User', 'email' => 'system@releaseit.com']);

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
    public function test_user_can_view_their_own_feedback()
    {
        // Given: A user who owns the feedback
        // When: They try to view their feedback
        $result = $this->policy->view($this->feedbackOwner, $this->feedback);

        // Then: Access should be granted
        $this->assertTrue($result);
    }

    /** @test */
    public function test_user_cannot_view_other_users_feedback()
    {
        // Given: A user who does not own the feedback
        // When: They try to view someone else's feedback
        $result = $this->policy->view($this->unrelatedUser, $this->feedback);

        // Then: Access should be denied
        $this->assertFalse($result);
    }

    /** @test */
    public function test_user_can_create_feedback_on_any_output()
    {
        // Given: Any authenticated user
        // When: They try to create feedback on an output
        $result = $this->policy->create($this->user, $this->output);

        // Then: Access should be granted (users can provide feedback on any output)
        $this->assertTrue($result);
    }

    /** @test */
    public function test_user_can_update_their_own_feedback()
    {
        // Given: A user who owns the feedback
        // When: They try to update their feedback
        $result = $this->policy->update($this->feedbackOwner, $this->feedback);

        // Then: Access should be granted
        $this->assertTrue($result);
    }

    /** @test */
    public function test_user_cannot_update_other_users_feedback()
    {
        // Given: A user who does not own the feedback
        // When: They try to update someone else's feedback
        $result = $this->policy->update($this->unrelatedUser, $this->feedback);

        // Then: Access should be denied
        $this->assertFalse($result);
    }

    /** @test */
    public function test_user_can_delete_their_own_feedback()
    {
        // Given: A user who owns the feedback
        // When: They try to delete their feedback
        $result = $this->policy->delete($this->feedbackOwner, $this->feedback);

        // Then: Access should be granted
        $this->assertTrue($result);
    }

    /** @test */
    public function test_user_cannot_delete_other_users_feedback()
    {
        // Given: A user who does not own the feedback
        // When: They try to delete someone else's feedback
        $result = $this->policy->delete($this->unrelatedUser, $this->feedback);

        // Then: Access should be denied
        $this->assertFalse($result);
    }

    /** @test */
    public function test_unauthenticated_user_cannot_access_feedback()
    {
        // Given: No authenticated user (null)
        // When: They try to view feedback
        $result = $this->policy->view(null, $this->feedback);

        // Then: Access should be denied
        $this->assertFalse($result);
    }

    /** @test */
    public function test_user_can_view_list_of_their_own_feedback()
    {
        // Given: Any authenticated user
        // When: They try to view their own feedback list
        $result = $this->policy->viewAny($this->user);

        // Then: Access should be granted (users can see their own feedback)
        $this->assertTrue($result);
    }

    /** @test */
    public function test_system_user_can_access_all_feedback_for_learning()
    {
        // Given: A system user (for AI learning purposes)
        // When: They try to view any feedback
        $result = $this->policy->view($this->systemUser, $this->feedback);

        // Then: Access should be granted for system learning
        $this->assertTrue($result);
    }

    /** @test */
    public function test_system_user_can_view_aggregated_feedback_data()
    {
        // Given: A system user
        // When: They try to view aggregated feedback data
        $result = $this->policy->viewAggregated($this->systemUser);

        // Then: Access should be granted
        $this->assertTrue($result);
    }

    /** @test */
    public function test_regular_user_cannot_view_aggregated_feedback_data()
    {
        // Given: A regular user
        // When: They try to view aggregated feedback data
        $result = $this->policy->viewAggregated($this->user);

        // Then: Access should be denied (privacy protection)
        $this->assertFalse($result);
    }

    /** @test */
    public function test_user_can_provide_feedback_on_output_they_created()
    {
        // Given: A user who created an input that generated an output
        // When: They try to provide feedback on that output
        $result = $this->policy->create($this->feedbackOwner, $this->output);

        // Then: Access should be granted
        $this->assertTrue($result);
    }

    /** @test */
    public function test_user_can_provide_feedback_on_output_they_did_not_create()
    {
        // Given: A user who did not create the input that generated an output
        // When: They try to provide feedback on that output
        $result = $this->policy->create($this->unrelatedUser, $this->output);

        // Then: Access should be granted (open feedback model)
        $this->assertTrue($result);
    }

    /** @test */
    public function test_user_cannot_create_multiple_explicit_feedback_on_same_output()
    {
        // Given: A user who already provided explicit feedback on an output
        $existingFeedback = Feedback::factory()->create([
            'output_id' => $this->output->id,
            'user_id' => $this->user->id,
            'type' => 'inline',
            'signal_type' => 'explicit',
        ]);

        // When: They try to create another explicit feedback on the same output
        $result = $this->policy->createExplicitFeedback($this->user, $this->output);

        // Then: Access should be denied (prevent duplicate explicit feedback)
        $this->assertFalse($result);
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
        ]);

        // When: They try to create another behavioral feedback on the same output
        $result = $this->policy->createBehavioralFeedback($this->user, $this->output);

        // Then: Access should be granted (multiple behavioral feedback allowed)
        $this->assertTrue($result);
    }

    /** @test */
    public function test_user_can_update_feedback_within_time_limit()
    {
        // Given: A user with recently created feedback (within edit window)
        $recentFeedback = Feedback::factory()->create([
            'output_id' => $this->output->id,
            'user_id' => $this->feedbackOwner->id,
            'created_at' => now()->subMinutes(5), // 5 minutes ago
        ]);

        // When: They try to update their recent feedback
        $result = $this->policy->update($this->feedbackOwner, $recentFeedback);

        // Then: Access should be granted
        $this->assertTrue($result);
    }

    /** @test */
    public function test_user_cannot_update_old_feedback_outside_time_limit()
    {
        // Given: A user with old feedback (outside edit window)
        $oldFeedback = Feedback::factory()->create([
            'output_id' => $this->output->id,
            'user_id' => $this->feedbackOwner->id,
            'created_at' => now()->subHours(25), // 25 hours ago (outside 24h window)
        ]);

        // When: They try to update their old feedback
        $result = $this->policy->updateWithTimeLimit($this->feedbackOwner, $oldFeedback);

        // Then: Access should be denied
        $this->assertFalse($result);
    }

    /** @test */
    public function test_feedback_privacy_is_maintained_across_user_boundaries()
    {
        // Given: Multiple users with feedback on the same output
        $user1Feedback = Feedback::factory()->create([
            'output_id' => $this->output->id,
            'user_id' => $this->user->id,
        ]);

        $user2Feedback = Feedback::factory()->create([
            'output_id' => $this->output->id,
            'user_id' => $this->unrelatedUser->id,
        ]);

        // When: User 1 tries to view User 2's feedback
        $result = $this->policy->view($this->user, $user2Feedback);

        // Then: Access should be denied (privacy isolation)
        $this->assertFalse($result);
    }

    /** @test */
    public function test_user_can_view_their_feedback_stats_and_history()
    {
        // Given: A user with feedback history
        // When: They try to view their own feedback statistics
        $result = $this->policy->viewOwnStats($this->user);

        // Then: Access should be granted
        $this->assertTrue($result);
    }

    /** @test */
    public function test_user_cannot_view_other_users_feedback_stats()
    {
        // Given: A user trying to access another user's feedback stats
        // When: They try to view someone else's feedback statistics
        $result = $this->policy->viewUserStats($this->user, $this->unrelatedUser);

        // Then: Access should be denied
        $this->assertFalse($result);
    }

    /** @test */
    public function test_feedback_on_private_outputs_maintains_additional_privacy()
    {
        // Given: A private output with feedback
        $privateOutput = Output::factory()->create([
            'input_id' => $this->input->id,
            'type' => 'private_answer',
            'content' => 'Private answer content',
        ]);

        $privateFeedback = Feedback::factory()->create([
            'output_id' => $privateOutput->id,
            'user_id' => $this->feedbackOwner->id,
        ]);

        // When: Even system user tries to access private feedback
        $result = $this->policy->viewPrivateFeedback($this->systemUser, $privateFeedback);

        // Then: Access should require additional authorization
        $this->assertFalse($result);
    }

    /** @test */
    public function test_feedback_deletion_is_permanent_and_cannot_be_restored()
    {
        // Given: A user who owns feedback
        // When: They delete their feedback
        $canDelete = $this->policy->delete($this->feedbackOwner, $this->feedback);

        // And: They try to restore it
        $canRestore = $this->policy->restore($this->feedbackOwner, $this->feedback);

        // Then: Deletion should be allowed but restoration should not
        $this->assertTrue($canDelete);
        $this->assertFalse($canRestore);
    }

    /** @test */
    public function test_bulk_feedback_operations_respect_individual_permissions()
    {
        // Given: A user with multiple feedback items, some owned by them, some not
        $ownFeedback = Feedback::factory()->create([
            'output_id' => $this->output->id,
            'user_id' => $this->user->id,
        ]);

        $otherFeedback = Feedback::factory()->create([
            'output_id' => $this->output->id,
            'user_id' => $this->unrelatedUser->id,
        ]);

        // When: They try to perform bulk operations
        $canBulkUpdateOwn = $this->policy->bulkUpdate($this->user, collect([$ownFeedback]));
        $canBulkUpdateMixed = $this->policy->bulkUpdate($this->user, collect([$ownFeedback, $otherFeedback]));

        // Then: They can only bulk update their own feedback
        $this->assertTrue($canBulkUpdateOwn);
        $this->assertFalse($canBulkUpdateMixed);
    }
}
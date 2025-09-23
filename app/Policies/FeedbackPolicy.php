<?php

namespace App\Policies;

use App\Models\Feedback;
use App\Models\Output;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Collection;

class FeedbackPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        // Users can see their own feedback list
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(?User $user, Feedback $feedback): bool
    {
        if (!$user) {
            return false;
        }

        // System users can access all feedback for learning
        if ($this->isSystemUser($user)) {
            return true;
        }

        return $this->userCanAccessFeedback($user, $feedback);
    }

    /**
     * Determine whether the user can create models with output.
     */
    public function create(?User $user, ?Output $output = null): bool
    {
        if (!$user) {
            return false;
        }

        // Any authenticated user can provide feedback on any output
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(?User $user, Feedback $feedback): bool
    {
        if (!$user) {
            return false;
        }

        return $this->userCanAccessFeedback($user, $feedback);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(?User $user, Feedback $feedback): bool
    {
        if (!$user) {
            return false;
        }

        return $this->userCanAccessFeedback($user, $feedback);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(?User $user, Feedback $feedback): bool
    {
        // Feedback deletion is permanent and cannot be restored
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(?User $user, Feedback $feedback): bool
    {
        if (!$user) {
            return false;
        }

        return $this->userCanAccessFeedback($user, $feedback);
    }

    /**
     * Determine whether the user can view aggregated feedback data.
     */
    public function viewAggregated(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        // Only system users can view aggregated feedback data for privacy protection
        return $this->isSystemUser($user);
    }

    /**
     * Determine whether the user can create explicit feedback on an output.
     */
    public function createExplicitFeedback(?User $user, Output $output): bool
    {
        if (!$user) {
            return false;
        }

        // Check if user already has explicit feedback on this output
        $existingExplicitFeedback = Feedback::where('output_id', $output->id)
            ->where('user_id', $user->id)
            ->where('signal_type', 'explicit')
            ->exists();

        // Prevent duplicate explicit feedback
        return !$existingExplicitFeedback;
    }

    /**
     * Determine whether the user can create behavioral feedback on an output.
     */
    public function createBehavioralFeedback(?User $user, Output $output): bool
    {
        if (!$user) {
            return false;
        }

        // Multiple behavioral feedback allowed
        return true;
    }

    /**
     * Determine whether the user can update feedback within time limit.
     */
    public function updateWithTimeLimit(?User $user, Feedback $feedback): bool
    {
        if (!$user) {
            return false;
        }

        if (!$this->userCanAccessFeedback($user, $feedback)) {
            return false;
        }

        // Check if feedback is within 24-hour edit window
        return $feedback->created_at->diffInHours(now()) <= 24;
    }

    /**
     * Determine whether the user can view their own feedback stats.
     */
    public function viewOwnStats(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        // Users can view their own feedback statistics
        return true;
    }

    /**
     * Determine whether the user can view another user's feedback stats.
     */
    public function viewUserStats(?User $user, User $targetUser): bool
    {
        if (!$user) {
            return false;
        }

        // Users cannot view other users' feedback statistics (privacy protection)
        return false;
    }

    /**
     * Determine whether the user can view private feedback.
     */
    public function viewPrivateFeedback(?User $user, Feedback $feedback): bool
    {
        if (!$user) {
            return false;
        }

        // Even system users need additional authorization for private feedback
        return false;
    }

    /**
     * Determine whether the user can perform bulk updates.
     */
    public function bulkUpdate(?User $user, Collection $feedbacks): bool
    {
        if (!$user) {
            return false;
        }

        // Check that all feedback items belong to the user
        foreach ($feedbacks as $feedback) {
            if (!$this->userCanAccessFeedback($user, $feedback)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Helper method to check if user can access feedback.
     */
    private function userCanAccessFeedback(User $user, Feedback $feedback): bool
    {
        // Users can only access their own feedback
        return $feedback->user_id === $user->id;
    }

    /**
     * Helper method to check if a user is a system user.
     */
    private function isSystemUser(User $user): bool
    {
        return str_contains($user->email, 'system@releaseit.com');
    }
}

<?php

namespace App\Policies;

use App\Models\Content;
use App\Models\Stakeholder;
use App\Models\User;
use App\Models\Workstream;
use App\Models\WorkstreamPermission;
use Illuminate\Auth\Access\Response;

class ContentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        // Users can view lists of content they have access to
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(?User $user, Content $content): bool
    {
        if (!$user) {
            return false;
        }

        return $this->userCanAccessContent($user, $content);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        // Any authenticated user can create their own content
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(?User $user, Content $content): bool
    {
        if (!$user) {
            return false;
        }

        // Only content owners can update their content
        return $content->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(?User $user, Content $content): bool
    {
        if (!$user) {
            return false;
        }

        // Only content owners can delete their content
        return $content->user_id === $user->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(?User $user, Content $content): bool
    {
        if (!$user) {
            return false;
        }

        // Only content owners can restore their content
        return $content->user_id === $user->id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(?User $user, Content $content): bool
    {
        if (!$user) {
            return false;
        }

        // Only content owners can force delete their content
        return $content->user_id === $user->id;
    }

    /**
     * Determine whether the user can view sensitive content.
     */
    public function viewSensitive(?User $user, Content $content): bool
    {
        if (!$user) {
            return false;
        }

        // Only content owners can view sensitive content
        return $content->user_id === $user->id;
    }

    /**
     * Determine whether the user can share content.
     */
    public function share(?User $user, Content $content): bool
    {
        if (!$user) {
            return false;
        }

        // Only content owners can share their content
        return $content->user_id === $user->id;
    }

    /**
     * Determine whether the user can export content.
     */
    public function export(?User $user, Content $content): bool
    {
        if (!$user) {
            return false;
        }

        // Only content owners can export their content
        return $content->user_id === $user->id;
    }

    /**
     * Determine whether the user can collaborate on content.
     */
    public function collaborate(?User $user, Content $content): bool
    {
        if (!$user) {
            return false;
        }

        // Check if this is collaborative content type
        if ($content->type !== 'collaborative_document') {
            return false;
        }

        // Content owner can always collaborate
        if ($content->user_id === $user->id) {
            return true;
        }

        // Check if user has edit permission on associated workstreams
        return $this->userHasEditPermissionOnAssociatedWorkstreams($user, $content);
    }

    /**
     * Determine whether the user can archive content.
     */
    public function archive(?User $user, Content $content): bool
    {
        if (!$user) {
            return false;
        }

        // Content owner can archive
        if ($content->user_id === $user->id) {
            return true;
        }

        // Workstream owners can archive content in their workstreams
        return $this->userOwnsAssociatedWorkstreams($user, $content);
    }

    /**
     * Helper method to check if user can access content.
     */
    private function userCanAccessContent(User $user, Content $content): bool
    {
        // Content owner can always access their content
        if ($content->user_id === $user->id) {
            return true;
        }

        // Private content types are restricted to owners only
        if (in_array($content->type, ['private_note', 'financial_data'])) {
            return false;
        }

        // Unprocessed content is only visible to the owner
        if ($content->status !== 'processed') {
            return false;
        }

        // Check if user has access through workstream associations
        if ($this->userHasAccessThroughWorkstreams($user, $content)) {
            return true;
        }

        // Check if user has access through release associations
        if ($this->userHasAccessThroughReleases($user, $content)) {
            return true;
        }

        // Check if user has access through stakeholder mentions
        if ($this->userHasAccessThroughStakeholderMentions($user, $content)) {
            return true;
        }

        return false;
    }

    /**
     * Check if user has access through workstream associations.
     */
    private function userHasAccessThroughWorkstreams(User $user, Content $content): bool
    {
        if (!$content->relationLoaded('workstreams')) {
            $content->load('workstreams');
        }

        foreach ($content->workstreams as $workstream) {
            // Check if user owns the workstream
            if ($workstream->owner_id === $user->id) {
                return true;
            }

            // Check if user has permission on the workstream
            $workstreamPolicy = new \App\Policies\WorkstreamPolicy();
            if ($workstreamPolicy->view($user, $workstream)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has access through release associations.
     */
    private function userHasAccessThroughReleases(User $user, Content $content): bool
    {
        if (!$content->relationLoaded('releases')) {
            $content->load('releases');
        }

        foreach ($content->releases as $release) {
            // Check if user is a stakeholder on the release
            $stakeholderRelation = \App\Models\StakeholderRelease::where('release_id', $release->id)
                ->where('user_id', $user->id)
                ->exists();

            if ($stakeholderRelation) {
                return true;
            }

            // Also check through release policy for other access patterns
            $releasePolicy = new \App\Policies\ReleasePolicy();
            if ($releasePolicy->view($user, $release)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has access through stakeholder mentions.
     */
    private function userHasAccessThroughStakeholderMentions(User $user, Content $content): bool
    {
        if (!$content->relationLoaded('stakeholders')) {
            $content->load('stakeholders');
        }

        // Find stakeholders associated with this user
        $userStakeholders = Stakeholder::where('user_id', $user->id)->pluck('id');

        foreach ($content->stakeholders as $stakeholder) {
            if ($userStakeholders->contains($stakeholder->id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has edit permission on associated workstreams.
     */
    private function userHasEditPermissionOnAssociatedWorkstreams(User $user, Content $content): bool
    {
        if (!$content->relationLoaded('workstreams')) {
            $content->load('workstreams');
        }

        foreach ($content->workstreams as $workstream) {
            $workstreamPolicy = new \App\Policies\WorkstreamPolicy();
            if ($workstreamPolicy->update($user, $workstream)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user owns any associated workstreams.
     */
    private function userOwnsAssociatedWorkstreams(User $user, Content $content): bool
    {
        if (!$content->relationLoaded('workstreams')) {
            $content->load('workstreams');
        }

        foreach ($content->workstreams as $workstream) {
            if ($workstream->owner_id === $user->id) {
                return true;
            }
        }

        return false;
    }
}

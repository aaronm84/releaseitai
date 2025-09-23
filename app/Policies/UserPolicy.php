<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workstream;

class UserPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(?User $user, User $model): bool
    {
        if (!$user) {
            return false;
        }

        // Users can view their own profile
        if ($user->id === $model->id) {
            return true;
        }

        // Admin users can view any profile
        if ($this->isAdmin($user)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(?User $user, User $model): bool
    {
        if (!$user) {
            return false;
        }

        // Users can update their own profile
        if ($user->id === $model->id) {
            return true;
        }

        // Admin users can update any profile
        if ($this->isAdmin($user)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(?User $user, User $model): bool
    {
        if (!$user) {
            return false;
        }

        // Regular users cannot delete their own profile (requires special process)
        if ($user->id === $model->id) {
            return false;
        }

        // Admin users can delete user profiles
        if ($this->isAdmin($user)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view colleague profiles.
     */
    public function viewColleague(?User $user, User $colleague): bool
    {
        if (!$user) {
            return false;
        }

        // Can't view own profile through this method
        if ($user->id === $colleague->id) {
            return false;
        }

        // Check if they work together on any workstream
        return $this->workTogether($user, $colleague);
    }

    /**
     * Determine whether the user can view team member profiles.
     */
    public function viewTeamMember(?User $user, User $teamMember): bool
    {
        if (!$user) {
            return false;
        }

        // Workstream owners can view profiles of users with permissions on their workstreams
        $ownedWorkstreams = $user->ownedWorkstreams()->pluck('id');

        if ($ownedWorkstreams->isEmpty()) {
            return false;
        }

        // Check if team member has permissions on any of the user's workstreams
        $hasPermissions = \App\Models\WorkstreamPermission::whereIn('workstream_id', $ownedWorkstreams)
            ->where('user_id', $teamMember->id)
            ->exists();

        return $hasPermissions;
    }

    /**
     * Determine whether the user can view public information of other users.
     */
    public function viewPublicInfo(?User $user, User $model): bool
    {
        if (!$user) {
            return false;
        }

        // Users can view their own info
        if ($user->id === $model->id) {
            return true;
        }

        // Check profile visibility settings
        if (isset($model->profile_visibility) && $model->profile_visibility === 'private') {
            return false;
        }

        // Public info (name, title, company) is generally accessible
        return true;
    }

    /**
     * Determine whether the user can view private information of other users.
     */
    public function viewPrivateInfo(?User $user, User $model): bool
    {
        if (!$user) {
            return false;
        }

        // Users can view their own private information
        if ($user->id === $model->id) {
            return true;
        }

        // Admin users can view private information
        if ($this->isAdmin($user)) {
            return true;
        }

        // Private information (email, phone, personal data) is restricted
        return false;
    }

    /**
     * Determine whether the user can search for colleagues.
     */
    public function searchColleagues(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        // Any authenticated user can search for colleagues they work with
        return true;
    }

    /**
     * Determine whether the user can view their own activity.
     */
    public function viewOwnActivity(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        // Users can view their own activity history
        return true;
    }

    /**
     * Determine whether the user can view another user's activity.
     */
    public function viewActivity(?User $user, User $model): bool
    {
        if (!$user) {
            return false;
        }

        // Users can view their own activity
        if ($user->id === $model->id) {
            return true;
        }

        // Admin users can view any activity
        if ($this->isAdmin($user)) {
            return true;
        }

        // Regular users cannot view other users' activity
        return false;
    }

    /**
     * Determine whether the user can view team activity for a specific workstream.
     */
    public function viewTeamActivity(?User $user, User $teamMember, Workstream $workstream): bool
    {
        if (!$user) {
            return false;
        }

        // Workstream owners can view activity of team members in their workstream
        if ($workstream->owner_id === $user->id) {
            // Check if team member has permissions on this workstream
            $hasPermissions = \App\Models\WorkstreamPermission::where('workstream_id', $workstream->id)
                ->where('user_id', $teamMember->id)
                ->exists();

            return $hasPermissions;
        }

        return false;
    }

    /**
     * Determine whether the user can manage notification preferences.
     */
    public function manageNotifications(?User $user, User $model): bool
    {
        if (!$user) {
            return false;
        }

        // Users can manage their own notification preferences
        if ($user->id === $model->id) {
            return true;
        }

        // Admin users can manage any user's notifications
        if ($this->isAdmin($user)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can change password.
     */
    public function changePassword(?User $user, User $model): bool
    {
        if (!$user) {
            return false;
        }

        // Users can change their own password
        if ($user->id === $model->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can reset another user's password.
     */
    public function resetPassword(?User $user, User $model): bool
    {
        if (!$user) {
            return false;
        }

        // Only admin users can reset other users' passwords
        if ($this->isAdmin($user)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can export data.
     */
    public function exportData(?User $user, User $model): bool
    {
        if (!$user) {
            return false;
        }

        // Users can export their own data
        if ($user->id === $model->id) {
            return true;
        }

        // Admin users can export any user's data
        if ($this->isAdmin($user)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can deactivate an account.
     */
    public function deactivate(?User $user, User $model): bool
    {
        if (!$user) {
            return false;
        }

        // Regular users cannot deactivate their own account (requires special process)
        if ($user->id === $model->id) {
            return false;
        }

        // Only admin users can deactivate accounts
        if ($this->isAdmin($user)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view team statistics for a workstream.
     */
    public function viewTeamStatistics(?User $user, Workstream $workstream): bool
    {
        if (!$user) {
            return false;
        }

        // Workstream owners can view team statistics for their workstreams
        if ($workstream->owner_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Helper method to check if a user is an admin.
     */
    private function isAdmin(User $user): bool
    {
        // Check if user email indicates admin status
        return str_contains($user->email, 'admin@releaseit.com') ||
               str_contains($user->email, '@releaseit.com');
    }

    /**
     * Helper method to check if two users work together.
     */
    private function workTogether(User $user1, User $user2): bool
    {
        // Get workstreams where user1 has permissions
        $user1WorkstreamIds = \App\Models\WorkstreamPermission::where('user_id', $user1->id)
            ->pluck('workstream_id');

        if ($user1WorkstreamIds->isEmpty()) {
            return false;
        }

        // Check if user2 has permissions on any of the same workstreams
        $sharedWorkstreams = \App\Models\WorkstreamPermission::where('user_id', $user2->id)
            ->whereIn('workstream_id', $user1WorkstreamIds)
            ->exists();

        return $sharedWorkstreams;
    }
}
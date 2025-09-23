<?php

namespace App\Policies;

use App\Models\Release;
use App\Models\User;
use App\Models\Workstream;
use Illuminate\Auth\Access\Response;

class ReleasePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(?User $user, Release $release): bool
    {
        if (!$user) {
            return false;
        }

        return $this->userCanAccessRelease($user, $release, 'view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(?User $user, ?Workstream $workstream = null): bool
    {
        if (!$user) {
            return false;
        }

        // If no workstream specified, allow general creation
        if (!$workstream) {
            return true;
        }

        // Check if user can edit the workstream
        $workstreamPolicy = new \App\Policies\WorkstreamPolicy();
        return $workstreamPolicy->update($user, $workstream);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(?User $user, Release $release): bool
    {
        if (!$user) {
            return false;
        }

        return $this->userCanAccessRelease($user, $release, 'edit');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(?User $user, Release $release): bool
    {
        if (!$user) {
            return false;
        }

        return $this->userCanAccessRelease($user, $release, 'delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(?User $user, Release $release): bool
    {
        if (!$user) {
            return false;
        }

        return $this->userCanAccessRelease($user, $release, 'edit');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(?User $user, Release $release): bool
    {
        if (!$user) {
            return false;
        }

        return $this->userCanAccessRelease($user, $release, 'delete');
    }

    /**
     * Determine whether the user can manage stakeholders for the release.
     */
    public function manageStakeholders(?User $user, Release $release): bool
    {
        if (!$user) {
            return false;
        }

        return $this->userCanAccessRelease($user, $release, 'edit');
    }

    /**
     * Determine whether the user can manage tasks for the release.
     */
    public function manageTasks(?User $user, Release $release): bool
    {
        if (!$user) {
            return false;
        }

        return $this->userCanAccessRelease($user, $release, 'edit');
    }

    /**
     * Determine whether the user can communicate about the release.
     */
    public function communicate(?User $user, Release $release): bool
    {
        if (!$user) {
            return false;
        }

        return $this->userCanAccessRelease($user, $release, 'view');
    }

    /**
     * Helper method to check if user can access release.
     */
    private function userCanAccessRelease(User $user, Release $release, string $permissionType): bool
    {
        // Load workstream if not already loaded
        if (!$release->relationLoaded('workstream')) {
            $release->load('workstream');
        }

        // Check if user owns the workstream
        if ($release->workstream && $release->workstream->owner_id === $user->id) {
            return true;
        }

        // Check if user is a stakeholder on the release first (takes precedence)
        $stakeholderRelations = \App\Models\StakeholderRelease::where('release_id', $release->id)
            ->where('user_id', $user->id)
            ->get();

        if ($stakeholderRelations->isNotEmpty()) {
            // Get all roles for this user on this release and find highest permission
            $roles = $stakeholderRelations->pluck('role')->toArray();
            $highestRole = $this->getHighestStakeholderRole($roles);

            switch ($permissionType) {
                case 'view':
                    return in_array($highestRole, ['viewer', 'reviewer', 'approver', 'manager']);
                case 'edit':
                    return in_array($highestRole, ['approver', 'manager']);
                case 'delete':
                    // Only workstream owners can delete releases, not stakeholders
                    return false;
                default:
                    return false;
            }
        }

        // Check if user has permission on the workstream
        if ($release->workstream) {
            $workstreamPolicy = new \App\Policies\WorkstreamPolicy();

            switch ($permissionType) {
                case 'view':
                    return $workstreamPolicy->view($user, $release->workstream);
                case 'edit':
                    return $workstreamPolicy->update($user, $release->workstream);
                case 'delete':
                    return $workstreamPolicy->delete($user, $release->workstream);
                default:
                    return false;
            }
        }

        return false;
    }

    /**
     * Get the highest permission role from an array of stakeholder roles.
     */
    private function getHighestStakeholderRole(array $roles): ?string
    {
        // Permission hierarchy from lowest to highest
        $hierarchy = ['viewer', 'reviewer', 'approver', 'manager'];

        foreach (array_reverse($hierarchy) as $role) {
            if (in_array($role, $roles)) {
                return $role;
            }
        }

        return null;
    }
}

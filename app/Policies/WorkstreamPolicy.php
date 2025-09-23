<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workstream;
use Illuminate\Auth\Access\Response;

class WorkstreamPolicy
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
    public function view(?User $user, Workstream $workstream): bool
    {
        if (!$user) {
            return false;
        }

        return $this->userCanAccessWorkstream($user, $workstream, 'view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Workstream $workstream): bool
    {
        return $this->userCanAccessWorkstream($user, $workstream, 'edit');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Workstream $workstream): bool
    {
        if (!$workstream->canBeDeleted()) {
            return false;
        }

        return $this->userCanAccessWorkstream($user, $workstream, 'delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Workstream $workstream): bool
    {
        return $this->userCanAccessWorkstream($user, $workstream, 'edit');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Workstream $workstream): bool
    {
        return $this->userCanAccessWorkstream($user, $workstream, 'delete');
    }

    /**
     * Determine whether the user can manage permissions for the workstream.
     */
    public function managePermissions(User $user, Workstream $workstream): bool
    {
        return $workstream->owner_id === $user->id;
    }

    /**
     * Determine whether the user can create a child workstream.
     */
    public function createChild(?User $user, Workstream $workstream): bool
    {
        if (!$user) {
            return false;
        }

        return $this->userCanAccessWorkstream($user, $workstream, 'edit');
    }

    /**
     * Determine whether the user can grant permissions on the workstream.
     */
    public function grantPermissions(?User $user, Workstream $workstream): bool
    {
        if (!$user) {
            return false;
        }

        // Owner can always grant permissions
        if ($workstream->owner_id === $user->id) {
            return true;
        }

        // Users with admin permission can grant permissions
        return $this->userCanAccessWorkstream($user, $workstream, 'admin');
    }

    /**
     * Helper method to check if user can access workstream based on permission type.
     */
    private function userCanAccessWorkstream(User $user, Workstream $workstream, string $permissionType): bool
    {
        // Owner has full access
        if ($workstream->owner_id === $user->id) {
            return true;
        }

        // Check direct permissions on this workstream
        $directPermission = $workstream->permissions()
            ->where('user_id', $user->id)
            ->where('permission_type', $permissionType)
            ->first();

        if ($directPermission) {
            return true;
        }

        // Check inherited permissions from ancestors
        if ($workstream->userHasInheritedPermission($user->id, $permissionType)) {
            return true;
        }

        // For view access, also check if user has any permission that would grant view
        if ($permissionType === 'view') {
            $hasAnyPermission = $workstream->permissions()
                ->where('user_id', $user->id)
                ->whereIn('permission_type', ['edit', 'delete'])
                ->exists();

            if ($hasAnyPermission) {
                return true;
            }

            // Check inherited edit/delete permissions
            if ($workstream->userHasInheritedPermission($user->id, 'edit') ||
                $workstream->userHasInheritedPermission($user->id, 'delete')) {
                return true;
            }
        }

        return false;
    }
}

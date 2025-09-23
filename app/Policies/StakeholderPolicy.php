<?php

namespace App\Policies;

use App\Models\Stakeholder;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class StakeholderPolicy
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
    public function view(User $user, Stakeholder $stakeholder): bool
    {
        // Users can view all stakeholders for collaboration purposes
        return true;
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
    public function update(User $user, Stakeholder $stakeholder): bool
    {
        // Users can only update stakeholders they created or manage
        return $this->userCanManageStakeholder($user, $stakeholder);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Stakeholder $stakeholder): bool
    {
        return $this->userCanManageStakeholder($user, $stakeholder);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Stakeholder $stakeholder): bool
    {
        return $this->userCanManageStakeholder($user, $stakeholder);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Stakeholder $stakeholder): bool
    {
        return $this->userCanManageStakeholder($user, $stakeholder);
    }

    /**
     * Determine whether the user can update contact information.
     */
    public function updateContactInfo(User $user, Stakeholder $stakeholder): bool
    {
        return $this->userCanManageStakeholder($user, $stakeholder);
    }

    /**
     * Helper method to check if user can manage stakeholder.
     */
    private function userCanManageStakeholder(User $user, Stakeholder $stakeholder): bool
    {
        // For now, all authenticated users can manage stakeholders
        // This could be enhanced to check if they have access to common workstreams/releases
        return true;
    }
}

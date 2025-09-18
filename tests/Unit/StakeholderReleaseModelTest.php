<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Release;
use App\Models\StakeholderRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StakeholderReleaseModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function stakeholder_release_belongs_to_user_and_release()
    {
        // Given: A stakeholder release relationship
        $user = User::factory()->create();
        $release = Release::factory()->create();

        $stakeholderRelease = StakeholderRelease::create([
            'user_id' => $user->id,
            'release_id' => $release->id,
            'role' => 'owner',
            'notification_preference' => 'email'
        ]);

        // When: Accessing relationships
        $stakeholderUser = $stakeholderRelease->user;
        $stakeholderReleaseFromUser = $stakeholderRelease->release;

        // Then: Relationships should be properly defined
        $this->assertInstanceOf(User::class, $stakeholderUser);
        $this->assertEquals($user->id, $stakeholderUser->id);

        $this->assertInstanceOf(Release::class, $stakeholderReleaseFromUser);
        $this->assertEquals($release->id, $stakeholderReleaseFromUser->id);
    }

    /** @test */
    public function stakeholder_release_validates_role_enum()
    {
        // Given: Valid roles
        $validRoles = ['owner', 'reviewer', 'approver', 'observer'];

        foreach ($validRoles as $role) {
            // When: Creating with valid role
            $stakeholderRelease = StakeholderRelease::make([
                'user_id' => 1,
                'release_id' => 1,
                'role' => $role,
                'notification_preference' => 'email'
            ]);

            // Then: Role should be accepted
            $this->assertEquals($role, $stakeholderRelease->role);
        }
    }

    /** @test */
    public function stakeholder_release_validates_notification_preference_enum()
    {
        // Given: Valid notification preferences
        $validPreferences = ['email', 'slack', 'none'];

        foreach ($validPreferences as $preference) {
            // When: Creating with valid preference
            $stakeholderRelease = StakeholderRelease::make([
                'user_id' => 1,
                'release_id' => 1,
                'role' => 'owner',
                'notification_preference' => $preference
            ]);

            // Then: Preference should be accepted
            $this->assertEquals($preference, $stakeholderRelease->notification_preference);
        }
    }

    /** @test */
    public function stakeholder_release_has_scope_for_role_filtering()
    {
        // Given: Multiple stakeholder releases with different roles
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $release = Release::factory()->create();

        StakeholderRelease::create([
            'user_id' => $user1->id,
            'release_id' => $release->id,
            'role' => 'owner',
            'notification_preference' => 'email'
        ]);

        StakeholderRelease::create([
            'user_id' => $user2->id,
            'release_id' => $release->id,
            'role' => 'reviewer',
            'notification_preference' => 'slack'
        ]);

        // When: Filtering by role
        $owners = StakeholderRelease::ofRole('owner')->get();
        $reviewers = StakeholderRelease::ofRole('reviewer')->get();

        // Then: Correct records should be returned
        $this->assertCount(1, $owners);
        $this->assertEquals('owner', $owners->first()->role);

        $this->assertCount(1, $reviewers);
        $this->assertEquals('reviewer', $reviewers->first()->role);
    }
}
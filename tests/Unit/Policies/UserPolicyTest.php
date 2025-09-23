<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Models\Workstream;
use App\Models\WorkstreamPermission;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    private UserPolicy $policy;
    private User $user;
    private User $otherUser;
    private User $adminUser;
    private User $workstreamOwner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new UserPolicy();

        // Create test users
        $this->user = User::factory()->create(['name' => 'Test User']);
        $this->otherUser = User::factory()->create(['name' => 'Other User']);
        $this->adminUser = User::factory()->create(['name' => 'Admin User', 'email' => 'admin@releaseit.com']);
        $this->workstreamOwner = User::factory()->create(['name' => 'Workstream Owner']);
    }

    /** @test */
    public function test_user_can_view_their_own_profile()
    {
        // Given: A user
        // When: They try to view their own profile
        $result = $this->policy->view($this->user, $this->user);

        // Then: Access should be granted
        $this->assertTrue($result);
    }

    /** @test */
    public function test_user_cannot_view_other_user_profiles_by_default()
    {
        // Given: A user trying to view another user's profile
        // When: They try to view the other user's profile
        $result = $this->policy->view($this->user, $this->otherUser);

        // Then: Access should be denied
        $this->assertFalse($result);
    }

    /** @test */
    public function test_user_can_update_their_own_profile()
    {
        // Given: A user
        // When: They try to update their own profile
        $result = $this->policy->update($this->user, $this->user);

        // Then: Access should be granted
        $this->assertTrue($result);
    }

    /** @test */
    public function test_user_cannot_update_other_user_profiles()
    {
        // Given: A user trying to update another user's profile
        // When: They try to update the other user's profile
        $result = $this->policy->update($this->user, $this->otherUser);

        // Then: Access should be denied
        $this->assertFalse($result);
    }

    /** @test */
    public function test_user_cannot_delete_their_own_profile()
    {
        // Given: A user
        // When: They try to delete their own profile
        $result = $this->policy->delete($this->user, $this->user);

        // Then: Access should be denied (account deletion requires special process)
        $this->assertFalse($result);
    }

    /** @test */
    public function test_admin_user_can_view_any_user_profile()
    {
        // Given: An admin user
        // When: They try to view any user's profile
        $result = $this->policy->view($this->adminUser, $this->user);

        // Then: Access should be granted
        $this->assertTrue($result);
    }

    /** @test */
    public function test_admin_user_can_update_any_user_profile()
    {
        // Given: An admin user
        // When: They try to update any user's profile
        $result = $this->policy->update($this->adminUser, $this->user);

        // Then: Access should be granted
        $this->assertTrue($result);
    }

    /** @test */
    public function test_admin_user_can_delete_user_profiles()
    {
        // Given: An admin user
        // When: They try to delete a user's profile
        $result = $this->policy->delete($this->adminUser, $this->user);

        // Then: Access should be granted
        $this->assertTrue($result);
    }

    /** @test */
    public function test_user_can_view_colleague_profile_if_they_work_together()
    {
        // Given: Two users working on the same workstream
        $workstream = Workstream::factory()->create([
            'owner_id' => $this->workstreamOwner->id,
        ]);

        // Both users have permissions on the same workstream
        WorkstreamPermission::factory()->create([
            'workstream_id' => $workstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'view',
            'scope' => 'workstream_only',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        WorkstreamPermission::factory()->create([
            'workstream_id' => $workstream->id,
            'user_id' => $this->otherUser->id,
            'permission_type' => 'edit',
            'scope' => 'workstream_only',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        // When: One user tries to view the other's profile
        $result = $this->policy->viewColleague($this->user, $this->otherUser);

        // Then: Access should be granted (they work together)
        $this->assertTrue($result);
    }

    /** @test */
    public function test_user_cannot_view_profiles_of_users_they_dont_work_with()
    {
        // Given: Two users who don't work together
        // When: One user tries to view the other's profile
        $result = $this->policy->viewColleague($this->user, $this->otherUser);

        // Then: Access should be denied
        $this->assertFalse($result);
    }

    /** @test */
    public function test_workstream_owner_can_view_profiles_of_users_with_permissions_on_their_workstream()
    {
        // Given: A workstream owner and a user with permission on their workstream
        $workstream = Workstream::factory()->create([
            'owner_id' => $this->workstreamOwner->id,
        ]);

        WorkstreamPermission::factory()->create([
            'workstream_id' => $workstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'view',
            'scope' => 'workstream_only',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        // When: The workstream owner tries to view the user's profile
        $result = $this->policy->viewTeamMember($this->workstreamOwner, $this->user);

        // Then: Access should be granted
        $this->assertTrue($result);
    }

    /** @test */
    public function test_user_can_view_basic_public_information_of_other_users()
    {
        // Given: Any user
        // When: They try to view basic public info of another user
        $result = $this->policy->viewPublicInfo($this->user, $this->otherUser);

        // Then: Access should be granted (name, title, company are public)
        $this->assertTrue($result);
    }

    /** @test */
    public function test_user_cannot_view_private_information_of_other_users()
    {
        // Given: Any user
        // When: They try to view private info of another user
        $result = $this->policy->viewPrivateInfo($this->user, $this->otherUser);

        // Then: Access should be denied (email, phone, personal data are private)
        $this->assertFalse($result);
    }

    /** @test */
    public function test_user_can_view_their_own_private_information()
    {
        // Given: A user
        // When: They try to view their own private information
        $result = $this->policy->viewPrivateInfo($this->user, $this->user);

        // Then: Access should be granted
        $this->assertTrue($result);
    }

    /** @test */
    public function test_unauthenticated_user_cannot_access_user_profiles()
    {
        // Given: No authenticated user (null)
        // When: They try to view a user profile
        $result = $this->policy->view(null, $this->user);

        // Then: Access should be denied
        $this->assertFalse($result);
    }

    /** @test */
    public function test_user_can_search_for_colleagues_they_work_with()
    {
        // Given: Two users working on the same workstream
        $workstream = Workstream::factory()->create([
            'owner_id' => $this->workstreamOwner->id,
        ]);

        WorkstreamPermission::factory()->create([
            'workstream_id' => $workstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'view',
            'scope' => 'workstream_only',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        WorkstreamPermission::factory()->create([
            'workstream_id' => $workstream->id,
            'user_id' => $this->otherUser->id,
            'permission_type' => 'edit',
            'scope' => 'workstream_only',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        // When: One user tries to search for the other
        $result = $this->policy->searchColleagues($this->user);

        // Then: Access should be granted
        $this->assertTrue($result);
    }

    /** @test */
    public function test_user_can_view_their_own_activity_history()
    {
        // Given: A user
        // When: They try to view their own activity history
        $result = $this->policy->viewOwnActivity($this->user);

        // Then: Access should be granted
        $this->assertTrue($result);
    }

    /** @test */
    public function test_user_cannot_view_other_users_activity_history()
    {
        // Given: A user trying to view another user's activity
        // When: They try to view the other user's activity history
        $result = $this->policy->viewActivity($this->user, $this->otherUser);

        // Then: Access should be denied
        $this->assertFalse($result);
    }

    /** @test */
    public function test_workstream_owner_can_view_activity_of_team_members_in_their_workstream()
    {
        // Given: A workstream owner and a user with permission on their workstream
        $workstream = Workstream::factory()->create([
            'owner_id' => $this->workstreamOwner->id,
        ]);

        WorkstreamPermission::factory()->create([
            'workstream_id' => $workstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'edit',
            'scope' => 'workstream_only',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        // When: The workstream owner tries to view the user's workstream-related activity
        $result = $this->policy->viewTeamActivity($this->workstreamOwner, $this->user, $workstream);

        // Then: Access should be granted
        $this->assertTrue($result);
    }

    /** @test */
    public function test_user_can_manage_their_own_notification_preferences()
    {
        // Given: A user
        // When: They try to manage their notification preferences
        $result = $this->policy->manageNotifications($this->user, $this->user);

        // Then: Access should be granted
        $this->assertTrue($result);
    }

    /** @test */
    public function test_user_cannot_manage_other_users_notification_preferences()
    {
        // Given: A user trying to manage another user's notifications
        // When: They try to manage the other user's notification preferences
        $result = $this->policy->manageNotifications($this->user, $this->otherUser);

        // Then: Access should be denied
        $this->assertFalse($result);
    }

    /** @test */
    public function test_user_can_change_their_own_password()
    {
        // Given: A user
        // When: They try to change their own password
        $result = $this->policy->changePassword($this->user, $this->user);

        // Then: Access should be granted
        $this->assertTrue($result);
    }

    /** @test */
    public function test_user_cannot_change_other_users_passwords()
    {
        // Given: A user trying to change another user's password
        // When: They try to change the other user's password
        $result = $this->policy->changePassword($this->user, $this->otherUser);

        // Then: Access should be denied
        $this->assertFalse($result);
    }

    /** @test */
    public function test_admin_can_reset_any_user_password()
    {
        // Given: An admin user
        // When: They try to reset any user's password
        $result = $this->policy->resetPassword($this->adminUser, $this->user);

        // Then: Access should be granted
        $this->assertTrue($result);
    }

    /** @test */
    public function test_regular_user_cannot_reset_other_users_passwords()
    {
        // Given: A regular user
        // When: They try to reset another user's password
        $result = $this->policy->resetPassword($this->user, $this->otherUser);

        // Then: Access should be denied
        $this->assertFalse($result);
    }

    /** @test */
    public function test_user_data_export_is_restricted_to_own_data()
    {
        // Given: A user
        // When: They try to export their own data
        $canExportOwn = $this->policy->exportData($this->user, $this->user);

        // And: They try to export another user's data
        $cannotExportOther = $this->policy->exportData($this->user, $this->otherUser);

        // Then: Only own data export should be allowed
        $this->assertTrue($canExportOwn);
        $this->assertFalse($cannotExportOther);
    }

    /** @test */
    public function test_account_deactivation_requires_special_permission()
    {
        // Given: A user trying to deactivate their account
        // When: They try to deactivate their own account
        $result = $this->policy->deactivate($this->user, $this->user);

        // Then: Access should be denied (requires special process)
        $this->assertFalse($result);
    }

    /** @test */
    public function test_admin_can_deactivate_user_accounts()
    {
        // Given: An admin user
        // When: They try to deactivate a user account
        $result = $this->policy->deactivate($this->adminUser, $this->user);

        // Then: Access should be granted
        $this->assertTrue($result);
    }

    /** @test */
    public function test_user_profile_visibility_settings_are_respected()
    {
        // Given: A user with restricted profile visibility
        $restrictedUser = User::factory()->create([
            'profile_visibility' => 'private',
        ]);

        // When: Another user tries to view the restricted profile
        $result = $this->policy->viewPublicInfo($this->user, $restrictedUser);

        // Then: Access should be denied
        $this->assertFalse($result);
    }

    /** @test */
    public function test_user_can_view_aggregated_team_statistics_for_workstreams_they_own()
    {
        // Given: A workstream owner
        $workstream = Workstream::factory()->create([
            'owner_id' => $this->workstreamOwner->id,
        ]);

        // When: They try to view team statistics for their workstream
        $result = $this->policy->viewTeamStatistics($this->workstreamOwner, $workstream);

        // Then: Access should be granted
        $this->assertTrue($result);
    }

    /** @test */
    public function test_user_cannot_view_team_statistics_for_workstreams_they_dont_own()
    {
        // Given: A workstream owned by someone else
        $workstream = Workstream::factory()->create([
            'owner_id' => $this->workstreamOwner->id,
        ]);

        // When: Another user tries to view team statistics
        $result = $this->policy->viewTeamStatistics($this->user, $workstream);

        // Then: Access should be denied
        $this->assertFalse($result);
    }
}
<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Models\Workstream;
use App\Models\Release;
use App\Models\WorkstreamPermission;
use App\Models\StakeholderRelease;
use App\Policies\ReleasePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReleasePolicyTest extends TestCase
{
    use RefreshDatabase;

    private ReleasePolicy $policy;
    private User $user;
    private User $workstreamOwner;
    private User $stakeholder;
    private User $unrelatedUser;
    private Workstream $workstream;
    private Release $release;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new ReleasePolicy();

        // Create test users
        $this->user = User::factory()->create(['name' => 'Test User']);
        $this->workstreamOwner = User::factory()->create(['name' => 'Workstream Owner']);
        $this->stakeholder = User::factory()->create(['name' => 'Stakeholder User']);
        $this->unrelatedUser = User::factory()->create(['name' => 'Unrelated User']);

        // Create workstream
        $this->workstream = Workstream::factory()->create([
            'name' => 'Test Workstream',
            'owner_id' => $this->workstreamOwner->id,
        ]);

        // Create release
        $this->release = Release::factory()->create([
            'name' => 'Test Release',
            'workstream_id' => $this->workstream->id,
        ]);
    }

    /** @test */
    public function test_workstream_owner_can_view_releases_in_their_workstream()
    {
        // Given: A workstream owner
        // When: They try to view a release in their workstream
        $result = $this->policy->view($this->workstreamOwner, $this->release);

        // Then: Access should be granted
        $this->assertTrue($result);
    }

    /** @test */
    public function test_workstream_owner_can_create_releases_in_their_workstream()
    {
        // Given: A workstream owner
        // When: They try to create a release in their workstream
        $result = $this->policy->create($this->workstreamOwner, $this->workstream);

        // Then: Access should be granted
        $this->assertTrue($result);
    }

    /** @test */
    public function test_workstream_owner_can_update_releases_in_their_workstream()
    {
        // Given: A workstream owner
        // When: They try to update a release in their workstream
        $result = $this->policy->update($this->workstreamOwner, $this->release);

        // Then: Access should be granted
        $this->assertTrue($result);
    }

    /** @test */
    public function test_workstream_owner_can_delete_releases_in_their_workstream()
    {
        // Given: A workstream owner
        // When: They try to delete a release in their workstream
        $result = $this->policy->delete($this->workstreamOwner, $this->release);

        // Then: Access should be granted
        $this->assertTrue($result);
    }

    /** @test */
    public function test_user_with_workstream_edit_permission_can_manage_releases()
    {
        // Given: A user with edit permission on the workstream
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->workstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'edit',
            'scope' => 'workstream_only',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        // When: They try to view, create, update a release
        $canView = $this->policy->view($this->user, $this->release);
        $canCreate = $this->policy->create($this->user, $this->workstream);
        $canUpdate = $this->policy->update($this->user, $this->release);

        // Then: All operations should be allowed
        $this->assertTrue($canView);
        $this->assertTrue($canCreate);
        $this->assertTrue($canUpdate);
    }

    /** @test */
    public function test_user_with_workstream_view_permission_can_only_view_releases()
    {
        // Given: A user with view permission on the workstream
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->workstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'view',
            'scope' => 'workstream_only',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        // When: They try to view, create, update a release
        $canView = $this->policy->view($this->user, $this->release);
        $canCreate = $this->policy->create($this->user, $this->workstream);
        $canUpdate = $this->policy->update($this->user, $this->release);

        // Then: Only view should be allowed
        $this->assertTrue($canView);
        $this->assertFalse($canCreate);
        $this->assertFalse($canUpdate);
    }

    /** @test */
    public function test_release_stakeholder_can_view_release()
    {
        // Given: A user who is a stakeholder on the release
        StakeholderRelease::factory()->create([
            'release_id' => $this->release->id,
            'user_id' => $this->stakeholder->id,
            'role' => 'reviewer',
        ]);

        // When: They try to view the release
        $result = $this->policy->view($this->stakeholder, $this->release);

        // Then: Access should be granted
        $this->assertTrue($result);
    }

    /** @test */
    public function test_release_stakeholder_with_approver_role_can_update_release()
    {
        // Given: A user who is an approver stakeholder on the release
        StakeholderRelease::factory()->create([
            'release_id' => $this->release->id,
            'user_id' => $this->stakeholder->id,
            'role' => 'approver',
        ]);

        // When: They try to update the release
        $result = $this->policy->update($this->stakeholder, $this->release);

        // Then: Access should be granted
        $this->assertTrue($result);
    }

    /** @test */
    public function test_release_stakeholder_with_reviewer_role_cannot_update_release()
    {
        // Given: A user who is a reviewer stakeholder on the release
        StakeholderRelease::factory()->create([
            'release_id' => $this->release->id,
            'user_id' => $this->stakeholder->id,
            'role' => 'reviewer',
        ]);

        // When: They try to update the release
        $result = $this->policy->update($this->stakeholder, $this->release);

        // Then: Access should be denied
        $this->assertFalse($result);
    }

    /** @test */
    public function test_release_stakeholder_with_viewer_role_can_only_view_release()
    {
        // Given: A user who is a viewer stakeholder on the release
        StakeholderRelease::factory()->create([
            'release_id' => $this->release->id,
            'user_id' => $this->stakeholder->id,
            'role' => 'viewer',
        ]);

        // When: They try to view and update the release
        $canView = $this->policy->view($this->stakeholder, $this->release);
        $canUpdate = $this->policy->update($this->stakeholder, $this->release);

        // Then: Only view should be allowed
        $this->assertTrue($canView);
        $this->assertFalse($canUpdate);
    }

    /** @test */
    public function test_stakeholder_cannot_delete_release()
    {
        // Given: A user who is an approver stakeholder on the release
        StakeholderRelease::factory()->create([
            'release_id' => $this->release->id,
            'user_id' => $this->stakeholder->id,
            'role' => 'approver',
        ]);

        // When: They try to delete the release
        $result = $this->policy->delete($this->stakeholder, $this->release);

        // Then: Access should be denied (only workstream owners/editors can delete)
        $this->assertFalse($result);
    }

    /** @test */
    public function test_unrelated_user_cannot_access_release()
    {
        // Given: A user with no relationship to the workstream or release
        // When: They try to view, create, update, or delete the release
        $canView = $this->policy->view($this->unrelatedUser, $this->release);
        $canCreate = $this->policy->create($this->unrelatedUser, $this->workstream);
        $canUpdate = $this->policy->update($this->unrelatedUser, $this->release);
        $canDelete = $this->policy->delete($this->unrelatedUser, $this->release);

        // Then: All operations should be denied
        $this->assertFalse($canView);
        $this->assertFalse($canCreate);
        $this->assertFalse($canUpdate);
        $this->assertFalse($canDelete);
    }

    /** @test */
    public function test_inherited_workstream_permission_allows_release_access()
    {
        // Given: A parent-child workstream hierarchy
        $parentWorkstream = Workstream::factory()->create([
            'owner_id' => $this->workstreamOwner->id,
        ]);

        $childWorkstream = Workstream::factory()->create([
            'owner_id' => $this->workstreamOwner->id,
            'parent_workstream_id' => $parentWorkstream->id,
        ]);

        $childRelease = Release::factory()->create([
            'workstream_id' => $childWorkstream->id,
        ]);

        // And: A user with permission on the parent workstream with inheritance scope
        WorkstreamPermission::factory()->create([
            'workstream_id' => $parentWorkstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'view',
            'scope' => 'workstream_and_children',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        // When: They try to view the release in the child workstream
        $result = $this->policy->view($this->user, $childRelease);

        // Then: Access should be granted through inheritance
        $this->assertTrue($result);
    }

    /** @test */
    public function test_workstream_only_permission_does_not_grant_child_release_access()
    {
        // Given: A parent-child workstream hierarchy
        $parentWorkstream = Workstream::factory()->create([
            'owner_id' => $this->workstreamOwner->id,
        ]);

        $childWorkstream = Workstream::factory()->create([
            'owner_id' => $this->workstreamOwner->id,
            'parent_workstream_id' => $parentWorkstream->id,
        ]);

        $childRelease = Release::factory()->create([
            'workstream_id' => $childWorkstream->id,
        ]);

        // And: A user with permission on the parent workstream with workstream-only scope
        WorkstreamPermission::factory()->create([
            'workstream_id' => $parentWorkstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'view',
            'scope' => 'workstream_only',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        // When: They try to view the release in the child workstream
        $result = $this->policy->view($this->user, $childRelease);

        // Then: Access should be denied (no inheritance)
        $this->assertFalse($result);
    }

    /** @test */
    public function test_unauthenticated_user_cannot_access_releases()
    {
        // Given: No authenticated user (null)
        // When: They try to view a release
        $result = $this->policy->view(null, $this->release);

        // Then: Access should be denied
        $this->assertFalse($result);
    }

    /** @test */
    public function test_stakeholder_permissions_take_precedence_over_workstream_permissions()
    {
        // Given: A user with view-only workstream permission but approver role on release
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->workstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'view',
            'scope' => 'workstream_only',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        StakeholderRelease::factory()->create([
            'release_id' => $this->release->id,
            'user_id' => $this->user->id,
            'role' => 'approver',
        ]);

        // When: They try to update the release
        $result = $this->policy->update($this->user, $this->release);

        // Then: Access should be granted (stakeholder role takes precedence)
        $this->assertTrue($result);
    }

    /** @test */
    public function test_release_manager_stakeholder_can_perform_all_operations_except_delete()
    {
        // Given: A user who is a manager stakeholder on the release
        StakeholderRelease::factory()->create([
            'release_id' => $this->release->id,
            'user_id' => $this->stakeholder->id,
            'role' => 'manager',
        ]);

        // When: They try to view, update, and delete the release
        $canView = $this->policy->view($this->stakeholder, $this->release);
        $canUpdate = $this->policy->update($this->stakeholder, $this->release);
        $canDelete = $this->policy->delete($this->stakeholder, $this->release);

        // Then: View and update should be allowed, delete should be denied
        $this->assertTrue($canView);
        $this->assertTrue($canUpdate);
        $this->assertFalse($canDelete);
    }

    /** @test */
    public function test_user_can_manage_stakeholders_if_they_can_update_release()
    {
        // Given: A workstream owner (who can update releases)
        // When: They try to manage stakeholders for the release
        $result = $this->policy->manageStakeholders($this->workstreamOwner, $this->release);

        // Then: Access should be granted
        $this->assertTrue($result);
    }

    /** @test */
    public function test_user_cannot_manage_stakeholders_if_they_cannot_update_release()
    {
        // Given: A user who can only view the release
        StakeholderRelease::factory()->create([
            'release_id' => $this->release->id,
            'user_id' => $this->stakeholder->id,
            'role' => 'viewer',
        ]);

        // When: They try to manage stakeholders for the release
        $result = $this->policy->manageStakeholders($this->stakeholder, $this->release);

        // Then: Access should be denied
        $this->assertFalse($result);
    }

    /** @test */
    public function test_release_with_multiple_stakeholder_roles_uses_highest_permission()
    {
        // Given: A user with multiple stakeholder relationships to the same release
        StakeholderRelease::factory()->create([
            'release_id' => $this->release->id,
            'user_id' => $this->user->id,
            'role' => 'viewer',
        ]);

        StakeholderRelease::factory()->create([
            'release_id' => $this->release->id,
            'user_id' => $this->user->id,
            'role' => 'approver',
        ]);

        // When: They try to update the release
        $result = $this->policy->update($this->user, $this->release);

        // Then: Access should be granted (approver role should take precedence)
        $this->assertTrue($result);
    }
}
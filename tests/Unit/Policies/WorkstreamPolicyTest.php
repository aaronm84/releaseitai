<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Models\Workstream;
use App\Models\WorkstreamPermission;
use App\Policies\WorkstreamPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkstreamPolicyTest extends TestCase
{
    use RefreshDatabase;

    private WorkstreamPolicy $policy;
    private User $user;
    private User $workstreamOwner;
    private User $unrelatedUser;
    private Workstream $rootWorkstream;
    private Workstream $childWorkstream;
    private Workstream $grandchildWorkstream;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new WorkstreamPolicy();

        // Create test users
        $this->user = User::factory()->create(['name' => 'Test User']);
        $this->workstreamOwner = User::factory()->create(['name' => 'Workstream Owner']);
        $this->unrelatedUser = User::factory()->create(['name' => 'Unrelated User']);

        // Create workstream hierarchy
        $this->rootWorkstream = Workstream::factory()->create([
            'name' => 'Root Workstream',
            'type' => Workstream::TYPE_PRODUCT_LINE,
            'owner_id' => $this->workstreamOwner->id,
            'parent_workstream_id' => null,
            'hierarchy_depth' => 1,
        ]);

        $this->childWorkstream = Workstream::factory()->create([
            'name' => 'Child Workstream',
            'type' => Workstream::TYPE_INITIATIVE,
            'owner_id' => $this->workstreamOwner->id,
            'parent_workstream_id' => $this->rootWorkstream->id,
            'hierarchy_depth' => 2,
        ]);

        $this->grandchildWorkstream = Workstream::factory()->create([
            'name' => 'Grandchild Workstream',
            'type' => Workstream::TYPE_EXPERIMENT,
            'owner_id' => $this->user->id,
            'parent_workstream_id' => $this->childWorkstream->id,
            'hierarchy_depth' => 3,
        ]);
    }

    /** @test */
    public function test_owner_can_view_their_own_workstream()
    {
        // Given: A workstream owner
        // When: They try to view their workstream
        $result = $this->policy->view($this->workstreamOwner, $this->rootWorkstream);

        // Then: Access should be granted
        $this->assertTrue($result);
    }

    /** @test */
    public function test_owner_can_update_their_own_workstream()
    {
        // Given: A workstream owner
        // When: They try to update their workstream
        $result = $this->policy->update($this->workstreamOwner, $this->rootWorkstream);

        // Then: Access should be granted
        $this->assertTrue($result);
    }

    /** @test */
    public function test_owner_can_delete_their_own_workstream_if_no_children()
    {
        // Given: A workstream owner with a workstream that has no children
        $leafWorkstream = Workstream::factory()->create([
            'owner_id' => $this->workstreamOwner->id,
            'parent_workstream_id' => null,
        ]);

        // When: They try to delete their workstream
        $result = $this->policy->delete($this->workstreamOwner, $leafWorkstream);

        // Then: Access should be granted
        $this->assertTrue($result);
    }

    /** @test */
    public function test_owner_cannot_delete_workstream_with_children()
    {
        // Given: A workstream owner with a workstream that has children
        // When: They try to delete their workstream that has children
        $result = $this->policy->delete($this->workstreamOwner, $this->rootWorkstream);

        // Then: Access should be denied
        $this->assertFalse($result);
    }

    /** @test */
    public function test_non_owner_cannot_view_workstream_without_permission()
    {
        // Given: A user who is not the owner and has no permissions
        // When: They try to view a workstream
        $result = $this->policy->view($this->unrelatedUser, $this->rootWorkstream);

        // Then: Access should be denied
        $this->assertFalse($result);
    }

    /** @test */
    public function test_non_owner_cannot_update_workstream_without_permission()
    {
        // Given: A user who is not the owner and has no permissions
        // When: They try to update a workstream
        $result = $this->policy->update($this->unrelatedUser, $this->rootWorkstream);

        // Then: Access should be denied
        $this->assertFalse($result);
    }

    /** @test */
    public function test_user_with_view_permission_can_view_workstream()
    {
        // Given: A user with view permission on a workstream
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->rootWorkstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'view',
            'scope' => 'workstream_only',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        // When: They try to view the workstream
        $result = $this->policy->view($this->user, $this->rootWorkstream);

        // Then: Access should be granted
        $this->assertTrue($result);
    }

    /** @test */
    public function test_user_with_edit_permission_can_update_workstream()
    {
        // Given: A user with edit permission on a workstream
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->rootWorkstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'edit',
            'scope' => 'workstream_only',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        // When: They try to update the workstream
        $result = $this->policy->update($this->user, $this->rootWorkstream);

        // Then: Access should be granted
        $this->assertTrue($result);
    }

    /** @test */
    public function test_user_with_view_only_permission_cannot_update_workstream()
    {
        // Given: A user with only view permission on a workstream
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->rootWorkstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'view',
            'scope' => 'workstream_only',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        // When: They try to update the workstream
        $result = $this->policy->update($this->user, $this->rootWorkstream);

        // Then: Access should be denied
        $this->assertFalse($result);
    }

    /** @test */
    public function test_inherited_permission_allows_access_to_child_workstream()
    {
        // Given: A user with permission on parent workstream with 'workstream_and_children' scope
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->rootWorkstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'view',
            'scope' => 'workstream_and_children',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        // When: They try to view a child workstream
        $result = $this->policy->view($this->user, $this->childWorkstream);

        // Then: Access should be granted through inheritance
        $this->assertTrue($result);
    }

    /** @test */
    public function test_workstream_only_permission_does_not_allow_access_to_child_workstream()
    {
        // Given: A user with permission on parent workstream with 'workstream_only' scope
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->rootWorkstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'view',
            'scope' => 'workstream_only',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        // When: They try to view a child workstream
        $result = $this->policy->view($this->user, $this->childWorkstream);

        // Then: Access should be denied as permission doesn't inherit
        $this->assertFalse($result);
    }

    /** @test */
    public function test_inherited_edit_permission_allows_updating_child_workstream()
    {
        // Given: A user with edit permission on parent workstream with 'workstream_and_children' scope
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->rootWorkstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'edit',
            'scope' => 'workstream_and_children',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        // When: They try to update a child workstream
        $result = $this->policy->update($this->user, $this->childWorkstream);

        // Then: Access should be granted through inheritance
        $this->assertTrue($result);
    }

    /** @test */
    public function test_permission_inheritance_works_across_multiple_hierarchy_levels()
    {
        // Given: A user with permission on root workstream with 'workstream_and_children' scope
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->rootWorkstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'view',
            'scope' => 'workstream_and_children',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        // When: They try to view a grandchild workstream
        $result = $this->policy->view($this->user, $this->grandchildWorkstream);

        // Then: Access should be granted through multi-level inheritance
        $this->assertTrue($result);
    }

    /** @test */
    public function test_user_can_create_workstream()
    {
        // Given: Any authenticated user
        // When: They try to create a workstream
        $result = $this->policy->create($this->user);

        // Then: Access should be granted (any user can create workstreams)
        $this->assertTrue($result);
    }

    /** @test */
    public function test_user_can_create_child_workstream_if_they_have_edit_permission_on_parent()
    {
        // Given: A user with edit permission on a workstream
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->rootWorkstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'edit',
            'scope' => 'workstream_only',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        // When: They try to create a child workstream
        $result = $this->policy->createChild($this->user, $this->rootWorkstream);

        // Then: Access should be granted
        $this->assertTrue($result);
    }

    /** @test */
    public function test_user_cannot_create_child_workstream_without_edit_permission_on_parent()
    {
        // Given: A user without edit permission on a workstream
        // When: They try to create a child workstream
        $result = $this->policy->createChild($this->unrelatedUser, $this->rootWorkstream);

        // Then: Access should be denied
        $this->assertFalse($result);
    }

    /** @test */
    public function test_workstream_owner_can_grant_permissions()
    {
        // Given: A workstream owner
        // When: They try to grant permissions on their workstream
        $result = $this->policy->grantPermissions($this->workstreamOwner, $this->rootWorkstream);

        // Then: Access should be granted
        $this->assertTrue($result);
    }

    /** @test */
    public function test_non_owner_cannot_grant_permissions()
    {
        // Given: A user who is not the workstream owner
        // When: They try to grant permissions on the workstream
        $result = $this->policy->grantPermissions($this->user, $this->rootWorkstream);

        // Then: Access should be denied
        $this->assertFalse($result);
    }

    /** @test */
    public function test_user_with_admin_permission_can_grant_permissions()
    {
        // Given: A user with admin permission on a workstream
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->rootWorkstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'admin',
            'scope' => 'workstream_only',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        // When: They try to grant permissions on the workstream
        $result = $this->policy->grantPermissions($this->user, $this->rootWorkstream);

        // Then: Access should be granted
        $this->assertTrue($result);
    }

    /** @test */
    public function test_unauthenticated_user_cannot_access_workstream()
    {
        // Given: No authenticated user (null)
        // When: They try to view a workstream
        $result = $this->policy->view(null, $this->rootWorkstream);

        // Then: Access should be denied
        $this->assertFalse($result);
    }

    /** @test */
    public function test_multiple_permissions_on_same_workstream_are_aggregated_correctly()
    {
        // Given: A user with both view and edit permissions on the same workstream
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->rootWorkstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'view',
            'scope' => 'workstream_only',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->rootWorkstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'edit',
            'scope' => 'workstream_only',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        // When: They try to update the workstream
        $result = $this->policy->update($this->user, $this->rootWorkstream);

        // Then: Access should be granted (edit permission should be recognized)
        $this->assertTrue($result);
    }

    /** @test */
    public function test_direct_permission_overrides_inherited_permission()
    {
        // Given: A user with view permission on parent and edit permission on child
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->rootWorkstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'view',
            'scope' => 'workstream_and_children',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->childWorkstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'edit',
            'scope' => 'workstream_only',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        // When: They try to update the child workstream
        $result = $this->policy->update($this->user, $this->childWorkstream);

        // Then: Access should be granted (direct edit permission should take precedence)
        $this->assertTrue($result);
    }
}
<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use App\Models\Workstream;
use App\Models\WorkstreamPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkstreamAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $workstreamOwner;
    private User $unrelatedUser;
    private Workstream $workstream;
    private Workstream $childWorkstream;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->user = User::factory()->create();
        $this->workstreamOwner = User::factory()->create();
        $this->unrelatedUser = User::factory()->create();

        // Create workstreams
        $this->workstream = Workstream::factory()->create([
            'name' => 'Test Workstream',
            'owner_id' => $this->workstreamOwner->id,
        ]);

        $this->childWorkstream = Workstream::factory()->create([
            'name' => 'Child Workstream',
            'parent_workstream_id' => $this->workstream->id,
            'owner_id' => $this->workstreamOwner->id,
        ]);
    }

    /** @test */
    public function test_unauthenticated_user_cannot_access_workstream_endpoints()
    {
        // When: Unauthenticated user tries to access workstream endpoints
        $response = $this->getJson('/api/workstreams');

        // Then: Should receive 401 Unauthorized
        $response->assertStatus(401);
    }

    /** @test */
    public function test_authenticated_user_can_view_workstreams_list()
    {
        // Given: An authenticated user
        // When: They try to view workstreams list
        $response = $this->actingAs($this->user)->getJson('/api/workstreams');

        // Then: Should receive success (they can see workstreams they have access to)
        $response->assertStatus(200);
    }

    /** @test */
    public function test_workstream_owner_can_view_their_workstream()
    {
        // Given: A workstream owner
        // When: They try to view their workstream
        $response = $this->actingAs($this->workstreamOwner)
            ->getJson("/api/workstreams/{$this->workstream->id}");

        // Then: Should receive success
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $this->workstream->id,
                    'name' => $this->workstream->name,
                ]
            ]);
    }

    /** @test */
    public function test_user_without_permission_cannot_view_workstream()
    {
        // Given: A user without permission on the workstream
        // When: They try to view the workstream
        $response = $this->actingAs($this->unrelatedUser)
            ->getJson("/api/workstreams/{$this->workstream->id}");

        // Then: Should receive 403 Forbidden
        $response->assertStatus(403);
    }

    /** @test */
    public function test_user_with_view_permission_can_view_workstream()
    {
        // Given: A user with view permission on the workstream
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->workstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'view',
            'scope' => 'workstream_only',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        // When: They try to view the workstream
        $response = $this->actingAs($this->user)
            ->getJson("/api/workstreams/{$this->workstream->id}");

        // Then: Should receive success
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $this->workstream->id,
                ]
            ]);
    }

    /** @test */
    public function test_workstream_owner_can_create_workstream()
    {
        // Given: A workstream owner
        $workstreamData = [
            'name' => 'New Workstream',
            'description' => 'Test description',
            'type' => Workstream::TYPE_PRODUCT_LINE,
            'status' => Workstream::STATUS_ACTIVE,
        ];

        // When: They try to create a workstream
        $response = $this->actingAs($this->workstreamOwner)
            ->postJson('/api/workstreams', $workstreamData);

        // Then: Should receive success
        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'name' => 'New Workstream',
                    'owner_id' => $this->workstreamOwner->id,
                ]
            ]);
    }

    /** @test */
    public function test_authenticated_user_can_create_workstream()
    {
        // Given: Any authenticated user
        $workstreamData = [
            'name' => 'User Created Workstream',
            'description' => 'Test description',
            'type' => Workstream::TYPE_INITIATIVE,
            'status' => Workstream::STATUS_ACTIVE,
        ];

        // When: They try to create a workstream
        $response = $this->actingAs($this->user)
            ->postJson('/api/workstreams', $workstreamData);

        // Then: Should receive success
        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'name' => 'User Created Workstream',
                    'owner_id' => $this->user->id,
                ]
            ]);
    }

    /** @test */
    public function test_workstream_owner_can_update_their_workstream()
    {
        // Given: A workstream owner
        $updateData = [
            'name' => 'Updated Workstream Name',
            'description' => 'Updated description',
        ];

        // When: They try to update their workstream
        $response = $this->actingAs($this->workstreamOwner)
            ->putJson("/api/workstreams/{$this->workstream->id}", $updateData);

        // Then: Should receive success
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'name' => 'Updated Workstream Name',
                ]
            ]);
    }

    /** @test */
    public function test_user_without_edit_permission_cannot_update_workstream()
    {
        // Given: A user with only view permission
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->workstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'view',
            'scope' => 'workstream_only',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        $updateData = [
            'name' => 'Unauthorized Update',
        ];

        // When: They try to update the workstream
        $response = $this->actingAs($this->user)
            ->putJson("/api/workstreams/{$this->workstream->id}", $updateData);

        // Then: Should receive 403 Forbidden
        $response->assertStatus(403);
    }

    /** @test */
    public function test_user_with_edit_permission_can_update_workstream()
    {
        // Given: A user with edit permission
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->workstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'edit',
            'scope' => 'workstream_only',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        $updateData = [
            'name' => 'Authorized Update',
        ];

        // When: They try to update the workstream
        $response = $this->actingAs($this->user)
            ->putJson("/api/workstreams/{$this->workstream->id}", $updateData);

        // Then: Should receive success
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'name' => 'Authorized Update',
                ]
            ]);
    }

    /** @test */
    public function test_workstream_owner_can_delete_workstream_without_children()
    {
        // Given: A workstream without children
        $leafWorkstream = Workstream::factory()->create([
            'owner_id' => $this->workstreamOwner->id,
        ]);

        // When: The owner tries to delete it
        $response = $this->actingAs($this->workstreamOwner)
            ->deleteJson("/api/workstreams/{$leafWorkstream->id}");

        // Then: Should receive success
        $response->assertStatus(204);
    }

    /** @test */
    public function test_workstream_owner_cannot_delete_workstream_with_children()
    {
        // Given: A workstream with children (our main workstream has childWorkstream)
        // When: The owner tries to delete it
        $response = $this->actingAs($this->workstreamOwner)
            ->deleteJson("/api/workstreams/{$this->workstream->id}");

        // Then: Should receive 422 Unprocessable Entity (business rule violation)
        $response->assertStatus(422);
    }

    /** @test */
    public function test_user_without_permission_cannot_delete_workstream()
    {
        // Given: A user without permission
        // When: They try to delete the workstream
        $response = $this->actingAs($this->unrelatedUser)
            ->deleteJson("/api/workstreams/{$this->workstream->id}");

        // Then: Should receive 403 Forbidden
        $response->assertStatus(403);
    }

    /** @test */
    public function test_user_can_create_child_workstream_with_parent_edit_permission()
    {
        // Given: A user with edit permission on parent workstream
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->workstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'edit',
            'scope' => 'workstream_only',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        $childData = [
            'name' => 'New Child Workstream',
            'description' => 'Child description',
            'type' => Workstream::TYPE_EXPERIMENT,
            'parent_workstream_id' => $this->workstream->id,
            'status' => Workstream::STATUS_ACTIVE,
        ];

        // When: They try to create a child workstream
        $response = $this->actingAs($this->user)
            ->postJson('/api/workstreams', $childData);

        // Then: Should receive success
        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'name' => 'New Child Workstream',
                    'parent_workstream_id' => $this->workstream->id,
                ]
            ]);
    }

    /** @test */
    public function test_user_cannot_create_child_workstream_without_parent_permission()
    {
        // Given: A user without permission on parent workstream
        $childData = [
            'name' => 'Unauthorized Child',
            'type' => Workstream::TYPE_EXPERIMENT,
            'parent_workstream_id' => $this->workstream->id,
            'status' => Workstream::STATUS_ACTIVE,
        ];

        // When: They try to create a child workstream
        $response = $this->actingAs($this->unrelatedUser)
            ->postJson('/api/workstreams', $childData);

        // Then: Should receive 403 Forbidden
        $response->assertStatus(403);
    }

    /** @test */
    public function test_inherited_permission_allows_access_to_child_workstream()
    {
        // Given: A user with inherited permission on parent workstream
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->workstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'view',
            'scope' => 'workstream_and_children',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        // When: They try to view the child workstream
        $response = $this->actingAs($this->user)
            ->getJson("/api/workstreams/{$this->childWorkstream->id}");

        // Then: Should receive success through inheritance
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $this->childWorkstream->id,
                ]
            ]);
    }

    /** @test */
    public function test_workstream_only_permission_does_not_allow_child_access()
    {
        // Given: A user with workstream-only permission on parent
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->workstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'view',
            'scope' => 'workstream_only',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        // When: They try to view the child workstream
        $response = $this->actingAs($this->user)
            ->getJson("/api/workstreams/{$this->childWorkstream->id}");

        // Then: Should receive 403 Forbidden (no inheritance)
        $response->assertStatus(403);
    }

    /** @test */
    public function test_workstream_owner_can_manage_permissions()
    {
        // Given: A workstream owner
        $permissionData = [
            'user_id' => $this->user->id,
            'permission_type' => 'edit',
            'scope' => 'workstream_only',
        ];

        // When: They try to grant permissions
        $response = $this->actingAs($this->workstreamOwner)
            ->postJson("/api/workstreams/{$this->workstream->id}/permissions", $permissionData);

        // Then: Should receive success
        $response->assertStatus(201);
    }

    /** @test */
    public function test_non_owner_cannot_manage_permissions()
    {
        // Given: A user with edit permission (but not owner)
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->workstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'edit',
            'scope' => 'workstream_only',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        $permissionData = [
            'user_id' => $this->unrelatedUser->id,
            'permission_type' => 'view',
            'scope' => 'workstream_only',
        ];

        // When: They try to grant permissions
        $response = $this->actingAs($this->user)
            ->postJson("/api/workstreams/{$this->workstream->id}/permissions", $permissionData);

        // Then: Should receive 403 Forbidden
        $response->assertStatus(403);
    }

    /** @test */
    public function test_workstream_hierarchy_endpoint_respects_permissions()
    {
        // Given: A user with permission on parent workstream only
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->workstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'view',
            'scope' => 'workstream_only',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        // When: They try to view the hierarchy
        $response = $this->actingAs($this->user)
            ->getJson("/api/workstreams/{$this->workstream->id}/hierarchy");

        // Then: Should only see workstreams they have access to
        $response->assertStatus(200);
        $responseData = $response->json();

        // Should contain parent workstream but not necessarily all children
        $this->assertEquals($this->workstream->id, $responseData['data']['id']);
    }

    /** @test */
    public function test_bulk_workstream_operations_respect_individual_permissions()
    {
        // Given: A user with permission on one workstream but not another
        $anotherWorkstream = Workstream::factory()->create([
            'owner_id' => $this->unrelatedUser->id,
        ]);

        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->workstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'view',
            'scope' => 'workstream_only',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        // When: They try to bulk fetch workstreams
        $response = $this->actingAs($this->user)
            ->postJson('/api/workstreams/bulk', [
                'workstream_ids' => [$this->workstream->id, $anotherWorkstream->id]
            ]);

        // Then: Should only receive workstreams they have access to
        $response->assertStatus(200);
        $responseData = $response->json();

        // Should only contain the workstream they have permission for
        $this->assertCount(1, $responseData['data']);
        $this->assertEquals($this->workstream->id, $responseData['data'][0]['id']);
    }

    /** @test */
    public function test_workstream_search_respects_permissions()
    {
        // Given: Multiple workstreams, user has permission on one
        $searchableWorkstream = Workstream::factory()->create([
            'name' => 'Searchable Workstream',
            'owner_id' => $this->workstreamOwner->id,
        ]);

        $hiddenWorkstream = Workstream::factory()->create([
            'name' => 'Hidden Searchable Workstream',
            'owner_id' => $this->unrelatedUser->id,
        ]);

        WorkstreamPermission::factory()->create([
            'workstream_id' => $searchableWorkstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'view',
            'scope' => 'workstream_only',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        // When: They search for workstreams
        $response = $this->actingAs($this->user)
            ->getJson('/api/workstreams/search?q=Searchable');

        // Then: Should only see workstreams they have access to
        $response->assertStatus(200);
        $responseData = $response->json();

        // Should find the searchable workstream but not the hidden one
        $foundIds = collect($responseData['data'])->pluck('id')->toArray();
        $this->assertContains($searchableWorkstream->id, $foundIds);
        $this->assertNotContains($hiddenWorkstream->id, $foundIds);
    }
}
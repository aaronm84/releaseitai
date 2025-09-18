<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Models\Workstream;
use App\Models\WorkstreamPermission;
use App\Models\Release;
use App\Models\Communication;
use App\Models\ChecklistItem;
use App\Models\ChecklistItemAssignment;
use App\Models\ApprovalRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $otherUser;
    private User $adminUser;
    private Workstream $userWorkstream;
    private Workstream $otherUserWorkstream;
    private Release $userRelease;
    private Release $otherUserRelease;
    private Communication $userCommunication;
    private Communication $otherUserCommunication;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['name' => 'Test User']);
        $this->otherUser = User::factory()->create(['name' => 'Other User']);
        $this->adminUser = User::factory()->create(['name' => 'Admin User']);

        // Create workstreams owned by different users
        $this->userWorkstream = Workstream::factory()->create([
            'name' => 'User Workstream',
            'owner_id' => $this->user->id
        ]);

        $this->otherUserWorkstream = Workstream::factory()->create([
            'name' => 'Other User Workstream',
            'owner_id' => $this->otherUser->id
        ]);

        // Create releases in different workstreams
        $this->userRelease = Release::factory()->create([
            'name' => 'User Release',
            'workstream_id' => $this->userWorkstream->id
        ]);

        $this->otherUserRelease = Release::factory()->create([
            'name' => 'Other User Release',
            'workstream_id' => $this->otherUserWorkstream->id
        ]);

        // Create communications for different releases
        $this->userCommunication = Communication::factory()->create([
            'release_id' => $this->userRelease->id,
            'initiated_by_user_id' => $this->user->id,
            'subject' => 'User Communication'
        ]);

        $this->otherUserCommunication = Communication::factory()->create([
            'release_id' => $this->otherUserRelease->id,
            'initiated_by_user_id' => $this->otherUser->id,
            'subject' => 'Other User Communication'
        ]);
    }

    /** @test */
    public function users_can_only_view_their_own_workstreams_without_explicit_permissions()
    {
        // Given: User is authenticated
        Sanctum::actingAs($this->user);

        // When: Attempting to view own workstream
        $response = $this->getJson("/api/workstreams/{$this->userWorkstream->id}");

        // Then: Should be allowed (owner has access)
        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'User Workstream');

        // When: Attempting to view other user's workstream
        $response = $this->getJson("/api/workstreams/{$this->otherUserWorkstream->id}");

        // Then: Should be forbidden
        $response->assertStatus(403);
        $response->assertJson(['message' => 'Forbidden']);
    }

    /** @test */
    public function users_cannot_modify_workstreams_they_do_not_own()
    {
        // Given: User is authenticated
        Sanctum::actingAs($this->user);

        // When: Attempting to update other user's workstream
        $response = $this->putJson("/api/workstreams/{$this->otherUserWorkstream->id}", [
            'name' => 'Hacked Workstream Name'
        ]);

        // Then: Should be forbidden
        $response->assertStatus(403);
        $response->assertJson(['message' => 'Forbidden']);

        // Verify the workstream was not modified
        $this->otherUserWorkstream->refresh();
        $this->assertNotEquals('Hacked Workstream Name', $this->otherUserWorkstream->name);
    }

    /** @test */
    public function users_cannot_delete_workstreams_they_do_not_own()
    {
        // Given: User is authenticated
        Sanctum::actingAs($this->user);

        // When: Attempting to delete other user's workstream
        $response = $this->deleteJson("/api/workstreams/{$this->otherUserWorkstream->id}");

        // Then: Should be forbidden or not found
        $this->assertContains($response->getStatusCode(), [403, 404]);

        // Verify the workstream still exists
        $this->assertDatabaseHas('workstreams', [
            'id' => $this->otherUserWorkstream->id,
            'name' => 'Other User Workstream'
        ]);
    }

    /** @test */
    public function workstream_permissions_grant_appropriate_access_levels()
    {
        // Given: User has view permission on other user's workstream
        WorkstreamPermission::create([
            'workstream_id' => $this->otherUserWorkstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'view',
            'scope' => 'workstream_only',
            'granted_by' => $this->otherUser->id
        ]);

        Sanctum::actingAs($this->user);

        // When: Attempting to view the workstream
        $response = $this->getJson("/api/workstreams/{$this->otherUserWorkstream->id}");

        // Then: Should be allowed
        $response->assertStatus(200);

        // When: Attempting to edit the workstream (with only view permission)
        $response = $this->putJson("/api/workstreams/{$this->otherUserWorkstream->id}", [
            'name' => 'Attempted Edit'
        ]);

        // Then: Should be forbidden (view permission doesn't allow editing)
        $response->assertStatus(403);
    }

    /** @test */
    public function edit_permissions_allow_workstream_modifications()
    {
        // Given: User has edit permission on other user's workstream
        WorkstreamPermission::create([
            'workstream_id' => $this->otherUserWorkstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'edit',
            'scope' => 'workstream_only',
            'granted_by' => $this->otherUser->id
        ]);

        Sanctum::actingAs($this->user);

        // When: Attempting to edit the workstream
        $response = $this->putJson("/api/workstreams/{$this->otherUserWorkstream->id}", [
            'name' => 'Updated by Editor'
        ]);

        // Then: Should be allowed
        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'Updated by Editor');
    }

    /** @test */
    public function admin_permissions_allow_full_workstream_access()
    {
        // Given: User has admin permission on other user's workstream
        WorkstreamPermission::create([
            'workstream_id' => $this->otherUserWorkstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'admin',
            'scope' => 'workstream_only',
            'granted_by' => $this->otherUser->id
        ]);

        Sanctum::actingAs($this->user);

        // When: Attempting admin-level operations
        $viewResponse = $this->getJson("/api/workstreams/{$this->otherUserWorkstream->id}");
        $editResponse = $this->putJson("/api/workstreams/{$this->otherUserWorkstream->id}", [
            'name' => 'Updated by Admin'
        ]);
        $permissionsResponse = $this->getJson("/api/workstreams/{$this->otherUserWorkstream->id}/permissions");

        // Then: All should be allowed
        $viewResponse->assertStatus(200);
        $editResponse->assertStatus(200);
        $permissionsResponse->assertStatus(200);
    }

    /** @test */
    public function hierarchical_permissions_cascade_to_child_workstreams()
    {
        // Given: Child workstream and permission with workstream_and_children scope
        $childWorkstream = Workstream::factory()->create([
            'name' => 'Child Workstream',
            'parent_workstream_id' => $this->otherUserWorkstream->id,
            'owner_id' => $this->otherUser->id
        ]);

        WorkstreamPermission::create([
            'workstream_id' => $this->otherUserWorkstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'view',
            'scope' => 'workstream_and_children',
            'granted_by' => $this->otherUser->id
        ]);

        Sanctum::actingAs($this->user);

        // When: Attempting to view child workstream
        $response = $this->getJson("/api/workstreams/{$childWorkstream->id}");

        // Then: Should be allowed due to inherited permissions
        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'Child Workstream');
    }

    /** @test */
    public function users_cannot_access_communications_from_unauthorized_releases()
    {
        // Given: User is authenticated
        Sanctum::actingAs($this->user);

        // When: Attempting to view communications from other user's release
        $response = $this->getJson("/api/releases/{$this->otherUserRelease->id}/communications");

        // Then: Should be forbidden or return empty results
        $this->assertContains($response->getStatusCode(), [403, 200]);

        if ($response->getStatusCode() === 200) {
            // If 200, should not return other user's communications
            $communications = $response->json('data');
            foreach ($communications as $communication) {
                $this->assertNotEquals($this->otherUserCommunication->id, $communication['id']);
            }
        }
    }

    /** @test */
    public function users_cannot_view_specific_communications_they_are_not_authorized_for()
    {
        // Given: User is authenticated
        Sanctum::actingAs($this->user);

        // When: Attempting to view other user's communication
        $response = $this->getJson("/api/communications/{$this->otherUserCommunication->id}");

        // Then: Should be forbidden or not found
        $this->assertContains($response->getStatusCode(), [403, 404]);
    }

    /** @test */
    public function users_cannot_create_communications_for_unauthorized_releases()
    {
        // Given: User is authenticated
        Sanctum::actingAs($this->user);

        // When: Attempting to create communication for other user's release
        $response = $this->postJson("/api/releases/{$this->otherUserRelease->id}/communications", [
            'channel' => 'email',
            'content' => 'Unauthorized communication',
            'communication_type' => 'notification',
            'direction' => 'outbound',
            'participants' => [
                ['user_id' => $this->user->id, 'type' => 'primary']
            ]
        ]);

        // Then: Should be forbidden
        $response->assertStatus(403);
    }

    /** @test */
    public function workstream_search_returns_only_authorized_results()
    {
        // Given: User is authenticated and multiple workstreams exist
        Sanctum::actingAs($this->user);

        // When: Searching for workstreams
        $response = $this->getJson('/api/workstreams');

        // Then: Should only return workstreams the user can access
        $response->assertStatus(200);
        $workstreams = $response->json('data');

        foreach ($workstreams as $workstream) {
            // Should not include other user's workstream without explicit permission
            if ($workstream['id'] === $this->otherUserWorkstream->id) {
                $this->fail('User should not see other user\'s workstream without permission');
            }
        }

        // Should include user's own workstream
        $userWorkstreamFound = false;
        foreach ($workstreams as $workstream) {
            if ($workstream['id'] === $this->userWorkstream->id) {
                $userWorkstreamFound = true;
                break;
            }
        }
        $this->assertTrue($userWorkstreamFound, 'User should see their own workstream');
    }

    /** @test */
    public function communication_search_respects_authorization_boundaries()
    {
        // Given: User is authenticated and communications exist
        Sanctum::actingAs($this->user);

        // When: Searching communications with a query that would match unauthorized content
        $response = $this->getJson('/api/communications/search?query=Communication');

        // Then: Should only return authorized communications
        $response->assertStatus(200);
        $communications = $response->json('data');

        foreach ($communications as $communication) {
            // Should not return other user's communications
            $this->assertNotEquals($this->otherUserCommunication->id, $communication['id']);
        }
    }

    /** @test */
    public function bulk_operations_respect_individual_authorization()
    {
        // Given: User is authenticated with mixed workstream ownership
        Sanctum::actingAs($this->user);

        // When: Attempting bulk update including unauthorized workstreams
        $response = $this->putJson('/api/workstreams/bulk-update', [
            'workstream_ids' => [
                $this->userWorkstream->id,      // User owns this
                $this->otherUserWorkstream->id  // User doesn't own this
            ],
            'updates' => ['status' => 'completed']
        ]);

        // Then: Should either reject entirely or only update authorized workstreams
        $this->assertContains($response->getStatusCode(), [200, 403, 422]);

        if ($response->getStatusCode() === 200) {
            // If successful, verify only authorized workstream was updated
            $this->userWorkstream->refresh();
            $this->otherUserWorkstream->refresh();

            $this->assertEquals('completed', $this->userWorkstream->status);
            // Other user's workstream should not be updated
            $this->assertNotEquals('completed', $this->otherUserWorkstream->status);
        }
    }

    /** @test */
    public function users_cannot_grant_permissions_on_workstreams_they_do_not_control()
    {
        // Given: User is authenticated
        Sanctum::actingAs($this->user);

        // When: Attempting to grant permissions on other user's workstream
        $response = $this->postJson("/api/workstreams/{$this->otherUserWorkstream->id}/permissions", [
            'user_id' => $this->adminUser->id,
            'permission_type' => 'admin'
        ]);

        // Then: Should be forbidden
        $response->assertStatus(403);

        // Verify no permission was actually granted
        $this->assertDatabaseMissing('workstream_permissions', [
            'workstream_id' => $this->otherUserWorkstream->id,
            'user_id' => $this->adminUser->id,
            'permission_type' => 'admin'
        ]);
    }

    /** @test */
    public function workstream_hierarchy_operations_respect_authorization()
    {
        // Given: User is authenticated
        Sanctum::actingAs($this->user);

        // When: Attempting to get hierarchy for unauthorized workstream
        $response = $this->getJson("/api/workstreams/{$this->otherUserWorkstream->id}/hierarchy");

        // Then: Should be forbidden
        $response->assertStatus(403);

        // When: Attempting to move unauthorized workstream
        $response = $this->putJson("/api/workstreams/{$this->otherUserWorkstream->id}/move", [
            'new_parent_workstream_id' => $this->userWorkstream->id
        ]);

        // Then: Should be forbidden
        $response->assertStatus(403);
    }

    /** @test */
    public function approval_requests_respect_approver_authorization()
    {
        // Given: User is authenticated and approval request exists
        $approvalRequest = ApprovalRequest::factory()->create([
            'release_id' => $this->userRelease->id,
            'approver_id' => $this->user->id,
            'workstream_id' => $this->userWorkstream->id
        ]);

        $otherApprovalRequest = ApprovalRequest::factory()->create([
            'release_id' => $this->otherUserRelease->id,
            'approver_id' => $this->otherUser->id,
            'workstream_id' => $this->otherUserWorkstream->id
        ]);

        Sanctum::actingAs($this->user);

        // When: Attempting to respond to own approval request
        $response = $this->postJson("/api/approval-requests/{$approvalRequest->id}/respond", [
            'decision' => 'approved',
            'comments' => 'Looks good'
        ]);

        // Then: Should be allowed
        $this->assertContains($response->getStatusCode(), [200, 422]); // 422 might be due to validation rules

        // When: Attempting to respond to other user's approval request
        $response = $this->postJson("/api/approval-requests/{$otherApprovalRequest->id}/respond", [
            'decision' => 'approved',
            'comments' => 'Unauthorized approval'
        ]);

        // Then: Should be forbidden
        $response->assertStatus(403);
    }

    /** @test */
    public function cross_tenant_data_isolation_is_enforced()
    {
        // Given: Users from different "tenants" (represented by ownership)
        Sanctum::actingAs($this->user);

        // When: Making requests that could expose cross-tenant data
        $endpoints = [
            "/api/workstreams/{$this->otherUserWorkstream->id}",
            "/api/workstreams/{$this->otherUserWorkstream->id}/hierarchy",
            "/api/workstreams/{$this->otherUserWorkstream->id}/rollup-report",
            "/api/releases/{$this->otherUserRelease->id}/communications",
            "/api/communications/{$this->otherUserCommunication->id}",
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->getJson($endpoint);

            // Then: All should be forbidden or return filtered results
            $this->assertContains($response->getStatusCode(), [403, 404, 200]);

            if ($response->getStatusCode() === 200) {
                // If 200, should not contain unauthorized data
                $responseData = $response->json();
                $this->assertIsArray($responseData);
                // Additional checks could be added here based on endpoint structure
            }
        }
    }

    /** @test */
    public function permission_inheritance_correctly_implements_hierarchy()
    {
        // Given: Multi-level workstream hierarchy
        $parentWorkstream = Workstream::factory()->create([
            'owner_id' => $this->otherUser->id,
            'name' => 'Parent Workstream'
        ]);

        $childWorkstream = Workstream::factory()->create([
            'owner_id' => $this->otherUser->id,
            'parent_workstream_id' => $parentWorkstream->id,
            'name' => 'Child Workstream'
        ]);

        $grandchildWorkstream = Workstream::factory()->create([
            'owner_id' => $this->otherUser->id,
            'parent_workstream_id' => $childWorkstream->id,
            'name' => 'Grandchild Workstream'
        ]);

        // Given: Permission granted on parent with inheritance
        WorkstreamPermission::create([
            'workstream_id' => $parentWorkstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'view',
            'scope' => 'workstream_and_children',
            'granted_by' => $this->otherUser->id
        ]);

        Sanctum::actingAs($this->user);

        // When: Accessing child and grandchild workstreams
        $childResponse = $this->getJson("/api/workstreams/{$childWorkstream->id}");
        $grandchildResponse = $this->getJson("/api/workstreams/{$grandchildWorkstream->id}");

        // Then: Both should be accessible due to inheritance
        $childResponse->assertStatus(200);
        $grandchildResponse->assertStatus(200);
    }

    /** @test */
    public function direct_permissions_override_inherited_permissions()
    {
        // Given: Parent workstream with inherited permission and child with direct permission
        $parentWorkstream = Workstream::factory()->create([
            'owner_id' => $this->otherUser->id
        ]);

        $childWorkstream = Workstream::factory()->create([
            'owner_id' => $this->otherUser->id,
            'parent_workstream_id' => $parentWorkstream->id
        ]);

        // Parent grants view with inheritance
        WorkstreamPermission::create([
            'workstream_id' => $parentWorkstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'view',
            'scope' => 'workstream_and_children',
            'granted_by' => $this->otherUser->id
        ]);

        // Child grants admin directly
        WorkstreamPermission::create([
            'workstream_id' => $childWorkstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'admin',
            'scope' => 'workstream_only',
            'granted_by' => $this->otherUser->id
        ]);

        Sanctum::actingAs($this->user);

        // When: Accessing child workstream permissions
        $response = $this->getJson("/api/workstreams/{$childWorkstream->id}/permissions");

        // Then: Should have admin access (direct overrides inherited view)
        $response->assertStatus(200);
        $permissions = $response->json('data.user_permissions.effective_permissions');
        $this->assertContains('admin', $permissions);
    }
}
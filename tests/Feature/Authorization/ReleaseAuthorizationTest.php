<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use App\Models\Workstream;
use App\Models\Release;
use App\Models\WorkstreamPermission;
use App\Models\StakeholderRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReleaseAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $workstreamOwner;
    private User $stakeholder;
    private User $unrelatedUser;
    private Workstream $workstream;
    private Release $release;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->user = User::factory()->create();
        $this->workstreamOwner = User::factory()->create();
        $this->stakeholder = User::factory()->create();
        $this->unrelatedUser = User::factory()->create();

        // Create workstream and release
        $this->workstream = Workstream::factory()->create([
            'name' => 'Test Workstream',
            'owner_id' => $this->workstreamOwner->id,
        ]);

        $this->release = Release::factory()->create([
            'name' => 'Test Release',
            'workstream_id' => $this->workstream->id,
            'target_date' => now()->addMonth(),
            'status' => 'planning',
        ]);
    }

    /** @test */
    public function test_unauthenticated_user_cannot_access_release_endpoints()
    {
        // When: Unauthenticated user tries to access release endpoints
        $response = $this->getJson('/api/releases');

        // Then: Should receive 401 Unauthorized
        $response->assertStatus(401);
    }

    /** @test */
    public function test_authenticated_user_can_view_releases_list()
    {
        // Given: An authenticated user
        // When: They try to view releases list
        $response = $this->actingAs($this->user)->getJson('/api/releases');

        // Then: Should receive success (they can see releases they have access to)
        $response->assertStatus(200);
    }

    /** @test */
    public function test_workstream_owner_can_view_releases_in_their_workstream()
    {
        // Given: A workstream owner
        // When: They try to view a release in their workstream
        $response = $this->actingAs($this->workstreamOwner)
            ->getJson("/api/releases/{$this->release->id}");

        // Then: Should receive success
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $this->release->id,
                    'name' => $this->release->name,
                ]
            ]);
    }

    /** @test */
    public function test_user_without_access_cannot_view_release()
    {
        // Given: A user without access to the workstream or release
        // When: They try to view the release
        $response = $this->actingAs($this->unrelatedUser)
            ->getJson("/api/releases/{$this->release->id}");

        // Then: Should receive 403 Forbidden
        $response->assertStatus(403);
    }

    /** @test */
    public function test_user_with_workstream_permission_can_view_release()
    {
        // Given: A user with view permission on the workstream
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->workstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'view',
            'scope' => 'workstream_only',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        // When: They try to view the release
        $response = $this->actingAs($this->user)
            ->getJson("/api/releases/{$this->release->id}");

        // Then: Should receive success
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $this->release->id,
                ]
            ]);
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
        $response = $this->actingAs($this->stakeholder)
            ->getJson("/api/releases/{$this->release->id}");

        // Then: Should receive success
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $this->release->id,
                ]
            ]);
    }

    /** @test */
    public function test_workstream_owner_can_create_release()
    {
        // Given: A workstream owner
        $releaseData = [
            'name' => 'New Release',
            'description' => 'Test release description',
            'workstream_id' => $this->workstream->id,
            'target_date' => now()->addMonths(2)->format('Y-m-d'),
            'status' => 'planning',
        ];

        // When: They try to create a release
        $response = $this->actingAs($this->workstreamOwner)
            ->postJson('/api/releases', $releaseData);

        // Then: Should receive success
        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'name' => 'New Release',
                    'workstream_id' => $this->workstream->id,
                ]
            ]);
    }

    /** @test */
    public function test_user_with_workstream_edit_permission_can_create_release()
    {
        // Given: A user with edit permission on the workstream
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->workstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'edit',
            'scope' => 'workstream_only',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        $releaseData = [
            'name' => 'User Created Release',
            'workstream_id' => $this->workstream->id,
            'target_date' => now()->addMonths(2)->format('Y-m-d'),
            'status' => 'planning',
        ];

        // When: They try to create a release
        $response = $this->actingAs($this->user)
            ->postJson('/api/releases', $releaseData);

        // Then: Should receive success
        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'name' => 'User Created Release',
                ]
            ]);
    }

    /** @test */
    public function test_user_without_workstream_edit_permission_cannot_create_release()
    {
        // Given: A user with only view permission on the workstream
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->workstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'view',
            'scope' => 'workstream_only',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        $releaseData = [
            'name' => 'Unauthorized Release',
            'workstream_id' => $this->workstream->id,
            'target_date' => now()->addMonths(2)->format('Y-m-d'),
            'status' => 'planning',
        ];

        // When: They try to create a release
        $response = $this->actingAs($this->user)
            ->postJson('/api/releases', $releaseData);

        // Then: Should receive 403 Forbidden
        $response->assertStatus(403);
    }

    /** @test */
    public function test_user_cannot_create_release_in_workstream_without_access()
    {
        // Given: A user without access to the workstream
        $releaseData = [
            'name' => 'Unauthorized Release',
            'workstream_id' => $this->workstream->id,
            'target_date' => now()->addMonths(2)->format('Y-m-d'),
            'status' => 'planning',
        ];

        // When: They try to create a release
        $response = $this->actingAs($this->unrelatedUser)
            ->postJson('/api/releases', $releaseData);

        // Then: Should receive 403 Forbidden
        $response->assertStatus(403);
    }

    /** @test */
    public function test_workstream_owner_can_update_release()
    {
        // Given: A workstream owner
        $updateData = [
            'name' => 'Updated Release Name',
            'description' => 'Updated description',
            'status' => 'in_progress',
        ];

        // When: They try to update the release
        $response = $this->actingAs($this->workstreamOwner)
            ->putJson("/api/releases/{$this->release->id}", $updateData);

        // Then: Should receive success
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'name' => 'Updated Release Name',
                    'status' => 'in_progress',
                ]
            ]);
    }

    /** @test */
    public function test_user_with_workstream_edit_permission_can_update_release()
    {
        // Given: A user with edit permission on the workstream
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->workstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'edit',
            'scope' => 'workstream_only',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        $updateData = [
            'name' => 'User Updated Release',
        ];

        // When: They try to update the release
        $response = $this->actingAs($this->user)
            ->putJson("/api/releases/{$this->release->id}", $updateData);

        // Then: Should receive success
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'name' => 'User Updated Release',
                ]
            ]);
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

        $updateData = [
            'name' => 'Stakeholder Updated Release',
        ];

        // When: They try to update the release
        $response = $this->actingAs($this->stakeholder)
            ->putJson("/api/releases/{$this->release->id}", $updateData);

        // Then: Should receive success
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'name' => 'Stakeholder Updated Release',
                ]
            ]);
    }

    /** @test */
    public function test_release_stakeholder_with_viewer_role_cannot_update_release()
    {
        // Given: A user who is a viewer stakeholder on the release
        StakeholderRelease::factory()->create([
            'release_id' => $this->release->id,
            'user_id' => $this->stakeholder->id,
            'role' => 'viewer',
        ]);

        $updateData = [
            'name' => 'Unauthorized Update',
        ];

        // When: They try to update the release
        $response = $this->actingAs($this->stakeholder)
            ->putJson("/api/releases/{$this->release->id}", $updateData);

        // Then: Should receive 403 Forbidden
        $response->assertStatus(403);
    }

    /** @test */
    public function test_workstream_owner_can_delete_release()
    {
        // Given: A workstream owner
        // When: They try to delete the release
        $response = $this->actingAs($this->workstreamOwner)
            ->deleteJson("/api/releases/{$this->release->id}");

        // Then: Should receive success
        $response->assertStatus(204);
    }

    /** @test */
    public function test_stakeholder_cannot_delete_release()
    {
        // Given: A user who is an approver stakeholder (highest stakeholder role)
        StakeholderRelease::factory()->create([
            'release_id' => $this->release->id,
            'user_id' => $this->stakeholder->id,
            'role' => 'approver',
        ]);

        // When: They try to delete the release
        $response = $this->actingAs($this->stakeholder)
            ->deleteJson("/api/releases/{$this->release->id}");

        // Then: Should receive 403 Forbidden (only workstream owners can delete)
        $response->assertStatus(403);
    }

    /** @test */
    public function test_user_can_manage_stakeholders_if_they_can_update_release()
    {
        // Given: A workstream owner (who can update releases)
        $stakeholderData = [
            'user_id' => $this->user->id,
            'role' => 'reviewer',
        ];

        // When: They try to add a stakeholder to the release
        $response = $this->actingAs($this->workstreamOwner)
            ->postJson("/api/releases/{$this->release->id}/stakeholders", $stakeholderData);

        // Then: Should receive success
        $response->assertStatus(201);
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

        $stakeholderData = [
            'user_id' => $this->user->id,
            'role' => 'reviewer',
        ];

        // When: They try to add a stakeholder to the release
        $response = $this->actingAs($this->stakeholder)
            ->postJson("/api/releases/{$this->release->id}/stakeholders", $stakeholderData);

        // Then: Should receive 403 Forbidden
        $response->assertStatus(403);
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

        // And: A user with inherited permission on the parent workstream
        WorkstreamPermission::factory()->create([
            'workstream_id' => $parentWorkstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'view',
            'scope' => 'workstream_and_children',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        // When: They try to view the release in the child workstream
        $response = $this->actingAs($this->user)
            ->getJson("/api/releases/{$childRelease->id}");

        // Then: Should receive success through inheritance
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $childRelease->id,
                ]
            ]);
    }

    /** @test */
    public function test_release_search_respects_permissions()
    {
        // Given: Multiple releases, user has access to one via workstream permission
        $accessibleRelease = Release::factory()->create([
            'name' => 'Accessible Release',
            'workstream_id' => $this->workstream->id,
        ]);

        $inaccessibleWorkstream = Workstream::factory()->create([
            'owner_id' => $this->unrelatedUser->id,
        ]);

        $inaccessibleRelease = Release::factory()->create([
            'name' => 'Inaccessible Release',
            'workstream_id' => $inaccessibleWorkstream->id,
        ]);

        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->workstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'view',
            'scope' => 'workstream_only',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        // When: They search for releases
        $response = $this->actingAs($this->user)
            ->getJson('/api/releases/search?q=Release');

        // Then: Should only see releases they have access to
        $response->assertStatus(200);
        $responseData = $response->json();

        $foundIds = collect($responseData['data'])->pluck('id')->toArray();
        $this->assertContains($accessibleRelease->id, $foundIds);
        $this->assertNotContains($inaccessibleRelease->id, $foundIds);
    }

    /** @test */
    public function test_release_status_updates_respect_business_rules_and_permissions()
    {
        // Given: A release with specific status and an approver stakeholder
        $this->release->update(['status' => 'pending_approval']);

        StakeholderRelease::factory()->create([
            'release_id' => $this->release->id,
            'user_id' => $this->stakeholder->id,
            'role' => 'approver',
        ]);

        // When: The approver tries to approve the release
        $response = $this->actingAs($this->stakeholder)
            ->putJson("/api/releases/{$this->release->id}/status", [
                'status' => 'approved',
                'action' => 'approve',
            ]);

        // Then: Should receive success
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'status' => 'approved',
                ]
            ]);
    }

    /** @test */
    public function test_bulk_release_operations_respect_individual_permissions()
    {
        // Given: Multiple releases with different access levels
        $accessibleRelease = Release::factory()->create([
            'workstream_id' => $this->workstream->id,
        ]);

        $inaccessibleWorkstream = Workstream::factory()->create([
            'owner_id' => $this->unrelatedUser->id,
        ]);

        $inaccessibleRelease = Release::factory()->create([
            'workstream_id' => $inaccessibleWorkstream->id,
        ]);

        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->workstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'view',
            'scope' => 'workstream_only',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        // When: They try to bulk fetch releases
        $response = $this->actingAs($this->user)
            ->postJson('/api/releases/bulk', [
                'release_ids' => [$accessibleRelease->id, $inaccessibleRelease->id]
            ]);

        // Then: Should only receive releases they have access to
        $response->assertStatus(200);
        $responseData = $response->json();

        $this->assertCount(1, $responseData['data']);
        $this->assertEquals($accessibleRelease->id, $responseData['data'][0]['id']);
    }

    /** @test */
    public function test_release_analytics_respect_permissions()
    {
        // Given: A user with access to a workstream
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->workstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'view',
            'scope' => 'workstream_only',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        // When: They try to view release analytics
        $response = $this->actingAs($this->user)
            ->getJson("/api/releases/{$this->release->id}/analytics");

        // Then: Should receive success with analytics data
        $response->assertStatus(200);
    }

    /** @test */
    public function test_user_without_access_cannot_view_release_analytics()
    {
        // Given: A user without access to the release
        // When: They try to view release analytics
        $response = $this->actingAs($this->unrelatedUser)
            ->getJson("/api/releases/{$this->release->id}/analytics");

        // Then: Should receive 403 Forbidden
        $response->assertStatus(403);
    }
}
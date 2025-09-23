<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use App\Models\Workstream;
use App\Models\Release;
use App\Models\Content;
use App\Models\WorkstreamPermission;
use App\Models\StakeholderRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleBasedPermissionTest extends TestCase
{
    use RefreshDatabase;

    private User $regularUser;
    private User $projectManager;
    private User $productManager;
    private User $stakeholderUser;
    private User $adminUser;
    private User $systemUser;
    private User $workstreamOwner;
    private Workstream $workstream;
    private Release $release;
    private Content $content;

    protected function setUp(): void
    {
        parent::setUp();

        // Create users with different role contexts
        $this->regularUser = User::factory()->create(['name' => 'Regular User']);
        $this->projectManager = User::factory()->create(['name' => 'Project Manager']);
        $this->productManager = User::factory()->create(['name' => 'Product Manager']);
        $this->stakeholderUser = User::factory()->create(['name' => 'Stakeholder User']);
        $this->adminUser = User::factory()->create(['name' => 'Admin User', 'email' => 'admin@releaseit.com']);
        $this->systemUser = User::factory()->create(['name' => 'System User', 'email' => 'system@releaseit.com']);
        $this->workstreamOwner = User::factory()->create(['name' => 'Workstream Owner']);

        // Create test resources
        $this->workstream = Workstream::factory()->create([
            'name' => 'Test Workstream',
            'owner_id' => $this->workstreamOwner->id,
        ]);

        $this->release = Release::factory()->create([
            'name' => 'Test Release',
            'workstream_id' => $this->workstream->id,
        ]);

        $this->content = Content::factory()->create([
            'user_id' => $this->productManager->id,
            'type' => 'brain_dump',
            'title' => 'Test Content',
        ]);
    }

    /** @test */
    public function test_regular_user_role_has_basic_access_permissions()
    {
        // Given: A regular user with no special permissions
        // When: They try to access basic functionality
        $workstreamsResponse = $this->actingAs($this->regularUser)
            ->getJson('/api/workstreams');

        $releasesResponse = $this->actingAs($this->regularUser)
            ->getJson('/api/releases');

        $profileResponse = $this->actingAs($this->regularUser)
            ->getJson('/api/profile');

        // Then: Should have access to their own data and basic endpoints
        $workstreamsResponse->assertStatus(200); // Can view workstreams they have access to
        $releasesResponse->assertStatus(200); // Can view releases they have access to
        $profileResponse->assertStatus(200); // Can view their own profile
    }

    /** @test */
    public function test_project_manager_role_can_manage_project_resources()
    {
        // Given: A project manager with edit permission on a workstream
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->workstream->id,
            'user_id' => $this->projectManager->id,
            'permission_type' => 'edit',
            'scope' => 'workstream_and_children',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        // When: They try to manage project resources
        $viewWorkstreamResponse = $this->actingAs($this->projectManager)
            ->getJson("/api/workstreams/{$this->workstream->id}");

        $updateWorkstreamResponse = $this->actingAs($this->projectManager)
            ->putJson("/api/workstreams/{$this->workstream->id}", [
                'name' => 'PM Updated Workstream',
            ]);

        $createReleaseResponse = $this->actingAs($this->projectManager)
            ->postJson('/api/releases', [
                'name' => 'PM Created Release',
                'workstream_id' => $this->workstream->id,
                'target_date' => now()->addMonth()->format('Y-m-d'),
                'status' => 'planning',
            ]);

        $manageStakeholdersResponse = $this->actingAs($this->projectManager)
            ->postJson("/api/releases/{$this->release->id}/stakeholders", [
                'user_id' => $this->stakeholderUser->id,
                'role' => 'reviewer',
            ]);

        // Then: Should have comprehensive project management capabilities
        $viewWorkstreamResponse->assertStatus(200);
        $updateWorkstreamResponse->assertStatus(200);
        $createReleaseResponse->assertStatus(201);
        $manageStakeholdersResponse->assertStatus(201);
    }

    /** @test */
    public function test_product_manager_role_has_strategic_oversight_permissions()
    {
        // Given: A product manager who is a workstream owner
        $pmWorkstream = Workstream::factory()->create([
            'name' => 'PM Workstream',
            'owner_id' => $this->productManager->id,
        ]);

        $pmRelease = Release::factory()->create([
            'workstream_id' => $pmWorkstream->id,
        ]);

        // When: They try to perform strategic oversight activities
        $createWorkstreamResponse = $this->actingAs($this->productManager)
            ->postJson('/api/workstreams', [
                'name' => 'PM Strategic Initiative',
                'type' => Workstream::TYPE_PRODUCT_LINE,
                'status' => Workstream::STATUS_ACTIVE,
            ]);

        $grantPermissionsResponse = $this->actingAs($this->productManager)
            ->postJson("/api/workstreams/{$pmWorkstream->id}/permissions", [
                'user_id' => $this->projectManager->id,
                'permission_type' => 'edit',
                'scope' => 'workstream_and_children',
            ]);

        $viewAnalyticsResponse = $this->actingAs($this->productManager)
            ->getJson("/api/workstreams/{$pmWorkstream->id}/analytics");

        $deleteReleaseResponse = $this->actingAs($this->productManager)
            ->deleteJson("/api/releases/{$pmRelease->id}");

        // Then: Should have comprehensive ownership and strategic capabilities
        $createWorkstreamResponse->assertStatus(201);
        $grantPermissionsResponse->assertStatus(201);
        $viewAnalyticsResponse->assertStatus(200);
        $deleteReleaseResponse->assertStatus(204);
    }

    /** @test */
    public function test_stakeholder_role_has_appropriate_release_access()
    {
        // Given: A stakeholder user with different roles on different releases
        $viewerRelease = Release::factory()->create([
            'workstream_id' => $this->workstream->id,
        ]);

        $reviewerRelease = Release::factory()->create([
            'workstream_id' => $this->workstream->id,
        ]);

        $approverRelease = Release::factory()->create([
            'workstream_id' => $this->workstream->id,
        ]);

        StakeholderRelease::factory()->create([
            'release_id' => $viewerRelease->id,
            'user_id' => $this->stakeholderUser->id,
            'role' => 'viewer',
        ]);

        StakeholderRelease::factory()->create([
            'release_id' => $reviewerRelease->id,
            'user_id' => $this->stakeholderUser->id,
            'role' => 'reviewer',
        ]);

        StakeholderRelease::factory()->create([
            'release_id' => $approverRelease->id,
            'user_id' => $this->stakeholderUser->id,
            'role' => 'approver',
        ]);

        // When: They try to access releases with different roles
        $viewViewerReleaseResponse = $this->actingAs($this->stakeholderUser)
            ->getJson("/api/releases/{$viewerRelease->id}");

        $updateViewerReleaseResponse = $this->actingAs($this->stakeholderUser)
            ->putJson("/api/releases/{$viewerRelease->id}", ['name' => 'Updated']);

        $viewReviewerReleaseResponse = $this->actingAs($this->stakeholderUser)
            ->getJson("/api/releases/{$reviewerRelease->id}");

        $updateReviewerReleaseResponse = $this->actingAs($this->stakeholderUser)
            ->putJson("/api/releases/{$reviewerRelease->id}", ['name' => 'Updated']);

        $viewApproverReleaseResponse = $this->actingAs($this->stakeholderUser)
            ->getJson("/api/releases/{$approverRelease->id}");

        $updateApproverReleaseResponse = $this->actingAs($this->stakeholderUser)
            ->putJson("/api/releases/{$approverRelease->id}", ['name' => 'Updated']);

        // Then: Should have appropriate access based on stakeholder role
        $viewViewerReleaseResponse->assertStatus(200);
        $updateViewerReleaseResponse->assertStatus(403); // Viewers can't update

        $viewReviewerReleaseResponse->assertStatus(200);
        $updateReviewerReleaseResponse->assertStatus(403); // Reviewers can't update

        $viewApproverReleaseResponse->assertStatus(200);
        $updateApproverReleaseResponse->assertStatus(200); // Approvers can update
    }

    /** @test */
    public function test_admin_user_has_system_wide_access()
    {
        // Given: An admin user
        // When: They try to access various system resources
        $viewAnyWorkstreamResponse = $this->actingAs($this->adminUser)
            ->getJson("/api/workstreams/{$this->workstream->id}");

        $updateAnyWorkstreamResponse = $this->actingAs($this->adminUser)
            ->putJson("/api/workstreams/{$this->workstream->id}", [
                'name' => 'Admin Updated Workstream',
            ]);

        $viewAnyUserProfileResponse = $this->actingAs($this->adminUser)
            ->getJson("/api/users/{$this->regularUser->id}/profile");

        $manageUserResponse = $this->actingAs($this->adminUser)
            ->putJson("/api/users/{$this->regularUser->id}", [
                'name' => 'Admin Updated User',
            ]);

        $systemAnalyticsResponse = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/analytics');

        // Then: Should have comprehensive system access
        $viewAnyWorkstreamResponse->assertStatus(200);
        $updateAnyWorkstreamResponse->assertStatus(200);
        $viewAnyUserProfileResponse->assertStatus(200);
        $manageUserResponse->assertStatus(200);
        $systemAnalyticsResponse->assertStatus(200);
    }

    /** @test */
    public function test_system_user_has_data_access_for_learning()
    {
        // Given: A system user (for AI/learning purposes)
        // When: They try to access data for learning
        $aggregatedFeedbackResponse = $this->actingAs($this->systemUser)
            ->getJson('/api/feedback/aggregated');

        $learningDataResponse = $this->actingAs($this->systemUser)
            ->getJson('/api/learning/data-export');

        $systemStatsResponse = $this->actingAs($this->systemUser)
            ->getJson('/api/system/statistics');

        // Then: Should have access to aggregated/anonymized data
        $aggregatedFeedbackResponse->assertStatus(200);
        $learningDataResponse->assertStatus(200);
        $systemStatsResponse->assertStatus(200);
    }

    /** @test */
    public function test_workstream_owner_has_full_ownership_rights()
    {
        // Given: A workstream owner
        // When: They try to exercise ownership rights
        $viewOwnWorkstreamResponse = $this->actingAs($this->workstreamOwner)
            ->getJson("/api/workstreams/{$this->workstream->id}");

        $updateOwnWorkstreamResponse = $this->actingAs($this->workstreamOwner)
            ->putJson("/api/workstreams/{$this->workstream->id}", [
                'name' => 'Owner Updated Workstream',
            ]);

        $grantPermissionsResponse = $this->actingAs($this->workstreamOwner)
            ->postJson("/api/workstreams/{$this->workstream->id}/permissions", [
                'user_id' => $this->regularUser->id,
                'permission_type' => 'view',
                'scope' => 'workstream_only',
            ]);

        $revokePermissionsResponse = $this->actingAs($this->workstreamOwner)
            ->deleteJson("/api/workstreams/{$this->workstream->id}/permissions/{$this->regularUser->id}");

        $transferOwnershipResponse = $this->actingAs($this->workstreamOwner)
            ->putJson("/api/workstreams/{$this->workstream->id}/transfer-ownership", [
                'new_owner_id' => $this->productManager->id,
            ]);

        // Then: Should have complete ownership control
        $viewOwnWorkstreamResponse->assertStatus(200);
        $updateOwnWorkstreamResponse->assertStatus(200);
        $grantPermissionsResponse->assertStatus(201);
        $revokePermissionsResponse->assertStatus(204);
        $transferOwnershipResponse->assertStatus(200);
    }

    /** @test */
    public function test_role_permissions_are_enforced_across_content_access()
    {
        // Given: Content associated with a workstream
        $this->content->workstreams()->attach($this->workstream->id, [
            'relevance_type' => 'mentioned',
            'confidence_score' => 0.85,
        ]);

        // And: Different users with different workstream permissions
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->workstream->id,
            'user_id' => $this->projectManager->id,
            'permission_type' => 'view',
            'scope' => 'workstream_only',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        // When: Different role types try to access the content
        $contentOwnerResponse = $this->actingAs($this->productManager)
            ->getJson("/api/content/{$this->content->id}");

        $workstreamMemberResponse = $this->actingAs($this->projectManager)
            ->getJson("/api/content/{$this->content->id}");

        $unrelatedUserResponse = $this->actingAs($this->regularUser)
            ->getJson("/api/content/{$this->content->id}");

        $adminResponse = $this->actingAs($this->adminUser)
            ->getJson("/api/content/{$this->content->id}");

        // Then: Access should be granted based on roles and permissions
        $contentOwnerResponse->assertStatus(200); // Content owner can access
        $workstreamMemberResponse->assertStatus(200); // Workstream member can access
        $unrelatedUserResponse->assertStatus(403); // Unrelated user cannot access
        $adminResponse->assertStatus(200); // Admin can access
    }

    /** @test */
    public function test_role_based_bulk_operations_respect_individual_permissions()
    {
        // Given: Multiple workstreams with different ownership
        $pmWorkstream = Workstream::factory()->create([
            'owner_id' => $this->productManager->id,
        ]);

        $ownerWorkstream = Workstream::factory()->create([
            'owner_id' => $this->workstreamOwner->id,
        ]);

        // And: Project manager with permission on one workstream
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->workstream->id,
            'user_id' => $this->projectManager->id,
            'permission_type' => 'edit',
            'scope' => 'workstream_only',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        // When: Project manager tries bulk operations
        $bulkUpdateResponse = $this->actingAs($this->projectManager)
            ->putJson('/api/workstreams/bulk-update', [
                'workstream_ids' => [
                    $this->workstream->id,
                    $pmWorkstream->id,
                    $ownerWorkstream->id,
                ],
                'updates' => [
                    'status' => 'active',
                ],
            ]);

        // Then: Should only affect workstreams they have permission to modify
        $bulkUpdateResponse->assertStatus(207); // Multi-status response
        $responseData = $bulkUpdateResponse->json();

        // Should have results for each workstream with appropriate status codes
        $this->assertArrayHasKey('results', $responseData);
        $results = $responseData['results'];

        // Find results by workstream ID and check status codes
        $accessibleResults = array_filter($results, function ($result) {
            return in_array($result['status_code'], [200, 204]);
        });

        $forbiddenResults = array_filter($results, function ($result) {
            return $result['status_code'] === 403;
        });

        $this->assertGreaterThan(0, count($accessibleResults));
        $this->assertGreaterThan(0, count($forbiddenResults));
    }

    /** @test */
    public function test_role_hierarchy_permissions_cascade_appropriately()
    {
        // Given: A hierarchy with different ownership levels
        $parentWorkstream = Workstream::factory()->create([
            'owner_id' => $this->productManager->id, // Product manager owns parent
        ]);

        $childWorkstream = Workstream::factory()->create([
            'parent_workstream_id' => $parentWorkstream->id,
            'owner_id' => $this->projectManager->id, // Project manager owns child
        ]);

        $grandchildWorkstream = Workstream::factory()->create([
            'parent_workstream_id' => $childWorkstream->id,
            'owner_id' => $this->regularUser->id, // Regular user owns grandchild
        ]);

        // When: Each role tries to access workstreams at different levels
        $pmAccessParentResponse = $this->actingAs($this->productManager)
            ->getJson("/api/workstreams/{$parentWorkstream->id}");

        $pmAccessChildResponse = $this->actingAs($this->productManager)
            ->getJson("/api/workstreams/{$childWorkstream->id}");

        $projMgrAccessChildResponse = $this->actingAs($this->projectManager)
            ->getJson("/api/workstreams/{$childWorkstream->id}");

        $projMgrAccessParentResponse = $this->actingAs($this->projectManager)
            ->getJson("/api/workstreams/{$parentWorkstream->id}");

        $userAccessGrandchildResponse = $this->actingAs($this->regularUser)
            ->getJson("/api/workstreams/{$grandchildWorkstream->id}");

        $userAccessParentResponse = $this->actingAs($this->regularUser)
            ->getJson("/api/workstreams/{$parentWorkstream->id}");

        // Then: Each should only access what they own (no automatic cascade)
        $pmAccessParentResponse->assertStatus(200); // PM owns parent
        $pmAccessChildResponse->assertStatus(403); // PM doesn't own child

        $projMgrAccessChildResponse->assertStatus(200); // Project manager owns child
        $projMgrAccessParentResponse->assertStatus(403); // Project manager doesn't own parent

        $userAccessGrandchildResponse->assertStatus(200); // User owns grandchild
        $userAccessParentResponse->assertStatus(403); // User doesn't own parent
    }

    /** @test */
    public function test_role_based_search_and_discovery_permissions()
    {
        // Given: Various workstreams with different access levels
        $publicWorkstream = Workstream::factory()->create([
            'name' => 'Public Product Line',
            'owner_id' => $this->productManager->id,
        ]);

        $restrictedWorkstream = Workstream::factory()->create([
            'name' => 'Restricted Initiative',
            'owner_id' => $this->workstreamOwner->id,
        ]);

        // Grant regular user access to public workstream
        WorkstreamPermission::factory()->create([
            'workstream_id' => $publicWorkstream->id,
            'user_id' => $this->regularUser->id,
            'permission_type' => 'view',
            'scope' => 'workstream_only',
            'granted_by' => $this->productManager->id,
        ]);

        // When: Different roles search for workstreams
        $regularUserSearchResponse = $this->actingAs($this->regularUser)
            ->getJson('/api/workstreams/search?q=Product');

        $adminSearchResponse = $this->actingAs($this->adminUser)
            ->getJson('/api/workstreams/search?q=Product');

        $pmSearchResponse = $this->actingAs($this->productManager)
            ->getJson('/api/workstreams/search?q=Product');

        // Then: Search results should respect role-based access
        $regularUserSearchResponse->assertStatus(200);
        $regularUserData = $regularUserSearchResponse->json();
        $regularUserIds = collect($regularUserData['data'])->pluck('id')->toArray();

        $adminSearchResponse->assertStatus(200);
        $adminData = $adminSearchResponse->json();
        $adminIds = collect($adminData['data'])->pluck('id')->toArray();

        $pmSearchResponse->assertStatus(200);
        $pmData = $pmSearchResponse->json();
        $pmIds = collect($pmData['data'])->pluck('id')->toArray();

        // Regular user should only see workstreams they have access to
        $this->assertContains($publicWorkstream->id, $regularUserIds);
        $this->assertNotContains($restrictedWorkstream->id, $regularUserIds);

        // Admin should see everything
        $this->assertContains($publicWorkstream->id, $adminIds);
        $this->assertContains($restrictedWorkstream->id, $adminIds);

        // PM should see their own workstreams
        $this->assertContains($publicWorkstream->id, $pmIds);
    }

    /** @test */
    public function test_role_transitions_maintain_permission_integrity()
    {
        // Given: A user who gets promoted from regular user to project manager
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->workstream->id,
            'user_id' => $this->regularUser->id,
            'permission_type' => 'view',
            'scope' => 'workstream_only',
            'granted_by' => $this->workstreamOwner->id,
        ]);

        // When: They initially have limited access
        $initialResponse = $this->actingAs($this->regularUser)
            ->putJson("/api/workstreams/{$this->workstream->id}", [
                'name' => 'Should Fail Update',
            ]);

        // Then: Should be denied
        $initialResponse->assertStatus(403);

        // When: They get promoted (permission upgraded)
        WorkstreamPermission::where('user_id', $this->regularUser->id)
            ->where('workstream_id', $this->workstream->id)
            ->update([
                'permission_type' => 'edit',
                'scope' => 'workstream_and_children',
            ]);

        $promotedResponse = $this->actingAs($this->regularUser)
            ->putJson("/api/workstreams/{$this->workstream->id}", [
                'name' => 'Promoted User Update',
            ]);

        // Then: Should now have access
        $promotedResponse->assertStatus(200);
    }
}
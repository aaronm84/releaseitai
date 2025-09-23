<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use App\Models\Workstream;
use App\Models\Release;
use App\Models\WorkstreamPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkstreamHierarchyPermissionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $hierarchyOwner;
    private User $unrelatedUser;
    private Workstream $rootWorkstream;
    private Workstream $level1Child;
    private Workstream $level2Child;
    private Workstream $level3Child;
    private Release $rootRelease;
    private Release $level1Release;
    private Release $level2Release;
    private Release $level3Release;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->user = User::factory()->create(['name' => 'Test User']);
        $this->hierarchyOwner = User::factory()->create(['name' => 'Hierarchy Owner']);
        $this->unrelatedUser = User::factory()->create(['name' => 'Unrelated User']);

        // Create a 4-level workstream hierarchy
        $this->rootWorkstream = Workstream::factory()->create([
            'name' => 'Root Product Line',
            'type' => Workstream::TYPE_PRODUCT_LINE,
            'owner_id' => $this->hierarchyOwner->id,
            'parent_workstream_id' => null,
            'hierarchy_depth' => 1,
        ]);

        $this->level1Child = Workstream::factory()->create([
            'name' => 'Level 1 Initiative',
            'type' => Workstream::TYPE_INITIATIVE,
            'owner_id' => $this->hierarchyOwner->id,
            'parent_workstream_id' => $this->rootWorkstream->id,
            'hierarchy_depth' => 2,
        ]);

        $this->level2Child = Workstream::factory()->create([
            'name' => 'Level 2 Initiative',
            'type' => Workstream::TYPE_INITIATIVE,
            'owner_id' => $this->hierarchyOwner->id,
            'parent_workstream_id' => $this->level1Child->id,
            'hierarchy_depth' => 3,
        ]);

        $this->level3Child = Workstream::factory()->create([
            'name' => 'Level 3 Experiment',
            'type' => Workstream::TYPE_EXPERIMENT,
            'owner_id' => $this->hierarchyOwner->id,
            'parent_workstream_id' => $this->level2Child->id,
            'hierarchy_depth' => 4,
        ]);

        // Create releases at each level
        $this->rootRelease = Release::factory()->create([
            'name' => 'Root Release',
            'workstream_id' => $this->rootWorkstream->id,
        ]);

        $this->level1Release = Release::factory()->create([
            'name' => 'Level 1 Release',
            'workstream_id' => $this->level1Child->id,
        ]);

        $this->level2Release = Release::factory()->create([
            'name' => 'Level 2 Release',
            'workstream_id' => $this->level2Child->id,
        ]);

        $this->level3Release = Release::factory()->create([
            'name' => 'Level 3 Release',
            'workstream_id' => $this->level3Child->id,
        ]);
    }

    /** @test */
    public function test_workstream_and_children_permission_grants_access_to_all_descendants()
    {
        // Given: A user with 'workstream_and_children' permission on root workstream
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->rootWorkstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'view',
            'scope' => 'workstream_and_children',
            'granted_by' => $this->hierarchyOwner->id,
        ]);

        // When: They try to access workstreams at all levels
        $rootResponse = $this->actingAs($this->user)
            ->getJson("/api/workstreams/{$this->rootWorkstream->id}");

        $level1Response = $this->actingAs($this->user)
            ->getJson("/api/workstreams/{$this->level1Child->id}");

        $level2Response = $this->actingAs($this->user)
            ->getJson("/api/workstreams/{$this->level2Child->id}");

        $level3Response = $this->actingAs($this->user)
            ->getJson("/api/workstreams/{$this->level3Child->id}");

        // Then: All should be accessible through inheritance
        $rootResponse->assertStatus(200);
        $level1Response->assertStatus(200);
        $level2Response->assertStatus(200);
        $level3Response->assertStatus(200);
    }

    /** @test */
    public function test_workstream_only_permission_does_not_grant_access_to_children()
    {
        // Given: A user with 'workstream_only' permission on root workstream
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->rootWorkstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'view',
            'scope' => 'workstream_only',
            'granted_by' => $this->hierarchyOwner->id,
        ]);

        // When: They try to access workstreams at different levels
        $rootResponse = $this->actingAs($this->user)
            ->getJson("/api/workstreams/{$this->rootWorkstream->id}");

        $level1Response = $this->actingAs($this->user)
            ->getJson("/api/workstreams/{$this->level1Child->id}");

        // Then: Only root should be accessible, children should be denied
        $rootResponse->assertStatus(200);
        $level1Response->assertStatus(403);
    }

    /** @test */
    public function test_inherited_edit_permission_allows_editing_descendant_workstreams()
    {
        // Given: A user with 'edit' permission on root with inheritance
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->rootWorkstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'edit',
            'scope' => 'workstream_and_children',
            'granted_by' => $this->hierarchyOwner->id,
        ]);

        $updateData = [
            'name' => 'Updated Child Workstream',
            'description' => 'Updated through inheritance',
        ];

        // When: They try to update a child workstream
        $response = $this->actingAs($this->user)
            ->putJson("/api/workstreams/{$this->level2Child->id}", $updateData);

        // Then: Should be able to edit through inheritance
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'name' => 'Updated Child Workstream',
                ]
            ]);
    }

    /** @test */
    public function test_inherited_permission_allows_release_access_in_descendant_workstreams()
    {
        // Given: A user with view permission on root workstream with inheritance
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->rootWorkstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'view',
            'scope' => 'workstream_and_children',
            'granted_by' => $this->hierarchyOwner->id,
        ]);

        // When: They try to access releases at different levels
        $rootReleaseResponse = $this->actingAs($this->user)
            ->getJson("/api/releases/{$this->rootRelease->id}");

        $level1ReleaseResponse = $this->actingAs($this->user)
            ->getJson("/api/releases/{$this->level1Release->id}");

        $level3ReleaseResponse = $this->actingAs($this->user)
            ->getJson("/api/releases/{$this->level3Release->id}");

        // Then: All releases should be accessible through inheritance
        $rootReleaseResponse->assertStatus(200);
        $level1ReleaseResponse->assertStatus(200);
        $level3ReleaseResponse->assertStatus(200);
    }

    /** @test */
    public function test_inherited_edit_permission_allows_release_creation_in_descendant_workstreams()
    {
        // Given: A user with edit permission on root workstream with inheritance
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->rootWorkstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'edit',
            'scope' => 'workstream_and_children',
            'granted_by' => $this->hierarchyOwner->id,
        ]);

        $releaseData = [
            'name' => 'Inherited Permission Release',
            'workstream_id' => $this->level2Child->id,
            'target_date' => now()->addMonths(2)->format('Y-m-d'),
            'status' => 'planning',
        ];

        // When: They try to create a release in a descendant workstream
        $response = $this->actingAs($this->user)
            ->postJson('/api/releases', $releaseData);

        // Then: Should be able to create through inheritance
        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'name' => 'Inherited Permission Release',
                    'workstream_id' => $this->level2Child->id,
                ]
            ]);
    }

    /** @test */
    public function test_direct_permission_overrides_inherited_permission()
    {
        // Given: A user with view permission on root (inherited) but edit permission on child (direct)
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->rootWorkstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'view',
            'scope' => 'workstream_and_children',
            'granted_by' => $this->hierarchyOwner->id,
        ]);

        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->level1Child->id,
            'user_id' => $this->user->id,
            'permission_type' => 'edit',
            'scope' => 'workstream_only',
            'granted_by' => $this->hierarchyOwner->id,
        ]);

        // When: They try to edit the child workstream
        $updateData = [
            'name' => 'Direct Permission Update',
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/workstreams/{$this->level1Child->id}", $updateData);

        // Then: Should be able to edit (direct permission overrides inherited)
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'name' => 'Direct Permission Update',
                ]
            ]);
    }

    /** @test */
    public function test_permission_inheritance_respects_hierarchy_depth_limits()
    {
        // Given: A user with permission on a middle-level workstream
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->level1Child->id,
            'user_id' => $this->user->id,
            'permission_type' => 'view',
            'scope' => 'workstream_and_children',
            'granted_by' => $this->hierarchyOwner->id,
        ]);

        // When: They try to access workstreams at different levels
        $rootResponse = $this->actingAs($this->user)
            ->getJson("/api/workstreams/{$this->rootWorkstream->id}");

        $level1Response = $this->actingAs($this->user)
            ->getJson("/api/workstreams/{$this->level1Child->id}");

        $level2Response = $this->actingAs($this->user)
            ->getJson("/api/workstreams/{$this->level2Child->id}");

        $level3Response = $this->actingAs($this->user)
            ->getJson("/api/workstreams/{$this->level3Child->id}");

        // Then: Should only access level1 and below, not parent
        $rootResponse->assertStatus(403); // No access to parent
        $level1Response->assertStatus(200); // Direct access
        $level2Response->assertStatus(200); // Inherited access
        $level3Response->assertStatus(200); // Inherited access
    }

    /** @test */
    public function test_permission_inheritance_works_for_creating_child_workstreams()
    {
        // Given: A user with edit permission on root workstream with inheritance
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->rootWorkstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'edit',
            'scope' => 'workstream_and_children',
            'granted_by' => $this->hierarchyOwner->id,
        ]);

        $childWorkstreamData = [
            'name' => 'New Inherited Child',
            'type' => Workstream::TYPE_EXPERIMENT,
            'parent_workstream_id' => $this->level2Child->id,
            'status' => Workstream::STATUS_ACTIVE,
        ];

        // When: They try to create a child of a descendant workstream
        $response = $this->actingAs($this->user)
            ->postJson('/api/workstreams', $childWorkstreamData);

        // Then: Should be able to create through inheritance
        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'name' => 'New Inherited Child',
                    'parent_workstream_id' => $this->level2Child->id,
                ]
            ]);
    }

    /** @test */
    public function test_hierarchy_view_endpoint_respects_permission_inheritance()
    {
        // Given: A user with permission on a middle-level workstream
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->level1Child->id,
            'user_id' => $this->user->id,
            'permission_type' => 'view',
            'scope' => 'workstream_and_children',
            'granted_by' => $this->hierarchyOwner->id,
        ]);

        // When: They request the hierarchy view from level1
        $response = $this->actingAs($this->user)
            ->getJson("/api/workstreams/{$this->level1Child->id}/hierarchy");

        // Then: Should see level1 and its descendants, but not ancestors
        $response->assertStatus(200);
        $responseData = $response->json();

        $this->assertEquals($this->level1Child->id, $responseData['data']['id']);
        $this->assertArrayHasKey('children', $responseData['data']);
        $this->assertArrayNotHasKey('parent', $responseData['data']); // Should not include parent they can't access
    }

    /** @test */
    public function test_bulk_hierarchy_operations_respect_inheritance()
    {
        // Given: A user with permission on root workstream with inheritance
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->rootWorkstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'view',
            'scope' => 'workstream_and_children',
            'granted_by' => $this->hierarchyOwner->id,
        ]);

        // And: Another workstream hierarchy they don't have access to
        $unrelatedWorkstream = Workstream::factory()->create([
            'owner_id' => $this->unrelatedUser->id,
        ]);

        // When: They try to bulk fetch workstreams including both accessible and inaccessible ones
        $response = $this->actingAs($this->user)
            ->postJson('/api/workstreams/bulk', [
                'workstream_ids' => [
                    $this->rootWorkstream->id,
                    $this->level1Child->id,
                    $this->level2Child->id,
                    $unrelatedWorkstream->id
                ]
            ]);

        // Then: Should only receive workstreams they have access to
        $response->assertStatus(200);
        $responseData = $response->json();

        $returnedIds = collect($responseData['data'])->pluck('id')->toArray();
        $this->assertContains($this->rootWorkstream->id, $returnedIds);
        $this->assertContains($this->level1Child->id, $returnedIds);
        $this->assertContains($this->level2Child->id, $returnedIds);
        $this->assertNotContains($unrelatedWorkstream->id, $returnedIds);
    }

    /** @test */
    public function test_inheritance_stops_at_workstream_without_permission()
    {
        // Given: A complex hierarchy where user has permission only on middle workstream
        $independentRoot = Workstream::factory()->create([
            'name' => 'Independent Root',
            'owner_id' => $this->unrelatedUser->id,
            'hierarchy_depth' => 1,
        ]);

        $independentChild = Workstream::factory()->create([
            'name' => 'Independent Child',
            'parent_workstream_id' => $independentRoot->id,
            'owner_id' => $this->unrelatedUser->id,
            'hierarchy_depth' => 2,
        ]);

        $grantedChild = Workstream::factory()->create([
            'name' => 'Granted Child',
            'parent_workstream_id' => $independentChild->id,
            'owner_id' => $this->unrelatedUser->id,
            'hierarchy_depth' => 3,
        ]);

        $grandChild = Workstream::factory()->create([
            'name' => 'Grand Child',
            'parent_workstream_id' => $grantedChild->id,
            'owner_id' => $this->unrelatedUser->id,
            'hierarchy_depth' => 4,
        ]);

        // User only has permission on the "granted child" level
        WorkstreamPermission::factory()->create([
            'workstream_id' => $grantedChild->id,
            'user_id' => $this->user->id,
            'permission_type' => 'view',
            'scope' => 'workstream_and_children',
            'granted_by' => $this->unrelatedUser->id,
        ]);

        // When: They try to access different levels
        $rootResponse = $this->actingAs($this->user)
            ->getJson("/api/workstreams/{$independentRoot->id}");

        $parentResponse = $this->actingAs($this->user)
            ->getJson("/api/workstreams/{$independentChild->id}");

        $grantedResponse = $this->actingAs($this->user)
            ->getJson("/api/workstreams/{$grantedChild->id}");

        $grandChildResponse = $this->actingAs($this->user)
            ->getJson("/api/workstreams/{$grandChild->id}");

        // Then: Should only access granted workstream and its children
        $rootResponse->assertStatus(403);
        $parentResponse->assertStatus(403);
        $grantedResponse->assertStatus(200);
        $grandChildResponse->assertStatus(200);
    }

    /** @test */
    public function test_permission_inheritance_cascade_affects_search_results()
    {
        // Given: A user with permission on root workstream with inheritance
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->rootWorkstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'view',
            'scope' => 'workstream_and_children',
            'granted_by' => $this->hierarchyOwner->id,
        ]);

        // And: Another hierarchy they don't have access to
        $unrelatedHierarchy = Workstream::factory()->create([
            'name' => 'Level Hidden Workstream',
            'owner_id' => $this->unrelatedUser->id,
        ]);

        // When: They search for workstreams by name
        $response = $this->actingAs($this->user)
            ->getJson('/api/workstreams/search?q=Level');

        // Then: Should find workstreams they have access to through inheritance
        $response->assertStatus(200);
        $responseData = $response->json();

        $foundIds = collect($responseData['data'])->pluck('id')->toArray();

        // Should find their accessible workstreams
        $this->assertContains($this->level1Child->id, $foundIds);
        $this->assertContains($this->level2Child->id, $foundIds);
        $this->assertContains($this->level3Child->id, $foundIds);

        // Should not find workstreams they don't have access to
        $this->assertNotContains($unrelatedHierarchy->id, $foundIds);
    }

    /** @test */
    public function test_permission_inheritance_depth_performance_optimization()
    {
        // Given: A very deep hierarchy (testing performance boundaries)
        $currentParent = $this->level3Child;
        $deepWorkstreams = [];

        // Create additional levels (up to max depth)
        for ($i = 5; $i <= 10; $i++) {
            $deepWorkstream = Workstream::factory()->create([
                'name' => "Deep Level $i",
                'parent_workstream_id' => $currentParent->id,
                'owner_id' => $this->hierarchyOwner->id,
                'hierarchy_depth' => $i,
            ]);
            $deepWorkstreams[] = $deepWorkstream;
            $currentParent = $deepWorkstream;
        }

        // User has permission on root with inheritance
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->rootWorkstream->id,
            'user_id' => $this->user->id,
            'permission_type' => 'view',
            'scope' => 'workstream_and_children',
            'granted_by' => $this->hierarchyOwner->id,
        ]);

        // When: They try to access the deepest workstream
        $deepestWorkstream = end($deepWorkstreams);
        $response = $this->actingAs($this->user)
            ->getJson("/api/workstreams/{$deepestWorkstream->id}");

        // Then: Should still be accessible (testing that inheritance works at depth)
        $response->assertStatus(200);
    }

    /** @test */
    public function test_circular_hierarchy_prevention_maintains_permission_integrity()
    {
        // Given: A user with edit permission on a child workstream
        WorkstreamPermission::factory()->create([
            'workstream_id' => $this->level2Child->id,
            'user_id' => $this->user->id,
            'permission_type' => 'edit',
            'scope' => 'workstream_only',
            'granted_by' => $this->hierarchyOwner->id,
        ]);

        // When: They try to create a circular hierarchy (make root a child of level2)
        $circularData = [
            'parent_workstream_id' => $this->level2Child->id,
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/workstreams/{$this->rootWorkstream->id}", $circularData);

        // Then: Should be denied (both for permission and business rule reasons)
        $response->assertStatus(403); // Permission denied (they can't edit root)
    }
}
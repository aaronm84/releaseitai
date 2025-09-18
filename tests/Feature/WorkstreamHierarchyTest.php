<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workstream;
use App\Models\Release;
use App\Models\ChecklistItem;
use App\Models\ChecklistItemAssignment;
use App\Models\WorkstreamPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class WorkstreamHierarchyTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users with different roles
        $this->productDirector = User::factory()->create(['email' => 'director@example.com']);
        $this->productManager = User::factory()->create(['email' => 'pm@example.com']);
        $this->teamLead = User::factory()->create(['email' => 'lead@example.com']);
        $this->developer = User::factory()->create(['email' => 'dev@example.com']);
        $this->designer = User::factory()->create(['email' => 'design@example.com']);

        // Create parent workstream (product line level)
        $this->parentWorkstream = Workstream::factory()->create([
            'name' => 'Mobile Platform',
            'type' => 'product_line',
            'owner_id' => $this->productDirector->id,
            'parent_workstream_id' => null
        ]);

        // Create child workstreams (initiative level)
        $this->childWorkstream1 = Workstream::factory()->create([
            'name' => 'iOS App',
            'type' => 'initiative',
            'owner_id' => $this->productManager->id,
            'parent_workstream_id' => $this->parentWorkstream->id
        ]);

        $this->childWorkstream2 = Workstream::factory()->create([
            'name' => 'Android App',
            'type' => 'initiative',
            'owner_id' => $this->teamLead->id,
            'parent_workstream_id' => $this->parentWorkstream->id
        ]);

        // Create grandchild workstream (experiment level)
        $this->grandchildWorkstream = Workstream::factory()->create([
            'name' => 'A/B Test: New Onboarding',
            'type' => 'experiment',
            'owner_id' => $this->productManager->id,
            'parent_workstream_id' => $this->childWorkstream1->id
        ]);
    }

    /** @test */
    public function pm_can_create_parent_child_workstream_relationships()
    {
        // Given: A PM wants to create nested workstreams
        $this->actingAs($this->productDirector);

        // When: They create a child workstream under a parent
        $response = $this->postJson("/api/workstreams", [
            'name' => 'Web Platform',
            'description' => 'Web-based applications and services',
            'type' => 'initiative',
            'owner_id' => $this->productManager->id,
            'parent_workstream_id' => $this->parentWorkstream->id
        ]);

        // Then: The child workstream should be created successfully
        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'type',
                'parent_workstream_id',
                'owner_id',
                'parent_workstream' => [
                    'id',
                    'name',
                    'type'
                ]
            ]
        ]);

        // And: The database should contain the relationship
        $workstreamId = $response->json('data.id');
        $this->assertDatabaseHas('workstreams', [
            'id' => $workstreamId,
            'parent_workstream_id' => $this->parentWorkstream->id,
            'type' => 'initiative'
        ]);
    }

    /** @test */
    public function workstream_types_are_validated_correctly()
    {
        // Given: A PM trying to create workstreams
        $this->actingAs($this->productDirector);

        // When: They try to create a workstream with invalid type
        $response = $this->postJson("/api/workstreams", [
            'name' => 'Invalid Workstream',
            'type' => 'invalid_type',
            'owner_id' => $this->productManager->id
        ]);

        // Then: The request should be rejected
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('type');

        // And: Valid types should be accepted
        $validTypes = ['product_line', 'initiative', 'experiment'];
        foreach ($validTypes as $type) {
            $response = $this->postJson("/api/workstreams", [
                'name' => "Test {$type} Workstream",
                'type' => $type,
                'owner_id' => $this->productManager->id
            ]);

            $response->assertStatus(201);
        }
    }

    /** @test */
    public function workstream_hierarchy_depth_is_limited()
    {
        // Given: A three-level deep hierarchy already exists
        // Parent -> Child -> Grandchild

        // When: PM tries to create a fourth level
        $this->actingAs($this->productManager);
        $response = $this->postJson("/api/workstreams", [
            'name' => 'Too Deep Workstream',
            'type' => 'experiment',
            'owner_id' => $this->developer->id,
            'parent_workstream_id' => $this->grandchildWorkstream->id
        ]);

        // Then: The request should be rejected
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('parent_workstream_id');
        $response->assertJson([
            'errors' => [
                'parent_workstream_id' => ['Workstream hierarchy cannot exceed 3 levels deep.']
            ]
        ]);
    }

    /** @test */
    public function circular_workstream_relationships_are_prevented()
    {
        // Given: An existing parent-child relationship
        // When: PM tries to make the parent a child of its own child
        $this->actingAs($this->productDirector);
        $response = $this->putJson("/api/workstreams/{$this->parentWorkstream->id}", [
            'parent_workstream_id' => $this->childWorkstream1->id
        ]);

        // Then: The request should be rejected
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('parent_workstream_id');
        $response->assertJson([
            'errors' => [
                'parent_workstream_id' => ['Cannot create circular workstream relationship.']
            ]
        ]);
    }

    /** @test */
    public function permissions_cascade_from_parent_to_child_workstreams()
    {
        // Given: Permissions set on parent workstream
        WorkstreamPermission::create([
            'workstream_id' => $this->parentWorkstream->id,
            'user_id' => $this->developer->id,
            'permission_type' => 'view',
            'scope' => 'workstream_and_children',
            'granted_by' => $this->productDirector->id
        ]);

        WorkstreamPermission::create([
            'workstream_id' => $this->parentWorkstream->id,
            'user_id' => $this->designer->id,
            'permission_type' => 'edit',
            'scope' => 'workstream_and_children',
            'granted_by' => $this->productDirector->id
        ]);

        // When: User checks permissions on child workstream
        $this->actingAs($this->developer);
        $response = $this->getJson("/api/workstreams/{$this->childWorkstream1->id}/permissions");

        // Then: Parent permissions should be inherited
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'user_permissions' => [
                    'workstream_id',
                    'direct_permissions' => [],
                    'inherited_permissions' => [
                        '*' => [
                            'permission_type',
                            'inherited_from_workstream_id',
                            'inherited_from_workstream_name'
                        ]
                    ],
                    'effective_permissions' => []
                ]
            ]
        ]);

        $permissions = $response->json('data.user_permissions');
        $inheritedPerms = collect($permissions['inherited_permissions']);

        $this->assertTrue($inheritedPerms->contains('permission_type', 'view'));
        $this->assertEquals($this->parentWorkstream->id,
            $inheritedPerms->where('permission_type', 'view')->first()['inherited_from_workstream_id']);
    }

    /** @test */
    public function direct_permissions_override_inherited_permissions()
    {
        // Given: User has 'view' permission on parent workstream
        WorkstreamPermission::create([
            'workstream_id' => $this->parentWorkstream->id,
            'user_id' => $this->developer->id,
            'permission_type' => 'view',
            'scope' => 'workstream_and_children',
            'granted_by' => $this->productDirector->id
        ]);

        // And: User has direct 'edit' permission on child workstream
        WorkstreamPermission::create([
            'workstream_id' => $this->childWorkstream1->id,
            'user_id' => $this->developer->id,
            'permission_type' => 'edit',
            'scope' => 'workstream_only',
            'granted_by' => $this->productManager->id
        ]);

        // When: User checks effective permissions
        $this->actingAs($this->developer);
        $response = $this->getJson("/api/workstreams/{$this->childWorkstream1->id}/permissions");

        // Then: Direct permission should be the effective one
        $response->assertStatus(200);
        $permissions = $response->json('data.user_permissions');

        $this->assertNotEmpty($permissions['direct_permissions']);
        $this->assertNotEmpty($permissions['inherited_permissions']);

        // Effective permission should be the higher of direct vs inherited
        $effectivePerms = collect($permissions['effective_permissions']);
        $this->assertTrue($effectivePerms->contains('edit'));
    }

    /** @test */
    public function rollup_reporting_aggregates_tasks_across_child_workstreams()
    {
        // Given: Releases and tasks in child workstreams
        $parentRelease = Release::factory()->create([
            'workstream_id' => $this->parentWorkstream->id,
            'status' => 'planned'
        ]);

        $childRelease1 = Release::factory()->create([
            'workstream_id' => $this->childWorkstream1->id,
            'status' => 'in_progress'
        ]);

        $childRelease2 = Release::factory()->create([
            'workstream_id' => $this->childWorkstream2->id,
            'status' => 'completed'
        ]);

        $grandchildRelease = Release::factory()->create([
            'workstream_id' => $this->grandchildWorkstream->id,
            'status' => 'in_progress'
        ]);

        // Create checklist assignments
        ChecklistItemAssignment::factory()->count(3)->create([
            'release_id' => $childRelease1->id,
            'status' => 'pending'
        ]);

        ChecklistItemAssignment::factory()->count(2)->create([
            'release_id' => $childRelease1->id,
            'status' => 'completed'
        ]);

        ChecklistItemAssignment::factory()->count(4)->create([
            'release_id' => $childRelease2->id,
            'status' => 'completed'
        ]);

        ChecklistItemAssignment::factory()->count(1)->create([
            'release_id' => $grandchildRelease->id,
            'status' => 'pending'
        ]);

        // When: PM requests rollup report for parent workstream
        $this->actingAs($this->productDirector);
        $response = $this->getJson("/api/workstreams/{$this->parentWorkstream->id}/rollup-report");

        // Then: Aggregated data should be returned
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'workstream_id',
                'workstream_name',
                'summary' => [
                    'total_releases',
                    'releases_by_status' => [
                        'planned',
                        'in_progress',
                        'completed'
                    ],
                    'total_tasks',
                    'tasks_by_status' => [
                        'pending',
                        'in_progress',
                        'completed'
                    ],
                    'completion_percentage'
                ],
                'child_workstreams' => [
                    '*' => [
                        'workstream_id',
                        'workstream_name',
                        'type',
                        'releases_count',
                        'tasks_count',
                        'completion_percentage'
                    ]
                ],
                'releases' => [
                    '*' => [
                        'id',
                        'name',
                        'status',
                        'workstream_name',
                        'tasks_count'
                    ]
                ]
            ]
        ]);

        $data = $response->json('data');

        // Verify rollup calculations
        $this->assertEquals(4, $data['summary']['total_releases']); // 1 + 1 + 1 + 1
        $this->assertEquals(10, $data['summary']['total_tasks']); // 3 + 2 + 4 + 1
        $this->assertEquals(1, $data['summary']['releases_by_status']['planned']);
        $this->assertEquals(2, $data['summary']['releases_by_status']['in_progress']);
        $this->assertEquals(1, $data['summary']['releases_by_status']['completed']);
        $this->assertEquals(4, $data['summary']['tasks_by_status']['pending']); // 3 + 1
        $this->assertEquals(6, $data['summary']['tasks_by_status']['completed']); // 2 + 4
    }

    /** @test */
    public function workstream_hierarchy_can_be_queried_efficiently()
    {
        // Given: A complex workstream hierarchy
        // When: PM requests the full hierarchy
        $this->actingAs($this->productDirector);
        $response = $this->getJson("/api/workstreams/{$this->parentWorkstream->id}/hierarchy");

        // Then: Complete hierarchy should be returned
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'type',
                'owner',
                'children' => [
                    '*' => [
                        'id',
                        'name',
                        'type',
                        'owner',
                        'children' => [
                            '*' => [
                                'id',
                                'name',
                                'type',
                                'owner',
                                'children'
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        $hierarchy = $response->json('data');

        // Verify structure
        $this->assertEquals($this->parentWorkstream->id, $hierarchy['id']);
        $this->assertCount(2, $hierarchy['children']); // iOS and Android apps

        // Find iOS app in children
        $iosApp = collect($hierarchy['children'])->where('id', $this->childWorkstream1->id)->first();
        $this->assertNotNull($iosApp);
        $this->assertCount(1, $iosApp['children']); // A/B Test experiment

        // Verify grandchild
        $experiment = $iosApp['children'][0];
        $this->assertEquals($this->grandchildWorkstream->id, $experiment['id']);
        $this->assertEmpty($experiment['children']); // No great-grandchildren
    }

    /** @test */
    public function workstream_access_control_respects_hierarchy()
    {
        // Given: User has permission only on parent workstream
        WorkstreamPermission::create([
            'workstream_id' => $this->parentWorkstream->id,
            'user_id' => $this->developer->id,
            'permission_type' => 'view',
            'scope' => 'workstream_and_children',
            'granted_by' => $this->productDirector->id
        ]);

        // When: User tries to access child workstream
        $this->actingAs($this->developer);
        $response = $this->getJson("/api/workstreams/{$this->childWorkstream1->id}");

        // Then: Access should be granted due to inherited permissions
        $response->assertStatus(200);

        // When: User tries to edit child workstream without edit permission
        $response = $this->putJson("/api/workstreams/{$this->childWorkstream1->id}", [
            'name' => 'Updated Name'
        ]);

        // Then: Access should be denied
        $response->assertStatus(403);
    }

    /** @test */
    public function workstream_deletion_handles_hierarchy_correctly()
    {
        // Given: A workstream with children
        // When: PM tries to delete parent workstream with children
        $this->actingAs($this->productDirector);
        $response = $this->deleteJson("/api/workstreams/{$this->parentWorkstream->id}");

        // Then: Deletion should be prevented
        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'Cannot delete workstream with child workstreams. Move or delete children first.'
        ]);

        // When: PM deletes a leaf workstream (no children)
        $response = $this->deleteJson("/api/workstreams/{$this->grandchildWorkstream->id}");

        // Then: Deletion should succeed
        $response->assertStatus(204);

        // And: Workstream should be removed from database
        $this->assertDatabaseMissing('workstreams', [
            'id' => $this->grandchildWorkstream->id
        ]);
    }

    /** @test */
    public function workstream_move_operations_maintain_valid_hierarchy()
    {
        // Given: An existing workstream hierarchy
        // When: PM moves a workstream to a different parent
        $this->actingAs($this->productDirector);
        $response = $this->putJson("/api/workstreams/{$this->grandchildWorkstream->id}/move", [
            'new_parent_workstream_id' => $this->childWorkstream2->id
        ]);

        // Then: The move should succeed
        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'id' => $this->grandchildWorkstream->id,
                'parent_workstream_id' => $this->childWorkstream2->id
            ]
        ]);

        // And: Database should reflect the change
        $this->assertDatabaseHas('workstreams', [
            'id' => $this->grandchildWorkstream->id,
            'parent_workstream_id' => $this->childWorkstream2->id
        ]);

        // When: PM tries to move to create invalid hierarchy (too deep)
        $deepWorkstream = Workstream::factory()->create([
            'parent_workstream_id' => $this->grandchildWorkstream->id
        ]);

        $response = $this->putJson("/api/workstreams/{$this->parentWorkstream->id}/move", [
            'new_parent_workstream_id' => $deepWorkstream->id
        ]);

        // Then: The move should be rejected
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('new_parent_workstream_id');
    }

    /** @test */
    public function workstream_owners_can_delegate_permissions_to_child_workstreams()
    {
        // Given: A parent workstream owner wants to delegate permissions
        $this->actingAs($this->productDirector);

        // When: They grant permissions on a child workstream
        $response = $this->postJson("/api/workstreams/{$this->childWorkstream1->id}/permissions", [
            'user_id' => $this->developer->id,
            'permission_type' => 'edit',
            'scope' => 'workstream_and_children' // Grant on this workstream and all children
        ]);

        // Then: Permission should be granted
        $response->assertStatus(201);

        // And: User should have access to child and grandchild workstreams
        $this->actingAs($this->developer);

        $childResponse = $this->getJson("/api/workstreams/{$this->childWorkstream1->id}");
        $childResponse->assertStatus(200);

        $grandchildResponse = $this->getJson("/api/workstreams/{$this->grandchildWorkstream->id}");
        $grandchildResponse->assertStatus(200);

        // And: Permission should be recorded with proper scope
        $this->assertDatabaseHas('workstream_permissions', [
            'workstream_id' => $this->childWorkstream1->id,
            'user_id' => $this->developer->id,
            'permission_type' => 'edit',
            'scope' => 'workstream_and_children'
        ]);
    }

    /** @test */
    public function workstream_reporting_can_filter_by_hierarchy_level()
    {
        // Given: Workstreams at different hierarchy levels
        // When: PM requests workstreams filtered by type/level
        $this->actingAs($this->productDirector);

        // Filter by product line level
        $response = $this->getJson("/api/workstreams?type=product_line");
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $this->assertEquals($this->parentWorkstream->id, $response->json('data.0.id'));

        // Filter by initiative level
        $response = $this->getJson("/api/workstreams?type=initiative");
        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');

        // Filter by experiment level
        $response = $this->getJson("/api/workstreams?type=experiment");
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $this->assertEquals($this->grandchildWorkstream->id, $response->json('data.0.id'));
    }

    /** @test */
    public function bulk_operations_can_be_performed_across_workstream_hierarchy()
    {
        // Given: Multiple workstreams in hierarchy
        $allWorkstreamIds = [
            $this->parentWorkstream->id,
            $this->childWorkstream1->id,
            $this->childWorkstream2->id,
            $this->grandchildWorkstream->id
        ];

        // When: PM performs bulk update on workstream hierarchy
        $this->actingAs($this->productDirector);
        $response = $this->putJson("/api/workstreams/bulk-update", [
            'workstream_ids' => $allWorkstreamIds,
            'updates' => [
                'status' => 'active',
                'updated_by' => $this->productDirector->id
            ]
        ]);

        // Then: All workstreams should be updated
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'updated_count',
                'updated_workstreams' => [
                    '*' => [
                        'id',
                        'status'
                    ]
                ]
            ]
        ]);

        $this->assertEquals(4, $response->json('data.updated_count'));

        // And: Database should reflect changes
        foreach ($allWorkstreamIds as $id) {
            $this->assertDatabaseHas('workstreams', [
                'id' => $id,
                'status' => 'active'
            ]);
        }
    }
}
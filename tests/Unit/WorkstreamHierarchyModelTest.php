<?php

namespace Tests\Unit;

use App\Models\Workstream;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkstreamHierarchyModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function workstream_can_have_parent_and_children_relationships()
    {
        // Given: A parent workstream and child workstreams
        $parent = Workstream::factory()->create([
            'name' => 'Parent Workstream',
            'type' => 'product_line'
        ]);

        $child1 = Workstream::factory()->create([
            'name' => 'Child 1',
            'type' => 'initiative',
            'parent_workstream_id' => $parent->id
        ]);

        $child2 = Workstream::factory()->create([
            'name' => 'Child 2',
            'type' => 'initiative',
            'parent_workstream_id' => $parent->id
        ]);

        // When: Accessing relationships
        $parentFromChild = $child1->parentWorkstream;
        $childrenFromParent = $parent->childWorkstreams;

        // Then: Relationships should be properly defined
        $this->assertInstanceOf(Workstream::class, $parentFromChild);
        $this->assertEquals($parent->id, $parentFromChild->id);

        $this->assertCount(2, $childrenFromParent);
        $childIds = $childrenFromParent->pluck('id')->toArray();
        $this->assertContains($child1->id, $childIds);
        $this->assertContains($child2->id, $childIds);
    }

    /** @test */
    public function workstream_validates_type_enum()
    {
        // Given: Valid workstream types
        $validTypes = ['product_line', 'initiative', 'experiment'];

        foreach ($validTypes as $type) {
            // When: Creating with valid type
            $workstream = Workstream::make([
                'name' => "Test {$type}",
                'type' => $type,
                'owner_id' => 1
            ]);

            // Then: Type should be accepted
            $this->assertEquals($type, $workstream->type);
        }
    }

    /** @test */
    public function workstream_can_calculate_hierarchy_depth()
    {
        // Given: A three-level hierarchy
        $level1 = Workstream::factory()->create(['type' => 'product_line']);
        $level2 = Workstream::factory()->create([
            'type' => 'initiative',
            'parent_workstream_id' => $level1->id
        ]);
        $level3 = Workstream::factory()->create([
            'type' => 'experiment',
            'parent_workstream_id' => $level2->id
        ]);

        // When: Calculating depth
        $depth1 = $level1->getHierarchyDepth();
        $depth2 = $level2->getHierarchyDepth();
        $depth3 = $level3->getHierarchyDepth();

        // Then: Depths should be correct
        $this->assertEquals(1, $depth1);
        $this->assertEquals(2, $depth2);
        $this->assertEquals(3, $depth3);
    }

    /** @test */
    public function workstream_can_detect_circular_hierarchy()
    {
        // Given: Two workstreams
        $workstream1 = Workstream::factory()->create();
        $workstream2 = Workstream::factory()->create([
            'parent_workstream_id' => $workstream1->id
        ]);

        // When: Checking for circular relationship
        $wouldCreateCircle = $workstream1->wouldCreateCircularHierarchy($workstream2->id);

        // Then: Circular relationship should be detected
        $this->assertTrue($wouldCreateCircle);

        // And: Valid relationship should not be circular
        $workstream3 = Workstream::factory()->create();
        $wouldNotCreateCircle = $workstream1->wouldCreateCircularHierarchy($workstream3->id);

        $this->assertFalse($wouldNotCreateCircle);
    }

    /** @test */
    public function workstream_can_get_all_ancestors()
    {
        // Given: A three-level hierarchy
        $grandparent = Workstream::factory()->create(['name' => 'Grandparent']);
        $parent = Workstream::factory()->create([
            'name' => 'Parent',
            'parent_workstream_id' => $grandparent->id
        ]);
        $child = Workstream::factory()->create([
            'name' => 'Child',
            'parent_workstream_id' => $parent->id
        ]);

        // When: Getting ancestors
        $ancestors = $child->getAllAncestors();

        // Then: All ancestors should be returned in order
        $this->assertCount(2, $ancestors);
        $this->assertEquals($parent->id, $ancestors[0]->id);
        $this->assertEquals($grandparent->id, $ancestors[1]->id);
    }

    /** @test */
    public function workstream_can_get_all_descendants()
    {
        // Given: A three-level hierarchy with multiple branches
        $parent = Workstream::factory()->create(['name' => 'Parent']);

        $child1 = Workstream::factory()->create([
            'name' => 'Child 1',
            'parent_workstream_id' => $parent->id
        ]);

        $child2 = Workstream::factory()->create([
            'name' => 'Child 2',
            'parent_workstream_id' => $parent->id
        ]);

        $grandchild = Workstream::factory()->create([
            'name' => 'Grandchild',
            'parent_workstream_id' => $child1->id
        ]);

        // When: Getting descendants
        $descendants = $parent->getAllDescendants();

        // Then: All descendants should be returned
        $this->assertCount(3, $descendants);
        $descendantIds = $descendants->pluck('id')->toArray();
        $this->assertContains($child1->id, $descendantIds);
        $this->assertContains($child2->id, $descendantIds);
        $this->assertContains($grandchild->id, $descendantIds);
    }

    /** @test */
    public function workstream_can_check_if_user_has_inherited_permissions()
    {
        // Given: A hierarchy with permissions on parent
        $parent = Workstream::factory()->create();
        $child = Workstream::factory()->create([
            'parent_workstream_id' => $parent->id
        ]);
        $user = User::factory()->create();

        // Create a user who will grant the permission
        $grantor = User::factory()->create();

        // Simulate permission on parent (this would be in WorkstreamPermission model)
        $parent->permissions()->create([
            'user_id' => $user->id,
            'permission_type' => 'view',
            'scope' => 'workstream_and_children',
            'granted_by' => $grantor->id
        ]);

        // When: Checking inherited permissions
        $hasInheritedPermission = $child->userHasInheritedPermission($user->id, 'view');

        // Then: Permission should be inherited
        $this->assertTrue($hasInheritedPermission);
    }

    /** @test */
    public function workstream_can_get_root_workstream()
    {
        // Given: A deep hierarchy
        $root = Workstream::factory()->create(['name' => 'Root']);
        $level2 = Workstream::factory()->create([
            'parent_workstream_id' => $root->id
        ]);
        $level3 = Workstream::factory()->create([
            'parent_workstream_id' => $level2->id
        ]);

        // When: Getting root from any level
        $rootFromLevel3 = $level3->getRootWorkstream();
        $rootFromLevel2 = $level2->getRootWorkstream();
        $rootFromRoot = $root->getRootWorkstream();

        // Then: Root should be correctly identified
        $this->assertEquals($root->id, $rootFromLevel3->id);
        $this->assertEquals($root->id, $rootFromLevel2->id);
        $this->assertEquals($root->id, $rootFromRoot->id);
    }

    /** @test */
    public function workstream_can_build_hierarchy_tree()
    {
        // Given: A complex hierarchy
        $root = Workstream::factory()->create(['name' => 'Root']);

        $branch1 = Workstream::factory()->create([
            'name' => 'Branch 1',
            'parent_workstream_id' => $root->id
        ]);

        $branch2 = Workstream::factory()->create([
            'name' => 'Branch 2',
            'parent_workstream_id' => $root->id
        ]);

        $leaf1 = Workstream::factory()->create([
            'name' => 'Leaf 1',
            'parent_workstream_id' => $branch1->id
        ]);

        // When: Building hierarchy tree
        $tree = $root->buildHierarchyTree();

        // Then: Tree structure should be correct
        $this->assertEquals($root->id, $tree['id']);
        $this->assertCount(2, $tree['children']);

        $branch1Tree = collect($tree['children'])->where('id', $branch1->id)->first();
        $this->assertCount(1, $branch1Tree['children']);
        $this->assertEquals($leaf1->id, $branch1Tree['children'][0]['id']);

        $branch2Tree = collect($tree['children'])->where('id', $branch2->id)->first();
        $this->assertEmpty($branch2Tree['children']);
    }
}
<?php

namespace Tests\Feature\Frontend;

use App\Models\Release;
use App\Models\User;
use App\Models\Workstream;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkstreamManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $pm;

    public function setUp(): void
    {
        parent::setUp();

        $this->pm = User::factory()->create([
            'name' => 'Product Manager',
            'email' => 'pm@example.com'
        ]);
    }

    /** @test */
    public function pm_can_view_workstreams_list_with_hierarchy()
    {
        // Given: A PM with hierarchical workstreams
        $this->actingAs($this->pm);

        $productLine = Workstream::factory()->create([
            'name' => 'Mobile Platform',
            'type' => 'product_line',
            'owner_id' => $this->pm->id,
        ]);

        $initiative = Workstream::factory()->create([
            'name' => 'User Authentication',
            'type' => 'initiative',
            'parent_workstream_id' => $productLine->id,
            'owner_id' => $this->pm->id,
        ]);

        $experiment = Workstream::factory()->create([
            'name' => 'A/B Test: Login Flow',
            'type' => 'experiment',
            'parent_workstream_id' => $initiative->id,
            'owner_id' => $this->pm->id,
        ]);

        // When: They visit the workstreams page
        $response = $this->get('/workstreams');

        // Then: They should see the hierarchical structure
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Workstreams/Index')
                ->has('workstreams', 3)
                ->where('workstreams.0.name', 'Mobile Platform')
                ->where('workstreams.0.type', 'product_line')
                ->where('workstreams.0.hierarchy_depth', 1)
                ->where('workstreams.1.name', 'User Authentication')
                ->where('workstreams.1.type', 'initiative')
                ->where('workstreams.1.hierarchy_depth', 2)
                ->where('workstreams.2.name', 'A/B Test: Login Flow')
                ->where('workstreams.2.type', 'experiment')
                ->where('workstreams.2.hierarchy_depth', 3)
        );
    }

    /** @test */
    public function pm_can_create_new_workstream_with_proper_hierarchy()
    {
        // Given: A PM wants to create a new workstream
        $this->actingAs($this->pm);

        $parentWorkstream = Workstream::factory()->create([
            'name' => 'Web Platform',
            'type' => 'product_line',
            'owner_id' => $this->pm->id,
        ]);

        // When: They create a new initiative under the product line
        $response = $this->post('/workstreams', [
            'name' => 'Payment Integration',
            'description' => 'Integrate with Stripe and PayPal',
            'type' => 'initiative',
            'parent_workstream_id' => $parentWorkstream->id,
            'status' => 'active',
        ]);

        // Then: The workstream should be created with proper hierarchy
        $response->assertRedirect('/workstreams');
        $this->assertDatabaseHas('workstreams', [
            'name' => 'Payment Integration',
            'type' => 'initiative',
            'parent_workstream_id' => $parentWorkstream->id,
            'owner_id' => $this->pm->id,
            'hierarchy_depth' => 2,
        ]);
    }

    /** @test */
    public function pm_can_view_workstream_detail_with_releases_and_metrics()
    {
        // Given: A PM with a workstream containing releases
        $this->actingAs($this->pm);

        $workstream = Workstream::factory()->create([
            'name' => 'Mobile App',
            'type' => 'product_line',
            'owner_id' => $this->pm->id,
        ]);

        $activeRelease = Release::factory()->create([
            'name' => 'v2.1 Features',
            'workstream_id' => $workstream->id,
            'status' => 'in_progress',
            'planned_date' => now()->addDays(7),
        ]);

        $completedRelease = Release::factory()->create([
            'name' => 'v2.0 Bug Fixes',
            'workstream_id' => $workstream->id,
            'status' => 'completed',
            'planned_date' => now()->subDays(14),
        ]);

        // When: They view the workstream detail
        $response = $this->get("/workstreams/{$workstream->id}");

        // Then: They should see comprehensive workstream information
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Workstreams/Show')
                ->where('workstream.name', 'Mobile App')
                ->where('workstream.type', 'product_line')
                ->has('workstream.releases', 2)
                ->where('workstream.releases.0.name', 'v2.1 Features')
                ->where('workstream.releases.0.status', 'in_progress')
                ->where('workstream.releases.1.name', 'v2.0 Bug Fixes')
                ->where('workstream.releases.1.status', 'completed')
                ->has('workstream.metrics')
                ->where('workstream.metrics.total_releases', 2)
                ->where('workstream.metrics.active_releases', 1)
                ->where('workstream.metrics.completed_releases', 1)
        );
    }

    /** @test */
    public function workstream_hierarchy_prevents_circular_references()
    {
        // Given: A PM with existing workstream hierarchy
        $this->actingAs($this->pm);

        $parent = Workstream::factory()->create([
            'name' => 'Parent Workstream',
            'type' => 'product_line',
            'owner_id' => $this->pm->id,
        ]);

        $child = Workstream::factory()->create([
            'name' => 'Child Workstream',
            'type' => 'initiative',
            'parent_workstream_id' => $parent->id,
            'owner_id' => $this->pm->id,
        ]);

        // When: They try to make the parent a child of its own child (circular reference)
        $response = $this->put("/workstreams/{$parent->id}", [
            'name' => 'Parent Workstream',
            'type' => 'product_line',
            'parent_workstream_id' => $child->id,
            'status' => 'active',
        ]);

        // Then: The update should be rejected
        $response->assertSessionHasErrors('parent_workstream_id');
        $this->assertDatabaseMissing('workstreams', [
            'id' => $parent->id,
            'parent_workstream_id' => $child->id,
        ]);
    }

    /** @test */
    public function workstream_list_shows_actionable_insights_for_pms()
    {
        // Given: A PM with workstreams in various states
        $this->actingAs($this->pm);

        $staleWorkstream = Workstream::factory()->create([
            'name' => 'Stale Project',
            'type' => 'initiative',
            'status' => 'active',
            'owner_id' => $this->pm->id,
            'updated_at' => now()->subDays(30),
        ]);

        $activeWorkstream = Workstream::factory()->create([
            'name' => 'Active Project',
            'type' => 'initiative',
            'status' => 'active',
            'owner_id' => $this->pm->id,
        ]);

        // Create releases to show activity
        Release::factory()->create([
            'workstream_id' => $activeWorkstream->id,
            'status' => 'in_progress',
            'planned_date' => now()->addDays(7),
        ]);

        // When: They view the workstreams list
        $response = $this->get('/workstreams');

        // Then: They should see actionable insights
        $response->assertInertia(fn ($page) =>
            $page->has('insights')
                ->where('insights.stale_workstreams_count', 1)
                ->where('insights.active_workstreams_count', 2)
                ->has('insights.recommendations')
        );
    }

    /** @test */
    public function workstream_navigation_is_optimized_for_adhd_users()
    {
        // Given: A PM with multiple workstreams
        $this->actingAs($this->pm);

        Workstream::factory()->count(10)->create(['owner_id' => $this->pm->id]);

        // When: They access workstreams
        $response = $this->get('/workstreams');

        // Then: The interface should be optimized for focus and minimal cognitive load
        $response->assertInertia(fn ($page) =>
            $page->has('uiConfig')
                ->where('uiConfig.groupByType', true) // Grouped for easier scanning
                ->where('uiConfig.showQuickActions', true) // Quick actions available
                ->where('uiConfig.maxItemsPerPage', 20) // Not overwhelming
        );
    }
}
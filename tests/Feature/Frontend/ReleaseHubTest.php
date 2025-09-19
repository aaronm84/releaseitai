<?php

namespace Tests\Feature\Frontend;

use App\Models\Release;
use App\Models\User;
use App\Models\Workstream;
use App\Models\Communication;
use App\Models\ReleaseTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReleaseHubTest extends TestCase
{
    use RefreshDatabase;

    private User $pm;
    private Workstream $workstream;
    private Release $activeRelease;

    public function setUp(): void
    {
        parent::setUp();

        $this->pm = User::factory()->create([
            'name' => 'Product Manager',
            'email' => 'pm@example.com'
        ]);

        $this->workstream = Workstream::factory()->create([
            'name' => 'Mobile App',
            'type' => 'product_line',
            'owner_id' => $this->pm->id,
        ]);

        $this->activeRelease = Release::factory()->create([
            'name' => 'v2.1 Login Flow',
            'workstream_id' => $this->workstream->id,
            'status' => 'in_progress',
            'target_date' => now()->addDays(7),
        ]);
    }

    /** @test */
    public function pm_can_view_release_hub_with_comprehensive_overview()
    {
        // Given: A PM with a release containing tasks and communications
        $this->actingAs($this->pm);

        ReleaseTask::factory()->count(5)->create([
            'release_id' => $this->activeRelease->id,
            'status' => 'pending',
        ]);

        ReleaseTask::factory()->count(3)->create([
            'release_id' => $this->activeRelease->id,
            'status' => 'completed',
        ]);

        Communication::factory()->count(2)->create([
            'release_id' => $this->activeRelease->id,
            'channel' => 'email',
        ]);

        // When: They visit the release hub
        $response = $this->get("/releases/{$this->activeRelease->id}");

        // Then: They should see a comprehensive release overview
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Releases/Hub')
                ->where('release.name', 'v2.1 Login Flow')
                ->where('release.status', 'in_progress')
                ->has('release.tasks', 8)
                ->has('release.communications', 2)
                ->has('release.metrics')
                ->where('release.metrics.total_tasks', 8)
                ->where('release.metrics.completed_tasks', 3)
                ->where('release.metrics.progress_percentage', 38)
                ->has('checklistTemplate')
        );
    }

    /** @test */
    public function release_hub_displays_dynamic_checklist_based_on_release_type()
    {
        // Given: A PM with different types of releases
        $this->actingAs($this->pm);

        $productRelease = Release::factory()->create([
            'name' => 'Product Feature Release',
            'workstream_id' => $this->workstream->id,
            'type' => 'feature',
            'status' => 'planned',
        ]);

        $hotfixRelease = Release::factory()->create([
            'name' => 'Critical Bug Fix',
            'workstream_id' => $this->workstream->id,
            'type' => 'hotfix',
            'status' => 'in_progress',
        ]);

        // When: They view each release type
        $productResponse = $this->get("/releases/{$productRelease->id}");
        $hotfixResponse = $this->get("/releases/{$hotfixRelease->id}");

        // Then: They should see different checklist templates
        $productResponse->assertInertia(fn ($page) =>
            $page->has('checklistTemplate')
                ->where('checklistTemplate.type', 'feature')
                ->has('checklistTemplate.items')
                ->whereContains('checklistTemplate.items.*.name', 'Design Review')
                ->whereContains('checklistTemplate.items.*.name', 'QA Testing')
        );

        $hotfixResponse->assertInertia(fn ($page) =>
            $page->has('checklistTemplate')
                ->where('checklistTemplate.type', 'hotfix')
                ->has('checklistTemplate.items')
                ->whereContains('checklistTemplate.items.*.name', 'Impact Assessment')
                ->whereContains('checklistTemplate.items.*.name', 'Rollback Plan')
        );
    }

    /** @test */
    public function pm_can_add_and_complete_checklist_items_with_auto_save()
    {
        // Given: A PM working on a release
        $this->actingAs($this->pm);

        // When: They add a new checklist item
        $response = $this->post("/releases/{$this->activeRelease->id}/tasks", [
            'name' => 'Security Review',
            'description' => 'Complete security audit',
            'due_date' => now()->addDays(3)->toDateString(),
            'priority' => 'high',
        ]);

        // Then: The item should be created and auto-saved
        $response->assertStatus(201);
        $this->assertDatabaseHas('release_tasks', [
            'release_id' => $this->activeRelease->id,
            'name' => 'Security Review',
            'status' => 'planned',
            'priority' => 'high',
        ]);
    }

    /** @test */
    public function release_hub_shows_stakeholder_communication_tracking()
    {
        // Given: A PM with stakeholder communications
        $this->actingAs($this->pm);

        $stakeholder = User::factory()->create(['name' => 'Engineering Lead']);

        Communication::factory()->create([
            'release_id' => $this->activeRelease->id,
            'channel' => 'email',
            'subject' => 'Release Timeline Update',
            'communication_type' => 'update',
            'initiated_by_user_id' => $this->pm->id,
        ]);

        // When: They view the release hub
        $response = $this->get("/releases/{$this->activeRelease->id}");

        // Then: They should see stakeholder communication status
        $response->assertInertia(fn ($page) =>
            $page->has('stakeholderInsights')
                ->where('stakeholderInsights.total_communications', 1)
                ->has('stakeholderInsights.by_channel')
                ->has('stakeholderInsights.recent_interactions')
        );
    }

    /** @test */
    public function release_hub_provides_quick_status_update_functionality()
    {
        // Given: A PM needs to quickly update release status
        $this->actingAs($this->pm);

        // When: They update the release status
        $response = $this->patch("/releases/{$this->activeRelease->id}/status", [
            'status' => 'on_hold',
            'status_note' => 'Dependency blocker identified',
            'notify_stakeholders' => true,
        ]);

        // Then: The status should be updated with stakeholder notification
        $response->assertStatus(200);
        $this->assertDatabaseHas('releases', [
            'id' => $this->activeRelease->id,
            'status' => 'on_hold',
        ]);

        // Should create a communication record for stakeholder notification
        $this->assertDatabaseHas('communications', [
            'release_id' => $this->activeRelease->id,
            'communication_type' => 'status_update',
            'subject' => 'Release Status Update: v2.1 Login Flow',
        ]);
    }

    /** @test */
    public function release_hub_displays_timeline_with_critical_path_analysis()
    {
        // Given: A PM with a release containing dependent tasks
        $this->actingAs($this->pm);

        $criticalTask = ReleaseTask::factory()->create([
            'release_id' => $this->activeRelease->id,
            'title' => 'API Integration',
            'due_date' => now()->addDays(2),
            'status' => 'in_progress',
            'priority' => 'critical',
        ]);

        $dependentTask = ReleaseTask::factory()->create([
            'release_id' => $this->activeRelease->id,
            'title' => 'Frontend Implementation',
            'due_date' => now()->addDays(5),
            'status' => 'pending',
        ]);

        // When: They view the release hub
        $response = $this->get("/releases/{$this->activeRelease->id}");

        // Then: They should see timeline analysis
        $response->assertInertia(fn ($page) =>
            $page->has('timelineAnalysis')
                ->where('timelineAnalysis.critical_path_days', 7)
                ->where('timelineAnalysis.has_risks', true)
                ->has('timelineAnalysis.critical_tasks')
                ->where('timelineAnalysis.critical_tasks.0.name', 'API Integration')
        );
    }

    /** @test */
    public function release_hub_optimized_for_adhd_quick_scanning()
    {
        // Given: A PM with ADHD using the release hub
        $this->actingAs($this->pm);

        ReleaseTask::factory()->count(15)->create([
            'release_id' => $this->activeRelease->id,
        ]);

        // When: They view the release hub
        $response = $this->get("/releases/{$this->activeRelease->id}");

        // Then: The interface should be optimized for quick scanning
        $response->assertInertia(fn ($page) =>
            $page->has('uiConfig')
                ->where('uiConfig.enableQuickScan', true)
                ->where('uiConfig.groupTasksByStatus', true)
                ->where('uiConfig.showProgressVisuals', true)
                ->where('uiConfig.enableKeyboardShortcuts', true)
                ->has('uiConfig.colorCoding')
        );
    }

    /** @test */
    public function release_hub_shows_blockers_and_risks_prominently()
    {
        // Given: A PM with a release containing blockers
        $this->actingAs($this->pm);

        $blockedTask = ReleaseTask::factory()->create([
            'release_id' => $this->activeRelease->id,
            'title' => 'Database Migration',
            'status' => 'blocked',
            'notes' => 'Waiting for DBA approval',
            'priority' => 'critical',
            'is_blocker' => true,
        ]);

        // When: They view the release hub
        $response = $this->get("/releases/{$this->activeRelease->id}");

        // Then: Blockers should be prominently displayed
        $response->assertInertia(fn ($page) =>
            $page->has('blockers', 1)
                ->where('blockers.0.task_name', 'Database Migration')
                ->where('blockers.0.description', 'Waiting for DBA approval')
                ->where('blockers.0.priority', 'critical')
                ->has('riskAnalysis')
                ->where('riskAnalysis.blocked_tasks_count', 1)
        );
    }

    /** @test */
    public function release_hub_enables_bulk_task_operations()
    {
        // Given: A PM with multiple tasks to update
        $this->actingAs($this->pm);

        $tasks = ReleaseTask::factory()->count(5)->create([
            'release_id' => $this->activeRelease->id,
            'status' => 'pending',
        ]);

        // When: They perform bulk operations
        $response = $this->patch("/releases/{$this->activeRelease->id}/tasks/bulk", [
            'task_ids' => $tasks->pluck('id')->toArray(),
            'operation' => 'update_status',
            'status' => 'in_progress',
        ]);

        // Then: All tasks should be updated
        $response->assertStatus(200);
        foreach ($tasks as $task) {
            $this->assertDatabaseHas('release_tasks', [
                'id' => $task->id,
                'status' => 'in_progress',
            ]);
        }
    }

    /** @test */
    public function release_hub_loads_quickly_with_large_datasets()
    {
        // Given: A PM with a large release dataset
        $this->actingAs($this->pm);

        // Create realistic data volume
        ReleaseTask::factory()->count(50)->create(['release_id' => $this->activeRelease->id]);
        Communication::factory()->count(20)->create(['release_id' => $this->activeRelease->id]);

        $startTime = microtime(true);

        // When: They visit the release hub
        $response = $this->get("/releases/{$this->activeRelease->id}");

        $loadTime = microtime(true) - $startTime;

        // Then: The page should load quickly for ADHD users
        $response->assertStatus(200);
        $this->assertLessThan(1.0, $loadTime, 'Release Hub should load in under 1 second for ADHD users');
    }
}
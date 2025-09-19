<?php

namespace Tests\Feature\Frontend;

use App\Models\Release;
use App\Models\User;
use App\Models\Workstream;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
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
    public function pm_can_access_dashboard_and_see_key_information()
    {
        // Given: A PM with active releases and tasks
        $this->actingAs($this->pm);

        // When: They visit the dashboard
        $response = $this->get('/');

        // Then: They should see the dashboard with essential PM information
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Dashboard/Index')
                ->has('topPriorities')
                ->has('workstreams')
                ->has('user')
                ->where('user.name', 'Product Manager')
        );
    }

    /** @test */
    public function dashboard_displays_top_3_priorities_based_on_urgency()
    {
        // Given: A PM with multiple tasks and releases
        $this->actingAs($this->pm);

        // Create releases with different priority levels
        $urgentRelease = Release::factory()->create([
            'name' => 'Critical Bug Fix',
            'workstream_id' => $this->workstream->id,
            'status' => 'in_progress',
            'target_date' => now()->addDays(1), // Due soon
        ]);

        $mediumRelease = Release::factory()->create([
            'name' => 'Feature Enhancement',
            'workstream_id' => $this->workstream->id,
            'status' => 'planned',
            'target_date' => now()->addDays(14),
        ]);

        // When: They visit the dashboard
        $response = $this->get('/');

        // Then: They should see top 3 priorities ordered by urgency
        $response->assertInertia(fn ($page) =>
            $page->has('topPriorities', 3)
                ->where('topPriorities.0.name', 'Critical Bug Fix')
                ->where('topPriorities.0.due_in_days', 1)
                ->where('topPriorities.1.name', 'v2.1 Login Flow')
                ->where('topPriorities.2.name', 'Feature Enhancement')
        );
    }

    /** @test */
    public function dashboard_shows_workstream_overview_with_release_counts()
    {
        // Given: A PM with multiple workstreams and releases
        $this->actingAs($this->pm);

        $secondWorkstream = Workstream::factory()->create([
            'name' => 'Web Platform',
            'type' => 'product_line',
            'owner_id' => $this->pm->id,
        ]);

        Release::factory()->count(3)->create([
            'workstream_id' => $secondWorkstream->id,
            'status' => 'in_progress',
        ]);

        // When: They visit the dashboard
        $response = $this->get('/');

        // Then: They should see workstream overview with counts
        $response->assertInertia(fn ($page) =>
            $page->has('workstreams', 2)
                ->where('workstreams.0.name', 'Mobile App')
                ->where('workstreams.0.active_releases_count', 1)
                ->where('workstreams.1.name', 'Web Platform')
                ->where('workstreams.1.active_releases_count', 3)
        );
    }

    /** @test */
    public function dashboard_provides_quick_add_functionality()
    {
        // Given: A PM wants to quickly add content
        $this->actingAs($this->pm);

        // When: They visit the dashboard
        $response = $this->get('/');

        // Then: They should see the Quick Add component ready for input
        $response->assertInertia(fn ($page) =>
            $page->component('Dashboard/Index')
                ->has('quickAddConfig')
                ->where('quickAddConfig.enabled', true)
                ->where('quickAddConfig.placeholder', 'Paste meeting notes, emails, or ideas...')
        );
    }

    /** @test */
    public function dashboard_loads_quickly_for_adhd_optimized_experience()
    {
        // Given: A PM with typical data volume
        $this->actingAs($this->pm);

        // Create realistic data volume
        $workstreams = Workstream::factory()->count(5)->create(['owner_id' => $this->pm->id]);
        foreach ($workstreams as $workstream) {
            Release::factory()->count(3)->create(['workstream_id' => $workstream->id]);
        }

        $startTime = microtime(true);

        // When: They visit the dashboard
        $response = $this->get('/');

        $loadTime = microtime(true) - $startTime;

        // Then: The page should load quickly (ADHD requirement)
        $response->assertStatus(200);
        $this->assertLessThan(1.0, $loadTime, 'Dashboard should load in under 1 second for ADHD users');
    }

    /** @test */
    public function dashboard_shows_morning_brief_when_available()
    {
        // Given: A PM with a generated morning brief
        $this->actingAs($this->pm);

        // When: They visit the dashboard in the morning
        $response = $this->get('/');

        // Then: They should see space for morning brief (will be AI-generated later)
        $response->assertInertia(fn ($page) =>
            $page->component('Dashboard/Index')
                ->has('morningBrief')
        );
    }
}
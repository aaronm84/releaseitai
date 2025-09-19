<?php

namespace Tests\Feature\Frontend;

use App\Models\Communication;
use App\Models\Release;
use App\Models\User;
use App\Models\Workstream;
use App\Models\CommunicationParticipant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StakeholderManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $pm;
    private User $stakeholder;
    private User $engineer;
    private Workstream $workstream;
    private Release $activeRelease;

    public function setUp(): void
    {
        parent::setUp();

        $this->pm = User::factory()->create([
            'name' => 'Product Manager',
            'email' => 'pm@example.com'
        ]);

        $this->stakeholder = User::factory()->create([
            'name' => 'Marketing Director',
            'email' => 'marketing@example.com'
        ]);

        $this->engineer = User::factory()->create([
            'name' => 'Lead Engineer',
            'email' => 'eng@example.com'
        ]);

        $this->workstream = Workstream::factory()->create([
            'name' => 'Mobile App',
            'type' => 'product_line',
            'owner_id' => $this->pm->id,
        ]);

        $this->activeRelease = Release::factory()->create([
            'name' => 'v2.1 Login Flow',
            'workstream_id' => $this->workstream->id,
            'status' => 'active',
            'planned_date' => now()->addDays(7),
        ]);
    }

    /** @test */
    public function pm_can_view_stakeholder_dashboard_with_engagement_metrics()
    {
        // Given: A PM with stakeholder communications
        $this->actingAs($this->pm);

        $communication = Communication::factory()->create([
            'release_id' => $this->activeRelease->id,
            'channel' => 'email',
            'subject' => 'Release Update',
            'initiated_by_user_id' => $this->pm->id,
        ]);

        CommunicationParticipant::factory()->create([
            'communication_id' => $communication->id,
            'user_id' => $this->stakeholder->id,
            'delivery_status' => 'read',
            'delivered_at' => now()->subHours(2),
            'read_at' => now()->subHours(1),
        ]);

        CommunicationParticipant::factory()->create([
            'communication_id' => $communication->id,
            'user_id' => $this->engineer->id,
            'delivery_status' => 'delivered',
            'delivered_at' => now()->subHours(2),
        ]);

        // When: They visit the stakeholder management page
        $response = $this->get("/releases/{$this->activeRelease->id}/stakeholders");

        // Then: They should see engagement metrics
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Releases/Stakeholders')
                ->has('stakeholders', 2)
                ->has('engagementMetrics')
                ->where('engagementMetrics.total_stakeholders', 2)
                ->where('engagementMetrics.response_rate', 50.0)
                ->where('engagementMetrics.avg_response_time_hours', 1.0)
        );
    }

    /** @test */
    public function pm_can_create_targeted_stakeholder_communication()
    {
        // Given: A PM wanting to communicate with specific stakeholders
        $this->actingAs($this->pm);

        // When: They create a targeted communication
        $response = $this->post("/releases/{$this->activeRelease->id}/communications", [
            'channel' => 'email',
            'subject' => 'Critical Update: Timeline Change',
            'content' => 'We need to discuss the timeline adjustment for the login flow.',
            'communication_type' => 'update',
            'priority' => 'high',
            'participants' => [
                ['user_id' => $this->stakeholder->id, 'role' => 'reviewer'],
                ['user_id' => $this->engineer->id, 'role' => 'implementer'],
            ],
        ]);

        // Then: The communication should be created with participants
        $response->assertStatus(201);
        $this->assertDatabaseHas('communications', [
            'release_id' => $this->activeRelease->id,
            'subject' => 'Critical Update: Timeline Change',
            'priority' => 'high',
        ]);

        $this->assertDatabaseHas('communication_participants', [
            'user_id' => $this->stakeholder->id,
            'role' => 'reviewer',
        ]);
    }

    /** @test */
    public function stakeholder_interface_shows_personalized_release_view()
    {
        // Given: A stakeholder who needs to review release information
        $this->actingAs($this->stakeholder);

        $communication = Communication::factory()->create([
            'release_id' => $this->activeRelease->id,
            'subject' => 'Review Required: Marketing Assets',
            'initiated_by_user_id' => $this->pm->id,
        ]);

        CommunicationParticipant::factory()->create([
            'communication_id' => $communication->id,
            'user_id' => $this->stakeholder->id,
            'delivery_status' => 'delivered',
        ]);

        // When: They view their stakeholder dashboard
        $response = $this->get("/stakeholder/releases/{$this->activeRelease->id}");

        // Then: They should see a personalized view
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Stakeholder/ReleaseView')
                ->where('release.name', 'v2.1 Login Flow')
                ->has('pendingActions', 1)
                ->where('pendingActions.0.subject', 'Review Required: Marketing Assets')
                ->has('releaseProgress')
                ->has('keyDates')
        );
    }

    /** @test */
    public function pm_can_track_stakeholder_action_items_and_responses()
    {
        // Given: A PM with stakeholder action items
        $this->actingAs($this->pm);

        $communication = Communication::factory()->create([
            'release_id' => $this->activeRelease->id,
            'subject' => 'Action Required: Sign-off Needed',
            'follow_up_required' => true,
            'follow_up_due_date' => now()->addDays(2),
            'initiated_by_user_id' => $this->pm->id,
        ]);

        $participant = CommunicationParticipant::factory()->create([
            'communication_id' => $communication->id,
            'user_id' => $this->stakeholder->id,
            'delivery_status' => 'read',
            'read_at' => now()->subHours(1),
        ]);

        // When: They view stakeholder tracking
        $response = $this->get("/releases/{$this->activeRelease->id}/stakeholders/tracking");

        // Then: They should see action item status
        $response->assertInertia(fn ($page) =>
            $page->has('actionItems', 1)
                ->where('actionItems.0.subject', 'Action Required: Sign-off Needed')
                ->where('actionItems.0.status', 'pending')
                ->where('actionItems.0.days_until_due', 2)
                ->has('overdueItems', 0)
        );
    }

    /** @test */
    public function stakeholder_can_provide_feedback_with_sentiment_tracking()
    {
        // Given: A stakeholder responding to a communication
        $this->actingAs($this->stakeholder);

        $communication = Communication::factory()->create([
            'release_id' => $this->activeRelease->id,
            'subject' => 'Feedback Request: UI Design',
            'initiated_by_user_id' => $this->pm->id,
        ]);

        $participant = CommunicationParticipant::factory()->create([
            'communication_id' => $communication->id,
            'user_id' => $this->stakeholder->id,
            'delivery_status' => 'read',
        ]);

        // When: They provide feedback
        $response = $this->post("/communications/{$communication->id}/respond", [
            'response_content' => 'The design looks great! I approve the changes.',
            'response_sentiment' => 'positive',
            'action_status' => 'approved',
        ]);

        // Then: Their response should be recorded with sentiment
        $response->assertStatus(200);
        $this->assertDatabaseHas('communication_participants', [
            'id' => $participant->id,
            'delivery_status' => 'responded',
            'response_content' => 'The design looks great! I approve the changes.',
            'response_sentiment' => 'positive',
        ]);
    }

    /** @test */
    public function pm_can_view_stakeholder_sentiment_trends()
    {
        // Given: A PM with historical stakeholder interactions
        $this->actingAs($this->pm);

        // Create communications with varying sentiment
        $communications = Communication::factory()->count(5)->create([
            'release_id' => $this->activeRelease->id,
            'initiated_by_user_id' => $this->pm->id,
        ]);

        foreach ($communications as $index => $communication) {
            CommunicationParticipant::factory()->create([
                'communication_id' => $communication->id,
                'user_id' => $this->stakeholder->id,
                'delivery_status' => 'responded',
                'response_sentiment' => $index < 3 ? 'positive' : 'negative',
            ]);
        }

        // When: They view sentiment analytics
        $response = $this->get("/releases/{$this->activeRelease->id}/stakeholders/analytics");

        // Then: They should see sentiment trends
        $response->assertInertia(fn ($page) =>
            $page->has('sentimentAnalysis')
                ->where('sentimentAnalysis.total_responses', 5)
                ->where('sentimentAnalysis.positive_percentage', 60.0)
                ->where('sentimentAnalysis.negative_percentage', 40.0)
                ->has('sentimentAnalysis.trend_data')
        );
    }

    /** @test */
    public function stakeholder_management_supports_communication_preferences()
    {
        // Given: A PM setting up stakeholder preferences
        $this->actingAs($this->pm);

        // When: They configure stakeholder communication preferences
        $response = $this->put("/stakeholders/{$this->stakeholder->id}/preferences", [
            'preferred_channel' => 'slack',
            'frequency' => 'weekly',
            'notification_types' => ['status_updates', 'blockers', 'milestones'],
            'timezone' => 'America/New_York',
        ]);

        // Then: Preferences should be stored for targeted communications
        $response->assertStatus(200);
        $this->assertDatabaseHas('stakeholder_preferences', [
            'user_id' => $this->stakeholder->id,
            'preferred_channel' => 'slack',
            'frequency' => 'weekly',
        ]);
    }

    /** @test */
    public function stakeholder_interface_provides_quick_approval_workflow()
    {
        // Given: A stakeholder with pending approvals
        $this->actingAs($this->stakeholder);

        $communication = Communication::factory()->create([
            'release_id' => $this->activeRelease->id,
            'subject' => 'Approval Required: Go-Live Decision',
            'communication_type' => 'approval_request',
            'initiated_by_user_id' => $this->pm->id,
        ]);

        CommunicationParticipant::factory()->create([
            'communication_id' => $communication->id,
            'user_id' => $this->stakeholder->id,
            'delivery_status' => 'delivered',
        ]);

        // When: They view their approval queue
        $response = $this->get('/stakeholder/approvals');

        // Then: They should see quick approval options
        $response->assertInertia(fn ($page) =>
            $page->component('Stakeholder/Approvals')
                ->has('pendingApprovals', 1)
                ->where('pendingApprovals.0.subject', 'Approval Required: Go-Live Decision')
                ->has('quickActions')
                ->where('quickActions.approve', true)
                ->where('quickActions.reject', true)
                ->where('quickActions.request_changes', true)
        );
    }

    /** @test */
    public function stakeholder_management_enables_escalation_workflows()
    {
        // Given: A PM with overdue stakeholder responses
        $this->actingAs($this->pm);

        $communication = Communication::factory()->create([
            'release_id' => $this->activeRelease->id,
            'subject' => 'Critical: Decision Needed',
            'follow_up_required' => true,
            'follow_up_due_date' => now()->subDays(1), // Overdue
            'initiated_by_user_id' => $this->pm->id,
        ]);

        CommunicationParticipant::factory()->create([
            'communication_id' => $communication->id,
            'user_id' => $this->stakeholder->id,
            'delivery_status' => 'read',
        ]);

        // When: They trigger escalation
        $response = $this->post("/communications/{$communication->id}/escalate", [
            'escalation_reason' => 'No response to critical decision request',
            'escalate_to' => 'manager',
            'urgency_level' => 'high',
        ]);

        // Then: Escalation should be logged and triggered
        $response->assertStatus(200);
        $this->assertDatabaseHas('communications', [
            'subject' => 'ESCALATED: Critical: Decision Needed',
            'communication_type' => 'escalation',
            'priority' => 'urgent',
        ]);
    }

    /** @test */
    public function stakeholder_management_interface_loads_quickly_for_adhd_users()
    {
        // Given: A PM with extensive stakeholder data
        $this->actingAs($this->pm);

        // Create realistic data volume
        $stakeholders = User::factory()->count(20)->create();
        foreach ($stakeholders as $stakeholder) {
            $communications = Communication::factory()->count(3)->create([
                'release_id' => $this->activeRelease->id,
                'initiated_by_user_id' => $this->pm->id,
            ]);

            foreach ($communications as $communication) {
                CommunicationParticipant::factory()->create([
                    'communication_id' => $communication->id,
                    'user_id' => $stakeholder->id,
                ]);
            }
        }

        $startTime = microtime(true);

        // When: They visit stakeholder management
        $response = $this->get("/releases/{$this->activeRelease->id}/stakeholders");

        $loadTime = microtime(true) - $startTime;

        // Then: The page should load quickly for ADHD users
        $response->assertStatus(200);
        $this->assertLessThan(1.0, $loadTime, 'Stakeholder management should load in under 1 second for ADHD users');
    }
}
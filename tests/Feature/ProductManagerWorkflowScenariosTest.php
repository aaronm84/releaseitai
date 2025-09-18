<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workstream;
use App\Models\Release;
use App\Models\StakeholderRelease;
use App\Models\ChecklistItem;
use App\Models\ChecklistItemAssignment;
use App\Models\ChecklistItemDependency;
use App\Models\ReleaseDependency;
use App\Models\ApprovalRequest;
use App\Models\ApprovalResponse;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

/**
 * Test class demonstrating real PM workflow scenarios using the enhanced domain model
 * These tests serve as both specification and validation for PM-centric features
 */
class ProductManagerWorkflowScenariosTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        // Create realistic PM team structure
        $this->productManager = User::factory()->create(['email' => 'pm@company.com', 'name' => 'Sarah PM']);
        $this->legalApprover = User::factory()->create(['email' => 'legal@company.com', 'name' => 'John Legal']);
        $this->securityApprover = User::factory()->create(['email' => 'security@company.com', 'name' => 'Jane Security']);
        $this->techLead = User::factory()->create(['email' => 'tech@company.com', 'name' => 'Mike TechLead']);
        $this->designer = User::factory()->create(['email' => 'design@company.com', 'name' => 'Lisa Designer']);
        $this->qaEngineer = User::factory()->create(['email' => 'qa@company.com', 'name' => 'Tom QA']);

        // Create workstream hierarchy
        $this->mobileWorkstream = Workstream::factory()->create([
            'name' => 'Mobile Platform',
            'type' => 'product_line',
            'owner_id' => $this->productManager->id
        ]);

        $this->iosWorkstream = Workstream::factory()->create([
            'name' => 'iOS App',
            'type' => 'initiative',
            'parent_workstream_id' => $this->mobileWorkstream->id,
            'owner_id' => $this->productManager->id
        ]);

        // Create releases for testing
        $this->currentRelease = Release::factory()->create([
            'name' => 'iOS App V3.1 - Privacy Updates',
            'workstream_id' => $this->iosWorkstream->id,
            'target_date' => now()->addDays(21),
            'status' => 'in_progress'
        ]);

        $this->futureRelease = Release::factory()->create([
            'name' => 'iOS App V3.2 - New Features',
            'workstream_id' => $this->iosWorkstream->id,
            'target_date' => now()->addDays(45),
            'status' => 'planned'
        ]);
    }

    /** @test */
    public function pm_workflow_scenario_managing_stakeholders_for_privacy_release()
    {
        /**
         * SCENARIO: As a PM, I need to manage stakeholders for a privacy-focused release
         * that requires legal, security, and design approvals with specific roles and notifications
         */

        $this->actingAs($this->productManager);

        // Step 1: Associate stakeholders with the privacy release
        $response = $this->postJson("/api/releases/{$this->currentRelease->id}/stakeholders", [
            'stakeholders' => [
                [
                    'user_id' => $this->legalApprover->id,
                    'role' => 'approver',
                    'notification_preference' => 'email'
                ],
                [
                    'user_id' => $this->securityApprover->id,
                    'role' => 'approver',
                    'notification_preference' => 'email'
                ],
                [
                    'user_id' => $this->designer->id,
                    'role' => 'reviewer',
                    'notification_preference' => 'slack'
                ],
                [
                    'user_id' => $this->techLead->id,
                    'role' => 'owner',
                    'notification_preference' => 'email'
                ]
            ]
        ]);

        $response->assertStatus(201);

        // Step 2: Query all stakeholders who need to approve this release
        $approversResponse = $this->getJson("/api/releases/{$this->currentRelease->id}/stakeholders?role=approver");
        $approversResponse->assertStatus(200);
        $approversResponse->assertJsonCount(2, 'data'); // Legal and Security

        $approvers = collect($approversResponse->json('data'));
        $this->assertTrue($approvers->contains('user.email', 'legal@company.com'));
        $this->assertTrue($approvers->contains('user.email', 'security@company.com'));

        // Step 3: Verify notification preferences are correctly set
        $legalStakeholder = $approvers->where('user.email', 'legal@company.com')->first();
        $this->assertEquals('email', $legalStakeholder['notification_preference']);

        // This scenario validates that PMs can:
        // - Assign multiple stakeholders with different roles
        // - Filter stakeholders by role to see who needs to approve
        // - Track notification preferences for automated communications
    }

    /** @test */
    public function pm_workflow_scenario_tracking_overdue_approvals_and_sending_reminders()
    {
        /**
         * SCENARIO: As a PM, I need to track overdue approvals and send reminders
         * to keep the release on track
         */

        $this->actingAs($this->productManager);

        // Step 1: Create approval requests with different due dates
        $this->postJson("/api/releases/{$this->currentRelease->id}/approval-requests", [
            'approval_requests' => [
                [
                    'approval_type' => 'legal',
                    'approver_id' => $this->legalApprover->id,
                    'description' => 'Review privacy policy changes',
                    'due_date' => now()->subDays(3)->toDateString(), // Overdue
                    'priority' => 'high'
                ],
                [
                    'approval_type' => 'security',
                    'approver_id' => $this->securityApprover->id,
                    'description' => 'Security review of data handling',
                    'due_date' => now()->addHours(6)->toDateString(), // Due soon
                    'priority' => 'critical'
                ]
            ]
        ]);

        // Step 2: Query overdue approvals
        $overdueResponse = $this->getJson("/api/releases/{$this->currentRelease->id}/approval-requests?status=overdue");
        $overdueResponse->assertStatus(200);
        $overdueResponse->assertJsonCount(1, 'data');

        $overdueApproval = $overdueResponse->json('data.0');
        $this->assertEquals('legal', $overdueApproval['approval_type']);
        $this->assertEquals('high', $overdueApproval['priority']);

        // Step 3: Send reminders for overdue approvals
        $reminderResponse = $this->postJson("/api/approval-requests/send-reminders", [
            'release_id' => $this->currentRelease->id,
            'reminder_type' => 'overdue'
        ]);

        $reminderResponse->assertStatus(200);
        $reminderData = $reminderResponse->json('data');
        $this->assertEquals(1, $reminderData['reminders_sent']);
        $this->assertEquals('legal@company.com', $reminderData['recipients'][0]['approver_email']);

        // This scenario validates that PMs can:
        // - Identify overdue approvals quickly
        // - Send targeted reminders to specific approvers
        // - Track reminder history to avoid spam
    }

    /** @test */
    public function pm_workflow_scenario_managing_task_dependencies_and_detecting_delays()
    {
        /**
         * SCENARIO: As a PM, I need to manage task dependencies and understand
         * the impact when upstream tasks are delayed
         */

        $this->actingAs($this->productManager);

        // Step 1: Create task assignments with dependencies
        $designTask = ChecklistItemAssignment::create([
            'checklist_item_id' => ChecklistItem::factory()->create(['title' => 'Design Privacy UI'])->id,
            'assignee_id' => $this->designer->id,
            'release_id' => $this->currentRelease->id,
            'due_date' => now()->addDays(5),
            'status' => 'pending'
        ]);

        $implementTask = ChecklistItemAssignment::create([
            'checklist_item_id' => ChecklistItem::factory()->create(['title' => 'Implement Privacy Features'])->id,
            'assignee_id' => $this->techLead->id,
            'release_id' => $this->currentRelease->id,
            'due_date' => now()->addDays(12),
            'status' => 'pending'
        ]);

        $testingTask = ChecklistItemAssignment::create([
            'checklist_item_id' => ChecklistItem::factory()->create(['title' => 'Test Privacy Features'])->id,
            'assignee_id' => $this->qaEngineer->id,
            'release_id' => $this->currentRelease->id,
            'due_date' => now()->addDays(16),
            'status' => 'pending'
        ]);

        // Step 2: Create dependency chain: Design -> Implementation -> Testing
        $this->postJson("/api/checklist-dependencies", [
            'prerequisite_assignment_id' => $designTask->id,
            'dependent_assignment_id' => $implementTask->id,
            'dependency_type' => 'blocks'
        ]);

        $this->postJson("/api/checklist-dependencies", [
            'prerequisite_assignment_id' => $implementTask->id,
            'dependent_assignment_id' => $testingTask->id,
            'dependency_type' => 'blocks'
        ]);

        // Step 3: Simulate design task delay
        $this->putJson("/api/checklist-assignments/{$designTask->id}", [
            'due_date' => now()->addDays(10)->toDateString(), // 5-day delay
            'delay_reason' => 'Complex privacy requirements discovered'
        ]);

        // Step 4: Check impact on dependent tasks
        $impactResponse = $this->getJson("/api/checklist-assignments/{$designTask->id}/impact-analysis");
        $impactResponse->assertStatus(200);

        $impactData = $impactResponse->json('data');
        $this->assertCount(2, $impactData['affected_assignments']); // Implementation and Testing
        $this->assertEquals(5, $impactData['delay_days']);

        // This scenario validates that PMs can:
        // - Create complex task dependency chains
        // - Understand the ripple effect of delays
        // - Proactively manage schedule risks
    }

    /** @test */
    public function pm_workflow_scenario_managing_release_dependencies_across_teams()
    {
        /**
         * SCENARIO: As a PM, I need to understand which releases are blocked by
         * delays in other teams' releases
         */

        $this->actingAs($this->productManager);

        // Step 1: Create backend release that our mobile release depends on
        $backendWorkstream = Workstream::factory()->create([
            'name' => 'Backend Services',
            'owner_id' => $this->techLead->id
        ]);

        $backendRelease = Release::factory()->create([
            'name' => 'Privacy API V2',
            'workstream_id' => $backendWorkstream->id,
            'target_date' => now()->addDays(14),
            'status' => 'in_progress'
        ]);

        // Step 2: Create dependency relationship
        $this->postJson("/api/release-dependencies", [
            'dependencies' => [
                [
                    'upstream_release_id' => $backendRelease->id,
                    'downstream_release_id' => $this->currentRelease->id,
                    'dependency_type' => 'blocks',
                    'description' => 'Mobile app requires new privacy endpoints'
                ],
                [
                    'upstream_release_id' => $backendRelease->id,
                    'downstream_release_id' => $this->futureRelease->id,
                    'dependency_type' => 'enables',
                    'description' => 'Future features will leverage privacy API'
                ]
            ]
        ]);

        // Step 3: Simulate backend release delay
        $this->actingAs($this->techLead);
        $this->putJson("/api/releases/{$backendRelease->id}", [
            'target_date' => now()->addDays(25)->toDateString(), // 11-day delay
            'delay_reason' => 'Performance issues with new endpoints'
        ]);

        // Step 4: Analyze impact on our releases
        $this->actingAs($this->productManager);
        $impactResponse = $this->getJson("/api/releases/{$backendRelease->id}/impact-analysis");
        $impactResponse->assertStatus(200);

        $impactData = $impactResponse->json('data');
        $this->assertEquals(11, $impactData['delayed_release']['delay_days']);
        $this->assertCount(2, $impactData['affected_releases']);

        // Find our current release in the affected releases
        $affectedReleases = collect($impactData['affected_releases']);
        $ourRelease = $affectedReleases->where('id', $this->currentRelease->id)->first();
        $this->assertEquals('high', $ourRelease['impact_severity']); // Blocking dependency

        // This scenario validates that PMs can:
        // - Track cross-team dependencies
        // - Understand impact of external delays
        // - Prioritize based on dependency types (blocking vs enabling)
    }

    /** @test */
    public function pm_workflow_scenario_rollup_reporting_across_workstream_hierarchy()
    {
        /**
         * SCENARIO: As a PM, I need to see rollup reporting across the entire
         * mobile platform to understand overall progress
         */

        $this->actingAs($this->productManager);

        // Step 1: Create additional workstreams and releases
        $androidWorkstream = Workstream::factory()->create([
            'name' => 'Android App',
            'type' => 'initiative',
            'parent_workstream_id' => $this->mobileWorkstream->id,
            'owner_id' => $this->productManager->id
        ]);

        $androidRelease = Release::factory()->create([
            'name' => 'Android App V2.5',
            'workstream_id' => $androidWorkstream->id,
            'status' => 'completed'
        ]);

        // Step 2: Create some tasks across releases
        ChecklistItemAssignment::factory()->count(5)->create([
            'release_id' => $this->currentRelease->id,
            'status' => 'completed'
        ]);

        ChecklistItemAssignment::factory()->count(3)->create([
            'release_id' => $this->currentRelease->id,
            'status' => 'pending'
        ]);

        ChecklistItemAssignment::factory()->count(8)->create([
            'release_id' => $androidRelease->id,
            'status' => 'completed'
        ]);

        ChecklistItemAssignment::factory()->count(2)->create([
            'release_id' => $this->futureRelease->id,
            'status' => 'pending'
        ]);

        // Step 3: Get rollup report for entire mobile platform
        $rollupResponse = $this->getJson("/api/workstreams/{$this->mobileWorkstream->id}/rollup-report");
        $rollupResponse->assertStatus(200);

        $rollupData = $rollupResponse->json('data');

        // Verify aggregated metrics
        $this->assertEquals(3, $rollupData['summary']['total_releases']);
        $this->assertEquals(18, $rollupData['summary']['total_tasks']); // 5+3+8+2
        $this->assertEquals(13, $rollupData['summary']['tasks_by_status']['completed']); // 5+8
        $this->assertEquals(5, $rollupData['summary']['tasks_by_status']['pending']); // 3+2

        // Verify child workstream breakdown
        $this->assertCount(2, $rollupData['child_workstreams']); // iOS and Android

        $iosWorkstreamData = collect($rollupData['child_workstreams'])
            ->where('workstream_name', 'iOS App')
            ->first();
        $this->assertEquals(2, $iosWorkstreamData['releases_count']); // Current and Future
        $this->assertEquals(10, $iosWorkstreamData['tasks_count']); // 5+3+2

        // This scenario validates that PMs can:
        // - Get aggregated metrics across the entire product line
        // - See breakdown by individual workstreams
        // - Track progress at different hierarchy levels
    }

    /** @test */
    public function pm_workflow_scenario_end_to_end_release_management()
    {
        /**
         * SCENARIO: Complete end-to-end workflow for managing a complex release
         * with stakeholders, approvals, dependencies, and status tracking
         */

        $this->actingAs($this->productManager);

        // Step 1: Set up stakeholders for the release
        $this->postJson("/api/releases/{$this->currentRelease->id}/stakeholders", [
            'stakeholders' => [
                ['user_id' => $this->legalApprover->id, 'role' => 'approver', 'notification_preference' => 'email'],
                ['user_id' => $this->securityApprover->id, 'role' => 'approver', 'notification_preference' => 'email'],
                ['user_id' => $this->techLead->id, 'role' => 'owner', 'notification_preference' => 'slack']
            ]
        ]);

        // Step 2: Request required approvals
        $this->postJson("/api/releases/{$this->currentRelease->id}/approval-requests", [
            'approval_requests' => [
                [
                    'approval_type' => 'legal',
                    'approver_id' => $this->legalApprover->id,
                    'description' => 'Privacy compliance review',
                    'due_date' => now()->addDays(7)->toDateString(),
                    'priority' => 'high'
                ],
                [
                    'approval_type' => 'security',
                    'approver_id' => $this->securityApprover->id,
                    'description' => 'Security audit of new features',
                    'due_date' => now()->addDays(5)->toDateString(),
                    'priority' => 'critical'
                ]
            ]
        ]);

        // Step 3: Approvers respond to requests
        $legalRequest = ApprovalRequest::where('approval_type', 'legal')->first();
        $this->actingAs($this->legalApprover);
        $this->postJson("/api/approval-requests/{$legalRequest->id}/respond", [
            'decision' => 'approved',
            'comments' => 'Privacy requirements met',
            'conditions' => ['Update privacy policy in app stores']
        ]);

        $securityRequest = ApprovalRequest::where('approval_type', 'security')->first();
        $this->actingAs($this->securityApprover);
        $this->postJson("/api/approval-requests/{$securityRequest->id}/respond", [
            'decision' => 'needs_changes',
            'comments' => 'Data encryption needs enhancement',
            'conditions' => ['Implement AES-256 encryption', 'Add security audit logs']
        ]);

        // Step 4: PM checks overall approval status
        $this->actingAs($this->productManager);
        $statusResponse = $this->getJson("/api/releases/{$this->currentRelease->id}/approval-status");
        $statusResponse->assertStatus(200);

        $statusData = $statusResponse->json('data');
        $this->assertEquals('partially_approved', $statusData['overall_status']);
        $this->assertEquals(2, $statusData['total_approvals_required']);
        $this->assertEquals(2, $statusData['approvals_completed']);
        $this->assertCount(1, $statusData['blocked_approvals']); // Security needs changes

        // Step 5: Get comprehensive view of all stakeholders needing attention
        $stakeholdersResponse = $this->getJson("/api/releases/{$this->currentRelease->id}/stakeholders");
        $stakeholdersResponse->assertStatus(200);
        $this->assertCount(3, $stakeholdersResponse->json('data'));

        // This scenario validates the complete PM workflow:
        // - Stakeholder management
        // - Approval workflow orchestration
        // - Status tracking and reporting
        // - Cross-functional coordination
    }

    /** @test */
    public function pm_workflow_scenario_identifying_critical_path_bottlenecks()
    {
        /**
         * SCENARIO: As a PM, I need to identify critical path bottlenecks
         * to focus my attention on the most impactful issues
         */

        $this->actingAs($this->productManager);

        // Step 1: Create a complex release dependency chain
        $designSystemRelease = Release::factory()->create([
            'name' => 'Design System V3',
            'workstream_id' => $this->iosWorkstream->id,
            'target_date' => now()->addDays(10),
            'status' => 'in_progress'
        ]);

        $apiRelease = Release::factory()->create([
            'name' => 'Privacy API Updates',
            'workstream_id' => $this->iosWorkstream->id,
            'target_date' => now()->addDays(15),
            'status' => 'planned'
        ]);

        // Create blocking dependency chain
        $this->postJson("/api/release-dependencies", [
            'dependencies' => [
                [
                    'upstream_release_id' => $designSystemRelease->id,
                    'downstream_release_id' => $apiRelease->id,
                    'dependency_type' => 'blocks'
                ],
                [
                    'upstream_release_id' => $apiRelease->id,
                    'downstream_release_id' => $this->currentRelease->id,
                    'dependency_type' => 'blocks'
                ],
                [
                    'upstream_release_id' => $this->currentRelease->id,
                    'downstream_release_id' => $this->futureRelease->id,
                    'dependency_type' => 'blocks'
                ]
            ]
        ]);

        // Step 2: Analyze critical path
        $criticalPathResponse = $this->getJson("/api/workstreams/{$this->iosWorkstream->id}/critical-path");
        $criticalPathResponse->assertStatus(200);

        $criticalPathData = $criticalPathResponse->json('data');
        $criticalPath = collect($criticalPathData['critical_path']);

        // Verify all releases in blocking chain are identified as critical
        $criticalPathIds = $criticalPath->pluck('release_id')->toArray();
        $this->assertContains($designSystemRelease->id, $criticalPathIds);
        $this->assertContains($apiRelease->id, $criticalPathIds);
        $this->assertContains($this->currentRelease->id, $criticalPathIds);
        $this->assertContains($this->futureRelease->id, $criticalPathIds);

        // Step 3: Identify bottlenecks when design system is delayed
        $this->putJson("/api/releases/{$designSystemRelease->id}", [
            'target_date' => now()->addDays(20)->toDateString(), // 10-day delay
            'delay_reason' => 'Complex design requirements'
        ]);

        $bottleneckResponse = $this->getJson("/api/workstreams/{$this->iosWorkstream->id}/critical-path");
        $bottleneckData = $bottleneckResponse->json('data');

        $this->assertEquals('high', $bottleneckData['risk_level']);
        $this->assertNotEmpty($bottleneckData['bottlenecks']);

        // Find the design system bottleneck
        $bottlenecks = collect($bottleneckData['bottlenecks']);
        $designBottleneck = $bottlenecks->where('release_id', $designSystemRelease->id)->first();
        $this->assertEquals('delay', $designBottleneck['issue_type']);

        // This scenario validates that PMs can:
        // - Identify critical path across complex dependency chains
        // - Detect bottlenecks and their impact
        // - Focus on highest-risk releases for attention
    }
}
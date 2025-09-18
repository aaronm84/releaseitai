<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workstream;
use App\Models\Release;
use App\Models\ChecklistTemplate;
use App\Models\ChecklistItem;
use App\Models\ChecklistItemAssignment;
use App\Models\ChecklistItemDependency;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class TaskAssignmentAndDependenciesTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->productManager = User::factory()->create(['email' => 'pm@example.com']);
        $this->developer = User::factory()->create(['email' => 'dev@example.com']);
        $this->designer = User::factory()->create(['email' => 'design@example.com']);
        $this->qaEngineer = User::factory()->create(['email' => 'qa@example.com']);

        // Create test workstream and release
        $this->workstream = Workstream::factory()->create([
            'name' => 'Mobile App V2',
            'owner_id' => $this->productManager->id
        ]);

        $this->release = Release::factory()->create([
            'name' => 'Mobile App V2.1',
            'workstream_id' => $this->workstream->id,
            'target_date' => now()->addDays(30)
        ]);

        // Create checklist template and items
        $this->checklistTemplate = ChecklistTemplate::factory()->create([
            'name' => 'Standard Release Checklist',
            'workstream_id' => $this->workstream->id
        ]);

        $this->designItem = ChecklistItem::factory()->create([
            'checklist_template_id' => $this->checklistTemplate->id,
            'title' => 'Complete UI Design',
            'description' => 'Finalize all UI screens and components',
            'order' => 1,
            'estimated_hours' => 40,
            'sla_hours' => 120 // 5 days
        ]);

        $this->developmentItem = ChecklistItem::factory()->create([
            'checklist_template_id' => $this->checklistTemplate->id,
            'title' => 'Implement Features',
            'description' => 'Develop all required features',
            'order' => 2,
            'estimated_hours' => 80,
            'sla_hours' => 168 // 7 days
        ]);

        $this->testingItem = ChecklistItem::factory()->create([
            'checklist_template_id' => $this->checklistTemplate->id,
            'title' => 'QA Testing',
            'description' => 'Complete functional and regression testing',
            'order' => 3,
            'estimated_hours' => 24,
            'sla_hours' => 48 // 2 days
        ]);
    }

    /** @test */
    public function pm_can_assign_checklist_items_to_specific_stakeholders()
    {
        // Given: A PM wants to assign checklist items to stakeholders
        $this->actingAs($this->productManager);

        // When: They assign items to different stakeholders
        $assignments = [
            [
                'checklist_item_id' => $this->designItem->id,
                'assignee_id' => $this->designer->id,
                'due_date' => now()->addDays(5)->toDateString(),
                'priority' => 'high'
            ],
            [
                'checklist_item_id' => $this->developmentItem->id,
                'assignee_id' => $this->developer->id,
                'due_date' => now()->addDays(12)->toDateString(),
                'priority' => 'medium'
            ],
            [
                'checklist_item_id' => $this->testingItem->id,
                'assignee_id' => $this->qaEngineer->id,
                'due_date' => now()->addDays(14)->toDateString(),
                'priority' => 'high'
            ]
        ];

        $response = $this->postJson("/api/releases/{$this->release->id}/checklist-assignments", [
            'assignments' => $assignments
        ]);

        // Then: The assignments should be created successfully
        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'checklist_item_id',
                    'assignee_id',
                    'release_id',
                    'due_date',
                    'priority',
                    'status',
                    'assigned_at',
                    'assignee' => [
                        'id',
                        'name',
                        'email'
                    ],
                    'checklist_item' => [
                        'id',
                        'title',
                        'description',
                        'estimated_hours',
                        'sla_hours'
                    ]
                ]
            ]
        ]);

        // And: The database should contain the assignments
        foreach ($assignments as $assignment) {
            $this->assertDatabaseHas('checklist_item_assignments', [
                'checklist_item_id' => $assignment['checklist_item_id'],
                'assignee_id' => $assignment['assignee_id'],
                'release_id' => $this->release->id,
                'due_date' => $assignment['due_date'],
                'priority' => $assignment['priority'],
                'status' => 'pending'
            ]);
        }
    }

    /** @test */
    public function task_dependency_chains_can_be_defined()
    {
        // Given: Assigned checklist items
        $designAssignment = ChecklistItemAssignment::create([
            'checklist_item_id' => $this->designItem->id,
            'assignee_id' => $this->designer->id,
            'release_id' => $this->release->id,
            'due_date' => now()->addDays(5),
            'priority' => 'high',
            'status' => 'pending'
        ]);

        $developmentAssignment = ChecklistItemAssignment::create([
            'checklist_item_id' => $this->developmentItem->id,
            'assignee_id' => $this->developer->id,
            'release_id' => $this->release->id,
            'due_date' => now()->addDays(12),
            'priority' => 'medium',
            'status' => 'pending'
        ]);

        $testingAssignment = ChecklistItemAssignment::create([
            'checklist_item_id' => $this->testingItem->id,
            'assignee_id' => $this->qaEngineer->id,
            'release_id' => $this->release->id,
            'due_date' => now()->addDays(14),
            'priority' => 'high',
            'status' => 'pending'
        ]);

        // When: PM creates dependency chains
        $this->actingAs($this->productManager);

        // Development depends on Design completion
        $response1 = $this->postJson("/api/checklist-dependencies", [
            'prerequisite_assignment_id' => $designAssignment->id,
            'dependent_assignment_id' => $developmentAssignment->id,
            'dependency_type' => 'blocks'
        ]);

        // Testing depends on Development completion
        $response2 = $this->postJson("/api/checklist-dependencies", [
            'prerequisite_assignment_id' => $developmentAssignment->id,
            'dependent_assignment_id' => $testingAssignment->id,
            'dependency_type' => 'blocks'
        ]);

        // Then: Dependencies should be created
        $response1->assertStatus(201);
        $response2->assertStatus(201);

        // And: Database should contain the dependencies
        $this->assertDatabaseHas('checklist_item_dependencies', [
            'prerequisite_assignment_id' => $designAssignment->id,
            'dependent_assignment_id' => $developmentAssignment->id,
            'dependency_type' => 'blocks'
        ]);

        $this->assertDatabaseHas('checklist_item_dependencies', [
            'prerequisite_assignment_id' => $developmentAssignment->id,
            'dependent_assignment_id' => $testingAssignment->id,
            'dependency_type' => 'blocks'
        ]);
    }

    /** @test */
    public function circular_dependencies_are_detected_and_prevented()
    {
        // Given: Three checklist item assignments
        $assignmentA = ChecklistItemAssignment::factory()->create([
            'release_id' => $this->release->id,
            'assignee_id' => $this->developer->id
        ]);

        $assignmentB = ChecklistItemAssignment::factory()->create([
            'release_id' => $this->release->id,
            'assignee_id' => $this->designer->id
        ]);

        $assignmentC = ChecklistItemAssignment::factory()->create([
            'release_id' => $this->release->id,
            'assignee_id' => $this->qaEngineer->id
        ]);

        // And: Existing dependencies A -> B -> C
        ChecklistItemDependency::create([
            'prerequisite_assignment_id' => $assignmentA->id,
            'dependent_assignment_id' => $assignmentB->id,
            'dependency_type' => 'blocks'
        ]);

        ChecklistItemDependency::create([
            'prerequisite_assignment_id' => $assignmentB->id,
            'dependent_assignment_id' => $assignmentC->id,
            'dependency_type' => 'blocks'
        ]);

        // When: PM tries to create a circular dependency C -> A
        $this->actingAs($this->productManager);
        $response = $this->postJson("/api/checklist-dependencies", [
            'prerequisite_assignment_id' => $assignmentC->id,
            'dependent_assignment_id' => $assignmentA->id,
            'dependency_type' => 'blocks'
        ]);

        // Then: The request should be rejected
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['dependent_assignment_id']);
        $response->assertJson([
            'errors' => [
                'dependent_assignment_id' => ['Creating this dependency would result in a circular dependency chain.']
            ]
        ]);

        // And: No circular dependency should exist in the database
        $this->assertDatabaseMissing('checklist_item_dependencies', [
            'prerequisite_assignment_id' => $assignmentC->id,
            'dependent_assignment_id' => $assignmentA->id
        ]);
    }

    /** @test */
    public function sla_tracking_is_automatically_calculated()
    {
        // Given: A checklist item assignment with SLA
        $assignment = ChecklistItemAssignment::create([
            'checklist_item_id' => $this->designItem->id,
            'assignee_id' => $this->designer->id,
            'release_id' => $this->release->id,
            'due_date' => now()->addDays(5),
            'priority' => 'high',
            'status' => 'pending',
            'assigned_at' => now()
        ]);

        // When: PM queries the assignment
        $this->actingAs($this->productManager);
        $response = $this->getJson("/api/checklist-assignments/{$assignment->id}");

        // Then: SLA information should be included
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'sla_deadline',
                'hours_until_sla_breach',
                'is_sla_breached',
                'sla_status' // 'on_track', 'at_risk', 'breached'
            ]
        ]);

        // And: SLA deadline should be calculated correctly (assigned_at + sla_hours)
        $expectedSlaDeadline = now()->addHours($this->designItem->sla_hours);
        $actualSlaDeadline = Carbon::parse($response->json('data.sla_deadline'));

        $this->assertTrue($expectedSlaDeadline->diffInMinutes($actualSlaDeadline) < 1);
    }

    /** @test */
    public function overdue_tasks_are_detected_correctly()
    {
        // Given: Multiple assignments with different statuses and due dates
        $overdueAssignment = ChecklistItemAssignment::create([
            'checklist_item_id' => $this->designItem->id,
            'assignee_id' => $this->designer->id,
            'release_id' => $this->release->id,
            'due_date' => now()->subDays(2), // Overdue
            'priority' => 'high',
            'status' => 'pending',
            'assigned_at' => now()->subDays(10)
        ]);

        $atRiskAssignment = ChecklistItemAssignment::create([
            'checklist_item_id' => $this->developmentItem->id,
            'assignee_id' => $this->developer->id,
            'release_id' => $this->release->id,
            'due_date' => now()->addHours(2), // Due soon
            'priority' => 'medium',
            'status' => 'pending',
            'assigned_at' => now()->subDays(5)
        ]);

        $onTrackAssignment = ChecklistItemAssignment::create([
            'checklist_item_id' => $this->testingItem->id,
            'assignee_id' => $this->qaEngineer->id,
            'release_id' => $this->release->id,
            'due_date' => now()->addDays(5), // On track
            'priority' => 'high',
            'status' => 'pending',
            'assigned_at' => now()->subDays(1)
        ]);

        // Create another checklist item for the completed assignment
        $completedItem = ChecklistItem::factory()->create([
            'checklist_template_id' => $this->checklistTemplate->id,
            'title' => 'Completed Design Review',
            'description' => 'Review completed design work',
            'order' => 4,
            'estimated_hours' => 8,
            'sla_hours' => 24
        ]);

        $completedAssignment = ChecklistItemAssignment::create([
            'checklist_item_id' => $completedItem->id,
            'assignee_id' => $this->designer->id,
            'release_id' => $this->release->id,
            'due_date' => now()->subDays(1), // Was overdue but completed
            'priority' => 'high',
            'status' => 'completed',
            'assigned_at' => now()->subDays(8),
            'completed_at' => now()->subHours(12)
        ]);

        // When: PM queries overdue tasks
        $this->actingAs($this->productManager);
        $response = $this->getJson("/api/releases/{$this->release->id}/checklist-assignments?status=overdue");

        // Then: Only the overdue pending assignment should be returned
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $this->assertEquals($overdueAssignment->id, $response->json('data.0.id'));

        // When: PM queries at-risk tasks (due within 24 hours)
        $response = $this->getJson("/api/releases/{$this->release->id}/checklist-assignments?status=at_risk");

        // Then: Only the at-risk assignment should be returned
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $this->assertEquals($atRiskAssignment->id, $response->json('data.0.id'));
    }

    /** @test */
    public function escalation_notifications_are_triggered_for_overdue_tasks()
    {
        // Given: An overdue assignment
        $overdueAssignment = ChecklistItemAssignment::create([
            'checklist_item_id' => $this->designItem->id,
            'assignee_id' => $this->designer->id,
            'release_id' => $this->release->id,
            'due_date' => now()->subDays(2),
            'priority' => 'high',
            'status' => 'pending',
            'assigned_at' => now()->subDays(10)
        ]);

        // When: Escalation process is triggered
        $this->actingAs($this->productManager);
        $response = $this->postJson("/api/checklist-assignments/{$overdueAssignment->id}/escalate", [
            'escalation_reason' => 'Task is overdue by 2 days',
            'notify_stakeholders' => true
        ]);

        // Then: Escalation should be recorded
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'escalated',
                'escalated_at',
                'escalation_reason'
            ]
        ]);

        $responseData = $response->json('data');
        $this->assertTrue($responseData['escalated']);
        $this->assertEquals('Task is overdue by 2 days', $responseData['escalation_reason']);
        $this->assertNotNull($responseData['escalated_at']);

        // And: Database should be updated
        $this->assertDatabaseHas('checklist_item_assignments', [
            'id' => $overdueAssignment->id,
            'escalated' => true,
            'escalation_reason' => 'Task is overdue by 2 days'
        ]);
    }

    /** @test */
    public function pm_can_reassign_tasks_to_different_stakeholders()
    {
        // Given: An existing assignment
        $assignment = ChecklistItemAssignment::create([
            'checklist_item_id' => $this->designItem->id,
            'assignee_id' => $this->designer->id,
            'release_id' => $this->release->id,
            'due_date' => now()->addDays(5),
            'priority' => 'high',
            'status' => 'pending',
            'assigned_at' => now()
        ]);

        // When: PM reassigns the task to a different user
        $this->actingAs($this->productManager);
        $response = $this->putJson("/api/checklist-assignments/{$assignment->id}/reassign", [
            'new_assignee_id' => $this->developer->id,
            'reassignment_reason' => 'Designer is unavailable'
        ]);

        // Then: Assignment should be updated
        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'assignee_id' => $this->developer->id,
                'reassigned' => true,
                'reassignment_reason' => 'Designer is unavailable'
            ]
        ]);

        // And: Database should reflect the changes
        $this->assertDatabaseHas('checklist_item_assignments', [
            'id' => $assignment->id,
            'assignee_id' => $this->developer->id,
            'reassigned' => true,
            'reassignment_reason' => 'Designer is unavailable'
        ]);
    }

    /** @test */
    public function dependency_types_are_validated_correctly()
    {
        // Given: Two assignments
        $assignment1 = ChecklistItemAssignment::factory()->create(['release_id' => $this->release->id]);
        $assignment2 = ChecklistItemAssignment::factory()->create(['release_id' => $this->release->id]);

        // When: PM tries to create dependency with invalid type
        $this->actingAs($this->productManager);
        $response = $this->postJson("/api/checklist-dependencies", [
            'prerequisite_assignment_id' => $assignment1->id,
            'dependent_assignment_id' => $assignment2->id,
            'dependency_type' => 'invalid_type'
        ]);

        // Then: Request should be rejected
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('dependency_type');

        // And: Valid types should be accepted
        $validTypes = ['blocks', 'enables', 'informs'];
        foreach ($validTypes as $type) {
            // Clear previous dependencies
            ChecklistItemDependency::truncate();

            $response = $this->postJson("/api/checklist-dependencies", [
                'prerequisite_assignment_id' => $assignment1->id,
                'dependent_assignment_id' => $assignment2->id,
                'dependency_type' => $type
            ]);

            $response->assertStatus(201);
        }
    }

    /** @test */
    public function assignments_can_be_filtered_by_assignee_and_status()
    {
        // Given: Multiple assignments for different users and statuses
        ChecklistItemAssignment::create([
            'checklist_item_id' => $this->designItem->id,
            'assignee_id' => $this->designer->id,
            'release_id' => $this->release->id,
            'due_date' => now()->addDays(5),
            'status' => 'pending'
        ]);

        ChecklistItemAssignment::create([
            'checklist_item_id' => $this->developmentItem->id,
            'assignee_id' => $this->developer->id,
            'release_id' => $this->release->id,
            'due_date' => now()->addDays(10),
            'status' => 'in_progress'
        ]);

        ChecklistItemAssignment::create([
            'checklist_item_id' => $this->testingItem->id,
            'assignee_id' => $this->designer->id,
            'release_id' => $this->release->id,
            'due_date' => now()->addDays(15),
            'status' => 'completed'
        ]);

        // When: PM filters by assignee
        $this->actingAs($this->productManager);
        $response = $this->getJson("/api/releases/{$this->release->id}/checklist-assignments?assignee_id={$this->designer->id}");

        // Then: Only designer's assignments should be returned
        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');

        // When: PM filters by status
        $response = $this->getJson("/api/releases/{$this->release->id}/checklist-assignments?status=pending");

        // Then: Only pending assignments should be returned
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');

        // When: PM filters by both assignee and status
        $response = $this->getJson("/api/releases/{$this->release->id}/checklist-assignments?assignee_id={$this->designer->id}&status=completed");

        // Then: Only completed assignments for designer should be returned
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }
}
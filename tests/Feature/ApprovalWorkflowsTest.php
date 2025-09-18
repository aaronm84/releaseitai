<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workstream;
use App\Models\Release;
use App\Models\ApprovalRequest;
use App\Models\ApprovalResponse;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ApprovalWorkflowsTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users with different roles
        $this->productManager = User::factory()->create(['email' => 'pm@example.com']);
        $this->legalApprover = User::factory()->create(['email' => 'legal@example.com']);
        $this->securityApprover = User::factory()->create(['email' => 'security@example.com']);
        $this->designApprover = User::factory()->create(['email' => 'design@example.com']);
        $this->technicalApprover = User::factory()->create(['email' => 'tech@example.com']);

        // Create test workstream and release
        $this->workstream = Workstream::factory()->create([
            'name' => 'Mobile App V2',
            'owner_id' => $this->productManager->id
        ]);

        $this->release = Release::factory()->create([
            'name' => 'Mobile App V2.1',
            'workstream_id' => $this->workstream->id,
            'target_date' => now()->addDays(30),
            'status' => 'planned'
        ]);
    }

    /** @test */
    public function pm_can_create_approval_requests_for_releases()
    {
        // Given: A PM wants to request approvals for a release
        $this->actingAs($this->productManager);

        // When: They create multiple approval requests
        $approvalRequests = [
            [
                'approval_type' => 'legal',
                'approver_id' => $this->legalApprover->id,
                'description' => 'Legal review for data collection practices',
                'due_date' => now()->addDays(7)->toDateString(),
                'priority' => 'high'
            ],
            [
                'approval_type' => 'security',
                'approver_id' => $this->securityApprover->id,
                'description' => 'Security review for new authentication features',
                'due_date' => now()->addDays(5)->toDateString(),
                'priority' => 'critical'
            ],
            [
                'approval_type' => 'design',
                'approver_id' => $this->designApprover->id,
                'description' => 'Design sign-off for UI changes',
                'due_date' => now()->addDays(3)->toDateString(),
                'priority' => 'medium'
            ]
        ];

        $response = $this->postJson("/api/releases/{$this->release->id}/approval-requests", [
            'approval_requests' => $approvalRequests
        ]);

        // Then: The approval requests should be created successfully
        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'release_id',
                    'approval_type',
                    'approver_id',
                    'description',
                    'due_date',
                    'priority',
                    'status',
                    'created_at',
                    'approver' => [
                        'id',
                        'name',
                        'email'
                    ]
                ]
            ]
        ]);

        // And: The database should contain the approval requests
        foreach ($approvalRequests as $request) {
            $this->assertDatabaseHas('approval_requests', [
                'release_id' => $this->release->id,
                'approval_type' => $request['approval_type'],
                'approver_id' => $request['approver_id'],
                'description' => $request['description'],
                'due_date' => $request['due_date'],
                'priority' => $request['priority'],
                'status' => 'pending'
            ]);
        }
    }

    /** @test */
    public function approval_types_are_validated_correctly()
    {
        // Given: A PM trying to create approval requests
        $this->actingAs($this->productManager);

        // When: They try to create an approval with invalid type
        $response = $this->postJson("/api/releases/{$this->release->id}/approval-requests", [
            'approval_requests' => [
                [
                    'approval_type' => 'invalid_type',
                    'approver_id' => $this->legalApprover->id,
                    'description' => 'Test approval',
                    'due_date' => now()->addDays(7)->toDateString(),
                    'priority' => 'medium'
                ]
            ]
        ]);

        // Then: The request should be rejected
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('approval_requests.0.approval_type');

        // And: Valid approval types should be accepted
        $validTypes = ['legal', 'security', 'design', 'technical'];
        foreach ($validTypes as $type) {
            // Clear previous approval requests
            ApprovalRequest::where('release_id', $this->release->id)->delete();

            $response = $this->postJson("/api/releases/{$this->release->id}/approval-requests", [
                'approval_requests' => [
                    [
                        'approval_type' => $type,
                        'approver_id' => $this->legalApprover->id,
                        'description' => "Test {$type} approval",
                        'due_date' => now()->addDays(7)->toDateString(),
                        'priority' => 'medium'
                    ]
                ]
            ]);

            $response->assertStatus(201);
        }
    }

    /** @test */
    public function approval_priorities_are_validated_correctly()
    {
        // Given: A PM trying to create approval requests
        $this->actingAs($this->productManager);

        // When: They try to create an approval with invalid priority
        $response = $this->postJson("/api/releases/{$this->release->id}/approval-requests", [
            'approval_requests' => [
                [
                    'approval_type' => 'legal',
                    'approver_id' => $this->legalApprover->id,
                    'description' => 'Test approval',
                    'due_date' => now()->addDays(7)->toDateString(),
                    'priority' => 'invalid_priority'
                ]
            ]
        ]);

        // Then: The request should be rejected
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('approval_requests.0.priority');

        // And: Valid priorities should be accepted
        $validPriorities = ['low', 'medium', 'high', 'critical'];
        foreach ($validPriorities as $priority) {
            // Clear previous approval requests
            ApprovalRequest::where('release_id', $this->release->id)->delete();

            $response = $this->postJson("/api/releases/{$this->release->id}/approval-requests", [
                'approval_requests' => [
                    [
                        'approval_type' => 'legal',
                        'approver_id' => $this->legalApprover->id,
                        'description' => "Test approval with {$priority} priority",
                        'due_date' => now()->addDays(7)->toDateString(),
                        'priority' => $priority
                    ]
                ]
            ]);

            $response->assertStatus(201);
        }
    }

    /** @test */
    public function approvers_can_respond_to_approval_requests()
    {
        // Given: An existing approval request
        $approvalRequest = ApprovalRequest::create([
            'release_id' => $this->release->id,
            'approval_type' => 'legal',
            'approver_id' => $this->legalApprover->id,
            'description' => 'Legal review for data collection',
            'due_date' => now()->addDays(7),
            'priority' => 'high',
            'status' => 'pending'
        ]);

        // When: The approver responds to the request
        $this->actingAs($this->legalApprover);
        $response = $this->postJson("/api/approval-requests/{$approvalRequest->id}/respond", [
            'decision' => 'approved',
            'comments' => 'Legal review completed. All requirements met.',
            'conditions' => [
                'Add privacy policy update to release notes',
                'Ensure GDPR compliance documentation is updated'
            ]
        ]);

        // Then: The response should be recorded
        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'approval_request_id',
                'decision',
                'comments',
                'conditions',
                'responded_at',
                'responder' => [
                    'id',
                    'name',
                    'email'
                ]
            ]
        ]);

        // And: The approval request status should be updated
        $this->assertDatabaseHas('approval_requests', [
            'id' => $approvalRequest->id,
            'status' => 'approved'
        ]);

        // And: The approval response should be stored
        $this->assertDatabaseHas('approval_responses', [
            'approval_request_id' => $approvalRequest->id,
            'responder_id' => $this->legalApprover->id,
            'decision' => 'approved',
            'comments' => 'Legal review completed. All requirements met.'
        ]);
    }

    /** @test */
    public function approval_decisions_are_validated_correctly()
    {
        // Given: An approval request
        $approvalRequest = ApprovalRequest::factory()->create([
            'release_id' => $this->release->id,
            'approver_id' => $this->legalApprover->id
        ]);

        // When: Approver tries to respond with invalid decision
        $this->actingAs($this->legalApprover);
        $response = $this->postJson("/api/approval-requests/{$approvalRequest->id}/respond", [
            'decision' => 'invalid_decision',
            'comments' => 'Test comments'
        ]);

        // Then: The request should be rejected
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('decision');

        // And: Valid decisions should be accepted
        $validDecisions = ['approved', 'rejected', 'needs_changes'];
        foreach ($validDecisions as $decision) {
            // Create new approval request for each test
            $newRequest = ApprovalRequest::factory()->create([
                'release_id' => $this->release->id,
                'approver_id' => $this->legalApprover->id
            ]);

            $response = $this->postJson("/api/approval-requests/{$newRequest->id}/respond", [
                'decision' => $decision,
                'comments' => "Test {$decision} decision"
            ]);

            $response->assertStatus(201);
        }
    }

    /** @test */
    public function only_designated_approvers_can_respond_to_requests()
    {
        // Given: An approval request for legal approver
        $approvalRequest = ApprovalRequest::create([
            'release_id' => $this->release->id,
            'approval_type' => 'legal',
            'approver_id' => $this->legalApprover->id,
            'description' => 'Legal review',
            'due_date' => now()->addDays(7),
            'priority' => 'high',
            'status' => 'pending'
        ]);

        // When: A different user tries to respond
        $this->actingAs($this->securityApprover);
        $response = $this->postJson("/api/approval-requests/{$approvalRequest->id}/respond", [
            'decision' => 'approved',
            'comments' => 'Unauthorized response'
        ]);

        // Then: The request should be forbidden
        $response->assertStatus(403);

        // And: No response should be recorded
        $this->assertDatabaseMissing('approval_responses', [
            'approval_request_id' => $approvalRequest->id
        ]);
    }

    /** @test */
    public function pm_can_track_approval_status_for_releases()
    {
        // Given: Multiple approval requests with different statuses
        $pendingRequest = ApprovalRequest::create([
            'release_id' => $this->release->id,
            'approval_type' => 'legal',
            'approver_id' => $this->legalApprover->id,
            'description' => 'Legal review required',
            'status' => 'pending',
            'due_date' => now()->addDays(5)
        ]);

        $approvedRequest = ApprovalRequest::create([
            'release_id' => $this->release->id,
            'approval_type' => 'design',
            'approver_id' => $this->designApprover->id,
            'description' => 'Design approval completed',
            'status' => 'approved',
            'due_date' => now()->addDays(3)
        ]);

        $rejectedRequest = ApprovalRequest::create([
            'release_id' => $this->release->id,
            'approval_type' => 'security',
            'approver_id' => $this->securityApprover->id,
            'description' => 'Security review rejected',
            'status' => 'rejected',
            'due_date' => now()->addDays(7)
        ]);

        // Create corresponding responses
        ApprovalResponse::create([
            'approval_request_id' => $approvedRequest->id,
            'responder_id' => $this->designApprover->id,
            'decision' => 'approved',
            'comments' => 'Design looks good'
        ]);

        ApprovalResponse::create([
            'approval_request_id' => $rejectedRequest->id,
            'responder_id' => $this->securityApprover->id,
            'decision' => 'rejected',
            'comments' => 'Security concerns identified'
        ]);

        // When: PM queries approval status
        $this->actingAs($this->productManager);
        $response = $this->getJson("/api/releases/{$this->release->id}/approval-status");

        // Then: Complete approval status should be returned
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'release_id',
                'overall_status', // 'pending', 'approved', 'rejected', 'partially_approved'
                'total_approvals_required',
                'approvals_completed',
                'approval_requests' => [
                    '*' => [
                        'id',
                        'approval_type',
                        'status',
                        'due_date',
                        'priority',
                        'approver',
                        'response' => [
                            'decision',
                            'comments',
                            'conditions',
                            'responded_at'
                        ]
                    ]
                ],
                'pending_approvals' => [
                    '*' => [
                        'id',
                        'approval_type',
                        'approver',
                        'due_date',
                        'days_until_due'
                    ]
                ],
                'blocked_approvals' => [
                    '*' => [
                        'id',
                        'approval_type',
                        'rejection_reason'
                    ]
                ]
            ]
        ]);

        $data = $response->json('data');

        // Verify overall status calculation
        $this->assertEquals('partially_approved', $data['overall_status']);
        $this->assertEquals(3, $data['total_approvals_required']);
        $this->assertEquals(2, $data['approvals_completed']); // approved + rejected

        // Verify pending approvals
        $this->assertCount(1, $data['pending_approvals']);
        $this->assertEquals('legal', $data['pending_approvals'][0]['approval_type']);

        // Verify blocked approvals
        $this->assertCount(1, $data['blocked_approvals']);
        $this->assertEquals('security', $data['blocked_approvals'][0]['approval_type']);
    }

    /** @test */
    public function overdue_approvals_are_identified_correctly()
    {
        // Given: Approval requests with different due dates
        $overdueRequest = ApprovalRequest::create([
            'release_id' => $this->release->id,
            'approval_type' => 'legal',
            'approver_id' => $this->legalApprover->id,
            'description' => 'Legal review - overdue',
            'due_date' => now()->subDays(2), // Overdue
            'status' => 'pending'
        ]);

        $dueSoonRequest = ApprovalRequest::create([
            'release_id' => $this->release->id,
            'approval_type' => 'security',
            'approver_id' => $this->securityApprover->id,
            'description' => 'Security review - due soon',
            'due_date' => now()->addHours(6), // Due soon
            'status' => 'pending'
        ]);

        $notDueRequest = ApprovalRequest::create([
            'release_id' => $this->release->id,
            'approval_type' => 'design',
            'approver_id' => $this->designApprover->id,
            'description' => 'Design review - not due yet',
            'due_date' => now()->addDays(5), // Not due yet
            'status' => 'pending'
        ]);

        // When: PM queries overdue approvals
        $this->actingAs($this->productManager);
        $response = $this->getJson("/api/releases/{$this->release->id}/approval-requests?status=overdue");

        // Then: Only overdue requests should be returned
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $this->assertEquals($overdueRequest->id, $response->json('data.0.id'));

        // When: PM queries approvals due soon (within 24 hours)
        $response = $this->getJson("/api/releases/{$this->release->id}/approval-requests?status=due_soon");

        // Then: Only requests due soon should be returned
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $this->assertEquals($dueSoonRequest->id, $response->json('data.0.id'));
    }

    /** @test */
    public function automated_reminder_system_can_be_triggered()
    {
        // Given: Overdue approval requests
        $overdueRequest = ApprovalRequest::create([
            'release_id' => $this->release->id,
            'approval_type' => 'legal',
            'approver_id' => $this->legalApprover->id,
            'description' => 'Legal review for reminders test',
            'due_date' => now()->subDays(3),
            'status' => 'pending',
            'reminder_count' => 1,
            'last_reminder_sent' => now()->subDays(1)
        ]);

        // When: Reminder system is triggered
        $this->actingAs($this->productManager);
        $response = $this->postJson("/api/approval-requests/send-reminders", [
            'release_id' => $this->release->id,
            'reminder_type' => 'overdue'
        ]);

        // Then: Reminders should be sent
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'reminders_sent',
                'recipients' => [
                    '*' => [
                        'approver_id',
                        'approver_email',
                        'approval_type',
                        'days_overdue'
                    ]
                ]
            ]
        ]);

        $data = $response->json('data');
        $this->assertEquals(1, $data['reminders_sent']);
        $this->assertEquals($this->legalApprover->email, $data['recipients'][0]['approver_email']);

        // And: Reminder count should be updated
        $this->assertDatabaseHas('approval_requests', [
            'id' => $overdueRequest->id,
            'reminder_count' => 2
        ]);
    }

    /** @test */
    public function approval_requests_can_be_updated_by_pm()
    {
        // Given: An existing approval request
        $approvalRequest = ApprovalRequest::create([
            'release_id' => $this->release->id,
            'approval_type' => 'legal',
            'approver_id' => $this->legalApprover->id,
            'description' => 'Original description',
            'due_date' => now()->addDays(7),
            'priority' => 'medium',
            'status' => 'pending'
        ]);

        // When: PM updates the approval request
        $this->actingAs($this->productManager);
        $response = $this->putJson("/api/approval-requests/{$approvalRequest->id}", [
            'description' => 'Updated description with more details',
            'due_date' => now()->addDays(10)->toDateString(),
            'priority' => 'high',
            'approver_id' => $this->technicalApprover->id // Change approver
        ]);

        // Then: The approval request should be updated
        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'description' => 'Updated description with more details',
                'priority' => 'high',
                'approver_id' => $this->technicalApprover->id
            ]
        ]);

        // And: The database should reflect the changes
        $this->assertDatabaseHas('approval_requests', [
            'id' => $approvalRequest->id,
            'description' => 'Updated description with more details',
            'priority' => 'high',
            'approver_id' => $this->technicalApprover->id
        ]);
    }

    /** @test */
    public function approval_requests_can_be_cancelled()
    {
        // Given: An existing pending approval request
        $approvalRequest = ApprovalRequest::create([
            'release_id' => $this->release->id,
            'approval_type' => 'legal',
            'approver_id' => $this->legalApprover->id,
            'description' => 'Legal review to be cancelled',
            'due_date' => now()->addDays(7),
            'status' => 'pending'
        ]);

        // When: PM cancels the approval request
        $this->actingAs($this->productManager);
        $response = $this->postJson("/api/approval-requests/{$approvalRequest->id}/cancel", [
            'cancellation_reason' => 'Legal requirements changed'
        ]);

        // Then: The approval request should be cancelled
        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'status' => 'cancelled',
                'cancellation_reason' => 'Legal requirements changed'
            ]
        ]);

        // And: The database should reflect the cancellation
        $this->assertDatabaseHas('approval_requests', [
            'id' => $approvalRequest->id,
            'status' => 'cancelled',
            'cancellation_reason' => 'Legal requirements changed'
        ]);
    }

    /** @test */
    public function approval_requests_expire_automatically()
    {
        // Given: An approval request that has passed its due date
        $expiredRequest = ApprovalRequest::create([
            'release_id' => $this->release->id,
            'approval_type' => 'legal',
            'approver_id' => $this->legalApprover->id,
            'description' => 'Legal review that will expire',
            'due_date' => now()->subDays(30), // Way past due
            'status' => 'pending',
            'auto_expire_days' => 30
        ]);

        // When: Expiration process is run
        $this->actingAs($this->productManager);
        $response = $this->postJson("/api/approval-requests/process-expirations");

        // Then: Expired requests should be identified and updated
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'expired_requests_count',
                'expired_requests' => [
                    '*' => [
                        'id',
                        'approval_type',
                        'days_overdue'
                    ]
                ]
            ]
        ]);

        // And: The request should be marked as expired
        $this->assertDatabaseHas('approval_requests', [
            'id' => $expiredRequest->id,
            'status' => 'expired'
        ]);
    }

    /** @test */
    public function approval_workflow_summary_provides_insights()
    {
        // Given: Multiple releases with various approval statuses
        $release2 = Release::factory()->create(['workstream_id' => $this->workstream->id]);

        // Create various approval requests across releases
        ApprovalRequest::factory()->count(3)->create([
            'release_id' => $this->release->id,
            'status' => 'pending'
        ]);

        ApprovalRequest::factory()->count(2)->create([
            'release_id' => $this->release->id,
            'status' => 'approved'
        ]);

        ApprovalRequest::factory()->count(1)->create([
            'release_id' => $this->release->id,
            'status' => 'rejected'
        ]);

        ApprovalRequest::factory()->count(2)->create([
            'release_id' => $release2->id,
            'status' => 'pending',
            'due_date' => now()->subDays(1) // Overdue
        ]);

        // When: PM requests workflow summary
        $this->actingAs($this->productManager);
        $response = $this->getJson("/api/workstreams/{$this->workstream->id}/approval-summary");

        // Then: Comprehensive summary should be provided
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'total_approval_requests',
                'pending_approvals',
                'approved_requests',
                'rejected_requests',
                'overdue_approvals',
                'average_approval_time_days',
                'approval_types_breakdown' => [
                    '*' => [
                        'type',
                        'total',
                        'pending',
                        'approved',
                        'rejected'
                    ]
                ],
                'releases_needing_attention' => [
                    '*' => [
                        'release_id',
                        'release_name',
                        'pending_approvals',
                        'overdue_approvals',
                        'blocked_approvals'
                    ]
                ]
            ]
        ]);

        $data = $response->json('data');
        $this->assertEquals(8, $data['total_approval_requests']);
        $this->assertEquals(5, $data['pending_approvals']); // 3 + 2
        $this->assertEquals(2, $data['overdue_approvals']);
    }
}
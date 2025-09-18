<?php

namespace Tests\Feature;

use App\Models\Communication;
use App\Models\CommunicationParticipant;
use App\Models\Release;
use App\Models\User;
use App\Models\Workstream;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

/**
 * Test class for communication audit trail functionality
 * Validates all aspects of communication logging, tracking, and retrieval
 */
class CommunicationAuditTrailTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users for different roles
        $this->productManager = User::factory()->create(['email' => 'pm@company.com', 'name' => 'Product Manager']);
        $this->stakeholder1 = User::factory()->create(['email' => 'legal@company.com', 'name' => 'Legal Stakeholder']);
        $this->stakeholder2 = User::factory()->create(['email' => 'security@company.com', 'name' => 'Security Stakeholder']);
        $this->stakeholder3 = User::factory()->create(['email' => 'engineering@company.com', 'name' => 'Engineering Lead']);

        // Create test workstream and release
        $this->workstream = Workstream::factory()->create([
            'name' => 'Mobile App Release',
            'owner_id' => $this->productManager->id
        ]);

        $this->release = Release::factory()->create([
            'name' => 'Mobile App V2.1 - Privacy Updates',
            'workstream_id' => $this->workstream->id,
            'target_date' => now()->addDays(30),
            'status' => 'in_progress'
        ]);
    }

    /** @test */
    public function can_log_email_communication_for_release()
    {
        $this->actingAs($this->productManager);

        $communicationData = [
            'channel' => 'email',
            'subject' => 'Privacy Policy Review Required',
            'content' => 'Please review the updated privacy policy for the mobile app release.',
            'communication_type' => 'approval_request',
            'direction' => 'outbound',
            'priority' => 'high',
            'participants' => [
                [
                    'user_id' => $this->stakeholder1->id,
                    'type' => 'to',
                    'role' => 'approver',
                    'contact_method' => 'legal@company.com'
                ],
                [
                    'user_id' => $this->stakeholder2->id,
                    'type' => 'cc',
                    'role' => 'stakeholder',
                    'contact_method' => 'security@company.com'
                ]
            ]
        ];

        $response = $this->postJson("/api/releases/{$this->release->id}/communications", $communicationData);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'release_id',
                'initiated_by_user_id',
                'channel',
                'subject',
                'content',
                'communication_type',
                'direction',
                'priority',
                'communication_date',
                'status',
                'thread_id',
                'created_at',
                'updated_at',
                'initiated_by' => ['id', 'name', 'email'],
                'participants' => [
                    '*' => [
                        'id',
                        'communication_id',
                        'user_id',
                        'participant_type',
                        'role',
                        'delivery_status',
                        'contact_method',
                        'created_at',
                        'updated_at',
                        'user' => ['id', 'name', 'email']
                    ]
                ]
            ]
        ]);

        // Verify database records
        $this->assertDatabaseHas('communications', [
            'release_id' => $this->release->id,
            'initiated_by_user_id' => $this->productManager->id,
            'channel' => 'email',
            'subject' => 'Privacy Policy Review Required',
            'communication_type' => 'approval_request',
            'direction' => 'outbound',
            'priority' => 'high'
        ]);

        $this->assertDatabaseHas('communication_participants', [
            'user_id' => $this->stakeholder1->id,
            'participant_type' => 'to',
            'role' => 'approver',
            'contact_method' => 'legal@company.com'
        ]);

        $this->assertDatabaseHas('communication_participants', [
            'user_id' => $this->stakeholder2->id,
            'participant_type' => 'cc',
            'role' => 'stakeholder',
            'contact_method' => 'security@company.com'
        ]);
    }

    /** @test */
    public function can_log_slack_meeting_communication()
    {
        $this->actingAs($this->productManager);

        $communicationData = [
            'channel' => 'slack',
            'content' => 'Quick sync on release timeline in #mobile-release channel',
            'communication_type' => 'discussion',
            'direction' => 'internal',
            'priority' => 'medium',
            'external_id' => 'slack_msg_123456',
            'metadata' => [
                'channel_name' => '#mobile-release',
                'message_id' => '1234567890.123456'
            ],
            'participants' => [
                [
                    'user_id' => $this->stakeholder3->id,
                    'type' => 'to',
                    'role' => 'stakeholder',
                    'contact_method' => '@engineering-lead'
                ]
            ]
        ];

        $response = $this->postJson("/api/releases/{$this->release->id}/communications", $communicationData);

        $response->assertStatus(201);

        // Verify Slack-specific metadata is stored
        $communication = Communication::latest()->first();
        $this->assertEquals('slack', $communication->channel);
        $this->assertEquals('slack_msg_123456', $communication->external_id);
        $this->assertArrayHasKey('channel_name', $communication->metadata);
        $this->assertEquals('#mobile-release', $communication->metadata['channel_name']);
    }

    /** @test */
    public function can_retrieve_communication_history_for_release()
    {
        // Create multiple communications for the release
        $communications = Communication::factory()->count(5)->create([
            'release_id' => $this->release->id,
            'initiated_by_user_id' => $this->productManager->id,
        ]);

        $this->actingAs($this->productManager);

        $response = $this->getJson("/api/releases/{$this->release->id}/communications");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'release_id',
                    'channel',
                    'communication_type',
                    'communication_date',
                    'initiated_by',
                    'participants'
                ]
            ],
            'links',
            'meta'
        ]);

        $this->assertCount(5, $response->json('data'));
    }

    /** @test */
    public function can_filter_communications_by_channel_and_type()
    {
        // Create communications with different channels and types
        Communication::factory()->create([
            'release_id' => $this->release->id,
            'channel' => 'email',
            'communication_type' => 'approval_request'
        ]);

        Communication::factory()->create([
            'release_id' => $this->release->id,
            'channel' => 'slack',
            'communication_type' => 'discussion'
        ]);

        Communication::factory()->create([
            'release_id' => $this->release->id,
            'channel' => 'email',
            'communication_type' => 'status_update'
        ]);

        $this->actingAs($this->productManager);

        // Filter by email channel
        $response = $this->getJson("/api/releases/{$this->release->id}/communications?channel=email");
        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));

        // Filter by approval_request type
        $response = $this->getJson("/api/releases/{$this->release->id}/communications?type=approval_request");
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));

        // Filter by both channel and type
        $response = $this->getJson("/api/releases/{$this->release->id}/communications?channel=email&type=approval_request");
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    /** @test */
    public function can_filter_communications_by_date_range()
    {
        // Create communications on different dates
        Communication::factory()->create([
            'release_id' => $this->release->id,
            'communication_date' => now()->subDays(10)
        ]);

        Communication::factory()->create([
            'release_id' => $this->release->id,
            'communication_date' => now()->subDays(5)
        ]);

        Communication::factory()->create([
            'release_id' => $this->release->id,
            'communication_date' => now()->subDays(1)
        ]);

        $this->actingAs($this->productManager);

        $startDate = now()->subDays(7)->toDateString();
        $endDate = now()->toDateString();

        $response = $this->getJson("/api/releases/{$this->release->id}/communications?start_date={$startDate}&end_date={$endDate}");

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data')); // Only communications from last 7 days
    }

    /** @test */
    public function can_filter_communications_by_participant()
    {
        // Create communication with specific participant
        $communication = Communication::factory()->create([
            'release_id' => $this->release->id,
        ]);

        CommunicationParticipant::factory()->create([
            'communication_id' => $communication->id,
            'user_id' => $this->stakeholder1->id
        ]);

        // Create communication without that participant
        Communication::factory()->create([
            'release_id' => $this->release->id,
        ]);

        $this->actingAs($this->productManager);

        $response = $this->getJson("/api/releases/{$this->release->id}/communications?participant_id={$this->stakeholder1->id}");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    /** @test */
    public function can_update_communication_outcome_and_follow_up()
    {
        $communication = Communication::factory()->create([
            'release_id' => $this->release->id,
            'initiated_by_user_id' => $this->productManager->id,
        ]);

        $this->actingAs($this->productManager);

        $updateData = [
            'outcome_summary' => 'Legal approval received with conditions',
            'follow_up_actions' => [
                'Update privacy policy in app stores',
                'Schedule follow-up review in 30 days'
            ],
            'follow_up_due_date' => now()->addDays(30)->toDateString(),
            'status' => 'responded'
        ];

        $response = $this->putJson("/api/communications/{$communication->id}/outcome", $updateData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('communications', [
            'id' => $communication->id,
            'outcome_summary' => 'Legal approval received with conditions',
            'status' => 'responded'
        ]);

        $updatedCommunication = Communication::find($communication->id);
        $this->assertCount(2, $updatedCommunication->follow_up_actions);
        $this->assertEquals('Update privacy policy in app stores', $updatedCommunication->follow_up_actions[0]);
    }

    /** @test */
    public function can_track_participant_delivery_and_response_status()
    {
        $communication = Communication::factory()->create([
            'release_id' => $this->release->id,
        ]);

        $participant = CommunicationParticipant::factory()->create([
            'communication_id' => $communication->id,
            'user_id' => $this->stakeholder1->id,
            'delivery_status' => 'pending'
        ]);

        $this->actingAs($this->productManager);

        // Mark as delivered
        $response = $this->putJson("/api/communications/{$communication->id}/participants/{$participant->id}/status", [
            'delivery_status' => 'delivered'
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('communication_participants', [
            'id' => $participant->id,
            'delivery_status' => 'delivered'
        ]);

        // Mark as responded with sentiment
        $response = $this->putJson("/api/communications/{$communication->id}/participants/{$participant->id}/status", [
            'delivery_status' => 'responded',
            'response_content' => 'Approved with minor changes requested',
            'response_sentiment' => 'positive'
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('communication_participants', [
            'id' => $participant->id,
            'delivery_status' => 'responded',
            'response_content' => 'Approved with minor changes requested',
            'response_sentiment' => 'positive'
        ]);
    }

    /** @test */
    public function can_search_communications_across_releases()
    {
        // Create communications with searchable content
        Communication::factory()->create([
            'release_id' => $this->release->id,
            'subject' => 'Privacy Policy Review',
            'content' => 'Please review the GDPR compliance requirements'
        ]);

        Communication::factory()->create([
            'release_id' => $this->release->id,
            'subject' => 'Security Audit',
            'content' => 'Security team needs to audit the authentication system'
        ]);

        Communication::factory()->create([
            'release_id' => $this->release->id,
            'subject' => 'Release Timeline',
            'content' => 'Timeline update for mobile app deployment'
        ]);

        $this->actingAs($this->productManager);

        // Search for "privacy" content
        $response = $this->getJson('/api/communications/search?query=privacy');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));

        // Search for "review" content (appears in subject and content)
        $response = $this->getJson('/api/communications/search?query=review');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));

        // Search with additional filters
        $response = $this->getJson("/api/communications/search?query=security&release_id={$this->release->id}");
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    /** @test */
    public function can_get_communication_analytics_for_release()
    {
        // Create diverse communications for analytics
        Communication::factory()->create([
            'release_id' => $this->release->id,
            'channel' => 'email',
            'communication_type' => 'approval_request',
            'priority' => 'high',
            'status' => 'sent'
        ]);

        Communication::factory()->create([
            'release_id' => $this->release->id,
            'channel' => 'slack',
            'communication_type' => 'discussion',
            'priority' => 'medium',
            'status' => 'read'
        ]);

        Communication::factory()->create([
            'release_id' => $this->release->id,
            'channel' => 'email',
            'communication_type' => 'status_update',
            'priority' => 'low',
            'status' => 'responded',
            'follow_up_due_date' => now()->addDays(5)
        ]);

        $this->actingAs($this->productManager);

        $response = $this->getJson("/api/releases/{$this->release->id}/communication-analytics");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'total_communications',
                'by_channel',
                'by_type',
                'by_priority',
                'by_status',
                'requiring_follow_up',
                'overdue_follow_ups',
                'sensitive_communications',
                'participant_engagement',
                'average_response_time_hours'
            ]
        ]);

        $data = $response->json('data');
        $this->assertEquals(3, $data['total_communications']);
        $this->assertEquals(2, $data['by_channel']['email']);
        $this->assertEquals(1, $data['by_channel']['slack']);
    }

    /** @test */
    public function can_retrieve_communications_requiring_follow_up()
    {
        // Create communications with different follow-up statuses
        Communication::factory()->create([
            'release_id' => $this->release->id,
            'follow_up_due_date' => now()->addDays(5),
            'status' => 'sent'
        ]);

        Communication::factory()->create([
            'release_id' => $this->release->id,
            'follow_up_due_date' => now()->subDays(2),
            'status' => 'sent'
        ]);

        Communication::factory()->create([
            'release_id' => $this->release->id,
            'follow_up_due_date' => now()->addDays(3),
            'status' => 'responded'
        ]);

        $this->actingAs($this->productManager);

        // Get all pending follow-ups
        $response = $this->getJson('/api/communications/follow-ups');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data')); // Only pending follow-ups

        // Get overdue follow-ups
        $response = $this->getJson('/api/communications/follow-ups?status=overdue');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data')); // Only overdue follow-ups
    }

    /** @test */
    public function can_handle_sensitive_communications_with_compliance_tags()
    {
        $this->actingAs($this->productManager);

        $sensitiveData = [
            'channel' => 'email',
            'subject' => 'CONFIDENTIAL: Customer Data Breach Response',
            'content' => 'Details about customer data security incident requiring immediate action',
            'communication_type' => 'escalation',
            'direction' => 'outbound',
            'priority' => 'urgent',
            'is_sensitive' => true,
            'compliance_tags' => 'GDPR,SOX,PCI-DSS',
            'participants' => [
                [
                    'user_id' => $this->stakeholder1->id,
                    'type' => 'to',
                    'role' => 'approver'
                ]
            ]
        ];

        $response = $this->postJson("/api/releases/{$this->release->id}/communications", $sensitiveData);

        $response->assertStatus(201);

        $this->assertDatabaseHas('communications', [
            'release_id' => $this->release->id,
            'subject' => 'CONFIDENTIAL: Customer Data Breach Response',
            'is_sensitive' => true,
            'compliance_tags' => 'GDPR,SOX,PCI-DSS'
        ]);

        // Verify sensitive communications can be filtered
        $response = $this->getJson("/api/releases/{$this->release->id}/communications?sensitive_only=true");
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    /** @test */
    public function can_group_communications_by_thread_id()
    {
        $threadId = Communication::generateThreadId();

        // Create multiple communications in the same thread
        $communication1 = Communication::factory()->create([
            'release_id' => $this->release->id,
            'thread_id' => $threadId,
            'subject' => 'Initial Review Request',
            'communication_date' => now()->subHours(2)
        ]);

        $communication2 = Communication::factory()->create([
            'release_id' => $this->release->id,
            'thread_id' => $threadId,
            'subject' => 'Re: Initial Review Request',
            'communication_date' => now()->subHours(1)
        ]);

        $this->actingAs($this->productManager);

        // Get communication details including thread
        $response = $this->getJson("/api/communications/{$communication1->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'communication',
                'thread_communications',
                'requires_follow_up',
                'is_follow_up_overdue',
                'days_until_follow_up'
            ]
        ]);

        $data = $response->json('data');
        $this->assertCount(2, $data['thread_communications']); // Both communications in thread

        // Filter communications by thread
        $response = $this->getJson("/api/releases/{$this->release->id}/communications?thread_id={$threadId}");
        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    /** @test */
    public function validates_communication_input_correctly()
    {
        $this->actingAs($this->productManager);

        // Test invalid channel
        $response = $this->postJson("/api/releases/{$this->release->id}/communications", [
            'channel' => 'invalid_channel',
            'content' => 'Test content',
            'communication_type' => 'discussion',
            'direction' => 'outbound',
            'participants' => [
                ['user_id' => $this->stakeholder1->id, 'type' => 'to']
            ]
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('channel');

        // Test missing required fields
        $response = $this->postJson("/api/releases/{$this->release->id}/communications", [
            'channel' => 'email'
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['content', 'communication_type', 'direction', 'participants']);

        // Test invalid participant user
        $response = $this->postJson("/api/releases/{$this->release->id}/communications", [
            'channel' => 'email',
            'content' => 'Test content',
            'communication_type' => 'discussion',
            'direction' => 'outbound',
            'participants' => [
                ['user_id' => 999999, 'type' => 'to'] // Non-existent user
            ]
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('participants.0.user_id');
    }
}
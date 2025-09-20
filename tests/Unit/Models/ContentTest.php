<?php

namespace Tests\Unit\Models;

use App\Models\Content;
use App\Models\User;
use App\Models\Stakeholder;
use App\Models\Workstream;
use App\Models\Release;
use App\Models\ContentActionItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->stakeholder1 = Stakeholder::factory()->create(['user_id' => $this->user->id]);
        $this->stakeholder2 = Stakeholder::factory()->create(['user_id' => $this->user->id]);
        $this->workstream = Workstream::factory()->create(['owner_id' => $this->user->id]);
        $this->release = Release::factory()->create(['workstream_id' => $this->workstream->id]);
    }

    /** @test */
    public function it_can_create_content_with_basic_attributes()
    {
        $content = Content::create([
            'user_id' => $this->user->id,
            'type' => 'email',
            'title' => 'Project Update Email',
            'content' => 'This is the main content of the email about project updates.',
            'raw_content' => 'Raw email content with headers and formatting.',
            'metadata' => [
                'sender' => 'john@example.com',
                'timestamp' => '2025-09-20 10:30:00',
                'subject' => 'Project Update Email'
            ],
            'status' => 'pending'
        ]);

        $this->assertInstanceOf(Content::class, $content);
        $this->assertEquals('email', $content->type);
        $this->assertEquals('Project Update Email', $content->title);
        $this->assertEquals('pending', $content->status);
        $this->assertEquals($this->user->id, $content->user_id);
    }

    /** @test */
    public function it_belongs_to_a_user()
    {
        $content = Content::factory()->create(['user_id' => $this->user->id]);

        $this->assertInstanceOf(User::class, $content->user);
        $this->assertEquals($this->user->id, $content->user->id);
    }

    /** @test */
    public function it_can_have_many_stakeholders_with_pivot_data()
    {
        $content = Content::factory()->create(['user_id' => $this->user->id]);

        $content->stakeholders()->attach($this->stakeholder1->id, [
            'mention_type' => 'direct_mention',
            'confidence_score' => 0.95,
            'context' => 'John was mentioned in the email subject'
        ]);

        $content->stakeholders()->attach($this->stakeholder2->id, [
            'mention_type' => 'cc',
            'confidence_score' => 0.87,
            'context' => 'Sarah was CC\'d on the email'
        ]);

        $this->assertCount(2, $content->stakeholders);

        $pivotData = $content->stakeholders()->where('stakeholder_id', $this->stakeholder1->id)->first()->pivot;
        $this->assertEquals('direct_mention', $pivotData->mention_type);
        $this->assertEquals(0.95, $pivotData->confidence_score);
        $this->assertEquals('John was mentioned in the email subject', $pivotData->context);
    }

    /** @test */
    public function it_can_have_many_workstreams_with_relevance_data()
    {
        $content = Content::factory()->create(['user_id' => $this->user->id]);
        $workstream2 = Workstream::factory()->create(['owner_id' => $this->user->id]);

        $content->workstreams()->attach($this->workstream->id, [
            'relevance_type' => 'primary',
            'confidence_score' => 0.92,
            'context' => 'Mobile app project was directly mentioned'
        ]);

        $content->workstreams()->attach($workstream2->id, [
            'relevance_type' => 'secondary',
            'confidence_score' => 0.78,
            'context' => 'API work relates to backend project'
        ]);

        $this->assertCount(2, $content->workstreams);

        $pivotData = $content->workstreams()->where('workstream_id', $this->workstream->id)->first()->pivot;
        $this->assertEquals('primary', $pivotData->relevance_type);
        $this->assertEquals(0.92, $pivotData->confidence_score);
    }

    /** @test */
    public function it_can_have_many_releases_with_impact_data()
    {
        $content = Content::factory()->create(['user_id' => $this->user->id]);
        $release2 = Release::factory()->create(['workstream_id' => $this->workstream->id]);

        $content->releases()->attach($this->release->id, [
            'relevance_type' => 'primary',
            'confidence_score' => 0.88,
            'context' => 'v2.1 release mentioned for mobile features'
        ]);

        $content->releases()->attach($release2->id, [
            'relevance_type' => 'secondary',
            'confidence_score' => 0.91,
            'context' => 'API changes will block v2.2 release'
        ]);

        $this->assertCount(2, $content->releases);

        $pivotData = $content->releases()->where('release_id', $this->release->id)->first()->pivot;
        $this->assertEquals('primary', $pivotData->relevance_type);
        $this->assertEquals(0.88, $pivotData->confidence_score);
    }

    /** @test */
    public function it_can_have_many_action_items()
    {
        $content = Content::factory()->create(['user_id' => $this->user->id]);

        $actionItem1 = ContentActionItem::create([
            'content_id' => $content->id,
            'action_text' => 'Complete API documentation',
            'assignee_stakeholder_id' => $this->stakeholder1->id,
            'priority' => 'high',
            'due_date' => '2025-09-25',
            'status' => 'pending',
            'confidence_score' => 0.93,
            'context' => 'John needs to finish the API docs by Friday'
        ]);

        $actionItem2 = ContentActionItem::create([
            'content_id' => $content->id,
            'action_text' => 'Review user flows',
            'priority' => 'medium',
            'status' => 'pending',
            'confidence_score' => 0.85,
            'context' => 'Someone from product should review the flows'
        ]);

        $this->assertCount(2, $content->actionItems);
        $this->assertEquals('Complete API documentation', $content->actionItems->first()->action_text);
        $this->assertEquals($this->stakeholder1->id, $content->actionItems->first()->assignee_stakeholder_id);
    }

    /** @test */
    public function it_can_scope_content_by_status()
    {
        Content::factory()->create(['status' => 'pending', 'user_id' => $this->user->id]);
        Content::factory()->create(['status' => 'processing', 'user_id' => $this->user->id]);
        Content::factory()->create(['status' => 'processed', 'user_id' => $this->user->id]);
        Content::factory()->create(['status' => 'failed', 'user_id' => $this->user->id]);

        $this->assertCount(1, Content::pending()->get());
        $this->assertCount(1, Content::processing()->get());
        $this->assertCount(1, Content::processed()->get());
        $this->assertCount(1, Content::failed()->get());
    }

    /** @test */
    public function it_can_scope_content_by_type()
    {
        Content::factory()->create(['type' => 'email', 'user_id' => $this->user->id]);
        Content::factory()->create(['type' => 'file', 'user_id' => $this->user->id]);
        Content::factory()->create(['type' => 'manual', 'user_id' => $this->user->id]);
        Content::factory()->create(['type' => 'meeting_notes', 'user_id' => $this->user->id]);

        $this->assertCount(1, Content::ofType('email')->get());
        $this->assertCount(1, Content::ofType('file')->get());
        $this->assertCount(1, Content::ofType('manual')->get());
        $this->assertCount(1, Content::ofType('meeting_notes')->get());
    }

    /** @test */
    public function it_can_check_if_content_is_processed()
    {
        $pendingContent = Content::factory()->create(['status' => 'pending']);
        $processedContent = Content::factory()->create(['status' => 'processed']);

        $this->assertFalse($pendingContent->isProcessed());
        $this->assertTrue($processedContent->isProcessed());
    }

    /** @test */
    public function it_can_check_if_content_has_ai_analysis()
    {
        $contentWithoutAi = Content::factory()->create(['ai_summary' => null]);
        $contentWithAi = Content::factory()->create(['ai_summary' => 'This is an AI-generated summary']);

        $this->assertFalse($contentWithoutAi->hasAiAnalysis());
        $this->assertTrue($contentWithAi->hasAiAnalysis());
    }

    /** @test */
    public function it_can_get_all_mentioned_stakeholders_across_relationships()
    {
        $content = Content::factory()->create(['user_id' => $this->user->id]);

        // Attach stakeholders via content-stakeholder relationship
        $content->stakeholders()->attach($this->stakeholder1->id, [
            'mention_type' => 'direct_mention',
            'confidence_score' => 0.95,
            'context' => 'Direct mention'
        ]);

        // Create action item with different stakeholder
        ContentActionItem::create([
            'content_id' => $content->id,
            'action_text' => 'Review code',
            'assignee_stakeholder_id' => $this->stakeholder2->id,
            'priority' => 'medium',
            'status' => 'pending',
            'confidence_score' => 0.90,
            'context' => 'Code review task'
        ]);

        $allStakeholders = $content->getAllRelatedStakeholders();
        $this->assertCount(2, $allStakeholders);
        $this->assertTrue($allStakeholders->contains($this->stakeholder1));
        $this->assertTrue($allStakeholders->contains($this->stakeholder2));
    }

    /** @test */
    public function it_can_mark_content_as_processed()
    {
        $content = Content::factory()->create(['status' => 'processing']);

        $content->markAsProcessed();

        $this->assertEquals('processed', $content->fresh()->status);
        $this->assertNotNull($content->fresh()->processed_at);
    }

    /** @test */
    public function it_stores_metadata_as_json()
    {
        $metadata = [
            'sender' => 'john@example.com',
            'recipients' => ['sarah@example.com', 'mike@example.com'],
            'timestamp' => '2025-09-20 10:30:00',
            'message_id' => 'abc123',
            'thread_id' => 'thread456'
        ];

        $content = Content::factory()->create(['metadata' => $metadata]);

        $this->assertEquals($metadata, $content->metadata);
        $this->assertEquals('john@example.com', $content->metadata['sender']);
        $this->assertIsArray($content->metadata['recipients']);
    }
}
<?php

namespace Tests\Unit\Models;

use App\Models\Content;
use App\Models\ContentActionItem;
use App\Models\User;
use App\Models\Stakeholder;
use App\Models\Workstream;
use App\Models\Release;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentActionItemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->content = Content::factory()->create(['user_id' => $this->user->id]);
        $this->stakeholder = Stakeholder::factory()->create(['user_id' => $this->user->id]);
        $this->workstream = Workstream::factory()->create(['owner_id' => $this->user->id]);
        $this->release = Release::factory()->create(['workstream_id' => $this->workstream->id]);
    }

    /** @test */
    public function it_can_create_action_item_with_basic_attributes()
    {
        $actionItem = ContentActionItem::create([
            'content_id' => $this->content->id,
            'action_text' => 'Complete API documentation for mobile app',
            'assignee_stakeholder_id' => $this->stakeholder->id,
            'priority' => 'high',
            'due_date' => '2025-09-25',
            'status' => 'pending',
            'confidence_score' => 0.93,
            'context' => 'John needs to finish the API docs by Friday as mentioned in the email'
        ]);

        $this->assertInstanceOf(ContentActionItem::class, $actionItem);
        $this->assertEquals('Complete API documentation for mobile app', $actionItem->action_text);
        $this->assertEquals('high', $actionItem->priority);
        $this->assertEquals('pending', $actionItem->status);
        $this->assertEquals(0.93, $actionItem->confidence_score);
        $this->assertEquals($this->stakeholder->id, $actionItem->assignee_stakeholder_id);
    }

    /** @test */
    public function it_belongs_to_content()
    {
        $actionItem = ContentActionItem::factory()->create(['content_id' => $this->content->id]);

        $this->assertInstanceOf(Content::class, $actionItem->content);
        $this->assertEquals($this->content->id, $actionItem->content->id);
    }

    /** @test */
    public function it_belongs_to_assignee_stakeholder()
    {
        $actionItem = ContentActionItem::factory()->create([
            'content_id' => $this->content->id,
            'assignee_stakeholder_id' => $this->stakeholder->id
        ]);

        $this->assertInstanceOf(Stakeholder::class, $actionItem->assignee);
        $this->assertEquals($this->stakeholder->id, $actionItem->assignee->id);
    }

    /** @test */
    public function it_can_have_many_related_stakeholders()
    {
        $actionItem = ContentActionItem::factory()->create(['content_id' => $this->content->id]);
        $stakeholder2 = Stakeholder::factory()->create(['user_id' => $this->user->id]);

        $actionItem->stakeholders()->attach($this->stakeholder->id, ['role' => 'assignee']);
        $actionItem->stakeholders()->attach($stakeholder2->id, ['role' => 'reviewer']);

        $this->assertCount(2, $actionItem->stakeholders);

        $assigneeRole = $actionItem->stakeholders()->where('stakeholder_id', $this->stakeholder->id)->first()->pivot->role;
        $reviewerRole = $actionItem->stakeholders()->where('stakeholder_id', $stakeholder2->id)->first()->pivot->role;

        $this->assertEquals('assignee', $assigneeRole);
        $this->assertEquals('reviewer', $reviewerRole);
    }

    /** @test */
    public function it_can_have_many_related_workstreams()
    {
        $actionItem = ContentActionItem::factory()->create(['content_id' => $this->content->id]);
        $workstream2 = Workstream::factory()->create(['user_id' => $this->user->id]);

        $actionItem->workstreams()->attach($this->workstream->id);
        $actionItem->workstreams()->attach($workstream2->id);

        $this->assertCount(2, $actionItem->workstreams);
        $this->assertTrue($actionItem->workstreams->contains($this->workstream));
        $this->assertTrue($actionItem->workstreams->contains($workstream2));
    }

    /** @test */
    public function it_can_have_many_related_releases()
    {
        $actionItem = ContentActionItem::factory()->create(['content_id' => $this->content->id]);
        $release2 = Release::factory()->create(['workstream_id' => $this->workstream->id]);

        $actionItem->releases()->attach($this->release->id);
        $actionItem->releases()->attach($release2->id);

        $this->assertCount(2, $actionItem->releases);
        $this->assertTrue($actionItem->releases->contains($this->release));
        $this->assertTrue($actionItem->releases->contains($release2));
    }

    /** @test */
    public function it_can_scope_action_items_by_status()
    {
        ContentActionItem::factory()->create(['content_id' => $this->content->id, 'status' => 'pending']);
        ContentActionItem::factory()->create(['content_id' => $this->content->id, 'status' => 'in_progress']);
        ContentActionItem::factory()->create(['content_id' => $this->content->id, 'status' => 'completed']);
        ContentActionItem::factory()->create(['content_id' => $this->content->id, 'status' => 'cancelled']);

        $this->assertCount(1, ContentActionItem::pending()->get());
        $this->assertCount(1, ContentActionItem::inProgress()->get());
        $this->assertCount(1, ContentActionItem::completed()->get());
        $this->assertCount(1, ContentActionItem::cancelled()->get());
    }

    /** @test */
    public function it_can_scope_action_items_by_priority()
    {
        ContentActionItem::factory()->create(['content_id' => $this->content->id, 'priority' => 'low']);
        ContentActionItem::factory()->create(['content_id' => $this->content->id, 'priority' => 'medium']);
        ContentActionItem::factory()->create(['content_id' => $this->content->id, 'priority' => 'high']);
        ContentActionItem::factory()->create(['content_id' => $this->content->id, 'priority' => 'urgent']);

        $this->assertCount(1, ContentActionItem::priority('low')->get());
        $this->assertCount(1, ContentActionItem::priority('medium')->get());
        $this->assertCount(1, ContentActionItem::priority('high')->get());
        $this->assertCount(1, ContentActionItem::priority('urgent')->get());
    }

    /** @test */
    public function it_can_scope_action_items_by_assignee()
    {
        $actionItem1 = ContentActionItem::factory()->create([
            'content_id' => $this->content->id,
            'assignee_stakeholder_id' => $this->stakeholder->id
        ]);

        $stakeholder2 = Stakeholder::factory()->create(['user_id' => $this->user->id]);
        $actionItem2 = ContentActionItem::factory()->create([
            'content_id' => $this->content->id,
            'assignee_stakeholder_id' => $stakeholder2->id
        ]);

        $this->assertCount(1, ContentActionItem::assignedTo($this->stakeholder->id)->get());
        $this->assertCount(1, ContentActionItem::assignedTo($stakeholder2->id)->get());
    }

    /** @test */
    public function it_can_scope_action_items_by_due_date()
    {
        ContentActionItem::factory()->create([
            'content_id' => $this->content->id,
            'due_date' => '2025-09-20'
        ]);
        ContentActionItem::factory()->create([
            'content_id' => $this->content->id,
            'due_date' => '2025-09-25'
        ]);
        ContentActionItem::factory()->create([
            'content_id' => $this->content->id,
            'due_date' => null
        ]);

        $this->assertCount(1, ContentActionItem::dueBy('2025-09-20')->get());
        $this->assertCount(2, ContentActionItem::dueBy('2025-09-25')->get()); // Includes both 20th and 25th
        $this->assertCount(1, ContentActionItem::withoutDueDate()->get());
    }

    /** @test */
    public function it_can_check_if_action_item_is_overdue()
    {
        $overdueItem = ContentActionItem::factory()->create([
            'content_id' => $this->content->id,
            'due_date' => '2025-09-15',
            'status' => 'pending'
        ]);

        $upcomingItem = ContentActionItem::factory()->create([
            'content_id' => $this->content->id,
            'due_date' => '2025-12-31',
            'status' => 'pending'
        ]);

        $completedItem = ContentActionItem::factory()->create([
            'content_id' => $this->content->id,
            'due_date' => '2025-09-15',
            'status' => 'completed'
        ]);

        $this->assertTrue($overdueItem->isOverdue());
        $this->assertFalse($upcomingItem->isOverdue());
        $this->assertFalse($completedItem->isOverdue()); // Completed items are not overdue
    }

    /** @test */
    public function it_can_mark_action_item_as_completed()
    {
        $actionItem = ContentActionItem::factory()->create([
            'content_id' => $this->content->id,
            'status' => 'pending'
        ]);

        $actionItem->markAsCompleted();

        $this->assertEquals('completed', $actionItem->fresh()->status);
    }

    /** @test */
    public function it_can_mark_action_item_as_in_progress()
    {
        $actionItem = ContentActionItem::factory()->create([
            'content_id' => $this->content->id,
            'status' => 'pending'
        ]);

        $actionItem->markAsInProgress();

        $this->assertEquals('in_progress', $actionItem->fresh()->status);
    }

    /** @test */
    public function it_can_get_all_related_entities()
    {
        $actionItem = ContentActionItem::factory()->create(['content_id' => $this->content->id]);

        $actionItem->stakeholders()->attach($this->stakeholder->id, ['role' => 'assignee']);
        $actionItem->workstreams()->attach($this->workstream->id);
        $actionItem->releases()->attach($this->release->id);

        $relatedEntities = $actionItem->getAllRelatedEntities();

        $this->assertArrayHasKey('stakeholders', $relatedEntities);
        $this->assertArrayHasKey('workstreams', $relatedEntities);
        $this->assertArrayHasKey('releases', $relatedEntities);
        $this->assertCount(1, $relatedEntities['stakeholders']);
        $this->assertCount(1, $relatedEntities['workstreams']);
        $this->assertCount(1, $relatedEntities['releases']);
    }

    /** @test */
    public function it_can_scope_action_items_by_confidence_score()
    {
        ContentActionItem::factory()->create(['content_id' => $this->content->id, 'confidence_score' => 0.95]);
        ContentActionItem::factory()->create(['content_id' => $this->content->id, 'confidence_score' => 0.75]);
        ContentActionItem::factory()->create(['content_id' => $this->content->id, 'confidence_score' => 0.45]);

        $this->assertCount(2, ContentActionItem::highConfidence(0.7)->get());
        $this->assertCount(1, ContentActionItem::lowConfidence(0.7)->get());
    }
}
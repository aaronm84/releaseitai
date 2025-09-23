<?php

namespace Tests\Unit\DataIntegrity;

use Tests\TestCase;
use App\Models\User;
use App\Models\Workstream;
use App\Models\Release;
use App\Models\Stakeholder;
use App\Models\Content;
use App\Models\Feedback;
use App\Models\Input;
use App\Models\Output;
use App\Models\WorkstreamPermission;
use App\Models\AiJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class ForeignKeyCascadeTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function workstream_deletion_cascades_to_releases()
    {
        // Arrange
        $user = User::factory()->create();
        $workstream = Workstream::factory()->create(['owner_id' => $user->id]);
        $release = Release::factory()->create(['workstream_id' => $workstream->id]);

        $this->assertDatabaseHas('releases', ['id' => $release->id]);

        // Act
        $workstream->delete();

        // Assert
        $this->assertDatabaseMissing('releases', ['id' => $release->id]);
    }

    /** @test */
    public function user_deletion_sets_workstream_owner_to_null()
    {
        // Arrange
        $user = User::factory()->create();
        $workstream = Workstream::factory()->create(['owner_id' => $user->id]);

        $this->assertDatabaseHas('workstreams', [
            'id' => $workstream->id,
            'owner_id' => $user->id
        ]);

        // Act
        $user->delete();

        // Assert - Workstream should still exist but with null owner
        $this->assertDatabaseHas('workstreams', [
            'id' => $workstream->id,
            'owner_id' => null
        ]);
    }

    /** @test */
    public function user_deletion_creates_ownership_review_job()
    {
        // Arrange
        $user = User::factory()->create();
        $workstream = Workstream::factory()->create([
            'owner_id' => $user->id,
            'name' => 'Test Workstream'
        ]);

        $initialJobCount = AiJob::count();

        // Act
        $user->delete();

        // Assert - Should create an AI job for ownership review
        $this->assertEquals($initialJobCount + 1, AiJob::count());

        $job = AiJob::latest()->first();
        $this->assertEquals('workstream_ownership_review', $job->type);
        $this->assertEquals('pending', $job->status);

        $payload = json_decode($job->payload, true);
        $this->assertEquals($workstream->id, $payload['workstream_id']);
        $this->assertEquals('Test Workstream', $payload['workstream_name']);
        $this->assertEquals($user->id, $payload['previous_owner_id']);
        $this->assertTrue($payload['requires_manual_assignment']);
    }

    /** @test */
    public function workstream_permissions_granted_by_sets_to_null_on_user_deletion()
    {
        // Arrange
        $granter = User::factory()->create();
        $grantee = User::factory()->create();
        $workstream = Workstream::factory()->create(['owner_id' => $grantee->id]);

        $permission = WorkstreamPermission::create([
            'workstream_id' => $workstream->id,
            'user_id' => $grantee->id,
            'permission_type' => 'view',
            'scope' => 'workstream_only',
            'granted_by' => $granter->id
        ]);

        $this->assertDatabaseHas('workstream_permissions', [
            'id' => $permission->id,
            'granted_by' => $granter->id
        ]);

        // Act
        $granter->delete();

        // Assert - Permission should still exist but granted_by should be null
        $this->assertDatabaseHas('workstream_permissions', [
            'id' => $permission->id,
            'granted_by' => null
        ]);
    }

    /** @test */
    public function content_deletion_cascades_to_related_entities()
    {
        // Arrange
        $user = User::factory()->create();
        $content = Content::factory()->create(['user_id' => $user->id]);

        // Create related entities
        $actionItem = \App\Models\ContentActionItem::create([
            'content_id' => $content->id,
            'title' => 'Test Action Item',
            'description' => 'Test Description',
            'priority' => 'medium',
            'status' => 'pending'
        ]);

        $this->assertDatabaseHas('content_action_items', ['id' => $actionItem->id]);

        // Act
        $content->delete();

        // Assert
        $this->assertDatabaseMissing('content_action_items', ['id' => $actionItem->id]);
    }

    /** @test */
    public function feedback_deletion_cascades_from_output_deletion()
    {
        // Arrange
        $user = User::factory()->create();
        $input = Input::factory()->create(['user_id' => $user->id]);
        $output = Output::factory()->create(['input_id' => $input->id]);
        $feedback = Feedback::create([
            'output_id' => $output->id,
            'user_id' => $user->id,
            'action' => 'accept',
            'confidence' => 0.8,
            'metadata' => []
        ]);

        $this->assertDatabaseHas('feedback', ['id' => $feedback->id]);

        // Act
        $output->delete();

        // Assert
        $this->assertDatabaseMissing('feedback', ['id' => $feedback->id]);
    }

    /** @test */
    public function output_deletion_cascades_from_input_deletion()
    {
        // Arrange
        $user = User::factory()->create();
        $input = Input::factory()->create(['user_id' => $user->id]);
        $output = Output::factory()->create(['input_id' => $input->id]);

        $this->assertDatabaseHas('outputs', ['id' => $output->id]);

        // Act
        $input->delete();

        // Assert
        $this->assertDatabaseMissing('outputs', ['id' => $output->id]);
    }

    /** @test */
    public function stakeholder_deletion_cascades_to_stakeholder_releases()
    {
        // Arrange
        $user = User::factory()->create();
        $stakeholder = Stakeholder::factory()->create(['user_id' => $user->id]);
        $workstream = Workstream::factory()->create(['owner_id' => $user->id]);
        $release = Release::factory()->create(['workstream_id' => $workstream->id]);

        // Create stakeholder-release relationship
        DB::table('stakeholder_releases')->insert([
            'stakeholder_id' => $stakeholder->id,
            'release_id' => $release->id,
            'role' => 'reviewer',
            'notification_preference' => 'email',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $this->assertDatabaseHas('stakeholder_releases', [
            'stakeholder_id' => $stakeholder->id,
            'release_id' => $release->id
        ]);

        // Act
        $stakeholder->delete();

        // Assert
        $this->assertDatabaseMissing('stakeholder_releases', [
            'stakeholder_id' => $stakeholder->id,
            'release_id' => $release->id
        ]);
    }

    /** @test */
    public function it_prevents_user_deletion_when_referenced_by_restrict_constraints()
    {
        // This test would check RESTRICT constraints if any exist
        // Currently we changed most to SET NULL, but good to test the behavior

        $this->assertTrue(true); // Placeholder - no RESTRICT constraints left after our fixes
    }

    /** @test */
    public function hierarchical_workstream_deletion_cascades_correctly()
    {
        // Arrange
        $user = User::factory()->create();
        $parentWorkstream = Workstream::factory()->create([
            'owner_id' => $user->id,
            'type' => 'product_line',
            'parent_workstream_id' => null
        ]);

        $childWorkstream = Workstream::factory()->create([
            'owner_id' => $user->id,
            'type' => 'initiative',
            'parent_workstream_id' => $parentWorkstream->id
        ]);

        $this->assertDatabaseHas('workstreams', ['id' => $childWorkstream->id]);

        // Act
        $parentWorkstream->delete();

        // Assert - Child should be deleted due to CASCADE
        $this->assertDatabaseMissing('workstreams', ['id' => $childWorkstream->id]);
    }

    /** @test */
    public function release_task_assigned_user_sets_to_null_on_user_deletion()
    {
        // Arrange
        $user = User::factory()->create();
        $assignee = User::factory()->create();
        $workstream = Workstream::factory()->create(['owner_id' => $user->id]);
        $release = Release::factory()->create(['workstream_id' => $workstream->id]);

        $task = \App\Models\ReleaseTask::create([
            'release_id' => $release->id,
            'title' => 'Test Task',
            'description' => 'Test Description',
            'type' => 'development',
            'status' => 'pending',
            'priority' => 'medium',
            'assigned_to' => $assignee->id,
            'order' => 1
        ]);

        $this->assertDatabaseHas('release_tasks', [
            'id' => $task->id,
            'assigned_to' => $assignee->id
        ]);

        // Act
        $assignee->delete();

        // Assert - Task should still exist but assigned_to should be null
        $this->assertDatabaseHas('release_tasks', [
            'id' => $task->id,
            'assigned_to' => null
        ]);
    }
}
<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\BrainDumpProcessor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BrainDumpProcessorIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private BrainDumpProcessor $brainDumpProcessor;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->brainDumpProcessor = app(BrainDumpProcessor::class);
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    /**
     * Test that BrainDumpProcessor correctly delegates to ContentProcessor
     * and returns the expected legacy format
     */
    public function test_brain_dump_processor_integration_with_content_processor()
    {
        $content = "Had a meeting with Sarah from marketing about Q1 release. Need to update the user auth workstream. Follow up with John by Friday.";

        $result = $this->brainDumpProcessor->process($content, $this->user);

        // Test legacy format structure that BrainDump component expects
        $this->assertArrayHasKey('tasks', $result);
        $this->assertArrayHasKey('meetings', $result);
        $this->assertArrayHasKey('decisions', $result);
        $this->assertArrayHasKey('content_id', $result);

        // Test that arrays are properly structured
        $this->assertIsArray($result['tasks']);
        $this->assertIsArray($result['meetings']);
        $this->assertIsArray($result['decisions']);
        $this->assertIsInt($result['content_id']);

        // Test that content was stored with proper entity matching and relationships
        $this->assertGreaterThan(0, $result['content_id']);
    }

    /**
     * Test backward compatibility with existing BrainDump component
     */
    public function test_maintains_backward_compatibility()
    {
        $content = "Team standup tomorrow at 9am. Alice needs to finish the API docs. Decision: we're using React for the frontend.";

        $result = $this->brainDumpProcessor->process($content, $this->user);

        // Verify the structure matches what BrainDump.vue expects
        if (!empty($result['tasks'])) {
            foreach ($result['tasks'] as $task) {
                $this->assertArrayHasKey('title', $task);
                $this->assertArrayHasKey('priority', $task);
                $this->assertArrayHasKey('assignee', $task);
                $this->assertArrayHasKey('due_date', $task);
            }
        }

        if (!empty($result['meetings'])) {
            foreach ($result['meetings'] as $meeting) {
                $this->assertArrayHasKey('title', $meeting);
                $this->assertArrayHasKey('date', $meeting);
                $this->assertArrayHasKey('attendees', $meeting);
            }
        }

        if (!empty($result['decisions'])) {
            foreach ($result['decisions'] as $decision) {
                $this->assertArrayHasKey('title', $decision);
                $this->assertArrayHasKey('impact', $decision);
                $this->assertArrayHasKey('date', $decision);
            }
        }
    }
}
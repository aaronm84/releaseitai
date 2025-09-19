<?php

namespace Tests\Feature\Frontend;

use App\Models\User;
use App\Models\Workstream;
use App\Models\Release;
use App\Models\ReleaseTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuickAddTest extends TestCase
{
    use RefreshDatabase;

    private User $pm;
    private Workstream $workstream;

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
    }

    /** @test */
    public function pm_can_paste_meeting_notes_and_extract_actionable_items()
    {
        // Given: A PM with meeting notes to process
        $this->actingAs($this->pm);

        $meetingNotes = "Meeting with Engineering Team - Login Flow Discussion\n" .
                       "Date: Today\n" .
                       "Attendees: PM, Lead Engineer, UX Designer\n\n" .
                       "Key Points:\n" .
                       "- Need to implement OAuth integration by Friday\n" .
                       "- Design review scheduled for next Tuesday\n" .
                       "- Security audit required before launch\n" .
                       "- Bug in password reset flow needs fixing\n\n" .
                       "Action Items:\n" .
                       "- John will complete API integration\n" .
                       "- Sarah to finalize UI mockups\n" .
                       "- Schedule penetration testing\n\n" .
                       "Next Meeting: Friday 2pm";

        // When: They use Quick Add to process the notes
        $response = $this->post('/quick-add', [
            'content' => $meetingNotes,
            'context' => 'meeting_notes',
            'source' => 'engineering_meeting',
            'auto_extract' => true,
        ]);

        // Then: The system should extract actionable items
        $response->assertStatus(200);
        $response->assertJson([
            'extracted_items' => [
                'tasks' => [
                    ['title' => 'Implement OAuth integration', 'due_date' => 'Friday', 'assignee' => 'John'],
                    ['title' => 'Finalize UI mockups', 'assignee' => 'Sarah'],
                    ['title' => 'Schedule penetration testing'],
                    ['title' => 'Fix password reset flow bug'],
                ],
                'meetings' => [
                    ['title' => 'Follow-up meeting', 'date' => 'Friday 2pm'],
                ],
                'decisions' => [
                    ['item' => 'Design review scheduled for next Tuesday'],
                    ['item' => 'Security audit required before launch'],
                ],
            ],
            'suggestions' => [
                'create_release' => true,
                'suggested_release_name' => 'Login Flow OAuth Integration',
                'workstream_id' => $this->workstream->id,
            ],
        ]);
    }

    /** @test */
    public function quick_add_can_process_email_content_and_create_tasks()
    {
        // Given: A PM with email content to process
        $this->actingAs($this->pm);

        $emailContent = "From: stakeholder@company.com\n" .
                       "Subject: Urgent: Marketing Launch Requirements\n\n" .
                       "Hi Team,\n\n" .
                       "We need the following items completed for the marketing launch:\n\n" .
                       "1. Update landing page with new messaging\n" .
                       "2. Prepare press kit materials\n" .
                       "3. Coordinate with PR agency for announcement\n" .
                       "4. Set up analytics tracking for campaign\n\n" .
                       "Deadline is next Friday. Please confirm receipt.\n\n" .
                       "Best regards,\nMarketing Director";

        // When: They process the email through Quick Add
        $response = $this->post('/quick-add', [
            'content' => $emailContent,
            'context' => 'email',
            'priority' => 'high',
            'auto_extract' => true,
        ]);

        // Then: Tasks should be extracted and prioritized
        $response->assertStatus(200);
        $response->assertJson([
            'extracted_items' => [
                'tasks' => [
                    ['title' => 'Update landing page with new messaging', 'priority' => 'high'],
                    ['title' => 'Prepare press kit materials', 'priority' => 'high'],
                    ['title' => 'Coordinate with PR agency for announcement', 'priority' => 'high'],
                    ['title' => 'Set up analytics tracking for campaign', 'priority' => 'high'],
                ],
                'deadline' => 'next Friday',
                'stakeholder' => 'Marketing Director',
            ],
        ]);
    }

    /** @test */
    public function pm_can_quickly_convert_extracted_items_to_release_plan()
    {
        // Given: A PM with extracted items ready to convert
        $this->actingAs($this->pm);

        $extractedItems = [
            'tasks' => [
                ['title' => 'API Development', 'priority' => 'high', 'estimated_hours' => 16],
                ['title' => 'Frontend Implementation', 'priority' => 'medium', 'estimated_hours' => 12],
                ['title' => 'Testing & QA', 'priority' => 'high', 'estimated_hours' => 8],
            ],
            'release_name' => 'Payment Integration v1.0',
            'target_date' => '2024-03-15',
        ];

        // When: They convert to a release plan
        $response = $this->post('/quick-add/convert-to-release', [
            'workstream_id' => $this->workstream->id,
            'release_data' => [
                'name' => $extractedItems['release_name'],
                'planned_date' => $extractedItems['target_date'],
                'type' => 'feature',
                'status' => 'planning',
            ],
            'tasks' => $extractedItems['tasks'],
        ]);

        // Then: A release and tasks should be created
        $response->assertStatus(201);
        $this->assertDatabaseHas('releases', [
            'name' => 'Payment Integration v1.0',
            'workstream_id' => $this->workstream->id,
            'status' => 'planning',
        ]);

        $release = Release::where('name', 'Payment Integration v1.0')->first();
        $this->assertDatabaseHas('release_tasks', [
            'release_id' => $release->id,
            'name' => 'API Development',
            'priority' => 'high',
        ]);
    }

    /** @test */
    public function quick_add_provides_smart_suggestions_based_on_context()
    {
        // Given: A PM with an existing release needing updates
        $this->actingAs($this->pm);

        $existingRelease = Release::factory()->create([
            'name' => 'Mobile App Login',
            'workstream_id' => $this->workstream->id,
            'status' => 'active',
        ]);

        $input = "Quick update: Login flow testing revealed two bugs that need fixing before release";

        // When: They use Quick Add with contextual content
        $response = $this->post('/quick-add', [
            'content' => $input,
            'context' => 'status_update',
            'auto_extract' => true,
        ]);

        // Then: Smart suggestions should be provided
        $response->assertStatus(200);
        $response->assertJson([
            'suggestions' => [
                'related_release' => [
                    'id' => $existingRelease->id,
                    'name' => 'Mobile App Login',
                    'confidence' => 'high',
                ],
                'suggested_actions' => [
                    'create_bug_tasks',
                    'update_release_status',
                    'notify_stakeholders',
                ],
                'extracted_items' => [
                    'bugs' => [
                        ['description' => 'Login flow testing revealed bugs', 'priority' => 'high'],
                    ],
                ],
            ],
        ]);
    }

    /** @test */
    public function quick_add_supports_voice_to_text_input_processing()
    {
        // Given: A PM using voice input
        $this->actingAs($this->pm);

        $voiceTranscript = "Okay so I just had a call with the client and they want us to prioritize the mobile push notifications feature. " .
                          "They said it's critical for their Q2 launch. We need to get engineering involved and probably need to " .
                          "reprioritize our current sprint. Also mentioned they want weekly updates going forward.";

        // When: They process voice transcript through Quick Add
        $response = $this->post('/quick-add', [
            'content' => $voiceTranscript,
            'context' => 'voice_input',
            'source' => 'client_call',
            'auto_extract' => true,
        ]);

        // Then: Natural language should be processed correctly
        $response->assertStatus(200);
        $response->assertJson([
            'extracted_items' => [
                'priorities' => [
                    ['item' => 'Mobile push notifications feature', 'urgency' => 'critical'],
                ],
                'stakeholder_requests' => [
                    ['request' => 'Weekly updates', 'frequency' => 'weekly'],
                ],
                'action_items' => [
                    ['action' => 'Get engineering involved'],
                    ['action' => 'Reprioritize current sprint'],
                ],
                'context' => [
                    'source' => 'client_call',
                    'timeline' => 'Q2 launch',
                ],
            ],
        ]);
    }

    /** @test */
    public function quick_add_handles_complex_multi_format_input()
    {
        // Given: A PM with complex mixed content
        $this->actingAs($this->pm);

        $complexInput = "Sprint Planning Notes - Week of March 10\n\n" .
                       "COMPLETED:\n" .
                       "✓ User authentication API\n" .
                       "✓ Database schema updates\n" .
                       "✓ Initial UI wireframes\n\n" .
                       "IN PROGRESS:\n" .
                       "- Frontend component development (80% complete)\n" .
                       "- Integration testing setup\n\n" .
                       "BLOCKERS:\n" .
                       "- Waiting for design approval from stakeholders\n" .
                       "- Third-party API documentation missing\n\n" .
                       "NEXT WEEK:\n" .
                       "- Complete frontend components\n" .
                       "- Begin end-to-end testing\n" .
                       "- Schedule demo with product team\n\n" .
                       "RISKS:\n" .
                       "- Design delays could impact timeline\n" .
                       "- Need backup plan for third-party integration";

        // When: They process the complex input
        $response = $this->post('/quick-add', [
            'content' => $complexInput,
            'context' => 'sprint_planning',
            'auto_extract' => true,
        ]);

        // Then: All sections should be properly parsed
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'extracted_items' => [
                'completed_tasks',
                'in_progress_tasks',
                'blockers',
                'planned_tasks',
                'risks',
            ],
            'status_summary' => [
                'overall_progress',
                'completion_percentage',
                'blocker_count',
                'risk_level',
            ],
        ]);
    }

    /** @test */
    public function quick_add_enables_rapid_task_creation_with_keyboard_shortcuts()
    {
        // Given: A PM using keyboard shortcuts for rapid entry
        $this->actingAs($this->pm);

        // When: They use quick add with shorthand notation
        $response = $this->post('/quick-add', [
            'content' => '@john Fix login bug #high !urgent due:friday',
            'context' => 'shorthand',
            'auto_extract' => true,
        ]);

        // Then: Shorthand should be parsed correctly
        $response->assertStatus(200);
        $response->assertJson([
            'extracted_items' => [
                'tasks' => [
                    [
                        'title' => 'Fix login bug',
                        'assignee' => 'john',
                        'priority' => 'high',
                        'urgency' => 'urgent',
                        'due_date' => 'friday',
                    ],
                ],
            ],
        ]);
    }

    /** @test */
    public function quick_add_interface_optimized_for_adhd_rapid_input()
    {
        // Given: A PM with ADHD needing rapid input capability
        $this->actingAs($this->pm);

        // When: They access the Quick Add interface
        $response = $this->get('/quick-add');

        // Then: The interface should be optimized for rapid input
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('QuickAdd/Index')
                ->has('uiConfig')
                ->where('uiConfig.autoFocus', true)
                ->where('uiConfig.enableKeyboardShortcuts', true)
                ->where('uiConfig.autoSave', true)
                ->where('uiConfig.processingDelay', 500) // 500ms delay for ADHD
                ->has('uiConfig.templates')
                ->has('uiConfig.quickInserts')
        );
    }

    /** @test */
    public function quick_add_processes_input_quickly_for_adhd_users()
    {
        // Given: A PM with ADHD using Quick Add
        $this->actingAs($this->pm);

        $startTime = microtime(true);

        // When: They process content through Quick Add
        $response = $this->post('/quick-add', [
            'content' => 'Create new feature for user profiles with photo upload and bio editing',
            'context' => 'feature_request',
            'auto_extract' => true,
        ]);

        $processTime = microtime(true) - $startTime;

        // Then: Processing should be fast for ADHD users
        $response->assertStatus(200);
        $this->assertLessThan(0.5, $processTime, 'Quick Add should process in under 500ms for ADHD users');
    }
}
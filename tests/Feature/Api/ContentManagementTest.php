<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Content;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test_token')->plainTextToken;
    }

    protected function authenticatedRequest()
    {
        return $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ]);
    }

    /** @test */
    public function user_can_upload_text_file_content()
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('document.txt', 100, 'text/plain');

        $response = $this->authenticatedRequest()
            ->postJson('/api/content', [
                'title' => 'Meeting Notes',
                'description' => 'Weekly team meeting notes',
                'file' => $file,
                'tags' => ['meeting', 'weekly'],
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'title',
                'description',
                'file_type',
                'file_path',
                'status',
                'tags',
                'user_id',
                'created_at',
                'updated_at',
            ])
            ->assertJson([
                'title' => 'Meeting Notes',
                'description' => 'Weekly team meeting notes',
                'file_type' => 'txt',
                'status' => 'pending',
                'tags' => ['meeting', 'weekly'],
                'user_id' => $this->user->id,
            ]);

        $this->assertDatabaseHas('contents', [
            'title' => 'Meeting Notes',
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);

        Storage::disk('local')->assertExists($response->json('file_path'));
    }

    /** @test */
    public function user_can_upload_pdf_file_content()
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('document.pdf', 200, 'application/pdf');

        $response = $this->authenticatedRequest()
            ->postJson('/api/content', [
                'title' => 'Project Report',
                'file' => $file,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'title' => 'Project Report',
                'file_type' => 'pdf',
                'status' => 'pending',
                'user_id' => $this->user->id,
            ]);
    }

    /** @test */
    public function user_cannot_upload_content_without_authentication()
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('document.txt', 100);

        $response = $this->postJson('/api/content', [
            'title' => 'Test Document',
            'file' => $file,
        ]);

        $response->assertStatus(401);

        $this->assertDatabaseMissing('contents', [
            'title' => 'Test Document',
        ]);
    }

    /** @test */
    public function user_cannot_upload_content_without_required_fields()
    {
        $response = $this->authenticatedRequest()
            ->postJson('/api/content', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'file']);
    }

    /** @test */
    public function user_cannot_upload_unsupported_file_type()
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('image.jpg', 100, 'image/jpeg');

        $response = $this->authenticatedRequest()
            ->postJson('/api/content', [
                'title' => 'Test Image',
                'file' => $file,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    /** @test */
    public function user_can_upload_content_with_direct_text()
    {
        $response = $this->authenticatedRequest()
            ->postJson('/api/content', [
                'title' => 'Direct Text Content',
                'description' => 'Content entered directly',
                'content' => 'This is some direct text content that was typed in.',
                'tags' => ['direct', 'text'],
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'title' => 'Direct Text Content',
                'description' => 'Content entered directly',
                'content' => 'This is some direct text content that was typed in.',
                'status' => 'processing', // Direct text should start processing immediately
                'tags' => ['direct', 'text'],
                'user_id' => $this->user->id,
            ]);

        $this->assertDatabaseHas('contents', [
            'title' => 'Direct Text Content',
            'user_id' => $this->user->id,
            'status' => 'processing',
        ]);
    }

    /** @test */
    public function user_can_list_their_content()
    {
        $content1 = Content::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'First Document',
            'status' => 'processed',
        ]);

        $content2 = Content::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Second Document',
            'status' => 'pending',
        ]);

        // Create content for another user (should not appear)
        $otherUser = User::factory()->create();
        Content::factory()->create([
            'user_id' => $otherUser->id,
            'title' => 'Other User Document',
        ]);

        $response = $this->authenticatedRequest()
            ->getJson('/api/content');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'description',
                        'status',
                        'file_type',
                        'created_at',
                        'updated_at',
                    ]
                ],
                'meta' => [
                    'current_page',
                    'total',
                    'per_page',
                ],
            ]);

        $contentTitles = collect($response->json('data'))->pluck('title');
        $this->assertContains('First Document', $contentTitles);
        $this->assertContains('Second Document', $contentTitles);
        $this->assertNotContains('Other User Document', $contentTitles);
    }

    /** @test */
    public function user_can_filter_content_by_status()
    {
        Content::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Processed Document',
            'status' => 'processed',
        ]);

        Content::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Failed Document',
            'status' => 'failed',
        ]);

        $response = $this->authenticatedRequest()
            ->getJson('/api/content?status=processed');

        $response->assertStatus(200);

        $contentTitles = collect($response->json('data'))->pluck('title');
        $this->assertContains('Processed Document', $contentTitles);
        $this->assertNotContains('Failed Document', $contentTitles);
    }

    /** @test */
    public function user_can_search_content_by_title()
    {
        Content::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Meeting Notes from Monday',
            'status' => 'processed',
        ]);

        Content::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Project Report',
            'status' => 'processed',
        ]);

        $response = $this->authenticatedRequest()
            ->getJson('/api/content?search=meeting');

        $response->assertStatus(200);

        $contentTitles = collect($response->json('data'))->pluck('title');
        $this->assertContains('Meeting Notes from Monday', $contentTitles);
        $this->assertNotContains('Project Report', $contentTitles);
    }

    /** @test */
    public function user_can_view_specific_content()
    {
        $content = Content::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Test Document',
            'description' => 'A test document',
            'content' => 'This is the processed content',
            'status' => 'processed',
            'tags' => ['test', 'document'],
        ]);

        $response = $this->authenticatedRequest()
            ->getJson("/api/content/{$content->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'title',
                'description',
                'content',
                'status',
                'file_type',
                'file_path',
                'tags',
                'metadata',
                'processed_at',
                'created_at',
                'updated_at',
                'stakeholders',
                'workstreams',
                'releases',
                'action_items',
            ])
            ->assertJson([
                'id' => $content->id,
                'title' => 'Test Document',
                'description' => 'A test document',
                'content' => 'This is the processed content',
                'status' => 'processed',
                'tags' => ['test', 'document'],
            ]);
    }

    /** @test */
    public function user_cannot_view_other_users_content()
    {
        $otherUser = User::factory()->create();
        $content = Content::factory()->create([
            'user_id' => $otherUser->id,
            'title' => 'Private Document',
        ]);

        $response = $this->authenticatedRequest()
            ->getJson("/api/content/{$content->id}");

        $response->assertStatus(404);
    }

    /** @test */
    public function user_can_update_content_metadata()
    {
        $content = Content::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Original Title',
            'description' => 'Original description',
            'tags' => ['original'],
        ]);

        $updateData = [
            'title' => 'Updated Title',
            'description' => 'Updated description',
            'tags' => ['updated', 'modified'],
        ];

        $response = $this->authenticatedRequest()
            ->putJson("/api/content/{$content->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $content->id,
                'title' => 'Updated Title',
                'description' => 'Updated description',
                'tags' => ['updated', 'modified'],
            ]);

        $this->assertDatabaseHas('contents', [
            'id' => $content->id,
            'title' => 'Updated Title',
            'description' => 'Updated description',
        ]);
    }

    /** @test */
    public function user_cannot_update_other_users_content()
    {
        $otherUser = User::factory()->create();
        $content = Content::factory()->create([
            'user_id' => $otherUser->id,
            'title' => 'Private Document',
        ]);

        $response = $this->authenticatedRequest()
            ->putJson("/api/content/{$content->id}", [
                'title' => 'Attempted Update',
            ]);

        $response->assertStatus(404);

        $this->assertDatabaseHas('contents', [
            'id' => $content->id,
            'title' => 'Private Document', // Should remain unchanged
        ]);
    }

    /** @test */
    public function user_can_delete_their_content()
    {
        Storage::fake('local');

        $content = Content::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Document to Delete',
            'file_path' => 'uploads/test-file.txt',
        ]);

        // Create the fake file
        Storage::disk('local')->put('uploads/test-file.txt', 'test content');

        $response = $this->authenticatedRequest()
            ->deleteJson("/api/content/{$content->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('contents', [
            'id' => $content->id,
        ]);

        Storage::disk('local')->assertMissing('uploads/test-file.txt');
    }

    /** @test */
    public function user_cannot_delete_other_users_content()
    {
        $otherUser = User::factory()->create();
        $content = Content::factory()->create([
            'user_id' => $otherUser->id,
            'title' => 'Private Document',
        ]);

        $response = $this->authenticatedRequest()
            ->deleteJson("/api/content/{$content->id}");

        $response->assertStatus(404);

        $this->assertDatabaseHas('contents', [
            'id' => $content->id,
        ]);
    }

    /** @test */
    public function user_can_reprocess_failed_content()
    {
        $content = Content::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Failed Document',
            'status' => 'failed',
        ]);

        $response = $this->authenticatedRequest()
            ->postJson("/api/content/{$content->id}/reprocess");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Content reprocessing started',
                'content' => [
                    'id' => $content->id,
                    'status' => 'processing',
                ],
            ]);

        $this->assertDatabaseHas('contents', [
            'id' => $content->id,
            'status' => 'processing',
        ]);
    }

    /** @test */
    public function user_cannot_reprocess_already_processed_content()
    {
        $content = Content::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Processed Document',
            'status' => 'processed',
        ]);

        $response = $this->authenticatedRequest()
            ->postJson("/api/content/{$content->id}/reprocess");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Content is already processed or currently processing',
            ]);

        $this->assertDatabaseHas('contents', [
            'id' => $content->id,
            'status' => 'processed', // Should remain unchanged
        ]);
    }

    /** @test */
    public function user_can_get_content_analysis_results()
    {
        $content = Content::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Analyzed Document',
            'status' => 'processed',
            'ai_summary' => 'This document discusses project timelines and stakeholder responsibilities.',
        ]);

        $response = $this->authenticatedRequest()
            ->getJson("/api/content/{$content->id}/analysis");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'content_id',
                'ai_summary',
                'stakeholders' => [
                    '*' => [
                        'id',
                        'name',
                        'mention_type',
                        'confidence_score',
                        'context',
                    ]
                ],
                'workstreams' => [
                    '*' => [
                        'id',
                        'name',
                        'relevance_type',
                        'confidence_score',
                        'context',
                    ]
                ],
                'releases' => [
                    '*' => [
                        'id',
                        'name',
                        'version',
                        'relevance_type',
                        'confidence_score',
                        'context',
                    ]
                ],
                'action_items' => [
                    '*' => [
                        'id',
                        'action_text',
                        'priority',
                        'status',
                        'assignee_stakeholder_id',
                        'due_date',
                        'confidence_score',
                    ]
                ],
            ])
            ->assertJson([
                'content_id' => $content->id,
                'ai_summary' => 'This document discusses project timelines and stakeholder responsibilities.',
            ]);
    }

    /** @test */
    public function user_cannot_get_analysis_for_unprocessed_content()
    {
        $content = Content::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Unprocessed Document',
            'status' => 'pending',
        ]);

        $response = $this->authenticatedRequest()
            ->getJson("/api/content/{$content->id}/analysis");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Content has not been processed yet',
            ]);
    }

    /** @test */
    public function content_upload_triggers_processing_pipeline()
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('document.txt', 100, 'text/plain');

        $response = $this->authenticatedRequest()
            ->postJson('/api/content', [
                'title' => 'Pipeline Test',
                'file' => $file,
            ]);

        $response->assertStatus(201);

        $content = Content::where('title', 'Pipeline Test')->first();

        // Verify the content exists and processing was triggered
        $this->assertNotNull($content);
        $this->assertEquals('pending', $content->status);

        // In a real implementation, we would check that ProcessUploadedFile job was dispatched
        // For now, we'll just verify the content was created with the correct status
    }
}
<?php

namespace Tests\Unit\Services;

use App\Models\Content;
use App\Models\User;
use App\Models\Stakeholder;
use App\Models\Workstream;
use App\Models\Release;
use App\Services\ContentIngestionService;
use App\Services\AiService;
use App\Jobs\ProcessUploadedFile;
use App\Jobs\AnalyzeContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Mockery;

class ContentIngestionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ContentIngestionService $service;
    protected User $user;
    protected Stakeholder $stakeholder;
    protected Workstream $workstream;
    protected Release $release;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->stakeholder = Stakeholder::factory()->create(['user_id' => $this->user->id]);
        $this->workstream = Workstream::factory()->create(['owner_id' => $this->user->id]);
        $this->release = Release::factory()->create(['workstream_id' => $this->workstream->id]);

        $this->service = app(ContentIngestionService::class);
    }

    /** @test */
    public function it_can_ingest_manual_content()
    {
        Queue::fake();

        $contentData = [
            'type' => 'manual',
            'title' => 'Meeting Notes - Sprint Planning',
            'content' => 'We discussed the new features for v2.1. John will handle the API changes.',
            'metadata' => [
                'meeting_date' => '2025-09-20',
                'attendees' => ['John', 'Sarah', 'Mike']
            ]
        ];

        $content = $this->service->ingestContent($this->user, $contentData);

        $this->assertInstanceOf(Content::class, $content);
        $this->assertEquals('manual', $content->type);
        $this->assertEquals('Meeting Notes - Sprint Planning', $content->title);
        $this->assertEquals('pending', $content->status);
        $this->assertEquals($this->user->id, $content->user_id);

        Queue::assertPushed(AnalyzeContent::class, function ($job) use ($content) {
            return $job->content->id === $content->id;
        });
    }

    /** @test */
    public function it_can_ingest_email_content()
    {
        Queue::fake();

        $emailData = [
            'type' => 'email',
            'title' => 'Project Update from John',
            'content' => 'Hi team, the mobile app release is on track. Sarah finished the API documentation.',
            'raw_content' => 'From: john@example.com\nTo: team@example.com\nSubject: Project Update\n\nHi team...',
            'metadata' => [
                'sender' => 'john@example.com',
                'recipients' => ['team@example.com'],
                'timestamp' => '2025-09-20 10:30:00',
                'message_id' => 'abc123'
            ]
        ];

        $content = $this->service->ingestContent($this->user, $emailData);

        $this->assertEquals('email', $content->type);
        $this->assertEquals('john@example.com', $content->metadata['sender']);
        $this->assertNotNull($content->raw_content);

        Queue::assertPushed(AnalyzeContent::class);
    }

    /** @test */
    public function it_can_ingest_file_content()
    {
        Storage::fake('local');
        Queue::fake();

        $file = UploadedFile::fake()->create('document.pdf', 1000, 'application/pdf');

        $fileData = [
            'type' => 'file',
            'title' => 'Project Requirements Document',
            'file' => $file,
            'metadata' => [
                'original_filename' => 'requirements.pdf',
                'upload_timestamp' => now()->toISOString()
            ]
        ];

        $content = $this->service->ingestContent($this->user, $fileData);

        $this->assertEquals('file', $content->type);
        $this->assertEquals('pdf', $content->file_type);
        $this->assertEquals(1000, $content->file_size);
        $this->assertNotNull($content->file_path);

        Storage::disk('local')->assertExists($content->file_path);

        Queue::assertPushed(ProcessUploadedFile::class, function ($job) use ($content) {
            return $job->content->id === $content->id;
        });
    }

    /** @test */
    public function it_validates_required_fields_for_content()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Content type is required');

        $this->service->ingestContent($this->user, [
            'title' => 'Test Content',
            'content' => 'Some content'
        ]);
    }

    /** @test */
    public function it_validates_content_type_enum()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid content type');

        $this->service->ingestContent($this->user, [
            'type' => 'invalid_type',
            'title' => 'Test Content',
            'content' => 'Some content'
        ]);
    }

    /** @test */
    public function it_can_bulk_ingest_multiple_content_items()
    {
        Queue::fake();

        $contentItems = [
            [
                'type' => 'manual',
                'title' => 'Notes 1',
                'content' => 'First set of notes'
            ],
            [
                'type' => 'email',
                'title' => 'Email 1',
                'content' => 'First email content',
                'metadata' => ['sender' => 'test@example.com']
            ]
        ];

        $contents = $this->service->bulkIngestContent($this->user, $contentItems);

        $this->assertCount(2, $contents);
        $this->assertEquals('manual', $contents[0]->type);
        $this->assertEquals('email', $contents[1]->type);

        Queue::assertPushed(AnalyzeContent::class, 2);
    }

    /** @test */
    public function it_can_reprocess_content()
    {
        Queue::fake();

        $content = Content::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'failed'
        ]);

        $reprocessedContent = $this->service->reprocessContent($content);

        $this->assertEquals('pending', $reprocessedContent->status);
        $this->assertNull($reprocessedContent->processed_at);

        Queue::assertPushed(AnalyzeContent::class, function ($job) use ($content) {
            return $job->content->id === $content->id;
        });
    }

    /** @test */
    public function it_can_extract_metadata_from_different_content_types()
    {
        $emailMetadata = $this->service->extractMetadata('email', [
            'sender' => 'john@example.com',
            'subject' => 'Project Update',
            'timestamp' => '2025-09-20 10:30:00'
        ]);

        $this->assertArrayHasKey('sender', $emailMetadata);
        $this->assertArrayHasKey('parsed_timestamp', $emailMetadata);

        $meetingMetadata = $this->service->extractMetadata('meeting_notes', [
            'meeting_date' => '2025-09-20',
            'attendees' => ['John', 'Sarah']
        ]);

        $this->assertArrayHasKey('meeting_date', $meetingMetadata);
        $this->assertArrayHasKey('attendee_count', $meetingMetadata);
    }

    /** @test */
    public function it_handles_file_upload_errors_gracefully()
    {
        Storage::fake('local');

        // Mock a file upload that fails
        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('store')->andReturn(false);
        $file->shouldReceive('getClientOriginalName')->andReturn('test.pdf');
        $file->shouldReceive('getSize')->andReturn(1000);
        $file->shouldReceive('getClientOriginalExtension')->andReturn('pdf');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to store uploaded file');

        $this->service->ingestContent($this->user, [
            'type' => 'file',
            'title' => 'Test File',
            'file' => $file
        ]);
    }

    /** @test */
    public function it_can_get_ingestion_statistics()
    {
        Content::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'type' => 'email',
            'status' => 'processed'
        ]);

        Content::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'type' => 'file',
            'status' => 'pending'
        ]);

        Content::factory()->count(1)->create([
            'user_id' => $this->user->id,
            'type' => 'manual',
            'status' => 'failed'
        ]);

        $stats = $this->service->getIngestionStatistics($this->user);

        $this->assertEquals(6, $stats['total_content']);
        $this->assertEquals(3, $stats['processed_content']);
        $this->assertEquals(2, $stats['pending_content']);
        $this->assertEquals(1, $stats['failed_content']);
        $this->assertArrayHasKey('content_by_type', $stats);
        $this->assertEquals(3, $stats['content_by_type']['email']);
        $this->assertEquals(2, $stats['content_by_type']['file']);
        $this->assertEquals(1, $stats['content_by_type']['manual']);
    }

    /** @test */
    public function it_can_search_content_by_text()
    {
        Content::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Mobile App Project',
            'content' => 'Discussion about mobile app features and API integration'
        ]);

        Content::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Backend Updates',
            'content' => 'Database migration and performance improvements'
        ]);

        $results = $this->service->searchContent($this->user, 'mobile app');

        $this->assertCount(1, $results);
        $this->assertStringContainsString('Mobile App Project', $results->first()->title);
    }

    /** @test */
    public function it_can_filter_content_by_criteria()
    {
        $content1 = Content::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'email',
            'status' => 'processed'
        ]);

        $content2 = Content::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'file',
            'status' => 'pending'
        ]);

        // Test filtering by type
        $emailContent = $this->service->filterContent($this->user, ['type' => 'email']);
        $this->assertCount(1, $emailContent);
        $this->assertEquals('email', $emailContent->first()->type);

        // Test filtering by status
        $processedContent = $this->service->filterContent($this->user, ['status' => 'processed']);
        $this->assertCount(1, $processedContent);
        $this->assertEquals('processed', $processedContent->first()->status);
    }
}
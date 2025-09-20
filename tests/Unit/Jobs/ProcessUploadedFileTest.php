<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ProcessUploadedFile;
use App\Jobs\AnalyzeContent;
use App\Models\Content;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessUploadedFileTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Content $content;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->content = Content::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'file',
            'file_type' => 'pdf',
            'file_path' => 'uploads/test-document.pdf',
            'status' => 'pending'
        ]);
    }

    /** @test */
    public function it_can_extract_text_from_pdf_file()
    {
        Storage::fake('local');
        Storage::put('uploads/test-document.pdf', 'fake pdf content');

        Queue::fake();

        $job = new ProcessUploadedFile($this->content);
        $job->handle();

        $updatedContent = $this->content->fresh();
        $this->assertNotNull($updatedContent->content);
        $this->assertEquals('processing', $updatedContent->status);

        Queue::assertPushed(AnalyzeContent::class, function ($job) {
            return $job->content->id === $this->content->id;
        });
    }

    /** @test */
    public function it_can_extract_text_from_word_document()
    {
        Storage::fake('local');

        $this->content->update([
            'file_type' => 'docx',
            'file_path' => 'uploads/test-document.docx'
        ]);

        Storage::put('uploads/test-document.docx', 'fake docx content');

        Queue::fake();

        $job = new ProcessUploadedFile($this->content);
        $job->handle();

        $updatedContent = $this->content->fresh();
        $this->assertNotNull($updatedContent->content);
        $this->assertEquals('processing', $updatedContent->status);

        Queue::assertPushed(AnalyzeContent::class);
    }

    /** @test */
    public function it_can_extract_text_from_plain_text_file()
    {
        Storage::fake('local');

        $this->content->update([
            'file_type' => 'txt',
            'file_path' => 'uploads/test-document.txt'
        ]);

        $textContent = "This is a test document.\nIt contains multiple lines.\nAnd some important information.";
        Storage::put('uploads/test-document.txt', $textContent);

        Queue::fake();

        $job = new ProcessUploadedFile($this->content);
        $job->handle();

        $updatedContent = $this->content->fresh();
        $this->assertEquals($textContent, $updatedContent->content);
        $this->assertEquals('processing', $updatedContent->status);

        Queue::assertPushed(AnalyzeContent::class);
    }

    /** @test */
    public function it_handles_unsupported_file_types()
    {
        Storage::fake('local');

        $this->content->update([
            'file_type' => 'jpg',
            'file_path' => 'uploads/image.jpg'
        ]);

        Storage::put('uploads/image.jpg', 'fake image content');

        $job = new ProcessUploadedFile($this->content);
        $job->handle();

        $updatedContent = $this->content->fresh();
        $this->assertEquals('failed', $updatedContent->status);
        $this->assertNotNull($updatedContent->content); // Should contain error message
        $this->assertStringContainsString('Unsupported file type', $updatedContent->content);
    }

    /** @test */
    public function it_handles_missing_files_gracefully()
    {
        Storage::fake('local');
        // Don't create the file

        $job = new ProcessUploadedFile($this->content);
        $job->handle();

        $updatedContent = $this->content->fresh();
        $this->assertEquals('failed', $updatedContent->status);
        $this->assertStringContainsString('File not found', $updatedContent->content);
    }

    /** @test */
    public function it_updates_processing_metadata()
    {
        Storage::fake('local');
        Storage::put('uploads/test-document.txt', 'Sample text content for processing');

        $this->content->update([
            'file_type' => 'txt',
            'file_path' => 'uploads/test-document.txt'
        ]);

        $job = new ProcessUploadedFile($this->content);
        $job->handle();

        $updatedContent = $this->content->fresh();

        $this->assertArrayHasKey('processing_completed_at', $updatedContent->metadata);
        $this->assertArrayHasKey('text_extraction_method', $updatedContent->metadata);
        $this->assertArrayHasKey('extracted_text_length', $updatedContent->metadata);

        $this->assertEquals('direct_read', $updatedContent->metadata['text_extraction_method']);
        $this->assertEquals(strlen('Sample text content for processing'), $updatedContent->metadata['extracted_text_length']);
    }

    /** @test */
    public function it_preserves_original_file_content_in_raw_content()
    {
        Storage::fake('local');
        $originalContent = "Original file content with special formatting";

        $this->content->update([
            'file_type' => 'txt',
            'file_path' => 'uploads/test-document.txt',
            'raw_content' => null
        ]);

        Storage::put('uploads/test-document.txt', $originalContent);

        $job = new ProcessUploadedFile($this->content);
        $job->handle();

        $updatedContent = $this->content->fresh();
        $this->assertEquals($originalContent, $updatedContent->raw_content);
        $this->assertEquals($originalContent, $updatedContent->content); // For text files, they should be the same
    }

    /** @test */
    public function it_cleans_extracted_text_content()
    {
        Storage::fake('local');
        $messyContent = "   This is text with   extra spaces\n\n\nand multiple\r\n\r\nline breaks   ";
        $expectedCleanContent = "This is text with extra spaces\n\nand multiple\n\nline breaks";

        $this->content->update([
            'file_type' => 'txt',
            'file_path' => 'uploads/messy-document.txt'
        ]);

        Storage::put('uploads/messy-document.txt', $messyContent);

        $job = new ProcessUploadedFile($this->content);
        $job->handle();

        $updatedContent = $this->content->fresh();
        $this->assertEquals($expectedCleanContent, $updatedContent->content);
        $this->assertEquals($messyContent, $updatedContent->raw_content); // Raw content should be preserved
    }

    /** @test */
    public function it_limits_extracted_text_length()
    {
        Storage::fake('local');
        $longContent = str_repeat('This is a very long document. ', 10000); // Very long content

        $this->content->update([
            'file_type' => 'txt',
            'file_path' => 'uploads/long-document.txt'
        ]);

        Storage::put('uploads/long-document.txt', $longContent);

        $job = new ProcessUploadedFile($this->content);
        $job->handle();

        $updatedContent = $this->content->fresh();

        // Should be truncated to reasonable length (e.g., 50000 characters)
        $this->assertLessThanOrEqual(50000, strlen($updatedContent->content));
        $this->assertStringContainsString('[TRUNCATED]', $updatedContent->content);

        // Metadata should indicate truncation
        $this->assertTrue($updatedContent->metadata['text_truncated']);
        $this->assertGreaterThan(50000, $updatedContent->metadata['original_text_length']);
        $this->assertGreaterThan(strlen($updatedContent->content), $updatedContent->metadata['original_text_length']);
    }

    /** @test */
    public function it_retries_failed_processing()
    {
        Storage::fake('local');
        Storage::put('uploads/test-document.txt', 'Sample content');

        $this->content->update([
            'file_type' => 'txt',
            'file_path' => 'uploads/test-document.txt',
            'status' => 'failed'
        ]);

        $job = new ProcessUploadedFile($this->content);
        $job->handle();

        $updatedContent = $this->content->fresh();
        $this->assertEquals('processed', $updatedContent->status);
        $this->assertNotNull($updatedContent->content);
    }
}
<?php

namespace App\Jobs;

use App\Models\Content;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessUploadedFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Content $content;

    public function __construct(Content $content)
    {
        $this->content = $content;
    }

    public function handle(): void
    {
        Log::info("Starting file processing for content ID: {$this->content->id}");

        try {
            // Check if file exists
            if (!Storage::exists($this->content->file_path)) {
                $this->handleError('File not found: ' . $this->content->file_path);
                return;
            }

            // Get file content
            $rawContent = Storage::get($this->content->file_path);

            // Extract text based on file type
            $extractedText = $this->extractTextFromFile($rawContent, $this->content->file_type);

            if ($extractedText === false) {
                $this->handleError('Unsupported file type: ' . $this->content->file_type);
                return;
            }

            // Clean the extracted text
            $cleanedText = $this->cleanText($extractedText);

            // Handle text length limits
            $textData = $this->handleTextLength($cleanedText);

            // Update content with extracted text and metadata
            $metadata = array_merge($this->content->metadata ?? [], [
                'processing_completed_at' => now()->toISOString(),
                'text_extraction_method' => $this->getExtractionMethod($this->content->file_type),
                'extracted_text_length' => strlen($textData['text']),
                'text_truncated' => $textData['truncated'],
                'original_text_length' => strlen($cleanedText)
            ]);

            $this->content->update([
                'content' => $textData['text'],
                'raw_content' => $rawContent,
                'status' => 'processing',
                'metadata' => $metadata
            ]);

            // Dispatch AI analysis job
            AnalyzeContent::dispatch($this->content);

            Log::info("File processing completed for content ID: {$this->content->id}");

        } catch (\Exception $e) {
            Log::error("File processing failed for content ID: {$this->content->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->content->update(['status' => 'failed']);
            throw $e;
        }
    }

    private function extractTextFromFile(string $rawContent, string $fileType): string|false
    {
        switch (strtolower($fileType)) {
            case 'txt':
                return $rawContent;

            case 'pdf':
                // For now, return a placeholder. In production, you'd use a PDF parser
                return 'Extracted PDF content: ' . substr($rawContent, 0, 100);

            case 'docx':
                // For now, return a placeholder. In production, you'd use a DOCX parser
                return 'Extracted DOCX content: ' . substr($rawContent, 0, 100);

            case 'md':
                return $rawContent;

            default:
                return false;
        }
    }

    private function cleanText(string $text): string
    {
        // Remove excessive whitespace
        $text = preg_replace('/[ \t]+/', ' ', $text);

        // Normalize line endings
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Remove excessive line breaks (more than 2 consecutive)
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        // Trim leading and trailing whitespace
        return trim($text);
    }

    private function handleTextLength(string $text): array
    {
        $maxLength = 50000;

        if (strlen($text) <= $maxLength) {
            return [
                'text' => $text,
                'truncated' => false
            ];
        }

        // Truncate and add indicator
        $truncatedText = substr($text, 0, $maxLength - 20) . ' [TRUNCATED]';

        return [
            'text' => $truncatedText,
            'truncated' => true
        ];
    }

    private function getExtractionMethod(string $fileType): string
    {
        switch (strtolower($fileType)) {
            case 'txt':
            case 'md':
                return 'direct_read';
            case 'pdf':
                return 'pdf_parser';
            case 'docx':
                return 'docx_parser';
            default:
                return 'unknown';
        }
    }

    private function handleError(string $message): void
    {
        Log::error("ProcessUploadedFile error for content ID {$this->content->id}: {$message}");

        $this->content->update([
            'status' => 'failed',
            'content' => $message
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessUploadedFile job failed for content ID: {$this->content->id}", [
            'error' => $exception->getMessage()
        ]);

        $this->content->update(['status' => 'failed']);
    }
}
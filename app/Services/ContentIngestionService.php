<?php

namespace App\Services;

use App\Models\Content;
use App\Models\User;
use App\Jobs\AnalyzeContent;
use App\Jobs\ProcessUploadedFile;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ContentIngestionService
{
    public function ingestContent(User $user, array $contentData): Content
    {
        $this->validateContentData($contentData);

        DB::beginTransaction();

        try {
            // Handle file upload if present
            if (isset($contentData['file']) && $contentData['file'] instanceof UploadedFile) {
                $fileData = $this->handleFileUpload($contentData['file']);
                $contentData = array_merge($contentData, $fileData);
                unset($contentData['file']);
            }

            // Create the content record
            $content = Content::create([
                'user_id' => $user->id,
                'type' => $contentData['type'],
                'title' => $contentData['title'],
                'content' => $contentData['content'] ?? null,
                'raw_content' => $contentData['raw_content'] ?? $contentData['content'] ?? null,
                'metadata' => $contentData['metadata'] ?? [],
                'file_path' => $contentData['file_path'] ?? null,
                'file_type' => $contentData['file_type'] ?? null,
                'file_size' => $contentData['file_size'] ?? null,
                'source_reference' => $contentData['source_reference'] ?? null,
                'status' => 'pending'
            ]);

            DB::commit();

            // Queue content for AI analysis
            AnalyzeContent::dispatch($content);

            Log::info("Content ingested successfully", [
                'content_id' => $content->id,
                'user_id' => $user->id,
                'type' => $content->type
            ]);

            return $content;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Content ingestion failed", [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function bulkIngestContent(User $user, array $contentItems): Collection
    {
        $ingestedContent = new Collection();

        foreach ($contentItems as $contentData) {
            try {
                $content = $this->ingestContent($user, $contentData);
                $ingestedContent->push($content);
            } catch (\Exception $e) {
                Log::error("Bulk ingestion failed for item", [
                    'user_id' => $user->id,
                    'content_data' => $contentData,
                    'error' => $e->getMessage()
                ]);
                // Continue with next item
            }
        }

        return $ingestedContent;
    }

    public function reprocessContent(Content $content): Content
    {
        $content->update([
            'status' => 'pending',
            'processed_at' => null,
            'ai_summary' => null
        ]);

        // Clear existing AI-detected relationships
        $content->stakeholders()->detach();
        $content->workstreams()->detach();
        $content->releases()->detach();
        $content->actionItems()->delete();

        // Queue for reanalysis
        AnalyzeContent::dispatch($content);

        Log::info("Content queued for reprocessing", ['content_id' => $content->id]);

        return $content;
    }

    public function getIngestionStatistics(User $user): array
    {
        $userContent = Content::where('user_id', $user->id);

        $stats = [
            'total_content' => $userContent->count(),
            'processed_content' => $userContent->clone()->where('status', 'processed')->count(),
            'pending_content' => $userContent->clone()->where('status', 'pending')->count(),
            'failed_content' => $userContent->clone()->where('status', 'failed')->count(),
            'content_by_type' => []
        ];

        // Get content counts by type
        $contentByType = $userContent->clone()
            ->select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        $stats['content_by_type'] = $contentByType;

        return $stats;
    }

    public function searchContent(User $user, string $searchTerm): Collection
    {
        return Content::where('user_id', $user->id)
            ->where(function ($query) use ($searchTerm) {
                $query->where('title', 'ILIKE', "%{$searchTerm}%")
                      ->orWhere('content', 'ILIKE', "%{$searchTerm}%")
                      ->orWhere('ai_summary', 'ILIKE', "%{$searchTerm}%");
            })
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function filterContent(User $user, array $criteria): Collection
    {
        $query = Content::where('user_id', $user->id);

        if (isset($criteria['type'])) {
            $query->where('type', $criteria['type']);
        }

        if (isset($criteria['status'])) {
            $query->where('status', $criteria['status']);
        }

        if (isset($criteria['date_from'])) {
            $query->where('created_at', '>=', $criteria['date_from']);
        }

        if (isset($criteria['date_to'])) {
            $query->where('created_at', '<=', $criteria['date_to']);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function extractMetadata(string $type, array $metadata = []): array
    {
        switch ($type) {
            case 'email':
                return array_merge($metadata, [
                    'processed_at' => now()->toISOString(),
                    'parsed_timestamp' => now()->toISOString(),
                    'sender' => $metadata['sender'] ?? null
                ]);

            case 'file':
                return array_merge($metadata, [
                    'processed_at' => now()->toISOString(),
                    'file_extension' => $metadata['file_extension'] ?? null
                ]);

            case 'manual':
                return array_merge($metadata, [
                    'created_at' => now()->toISOString(),
                    'created_timestamp' => now()->toISOString()
                ]);

            case 'meeting_notes':
                return array_merge($metadata, [
                    'processed_at' => now()->toISOString(),
                    'meeting_timestamp' => now()->toISOString(),
                    'has_attendees' => isset($metadata['attendees']) && !empty($metadata['attendees'])
                ]);

            default:
                return array_merge($metadata, [
                    'processed_at' => now()->toISOString()
                ]);
        }
    }

    protected function validateContentData(array $contentData): void
    {
        if (empty($contentData['type'])) {
            throw new InvalidArgumentException('Content type is required');
        }

        $validTypes = ['email', 'file', 'manual', 'meeting_notes', 'slack', 'teams'];
        if (!in_array($contentData['type'], $validTypes)) {
            throw new InvalidArgumentException('Invalid content type. Must be one of: ' . implode(', ', $validTypes));
        }

        if (empty($contentData['title'])) {
            throw new InvalidArgumentException('Title is required');
        }

        // For non-file types, content is required
        if ($contentData['type'] !== 'file' && empty($contentData['content']) && !isset($contentData['file'])) {
            throw new InvalidArgumentException('Content is required');
        }
    }

    protected function handleFileUpload(UploadedFile $file): array
    {
        if (!$file->isValid()) {
            throw new \Exception('Invalid file upload');
        }

        // Store the file
        $path = $file->store('content-files', 'local');

        // Queue for processing if it's a supported file type
        $supportedTypes = ['txt', 'pdf', 'docx', 'md'];
        $extension = $file->getClientOriginalExtension();

        $fileData = [
            'file_path' => $path,
            'file_type' => $extension,
            'file_size' => $file->getSize(),
            'content' => null, // Will be extracted by ProcessUploadedFile job
        ];

        if (in_array(strtolower($extension), $supportedTypes)) {
            // For now, just extract basic text for testing
            // In production, this would be handled by ProcessUploadedFile job
            try {
                if ($extension === 'txt') {
                    $fileData['content'] = $file->get();
                } else {
                    $fileData['content'] = "File content will be extracted: " . $file->getClientOriginalName();
                }
            } catch (\Exception $e) {
                Log::warning("Could not extract content from file", [
                    'file' => $file->getClientOriginalName(),
                    'error' => $e->getMessage()
                ]);
                $fileData['content'] = "Content extraction pending";
            }
        }

        return $fileData;
    }
}
<?php

namespace App\Services;

use App\Models\Content;
use App\Models\User;
use App\Services\ContentProcessor;
use App\Exceptions\BrainDumpProcessingException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BrainDumpProcessor
{
    public function __construct(
        private ContentProcessor $contentProcessor
    ) {}

    public function process(string $content, User $user): array
    {
        try {
            // Use the new ContentProcessor for all the heavy lifting
            $result = $this->contentProcessor->process($content, 'brain_dump');

            // Transform to the legacy format that the BrainDump component expects
            return $this->transformToLegacyFormat($result);

        } catch (\Exception $e) {
            Log::error('Brain dump processing failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'content_preview' => substr($content, 0, 100)
            ]);

            throw new BrainDumpProcessingException(
                'Failed to process brain dump content',
                'processing_error',
                500,
                $e
            );
        }
    }

    /**
     * Transform ContentProcessor result to the format expected by BrainDump component
     */
    private function transformToLegacyFormat(array $result): array
    {
        $extractedEntities = $result['extracted_entities'];

        // Transform tasks (action items) - include stakeholders and workstreams as tasks for now
        $tasks = [];
        if (isset($extractedEntities['action_items'])) {
            foreach ($extractedEntities['action_items'] as $item) {
                $tasks[] = [
                    'title' => $item['action_text'] ?? $item['title'] ?? $item['text'] ?? '',
                    'priority' => $item['priority'] ?? 'medium',
                    'assignee' => $item['assignee'] ?? null,
                    'due_date' => $item['due_date'] ?? null
                ];
            }
        }

        // Add stakeholders as tasks (temporary solution for UI display)
        if (isset($extractedEntities['stakeholders'])) {
            foreach ($extractedEntities['stakeholders'] as $stakeholder) {
                $tasks[] = [
                    'title' => '👤 ' . ($stakeholder['name'] ?? 'Unknown') . ': ' . ($stakeholder['context'] ?? 'Mentioned'),
                    'priority' => 'medium',
                    'assignee' => null,
                    'due_date' => null
                ];
            }
        }

        // Add workstreams as tasks (temporary solution for UI display)
        if (isset($extractedEntities['workstreams'])) {
            foreach ($extractedEntities['workstreams'] as $workstream) {
                $tasks[] = [
                    'title' => '🏗️ Project: ' . ($workstream['name'] ?? 'Unknown') . ' - ' . ($workstream['context'] ?? 'Mentioned'),
                    'priority' => 'high',
                    'assignee' => null,
                    'due_date' => null
                ];
            }
        }

        // Transform meetings
        $meetings = [];
        if (isset($extractedEntities['meetings'])) {
            foreach ($extractedEntities['meetings'] as $meeting) {
                $meetings[] = [
                    'title' => $meeting['title'] ?? $meeting['name'] ?? '',
                    'date' => $meeting['date'] ?? null,
                    'attendees' => $meeting['attendees'] ?? []
                ];
            }
        }

        // Transform decisions
        $decisions = [];
        if (isset($extractedEntities['decisions'])) {
            foreach ($extractedEntities['decisions'] as $decision) {
                $decisions[] = [
                    'title' => $decision['title'] ?? $decision['name'] ?? '',
                    'impact' => $decision['impact'] ?? 'medium',
                    'date' => $decision['date'] ?? now()->toDateString()
                ];
            }
        }

        return [
            'tasks' => $tasks,
            'meetings' => $meetings,
            'decisions' => $decisions,
            'content_id' => $result['content_id']
        ];
    }
}
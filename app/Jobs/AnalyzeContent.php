<?php

namespace App\Jobs;

use App\Models\Content;
use App\Services\AiEntityDetectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AnalyzeContent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Content $content;

    public function __construct(Content $content)
    {
        $this->content = $content;
    }

    public function handle(AiEntityDetectionService $aiEntityDetectionService): void
    {
        Log::info("Starting content analysis for content ID: {$this->content->id}");

        try {
            $this->content->update(['status' => 'processing']);

            // Run AI entity detection to find stakeholders, workstreams, releases, and action items
            $results = $aiEntityDetectionService->analyzeContent($this->content);

            // Associate detected entities with the content
            if (!empty($results['stakeholders'])) {
                foreach ($results['stakeholders'] as $stakeholderData) {
                    $this->content->stakeholders()->attach($stakeholderData['stakeholder_id'], [
                        'mention_type' => $stakeholderData['mention_type'],
                        'confidence_score' => $stakeholderData['confidence_score'],
                        'context' => $stakeholderData['context']
                    ]);
                }
            }

            if (!empty($results['workstreams'])) {
                foreach ($results['workstreams'] as $workstreamData) {
                    $this->content->workstreams()->attach($workstreamData['workstream_id'], [
                        'relevance_type' => $workstreamData['relevance_type'],
                        'confidence_score' => $workstreamData['confidence_score'],
                        'context' => $workstreamData['context']
                    ]);
                }
            }

            if (!empty($results['releases'])) {
                foreach ($results['releases'] as $releaseData) {
                    $this->content->releases()->attach($releaseData['release_id'], [
                        'relevance_type' => $releaseData['relevance_type'],
                        'confidence_score' => $releaseData['confidence_score'],
                        'context' => $releaseData['context']
                    ]);
                }
            }

            // Create action items
            if (!empty($results['action_items'])) {
                foreach ($results['action_items'] as $actionItemData) {
                    $actionItem = $this->content->actionItems()->create([
                        'action_text' => $actionItemData['action_text'],
                        'assignee_stakeholder_id' => $actionItemData['assignee_stakeholder_id'] ?? null,
                        'priority' => $actionItemData['priority'] ?? 'medium',
                        'due_date' => $actionItemData['due_date'] ?? null,
                        'status' => 'pending',
                        'confidence_score' => $actionItemData['confidence_score'],
                        'context' => $actionItemData['context'] ?? null,
                    ]);

                    // Associate action item with related entities
                    if (!empty($actionItemData['stakeholder_ids'])) {
                        $actionItem->stakeholders()->attach($actionItemData['stakeholder_ids']);
                    }
                    if (!empty($actionItemData['workstream_ids'])) {
                        $actionItem->workstreams()->attach($actionItemData['workstream_ids']);
                    }
                    if (!empty($actionItemData['release_ids'])) {
                        $actionItem->releases()->attach($actionItemData['release_ids']);
                    }
                }
            }

            $this->content->update([
                'status' => 'processed',
                'processed_at' => now(),
                'ai_summary' => $results['summary'] ?? null
            ]);

            Log::info("Content analysis completed for content ID: {$this->content->id}");

        } catch (\Exception $e) {
            Log::error("Content analysis failed for content ID: {$this->content->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->content->update(['status' => 'failed']);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("AnalyzeContent job failed for content ID: {$this->content->id}", [
            'error' => $exception->getMessage()
        ]);

        $this->content->update(['status' => 'failed']);
    }
}
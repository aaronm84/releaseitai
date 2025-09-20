<?php

namespace App\Services;

use App\Models\Content;
use App\Models\Stakeholder;
use App\Models\Workstream;
use App\Models\Release;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiEntityDetectionService
{
    protected AiService $aiService;

    public function __construct(AiService $aiService)
    {
        $this->aiService = $aiService;
    }

    public function analyzeContent(Content $content): array
    {
        try {
            Log::info("Starting AI entity detection for content", ['content_id' => $content->id]);

            // Get AI entities from the content
            $detectedEntities = $this->detectEntities($content);

            // Match detected entities to existing database records
            $matchedEntities = $this->matchEntitiesWithDatabase($content, $detectedEntities);

            // Convert to the format expected by AnalyzeContent job
            return $this->convertToAnalysisFormat($matchedEntities);

        } catch (\Exception $e) {
            Log::error("AI entity detection failed", [
                'content_id' => $content->id,
                'error' => $e->getMessage()
            ]);

            return [
                'stakeholders' => [],
                'workstreams' => [],
                'releases' => [],
                'action_items' => [],
                'summary' => null
            ];
        }
    }

    public function detectEntities(Content $content): array
    {
        $aiResponse = $this->aiService->analyzeContentEntities($content->content);

        return [
            'stakeholders' => $aiResponse['stakeholders'] ?? [],
            'workstreams' => $aiResponse['workstreams'] ?? [],
            'releases' => $aiResponse['releases'] ?? [],
            'action_items' => $aiResponse['action_items'] ?? [],
            'summary' => $aiResponse['summary'] ?? null
        ];
    }

    public function matchEntitiesWithDatabase(Content $content, array $detectedEntities): array
    {
        return [
            'stakeholders' => $this->matchStakeholders($content, $detectedEntities['stakeholders'] ?? []),
            'workstreams' => $this->matchWorkstreams($content, $detectedEntities['workstreams'] ?? []),
            'releases' => $this->matchReleases($content, $detectedEntities['releases'] ?? []),
            'action_items' => $detectedEntities['action_items'] ?? [],
            'summary' => $detectedEntities['summary'] ?? null
        ];
    }

    protected function matchStakeholders(Content $content, array $detectedStakeholders): array
    {
        $matched = [];
        $unmatched = [];

        foreach ($detectedStakeholders as $detected) {
            $stakeholder = $this->findStakeholderByName($content->user_id, $detected['name']);

            if ($stakeholder) {
                $matched[] = [
                    'existing_id' => $stakeholder->id,
                    'detected_name' => $detected['name'],
                    'confidence' => $detected['confidence'],
                    'context' => $detected['context'],
                    'mention_type' => $this->determineMentionType($detected)
                ];
            } else {
                $unmatched[] = $detected;
            }
        }

        return [
            'matched' => $matched,
            'unmatched' => $unmatched
        ];
    }

    protected function matchWorkstreams(Content $content, array $detectedWorkstreams): array
    {
        $matched = [];
        $unmatched = [];

        foreach ($detectedWorkstreams as $detected) {
            $workstream = $this->findWorkstreamByName($content->user_id, $detected['name']);

            if ($workstream) {
                $matched[] = [
                    'existing_id' => $workstream->id,
                    'detected_name' => $detected['name'],
                    'confidence' => $detected['confidence'],
                    'context' => $detected['context'],
                    'relevance_type' => $this->determineRelevanceType($detected)
                ];
            } else {
                $unmatched[] = $detected;
            }
        }

        return [
            'matched' => $matched,
            'unmatched' => $unmatched
        ];
    }

    protected function matchReleases(Content $content, array $detectedReleases): array
    {
        $matched = [];
        $unmatched = [];

        foreach ($detectedReleases as $detected) {
            $release = $this->findReleaseByVersion($content->user_id, $detected['version'] ?? $detected['name']);

            if ($release) {
                $matched[] = [
                    'existing_id' => $release->id,
                    'detected_name' => $detected['version'] ?? $detected['name'],
                    'confidence' => $detected['confidence'],
                    'context' => $detected['context'],
                    'relevance_type' => $this->determineRelevanceType($detected)
                ];
            } else {
                $unmatched[] = $detected;
            }
        }

        return [
            'matched' => $matched,
            'unmatched' => $unmatched
        ];
    }

    protected function findStakeholderByName(int $userId, string $name): ?Stakeholder
    {
        // First try exact name match
        $stakeholder = Stakeholder::whereHas('user', function ($query) use ($userId) {
            $query->where('id', $userId);
        })->where('name', $name)->first();

        if ($stakeholder) {
            return $stakeholder;
        }

        // Try fuzzy matching for variations
        return Stakeholder::whereHas('user', function ($query) use ($userId) {
            $query->where('id', $userId);
        })->where(function ($query) use ($name) {
            $query->where('name', 'ILIKE', "%{$name}%")
                  ->orWhere('email', 'ILIKE', "%{$name}%");
        })->first();
    }

    protected function findWorkstreamByName(int $userId, string $name): ?Workstream
    {
        // First try exact name match
        $workstream = Workstream::where('owner_id', $userId)
            ->where('name', $name)
            ->first();

        if ($workstream) {
            return $workstream;
        }

        // Try fuzzy matching
        return Workstream::where('owner_id', $userId)
            ->where(function ($query) use ($name) {
                $query->where('name', 'ILIKE', "%{$name}%")
                      ->orWhere('description', 'ILIKE', "%{$name}%");
            })
            ->first();
    }

    protected function findReleaseByVersion(int $userId, string $version): ?Release
    {
        // Clean version string (remove 'v', spaces, etc.)
        $cleanVersion = preg_replace('/[^0-9\.]/', '', $version);

        return Release::whereHas('workstream', function ($query) use ($userId) {
            $query->where('owner_id', $userId);
        })->where(function ($query) use ($version, $cleanVersion) {
            $query->where('version', $version)
                  ->orWhere('version', $cleanVersion)
                  ->orWhere('version', 'ILIKE', "%{$version}%")
                  ->orWhere('version', 'ILIKE', "%{$cleanVersion}%")
                  ->orWhere('version', 'ILIKE', "{$cleanVersion}.%")
                  ->orWhere('name', 'ILIKE', "%{$version}%")
                  ->orWhere('name', 'ILIKE', "%{$cleanVersion}%");
        })->first();
    }

    protected function determineMentionType(array $detected): string
    {
        $context = strtolower($detected['context'] ?? '');

        if (Str::contains($context, ['assigned', 'assignee', 'responsible'])) {
            return 'assignee';
        }

        if (Str::contains($context, ['cc', 'copied', 'looped in'])) {
            return 'cc';
        }

        if (Str::contains($context, ['participant', 'attendee', 'member'])) {
            return 'participant';
        }

        return 'direct_mention';
    }

    protected function determineRelevanceType(array $detected): string
    {
        $context = strtolower($detected['context'] ?? '');
        $confidence = $detected['confidence'] ?? 0;

        if ($confidence >= 0.9 || Str::contains($context, ['directly', 'main', 'primary', 'core'])) {
            return 'primary';
        }

        if ($confidence >= 0.7 || Str::contains($context, ['related', 'involved', 'impacts'])) {
            return 'secondary';
        }

        if (Str::contains($context, ['mentioned', 'referenced', 'noted'])) {
            return 'mentioned';
        }

        return 'related';
    }

    protected function convertToAnalysisFormat(array $matchedEntities): array
    {
        $result = [
            'stakeholders' => [],
            'workstreams' => [],
            'releases' => [],
            'action_items' => [],
            'summary' => $matchedEntities['summary'] ?? null
        ];

        // Convert stakeholders
        foreach ($matchedEntities['stakeholders']['matched'] ?? [] as $match) {
            $result['stakeholders'][] = [
                'stakeholder_id' => $match['existing_id'],
                'mention_type' => $match['mention_type'],
                'confidence_score' => $match['confidence'],
                'context' => $match['context']
            ];
        }

        // Convert workstreams
        foreach ($matchedEntities['workstreams']['matched'] ?? [] as $match) {
            $result['workstreams'][] = [
                'workstream_id' => $match['existing_id'],
                'relevance_type' => $this->normalizeRelevanceType($match['relevance_type']),
                'confidence_score' => $match['confidence'],
                'context' => $match['context']
            ];
        }

        // Convert releases
        foreach ($matchedEntities['releases']['matched'] ?? [] as $match) {
            $result['releases'][] = [
                'release_id' => $match['existing_id'],
                'relevance_type' => $this->normalizeRelevanceType($match['relevance_type']),
                'confidence_score' => $match['confidence'],
                'context' => $match['context']
            ];
        }

        // Convert action items
        foreach ($matchedEntities['action_items'] ?? [] as $actionItem) {
            $assigneeId = null;

            // Try to find assignee if specified
            if (!empty($actionItem['assignee'])) {
                $assignee = $this->findStakeholderByName(
                    0, // We need user context, for now use 0
                    $actionItem['assignee']
                );
                $assigneeId = $assignee?->id;
            }

            $result['action_items'][] = [
                'action_text' => $actionItem['text'],
                'assignee_stakeholder_id' => $assigneeId,
                'priority' => $actionItem['priority'] ?? 'medium',
                'due_date' => $actionItem['due_date'] ?? null,
                'confidence_score' => $actionItem['confidence'],
                'context' => $actionItem['context'] ?? null,
                'stakeholder_ids' => [],
                'workstream_ids' => [],
                'release_ids' => []
            ];
        }

        return $result;
    }

    protected function normalizeRelevanceType(string $type): string
    {
        // Ensure the relevance type is one of the allowed enum values
        $validTypes = ['primary', 'secondary', 'mentioned', 'related'];

        if (in_array($type, $validTypes)) {
            return $type;
        }

        // Map common variations to valid types
        $mappings = [
            'direct_reference' => 'primary',
            'feature_update' => 'secondary',
            'blocker' => 'primary',
            'task_related' => 'secondary'
        ];

        return $mappings[$type] ?? 'related';
    }

    public function suggestNewEntities(array $unmatchedEntities): array
    {
        $suggestions = [];

        foreach ($unmatchedEntities['stakeholders']['unmatched'] ?? [] as $stakeholder) {
            if ($stakeholder['confidence'] >= 0.7) {
                $suggestions['stakeholders'][] = [
                    'suggested_name' => $stakeholder['name'],
                    'confidence' => $stakeholder['confidence'],
                    'context' => $stakeholder['context']
                ];
            }
        }

        foreach ($unmatchedEntities['workstreams']['unmatched'] ?? [] as $workstream) {
            if ($workstream['confidence'] >= 0.7) {
                $suggestions['workstreams'][] = [
                    'suggested_name' => $workstream['name'],
                    'confidence' => $workstream['confidence'],
                    'context' => $workstream['context']
                ];
            }
        }

        foreach ($unmatchedEntities['releases']['unmatched'] ?? [] as $release) {
            if ($release['confidence'] >= 0.7) {
                $suggestions['releases'][] = [
                    'suggested_version' => $release['version'] ?? $release['name'],
                    'confidence' => $release['confidence'],
                    'context' => $release['context']
                ];
            }
        }

        return $suggestions;
    }

    public function updateConfidenceScores(Content $content, array $feedback): void
    {
        // This would update confidence scores based on user feedback
        // Implementation would depend on how feedback is collected
        Log::info("Updating confidence scores based on feedback", [
            'content_id' => $content->id,
            'feedback_count' => count($feedback)
        ]);
    }

    public function filterByConfidenceThreshold(array $entities, float $threshold = 0.7): array
    {
        $filtered = [];

        foreach ($entities as $type => $entityList) {
            if ($type === 'summary') {
                $filtered[$type] = $entityList;
                continue;
            }

            if (is_array($entityList)) {
                $filtered[$type] = array_values(array_filter($entityList, function ($entity) use ($threshold) {
                    return ($entity['confidence'] ?? 0) >= $threshold;
                }));
            } else {
                $filtered[$type] = $entityList;
            }
        }

        return $filtered;
    }

    public function matchEntitiesToExisting($user, array $detectedEntities): array
    {
        $content = new Content(['user_id' => $user->id]);
        return $this->matchEntitiesWithDatabase($content, $detectedEntities);
    }

    public function createContentAssociations(Content $content, array $matchedEntities): void
    {
        // Create stakeholder associations
        foreach ($matchedEntities['stakeholders']['matched'] ?? [] as $match) {
            $content->stakeholders()->attach($match['existing_id'], [
                'mention_type' => $match['mention_type'],
                'confidence_score' => $match['confidence'],
                'context' => $match['context']
            ]);
        }

        // Create workstream associations
        foreach ($matchedEntities['workstreams']['matched'] ?? [] as $match) {
            $content->workstreams()->attach($match['existing_id'], [
                'relevance_type' => $this->normalizeRelevanceType($match['relevance_type']),
                'confidence_score' => $match['confidence'],
                'context' => $match['context']
            ]);
        }

        // Create release associations
        foreach ($matchedEntities['releases']['matched'] ?? [] as $match) {
            $content->releases()->attach($match['existing_id'], [
                'relevance_type' => $this->normalizeRelevanceType($match['relevance_type']),
                'confidence_score' => $match['confidence'],
                'context' => $match['context']
            ]);
        }
    }

    public function filterByConfidence(array $entities, float $threshold): array
    {
        return $this->filterByConfidenceThreshold($entities, $threshold);
    }

    public function updateConfidenceFromFeedback(array $feedback): void
    {
        // Update confidence scores based on user feedback
        Log::info("Processing user feedback for confidence adjustment", [
            'feedback' => $feedback
        ]);
    }

    public function reprocessContentEntities(Content $content): bool
    {
        try {
            // Clear existing associations
            $content->stakeholders()->detach();
            $content->workstreams()->detach();
            $content->releases()->detach();

            // Reanalyze and create new associations
            $analysisResult = $this->analyzeContent($content);

            // Create new associations based on analysis
            if (!empty($analysisResult['stakeholders'])) {
                foreach ($analysisResult['stakeholders'] as $stakeholderData) {
                    $content->stakeholders()->attach($stakeholderData['stakeholder_id'], [
                        'mention_type' => $stakeholderData['mention_type'],
                        'confidence_score' => $stakeholderData['confidence_score'],
                        'context' => $stakeholderData['context']
                    ]);
                }
            }

            return true;
        } catch (\Exception $e) {
            Log::error("Reprocessing failed", [
                'content_id' => $content->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function createActionItems(Content $content, array $actionItemsData, $user): array
    {
        $createdActionItems = [];

        foreach ($actionItemsData as $actionItemData) {
            $assigneeId = null;

            if (!empty($actionItemData['assignee'])) {
                $assignee = $this->findStakeholderByName(
                    $user->id,
                    $actionItemData['assignee']
                );
                $assigneeId = $assignee?->id;
            }

            $actionItem = \App\Models\ContentActionItem::create([
                'content_id' => $content->id,
                'action_text' => $actionItemData['text'],
                'assignee_stakeholder_id' => $assigneeId,
                'priority' => $actionItemData['priority'] ?? 'medium',
                'due_date' => $actionItemData['due_date'] ?? null,
                'confidence_score' => $actionItemData['confidence'],
                'context' => $actionItemData['context'] ?? null,
                'status' => 'pending'
            ]);

            $createdActionItems[] = $actionItem;
        }

        return $createdActionItems;
    }
}
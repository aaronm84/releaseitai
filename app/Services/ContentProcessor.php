<?php

namespace App\Services;

use App\Models\Content;
use App\Models\Stakeholder;
use App\Models\Workstream;
use App\Models\Release;
use App\Models\ActionItem;
use App\Models\User;
use App\Services\AiService;
use App\Exceptions\ContentProcessingException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

class ContentProcessor
{
    private const SUPPORTED_CONTENT_TYPES = [
        'brain_dump',
        'email',
        'slack',
        'document',
        'meeting_notes',
        'teams',
        'file'
    ];

    private const TYPE_MAPPING = [
        'brain_dump' => 'manual',
        'document' => 'file',
        'email' => 'email',
        'slack' => 'slack',
        'meeting_notes' => 'meeting_notes',
        'teams' => 'teams',
        'file' => 'file'
    ];

    private const FUZZY_MATCH_THRESHOLD = 0.8;
    private const LOW_CONFIDENCE_THRESHOLD = 0.6;

    public function __construct(
        private AiService $aiService
    ) {}

    /**
     * Process content and extract entities with intelligent matching
     */
    public function process(string $content, string $type, array $metadata = []): array
    {
        if (!$this->validateContent($content, $type, $metadata)) {
            throw new ContentProcessingException('Invalid content provided');
        }

        try {
            // Extract entities using AI service
            $extractedEntities = $this->extractEntities($content, $type);

            // Match against existing entities
            $matchResults = $this->matchEntities($extractedEntities);

            // Generate confirmation tasks for uncertain matches
            $confirmationTasks = $this->generateConfirmationTasks($matchResults, $extractedEntities);

            // Store content and relationships (in separate transaction)
            $contentRecord = $this->storeContent($content, $type, $metadata, $extractedEntities, $matchResults);

            // Fire processing event
            Event::dispatch('content.processed', [
                'content_id' => $contentRecord->id,
                'type' => $type,
                'entities_extracted' => count($extractedEntities),
                'matches_found' => count($matchResults['exact_matches']),
                'confirmations_needed' => count($confirmationTasks)
            ]);

            return [
                'content_id' => $contentRecord->id,
                'extracted_entities' => $extractedEntities,
                'match_results' => $matchResults,
                'confirmation_tasks' => $confirmationTasks,
                'stats' => $this->getProcessingStats()
            ];

        } catch (\Exception $e) {
            Log::error('Content processing failed', [
                'type' => $type,
                'error' => $e->getMessage(),
                'content_preview' => substr($content, 0, 100)
            ]);
            throw new ContentProcessingException('Processing failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Process multiple content items in batch
     */
    public function processBatch(array $contents, string $type): array
    {
        $results = [];
        $errors = [];

        foreach ($contents as $index => $contentData) {
            try {
                $content = is_array($contentData) ? $contentData['content'] : $contentData;
                $metadata = is_array($contentData) ? ($contentData['metadata'] ?? []) : [];

                $results[$index] = $this->process($content, $type, $metadata);
            } catch (\Exception $e) {
                $errors[$index] = $e->getMessage();
            }
        }

        return [
            'successful' => $results,
            'failed' => $errors,
            'total_processed' => count($contents),
            'success_count' => count($results),
            'error_count' => count($errors)
        ];
    }

    /**
     * Match extracted entities against existing records
     */
    public function matchEntities(array $extractedEntities, array $options = []): array
    {
        $user = auth()->user();
        $threshold = $options['threshold'] ?? self::FUZZY_MATCH_THRESHOLD;

        $exactMatches = [];
        $fuzzyMatches = [];
        $newEntities = [];

        foreach ($extractedEntities as $entityType => $entities) {
            foreach ($entities as $entity) {
                $matches = $this->findEntityMatches($entityType, $entity, $user, $threshold);

                if (!empty($matches['exact'])) {
                    $exactMatches[] = [
                        'type' => $entityType,
                        'extracted' => $entity,
                        'matches' => $matches['exact'],
                        'confidence' => 1.0
                    ];
                } elseif (!empty($matches['fuzzy'])) {
                    $fuzzyMatches[] = [
                        'type' => $entityType,
                        'extracted' => $entity,
                        'matches' => $matches['fuzzy'],
                        'confidence' => $matches['best_score']
                    ];
                } else {
                    $newEntities[] = [
                        'type' => $entityType,
                        'extracted' => $entity,
                        'confidence' => $this->calculateNewEntityConfidence($entity)
                    ];
                }
            }
        }

        return [
            'exact_matches' => $exactMatches,
            'fuzzy_matches' => $fuzzyMatches,
            'new_entities' => $newEntities
        ];
    }

    /**
     * Generate confirmation tasks for uncertain matches
     */
    public function generateConfirmationTasks(array $matchResults, array $extractedEntities): array
    {
        $tasks = [];

        // Low confidence fuzzy matches need confirmation
        foreach ($matchResults['fuzzy_matches'] as $match) {
            if ($match['confidence'] < self::LOW_CONFIDENCE_THRESHOLD) {
                $tasks[] = [
                    'type' => 'confirm_entity_match',
                    'priority' => 'medium',
                    'entity_type' => $match['type'],
                    'extracted_entity' => $match['extracted'],
                    'suggested_matches' => $match['matches'],
                    'confidence' => $match['confidence'],
                    'action_required' => 'confirm_or_create_new'
                ];
            }
        }

        // High confidence new entities might need confirmation
        foreach ($matchResults['new_entities'] as $newEntity) {
            if ($newEntity['confidence'] > 0.8) {
                $tasks[] = [
                    'type' => 'confirm_new_entity',
                    'priority' => 'low',
                    'entity_type' => $newEntity['type'],
                    'extracted_entity' => $newEntity['extracted'],
                    'confidence' => $newEntity['confidence'],
                    'action_required' => 'confirm_creation'
                ];
            }
        }

        // Sort by priority: high > medium > low
        $priorityOrder = ['high' => 3, 'medium' => 2, 'low' => 1];
        usort($tasks, function($a, $b) use ($priorityOrder) {
            return $priorityOrder[$b['priority']] <=> $priorityOrder[$a['priority']];
        });

        return $tasks;
    }

    /**
     * Execute a confirmation task based on user input
     */
    public function executeConfirmationTask(array $task, array $userInput): array
    {
        $user = auth()->user();

        switch ($task['type']) {
            case 'confirm_entity_match':
                if ($userInput['action'] === 'confirm_match') {
                    $entityId = $userInput['selected_entity_id'];
                    return $this->confirmEntityMatch($task, $entityId, $user);
                } elseif ($userInput['action'] === 'create_new') {
                    return $this->createNewEntity($task['entity_type'], $task['extracted_entity'], $user);
                }
                break;

            case 'confirm_new_entity':
                if ($userInput['action'] === 'confirm_creation') {
                    return $this->createNewEntity($task['entity_type'], $task['extracted_entity'], $user);
                }
                break;
        }

        throw new ContentProcessingException('Invalid confirmation task action');
    }

    /**
     * Get supported content types
     */
    public function getSupportedContentTypes(): array
    {
        return self::SUPPORTED_CONTENT_TYPES;
    }

    /**
     * Validate content before processing
     */
    public function validateContent(string $content, string $type, array $metadata = []): bool
    {
        if (empty($content) || strlen($content) < 10) {
            return false;
        }

        if (!in_array($type, self::SUPPORTED_CONTENT_TYPES)) {
            return false;
        }

        if (strlen($content) > 50000) {
            return false;
        }

        return true;
    }

    /**
     * Get processing statistics
     */
    public function getProcessingStats(): array
    {
        $user = auth()->user();

        return [
            'total_content_processed' => Content::where('user_id', $user->id)->count(),
            'entities_extracted_today' => $this->getEntitiesExtractedToday($user),
            'confirmation_tasks_pending' => $this->getPendingConfirmationTasks($user),
            'processing_accuracy' => $this->calculateProcessingAccuracy($user)
        ];
    }

    /**
     * Extract entities from content using AI service
     */
    private function extractEntities(string $content, string $type): array
    {
        // Extract action items
        $actionItemsResponse = $this->aiService->extractActionItems($content);
        $actionItems = json_decode($actionItemsResponse->getContent(), true) ?? [];

        // Analyze entities
        $entities = $this->aiService->analyzeContentEntities($content);

        return [
            'stakeholders' => $entities['stakeholders'] ?? [],
            'workstreams' => $entities['workstreams'] ?? [],
            'releases' => $entities['releases'] ?? [],
            'action_items' => $actionItems,
            'meetings' => $this->extractMeetings($entities),
            'decisions' => $this->extractDecisions($entities)
        ];
    }

    /**
     * Find matches for a specific entity
     */
    private function findEntityMatches(string $entityType, array $entity, User $user, float $threshold): array
    {
        $exactMatches = [];
        $fuzzyMatches = [];
        $bestScore = 0;

        switch ($entityType) {
            case 'stakeholders':
                $existing = Stakeholder::where('user_id', $user->id)->get();
                foreach ($existing as $stakeholder) {
                    $score = $this->calculateStakeholderMatchScore($entity, $stakeholder);
                    if ($score === 1.0) {
                        $exactMatches[] = $stakeholder;
                    } elseif ($score >= $threshold) {
                        $fuzzyMatches[] = ['entity' => $stakeholder, 'score' => $score];
                        $bestScore = max($bestScore, $score);
                    }
                }
                break;

            case 'workstreams':
                $existing = Workstream::where('owner_id', $user->id)->get();
                foreach ($existing as $workstream) {
                    $score = $this->calculateWorkstreamMatchScore($entity, $workstream);
                    if ($score === 1.0) {
                        $exactMatches[] = $workstream;
                    } elseif ($score >= $threshold) {
                        $fuzzyMatches[] = ['entity' => $workstream, 'score' => $score];
                        $bestScore = max($bestScore, $score);
                    }
                }
                break;

            case 'releases':
                // Releases are linked to users through workstreams
                $existing = Release::whereHas('workstream', function($query) use ($user) {
                    $query->where('owner_id', $user->id);
                })->get();
                foreach ($existing as $release) {
                    $score = $this->calculateReleaseMatchScore($entity, $release);
                    if ($score === 1.0) {
                        $exactMatches[] = $release;
                    } elseif ($score >= $threshold) {
                        $fuzzyMatches[] = ['entity' => $release, 'score' => $score];
                        $bestScore = max($bestScore, $score);
                    }
                }
                break;
        }

        return [
            'exact' => $exactMatches,
            'fuzzy' => $fuzzyMatches,
            'best_score' => $bestScore
        ];
    }

    /**
     * Calculate match score for stakeholders
     */
    private function calculateStakeholderMatchScore(array $extracted, Stakeholder $existing): float
    {
        $name = $extracted['name'] ?? '';
        $email = $extracted['email'] ?? '';

        // Exact email match = 100%
        if (!empty($email) && $existing->email === $email) {
            return 1.0;
        }

        // Exact name match = 100%
        if (!empty($name) && strtolower($existing->name) === strtolower($name)) {
            return 1.0;
        }

        // Fuzzy name matching
        if (!empty($name)) {
            $similarity = 0;
            similar_text(strtolower($name), strtolower($existing->name), $similarity);
            return $similarity / 100;
        }

        return 0;
    }

    /**
     * Calculate match score for workstreams
     */
    private function calculateWorkstreamMatchScore(array $extracted, Workstream $existing): float
    {
        $name = $extracted['name'] ?? '';

        if (empty($name)) {
            return 0;
        }

        // Exact match
        if (strtolower($existing->name) === strtolower($name)) {
            return 1.0;
        }

        // Fuzzy match
        $similarity = 0;
        similar_text(strtolower($name), strtolower($existing->name), $similarity);
        return $similarity / 100;
    }

    /**
     * Calculate match score for releases
     */
    private function calculateReleaseMatchScore(array $extracted, Release $existing): float
    {
        $name = $extracted['name'] ?? '';
        $version = $extracted['version'] ?? '';

        if (empty($name) && empty($version)) {
            return 0;
        }

        // Exact version match = 100%
        if (!empty($version) && $existing->version === $version) {
            return 1.0;
        }

        // Exact name match = 100%
        if (!empty($name) && strtolower($existing->name) === strtolower($name)) {
            return 1.0;
        }

        // Fuzzy matching
        $nameScore = 0;
        if (!empty($name)) {
            similar_text(strtolower($name), strtolower($existing->name), $nameScore);
            $nameScore /= 100;
        }

        return $nameScore;
    }

    /**
     * Calculate confidence for new entity creation
     */
    private function calculateNewEntityConfidence(array $entity): float
    {
        $confidence = 0.5; // Base confidence

        // Higher confidence if we have complete information
        if (isset($entity['name']) && !empty($entity['name'])) {
            $confidence += 0.2;
        }

        if (isset($entity['email']) && !empty($entity['email'])) {
            $confidence += 0.3;
        }

        // Additional context boosts confidence
        if (isset($entity['company']) || isset($entity['department'])) {
            $confidence += 0.1;
        }

        return min($confidence, 1.0);
    }

    /**
     * Store content and entity relationships
     */
    private function storeContent(string $content, string $type, array $metadata, array $extractedEntities, array $matchResults): Content
    {
        $user = auth()->user();

        // Map content type to database-allowed type
        $databaseType = self::TYPE_MAPPING[$type] ?? 'manual';

        // Create content record with extracted entities in metadata
        $contentMetadata = array_merge($metadata, [
            'extracted_entities' => $extractedEntities,
            'processing_timestamp' => now()->toISOString()
        ]);

        $contentRecord = Content::create([
            'user_id' => $user->id,
            'title' => $this->generateContentTitle($content, $type),
            'content' => $content,
            'type' => $databaseType,
            'status' => 'processed',
            'tags' => $this->generateTags($type, $extractedEntities),
            'metadata' => $contentMetadata
        ]);

        // Create actual database records for extracted entities
        $this->persistExtractedEntities($extractedEntities, $contentRecord);

        // Store entity relationships
        $this->storeEntityRelationships($contentRecord, $matchResults);

        // Store action items
        $this->storeActionItems($contentRecord, $extractedEntities['action_items'] ?? []);

        return $contentRecord;
    }

    /**
     * Generate appropriate title for content
     */
    private function generateContentTitle(string $content, string $type): string
    {
        $typeLabels = [
            'brain_dump' => 'Brain Dump',
            'email' => 'Email',
            'slack' => 'Slack Message',
            'document' => 'Document',
            'meeting_notes' => 'Meeting Notes',
            'teams' => 'Teams Message',
            'file' => 'File Upload'
        ];

        $typeLabel = $typeLabels[$type] ?? ucfirst($type);
        return $typeLabel . ' - ' . now()->format('M j, Y g:i A');
    }

    /**
     * Generate tags based on content type and entities
     */
    private function generateTags(string $type, array $extractedEntities): array
    {
        $tags = [$type];

        if (!empty($extractedEntities['action_items'])) {
            $tags[] = 'action_items';
        }

        if (!empty($extractedEntities['meetings'])) {
            $tags[] = 'meetings';
        }

        if (!empty($extractedEntities['decisions'])) {
            $tags[] = 'decisions';
        }

        return $tags;
    }

    /**
     * Store entity relationships in pivot tables
     */
    private function storeEntityRelationships(Content $content, array $matchResults): void
    {
        foreach ($matchResults['exact_matches'] as $match) {
            $this->attachEntityToContent($content, $match['matches'][0], $match['type'], 1.0);
        }

        foreach ($matchResults['fuzzy_matches'] as $match) {
            if ($match['confidence'] >= self::LOW_CONFIDENCE_THRESHOLD) {
                $entity = $match['matches'][0]['entity'];
                $confidence = $match['matches'][0]['score'];
                $this->attachEntityToContent($content, $entity, $match['type'], $confidence);
            }
        }
    }

    /**
     * Attach entity to content with confidence score
     */
    private function attachEntityToContent(Content $content, $entity, string $type, float $confidence): void
    {
        switch ($type) {
            case 'stakeholders':
                $content->stakeholders()->attach($entity->id, ['confidence_score' => $confidence]);
                break;
            case 'workstreams':
                $content->workstreams()->attach($entity->id, ['confidence_score' => $confidence]);
                break;
            case 'releases':
                $content->releases()->attach($entity->id, ['confidence_score' => $confidence]);
                break;
        }
    }

    /**
     * Store action items
     */
    private function storeActionItems(Content $content, array $actionItems): void
    {
        foreach ($actionItems as $item) {
            ActionItem::create([
                'content_id' => $content->id,
                'action_text' => $item['action_text'] ?? $item['title'] ?? '',
                'priority' => $item['priority'] ?? 'medium',
                'status' => 'pending',
                'due_date' => $item['due_date'] ?? null,
                'assignee_stakeholder_id' => $this->findAssigneeId($item['assignee'] ?? null)
            ]);
        }
    }

    /**
     * Find stakeholder ID for assignee
     */
    private function findAssigneeId(?string $assigneeName): ?int
    {
        if (empty($assigneeName)) {
            return null;
        }

        $user = auth()->user();
        $stakeholder = Stakeholder::where('user_id', $user->id)
            ->where('name', 'ILIKE', "%{$assigneeName}%")
            ->first();

        return $stakeholder?->id;
    }

    /**
     * Extract meetings from entities
     */
    private function extractMeetings(array $entities): array
    {
        $meetings = [];

        foreach ($entities as $entity) {
            if (($entity['type'] ?? '') === 'meeting' || ($entity['type'] ?? '') === 'event') {
                $meetings[] = [
                    'title' => $entity['name'] ?? $entity['title'] ?? '',
                    'date' => $entity['date'] ?? null,
                    'attendees' => $entity['attendees'] ?? []
                ];
            }
        }

        return $meetings;
    }

    /**
     * Extract decisions from entities
     */
    private function extractDecisions(array $entities): array
    {
        $decisions = [];

        foreach ($entities as $entity) {
            if (($entity['type'] ?? '') === 'decision' || ($entity['type'] ?? '') === 'resolution') {
                $decisions[] = [
                    'title' => $entity['name'] ?? $entity['title'] ?? '',
                    'impact' => $entity['impact'] ?? 'medium',
                    'date' => $entity['date'] ?? now()->toDateString()
                ];
            }
        }

        return $decisions;
    }

    /**
     * Confirm entity match
     */
    private function confirmEntityMatch(array $task, int $entityId, User $user): array
    {
        // Implementation for confirming entity matches
        return ['status' => 'confirmed', 'entity_id' => $entityId];
    }

    /**
     * Create new entity
     */
    private function createNewEntity(string $entityType, array $extractedEntity, User $user): array
    {
        switch ($entityType) {
            case 'stakeholders':
                $entity = Stakeholder::create([
                    'user_id' => $user->id,
                    'name' => $extractedEntity['name'],
                    'email' => $extractedEntity['email'] ?? null,
                    'company' => $extractedEntity['company'] ?? null,
                    'role' => $extractedEntity['role'] ?? null
                ]);
                break;

            case 'workstreams':
                $entity = Workstream::create([
                    'owner_id' => $user->id,
                    'name' => $extractedEntity['name'],
                    'description' => $extractedEntity['description'] ?? null
                ]);
                break;

            case 'releases':
                // Releases need a workstream, create a default one if none exists
                $defaultWorkstream = Workstream::firstOrCreate([
                    'owner_id' => $user->id,
                    'name' => 'General'
                ], [
                    'description' => 'Default workstream for releases'
                ]);

                $entity = Release::create([
                    'workstream_id' => $defaultWorkstream->id,
                    'name' => $extractedEntity['name'],
                    'version' => $extractedEntity['version'] ?? null,
                    'description' => $extractedEntity['description'] ?? null,
                    'target_date' => $extractedEntity['planned_date'] ?? $extractedEntity['target_date'] ?? null
                ]);
                break;

            default:
                throw new ContentProcessingException("Unsupported entity type: {$entityType}");
        }

        return ['status' => 'created', 'entity' => $entity];
    }

    /**
     * Get entities extracted today
     */
    private function getEntitiesExtractedToday(User $user): int
    {
        return Content::where('user_id', $user->id)
            ->whereDate('created_at', today())
            ->count();
    }

    /**
     * Get pending confirmation tasks
     */
    private function getPendingConfirmationTasks(User $user): int
    {
        // This would be implemented with a proper confirmation tasks table
        return 0;
    }

    /**
     * Calculate processing accuracy
     */
    private function calculateProcessingAccuracy(User $user): float
    {
        // This would be calculated based on user feedback on AI accuracy
        return 0.85; // Placeholder
    }


    /**
     * Create database records for extracted entities
     */
    private function persistExtractedEntities(array $extractedEntities, Content $contentRecord): void
    {
        $user = auth()->user();

        // Process entities separately to avoid transaction rollback issues
        $this->persistStakeholders($extractedEntities, $user);
        $this->persistWorkstreams($extractedEntities, $user);
    }

    /**
     * Persist stakeholder entities
     */
    private function persistStakeholders(array $extractedEntities, $user): void
    {
        if (isset($extractedEntities['stakeholders']) && is_array($extractedEntities['stakeholders'])) {
            Log::info('Processing stakeholders', ['count' => count($extractedEntities['stakeholders'])]);

            foreach ($extractedEntities['stakeholders'] as $stakeholder) {
                if (!isset($stakeholder['name']) || empty($stakeholder['name'])) {
                    Log::warning('Skipping stakeholder without name', ['stakeholder' => $stakeholder]);
                    continue;
                }

                // Check if stakeholder already exists
                $existingStakeholder = Stakeholder::where('user_id', $user->id)
                    ->where('name', $stakeholder['name'])
                    ->first();

                if (!$existingStakeholder) {
                    try {
                        $newStakeholder = Stakeholder::create([
                            'user_id' => $user->id,
                            'name' => $stakeholder['name'],
                            'email' => $stakeholder['email'] ?? null,
                            'phone' => $stakeholder['phone'] ?? null,
                            'title' => $stakeholder['title'] ?? null,
                            'department' => $stakeholder['department'] ?? null,
                            'notes' => $stakeholder['context'] ?? 'Extracted from content'
                        ]);
                        Log::info('Created new stakeholder', ['id' => $newStakeholder->id, 'name' => $newStakeholder->name]);
                    } catch (\Exception $e) {
                        Log::error('Failed to create stakeholder', [
                            'stakeholder' => $stakeholder,
                            'error' => $e->getMessage()
                        ]);
                    }
                } else {
                    Log::info('Stakeholder already exists', ['name' => $stakeholder['name']]);
                }
            }
        }
    }

    /**
     * Persist workstream entities
     */
    private function persistWorkstreams(array $extractedEntities, $user): void
    {
        if (isset($extractedEntities['workstreams']) && is_array($extractedEntities['workstreams'])) {
            Log::info('Processing workstreams', ['count' => count($extractedEntities['workstreams'])]);

            foreach ($extractedEntities['workstreams'] as $workstream) {
                if (!isset($workstream['name']) || empty($workstream['name'])) {
                    Log::warning('Skipping workstream without name', ['workstream' => $workstream]);
                    continue;
                }

                // Check if workstream already exists
                $existingWorkstream = Workstream::where('owner_id', $user->id)
                    ->where('name', $workstream['name'])
                    ->first();

                if (!$existingWorkstream) {
                    try {
                        $newWorkstream = Workstream::create([
                            'owner_id' => $user->id,
                            'name' => $workstream['name'],
                            'description' => $workstream['context'] ?? 'Extracted from content',
                            'type' => 'initiative',
                            'status' => 'active'
                        ]);
                        Log::info('Created new workstream', ['id' => $newWorkstream->id, 'name' => $newWorkstream->name]);
                    } catch (\Exception $e) {
                        Log::error('Failed to create workstream', [
                            'workstream' => $workstream,
                            'error' => $e->getMessage()
                        ]);
                    }
                } else {
                    Log::info('Workstream already exists', ['name' => $workstream['name']]);
                }
            }
        }
    }
}
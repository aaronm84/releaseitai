<?php

namespace App\Services;

use App\Models\Input;
use App\Models\Output;
use App\Models\Feedback;
use App\Models\Embedding;
use App\Traits\DistributedCacheable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * RetrievalService - Handles similarity search and RAG prompt building for feedback learning
 *
 * This service provides retrieval-augmented generation (RAG) functionality by finding
 * similar feedback examples from the global pool and building contextual prompts.
 */
class RetrievalService
{
    use DistributedCacheable;
    /**
     * Find similar feedback examples for RAG prompting
     *
     * @param int $inputId
     * @param array $filters
     * @param int|null $limit
     * @return Collection
     */
    public function findSimilarFeedbackExamples(int $inputId, array $filters = [], int|null $limit = null): Collection
    {
        // Create cache key from parameters
        $cacheKey = $this->buildDistributedCacheKey('similar_feedback', [
            'hash' => md5($inputId . serialize($filters) . $limit)
        ]);

        // Check cache first using distributed cache
        $cached = $this->distributedCacheGet($cacheKey);
        if ($cached !== null) {
            return collect($cached);
        }

        $input = Input::find($inputId);
        if (!$input) {
            throw new InvalidArgumentException('Input not found');
        }

        // Get embedding for the input
        $inputEmbedding = Embedding::where('content_id', $inputId)
            ->where('content_type', 'App\Models\Input')
            ->first();

        if (!$inputEmbedding) {
            return collect();
        }

        // Build query for similar feedback examples using pgvector similarity
        $inputVectorString = $inputEmbedding->vector;

        $query = DB::table('embeddings as e')
            ->join('inputs as i', function($join) {
                $join->on('e.content_id', '=', 'i.id')
                     ->where('e.content_type', '=', 'App\Models\Input');
            })
            ->join('outputs as o', 'i.id', '=', 'o.input_id')
            ->join('feedback as f', 'o.id', '=', 'f.output_id')
            ->select([
                'i.id as input_id',
                'i.content as input_content',
                'i.type as input_type',
                'o.id as output_id',
                'o.content as output_content',
                'o.type as output_type',
                'o.quality_score as output_quality_score',
                'f.id as feedback_id',
                'f.action as feedback_action',
                'f.confidence as feedback_confidence',
                'f.metadata as feedback_metadata',
                DB::raw("1 - (e.vector <=> '{$inputVectorString}') as similarity_score")
            ])
            ->where('i.id', '!=', $inputId) // Exclude the query input itself
            ->orderBy('similarity_score', 'desc'); // Order by similarity (highest first)

        // Apply filters
        if (isset($filters['type'])) {
            $query->where('o.type', $filters['type']);
        }

        if (isset($filters['action'])) {
            $query->where('f.action', $filters['action']);
        }

        if (isset($filters['min_confidence'])) {
            $query->where('f.confidence', '>=', $filters['min_confidence']);
        }

        if (isset($filters['min_quality_score'])) {
            $query->where('o.quality_score', '>=', $filters['min_quality_score']);
        }

        // Context filtering (JSON metadata search)
        if (isset($filters['context'])) {
            $query->whereRaw("f.metadata->>'context' = ?", [$filters['context']]);
        }

        // Handle positive feedback only filter
        if (isset($filters['positive_feedback_only']) && $filters['positive_feedback_only']) {
            $query->where('f.action', 'accept')
                  ->where('f.confidence', '>=', 0.8);
        } elseif (!isset($filters['action'])) {
            // Default to positive feedback only (accept actions with high confidence)
            $query->where('f.action', 'accept')
                  ->where('f.confidence', '>=', 0.8);
        }

        // Apply similarity threshold filtering at database level for better performance
        if (isset($filters['min_similarity'])) {
            $query->whereRaw("1 - (e.vector <=> '{$inputVectorString}') >= ?", [$filters['min_similarity']]);
        }

        // Get results with similarity already calculated by pgvector
        $results = $query->get();

        $examples = $results->map(function ($row) {
            return [
                'input' => [
                    'id' => $row->input_id,
                    'content' => $row->input_content,
                    'type' => $row->input_type
                ],
                'output' => [
                    'id' => $row->output_id,
                    'content' => $row->output_content,
                    'type' => $row->output_type,
                    'quality_score' => (float) $row->output_quality_score
                ],
                'feedback' => [
                    'id' => $row->feedback_id,
                    'action' => $row->feedback_action,
                    'confidence' => (float) $row->feedback_confidence,
                    'metadata' => json_decode($row->feedback_metadata, true) ?? []
                ],
                'similarity_score' => (float) $row->similarity_score
            ];
        });

        // Results are already sorted by similarity (highest first) due to ORDER BY in query

        // Apply limit
        if ($limit) {
            $examples = $examples->take($limit);
        }

        $result = $examples->values();

        // Cache the results using distributed cache
        $this->cacheSimilarityData($cacheKey, function () use ($result) {
            return $result->toArray();
        }, ["input_similarity:{$inputId}", 'rag_similarity']);

        return $result;
    }

    /**
     * Find personalized examples based on user's feedback history
     *
     * @param int $inputId
     * @param int $userId
     * @param array $options
     * @return Collection
     */
    public function findPersonalizedExamples(int $inputId, int $userId, array $options = []): Collection
    {
        // Get user's feedback patterns
        $userFeedbackPatterns = $this->getUserFeedbackPatterns($userId);

        // Adjust filters based on user preferences
        $filters = $options['filters'] ?? [];

        // Prefer output types the user typically accepts
        if (isset($userFeedbackPatterns['preferred_output_types']) && !isset($filters['type'])) {
            $preferredTypes = $userFeedbackPatterns['preferred_output_types'];
            if (!empty($preferredTypes)) {
                $filters['type'] = $preferredTypes[0]; // Use most preferred type
            }
        }

        // Adjust confidence threshold based on user's typical confidence levels
        if (isset($userFeedbackPatterns['avg_confidence'])) {
            $filters['min_confidence'] = max(0.6, $userFeedbackPatterns['avg_confidence'] - 0.2);
        }

        $limit = $options['limit'] ?? 5;

        // Get similar examples with personalized filters
        $examples = $this->findSimilarFeedbackExamples($inputId, $filters, $limit);

        // Weight examples based on user's historical preferences
        return $examples->map(function ($example) use ($userFeedbackPatterns) {
            $personalizedScore = $this->calculatePersonalizationScore($example, $userFeedbackPatterns);
            $example['personalization_score'] = $personalizedScore;
            $example['combined_score'] = ($example['similarity_score'] + $personalizedScore) / 2;
            return $example;
        })->sortByDesc('combined_score')->values();
    }

    /**
     * Build RAG prompt with retrieved examples
     *
     * @param string $currentInput
     * @param Collection $examples
     * @param array $config
     * @return string
     */
    public function buildRagPrompt(string $currentInput, Collection $examples, array $config = []): string
    {
        $maxExamples = $config['max_examples'] ?? 3;
        $includeMetadata = $config['include_metadata'] ?? false;
        $promptTemplate = $config['template'] ?? 'default';

        // Limit examples
        $examples = $examples->take($maxExamples);

        // Build prompt based on template
        if ($promptTemplate === 'default') {
            return $this->buildDefaultRagPrompt($currentInput, $examples, $includeMetadata);
        } else {
            return $this->buildCustomRagPrompt($currentInput, $examples, $config);
        }
    }

    /**
     * Parse vector string to array
     *
     * @param string $vector
     * @return array
     */
    private function parseVector(string $vector): array
    {
        // Remove brackets and split by comma
        $vector = trim($vector, '[]');
        $values = explode(',', $vector);
        return array_map('floatval', array_map('trim', $values));
    }

    /**
     * Calculate cosine similarity between two vectors
     *
     * @param array $vector1
     * @param array $vector2
     * @return float
     */
    private function calculateCosineSimilarity(array $vector1, array $vector2): float
    {
        if (count($vector1) !== count($vector2)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $magnitude1 = 0.0;
        $magnitude2 = 0.0;

        for ($i = 0; $i < count($vector1); $i++) {
            $dotProduct += $vector1[$i] * $vector2[$i];
            $magnitude1 += $vector1[$i] * $vector1[$i];
            $magnitude2 += $vector2[$i] * $vector2[$i];
        }

        $magnitude1 = sqrt($magnitude1);
        $magnitude2 = sqrt($magnitude2);

        if ($magnitude1 == 0.0 || $magnitude2 == 0.0) {
            return 0.0;
        }

        return $dotProduct / ($magnitude1 * $magnitude2);
    }

    /**
     * Get user's feedback patterns for personalization
     *
     * @param int $userId
     * @return array
     */
    private function getUserFeedbackPatterns(int $userId): array
    {
        $cacheKey = $this->buildDistributedCacheKey('user_feedback_patterns', [
            'user_id' => $userId
        ]);

        return $this->cacheFeedbackData($cacheKey, function () use ($userId) {
            $feedbacks = Feedback::where('user_id', $userId)
                ->with(['output'])
                ->get();

            if ($feedbacks->isEmpty()) {
                return [];
            }

            // Analyze output type preferences
            $outputTypes = [];
            $confidences = [];
            $actions = [];

            foreach ($feedbacks as $feedback) {
                $outputType = $feedback->output->type ?? 'unknown';
                $outputTypes[$outputType] = ($outputTypes[$outputType] ?? 0) + 1;
                $confidences[] = $feedback->confidence;
                $actions[] = $feedback->action;
            }

            // Sort by frequency
            arsort($outputTypes);

            return [
                'preferred_output_types' => array_keys($outputTypes),
                'avg_confidence' => count($confidences) > 0 ? array_sum($confidences) / count($confidences) : 0.8,
                'action_distribution' => array_count_values($actions),
                'total_feedback_count' => $feedbacks->count()
            ];
        });
    }

    /**
     * Calculate personalization score for an example
     *
     * @param array $example
     * @param array $userPatterns
     * @return float
     */
    private function calculatePersonalizationScore(array $example, array $userPatterns): float
    {
        $score = 0.0;

        // Output type preference (0.4 weight)
        if (isset($userPatterns['preferred_output_types'])) {
            $outputType = $example['output']['type'];
            $typeIndex = array_search($outputType, $userPatterns['preferred_output_types']);
            if ($typeIndex !== false) {
                $score += 0.4 * (1.0 - ($typeIndex / count($userPatterns['preferred_output_types'])));
            }
        }

        // Confidence alignment (0.3 weight)
        if (isset($userPatterns['avg_confidence'])) {
            $confidenceDiff = abs($example['feedback']['confidence'] - $userPatterns['avg_confidence']);
            $score += 0.3 * (1.0 - $confidenceDiff);
        }

        // Action preference (0.3 weight)
        if (isset($userPatterns['action_distribution'])) {
            $action = $example['feedback']['action'];
            $actionCount = $userPatterns['action_distribution'][$action] ?? 0;
            $totalActions = array_sum($userPatterns['action_distribution']);
            if ($totalActions > 0) {
                $score += 0.3 * ($actionCount / $totalActions);
            }
        }

        return min(1.0, max(0.0, $score));
    }

    /**
     * Build default RAG prompt template
     *
     * @param string $currentInput
     * @param Collection $examples
     * @param bool $includeMetadata
     * @return string
     */
    private function buildDefaultRagPrompt(string $currentInput, Collection $examples, bool $includeMetadata): string
    {
        $prompt = "You are ReleaseIt.ai, an assistant for Product Managers.\n";
        $prompt .= "Learn from these high-quality examples where users provided positive feedback:\n\n";

        foreach ($examples as $index => $example) {
            $prompt .= "EXAMPLE " . ($index + 1) . ":\n";
            $prompt .= "Input: " . $example['input']['content'] . "\n";
            $prompt .= "AI Output: " . $example['output']['content'] . "\n";
            $prompt .= "User Feedback: " . $example['feedback']['action'] . " (confidence: " . $example['feedback']['confidence'] . ")\n";

            // Include feedback corrections and metadata
            if (!empty($example['feedback']['metadata'])) {
                $metadata = is_array($example['feedback']['metadata'])
                    ? $example['feedback']['metadata']
                    : json_decode($example['feedback']['metadata'], true);

                if ($metadata) {
                    // Include corrected content if it exists
                    if (isset($metadata['corrected_content'])) {
                        $prompt .= "User Corrections: " . $metadata['corrected_content'] . "\n";
                    }

                    // Include edit reason if it exists
                    if (isset($metadata['edit_reason'])) {
                        $prompt .= "Edit Reason: " . $metadata['edit_reason'] . "\n";
                    }

                    // Include other metadata if requested
                    if ($includeMetadata) {
                        $prompt .= "Context: " . json_encode($metadata) . "\n";
                    }
                }
            }

            $prompt .= "\n";
        }

        $prompt .= "Now generate a high-quality response for this new input:\n";
        $prompt .= "Input: " . $currentInput . "\n\n";
        $prompt .= "Respond with a JSON object containing your output:\n";

        return $prompt;
    }

    /**
     * Build custom RAG prompt template
     *
     * @param string $currentInput
     * @param Collection $examples
     * @param array $config
     * @return string
     */
    private function buildCustomRagPrompt(string $currentInput, Collection $examples, array $config): string
    {
        $template = $config['custom_template'] ?? $this->buildDefaultRagPrompt($currentInput, $examples, $config['include_metadata'] ?? false);

        // Replace placeholders
        $template = str_replace('{input}', $currentInput, $template);
        $template = str_replace('{example_count}', $examples->count(), $template);

        return $template;
    }
}
<?php

namespace App\Services;

use App\Models\Feedback;
use App\Models\Output;
use App\Models\Input;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Exception;

/**
 * FeedbackService - Captures and processes user corrections in the AI feedback system
 *
 * This service handles both explicit feedback (accept/edit/reject) and passive signals
 * (task completion, deletion, time spent) to improve AI outputs over time.
 */
class FeedbackService
{
    /**
     * Capture inline feedback (accept/edit/reject) on AI-generated content
     *
     * @param array $feedbackData
     * @return Feedback
     * @throws InvalidArgumentException
     */
    public function captureInlineFeedback(array $feedbackData): Feedback
    {
        $this->validateFeedbackData($feedbackData);

        // Ensure output exists and user has access
        $this->validateOutputExistenceAndAccess($feedbackData['output_id'], $feedbackData['user_id']);

        // Check rate limiting
        if (!$this->checkRateLimit($feedbackData['user_id'])) {
            throw new InvalidArgumentException('Feedback rate limit exceeded for user');
        }

        // Validate confidence score range
        if (isset($feedbackData['confidence'])) {
            $this->validateConfidenceScoreRange($feedbackData['confidence']);
        }

        // Validate required metadata by type
        $this->validateRequiredMetadataByType($feedbackData);

        $feedback = DB::transaction(function () use ($feedbackData) {
            $feedback = Feedback::create([
                'output_id' => $feedbackData['output_id'],
                'user_id' => $feedbackData['user_id'],
                'type' => 'inline',
                'action' => $feedbackData['action'],
                'signal_type' => 'explicit',
                'confidence' => $feedbackData['confidence'] ?? $this->getDefaultConfidence($feedbackData['action']),
                'metadata' => $this->prepareMetadata($feedbackData)
            ]);

            return $feedback;
        });

        return $feedback;
    }

    /**
     * Capture passive signals (task completion, deletion, time spent)
     *
     * @param array $signalData
     * @return Feedback
     */
    public function capturePassiveSignal(array $signalData): Feedback
    {
        $this->validateFeedbackData($signalData);

        $feedback = Feedback::create([
            'output_id' => $signalData['output_id'],
            'user_id' => $signalData['user_id'],
            'type' => 'behavioral',
            'action' => $signalData['action'],
            'signal_type' => 'passive',
            'confidence' => $signalData['confidence'] ?? 0.8,
            'metadata' => $this->prepareMetadata($signalData)
        ]);

        return $feedback;
    }

    /**
     * Process multiple feedback submissions in a batch
     *
     * @param array $batchData
     * @return array
     */
    public function processBatchFeedback(array $batchData): array
    {
        $results = [];
        $errors = [];

        DB::transaction(function () use ($batchData, &$results, &$errors) {
            foreach ($batchData as $index => $feedbackData) {
                try {
                    if ($feedbackData['type'] === 'inline') {
                        $feedback = $this->captureInlineFeedback($feedbackData);
                    } else {
                        $feedback = $this->capturePassiveSignal($feedbackData);
                    }
                    $results[] = $feedback;
                } catch (Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'error' => $e->getMessage(),
                        'data' => $feedbackData
                    ];
                }
            }
        });

        return [
            'success' => count($errors) === 0,
            'successful' => $results,
            'failed' => $errors,
            'total_processed' => count($batchData),
            'processed_count' => count($results),
            'success_count' => count($results),
            'failed_count' => count($errors),
            'error_count' => count($errors),
            'errors' => $errors
        ];
    }

    /**
     * Generate comprehensive analytics from feedback data
     *
     * @param array $options
     * @return array
     */
    public function generateFeedbackAnalytics(array $options = []): array
    {
        $query = Feedback::query();

        // Apply filters
        if (isset($options['date_range'])) {
            $query->whereBetween('created_at', $options['date_range']);
        }

        if (isset($options['user_id'])) {
            $query->where('user_id', $options['user_id']);
        }

        // Get basic counts
        $totalFeedback = $query->count();
        $acceptCount = (clone $query)->where('action', 'accept')->count();
        $editCount = (clone $query)->where('action', 'edit')->count();
        $rejectCount = (clone $query)->where('action', 'reject')->count();

        // Calculate rates
        $acceptanceRate = $totalFeedback > 0 ? ($acceptCount / $totalFeedback) * 100 : 0;
        $editRate = $totalFeedback > 0 ? ($editCount / $totalFeedback) * 100 : 0;
        $rejectionRate = $totalFeedback > 0 ? ($rejectCount / $totalFeedback) * 100 : 0;

        // Get average confidence
        $avgConfidence = $query->avg('confidence') ?? 0;

        // Get feedback by type
        $inlineCount = (clone $query)->where('type', 'inline')->count();
        $behavioralCount = (clone $query)->where('type', 'behavioral')->count();

        return [
            'total_feedback' => $totalFeedback,
            'acceptance_rate' => round($acceptanceRate, 2),
            'edit_rate' => round($editRate, 2),
            'rejection_rate' => round($rejectionRate, 2),
            'average_confidence' => round($avgConfidence, 2),
            'inline_feedback_count' => $inlineCount,
            'behavioral_feedback_count' => $behavioralCount,
            'feedback_distribution' => [
                'accept' => $acceptCount,
                'edit' => $editCount,
                'reject' => $rejectCount
            ]
        ];
    }

    /**
     * Calculate feedback quality scores for given feedback IDs
     *
     * @param array $feedbackIds
     * @return array
     */
    public function calculateFeedbackQualityScores(array $feedbackIds): array
    {
        // Preserve order by fetching feedbacks in the order they were provided
        $feedbacks = collect($feedbackIds)->map(function($id) {
            return Feedback::find($id);
        })->filter();

        $individualScores = [];
        $totalScore = 0;
        $count = 0;

        foreach ($feedbacks as $feedback) {
            $score = $this->calculateSingleQualityScore($feedback);
            $individualScores[$feedback->id] = [
                'quality_score' => $score,
                'feedback_id' => $feedback->id,
                'confidence' => $feedback->confidence,
                'action' => $feedback->action,
                'type' => $feedback->type
            ];
            $totalScore += $score;
            $count++;
        }

        $aggregateScore = $count > 0 ? $totalScore / $count : 0;

        return [
            'individual_scores' => $individualScores,
            'aggregate_score' => round($aggregateScore, 2),
            'quality_factors' => [
                'confidence_consistency' => $this->calculateConfidenceConsistency($feedbacks),
                'action_diversity' => $this->calculateActionDiversity($feedbacks)
            ]
        ];
    }

    /**
     * Analyze feedback trends over time
     *
     * @param array $options
     * @return array
     */
    public function analyzeFeedbackTrends(array $options = []): array
    {
        $period = $options['period'] ?? 'day';
        $dateRange = $options['date_range'] ?? [Carbon::now()->subDays(30), Carbon::now()];

        $query = Feedback::whereBetween('created_at', $dateRange);

        if ($period === 'day') {
            $trends = $query->selectRaw('DATE(created_at) as date, COUNT(*) as count, action')
                ->groupBy('date', 'action')
                ->orderBy('date')
                ->get()
                ->groupBy('date');
        } else {
            $trends = $query->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, COUNT(*) as count, action')
                ->groupBy('year', 'month', 'action')
                ->orderBy('year', 'month')
                ->get()
                ->groupBy(function($item) {
                    return $item->year . '-' . sprintf('%02d', $item->month);
                });
        }

        return [
            'period' => $period,
            'date_range' => $dateRange,
            'trends' => $trends->toArray()
        ];
    }

    /**
     * Process feedback data for machine learning
     *
     * @param array $feedbackData
     * @return array
     */
    public function processFeedbackForLearning(array $feedbackData): array
    {
        $feedback = Feedback::find($feedbackData['feedback_id']);
        if (!$feedback) {
            throw new InvalidArgumentException('Feedback not found');
        }

        $output = $feedback->output;
        $input = $output->input;

        return [
            'feedback_id' => $feedback->id,
            'input_content' => $input->content,
            'output_content' => $output->content,
            'feedback_action' => $feedback->action,
            'confidence' => $feedback->confidence,
            'metadata' => $feedback->metadata,
            'learning_features' => $this->extractLearningFeatures($feedback, $input, $output)
        ];
    }

    /**
     * Update output integration status when feedback is processed
     *
     * @param int $outputId
     * @param int $feedbackId
     * @return void
     */
    public function updateOutputIntegrationStatus(int $outputId, int $feedbackId): void
    {
        $output = Output::find($outputId);
        if (!$output) {
            throw new InvalidArgumentException('Output not found');
        }

        $output->update([
            'feedback_integrated' => true,
            'feedback_count' => $output->feedback_count + 1
        ]);
    }

    /**
     * Update user preferences based on feedback history
     *
     * @param int $userId
     * @param array $feedbackHistory
     * @return array
     */
    public function updateUserPreferences(int $userId, array $feedbackHistory): array
    {
        // Analyze user's feedback patterns
        $acceptanceRate = $this->calculateUserAcceptanceRate($userId);
        $preferredActions = $this->getPreferredActions($feedbackHistory);
        $confidencePatterns = $this->analyzeConfidencePatterns($feedbackHistory);

        // Analyze preferred output types and detail levels from metadata
        $outputTypes = [];
        $detailLevels = [];

        foreach ($feedbackHistory as $feedback) {
            // Check for output_type in metadata or at root level
            $outputType = $feedback['metadata']['output_type'] ?? $feedback['output_type'] ?? null;
            if ($outputType) {
                $outputTypes[$outputType] = ($outputTypes[$outputType] ?? 0) + 1;
            }

            // Check for detail_level in metadata or at root level
            $detailLevel = $feedback['metadata']['detail_level'] ?? $feedback['detail_level'] ?? null;
            if ($detailLevel) {
                $detailLevels[$detailLevel] = ($detailLevels[$detailLevel] ?? 0) + 1;
            }
        }

        arsort($outputTypes);
        arsort($detailLevels);

        $preferences = [
            'user_id' => $userId,
            'acceptance_rate' => $acceptanceRate,
            'preferred_actions' => $preferredActions,
            'confidence_patterns' => $confidencePatterns,
            'preferred_output_types' => array_keys($outputTypes),
            'preferred_detail_level' => count($detailLevels) > 0 ? array_keys($detailLevels)[0] : 'medium',
            'confidence_score' => $confidencePatterns['average'] ?? 0.8,
            'updated_at' => Carbon::now()
        ];

        // Cache user preferences for quick access
        Cache::put("user_preferences_{$userId}", $preferences, now()->addHours(24));

        return $preferences;
    }

    /**
     * Calculate confidence score for feedback scenario
     *
     * @param array $scenario
     * @return float
     */
    public function calculateConfidenceScore(array $scenario): float
    {
        $action = $scenario['action'] ?? '';
        $signalType = $scenario['signal_type'] ?? 'explicit';

        // Base confidence scores by action type and signal type
        if ($signalType === 'explicit') {
            switch ($action) {
                case 'accept':
                    $baseScore = 1.0; // High confidence for explicit acceptance
                    break;
                case 'reject':
                    $baseScore = 1.0; // High confidence for explicit rejection
                    break;
                case 'edit':
                    $baseScore = 0.7; // Lower confidence for edits (indicates uncertainty)
                    break;
                default:
                    $baseScore = 0.8;
            }
        } else { // passive signals
            switch ($action) {
                case 'task_completed':
                    $baseScore = 0.9; // High confidence for task completion
                    break;
                case 'task_deleted':
                    $baseScore = 0.8; // Medium-high confidence for deletion
                    break;
                case 'time_spent':
                    $baseScore = 0.6; // Lower confidence for time-based signals
                    break;
                default:
                    $baseScore = 0.7;
            }
        }

        // Adjust based on timing factors
        if (isset($scenario['time_to_action'])) {
            $timeToAction = $scenario['time_to_action'];
            if ($timeToAction <= 5.0) {
                // Quick decisions - no adjustment for accept/reject, slight penalty for edit
                if ($action === 'edit' && $timeToAction <= 5.0) {
                    $baseScore = max(0.7, $baseScore); // Maintain minimum for quick edits
                }
            } elseif ($timeToAction > 30.0) {
                // Slow decisions - slight confidence reduction for uncertainty
                if ($action === 'edit') {
                    $baseScore = 0.7; // Slower edits indicate more uncertainty
                }
            }
        }

        // Adjust based on user experience (optional)
        if (isset($scenario['user_experience'])) {
            switch ($scenario['user_experience']) {
                case 'expert':
                    $baseScore = min(1.0, $baseScore + 0.1);
                    break;
                case 'beginner':
                    $baseScore = max(0.1, $baseScore - 0.1);
                    break;
                // intermediate gets no adjustment
            }
        }

        // Adjust based on completion time for passive signals
        if (isset($scenario['completion_time']) && $action === 'task_completed') {
            $completionTime = $scenario['completion_time'];
            if ($completionTime < 1800) { // Less than 30 minutes
                $baseScore = min(1.0, $baseScore + 0.1); // Quick completion is good
            } elseif ($completionTime > 7200) { // More than 2 hours
                $baseScore = max(0.5, $baseScore - 0.1); // Slow completion might indicate difficulty
            }
        }

        // Ensure score is within valid range
        return max(0.0, min(1.0, round($baseScore, 1)));
    }

    /**
     * Aggregate feedback patterns for a specific output
     *
     * @param int $outputId
     * @return array
     */
    public function aggregateFeedbackPatterns(int $outputId): array
    {
        $feedbacks = Feedback::where('output_id', $outputId)->get();

        $actionCounts = [];
        $totalConfidence = 0;
        $count = 0;

        foreach ($feedbacks as $feedback) {
            $action = $feedback->action;
            $actionCounts[$action] = ($actionCounts[$action] ?? 0) + 1;
            $totalConfidence += $feedback->confidence;
            $count++;
        }

        $averageConfidence = $count > 0 ? $totalConfidence / $count : 0;

        return [
            'action_distribution' => $actionCounts,
            'average_confidence' => round($averageConfidence, 2),
            'feedback_count' => $count,
            'most_common_action' => count($actionCounts) > 0 ? array_keys($actionCounts, max($actionCounts))[0] : null
        ];
    }

    /**
     * Perform temporal analysis of feedback trends
     *
     * @param array $options
     * @return array
     */
    public function performTemporalAnalysis(array $options = []): array
    {
        $dateRange = $options['date_range'] ?? [Carbon::now()->subDays(30), Carbon::now()];
        $includeForecast = $options['include_forecasting'] ?? false;

        $query = Feedback::whereBetween('created_at', $dateRange);

        // Get daily feedback counts
        $timeSeries = $query->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();

        // Calculate trends
        $counts = array_column($timeSeries, 'count');
        $trend = 'stable';
        if (count($counts) > 1) {
            $firstHalf = array_slice($counts, 0, count($counts) / 2);
            $secondHalf = array_slice($counts, count($counts) / 2);
            $firstAvg = count($firstHalf) > 0 ? array_sum($firstHalf) / count($firstHalf) : 0;
            $secondAvg = count($secondHalf) > 0 ? array_sum($secondHalf) / count($secondHalf) : 0;

            if ($secondAvg > $firstAvg * 1.1) {
                $trend = 'increasing';
            } elseif ($secondAvg < $firstAvg * 0.9) {
                $trend = 'decreasing';
            }
        }

        $result = [
            'time_series' => $timeSeries,
            'trends' => [
                'overall_trend' => $trend,
                'period_start' => $dateRange[0]->toDateString(),
                'period_end' => $dateRange[1]->toDateString()
            ],
            'anomalies' => [] // Simplified - would detect outliers in real implementation
        ];

        if ($includeForecast) {
            $avgCount = count($counts) > 0 ? array_sum($counts) / count($counts) : 0;
            $result['forecast'] = [
                'next_7_days' => $avgCount * 7, // Simple forecast
                'confidence' => 0.7
            ];
        }

        return $result;
    }

    /**
     * Get default confidence score based on action type
     *
     * @param string $action
     * @return float
     */
    private function getDefaultConfidence(string $action): float
    {
        return match($action) {
            'accept' => 1.0,
            'reject' => 1.0,
            'edit' => 0.7,  // Lower confidence for edits
            'task_completed' => 0.9,
            'task_deleted' => 0.8,
            'time_spent' => 0.6,
            default => 0.8
        };
    }

    /**
     * Implement basic rate limiting for feedback submissions
     *
     * @param int $userId
     * @return bool
     */
    public function checkRateLimit(int $userId): bool
    {
        $key = "feedback_rate_limit_{$userId}";
        $submissions = Cache::get($key, 0);

        if ($submissions >= 5) { // Max 5 submissions per minute
            return false;
        }

        Cache::put($key, $submissions + 1, now()->addMinute());
        return true;
    }

    /**
     * Validate feedback data structure and required fields
     *
     * @param array $feedbackData
     * @throws InvalidArgumentException
     */
    private function validateFeedbackData(array $feedbackData): void
    {
        $required = ['output_id', 'user_id', 'action'];

        foreach ($required as $field) {
            if (!isset($feedbackData[$field])) {
                throw new InvalidArgumentException("Missing required field: {$field}");
            }
        }

        // Validate action type
        $validActions = ['accept', 'edit', 'reject', 'task_completed', 'task_deleted', 'time_spent'];
        if (!in_array($feedbackData['action'], $validActions)) {
            throw new InvalidArgumentException("Invalid action: {$feedbackData['action']}");
        }
    }

    /**
     * Validate that output exists and user has access
     *
     * @param int $outputId
     * @param int $userId
     * @throws InvalidArgumentException
     */
    private function validateOutputExistenceAndAccess(int $outputId, int $userId): void
    {
        $output = Output::find($outputId);
        if (!$output) {
            throw new InvalidArgumentException('Output not found or not accessible');
        }

        // For now, assume all users have access
        // In production, add proper access control checks here
    }

    /**
     * Validate confidence score is within valid range
     *
     * @param float $confidence
     * @throws InvalidArgumentException
     */
    private function validateConfidenceScoreRange(float $confidence): void
    {
        if ($confidence < 0.0 || $confidence > 1.0) {
            throw new InvalidArgumentException('Confidence score must be between 0.0 and 1.0');
        }
    }

    /**
     * Validate required metadata by feedback type
     *
     * @param array $feedbackData
     * @throws InvalidArgumentException
     */
    private function validateRequiredMetadataByType(array $feedbackData): void
    {
        if ($feedbackData['action'] === 'edit') {
            // Check for corrected_content in metadata or at root level
            $hasInMetadata = isset($feedbackData['metadata']['corrected_content']);
            $hasAtRoot = isset($feedbackData['corrected_content']);

            if (!$hasInMetadata && !$hasAtRoot) {
                throw new InvalidArgumentException('Edit feedback requires corrected_content in metadata or root level');
            }
        }
    }

    /**
     * Prepare metadata for storage
     *
     * @param array $feedbackData
     * @return array
     * @throws InvalidArgumentException
     */
    private function prepareMetadata(array $feedbackData): array
    {
        $metadata = $feedbackData['metadata'] ?? [];

        // Add context if provided at root level (for backward compatibility)
        if (isset($feedbackData['context'])) {
            $metadata['context'] = $feedbackData['context'];
        }

        // Add other common metadata fields if present
        foreach (['corrected_content', 'original_content', 'edit_reason', 'rejection_reason', 'time_spent'] as $field) {
            if (isset($feedbackData[$field])) {
                $metadata[$field] = $feedbackData[$field];
            }
        }

        // Add timestamps and session info
        $metadata['timestamp'] = Carbon::now()->toISOString();

        // Handle session ID safely for testing
        try {
            $metadata['session_id'] = request()->session()->getId();
        } catch (Exception $e) {
            $metadata['session_id'] = 'test_session';
        }

        // Validate metadata size (limit to 1MB)
        $metadataSize = strlen(json_encode($metadata));
        if ($metadataSize > 1048576) { // 1MB limit
            throw new InvalidArgumentException("Metadata payload too large ({$metadataSize} bytes). Maximum allowed: 1MB");
        }

        return $metadata;
    }

    /**
     * Calculate quality score for individual feedback
     *
     * @param Feedback $feedback
     * @return float
     */
    private function calculateSingleQualityScore(Feedback $feedback): float
    {
        $baseScore = $feedback->confidence;

        // Adjust based on action type
        switch ($feedback->action) {
            case 'accept':
                $baseScore *= 1.0;
                break;
            case 'edit':
                $baseScore *= 0.9; // Slightly lower since it needed correction
                break;
            case 'reject':
                $baseScore *= 0.7; // Lower since output was not useful
                break;
        }

        return round($baseScore, 2);
    }

    /**
     * Extract learning features from feedback
     *
     * @param Feedback $feedback
     * @param Input $input
     * @param Output $output
     * @return array
     */
    private function extractLearningFeatures(Feedback $feedback, Input $input, Output $output): array
    {
        return [
            'input_length' => strlen($input->content),
            'output_length' => strlen($output->content),
            'action_type' => $feedback->action,
            'confidence_level' => $feedback->confidence,
            'feedback_type' => $feedback->type,
            'time_to_feedback' => $feedback->metadata['time_to_feedback'] ?? null
        ];
    }

    /**
     * Calculate user's overall acceptance rate
     *
     * @param int $userId
     * @return float
     */
    private function calculateUserAcceptanceRate(int $userId): float
    {
        $totalFeedback = Feedback::where('user_id', $userId)->count();
        if ($totalFeedback === 0) {
            return 0.0;
        }

        $acceptedFeedback = Feedback::where('user_id', $userId)
            ->where('action', 'accept')
            ->count();

        return round(($acceptedFeedback / $totalFeedback) * 100, 2);
    }

    /**
     * Get user's preferred actions from feedback history
     *
     * @param array $feedbackHistory
     * @return array
     */
    private function getPreferredActions(array $feedbackHistory): array
    {
        $actions = [];
        foreach ($feedbackHistory as $feedback) {
            $action = $feedback['action'] ?? 'unknown';
            $actions[$action] = ($actions[$action] ?? 0) + 1;
        }

        arsort($actions);
        return $actions;
    }

    /**
     * Analyze confidence patterns in feedback history
     *
     * @param array $feedbackHistory
     * @return array
     */
    private function analyzeConfidencePatterns(array $feedbackHistory): array
    {
        $confidences = array_column($feedbackHistory, 'confidence');

        if (empty($confidences)) {
            return ['average' => 0, 'trend' => 'no_data'];
        }

        $average = array_sum($confidences) / count($confidences);

        // Simple trend analysis - compare first half to second half
        $midpoint = count($confidences) / 2;
        $firstHalf = array_slice($confidences, 0, $midpoint);
        $secondHalf = array_slice($confidences, $midpoint);

        $firstAvg = array_sum($firstHalf) / count($firstHalf);
        $secondAvg = array_sum($secondHalf) / count($secondHalf);

        $trend = $secondAvg > $firstAvg ? 'improving' : ($secondAvg < $firstAvg ? 'declining' : 'stable');

        return [
            'average' => round($average, 2),
            'trend' => $trend
        ];
    }

    /**
     * Calculate confidence consistency across feedbacks
     *
     * @param $feedbacks
     * @return float
     */
    private function calculateConfidenceConsistency($feedbacks): float
    {
        if ($feedbacks->count() < 2) {
            return 1.0;
        }

        $confidences = $feedbacks->pluck('confidence')->toArray();
        $mean = array_sum($confidences) / count($confidences);
        $variance = array_sum(array_map(function($x) use ($mean) { return pow($x - $mean, 2); }, $confidences)) / count($confidences);
        $stdDev = sqrt($variance);

        // Return consistency score (lower std dev = higher consistency)
        return max(0, 1 - $stdDev);
    }

    /**
     * Calculate action diversity in feedbacks
     *
     * @param $feedbacks
     * @return float
     */
    private function calculateActionDiversity($feedbacks): float
    {
        $actions = $feedbacks->pluck('action')->unique();
        $totalActions = ['accept', 'edit', 'reject', 'task_completed', 'task_deleted', 'time_spent'];

        return count($actions) / count($totalActions);
    }
}
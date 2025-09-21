<?php

namespace App\Services;

use App\Models\AiJob;
use App\Services\Ai\OpenAiProvider;
use App\Services\Ai\AnthropicProvider;
use App\Services\Ai\Contracts\AiProviderInterface;
use App\Exceptions\AiServiceException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class AiService
{
    private AiProviderInterface $provider;
    private string $defaultProvider;

    public function __construct()
    {
        $this->defaultProvider = config('ai.default_provider', 'openai');
        $this->setProvider($this->defaultProvider);
    }

    /**
     * Set the AI provider to use
     */
    public function setProvider(string $provider): self
    {
        $this->provider = match($provider) {
            'openai' => new OpenAiProvider(),
            'anthropic' => new AnthropicProvider(),
            default => throw new AiServiceException("Unsupported AI provider: {$provider}")
        };

        return $this;
    }

    /**
     * Generate text completion with automatic provider selection
     */
    public function complete(string $prompt, array $options = []): AiResponse
    {
        // Determine best provider based on task complexity
        $provider = $this->selectOptimalProvider($prompt, $options);

        return $this->executeWithProvider($provider, 'complete', $prompt, $options);
    }

    /**
     * Summarize content efficiently
     */
    public function summarize(string $content, int $maxLength = 200): AiResponse
    {
        $prompt = $this->buildSummarizePrompt($content, $maxLength);

        // Use faster, cheaper model for summaries
        return $this->executeWithProvider('openai', 'complete', $prompt, [
            'model' => 'gpt-4o-mini',
            'max_tokens' => $maxLength * 2,
            'temperature' => 0.3
        ]);
    }

    /**
     * Extract action items from text
     */
    public function extractActionItems(string $content): AiResponse
    {
        $prompt = $this->buildActionItemsPrompt($content);

        return $this->executeWithProvider('openai', 'complete', $prompt, [
            'model' => 'gpt-4o-mini',
            'max_tokens' => 1000,
            'temperature' => 0.2
        ]);
    }

    /**
     * Analyze content to detect entities (stakeholders, workstreams, releases, action items)
     */
    public function analyzeContentEntities(string $content): array
    {
        $prompt = $this->buildEntityDetectionPrompt($content);

        $response = $this->executeWithProvider('openai', 'complete', $prompt, [
            'model' => 'gpt-4o-mini',
            'max_tokens' => 2000,
            'temperature' => 0.3
        ]);

        // Parse the AI response into structured data
        return $this->parseEntityDetectionResponse($response->getContent());
    }

    /**
     * Analyze content for insights (complex reasoning)
     */
    public function analyzeContent(string $content, string $context = ''): AiResponse
    {
        $prompt = $this->buildAnalysisPrompt($content, $context);

        // Use Claude for complex analysis
        return $this->executeWithProvider('anthropic', 'complete', $prompt, [
            'model' => 'claude-3-5-sonnet-20241022',
            'max_tokens' => 2000,
            'temperature' => 0.4
        ]);
    }

    /**
     * Generate release notes from content
     */
    public function generateReleaseNotes(array $content, string $audience = 'technical'): AiResponse
    {
        $prompt = $this->buildReleaseNotesPrompt($content, $audience);

        // Use Claude for nuanced writing
        return $this->executeWithProvider('anthropic', 'complete', $prompt, [
            'model' => 'claude-3-5-sonnet-20241022',
            'max_tokens' => 1500,
            'temperature' => 0.6
        ]);
    }

    /**
     * Generate morning brief from overnight data
     */
    public function generateMorningBrief(array $data): AiResponse
    {
        $prompt = $this->buildMorningBriefPrompt($data);

        return $this->executeWithProvider('anthropic', 'complete', $prompt, [
            'model' => 'claude-3-5-sonnet-20241022',
            'max_tokens' => 1000,
            'temperature' => 0.5
        ]);
    }

    /**
     * Execute AI request with specific provider
     */
    private function executeWithProvider(string $providerName, string $method, string $prompt, array $options = []): AiResponse
    {
        // Check rate limits
        if (!$this->checkRateLimit()) {
            throw new AiServiceException('Rate limit exceeded');
        }

        // Check cost limits
        if (!$this->checkCostLimit($providerName, $options)) {
            throw new AiServiceException('Cost limit exceeded');
        }

        $originalProvider = $this->provider;

        try {
            // Switch to requested provider if different
            if ($providerName !== $this->defaultProvider) {
                $this->setProvider($providerName);
            }

            // Create AI job record
            $aiJob = $this->createAiJob($providerName, $method, $prompt, $options);

            // Execute the request
            $response = $this->provider->$method($prompt, $options);

            // Update job with results
            $this->updateAiJob($aiJob, $response);

            // Log for monitoring
            Log::info('AI request completed', [
                'provider' => $providerName,
                'method' => $method,
                'tokens_used' => $response->getTokensUsed(),
                'cost' => $response->getCost()
            ]);

            return $response;

        } catch (\Exception $e) {
            // Update job with error
            if (isset($aiJob)) {
                $this->updateAiJob($aiJob, null, $e->getMessage());
            }

            Log::error('AI request failed', [
                'provider' => $providerName,
                'error' => $e->getMessage(),
                'prompt_length' => strlen($prompt)
            ]);

            throw new AiServiceException("AI request failed: " . $e->getMessage(), 0, $e);
        } finally {
            // Restore original provider
            $this->provider = $originalProvider;
        }
    }

    /**
     * Select optimal provider based on task characteristics
     */
    private function selectOptimalProvider(string $prompt, array $options): string
    {
        // If provider is explicitly specified, use it
        if (isset($options['provider'])) {
            return $options['provider'];
        }

        $promptLength = strlen($prompt);
        $complexity = $options['complexity'] ?? 'medium';

        // Use OpenAI for simple, quick tasks
        if ($complexity === 'low' || $promptLength < 1000) {
            return 'openai';
        }

        // Use Anthropic for complex reasoning
        if ($complexity === 'high' || $promptLength > 5000) {
            return 'anthropic';
        }

        return $this->defaultProvider;
    }

    /**
     * Check rate limiting
     */
    private function checkRateLimit(): bool
    {
        $key = 'ai_rate_limit:' . now()->format('Y-m-d-H-i');
        $limit = config('ai.rate_limit_per_minute', 60);

        $current = Cache::get($key, 0);

        if ($current >= $limit) {
            return false;
        }

        Cache::put($key, $current + 1, 120); // 2 minute expiry
        return true;
    }

    /**
     * Check cost limits
     */
    private function checkCostLimit(string $provider, array $options): bool
    {
        $dailyLimit = config('ai.cost_limit_daily', 50.00);
        $monthlyLimit = config('ai.cost_limit_monthly', 1000.00);

        $today = now()->format('Y-m-d');
        $month = now()->format('Y-m');

        $dailyCost = AiJob::whereDate('created_at', $today)->sum('cost');
        $monthlyCost = AiJob::where('created_at', '>=', now()->startOfMonth())->sum('cost');

        // Estimate cost of this request
        $estimatedCost = $this->estimateRequestCost($provider, $options);

        return ($dailyCost + $estimatedCost <= $dailyLimit) &&
               ($monthlyCost + $estimatedCost <= $monthlyLimit);
    }

    /**
     * Estimate cost of AI request
     */
    private function estimateRequestCost(string $provider, array $options): float
    {
        $maxTokens = $options['max_tokens'] ?? 1000;

        // Rough cost estimates (per 1k tokens)
        $costs = [
            'openai' => ['input' => 0.00015, 'output' => 0.0006],
            'anthropic' => ['input' => 0.003, 'output' => 0.015]
        ];

        $providerCosts = $costs[$provider] ?? $costs['openai'];

        // Estimate: assume 50% input, 50% output tokens
        return (($maxTokens * 0.5 * $providerCosts['input']) +
                ($maxTokens * 0.5 * $providerCosts['output'])) / 1000;
    }

    /**
     * Create AI job record
     */
    private function createAiJob(string $provider, string $method, string $prompt, array $options): AiJob
    {
        return AiJob::create([
            'provider' => $provider,
            'method' => $method,
            'prompt_hash' => hash('sha256', $prompt),
            'prompt_length' => strlen($prompt),
            'options' => $options,
            'status' => 'processing',
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * Update AI job with results
     */
    private function updateAiJob(AiJob $aiJob, ?AiResponse $response, ?string $error = null): void
    {
        $updates = [
            'completed_at' => now(),
            'status' => $error ? 'failed' : 'completed'
        ];

        if ($response) {
            $updates = array_merge($updates, [
                'tokens_used' => $response->getTokensUsed(),
                'cost' => $response->getCost(),
                'response_length' => strlen($response->getContent())
            ]);
        }

        if ($error) {
            $updates['error_message'] = $error;
        }

        $aiJob->update($updates);
    }

    /**
     * Build prompt for summarization
     */
    private function buildSummarizePrompt(string $content, int $maxLength): string
    {
        return "Please provide a concise summary of the following content in approximately {$maxLength} characters. Focus on the key points and actionable information:\n\n{$content}";
    }

    /**
     * Build prompt for action item extraction
     */
    private function buildActionItemsPrompt(string $content): string
    {
        return "Extract actionable tasks and follow-up items from the following content. Format as a JSON array with each item having 'task', 'priority' (high/medium/low), 'assignee' (if mentioned), and 'deadline' (if mentioned):\n\n{$content}";
    }

    /**
     * Build prompt for content analysis
     */
    private function buildAnalysisPrompt(string $content, string $context): string
    {
        $contextSection = $context ? "Context: {$context}\n\n" : '';

        return "{$contextSection}Analyze the following content and provide insights including:\n1. Key themes and patterns\n2. Potential risks or concerns\n3. Opportunities identified\n4. Recommended next steps\n\nContent:\n{$content}";
    }

    /**
     * Build prompt for release notes generation
     */
    private function buildReleaseNotesPrompt(array $content, string $audience): string
    {
        $contentText = implode("\n\n", $content);

        $audienceGuidance = match($audience) {
            'technical' => 'Focus on technical details, API changes, and implementation specifics.',
            'business' => 'Focus on user benefits, business impact, and feature descriptions.',
            'executive' => 'Focus on strategic value, metrics, and high-level outcomes.',
            default => 'Balance technical and business perspectives.'
        };

        return "Generate professional release notes for a {$audience} audience. {$audienceGuidance}\n\nFormat as markdown with clear sections for New Features, Improvements, Bug Fixes, and Breaking Changes if applicable.\n\nSource content:\n{$contentText}";
    }

    /**
     * Build prompt for morning brief generation
     */
    private function buildMorningBriefPrompt(array $data): string
    {
        $sections = [];

        if (isset($data['emails'])) {
            $sections[] = "Recent emails:\n" . implode("\n", $data['emails']);
        }

        if (isset($data['tasks'])) {
            $sections[] = "Today's tasks:\n" . implode("\n", $data['tasks']);
        }

        if (isset($data['meetings'])) {
            $sections[] = "Scheduled meetings:\n" . implode("\n", $data['meetings']);
        }

        if (isset($data['releases'])) {
            $sections[] = "Active releases:\n" . implode("\n", $data['releases']);
        }

        $content = implode("\n\n", $sections);

        return "Generate a concise morning brief for a product manager based on the following information. Include:\n1. Priority items requiring immediate attention\n2. Key meetings and preparation needed\n3. Release status updates\n4. Potential blockers or risks\n\nKeep it actionable and under 300 words:\n\n{$content}";
    }

    /**
     * Build prompt for entity detection
     */
    private function buildEntityDetectionPrompt(string $content): string
    {
        return "Analyze the following content and extract structured entities. Return a JSON object with the following structure:

{
  \"stakeholders\": [
    {
      \"name\": \"Person or team name\",
      \"confidence\": 0.95,
      \"context\": \"How they are mentioned in the content\"
    }
  ],
  \"workstreams\": [
    {
      \"name\": \"Project or workstream name\",
      \"confidence\": 0.90,
      \"context\": \"How the workstream is referenced\"
    }
  ],
  \"releases\": [
    {
      \"version\": \"Version number or release name\",
      \"confidence\": 0.85,
      \"context\": \"How the release is mentioned\"
    }
  ],
  \"action_items\": [
    {
      \"text\": \"What needs to be done\",
      \"assignee\": \"Person responsible (if mentioned)\",
      \"priority\": \"high|medium|low\",
      \"due_date\": \"YYYY-MM-DD (if mentioned)\",
      \"confidence\": 0.90,
      \"context\": \"Context around the action item\"
    }
  ],
  \"summary\": \"Brief summary of the main points\"
}

Content to analyze:
{$content}";
    }

    /**
     * Parse entity detection response
     */
    private function parseEntityDetectionResponse(string $response): array
    {
        try {
            // Clean the response - remove markdown code blocks if present
            $cleanResponse = $this->cleanJsonResponse($response);


            $decoded = json_decode($cleanResponse, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('Failed to decode AI entity detection response', [
                    'error' => json_last_error_msg(),
                    'response_preview' => substr($response, 0, 200),
                    'cleaned_preview' => substr($cleanResponse, 0, 200)
                ]);
                return $this->getDefaultEntityStructure();
            }

            // Ensure all required keys exist
            return [
                'stakeholders' => $decoded['stakeholders'] ?? [],
                'workstreams' => $decoded['workstreams'] ?? [],
                'releases' => $decoded['releases'] ?? [],
                'action_items' => $decoded['action_items'] ?? [],
                'summary' => $decoded['summary'] ?? null
            ];

        } catch (\Exception $e) {
            Log::error('Error parsing AI entity detection response', [
                'error' => $e->getMessage(),
                'response_preview' => substr($response, 0, 200)
            ]);

            return $this->getDefaultEntityStructure();
        }
    }

    /**
     * Clean JSON response by removing markdown code blocks and extra formatting
     */
    private function cleanJsonResponse(string $response): string
    {
        // Remove markdown code blocks - be more aggressive
        $response = preg_replace('/^\s*```(?:json)?\s*/m', '', $response);
        $response = preg_replace('/\s*```\s*$/m', '', $response);

        // Also handle cases where there might be other text before/after
        $response = preg_replace('/.*?({.*}).*/s', '$1', $response);

        // Remove any leading/trailing whitespace
        $response = trim($response);

        return $response;
    }

    /**
     * Get default entity structure when parsing fails
     */
    private function getDefaultEntityStructure(): array
    {
        return [
            'stakeholders' => [],
            'workstreams' => [],
            'releases' => [],
            'action_items' => [],
            'summary' => null
        ];
    }

    /**
     * Get AI usage statistics
     */
    public function getUsageStats(string $period = 'today'): array
    {
        $query = AiJob::query();

        switch ($period) {
            case 'today':
                $query->whereDate('created_at', today());
                break;
            case 'week':
                $query->where('created_at', '>=', now()->startOfWeek());
                break;
            case 'month':
                $query->where('created_at', '>=', now()->startOfMonth());
                break;
        }

        return [
            'total_requests' => $query->count(),
            'total_cost' => $query->sum('cost'),
            'total_tokens' => $query->sum('tokens_used'),
            'success_rate' => $query->where('status', 'completed')->count() / max($query->count(), 1) * 100,
            'avg_response_time' => $query->whereNotNull('completed_at')
                ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, completed_at)) as avg_time')
                ->value('avg_time'),
            'by_provider' => $query->groupBy('provider')
                ->selectRaw('provider, COUNT(*) as count, SUM(cost) as cost')
                ->pluck('cost', 'provider')
                ->toArray()
        ];
    }
}

/**
 * AI Response wrapper class
 */
class AiResponse
{
    public function __construct(
        private string $content,
        private int $tokensUsed,
        private float $cost,
        private array $metadata = []
    ) {}

    public function getContent(): string
    {
        return $this->content;
    }

    public function getTokensUsed(): int
    {
        return $this->tokensUsed;
    }

    public function getCost(): float
    {
        return $this->cost;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'tokens_used' => $this->tokensUsed,
            'cost' => $this->cost,
            'metadata' => $this->metadata
        ];
    }
}
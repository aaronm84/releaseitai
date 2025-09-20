<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\AiProviderInterface;
use App\Services\AiResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAiProvider implements AiProviderInterface
{
    private string $apiKey;
    private string $baseUrl = 'https://api.openai.com/v1';

    public function __construct()
    {
        $this->apiKey = config('ai.openai_api_key');

        if (!$this->apiKey) {
            throw new \InvalidArgumentException('OpenAI API key not configured');
        }
    }

    public function complete(string $prompt, array $options = []): AiResponse
    {
        $model = $options['model'] ?? $this->getDefaultModel();
        $maxTokens = $options['max_tokens'] ?? 1000;
        $temperature = $options['temperature'] ?? 0.7;

        $payload = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(60)->post($this->baseUrl . '/chat/completions', $payload);

        if (!$response->successful()) {
            $error = $response->json('error.message') ?? 'OpenAI API request failed';
            Log::error('OpenAI API error', [
                'status' => $response->status(),
                'error' => $error,
                'response' => $response->body()
            ]);
            throw new \Exception("OpenAI API error: {$error}");
        }

        $data = $response->json();

        $content = $data['choices'][0]['message']['content'] ?? '';
        $tokensUsed = $data['usage']['total_tokens'] ?? 0;
        $cost = $this->calculateCost($model, $data['usage'] ?? []);

        return new AiResponse(
            content: $content,
            tokensUsed: $tokensUsed,
            cost: $cost,
            metadata: [
                'model' => $model,
                'provider' => 'openai',
                'finish_reason' => $data['choices'][0]['finish_reason'] ?? null,
                'usage' => $data['usage'] ?? []
            ]
        );
    }

    public function getName(): string
    {
        return 'openai';
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }

    public function getSupportedModels(): array
    {
        return [
            'gpt-4o-mini',
            'gpt-4o',
            'gpt-4-turbo',
            'gpt-3.5-turbo'
        ];
    }

    public function getDefaultModel(): string
    {
        return 'gpt-4o-mini';
    }

    /**
     * Calculate cost based on token usage
     */
    private function calculateCost(string $model, array $usage): float
    {
        $inputTokens = $usage['prompt_tokens'] ?? 0;
        $outputTokens = $usage['completion_tokens'] ?? 0;

        // Pricing per 1K tokens (as of late 2024)
        $pricing = [
            'gpt-4o-mini' => ['input' => 0.00015, 'output' => 0.0006],
            'gpt-4o' => ['input' => 0.0025, 'output' => 0.01],
            'gpt-4-turbo' => ['input' => 0.01, 'output' => 0.03],
            'gpt-3.5-turbo' => ['input' => 0.0005, 'output' => 0.0015],
        ];

        $modelPricing = $pricing[$model] ?? $pricing['gpt-4o-mini'];

        return (($inputTokens / 1000) * $modelPricing['input']) +
               (($outputTokens / 1000) * $modelPricing['output']);
    }
}
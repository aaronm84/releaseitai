<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\AiProviderInterface;
use App\Services\AiResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnthropicProvider implements AiProviderInterface
{
    private string $apiKey;
    private string $baseUrl = 'https://api.anthropic.com/v1';

    public function __construct()
    {
        $this->apiKey = config('ai.anthropic_api_key');

        if (!$this->apiKey) {
            throw new \InvalidArgumentException('Anthropic API key not configured');
        }
    }

    public function complete(string $prompt, array $options = []): AiResponse
    {
        $model = $options['model'] ?? $this->getDefaultModel();
        $maxTokens = $options['max_tokens'] ?? 1000;
        $temperature = $options['temperature'] ?? 0.7;

        $payload = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ]
        ];

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'content-type' => 'application/json',
            'anthropic-version' => '2023-06-01'
        ])->timeout(60)->post($this->baseUrl . '/messages', $payload);

        if (!$response->successful()) {
            $error = $response->json('error.message') ?? 'Anthropic API request failed';
            Log::error('Anthropic API error', [
                'status' => $response->status(),
                'error' => $error,
                'response' => $response->body()
            ]);
            throw new \Exception("Anthropic API error: {$error}");
        }

        $data = $response->json();

        $content = '';
        if (isset($data['content']) && is_array($data['content'])) {
            foreach ($data['content'] as $block) {
                if ($block['type'] === 'text') {
                    $content .= $block['text'];
                }
            }
        }

        $inputTokens = $data['usage']['input_tokens'] ?? 0;
        $outputTokens = $data['usage']['output_tokens'] ?? 0;
        $tokensUsed = $inputTokens + $outputTokens;
        $cost = $this->calculateCost($model, $inputTokens, $outputTokens);

        return new AiResponse(
            content: $content,
            tokensUsed: $tokensUsed,
            cost: $cost,
            metadata: [
                'model' => $model,
                'provider' => 'anthropic',
                'stop_reason' => $data['stop_reason'] ?? null,
                'usage' => $data['usage'] ?? []
            ]
        );
    }

    public function getName(): string
    {
        return 'anthropic';
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }

    public function getSupportedModels(): array
    {
        return [
            'claude-3-5-sonnet-20241022',
            'claude-3-5-haiku-20241022',
            'claude-3-opus-20240229',
            'claude-3-sonnet-20240229',
            'claude-3-haiku-20240307'
        ];
    }

    public function getDefaultModel(): string
    {
        return 'claude-3-5-sonnet-20241022';
    }

    /**
     * Calculate cost based on token usage
     */
    private function calculateCost(string $model, int $inputTokens, int $outputTokens): float
    {
        // Pricing per 1K tokens (as of late 2024)
        $pricing = [
            'claude-3-5-sonnet-20241022' => ['input' => 0.003, 'output' => 0.015],
            'claude-3-5-haiku-20241022' => ['input' => 0.0008, 'output' => 0.004],
            'claude-3-opus-20240229' => ['input' => 0.015, 'output' => 0.075],
            'claude-3-sonnet-20240229' => ['input' => 0.003, 'output' => 0.015],
            'claude-3-haiku-20240307' => ['input' => 0.00025, 'output' => 0.00125],
        ];

        $modelPricing = $pricing[$model] ?? $pricing['claude-3-5-sonnet-20241022'];

        return (($inputTokens / 1000) * $modelPricing['input']) +
               (($outputTokens / 1000) * $modelPricing['output']);
    }
}
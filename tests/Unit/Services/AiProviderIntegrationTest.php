<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\AiService;
use App\Services\AiResponse;
use App\Services\Ai\OpenAiProvider;
use App\Services\Ai\AnthropicProvider;
use App\Exceptions\AiServiceException;
use App\Models\AiJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Mockery;

class AiProviderIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected AiService $aiService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->aiService = new AiService();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_handles_openai_rate_limiting_gracefully()
    {
        // Given: OpenAI API returns rate limit response
        Http::fake([
            'api.openai.com/*' => Http::response([
                'error' => [
                    'message' => 'Rate limit reached for requests',
                    'type' => 'requests',
                    'param' => null,
                    'code' => 'rate_limit_exceeded'
                ]
            ], 429, [
                'retry-after' => '60'
            ])
        ]);

        // When: Making an AI request
        $this->expectException(AiServiceException::class);
        $this->expectExceptionMessage('Rate limit exceeded');

        $this->aiService->setProvider('openai')
                       ->complete('Test prompt');
    }

    /** @test */
    public function it_handles_openai_timeout_errors()
    {
        // Given: OpenAI API times out
        Http::fake([
            'api.openai.com/*' => function () {
                throw new \Exception('cURL error 28: Operation timed out');
            }
        ]);

        // When: Making an AI request
        $this->expectException(AiServiceException::class);
        $this->expectExceptionMessage('Request timeout');

        $this->aiService->setProvider('openai')
                       ->complete('Test prompt');
    }

    /** @test */
    public function it_handles_openai_authentication_errors()
    {
        // Given: Invalid API key
        Http::fake([
            'api.openai.com/*' => Http::response([
                'error' => [
                    'message' => 'Incorrect API key provided',
                    'type' => 'invalid_request_error',
                    'param' => null,
                    'code' => 'invalid_api_key'
                ]
            ], 401)
        ]);

        // When: Making an AI request
        $this->expectException(AiServiceException::class);
        $this->expectExceptionMessage('Authentication failed');

        $this->aiService->setProvider('openai')
                       ->complete('Test prompt');
    }

    /** @test */
    public function it_handles_openai_service_unavailable_errors()
    {
        // Given: OpenAI service is down
        Http::fake([
            'api.openai.com/*' => Http::response([
                'error' => [
                    'message' => 'The server is temporarily overloaded',
                    'type' => 'server_error',
                    'param' => null,
                    'code' => 'service_unavailable'
                ]
            ], 503)
        ]);

        // When: Making an AI request
        $this->expectException(AiServiceException::class);
        $this->expectExceptionMessage('Service temporarily unavailable');

        $this->aiService->setProvider('openai')
                       ->complete('Test prompt');
    }

    /** @test */
    public function it_handles_anthropic_rate_limiting()
    {
        // Given: Anthropic API returns rate limit response
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'type' => 'error',
                'error' => [
                    'type' => 'rate_limit_error',
                    'message' => 'Number of requests per minute exceeded'
                ]
            ], 429, [
                'retry-after' => '30'
            ])
        ]);

        // When: Making an AI request
        $this->expectException(AiServiceException::class);
        $this->expectExceptionMessage('Rate limit exceeded');

        $this->aiService->setProvider('anthropic')
                       ->complete('Test prompt');
    }

    /** @test */
    public function it_implements_exponential_backoff_for_retries()
    {
        // Given: Service that fails initially then succeeds
        $attempts = 0;
        Http::fake([
            'api.openai.com/*' => function () use (&$attempts) {
                $attempts++;
                if ($attempts < 3) {
                    return Http::response(['error' => ['message' => 'Temporary error']], 500);
                }
                return Http::response([
                    'id' => 'chatcmpl-123',
                    'object' => 'chat.completion',
                    'choices' => [
                        [
                            'message' => ['content' => 'Success response'],
                            'finish_reason' => 'stop'
                        ]
                    ],
                    'usage' => ['total_tokens' => 50]
                ]);
            }
        ]);

        // When: Making request with retry logic
        $provider = new OpenAiProvider();
        $provider->setRetryPolicy([
            'max_attempts' => 3,
            'backoff_multiplier' => 2,
            'initial_delay' => 1
        ]);

        // Then: Should eventually succeed
        $response = $provider->complete('Test prompt');
        $this->assertInstanceOf(AiResponse::class, $response);
        $this->assertEquals(3, $attempts);
    }

    /** @test */
    public function it_handles_provider_failover()
    {
        // Given: Primary provider fails, secondary succeeds
        Http::fake([
            'api.openai.com/*' => Http::response(['error' => ['message' => 'Service down']], 503),
            'api.anthropic.com/*' => Http::response([
                'id' => 'msg_123',
                'type' => 'message',
                'content' => [['text' => 'Failover response']],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 15]
            ])
        ]);

        // When: Making request with failover enabled
        Config::set('ai.enable_failover', true);
        Config::set('ai.failover_providers', ['anthropic']);

        $response = $this->aiService->complete('Test prompt');

        // Then: Should get response from failover provider
        $this->assertInstanceOf(AiResponse::class, $response);
        $this->assertStringContains('Failover response', $response->getContent());
    }

    /** @test */
    public function it_tracks_provider_health_metrics()
    {
        // Given: Multiple requests to track health
        Http::fake([
            'api.openai.com/*' => function () {
                static $counter = 0;
                $counter++;

                if ($counter <= 2) {
                    return Http::response(['choices' => [['message' => ['content' => 'Success']]]], 200);
                } else {
                    return Http::response(['error' => ['message' => 'Error']], 500);
                }
            }
        ]);

        // When: Making multiple requests
        for ($i = 0; $i < 4; $i++) {
            try {
                $this->aiService->setProvider('openai')->complete('Test ' . $i);
            } catch (AiServiceException $e) {
                // Expected for later requests
            }
        }

        // Then: Health metrics should be tracked
        $healthStats = Cache::get('ai_provider_health:openai', []);
        $this->assertArrayHasKey('total_requests', $healthStats);
        $this->assertArrayHasKey('failed_requests', $healthStats);
        $this->assertArrayHasKey('success_rate', $healthStats);
    }

    /** @test */
    public function it_handles_quota_exceeded_errors()
    {
        // Given: Provider quota is exceeded
        Http::fake([
            'api.openai.com/*' => Http::response([
                'error' => [
                    'message' => 'You exceeded your current quota',
                    'type' => 'insufficient_quota',
                    'param' => null,
                    'code' => 'insufficient_quota'
                ]
            ], 429)
        ]);

        // When: Making an AI request
        $this->expectException(AiServiceException::class);
        $this->expectExceptionMessage('Quota exceeded');

        $this->aiService->setProvider('openai')
                       ->complete('Test prompt');
    }

    /** @test */
    public function it_validates_api_responses()
    {
        // Given: Invalid API response format
        Http::fake([
            'api.openai.com/*' => Http::response([
                'invalid' => 'response format'
            ])
        ]);

        // When: Making an AI request
        $this->expectException(AiServiceException::class);
        $this->expectExceptionMessage('Invalid response format');

        $this->aiService->setProvider('openai')
                       ->complete('Test prompt');
    }

    /** @test */
    public function it_handles_network_connectivity_issues()
    {
        // Given: Network connectivity failure
        Http::fake([
            'api.openai.com/*' => function () {
                throw new \Exception('Could not resolve host');
            }
        ]);

        // When: Making an AI request
        $this->expectException(AiServiceException::class);
        $this->expectExceptionMessage('Network connectivity error');

        $this->aiService->setProvider('openai')
                       ->complete('Test prompt');
    }

    /** @test */
    public function it_implements_request_timeout_handling()
    {
        // Given: Configured request timeout
        Config::set('ai.request_timeout', 30);

        Http::fake([
            'api.openai.com/*' => function () {
                // Simulate slow response
                sleep(2);
                return Http::response(['choices' => [['message' => ['content' => 'Slow response']]]]);
            }
        ]);

        // When: Making request with timeout
        $provider = new OpenAiProvider();
        $provider->setTimeout(1); // 1 second timeout

        // Then: Should timeout
        $this->expectException(AiServiceException::class);
        $this->expectExceptionMessage('Request timeout');

        $provider->complete('Test prompt');
    }

    /** @test */
    public function it_handles_malformed_json_responses()
    {
        // Given: API returns malformed JSON
        Http::fake([
            'api.openai.com/*' => Http::response('Invalid JSON response}', 200, [
                'Content-Type' => 'application/json'
            ])
        ]);

        // When: Making an AI request
        $this->expectException(AiServiceException::class);
        $this->expectExceptionMessage('Invalid JSON response');

        $this->aiService->setProvider('openai')
                       ->complete('Test prompt');
    }

    /** @test */
    public function it_logs_provider_errors_appropriately()
    {
        // Given: Provider error scenario
        Http::fake([
            'api.openai.com/*' => Http::response([
                'error' => ['message' => 'Test error']
            ], 500)
        ]);

        Log::shouldReceive('error')
            ->once()
            ->with('AI provider error', Mockery::type('array'));

        Log::shouldReceive('warning')
            ->once()
            ->with('AI request failed, attempting retry', Mockery::type('array'));

        // When: Making request that fails
        try {
            $this->aiService->setProvider('openai')
                           ->complete('Test prompt');
        } catch (AiServiceException $e) {
            // Expected
        }
    }

    /** @test */
    public function it_handles_content_policy_violations()
    {
        // Given: Content violates provider policy
        Http::fake([
            'api.openai.com/*' => Http::response([
                'error' => [
                    'message' => 'Your request was rejected as a result of our safety system',
                    'type' => 'invalid_request_error',
                    'param' => null,
                    'code' => 'content_filter'
                ]
            ], 400)
        ]);

        // When: Making request with problematic content
        $this->expectException(AiServiceException::class);
        $this->expectExceptionMessage('Content policy violation');

        $this->aiService->setProvider('openai')
                       ->complete('Problematic content');
    }

    /** @test */
    public function it_handles_model_not_found_errors()
    {
        // Given: Requested model doesn't exist
        Http::fake([
            'api.openai.com/*' => Http::response([
                'error' => [
                    'message' => 'The model `nonexistent-model` does not exist',
                    'type' => 'invalid_request_error',
                    'param' => 'model',
                    'code' => 'model_not_found'
                ]
            ], 404)
        ]);

        // When: Making request with invalid model
        $this->expectException(AiServiceException::class);
        $this->expectExceptionMessage('Model not found');

        $this->aiService->setProvider('openai')
                       ->complete('Test prompt', ['model' => 'nonexistent-model']);
    }

    /** @test */
    public function it_handles_token_limit_exceeded()
    {
        // Given: Request exceeds token limit
        Http::fake([
            'api.openai.com/*' => Http::response([
                'error' => [
                    'message' => 'This model\'s maximum context length is 4097 tokens',
                    'type' => 'invalid_request_error',
                    'param' => 'messages',
                    'code' => 'context_length_exceeded'
                ]
            ], 400)
        ]);

        // When: Making request with too many tokens
        $this->expectException(AiServiceException::class);
        $this->expectExceptionMessage('Token limit exceeded');

        $longPrompt = str_repeat('This is a very long prompt. ', 1000);
        $this->aiService->setProvider('openai')
                       ->complete($longPrompt);
    }

    /** @test */
    public function it_implements_circuit_breaker_pattern()
    {
        // Given: Provider is consistently failing
        $failures = 0;
        Http::fake([
            'api.openai.com/*' => function () use (&$failures) {
                $failures++;
                return Http::response(['error' => ['message' => 'Consistent failure']], 500);
            }
        ]);

        // When: Making multiple failed requests
        for ($i = 0; $i < 5; $i++) {
            try {
                $this->aiService->setProvider('openai')
                               ->complete('Test ' . $i);
            } catch (AiServiceException $e) {
                // Expected
            }
        }

        // Then: Circuit breaker should open
        $circuitState = Cache::get('circuit_breaker:openai');
        $this->assertArrayHasKey('failures', $circuitState);
        $this->assertEquals(5, $failures);
    }

    /** @test */
    public function it_tracks_cost_and_usage_metrics()
    {
        // Given: Successful AI request
        Http::fake([
            'api.openai.com/*' => Http::response([
                'id' => 'chatcmpl-123',
                'object' => 'chat.completion',
                'choices' => [
                    [
                        'message' => ['content' => 'Test response'],
                        'finish_reason' => 'stop'
                    ]
                ],
                'usage' => [
                    'prompt_tokens' => 10,
                    'completion_tokens' => 15,
                    'total_tokens' => 25
                ]
            ])
        ]);

        // When: Making AI request
        $response = $this->aiService->setProvider('openai')
                                  ->complete('Test prompt');

        // Then: Cost and usage should be tracked
        $this->assertEquals(25, $response->getTokensUsed());
        $this->assertGreaterThan(0, $response->getCost());

        $aiJob = AiJob::latest()->first();
        $this->assertEquals(25, $aiJob->tokens_used);
        $this->assertGreaterThan(0, $aiJob->cost);
    }

    /** @test */
    public function it_handles_provider_specific_error_codes()
    {
        // Test OpenAI specific errors
        Http::fake([
            'api.openai.com/*' => Http::response([
                'error' => [
                    'message' => 'Your account is not active',
                    'type' => 'billing_not_active',
                    'param' => null,
                    'code' => 'billing_not_active'
                ]
            ], 400)
        ]);

        $this->expectException(AiServiceException::class);
        $this->expectExceptionMessage('Billing not active');

        $this->aiService->setProvider('openai')
                       ->complete('Test prompt');
    }

    /** @test */
    public function it_validates_provider_configuration()
    {
        // Given: Missing API key configuration
        Config::set('ai.providers.openai.api_key', null);

        // When: Attempting to use provider
        $this->expectException(AiServiceException::class);
        $this->expectExceptionMessage('Provider configuration invalid');

        $this->aiService->setProvider('openai')
                       ->complete('Test prompt');
    }

    /** @test */
    public function it_handles_concurrent_request_limits()
    {
        // Given: Provider has concurrent request limits
        Http::fake([
            'api.openai.com/*' => Http::response([
                'error' => [
                    'message' => 'Rate limit reached for concurrent requests',
                    'type' => 'requests',
                    'param' => null,
                    'code' => 'concurrent_limit_exceeded'
                ]
            ], 429)
        ]);

        // When: Making concurrent requests
        $this->expectException(AiServiceException::class);
        $this->expectExceptionMessage('Concurrent request limit exceeded');

        $this->aiService->setProvider('openai')
                       ->complete('Test prompt');
    }
}
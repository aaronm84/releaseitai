<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Services\AiService;
use App\Exceptions\AiServiceException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Mockery;

class BrainDumpProcessingTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;
    private string $endpoint = '/api/brain-dump/process';

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @test
     * @group brain-dump
     * @group happy-path
     */
    public function processBrainDump_WithValidContent_ReturnsStructuredData(): void
    {
        // Given: A user with valid brain dump content
        $content = "
            Team meeting notes from today:
            - Need to finish user authentication by Friday (high priority)
            - Schedule product review meeting with stakeholders next week
            - Decision made: We'll use React for the frontend
            - Bug fix for login issue should be done by Tom tomorrow
            - Planning sprint retrospective meeting for Thursday 2024-01-15
        ";

        $expectedResponse = [
            'tasks' => [
                ['title' => 'Finish user authentication', 'priority' => 'high'],
                ['title' => 'Bug fix for login issue', 'priority' => 'medium']
            ],
            'meetings' => [
                ['title' => 'Product review meeting', 'date' => '2024-01-15'],
                ['title' => 'Sprint retrospective meeting', 'date' => '2024-01-15']
            ],
            'decisions' => [
                ['title' => 'Use React for frontend', 'impact' => 'high']
            ]
        ];

        $this->mockAiServiceSuccess($content, $expectedResponse);

        // When: Processing the brain dump content
        $response = $this->actingAs($this->user)
            ->postJson($this->endpoint, ['content' => $content]);

        // Then: Returns structured data with expected format
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => $expectedResponse,
                'processing_time' => function($value) {
                    return is_numeric($value) && $value >= 0;
                }
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'tasks' => [
                        '*' => ['title', 'priority']
                    ],
                    'meetings' => [
                        '*' => ['title', 'date']
                    ],
                    'decisions' => [
                        '*' => ['title', 'impact']
                    ]
                ],
                'processing_time',
                'timestamp'
            ]);
    }

    /**
     * @test
     * @group brain-dump
     * @group validation
     */
    public function processBrainDump_WithEmptyContent_ReturnsValidationError(): void
    {
        // Given: Empty content
        $content = '';

        // When: Attempting to process empty content
        $response = $this->actingAs($this->user)
            ->postJson($this->endpoint, ['content' => $content]);

        // Then: Returns validation error
        $response->assertStatus(422)
            ->assertJson([
                'message' => 'The content field is required.',
                'errors' => [
                    'content' => ['The content field is required.']
                ]
            ]);
    }

    /**
     * @test
     * @group brain-dump
     * @group validation
     */
    public function processBrainDump_WithContentTooShort_ReturnsValidationError(): void
    {
        // Given: Content shorter than minimum length (10 characters)
        $content = 'Short';

        // When: Attempting to process too short content
        $response = $this->actingAs($this->user)
            ->postJson($this->endpoint, ['content' => $content]);

        // Then: Returns validation error
        $response->assertStatus(422)
            ->assertJson([
                'message' => 'The content field must be at least 10 characters.',
                'errors' => [
                    'content' => ['The content field must be at least 10 characters.']
                ]
            ]);
    }

    /**
     * @test
     * @group brain-dump
     * @group validation
     */
    public function processBrainDump_WithContentTooLong_ReturnsValidationError(): void
    {
        // Given: Content longer than maximum length (10000 characters)
        $content = str_repeat('a', 10001);

        // When: Attempting to process too long content
        $response = $this->actingAs($this->user)
            ->postJson($this->endpoint, ['content' => $content]);

        // Then: Returns validation error
        $response->assertStatus(422)
            ->assertJson([
                'message' => 'The content field must not be greater than 10000 characters.',
                'errors' => [
                    'content' => ['The content field must not be greater than 10000 characters.']
                ]
            ]);
    }

    /**
     * @test
     * @group brain-dump
     * @group validation
     */
    public function processBrainDump_WithNonStringContent_ReturnsValidationError(): void
    {
        // Given: Non-string content
        $content = ['invalid' => 'array'];

        // When: Attempting to process non-string content
        $response = $this->actingAs($this->user)
            ->postJson($this->endpoint, ['content' => $content]);

        // Then: Returns validation error
        $response->assertStatus(422)
            ->assertJson([
                'message' => 'The content field must be a string.',
                'errors' => [
                    'content' => ['The content field must be a string.']
                ]
            ]);
    }

    /**
     * @test
     * @group brain-dump
     * @group authentication
     */
    public function processBrainDump_WithoutAuthentication_ReturnsUnauthorized(): void
    {
        // Given: Valid content but no authentication
        $content = 'This is a valid brain dump content for testing';

        // When: Attempting to process without authentication
        $response = $this->postJson($this->endpoint, ['content' => $content]);

        // Then: Returns unauthorized error
        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.'
            ]);
    }

    /**
     * @test
     * @group brain-dump
     * @group integration
     */
    public function processBrainDump_CallsAiServiceWithCorrectParameters(): void
    {
        // Given: Valid content and mocked AI service
        $content = 'Meeting notes with tasks and decisions';
        $expectedResponse = [
            'tasks' => [],
            'meetings' => [],
            'decisions' => []
        ];

        $aiServiceMock = $this->mockAiService();
        $aiServiceMock->shouldReceive('extractActionItems')
            ->once()
            ->with($content)
            ->andReturn($this->createMockAiResponse(json_encode([
                'action_items' => []
            ])));

        $aiServiceMock->shouldReceive('analyzeContentEntities')
            ->once()
            ->with($content)
            ->andReturn($expectedResponse);

        // When: Processing the content
        $response = $this->actingAs($this->user)
            ->postJson($this->endpoint, ['content' => $content]);

        // Then: AI service is called with correct parameters
        $response->assertStatus(200);
    }

    /**
     * @test
     * @group brain-dump
     * @group error-handling
     */
    public function processBrainDump_WhenAiServiceFails_ReturnsErrorResponse(): void
    {
        // Given: Valid content but AI service throws exception
        $content = 'This content will cause AI service to fail';

        $aiServiceMock = $this->mockAiService();
        $aiServiceMock->shouldReceive('extractActionItems')
            ->once()
            ->andThrow(new AiServiceException('AI service temporarily unavailable'));

        // When: Processing the content
        $response = $this->actingAs($this->user)
            ->postJson($this->endpoint, ['content' => $content]);

        // Then: Returns error response
        $response->assertStatus(503)
            ->assertJson([
                'success' => false,
                'message' => 'AI processing temporarily unavailable. Please try again later.',
                'error' => 'AI service temporarily unavailable'
            ]);
    }

    /**
     * @test
     * @group brain-dump
     * @group error-handling
     */
    public function processBrainDump_WhenAiServiceReturnsInvalidJson_ReturnsErrorResponse(): void
    {
        // Given: AI service returns invalid JSON
        $content = 'Valid content but AI returns malformed response';

        $aiServiceMock = $this->mockAiService();
        $aiServiceMock->shouldReceive('extractActionItems')
            ->once()
            ->andReturn($this->createMockAiResponse('invalid json'));

        // When: Processing the content
        $response = $this->actingAs($this->user)
            ->postJson($this->endpoint, ['content' => $content]);

        // Then: Returns error response
        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Unable to parse AI response. Please try again.',
                'error' => 'Invalid JSON response from AI service'
            ]);
    }

    /**
     * @test
     * @group brain-dump
     * @group rate-limiting
     */
    public function processBrainDump_WhenRateLimitExceeded_ReturnsRateLimitError(): void
    {
        // Given: Rate limit exceeded
        $content = 'Valid content for rate limit test';

        $aiServiceMock = $this->mockAiService();
        $aiServiceMock->shouldReceive('extractActionItems')
            ->once()
            ->andThrow(new AiServiceException('Rate limit exceeded'));

        // When: Processing the content
        $response = $this->actingAs($this->user)
            ->postJson($this->endpoint, ['content' => $content]);

        // Then: Returns rate limit error
        $response->assertStatus(429)
            ->assertJson([
                'success' => false,
                'message' => 'Rate limit exceeded. Please wait before making another request.',
                'retry_after' => function($value) {
                    return is_numeric($value) && $value > 0;
                }
            ]);
    }

    /**
     * @test
     * @group brain-dump
     * @group edge-cases
     */
    public function processBrainDump_WithOnlyWhitespace_ReturnsValidationError(): void
    {
        // Given: Content with only whitespace
        $content = "   \n\t   \r\n   ";

        // When: Attempting to process whitespace-only content
        $response = $this->actingAs($this->user)
            ->postJson($this->endpoint, ['content' => $content]);

        // Then: Returns validation error
        $response->assertStatus(422)
            ->assertJson([
                'message' => 'The content field must contain meaningful text.',
                'errors' => [
                    'content' => ['The content field must contain meaningful text.']
                ]
            ]);
    }

    /**
     * @test
     * @group brain-dump
     * @group edge-cases
     */
    public function processBrainDump_WithSpecialCharacters_ProcessesSuccessfully(): void
    {
        // Given: Content with special characters and unicode
        $content = 'Meeting notes: ñoño café ☕ emoji test 🚀 and symbols @#$%^&*()';
        $expectedResponse = [
            'tasks' => [],
            'meetings' => [],
            'decisions' => []
        ];

        $this->mockAiServiceSuccess($content, $expectedResponse);

        // When: Processing content with special characters
        $response = $this->actingAs($this->user)
            ->postJson($this->endpoint, ['content' => $content]);

        // Then: Processes successfully
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => $expectedResponse
            ]);
    }

    /**
     * @test
     * @group brain-dump
     * @group response-format
     */
    public function processBrainDump_WithMixedContent_ReturnsAllCategories(): void
    {
        // Given: Content with all types of extractable items
        $content = "
            Product roadmap discussion:

            Tasks identified:
            - Implement user dashboard by March 15th (high priority)
            - Review API documentation (low priority)

            Meetings scheduled:
            - All-hands meeting on 2024-02-20
            - Design review session next Friday

            Key decisions made:
            - Moving to microservices architecture (high impact)
            - Adopting TypeScript for new projects (medium impact)
        ";

        $expectedResponse = [
            'tasks' => [
                ['title' => 'Implement user dashboard', 'priority' => 'high'],
                ['title' => 'Review API documentation', 'priority' => 'low']
            ],
            'meetings' => [
                ['title' => 'All-hands meeting', 'date' => '2024-02-20'],
                ['title' => 'Design review session', 'date' => null]
            ],
            'decisions' => [
                ['title' => 'Moving to microservices architecture', 'impact' => 'high'],
                ['title' => 'Adopting TypeScript for new projects', 'impact' => 'medium']
            ]
        ];

        $this->mockAiServiceSuccess($content, $expectedResponse);

        // When: Processing mixed content
        $response = $this->actingAs($this->user)
            ->postJson($this->endpoint, ['content' => $content]);

        // Then: Returns all categories with data
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => $expectedResponse
            ]);

        $data = $response->json('data');
        $this->assertCount(2, $data['tasks']);
        $this->assertCount(2, $data['meetings']);
        $this->assertCount(2, $data['decisions']);
    }

    /**
     * @test
     * @group brain-dump
     * @group performance
     */
    public function processBrainDump_TracksProcessingTime(): void
    {
        // Given: Valid content with artificial delay
        $content = 'Content for performance testing';
        $expectedResponse = [
            'tasks' => [],
            'meetings' => [],
            'decisions' => []
        ];

        $aiServiceMock = $this->mockAiService();
        $aiServiceMock->shouldReceive('extractActionItems')
            ->once()
            ->andReturnUsing(function() {
                usleep(100000); // 100ms delay
                return $this->createMockAiResponse('{"action_items": []}');
            });

        $aiServiceMock->shouldReceive('analyzeContentEntities')
            ->once()
            ->andReturn($expectedResponse);

        // When: Processing the content
        $startTime = microtime(true);
        $response = $this->actingAs($this->user)
            ->postJson($this->endpoint, ['content' => $content]);
        $endTime = microtime(true);

        // Then: Response includes processing time
        $response->assertStatus(200);
        $processingTime = $response->json('processing_time');

        $this->assertIsNumeric($processingTime);
        $this->assertGreaterThan(0, $processingTime);
        $this->assertLessThan(($endTime - $startTime) * 1000, $processingTime + 50); // Allow 50ms tolerance
    }

    /**
     * @test
     * @group brain-dump
     * @group logging
     */
    public function processBrainDump_LogsProcessingEvents(): void
    {
        // Given: Valid content and log expectation
        $content = 'Content for logging test';
        $expectedResponse = [
            'tasks' => [],
            'meetings' => [],
            'decisions' => []
        ];

        $this->mockAiServiceSuccess($content, $expectedResponse);

        Log::shouldReceive('info')
            ->once()
            ->with('Brain dump processing started', Mockery::type('array'));

        Log::shouldReceive('info')
            ->once()
            ->with('Brain dump processing completed', Mockery::type('array'));

        // When: Processing the content
        $response = $this->actingAs($this->user)
            ->postJson($this->endpoint, ['content' => $content]);

        // Then: Logs processing events
        $response->assertStatus(200);
    }

    /**
     * @test
     * @group brain-dump
     * @group caching
     */
    public function processBrainDump_DoesNotCacheResponses(): void
    {
        // Given: Same content processed twice
        $content = 'Content that should not be cached';
        $expectedResponse1 = [
            'tasks' => [['title' => 'First response', 'priority' => 'high']],
            'meetings' => [],
            'decisions' => []
        ];
        $expectedResponse2 = [
            'tasks' => [['title' => 'Second response', 'priority' => 'low']],
            'meetings' => [],
            'decisions' => []
        ];

        // Mock different responses for same content
        $aiServiceMock = $this->mockAiService();
        $aiServiceMock->shouldReceive('extractActionItems')
            ->twice()
            ->andReturn(
                $this->createMockAiResponse('{"action_items": [{"text": "First response", "priority": "high"}]}'),
                $this->createMockAiResponse('{"action_items": [{"text": "Second response", "priority": "low"}]}')
            );

        $aiServiceMock->shouldReceive('analyzeContentEntities')
            ->twice()
            ->andReturn($expectedResponse1, $expectedResponse2);

        // When: Processing same content twice
        $response1 = $this->actingAs($this->user)
            ->postJson($this->endpoint, ['content' => $content]);

        $response2 = $this->actingAs($this->user)
            ->postJson($this->endpoint, ['content' => $content]);

        // Then: Both requests return different responses (not cached)
        $response1->assertStatus(200);
        $response2->assertStatus(200);

        $this->assertNotEquals(
            $response1->json('data.tasks.0.title'),
            $response2->json('data.tasks.0.title')
        );
    }

    /**
     * @test
     * @group brain-dump
     * @group response-headers
     */
    public function processBrainDump_ReturnsCorrectHeaders(): void
    {
        // Given: Valid content
        $content = 'Content for header testing';
        $expectedResponse = [
            'tasks' => [],
            'meetings' => [],
            'decisions' => []
        ];

        $this->mockAiServiceSuccess($content, $expectedResponse);

        // When: Processing the content
        $response = $this->actingAs($this->user)
            ->postJson($this->endpoint, ['content' => $content]);

        // Then: Returns correct headers
        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/json')
            ->assertHeaderMissing('Cache-Control'); // Should not be cached
    }

    // Helper Methods

    private function mockAiService()
    {
        $mock = Mockery::mock(AiService::class);
        $this->app->instance(AiService::class, $mock);
        return $mock;
    }

    private function mockAiServiceSuccess(string $content, array $expectedResponse): void
    {
        $aiServiceMock = $this->mockAiService();

        $aiServiceMock->shouldReceive('extractActionItems')
            ->once()
            ->with($content)
            ->andReturn($this->createMockAiResponse(json_encode([
                'action_items' => $expectedResponse['tasks'] ?? []
            ])));

        $aiServiceMock->shouldReceive('analyzeContentEntities')
            ->once()
            ->with($content)
            ->andReturn($expectedResponse);
    }

    private function createMockAiResponse(string $content)
    {
        $mock = Mockery::mock();
        $mock->shouldReceive('getContent')->andReturn($content);
        $mock->shouldReceive('getTokensUsed')->andReturn(100);
        $mock->shouldReceive('getCost')->andReturn(0.01);
        return $mock;
    }
}
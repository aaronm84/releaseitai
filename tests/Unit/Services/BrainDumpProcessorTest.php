<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\AiService;
use App\Services\BrainDumpProcessor;
use App\Exceptions\AiServiceException;
use App\Exceptions\BrainDumpProcessingException;
use Illuminate\Support\Facades\Log;
use Mockery;

class BrainDumpProcessorTest extends TestCase
{
    private BrainDumpProcessor $processor;
    private $aiServiceMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aiServiceMock = Mockery::mock(AiService::class);
        $this->processor = new BrainDumpProcessor($this->aiServiceMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @test
     * @group unit
     * @group brain-dump-processor
     */
    public function process_WithValidContent_ReturnsStructuredData(): void
    {
        // Given: Valid content and expected AI responses
        $content = 'Meeting notes with tasks and decisions';

        $actionItemsResponse = $this->createMockAiResponse(json_encode([
            'action_items' => [
                [
                    'text' => 'Complete user authentication',
                    'priority' => 'high',
                    'assignee' => 'John',
                    'due_date' => '2024-01-15'
                ],
                [
                    'text' => 'Review code changes',
                    'priority' => 'medium',
                    'assignee' => null,
                    'due_date' => null
                ]
            ]
        ]));

        $entitiesResponse = [
            'summary' => 'Meeting discussion about authentication and code review',
            'stakeholders' => [
                ['name' => 'John', 'confidence' => 0.9]
            ],
            'workstreams' => [],
            'releases' => [],
            'action_items' => []
        ];

        $this->aiServiceMock->shouldReceive('extractActionItems')
            ->once()
            ->with($content)
            ->andReturn($actionItemsResponse);

        $this->aiServiceMock->shouldReceive('analyzeContentEntities')
            ->once()
            ->with($content)
            ->andReturn($entitiesResponse);

        // When: Processing the content
        $result = $this->processor->process($content);

        // Then: Returns structured data
        $this->assertArrayHasKey('tasks', $result);
        $this->assertArrayHasKey('meetings', $result);
        $this->assertArrayHasKey('decisions', $result);

        $this->assertCount(2, $result['tasks']);
        $this->assertEquals('Complete user authentication', $result['tasks'][0]['title']);
        $this->assertEquals('high', $result['tasks'][0]['priority']);
    }

    /**
     * @test
     * @group unit
     * @group brain-dump-processor
     */
    public function process_WithEmptyActionItems_ReturnsEmptyTasks(): void
    {
        // Given: Content with no action items
        $content = 'General discussion about project status';

        $actionItemsResponse = $this->createMockAiResponse(json_encode([
            'action_items' => []
        ]));

        $entitiesResponse = [
            'summary' => 'General project discussion',
            'stakeholders' => [],
            'workstreams' => [],
            'releases' => [],
            'action_items' => []
        ];

        $this->aiServiceMock->shouldReceive('extractActionItems')
            ->once()
            ->andReturn($actionItemsResponse);

        $this->aiServiceMock->shouldReceive('analyzeContentEntities')
            ->once()
            ->andReturn($entitiesResponse);

        // When: Processing the content
        $result = $this->processor->process($content);

        // Then: Returns empty tasks array
        $this->assertEmpty($result['tasks']);
        $this->assertEmpty($result['meetings']);
        $this->assertEmpty($result['decisions']);
    }

    /**
     * @test
     * @group unit
     * @group brain-dump-processor
     */
    public function process_WhenAiServiceThrowsException_ThrowsBrainDumpProcessingException(): void
    {
        // Given: AI service that throws exception
        $content = 'Content that will fail';

        $this->aiServiceMock->shouldReceive('extractActionItems')
            ->once()
            ->andThrow(new AiServiceException('AI service error'));

        // When & Then: Processing throws BrainDumpProcessingException
        $this->expectException(BrainDumpProcessingException::class);
        $this->expectExceptionMessage('Failed to process brain dump: AI service error');

        $this->processor->process($content);
    }

    /**
     * @test
     * @group unit
     * @group brain-dump-processor
     */
    public function process_WhenActionItemsResponseIsInvalid_ThrowsException(): void
    {
        // Given: Invalid JSON response from AI service
        $content = 'Valid content';

        $invalidResponse = $this->createMockAiResponse('invalid json');

        $this->aiServiceMock->shouldReceive('extractActionItems')
            ->once()
            ->andReturn($invalidResponse);

        // When & Then: Processing throws exception for invalid JSON
        $this->expectException(BrainDumpProcessingException::class);
        $this->expectExceptionMessage('Invalid response from AI service');

        $this->processor->process($content);
    }

    /**
     * @test
     * @group unit
     * @group brain-dump-processor
     */
    public function extractTasks_WithValidActionItems_ConvertsProperly(): void
    {
        // Given: Valid action items data
        $actionItems = [
            [
                'text' => 'Implement feature A',
                'priority' => 'high',
                'assignee' => 'Alice',
                'due_date' => '2024-01-20'
            ],
            [
                'text' => 'Review pull request',
                'priority' => 'low',
                'assignee' => null,
                'due_date' => null
            ],
            [
                'text' => 'Update documentation',
                'priority' => 'medium',
                'assignee' => 'Bob',
                'due_date' => '2024-01-25'
            ]
        ];

        // When: Extracting tasks
        $tasks = $this->processor->extractTasks($actionItems);

        // Then: Returns properly formatted tasks
        $this->assertCount(3, $tasks);

        $this->assertEquals('Implement feature A', $tasks[0]['title']);
        $this->assertEquals('high', $tasks[0]['priority']);
        $this->assertEquals('Alice', $tasks[0]['assignee']);
        $this->assertEquals('2024-01-20', $tasks[0]['due_date']);

        $this->assertEquals('Review pull request', $tasks[1]['title']);
        $this->assertEquals('low', $tasks[1]['priority']);
        $this->assertNull($tasks[1]['assignee']);
        $this->assertNull($tasks[1]['due_date']);
    }

    /**
     * @test
     * @group unit
     * @group brain-dump-processor
     */
    public function extractTasks_WithMissingPriority_DefaultsToMedium(): void
    {
        // Given: Action item without priority
        $actionItems = [
            [
                'text' => 'Task without priority',
                'assignee' => 'John'
            ]
        ];

        // When: Extracting tasks
        $tasks = $this->processor->extractTasks($actionItems);

        // Then: Defaults to medium priority
        $this->assertEquals('medium', $tasks[0]['priority']);
    }

    /**
     * @test
     * @group unit
     * @group brain-dump-processor
     */
    public function extractMeetings_WithMeetingKeywords_IdentifiesMeetings(): void
    {
        // Given: Content with meeting-related text
        $content = "
            Schedule standup meeting for Monday
            Product review session next Friday 2024-02-15
            All hands meeting on 2024-02-20
            Design workshop scheduled
        ";

        // When: Extracting meetings
        $meetings = $this->processor->extractMeetings($content);

        // Then: Identifies meetings with dates where possible
        $this->assertGreaterThan(0, count($meetings));

        // Check for specific meeting types
        $meetingTitles = array_column($meetings, 'title');
        $this->assertContains('standup meeting', $meetingTitles);
        $this->assertContains('Product review session', $meetingTitles);
    }

    /**
     * @test
     * @group unit
     * @group brain-dump-processor
     */
    public function extractMeetings_WithDateFormats_ParsesDatesCorrectly(): void
    {
        // Given: Content with various date formats
        $content = "
            Meeting on 2024-01-15
            Session next Friday
            Workshop on January 20th, 2024
            Call scheduled for 01/25/2024
        ";

        // When: Extracting meetings
        $meetings = $this->processor->extractMeetings($content);

        // Then: Parses recognizable dates
        $datedMeetings = array_filter($meetings, fn($m) => $m['date'] !== null);
        $this->assertGreaterThan(0, count($datedMeetings));
    }

    /**
     * @test
     * @group unit
     * @group brain-dump-processor
     */
    public function extractDecisions_WithDecisionKeywords_IdentifiesDecisions(): void
    {
        // Given: Content with decision-related text
        $content = "
            We decided to use React for the frontend
            The team agreed on microservices architecture
            Decision made: implement OAuth authentication
            Chose to postpone the mobile app release
        ";

        // When: Extracting decisions
        $decisions = $this->processor->extractDecisions($content);

        // Then: Identifies decisions with impact levels
        $this->assertGreaterThan(0, count($decisions));

        $decisionTitles = array_column($decisions, 'title');
        $this->assertContains('use React for frontend', $decisionTitles);
        $this->assertContains('microservices architecture', $decisionTitles);
    }

    /**
     * @test
     * @group unit
     * @group brain-dump-processor
     */
    public function extractDecisions_AssignsImpactLevels_BasedOnKeywords(): void
    {
        // Given: Content with high-impact decision keywords
        $content = "
            Critical decision: migrate to new database
            We decided to change the primary tech stack
            Minor update: adjusted button color
        ";

        // When: Extracting decisions
        $decisions = $this->processor->extractDecisions($content);

        // Then: Assigns appropriate impact levels
        $criticalDecision = array_filter($decisions, fn($d) =>
            str_contains($d['title'], 'database'));
        $this->assertNotEmpty($criticalDecision);

        $stackDecision = array_filter($decisions, fn($d) =>
            str_contains($d['title'], 'tech stack'));
        $this->assertNotEmpty($stackDecision);
    }

    /**
     * @test
     * @group unit
     * @group brain-dump-processor
     */
    public function validateContent_WithValidContent_ReturnsTrue(): void
    {
        // Given: Valid content
        $validContent = 'This is a meaningful brain dump with enough content to process effectively.';

        // When: Validating content
        $isValid = $this->processor->validateContent($validContent);

        // Then: Returns true
        $this->assertTrue($isValid);
    }

    /**
     * @test
     * @group unit
     * @group brain-dump-processor
     */
    public function validateContent_WithEmptyContent_ReturnsFalse(): void
    {
        // Given: Empty content
        $emptyContent = '';

        // When: Validating content
        $isValid = $this->processor->validateContent($emptyContent);

        // Then: Returns false
        $this->assertFalse($isValid);
    }

    /**
     * @test
     * @group unit
     * @group brain-dump-processor
     */
    public function validateContent_WithOnlyWhitespace_ReturnsFalse(): void
    {
        // Given: Whitespace-only content
        $whitespaceContent = "   \n\t   \r\n   ";

        // When: Validating content
        $isValid = $this->processor->validateContent($whitespaceContent);

        // Then: Returns false
        $this->assertFalse($isValid);
    }

    /**
     * @test
     * @group unit
     * @group brain-dump-processor
     */
    public function validateContent_WithTooShortContent_ReturnsFalse(): void
    {
        // Given: Content shorter than minimum
        $shortContent = 'Short';

        // When: Validating content
        $isValid = $this->processor->validateContent($shortContent);

        // Then: Returns false
        $this->assertFalse($isValid);
    }

    /**
     * @test
     * @group unit
     * @group brain-dump-processor
     */
    public function sanitizeContent_RemovesExcessiveWhitespace(): void
    {
        // Given: Content with excessive whitespace
        $messyContent = "  This   has    too   much   whitespace  \n\n\n  ";

        // When: Sanitizing content
        $clean = $this->processor->sanitizeContent($messyContent);

        // Then: Removes excessive whitespace
        $this->assertEquals('This has too much whitespace', $clean);
    }

    /**
     * @test
     * @group unit
     * @group brain-dump-processor
     */
    public function sanitizeContent_PreservesStructure(): void
    {
        // Given: Content with meaningful structure
        $structuredContent = "Meeting Notes:\n\n1. Task one\n2. Task two\n\nDecisions:\n- Choice A\n- Choice B";

        // When: Sanitizing content
        $clean = $this->processor->sanitizeContent($structuredContent);

        // Then: Preserves meaningful line breaks and structure
        $this->assertStringContainsString("Meeting Notes:\n\n", $clean);
        $this->assertStringContainsString("1. Task one\n2. Task two", $clean);
    }

    /**
     * @test
     * @group unit
     * @group brain-dump-processor
     */
    public function getProcessingMetrics_ReturnsExpectedMetrics(): void
    {
        // Given: Content and processing time
        $content = 'Sample content for metrics';
        $processingTime = 150; // milliseconds

        // When: Getting processing metrics
        $metrics = $this->processor->getProcessingMetrics($content, $processingTime);

        // Then: Returns expected metrics
        $this->assertArrayHasKey('content_length', $metrics);
        $this->assertArrayHasKey('processing_time_ms', $metrics);
        $this->assertArrayHasKey('words_per_second', $metrics);
        $this->assertArrayHasKey('timestamp', $metrics);

        $this->assertEquals(strlen($content), $metrics['content_length']);
        $this->assertEquals($processingTime, $metrics['processing_time_ms']);
        $this->assertIsFloat($metrics['words_per_second']);
    }

    /**
     * @test
     * @group unit
     * @group brain-dump-processor
     */
    public function prioritizeTasksByUrgency_SortsCorrectly(): void
    {
        // Given: Tasks with different urgency indicators
        $tasks = [
            ['title' => 'Regular task', 'priority' => 'medium'],
            ['title' => 'URGENT: Fix critical bug', 'priority' => 'high'],
            ['title' => 'Optional enhancement', 'priority' => 'low'],
            ['title' => 'ASAP: Deploy hotfix', 'priority' => 'high']
        ];

        // When: Prioritizing tasks
        $prioritized = $this->processor->prioritizeTasksByUrgency($tasks);

        // Then: High priority tasks come first, with urgent keywords at top
        $this->assertEquals('ASAP: Deploy hotfix', $prioritized[0]['title']);
        $this->assertEquals('URGENT: Fix critical bug', $prioritized[1]['title']);
        $this->assertEquals('high', $prioritized[0]['priority']);
        $this->assertEquals('high', $prioritized[1]['priority']);
    }

    // Helper Methods

    private function createMockAiResponse(string $content)
    {
        $mock = Mockery::mock();
        $mock->shouldReceive('getContent')->andReturn($content);
        $mock->shouldReceive('getTokensUsed')->andReturn(100);
        $mock->shouldReceive('getCost')->andReturn(0.01);
        return $mock;
    }
}
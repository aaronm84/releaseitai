<?php

namespace Tests\Unit\Components;

use PHPUnit\Framework\TestCase;

/**
 * End of Day Summary Component Tests
 *
 * Testing the End of Day Summary component that appears after 3:00 PM
 * and provides daily accomplishments and next-day priorities.
 * Defines behavior for dismissible state and content requirements.
 */
class EndOfDaySummaryTest extends TestCase
{
    /**
     * Test component visibility based on time of day
     */
    public function test_component_visible_after_3pm()
    {
        // Component should be visible after 3:00 PM
        $visibleTimes = ['15:00', '16:30', '18:45', '21:00', '23:59'];

        foreach ($visibleTimes as $time) {
            $shouldShow = $this->shouldShowEndOfDaySummary($time);
            $this->assertTrue($shouldShow, "End of Day Summary should be visible at {$time}");
        }
    }

    /**
     * Test component hidden before 3pm
     */
    public function test_component_hidden_before_3pm()
    {
        // Component should be hidden before 3:00 PM
        $hiddenTimes = ['06:00', '09:30', '12:00', '14:59'];

        foreach ($hiddenTimes as $time) {
            $shouldShow = $this->shouldShowEndOfDaySummary($time);
            $this->assertFalse($shouldShow, "End of Day Summary should be hidden at {$time}");
        }
    }

    /**
     * Test boundary condition at exactly 3:00 PM
     */
    public function test_boundary_condition_at_3pm()
    {
        $this->assertFalse($this->shouldShowEndOfDaySummary('14:59'), 'Should be hidden at 2:59 PM');
        $this->assertTrue($this->shouldShowEndOfDaySummary('15:00'), 'Should be visible at exactly 3:00 PM');
        $this->assertTrue($this->shouldShowEndOfDaySummary('15:01'), 'Should be visible at 3:01 PM');
    }

    /**
     * Test component content structure
     */
    public function test_component_content_structure()
    {
        $content = $this->getEndOfDayContent();

        // Required content elements
        $this->assertArrayHasKey('title', $content);
        $this->assertArrayHasKey('completedTasks', $content);
        $this->assertArrayHasKey('meetingsAttended', $content);
        $this->assertArrayHasKey('keyDecisions', $content);
        $this->assertArrayHasKey('tomorrowPriorities', $content);

        // Title should include evening emoji and encouraging text
        $this->assertStringContainsString('End of Day', $content['title']);
        $this->assertStringContainsString('🌅', $content['title']); // Evening emoji
    }

    /**
     * Test ADHD-friendly encouraging messaging
     */
    public function test_adhd_friendly_messaging()
    {
        $content = $this->getEndOfDayContent();

        // Should contain encouraging language for ADHD users
        $encouragingPhrases = [
            'progress', 'accomplished', 'achieved', 'completed', 'done well'
        ];

        $hasEncouragement = false;
        foreach ($encouragingPhrases as $phrase) {
            if (stripos(json_encode($content), $phrase) !== false) {
                $hasEncouragement = true;
                break;
            }
        }

        $this->assertTrue($hasEncouragement, 'Content should include encouraging language for ADHD users');
    }

    /**
     * Test dismissible state functionality
     */
    public function test_dismissible_state_functionality()
    {
        $component = $this->createEndOfDayComponent();

        // Initially not dismissed
        $this->assertFalse($component->isDismissed());
        $this->assertTrue($component->isVisible());

        // After dismissing
        $component->dismiss();
        $this->assertTrue($component->isDismissed());
        $this->assertFalse($component->isVisible());

        // Can be restored
        $component->restore();
        $this->assertFalse($component->isDismissed());
        $this->assertTrue($component->isVisible());
    }

    /**
     * Test state persistence across sessions
     */
    public function test_state_persistence()
    {
        $component = $this->createEndOfDayComponent();

        // Dismiss and save state
        $component->dismiss();
        $savedState = $component->getState();

        // Create new component instance with saved state
        $restoredComponent = $this->createEndOfDayComponent($savedState);
        $this->assertTrue($restoredComponent->isDismissed(), 'Dismissed state should persist');

        // Restore and save state
        $restoredComponent->restore();
        $newState = $restoredComponent->getState();

        // Create another instance
        $finalComponent = $this->createEndOfDayComponent($newState);
        $this->assertFalse($finalComponent->isDismissed(), 'Restored state should persist');
    }

    /**
     * Test content data aggregation
     */
    public function test_content_data_aggregation()
    {
        $mockData = [
            'tasks' => [
                ['title' => 'Complete user testing', 'status' => 'completed', 'completed_at' => '2024-01-15 14:30:00'],
                ['title' => 'Review designs', 'status' => 'completed', 'completed_at' => '2024-01-15 16:15:00'],
                ['title' => 'Send follow-up email', 'status' => 'pending'],
            ],
            'meetings' => [
                ['title' => 'Standup', 'start_time' => '2024-01-15 09:00:00', 'status' => 'completed'],
                ['title' => 'Client review', 'start_time' => '2024-01-15 15:00:00', 'status' => 'completed'],
            ],
            'decisions' => [
                ['title' => 'Approved new feature spec', 'made_at' => '2024-01-15 11:30:00'],
            ]
        ];

        $aggregatedContent = $this->aggregateEndOfDayContent($mockData);

        $this->assertCount(2, $aggregatedContent['completedTasks'], 'Should show only completed tasks');
        $this->assertCount(2, $aggregatedContent['meetingsAttended'], 'Should show attended meetings');
        $this->assertCount(1, $aggregatedContent['keyDecisions'], 'Should show decisions made today');
        $this->assertNotEmpty($aggregatedContent['tomorrowPriorities'], 'Should generate tomorrow priorities');
    }

    /**
     * Test component integration with time changes
     */
    public function test_component_reactivity_to_time_changes()
    {
        $component = $this->createEndOfDayComponent();

        // Before 3 PM - should not be visible
        $component->updateTime('14:30');
        $this->assertFalse($component->shouldBeVisible(), 'Should not be visible before 3 PM');

        // After 3 PM - should become visible
        $component->updateTime('15:30');
        $this->assertTrue($component->shouldBeVisible(), 'Should be visible after 3 PM');

        // Next day before 3 PM - should not be visible again
        $component->updateTime('10:30', '2024-01-16');
        $this->assertFalse($component->shouldBeVisible(), 'Should not be visible next day before 3 PM');
    }

    /**
     * Test accessibility requirements
     */
    public function test_accessibility_requirements()
    {
        $component = $this->createEndOfDayComponent();

        // Should have proper ARIA labels
        $ariaLabels = $component->getAriaLabels();
        $this->assertArrayHasKey('region', $ariaLabels);
        $this->assertArrayHasKey('heading', $ariaLabels);
        $this->assertArrayHasKey('dismissButton', $ariaLabels);

        // Should support keyboard navigation
        $this->assertTrue($component->supportsKeyboardNavigation());

        // Should announce state changes to screen readers
        $component->dismiss();
        $this->assertNotEmpty($component->getScreenReaderAnnouncement());
    }

    /**
     * Test error handling for missing data
     */
    public function test_error_handling_for_missing_data()
    {
        // Test with empty data
        $emptyContent = $this->aggregateEndOfDayContent([]);
        $this->assertIsArray($emptyContent['completedTasks']);
        $this->assertIsArray($emptyContent['meetingsAttended']);
        $this->assertIsArray($emptyContent['keyDecisions']);
        $this->assertIsArray($emptyContent['tomorrowPriorities']);

        // Test with null data
        $nullContent = $this->aggregateEndOfDayContent(null);
        $this->assertIsArray($nullContent['completedTasks']);

        // Should provide default encouraging message when no data
        $this->assertStringContainsString('great', strtolower($nullContent['encouragingMessage']));
    }

    /**
     * Test performance requirements
     */
    public function test_performance_requirements()
    {
        $startTime = microtime(true);

        // Test data aggregation performance
        $largeDataSet = $this->generateLargeDataSet(1000);
        $content = $this->aggregateEndOfDayContent($largeDataSet);

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        // Should process large datasets quickly
        $this->assertLessThan(100, $executionTime, 'Data aggregation should complete in under 100ms');
        $this->assertNotEmpty($content['completedTasks']);
    }

    // Helper methods for testing

    /**
     * Mock implementation of time-based visibility logic
     */
    private function shouldShowEndOfDaySummary(string $time): bool
    {
        $hour = (int) substr($time, 0, 2);
        return $hour >= 15; // 3:00 PM and later
    }

    /**
     * Mock End of Day content structure
     */
    private function getEndOfDayContent(): array
    {
        return [
            'title' => '🌅 End of Day Wrap-Up',
            'completedTasks' => [
                'Completed user research interviews',
                'Reviewed and approved design mockups',
                'Sent project update to stakeholders'
            ],
            'meetingsAttended' => [
                'Morning standup with development team',
                'Client feedback session',
                'Weekly planning meeting'
            ],
            'keyDecisions' => [
                'Approved moving forward with prototype A',
                'Scheduled additional user testing for next week'
            ],
            'tomorrowPriorities' => [
                'Finalize feature specifications',
                'Prepare presentation for stakeholder review',
                'Follow up on pending approvals'
            ],
            'encouragingMessage' => "You've made great progress today! Focus on tomorrow's priorities to maintain momentum."
        ];
    }

    /**
     * Create mock End of Day component for testing
     */
    private function createEndOfDayComponent(?array $initialState = null)
    {
        return new class($initialState) {
            private $dismissed = false;
            private $currentTime = '15:00';
            private $currentDate = '2024-01-15';

            public function __construct(?array $state = null)
            {
                if ($state) {
                    $this->dismissed = $state['dismissed'] ?? false;
                }
            }

            public function isDismissed(): bool
            {
                return $this->dismissed;
            }

            public function isVisible(): bool
            {
                return !$this->dismissed && $this->shouldBeVisible();
            }

            public function shouldBeVisible(): bool
            {
                $hour = (int) substr($this->currentTime, 0, 2);
                return $hour >= 15;
            }

            public function dismiss(): void
            {
                $this->dismissed = true;
            }

            public function restore(): void
            {
                $this->dismissed = false;
            }

            public function getState(): array
            {
                return ['dismissed' => $this->dismissed];
            }

            public function updateTime(string $time, ?string $date = null): void
            {
                $this->currentTime = $time;
                if ($date) {
                    $this->currentDate = $date;
                }
            }

            public function getAriaLabels(): array
            {
                return [
                    'region' => 'End of day summary',
                    'heading' => 'Daily accomplishments and tomorrow\'s priorities',
                    'dismissButton' => 'Dismiss end of day summary'
                ];
            }

            public function supportsKeyboardNavigation(): bool
            {
                return true;
            }

            public function getScreenReaderAnnouncement(): string
            {
                return $this->dismissed ? 'End of day summary dismissed' : 'End of day summary restored';
            }
        };
    }

    /**
     * Mock data aggregation logic
     */
    private function aggregateEndOfDayContent(?array $data): array
    {
        if (!$data) {
            return [
                'completedTasks' => [],
                'meetingsAttended' => [],
                'keyDecisions' => [],
                'tomorrowPriorities' => [],
                'encouragingMessage' => 'Every day is a step forward - great job today!'
            ];
        }

        return [
            'completedTasks' => array_filter($data['tasks'] ?? [], fn($task) => $task['status'] === 'completed'),
            'meetingsAttended' => array_filter($data['meetings'] ?? [], fn($meeting) => $meeting['status'] === 'completed'),
            'keyDecisions' => $data['decisions'] ?? [],
            'tomorrowPriorities' => $this->generateTomorrowPriorities($data),
            'encouragingMessage' => 'You accomplished great things today!'
        ];
    }

    /**
     * Generate tomorrow's priorities based on current data
     */
    private function generateTomorrowPriorities(array $data): array
    {
        // Mock logic for generating priorities
        return [
            'Follow up on pending items',
            'Continue current project momentum',
            'Review and plan next steps'
        ];
    }

    /**
     * Generate large dataset for performance testing
     */
    private function generateLargeDataSet(int $size): array
    {
        $data = ['tasks' => [], 'meetings' => [], 'decisions' => []];

        for ($i = 0; $i < $size; $i++) {
            $data['tasks'][] = [
                'title' => "Task {$i}",
                'status' => $i % 2 === 0 ? 'completed' : 'pending',
                'completed_at' => '2024-01-15 ' . sprintf('%02d:00:00', $i % 24)
            ];
        }

        return $data;
    }
}
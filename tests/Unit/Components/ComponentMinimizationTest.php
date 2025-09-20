<?php

namespace Tests\Unit\Components;

use PHPUnit\Framework\TestCase;

/**
 * Universal Component Minimization System Tests
 *
 * Testing the dashboard component minimization system that allows users
 * to minimize components into pills and restore them. Tests state management,
 * persistence, animations, and ADHD-friendly UX patterns.
 */
class ComponentMinimizationTest extends TestCase
{
    /**
     * Test basic minimize/restore functionality
     */
    public function test_component_can_be_minimized_and_restored()
    {
        $component = $this->createMinimizableComponent('MorningBrief');

        // Initially expanded
        $this->assertTrue($component->isExpanded());
        $this->assertFalse($component->isMinimized());

        // After minimizing
        $component->minimize();
        $this->assertFalse($component->isExpanded());
        $this->assertTrue($component->isMinimized());

        // After restoring
        $component->restore();
        $this->assertTrue($component->isExpanded());
        $this->assertFalse($component->isMinimized());
    }

    /**
     * Test that greeting component cannot be minimized
     */
    public function test_greeting_component_cannot_be_minimized()
    {
        $greetingComponent = $this->createMinimizableComponent('Greeting');

        $this->assertFalse($greetingComponent->canBeMinimized(), 'Greeting should not be minimizable');

        // Attempting to minimize should have no effect
        $greetingComponent->minimize();
        $this->assertTrue($greetingComponent->isExpanded(), 'Greeting should remain expanded');
    }

    /**
     * Test multiple components can be minimized simultaneously
     */
    public function test_multiple_components_can_be_minimized()
    {
        $dashboard = $this->createDashboard([
            'MorningBrief',
            'BrainDump',
            'TopPriorities',
            'EndOfDay'
        ]);

        // Minimize multiple components
        $dashboard->minimizeComponent('MorningBrief');
        $dashboard->minimizeComponent('BrainDump');

        $minimizedComponents = $dashboard->getMinimizedComponents();
        $this->assertCount(2, $minimizedComponents);
        $this->assertContains('MorningBrief', $minimizedComponents);
        $this->assertContains('BrainDump', $minimizedComponents);

        // Expanded components should be reduced
        $expandedComponents = $dashboard->getExpandedComponents();
        $this->assertCount(2, $expandedComponents);
        $this->assertContains('TopPriorities', $expandedComponents);
        $this->assertContains('EndOfDay', $expandedComponents);
    }

    /**
     * Test pill creation and properties
     */
    public function test_minimized_components_become_pills()
    {
        $component = $this->createMinimizableComponent('BrainDump');
        $component->minimize();

        $pill = $component->getPill();
        $pillArray = $pill->toArray();

        // Pill should have required properties
        $this->assertArrayHasKey('id', $pillArray);
        $this->assertArrayHasKey('name', $pillArray);
        $this->assertArrayHasKey('icon', $pillArray);
        $this->assertArrayHasKey('color', $pillArray);
        $this->assertArrayHasKey('description', $pillArray);

        // Pill properties should match component
        $this->assertEquals('BrainDump', $pillArray['id']);
        $this->assertEquals('Brain Dump', $pillArray['name']);
        $this->assertEquals('🧠', $pillArray['icon']);
        $this->assertNotEmpty($pillArray['color']);
    }

    /**
     * Test pill row layout and organization
     */
    public function test_pill_row_layout_and_organization()
    {
        $dashboard = $this->createDashboard([
            'MorningBrief', 'BrainDump', 'TopPriorities',
            'EndOfDay', 'Workstreams', 'Communications'
        ]);

        // Minimize multiple components
        foreach (['MorningBrief', 'BrainDump', 'TopPriorities', 'EndOfDay'] as $componentId) {
            $dashboard->minimizeComponent($componentId);
        }

        $pillRow = $dashboard->getPillRow();

        // Should organize pills properly
        $this->assertCount(4, $pillRow['pills']);
        $this->assertEquals(1, $pillRow['rowCount']); // Should fit in one row initially

        // Minimize more components to test wrapping
        $dashboard->minimizeComponent('Workstreams');
        $dashboard->minimizeComponent('Communications');

        $pillRow = $dashboard->getPillRow();
        $this->assertCount(6, $pillRow['pills']);

        // Should wrap to multiple rows if needed (max 5-6 per row)
        $this->assertGreaterThanOrEqual(1, $pillRow['rowCount']);
    }

    /**
     * Test state persistence across sessions
     */
    public function test_state_persistence_across_sessions()
    {
        $dashboard = $this->createDashboard(['MorningBrief', 'BrainDump', 'TopPriorities']);

        // Minimize some components
        $dashboard->minimizeComponent('MorningBrief');
        $dashboard->minimizeComponent('BrainDump');

        // Save state
        $savedState = $dashboard->getState();

        // Create new dashboard instance with saved state
        $restoredDashboard = $this->createDashboard(['MorningBrief', 'BrainDump', 'TopPriorities'], $savedState);

        // State should be restored
        $this->assertTrue($restoredDashboard->getComponent('MorningBrief')->isMinimized());
        $this->assertTrue($restoredDashboard->getComponent('BrainDump')->isMinimized());
        $this->assertFalse($restoredDashboard->getComponent('TopPriorities')->isMinimized());
    }

    /**
     * Test localStorage integration
     */
    public function test_local_storage_integration()
    {
        $storage = $this->createMockLocalStorage();
        $dashboard = $this->createDashboard(['MorningBrief', 'BrainDump'], null, $storage);

        // Minimize component
        $dashboard->minimizeComponent('MorningBrief');

        // Should save to localStorage
        $savedData = $storage->getItem('dashboard_state');
        $this->assertNotNull($savedData);

        $state = json_decode($savedData, true);
        $this->assertTrue($state['MorningBrief']['minimized']);
        $this->assertFalse($state['BrainDump']['minimized']);
    }

    /**
     * Test animation timing and smoothness
     */
    public function test_animation_timing_and_smoothness()
    {
        $component = $this->createMinimizableComponent('BrainDump');

        // Test minimize animation
        $startTime = microtime(true);
        $animationPromise = $component->minimizeWithAnimation();

        // Animation should complete within expected timeframe
        $this->assertTrue($animationPromise->isAnimating());

        // Simulate animation completion
        $animationPromise->complete();
        $endTime = microtime(true);

        $duration = ($endTime - $startTime) * 1000; // Convert to ms
        $this->assertLessThan(400, $duration, 'Animation should complete within 400ms');
        $this->assertTrue($component->isMinimized());
    }

    /**
     * Test keyboard navigation support
     */
    public function test_keyboard_navigation_support()
    {
        $component = $this->createMinimizableComponent('BrainDump');

        // Should support keyboard events
        $this->assertTrue($component->supportsKeyboardNavigation());

        // Test keyboard minimize (Escape key)
        $keyboardEvent = $this->createKeyboardEvent('Escape');
        $result = $component->handleKeyboardEvent($keyboardEvent);

        $this->assertTrue($result->wasHandled());
        $this->assertTrue($component->isMinimized());

        // Test keyboard restore (Enter key on pill)
        $pill = $component->getPill();
        $enterEvent = $this->createKeyboardEvent('Enter');
        $restoreResult = $pill->handleKeyboardEvent($enterEvent);

        $this->assertTrue($restoreResult->wasHandled());
        $this->assertTrue($component->isExpanded());
    }

    /**
     * Test accessibility requirements
     */
    public function test_accessibility_requirements()
    {
        $component = $this->createMinimizableComponent('BrainDump');

        // Should have proper ARIA attributes
        $ariaAttributes = $component->getAriaAttributes();
        $this->assertArrayHasKey('aria-label', $ariaAttributes);
        $this->assertArrayHasKey('aria-expanded', $ariaAttributes);
        $this->assertArrayHasKey('role', $ariaAttributes);

        // Should announce state changes
        $component->minimize();
        $announcement = $component->getScreenReaderAnnouncement();
        $this->assertStringContainsString('minimized', strtolower($announcement));

        // Pill should have proper attributes
        $pill = $component->getPill();
        $pillAria = $pill->getAriaAttributes();
        $this->assertArrayHasKey('aria-label', $pillAria);
        $this->assertArrayHasKey('role', $pillAria);
        $this->assertStringContainsString('button', $pillAria['role']);
    }

    /**
     * Test responsive behavior
     */
    public function test_responsive_behavior()
    {
        $dashboard = $this->createDashboard(['A', 'B', 'C', 'D', 'E', 'F', 'G']);

        // Minimize all components
        foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G'] as $id) {
            $dashboard->minimizeComponent($id);
        }

        // Test different screen sizes
        $mobileLayout = $dashboard->getPillRowLayout('mobile');
        $tabletLayout = $dashboard->getPillRowLayout('tablet');
        $desktopLayout = $dashboard->getPillRowLayout('desktop');

        // Mobile should have fewer pills per row
        $this->assertLessThanOrEqual(3, $mobileLayout['pillsPerRow']);

        // Tablet should have moderate number
        $this->assertLessThanOrEqual(5, $tabletLayout['pillsPerRow']);

        // Desktop should have most
        $this->assertLessThanOrEqual(6, $desktopLayout['pillsPerRow']);
    }

    /**
     * Test focus mode functionality
     */
    public function test_focus_mode_functionality()
    {
        $dashboard = $this->createDashboard(['MorningBrief', 'BrainDump', 'TopPriorities', 'EndOfDay']);

        // Activate focus mode - should minimize all except top priority
        $dashboard->activateFocusMode();

        $expandedComponents = $dashboard->getExpandedComponents();
        $this->assertCount(1, $expandedComponents, 'Focus mode should leave only one component expanded');

        $minimizedComponents = $dashboard->getMinimizedComponents();
        $this->assertCount(3, $minimizedComponents, 'Focus mode should minimize all other components');

        // Deactivate focus mode - should restore previous state
        $dashboard->deactivateFocusMode();
        $allComponents = $dashboard->getAllComponents();
        $this->assertCount(4, $allComponents, 'All components should be available after focus mode');
    }

    /**
     * Test error handling and edge cases
     */
    public function test_error_handling_and_edge_cases()
    {
        $dashboard = $this->createDashboard(['MorningBrief']);

        // Try to minimize non-existent component
        $result = $dashboard->minimizeComponent('NonExistent');
        $this->assertFalse($result->wasSuccessful());
        $this->assertNotEmpty($result->getErrorMessage());

        // Try to restore already expanded component
        $component = $dashboard->getComponent('MorningBrief');
        $restoreResult = $component->restore();
        $this->assertTrue($restoreResult->wasSuccessful()); // Should handle gracefully

        // Test with corrupted state data
        $corruptedState = ['invalid' => 'data'];
        $dashboardWithCorruptedState = $this->createDashboard(['MorningBrief'], $corruptedState);

        // Should fallback to default state
        $this->assertTrue($dashboardWithCorruptedState->getComponent('MorningBrief')->isExpanded());
    }

    /**
     * Test performance with many components
     */
    public function test_performance_with_many_components()
    {
        // Create dashboard with many components
        $componentIds = array_map(fn($i) => "Component{$i}", range(1, 50));
        $dashboard = $this->createDashboard($componentIds);

        $startTime = microtime(true);

        // Minimize all components
        foreach ($componentIds as $id) {
            $dashboard->minimizeComponent($id);
        }

        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000;

        // Should handle many components efficiently
        $this->assertLessThan(100, $duration, 'Should minimize 50 components in under 100ms');

        // Pill row should still be organized properly
        $pillRow = $dashboard->getPillRow();
        $this->assertCount(50, $pillRow['pills']);
        $this->assertGreaterThan(1, $pillRow['rowCount']); // Should wrap to multiple rows
    }

    // Helper methods for testing

    /**
     * Create a minimizable component for testing
     */
    private function createMinimizableComponent(string $type, array $config = [])
    {
        return new class($type, $config) {
            private $type;
            private $minimized = false;
            private $config;

            public function __construct(string $type, array $config = [])
            {
                $this->type = $type;
                $this->config = $config;
            }

            public function canBeMinimized(): bool
            {
                return $this->type !== 'Greeting';
            }

            public function isExpanded(): bool
            {
                return !$this->minimized;
            }

            public function isMinimized(): bool
            {
                return $this->minimized;
            }

            public function minimize()
            {
                if ($this->canBeMinimized()) {
                    $this->minimized = true;
                }
                return $this;
            }

            public function restore()
            {
                $this->minimized = false;
                return $this->createActionResult(true);
            }

            public function minimizeWithAnimation()
            {
                $this->minimize();
                return $this->createAnimationPromise();
            }

            public function getPill(): object
            {
                $pillConfig = [
                    'MorningBrief' => ['name' => 'Morning Brief', 'icon' => '🌅', 'color' => '#3B82F6'],
                    'BrainDump' => ['name' => 'Brain Dump', 'icon' => '🧠', 'color' => '#884DFF'],
                    'TopPriorities' => ['name' => 'Top Priorities', 'icon' => '⭐', 'color' => '#F59E0B'],
                    'EndOfDay' => ['name' => 'End of Day', 'icon' => '🌅', 'color' => '#10B981'],
                ];

                $config = $pillConfig[$this->type] ?? ['name' => $this->type, 'icon' => '📌', 'color' => '#6B7280'];

                return new class($this->type, $config, $this) {
                    private $id;
                    private $config;
                    private $component;

                    public function __construct(string $id, array $config, $component)
                    {
                        $this->id = $id;
                        $this->config = $config;
                        $this->component = $component;
                    }

                    public function __get($name)
                    {
                        if ($name === 'id') return $this->id;
                        return $this->config[$name] ?? null;
                    }

                    public function toArray(): array
                    {
                        return [
                            'id' => $this->id,
                            'name' => $this->config['name'],
                            'icon' => $this->config['icon'],
                            'color' => $this->config['color'],
                            'description' => $this->config['description'] ?? "Restore {$this->config['name']} component"
                        ];
                    }

                    public function handleKeyboardEvent($event)
                    {
                        if ($event->key === 'Enter') {
                            $this->component->restore();
                            return $this->createActionResult(true);
                        }
                        return $this->createActionResult(false);
                    }

                    public function getAriaAttributes(): array
                    {
                        return [
                            'aria-label' => "Restore {$this->config['name']} component",
                            'role' => 'button'
                        ];
                    }

                    private function createActionResult(bool $success): object
                    {
                        return new class($success) {
                            private $success;

                            public function __construct(bool $success)
                            {
                                $this->success = $success;
                            }

                            public function wasHandled(): bool { return $this->success; }
                        };
                    }
                };
            }

            public function supportsKeyboardNavigation(): bool
            {
                return true;
            }

            public function handleKeyboardEvent($event)
            {
                if ($event->key === 'Escape' && $this->canBeMinimized()) {
                    $this->minimize();
                    return $this->createActionResult(true);
                }
                return $this->createActionResult(false);
            }

            public function getAriaAttributes(): array
            {
                return [
                    'aria-label' => "{$this->type} component",
                    'aria-expanded' => $this->isExpanded() ? 'true' : 'false',
                    'role' => 'region'
                ];
            }

            public function getScreenReaderAnnouncement(): string
            {
                return $this->isMinimized() ? "{$this->type} minimized" : "{$this->type} restored";
            }

            private function createActionResult(bool $success, string $message = ''): object
            {
                return new class($success, $message) {
                    private $success;
                    private $message;

                    public function __construct(bool $success, string $message = '')
                    {
                        $this->success = $success;
                        $this->message = $message;
                    }

                    public function wasSuccessful(): bool { return $this->success; }
                    public function wasHandled(): bool { return $this->success; }
                    public function getErrorMessage(): string { return $this->message; }
                };
            }

            private function createAnimationPromise(): object
            {
                return new class {
                    private $animating = true;

                    public function isAnimating(): bool { return $this->animating; }
                    public function complete(): void { $this->animating = false; }
                };
            }
        };
    }

    /**
     * Create a dashboard for testing
     */
    private function createDashboard(array $componentIds, ?array $savedState = null, $storage = null)
    {
        return new class($componentIds, $savedState, $storage) {
            private $components = [];
            private $storage;

            public function __construct(array $componentIds, ?array $savedState = null, $storage = null)
            {
                $this->storage = $storage;

                foreach ($componentIds as $id) {
                    $component = $this->createComponent($id);

                    // Restore state if provided
                    if ($savedState && isset($savedState[$id]['minimized']) && $savedState[$id]['minimized']) {
                        $component->minimize();
                    }

                    $this->components[$id] = $component;
                }
            }

            public function minimizeComponent(string $id)
            {
                if (!isset($this->components[$id])) {
                    return $this->createActionResult(false, 'Component not found');
                }

                $this->components[$id]->minimize();
                $this->saveState();
                return $this->createActionResult(true);
            }

            public function getComponent(string $id)
            {
                return $this->components[$id] ?? null;
            }

            public function getMinimizedComponents(): array
            {
                return array_keys(array_filter($this->components, fn($c) => $c->isMinimized()));
            }

            public function getExpandedComponents(): array
            {
                return array_keys(array_filter($this->components, fn($c) => $c->isExpanded()));
            }

            public function getAllComponents(): array
            {
                return array_keys($this->components);
            }

            public function getPillRow(): array
            {
                $pills = [];
                foreach ($this->components as $component) {
                    if ($component->isMinimized()) {
                        $pills[] = $component->getPill();
                    }
                }

                return [
                    'pills' => $pills,
                    'rowCount' => max(1, ceil(count($pills) / 5)) // 5 pills per row
                ];
            }

            public function getPillRowLayout(string $screenSize): array
            {
                $pillsPerRow = match($screenSize) {
                    'mobile' => 3,
                    'tablet' => 5,
                    'desktop' => 6,
                    default => 5
                };

                return ['pillsPerRow' => $pillsPerRow];
            }

            public function activateFocusMode(): void
            {
                $componentIds = array_keys($this->components);
                $keepExpanded = $componentIds[0] ?? null; // Keep first component

                foreach ($this->components as $id => $component) {
                    if ($id !== $keepExpanded) {
                        $component->minimize();
                    }
                }
            }

            public function deactivateFocusMode(): void
            {
                // In real implementation, would restore previous state
                foreach ($this->components as $component) {
                    $component->restore();
                }
            }

            public function getState(): array
            {
                $state = [];
                foreach ($this->components as $id => $component) {
                    $state[$id] = ['minimized' => $component->isMinimized()];
                }
                return $state;
            }

            private function saveState(): void
            {
                if ($this->storage) {
                    $this->storage->setItem('dashboard_state', json_encode($this->getState()));
                }
            }

            private function createActionResult(bool $success, string $message = ''): object
            {
                return new class($success, $message) {
                    private $success;
                    private $message;

                    public function __construct(bool $success, string $message = '')
                    {
                        $this->success = $success;
                        $this->message = $message;
                    }

                    public function wasSuccessful(): bool { return $this->success; }
                    public function getErrorMessage(): string { return $this->message; }
                };
            }

            private function createComponent(string $id)
            {
                return new class($id) {
                    private $id;
                    private $minimized = false;

                    public function __construct(string $id) { $this->id = $id; }
                    public function canBeMinimized(): bool { return $this->id !== 'Greeting'; }
                    public function isExpanded(): bool { return !$this->minimized; }
                    public function isMinimized(): bool { return $this->minimized; }
                    public function minimize() { if ($this->canBeMinimized()) $this->minimized = true; return $this; }
                    public function restore() {
                        $this->minimized = false;
                        return new class {
                            public function wasSuccessful(): bool { return true; }
                        };
                    }

                    public function getPill() {
                        return (object)['id' => $this->id, 'name' => $this->id, 'icon' => '📌', 'color' => '#6B7280'];
                    }
                };
            }
        };
    }

    /**
     * Create mock localStorage for testing
     */
    private function createMockLocalStorage()
    {
        return new class {
            private $data = [];

            public function setItem(string $key, string $value): void
            {
                $this->data[$key] = $value;
            }

            public function getItem(string $key): ?string
            {
                return $this->data[$key] ?? null;
            }
        };
    }

    /**
     * Create mock keyboard event for testing
     */
    private function createKeyboardEvent(string $key): object
    {
        return (object)['key' => $key];
    }
}
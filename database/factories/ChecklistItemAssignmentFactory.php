<?php

namespace Database\Factories;

use App\Models\ChecklistItem;
use App\Models\Release;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ChecklistItemAssignment>
 */
class ChecklistItemAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $assignedAt = $this->faker->dateTimeBetween('-1 week', 'now');
        $dueDate = $this->faker->dateTimeBetween($assignedAt, '+2 weeks');

        return [
            'checklist_item_id' => ChecklistItem::factory(),
            'assignee_id' => User::factory(),
            'release_id' => Release::factory(),
            'due_date' => $dueDate,
            'priority' => $this->faker->randomElement(['low', 'medium', 'high', 'critical']),
            'status' => $this->faker->randomElement(['pending', 'in_progress', 'completed', 'blocked']),
            'assigned_at' => $assignedAt,
            'started_at' => null,
            'completed_at' => null,
            'notes' => $this->faker->optional()->paragraph(),
            'sla_deadline' => null, // Will be calculated by model
            'escalated' => false,
            'escalated_at' => null,
            'escalation_reason' => null,
            'reassigned' => false,
            'reassignment_reason' => null,
            'previous_assignee_id' => null,
        ];
    }

    /**
     * Indicate that the assignment is completed.
     */
    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $startedAt = $this->faker->dateTimeBetween($attributes['assigned_at'], 'now');
            $completedAt = $this->faker->dateTimeBetween($startedAt, 'now');

            return [
                'status' => 'completed',
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
            ];
        });
    }

    /**
     * Indicate that the assignment is in progress.
     */
    public function inProgress(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'in_progress',
                'started_at' => $this->faker->dateTimeBetween($attributes['assigned_at'], 'now'),
            ];
        });
    }

    /**
     * Indicate that the assignment is overdue.
     */
    public function overdue(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'due_date' => $this->faker->dateTimeBetween('-1 week', '-1 day'),
                'status' => 'pending',
            ];
        });
    }

    /**
     * Indicate that the assignment has been escalated.
     */
    public function escalated(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'escalated' => true,
                'escalated_at' => $this->faker->dateTimeBetween($attributes['assigned_at'], 'now'),
                'escalation_reason' => $this->faker->sentence(),
            ];
        });
    }

    /**
     * Indicate that the assignment has been reassigned.
     */
    public function reassigned(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'reassigned' => true,
                'reassignment_reason' => $this->faker->sentence(),
                'previous_assignee_id' => User::factory(),
            ];
        });
    }
}

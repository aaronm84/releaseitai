<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ApprovalRequest>
 */
class ApprovalRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'release_id' => \App\Models\Release::factory(),
            'approval_type' => $this->faker->randomElement(\App\Models\ApprovalRequest::APPROVAL_TYPES),
            'approver_id' => \App\Models\User::factory(),
            'description' => $this->faker->sentence(10),
            'due_date' => $this->faker->dateTimeBetween('now', '+30 days'),
            'priority' => $this->faker->randomElement(\App\Models\ApprovalRequest::PRIORITIES),
            'status' => 'pending',
            'reminder_count' => 0,
            'auto_expire_days' => $this->faker->numberBetween(14, 60),
        ];
    }

    /**
     * Indicate that the approval request is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    /**
     * Indicate that the approval request is approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
        ]);
    }

    /**
     * Indicate that the approval request is rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
        ]);
    }

    /**
     * Indicate that the approval request is overdue.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => $this->faker->dateTimeBetween('-10 days', '-1 day'),
            'status' => 'pending',
        ]);
    }

    /**
     * Indicate that the approval request is due soon.
     */
    public function dueSoon(): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => $this->faker->dateTimeBetween('now', '+1 day'),
            'status' => 'pending',
        ]);
    }

    /**
     * Indicate that the approval request is high priority.
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'high',
        ]);
    }

    /**
     * Indicate that the approval request is critical priority.
     */
    public function critical(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'critical',
        ]);
    }
}

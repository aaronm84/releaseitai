<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ApprovalResponse>
 */
class ApprovalResponseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'approval_request_id' => \App\Models\ApprovalRequest::factory(),
            'responder_id' => \App\Models\User::factory(),
            'decision' => $this->faker->randomElement(\App\Models\ApprovalResponse::DECISIONS),
            'comments' => $this->faker->paragraph(),
            'conditions' => null,
            'responded_at' => now(),
        ];
    }

    /**
     * Indicate that the response is approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'decision' => 'approved',
            'comments' => 'Approved - all requirements met.',
        ]);
    }

    /**
     * Indicate that the response is approved with conditions.
     */
    public function approvedWithConditions(): static
    {
        return $this->state(fn (array $attributes) => [
            'decision' => 'approved',
            'comments' => 'Approved with conditions.',
            'conditions' => [
                'Update documentation before release',
                'Add additional test coverage',
            ],
        ]);
    }

    /**
     * Indicate that the response is rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'decision' => 'rejected',
            'comments' => 'Rejected - requirements not met.',
        ]);
    }

    /**
     * Indicate that the response needs changes.
     */
    public function needsChanges(): static
    {
        return $this->state(fn (array $attributes) => [
            'decision' => 'needs_changes',
            'comments' => 'Changes required before approval.',
        ]);
    }
}

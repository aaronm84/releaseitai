<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReleaseTask>
 */
class ReleaseTaskFactory extends Factory
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
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'type' => $this->faker->randomElement(['development', 'testing', 'documentation', 'stakeholder', 'deployment', 'custom']),
            'status' => $this->faker->randomElement(['pending', 'in_progress', 'completed', 'blocked']),
            'priority' => $this->faker->randomElement(['low', 'medium', 'high', 'critical']),
            'assigned_to' => null, // Will be set when needed in tests
            'due_date' => $this->faker->optional(0.7)->dateTimeBetween('now', '+30 days'),
            'order' => $this->faker->numberBetween(1, 100),
            'is_blocker' => $this->faker->boolean(20), // 20% chance of being a blocker
            'notes' => $this->faker->optional(0.3)->paragraph(),
        ];
    }
}

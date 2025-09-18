<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Release>
 */
class ReleaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->paragraph(),
            'workstream_id' => \App\Models\Workstream::factory(),
            'target_date' => $this->faker->dateTimeBetween('now', '+6 months'),
            'status' => $this->faker->randomElement(['planned', 'in_progress', 'completed', 'cancelled', 'on_hold']),
        ];
    }
}

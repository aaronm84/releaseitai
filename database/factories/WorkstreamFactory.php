<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Workstream>
 */
class WorkstreamFactory extends Factory
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
            'type' => $this->faker->randomElement(['product_line', 'initiative', 'experiment']),
            'status' => $this->faker->randomElement(['draft', 'active', 'on_hold', 'completed', 'cancelled']),
            'owner_id' => \App\Models\User::factory(),
            'parent_workstream_id' => null,
            'hierarchy_depth' => 1,
        ];
    }
}

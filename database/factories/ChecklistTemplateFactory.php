<?php

namespace Database\Factories;

use App\Models\Workstream;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ChecklistTemplate>
 */
class ChecklistTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true) . ' Checklist',
            'description' => $this->faker->paragraph(),
            'workstream_id' => Workstream::factory(),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the template is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Models\ChecklistTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ChecklistItem>
 */
class ChecklistItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'checklist_template_id' => ChecklistTemplate::factory(),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'order' => $this->faker->numberBetween(1, 100),
            'estimated_hours' => $this->faker->numberBetween(1, 80),
            'sla_hours' => $this->faker->numberBetween(24, 168), // 1 day to 1 week
            'is_required' => $this->faker->boolean(80), // 80% chance of being required
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the item is not required.
     */
    public function optional(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_required' => false,
        ]);
    }

    /**
     * Indicate that the item is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Set a specific SLA hours value.
     */
    public function withSlaHours(int $hours): static
    {
        return $this->state(fn (array $attributes) => [
            'sla_hours' => $hours,
        ]);
    }
}

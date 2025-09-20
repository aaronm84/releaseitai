<?php

namespace Database\Factories;

use App\Models\Content;
use App\Models\Stakeholder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ContentActionItem>
 */
class ContentActionItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'content_id' => Content::factory(),
            'action_text' => $this->faker->sentence(),
            'assignee_stakeholder_id' => $this->faker->optional()->randomNumber(),
            'priority' => $this->faker->randomElement(['low', 'medium', 'high', 'urgent']),
            'due_date' => $this->faker->optional()->date(),
            'status' => $this->faker->randomElement(['pending', 'in_progress', 'completed', 'cancelled']),
            'confidence_score' => $this->faker->randomFloat(2, 0, 1),
            'context' => $this->faker->optional()->paragraph(),
        ];
    }
}

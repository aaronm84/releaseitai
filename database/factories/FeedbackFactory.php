<?php

namespace Database\Factories;

use App\Models\Output;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Feedback>
 */
class FeedbackFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'output_id' => Output::factory(),
            'user_id' => User::factory(),
            'type' => $this->faker->randomElement(['inline', 'behavioral']),
            'action' => $this->faker->randomElement(['thumbs_up', 'thumbs_down', 'copy', 'share', 'bookmark']),
            'signal_type' => $this->faker->randomElement(['explicit', 'passive']),
            'confidence' => $this->faker->randomFloat(2, 0, 1),
            'metadata' => [
                'timestamp' => $this->faker->iso8601(),
                'source' => $this->faker->word(),
            ],
        ];
    }

    /**
     * Indicate that the feedback is explicit.
     */
    public function explicit(): static
    {
        return $this->state(fn (array $attributes) => [
            'signal_type' => 'explicit',
        ]);
    }

    /**
     * Indicate that the feedback is passive.
     */
    public function passive(): static
    {
        return $this->state(fn (array $attributes) => [
            'signal_type' => 'passive',
        ]);
    }

    /**
     * Indicate that the feedback is inline type.
     */
    public function inline(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'inline',
        ]);
    }

    /**
     * Indicate that the feedback is behavioral type.
     */
    public function behavioral(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'behavioral',
        ]);
    }

    /**
     * Indicate positive feedback (thumbs up).
     */
    public function positive(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'thumbs_up',
            'signal_type' => 'explicit',
        ]);
    }

    /**
     * Indicate negative feedback (thumbs down).
     */
    public function negative(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'thumbs_down',
            'signal_type' => 'explicit',
        ]);
    }
}

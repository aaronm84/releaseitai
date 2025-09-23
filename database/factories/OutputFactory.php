<?php

namespace Database\Factories;

use App\Models\Input;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Output>
 */
class OutputFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'input_id' => Input::factory(),
            'content' => $this->faker->paragraphs(2, true),
            'type' => $this->faker->randomElement(['text', 'json', 'markdown']),
            'ai_model' => $this->faker->randomElement(['gpt-4', 'claude-3', 'gemini-pro']),
            'quality_score' => $this->faker->randomFloat(2, 0.1, 1.0),
            'version' => 1,
            'parent_output_id' => null,
            'feedback_integrated' => false,
            'feedback_count' => 0,
            'content_format' => 'json',
            'metadata' => [],
        ];
    }
}

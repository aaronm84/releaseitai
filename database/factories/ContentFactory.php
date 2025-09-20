<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Content>
 */
class ContentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => $this->faker->randomElement(['email', 'file', 'manual', 'meeting_notes', 'slack', 'teams']),
            'title' => $this->faker->sentence(),
            'description' => $this->faker->optional()->paragraph(),
            'content' => $this->faker->paragraphs(3, true),
            'raw_content' => $this->faker->paragraphs(4, true),
            'metadata' => [
                'timestamp' => $this->faker->dateTime->format('Y-m-d H:i:s'),
                'source' => $this->faker->word,
            ],
            'file_path' => $this->faker->optional()->filePath(),
            'file_type' => $this->faker->optional()->fileExtension(),
            'file_size' => $this->faker->optional()->numberBetween(1000, 1000000),
            'source_reference' => $this->faker->optional()->uuid(),
            'processed_at' => $this->faker->optional()->dateTime(),
            'ai_summary' => $this->faker->optional()->paragraph(),
            'status' => $this->faker->randomElement(['pending', 'processing', 'processed', 'failed']),
            'tags' => $this->faker->optional()->randomElements(['meeting', 'document', 'report', 'notes', 'email'], $this->faker->numberBetween(0, 3)),
        ];
    }
}

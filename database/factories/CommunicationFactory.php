<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Communication>
 */
class CommunicationFactory extends Factory
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
            'initiated_by_user_id' => \App\Models\User::factory(),
            'channel' => $this->faker->randomElement(\App\Models\Communication::CHANNELS),
            'subject' => $this->faker->sentence(),
            'content' => $this->faker->paragraphs(2, true),
            'communication_type' => $this->faker->randomElement(\App\Models\Communication::TYPES),
            'direction' => $this->faker->randomElement(\App\Models\Communication::DIRECTIONS),
            'priority' => $this->faker->randomElement(\App\Models\Communication::PRIORITIES),
            'communication_date' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'status' => $this->faker->randomElement(\App\Models\Communication::STATUSES),
            'external_id' => $this->faker->optional()->uuid(),
            'thread_id' => $this->faker->optional()->regexify('thread_[a-z0-9]{10}_[0-9]{10}'),
            'is_sensitive' => $this->faker->boolean(20), // 20% chance of being sensitive
            'compliance_tags' => $this->faker->optional()->randomElement(['GDPR', 'SOX', 'PCI-DSS', 'HIPAA']),
            'metadata' => $this->faker->optional()->randomElement([
                ['channel_name' => '#mobile-release', 'message_id' => '1234567890.123456'],
                ['meeting_id' => 'zoom_123456789', 'recording_url' => 'https://zoom.us/rec/123456'],
                ['email_message_id' => '<123456@company.com>', 'thread_index' => 'abc123def456']
            ]),
            'attachments' => $this->faker->optional()->randomElement([
                [['name' => 'document.pdf', 'size' => 1024000, 'type' => 'application/pdf']],
                [['name' => 'screenshot.png', 'size' => 512000, 'type' => 'image/png']]
            ]),
        ];
    }
}

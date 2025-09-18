<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CommunicationParticipant>
 */
class CommunicationParticipantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $deliveryStatus = $this->faker->randomElement(\App\Models\CommunicationParticipant::DELIVERY_STATUSES);
        $hasDelivered = in_array($deliveryStatus, ['delivered', 'read', 'responded']);
        $hasRead = in_array($deliveryStatus, ['read', 'responded']);
        $hasResponded = $deliveryStatus === 'responded';

        return [
            'communication_id' => \App\Models\Communication::factory(),
            'user_id' => \App\Models\User::factory(),
            'participant_type' => $this->faker->randomElement(\App\Models\CommunicationParticipant::PARTICIPANT_TYPES),
            'role' => $this->faker->randomElement(\App\Models\CommunicationParticipant::ROLES),
            'delivery_status' => $deliveryStatus,
            'delivered_at' => $hasDelivered ? $this->faker->dateTimeBetween('-7 days', 'now') : null,
            'read_at' => $hasRead ? $this->faker->dateTimeBetween('-6 days', 'now') : null,
            'responded_at' => $hasResponded ? $this->faker->dateTimeBetween('-5 days', 'now') : null,
            'response_content' => $hasResponded ? $this->faker->optional()->sentence() : null,
            'response_sentiment' => $hasResponded ? $this->faker->randomElement(\App\Models\CommunicationParticipant::RESPONSE_SENTIMENTS) : null,
            'contact_method' => $this->faker->randomElement([
                $this->faker->email(),
                '@' . $this->faker->userName(),
                $this->faker->phoneNumber(),
            ]),
            'channel_metadata' => $this->faker->optional()->randomElement([
                ['slack_user_id' => 'U123456789', 'slack_channel' => 'C987654321'],
                ['email_address' => $this->faker->email(), 'display_name' => $this->faker->name()],
                ['phone_number' => $this->faker->phoneNumber(), 'carrier' => 'Verizon']
            ]),
        ];
    }
}

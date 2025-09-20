<?php

namespace Database\Factories;

use App\Models\Stakeholder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Stakeholder>
 */
class StakeholderFactory extends Factory
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
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'company' => fake()->company(),
            'title' => fake()->jobTitle(),
            'department' => fake()->randomElement(['Engineering', 'Product', 'Marketing', 'Sales', 'HR', 'Finance']),
            'phone' => fake()->phoneNumber(),
            'slack_handle' => '@' . fake()->userName(),
            'influence_level' => fake()->randomElement(['low', 'medium', 'high']),
            'support_level' => fake()->randomElement(['low', 'medium', 'high']),
            'notes' => fake()->sentence(),
            'is_available' => true,
            'needs_follow_up' => false,
            'last_contact_at' => fake()->optional(0.7)->dateTimeBetween('-30 days', 'now'),
            'last_contact_channel' => fake()->randomElement(['email', 'slack', 'phone', 'in_person']),
        ];
    }

    /**
     * Indicate that the stakeholder needs follow up.
     */
    public function needsFollowUp(): static
    {
        return $this->state(fn (array $attributes) => [
            'needs_follow_up' => true,
            'last_contact_at' => fake()->dateTimeBetween('-15 days', '-10 days'),
        ]);
    }

    /**
     * Indicate that the stakeholder has high influence.
     */
    public function highInfluence(): static
    {
        return $this->state(fn (array $attributes) => [
            'influence_level' => 'high',
        ]);
    }

    /**
     * Indicate that the stakeholder has high support.
     */
    public function highSupport(): static
    {
        return $this->state(fn (array $attributes) => [
            'support_level' => 'high',
        ]);
    }

    /**
     * Indicate that the stakeholder is unavailable.
     */
    public function unavailable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_available' => false,
            'unavailable_until' => fake()->dateTimeBetween('now', '+30 days'),
        ]);
    }
}
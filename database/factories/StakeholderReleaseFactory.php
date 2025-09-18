<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StakeholderRelease>
 */
class StakeholderReleaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'release_id' => \App\Models\Release::factory(),
            'role' => $this->faker->randomElement(\App\Models\StakeholderRelease::ROLES),
            'notification_preference' => $this->faker->randomElement(\App\Models\StakeholderRelease::NOTIFICATION_PREFERENCES),
        ];
    }
}

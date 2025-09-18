<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkstreamPermission>
 */
class WorkstreamPermissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workstream_id' => \App\Models\Workstream::factory(),
            'user_id' => \App\Models\User::factory(),
            'permission_type' => $this->faker->randomElement(['view', 'edit', 'admin']),
            'scope' => $this->faker->randomElement(['workstream_only', 'workstream_and_children']),
            'granted_by' => \App\Models\User::factory(),
        ];
    }
}

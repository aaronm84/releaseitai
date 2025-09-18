<?php

namespace Database\Factories;

use App\Models\ChecklistItemAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ChecklistItemDependency>
 */
class ChecklistItemDependencyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'prerequisite_assignment_id' => ChecklistItemAssignment::factory(),
            'dependent_assignment_id' => ChecklistItemAssignment::factory(),
            'dependency_type' => $this->faker->randomElement(['blocks', 'enables', 'informs']),
            'description' => $this->faker->optional()->sentence(),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the dependency is a blocking type.
     */
    public function blocks(): static
    {
        return $this->state(fn (array $attributes) => [
            'dependency_type' => 'blocks',
        ]);
    }

    /**
     * Indicate that the dependency is an enabling type.
     */
    public function enables(): static
    {
        return $this->state(fn (array $attributes) => [
            'dependency_type' => 'enables',
        ]);
    }

    /**
     * Indicate that the dependency is an informational type.
     */
    public function informs(): static
    {
        return $this->state(fn (array $attributes) => [
            'dependency_type' => 'informs',
        ]);
    }

    /**
     * Indicate that the dependency is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}

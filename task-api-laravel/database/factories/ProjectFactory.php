<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->sentence(rand(2, 5)),
            'description' => fake()->optional(0.7)->paragraph(),
            'status' => fake()->randomElement([
                Project::STATUS_DRAFT,
                Project::STATUS_ACTIVE,
                Project::STATUS_ARCHIVED,
            ]),
        ];
    }
}

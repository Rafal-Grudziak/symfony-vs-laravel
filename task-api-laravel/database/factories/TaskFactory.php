<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => fake()->sentence(rand(3, 8)),
            'description' => fake()->optional(0.6)->paragraphs(1, 3, true),
            'status' => fake()->randomElement([
                Task::STATUS_TODO,
                Task::STATUS_IN_PROGRESS,
                Task::STATUS_DONE,
                Task::STATUS_CANCELLED,
            ]),
            'priority' => fake()->randomElement([
                Task::PRIORITY_LOW,
                Task::PRIORITY_MEDIUM,
                Task::PRIORITY_HIGH,
                Task::PRIORITY_URGENT,
            ]),
            'due_date' => fake()->boolean(50)
                ? fake()->dateTimeBetween('now', '+3 months')->format('Y-m-d')
                : null,
        ];
    }
}

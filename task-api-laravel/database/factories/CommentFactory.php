<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'content' => fake()->realText(rand(80, 400)),
        ];
    }
}

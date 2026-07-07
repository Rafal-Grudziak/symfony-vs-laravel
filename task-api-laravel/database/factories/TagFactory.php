<?php

namespace Database\Factories;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->slug(rand(2, 4)),
            'color' => fake()->randomElement([
                '#EF4444', '#F97316', '#EAB308', '#22C55E', '#14B8A6',
                '#3B82F6', '#8B5CF6', '#EC4899', '#6B7280',
            ]),
        ];
    }
}

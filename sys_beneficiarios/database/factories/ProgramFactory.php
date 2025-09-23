<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Program>
 */
class ProgramFactory extends Factory
{
    protected $model = Program::class;

    public function definition(): array
    {
        $name = 'Programa ' . Str::title(fake()->unique()->words(2, true));

        return [
            'name' => $name,
            'slug' => Str::slug($name . '-' . fake()->unique()->numerify('###')),
            'area' => fake()->randomElement(['educacion', 'salud', 'deporte']),
            'active' => true,
            'description' => fake()->optional()->sentence(),
        ];
    }
}

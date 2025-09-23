<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\VolGroup;
use App\Models\VolSite;
use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\VolGroup>
 */
class VolGroupFactory extends Factory
{
    protected $model = VolGroup::class;

    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-1 week', '+2 weeks');
        $maybeEnd = fake()->boolean(60) ? fake()->dateTimeBetween($startDate, '+6 months') : null;

        return [
            'program_id' => Program::factory(),
            'site_id' => VolSite::factory(),
            'code' => strtoupper(Str::random(6)),
            'name' => 'Grupo ' . Str::title(fake()->unique()->words(2, true)),
            'type' => fake()->randomElement(['semanal', 'sabatino']),
            'schedule_template' => fake()->randomElement(['lmv', 'mj', 'sab']),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $maybeEnd ? $maybeEnd->format('Y-m-d') : null,
            'capacity' => fake()->numberBetween(8, 20),
            'state' => fake()->randomElement(['borrador', 'publicado', 'cerrado']),
            'created_by' => User::factory(),
            'updated_by' => null,
        ];
    }
}

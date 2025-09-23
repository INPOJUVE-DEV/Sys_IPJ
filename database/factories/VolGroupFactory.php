<?php

namespace Database\Factories;

use App\Models\Program;
use App\Models\VolGroup;
use App\Models\VolSite;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class VolGroupFactory extends Factory
{
    protected $model = VolGroup::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-1 month', '+1 month');
        $end = fake()->boolean(60) ? fake()->dateTimeBetween($start, '+3 months') : null;

        return [
            'program_id' => Program::factory(),
            'site_id' => VolSite::factory(),
            'code' => 'JAV-' . Str::upper(Str::random(4)),
            'name' => 'Grupo ' . Str::title(fake()->unique()->words(2, true)),
            'type' => fake()->randomElement(['semanal', 'sabatino']),
            'schedule_template' => fake()->randomElement(['lmv', 'mj', 'sab']),
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end?->format('Y-m-d'),
            'capacity' => fake()->numberBetween(8, 20),
            'state' => 'borrador',
        ];
    }
}
<?php

namespace Database\Factories;

use App\Models\VolSite;
use Illuminate\Database\Eloquent\Factories\Factory;

class VolSiteFactory extends Factory
{
    protected $model = VolSite::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->city() . ' Center',
            'state' => fake()->state(),
            'city' => fake()->city(),
            'address' => fake()->streetAddress(),
            'active' => true,
        ];
    }
}
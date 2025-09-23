<?php

namespace Database\Factories;

use App\Models\Beneficiario;
use App\Models\VolPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

class VolPaymentFactory extends Factory
{
    protected $model = VolPayment::class;

    public function definition(): array
    {
        return [
            'beneficiario_id' => Beneficiario::factory(),
            'payment_type' => fake()->randomElement(['transferencia', 'tarjeta', 'deposito']),
            'payment_date' => fake()->date(),
            'receipt_ref' => fake()->optional()->bothify('REC-####'),
        ];
    }
}
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Beneficiario;
use App\Models\Municipio;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Beneficiario>
 */
class BeneficiarioFactory extends Factory
{
    protected $model = Beneficiario::class;

    public function definition(): array
    {
        $municipioId = Municipio::inRandomOrder()->value('id');

        return [
            'id' => (string) Str::uuid(),
            'folio_tarjeta' => Str::upper(Str::random(12)),
            'nombre' => fake()->firstName(),
            'apellido_paterno' => fake()->lastName(),
            'apellido_materno' => fake()->lastName(),
            'curp' => Str::upper(Str::random(18)),
            'fecha_nacimiento' => fake()->date('Y-m-d', '-18 years'),
            'edad' => fake()->numberBetween(18, 65),
            'sexo' => fake()->randomElement(['M', 'F', 'X']),
            'discapacidad' => false,
            'telefono' => fake()->numerify('##########'),
            'municipio_id' => $municipioId,
            'seccional' => fake()->numerify('####'),
            'distrito_local' => fake()->numerify('##'),
            'distrito_federal' => fake()->numerify('##'),
            'created_by' => null,
        ];
    }
}

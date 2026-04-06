<?php

namespace Database\Seeders;

use App\Models\Oficina;
use Illuminate\Database\Seeder;

class OficinaSeeder extends Seeder
{
    public function run(): void
    {
        $offices = [
            ['nombre' => 'Central', 'tipo' => Oficina::TIPO_CENTRAL],
            ['nombre' => 'Delegacion 1', 'tipo' => Oficina::TIPO_DELEGACION],
            ['nombre' => 'Delegacion 2', 'tipo' => Oficina::TIPO_DELEGACION],
            ['nombre' => 'Delegacion 3', 'tipo' => Oficina::TIPO_DELEGACION],
            ['nombre' => 'Delegacion 4', 'tipo' => Oficina::TIPO_DELEGACION],
        ];

        foreach ($offices as $office) {
            Oficina::firstOrCreate(
                ['nombre' => $office['nombre']],
                ['tipo' => $office['tipo'], 'activo' => true]
            );
        }
    }
}

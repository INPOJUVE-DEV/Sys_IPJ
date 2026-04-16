<?php

namespace Database\Seeders;

use App\Models\Oficina;
use Illuminate\Database\Seeder;

class OficinaSeeder extends Seeder
{
    public function run(): void
    {
        Oficina::updateOrCreate(
            ['nombre' => 'Central'],
            ['tipo' => Oficina::TIPO_CENTRAL, 'region' => null, 'activo' => true]
        );

        $regions = ['Altiplano', 'Centro', 'Huasteca', 'Media'];

        foreach ($regions as $index => $region) {
            $office = Oficina::where('region', $region)->first()
                ?: Oficina::where('nombre', 'Delegacion '.($index + 1))->first()
                ?: new Oficina();

            $office->fill([
                'nombre' => 'Delegacion '.$region,
                'tipo' => Oficina::TIPO_DELEGACION,
                'region' => $region,
                'activo' => true,
            ]);
            $office->save();
        }
    }
}

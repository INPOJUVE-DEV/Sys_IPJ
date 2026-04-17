<?php

namespace Database\Seeders;

use App\Models\EventoTipo;
use Illuminate\Database\Seeder;

class EventoTipoSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Colaboraciones', 'Trabajo social', 'Culturales', 'Deportivos'] as $nombre) {
            EventoTipo::firstOrCreate(
                ['nombre' => $nombre],
                ['activo' => true]
            );
        }
    }
}

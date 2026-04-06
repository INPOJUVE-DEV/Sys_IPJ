<?php

namespace Database\Seeders;

use App\Services\CatalogoCsvSyncService;
use Illuminate\Database\Seeder;

class CatalogosSeeder extends Seeder
{
    public function run(): void
    {
        $stats = app(CatalogoCsvSyncService::class)->sync();

        if (isset($this->command)) {
            $this->command->info(
                'Municipios: +'.$stats['municipios']['inserted']
                .' ~'.$stats['municipios']['updated']
                .' -'.$stats['municipios']['deleted']
                .' total='.$stats['municipios']['source_total']
            );
            $this->command->info(
                'Secciones: +'.$stats['secciones']['inserted']
                .' ~'.$stats['secciones']['updated']
                .' -'.$stats['secciones']['deleted']
                .' omitidas='.$stats['secciones']['skipped']
                .' total='.$stats['secciones']['source_total']
            );
        }
    }
}

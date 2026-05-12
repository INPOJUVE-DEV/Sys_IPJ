<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $seeders = [
            OficinaSeeder::class,
            RoleSeeder::class,
            AdminUserSeeder::class,
            CatalogosSeeder::class,
            EventoTipoSeeder::class,
        ];

        // Demo data must be opt-in because local environments may still target shared databases.
        $shouldSeedDemoData = app()->environment('local')
            && filter_var(env('SEED_DEMO_DATA', false), FILTER_VALIDATE_BOOL);

        if ($shouldSeedDemoData) {
            $seeders[] = DemoDataSeeder::class;
        }

        $this->call($seeders);
    }
}

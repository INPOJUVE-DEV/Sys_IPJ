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
            IntegrationTechnicalUserSeeder::class,
            CatalogosSeeder::class,
            EventoTipoSeeder::class,
            IntegrationClientSeeder::class,
        ];

        if (app()->environment('local')) {
            $seeders[] = DemoDataSeeder::class;
        }

        $this->call($seeders);
    }
}

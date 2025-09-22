<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            AdminUserSeeder::class,
            CatalogosSeeder::class,
            NormalizeRolesSeeder::class,
            Salud360RolesSeeder::class,
        ]);

        // Semilla demo opcional (solo en local)
        if (app()->environment('local')) {
            $this->call(Salud360DemoSeeder::class);
        }
    }
}

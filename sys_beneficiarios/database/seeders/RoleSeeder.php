<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['admin', 'delegado', 'capturista', 'capturista_programas', 'skate_plaza'] as $name) {
            Role::firstOrCreate(['name' => $name]);
        }
    }
}

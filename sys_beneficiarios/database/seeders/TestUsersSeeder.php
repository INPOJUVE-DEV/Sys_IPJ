<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class TestUsersSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure roles exist
        if (class_exists(RoleSeeder::class)) {
            $this->call(RoleSeeder::class);
        }

        $users = [
            ['email' => 'admin2@example.com', 'name' => 'Admin Prueba', 'role' => 'admin'],
            ['email' => 'encargado2@example.com', 'name' => 'Encargado Prueba', 'role' => 'encargado_360'],
            ['email' => 'capturista2@example.com', 'name' => 'Capturista Prueba', 'role' => 'capturista'],
        ];

        foreach ($users as $u) {
            $user = User::firstOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'password' => 'Prueba123*', // hashed by cast
                    'email_verified_at' => now(),
                ]
            );
            if (! $user->hasRole($u['role'])) {
                $user->assignRole($u['role']);
            }
        }
    }
}

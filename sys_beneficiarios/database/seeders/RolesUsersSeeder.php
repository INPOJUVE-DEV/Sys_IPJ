<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class RolesUsersSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure roles exist
        if (class_exists(RoleSeeder::class)) {
            $this->call(RoleSeeder::class);
        }

        $users = [
            ['email' => 'admin@example.com', 'name' => 'Administrador', 'role' => 'admin'],
            ['email' => 'encargado@example.com', 'name' => 'Encargado', 'role' => 'encargado_360'],
            ['email' => 'capturista@example.com', 'name' => 'Capturista', 'role' => 'capturista'],
        ];

        foreach ($users as $u) {
            $user = User::firstOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'password' => 'Password123',
                    'email_verified_at' => now(),
                ]
            );
            if (! $user->hasRole($u['role'])) {
                $user->assignRole($u['role']);
            }
        }
    }
}

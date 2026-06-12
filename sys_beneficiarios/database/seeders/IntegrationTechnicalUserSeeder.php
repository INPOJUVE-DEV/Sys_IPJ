<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class IntegrationTechnicalUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = trim((string) config('integrations.api_tj.integration_user_email'));
        if ($email === '') {
            return;
        }

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Integracion API_TJ',
                'password' => Hash::make(Str::random(32)),
                'email_verified_at' => now(),
            ]
        );

        if (! $user->email_verified_at || $user->name !== 'Integracion API_TJ') {
            $user->forceFill([
                'name' => 'Integracion API_TJ',
                'email_verified_at' => $user->email_verified_at ?: now(),
            ])->save();
        }
    }
}

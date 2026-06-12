<?php

namespace Database\Seeders;

use App\Models\Integrations\IntegrationClient;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class IntegrationClientSeeder extends Seeder
{
    public function run(): void
    {
        $clients = [
            [
                'client_code' => 'api_tj',
                'name' => 'API Tarjeta Joven',
                'status' => IntegrationClient::STATUS_ACTIVE,
                'allowed_scopes' => ['beneficiarios.staging.push'],
            ],
            [
                'client_code' => 'sys_ipj',
                'name' => 'Sys_IPJ',
                'status' => IntegrationClient::STATUS_ACTIVE,
                'allowed_scopes' => ['cardholders.sync'],
            ],
        ];

        foreach ($clients as $attributes) {
            IntegrationClient::query()->updateOrCreate(
                ['client_code' => $attributes['client_code']],
                [
                    'id' => IntegrationClient::query()
                        ->where('client_code', $attributes['client_code'])
                        ->value('id') ?: (string) Str::uuid(),
                    'name' => $attributes['name'],
                    'status' => $attributes['status'],
                    'allowed_scopes' => $attributes['allowed_scopes'],
                ]
            );
        }
    }
}

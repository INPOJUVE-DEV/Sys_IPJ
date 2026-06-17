<?php

namespace App\Console\Commands;

use App\Models\Integrations\IntegrationClient;
use App\Models\Integrations\IntegrationClientKey;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class IntegrationsKeysUpsert extends Command
{
    protected $signature = 'integrations:keys:upsert {client_code} {kid} {public_key_path}';

    protected $description = 'Crea o actualiza llaves publicas de clientes de integracion existentes.';

    public function handle(): int
    {
        $clientCode = trim((string) $this->argument('client_code'));
        $kid = trim((string) $this->argument('kid'));
        $publicKeyPath = trim((string) $this->argument('public_key_path'));

        $client = IntegrationClient::query()
            ->where('client_code', $clientCode)
            ->first();

        if (! $client) {
            $this->error("No existe un integration_client con client_code [{$clientCode}].");

            return self::FAILURE;
        }

        $resolvedPath = realpath($publicKeyPath) ?: $publicKeyPath;
        $publicKey = @file_get_contents($resolvedPath);

        if ($publicKey === false) {
            $this->error("No se pudo leer la llave publica en [{$publicKeyPath}].");

            return self::FAILURE;
        }

        $publicKey = trim($publicKey);

        if ($publicKey === '') {
            $this->error('La llave publica no puede estar vacia.');

            return self::FAILURE;
        }

        $existingKey = IntegrationClientKey::query()
            ->where('client_id', $client->id)
            ->where('kid', $kid)
            ->first();

        $key = IntegrationClientKey::query()->updateOrCreate(
            [
                'client_id' => $client->id,
                'kid' => $kid,
            ],
            [
                'id' => $existingKey?->id ?? (string) Str::uuid(),
                'public_key' => $publicKey,
                'status' => IntegrationClientKey::STATUS_ACTIVE,
                'valid_from' => $existingKey?->valid_from ?? now(),
            ],
        );

        $action = $existingKey ? 'actualizada' : 'creada';

        $this->info("Llave {$action} para [{$clientCode}] con kid [{$kid}].");
        $this->line("Key ID: {$key->id}");

        return self::SUCCESS;
    }
}

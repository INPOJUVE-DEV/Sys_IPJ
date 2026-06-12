<?php

namespace App\Services\Integrations\Security;

use App\Models\Integrations\IntegrationClient;
use App\Models\Integrations\IntegrationJtiLog;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class IntegrationJtiService
{
    public function assertNotReplayed(IntegrationClient $client, string $issuer, string $jti, Carbon $expiresAt): void
    {
        $exists = IntegrationJtiLog::query()
            ->where('issuer', $issuer)
            ->where('jti', $jti)
            ->exists();

        if ($exists) {
            throw new AuthenticationException('Integration JWT replay detected.');
        }

        IntegrationJtiLog::query()->create([
            'id' => (string) Str::uuid(),
            'client_id' => $client->id,
            'issuer' => $issuer,
            'jti' => $jti,
            'expires_at' => $expiresAt,
            'created_at' => now(),
        ]);
    }

    public function cleanupExpired(): int
    {
        return IntegrationJtiLog::query()
            ->where('expires_at', '<', now())
            ->delete();
    }
}

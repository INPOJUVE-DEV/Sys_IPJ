<?php

namespace App\Services\Integrations\Security;

use App\Models\Integrations\IntegrationClient;
use App\Models\Integrations\IntegrationJtiLog;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class IntegrationJtiService
{
    public function assertNotReplayed(IntegrationClient $client, string $issuer, string $jti, Carbon $expiresAt): void
    {
        try {
            IntegrationJtiLog::query()->create([
                'id' => (string) Str::uuid(),
                'client_id' => $client->id,
                'issuer' => $issuer,
                'jti' => $jti,
                'expires_at' => $expiresAt,
                'created_at' => now(),
            ]);
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraintViolation($exception)) {
                throw new AuthenticationException('Integration JWT replay detected.');
            }

            throw $exception;
        }
    }

    public function cleanupExpired(): int
    {
        return IntegrationJtiLog::query()
            ->where('expires_at', '<', now())
            ->delete();
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = (string) ($exception->errorInfo[1] ?? '');
        $message = strtolower($exception->getMessage());

        return in_array($sqlState, ['23000', '23505'], true)
            || in_array($driverCode, ['19', '1062'], true)
            || str_contains($message, 'unique')
            || str_contains($message, 'duplicate');
    }
}

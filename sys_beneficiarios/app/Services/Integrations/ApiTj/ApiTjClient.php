<?php

namespace App\Services\Integrations\ApiTj;

use App\Services\Integrations\Security\IntegrationJwtSigner;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class ApiTjClient
{
    public function __construct(
        private readonly IntegrationJwtSigner $signer,
    ) {
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function syncCardholders(string $syncId, array $items): ApiTjSyncResponse
    {
        $config = config('integrations.outbound');
        $baseUrl = rtrim((string) ($config['base_url'] ?? ''), '/');
        $audience = trim((string) ($config['audience'] ?? ''));
        $scope = trim((string) ($config['scope'] ?? ''));
        $timeout = max(1, (int) ($config['timeout_seconds'] ?? 15));

        if ($baseUrl === '' || $audience === '' || $scope === '') {
            throw new RuntimeException('Integration outbound API_TJ configuration is incomplete.');
        }

        $token = $this->signer->makeToken($audience, $scope);

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout($timeout)
                ->withToken($token)
                ->post($baseUrl.'/api/v1/cardholders/sync', [
                    'sync_id' => $syncId,
                    'items' => array_values($items),
                ]);
        } catch (Throwable $exception) {
            throw new RuntimeException('Unable to send outbound cardholder sync request.', previous: $exception);
        }

        $body = $response->json();

        return new ApiTjSyncResponse(
            statusCode: $response->status(),
            body: is_array($body) ? $body : ['raw' => $response->body()],
        );
    }
}

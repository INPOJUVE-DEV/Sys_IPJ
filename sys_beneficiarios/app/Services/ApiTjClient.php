<?php

namespace App\Services;

use App\Support\ApiTjHelper;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ApiTjClient
{
    public function __construct(private readonly ApiTjJwtService $jwtService)
    {
    }

    public function syncBeneficiarios(array $payload): Response
    {
        return $this->sendJson(
            'POST',
            config('api_tj.outbound.sync_path', '/api/v1/cardholders/sync'),
            $payload,
            config('api_tj.outbound.scope', 'cardholders.sync')
        );
    }

    public function syncCardholders(array $payload): Response
    {
        return $this->syncBeneficiarios($payload);
    }

    public function createOrUpdateCardholder(array $payload): Response
    {
        return $this->sendJson('POST', '/api/v1/cardholders', $payload, config('api_tj.outbound.scope', 'cardholders.sync'));
    }

    public function sendJson(string $method, string $path, array $payload, string $scope): Response
    {
        $token = $this->jwtService->sign([
            'iss' => config('api_tj.outbound.issuer', 'sys_ipj'),
            'sub' => config('api_tj.outbound.subject', 'sys_ipj'),
            'aud' => config('api_tj.outbound.audience', 'api_tj'),
            'scope' => $scope,
            'jti' => (string) Str::uuid(),
            'iat' => now()->timestamp,
            'exp' => now()->addMinutes(10)->timestamp,
        ], [
            'alg' => 'RS256',
            'typ' => 'JWT',
            'kid' => config('api_tj.outbound.jwt_kid', 'sys_ipj-current'),
        ], config('api_tj.outbound.private_key_path'));

        return Http::timeout(config('api_tj.timeout', 15))
            ->withToken($token)
            ->acceptJson()
            ->send($method, rtrim(config('api_tj.base_url'), '/').$path, [
                'json' => $payload,
            ]);
    }

    public function sanitizeResponse(Response $response): ?string
    {
        return ApiTjHelper::sanitizeResponseBody($response->json() ?: $response->body());
    }
}

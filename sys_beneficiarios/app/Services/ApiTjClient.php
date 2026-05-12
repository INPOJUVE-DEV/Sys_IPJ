<?php

namespace App\Services;

use App\Support\ApiTjHelper;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ApiTjClient
{
    private const DOCKER_HOST_ALIAS = 'host.docker.internal';

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
            ->send($method, $this->resolveBaseUrl().$path, [
                'json' => $payload,
            ]);
    }

    public function sanitizeResponse(Response $response): ?string
    {
        return ApiTjHelper::sanitizeResponseBody($response->json() ?: $response->body());
    }

    private function resolveBaseUrl(): string
    {
        $baseUrl = rtrim((string) config('api_tj.base_url'), '/');

        if (! $this->isRunningInsideDocker()) {
            return $baseUrl;
        }

        $parts = parse_url($baseUrl);

        if (! is_array($parts)) {
            return $baseUrl;
        }

        $host = $parts['host'] ?? null;

        if (! in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return $baseUrl;
        }

        $parts['host'] = (string) config('api_tj.docker_host_alias', self::DOCKER_HOST_ALIAS);

        return $this->buildUrl($parts);
    }

    private function isRunningInsideDocker(): bool
    {
        return is_file('/.dockerenv');
    }

    /**
     * @param  array{scheme?: string, user?: string, pass?: string, host?: string, port?: int, path?: string, query?: string, fragment?: string}  $parts
     */
    private function buildUrl(array $parts): string
    {
        $url = '';

        if (isset($parts['scheme'])) {
            $url .= $parts['scheme'].'://';
        }

        if (isset($parts['user'])) {
            $url .= $parts['user'];

            if (isset($parts['pass'])) {
                $url .= ':'.$parts['pass'];
            }

            $url .= '@';
        }

        $url .= $parts['host'] ?? '';

        if (isset($parts['port'])) {
            $url .= ':'.$parts['port'];
        }

        $url .= $parts['path'] ?? '';

        if (isset($parts['query'])) {
            $url .= '?'.$parts['query'];
        }

        if (isset($parts['fragment'])) {
            $url .= '#'.$parts['fragment'];
        }

        return $url;
    }
}

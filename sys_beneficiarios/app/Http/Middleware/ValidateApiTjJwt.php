<?php

namespace App\Http\Middleware;

use App\Services\ApiTjJwtService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ValidateApiTjJwt
{
    public function __construct(private readonly ApiTjJwtService $jwtService)
    {
    }

    public function handle(Request $request, \Closure $next): Response
    {
        $token = $this->extractBearerToken($request);
        if (! $token) {
            return response()->json([
                'accepted' => false,
                'status' => 'unauthorized',
                'message' => 'Missing bearer token.',
            ], 401);
        }

        try {
            // Toda la seguridad de la integración se resuelve desde config/api_tj.php.
            $verified = $this->jwtService->verify($token, [
                'public_key' => config('api_tj.inbound.public_key'),
                'kid' => config('api_tj.inbound.jwt_kid'),
            ]);

            $payload = $verified['payload'];
            $issuedAt = (int) ($payload['iat'] ?? 0);
            $expiresAt = (int) ($payload['exp'] ?? 0);
            $scope = $payload['scope'] ?? null;
            $scopeValues = is_string($scope) ? preg_split('/\s+/', trim($scope)) : (array) $scope;
            $expectedScope = config('api_tj.inbound.allowed_scope', 'beneficiarios.create');

            if (($payload['iss'] ?? null) !== config('api_tj.inbound.issuer', 'api_tj')) {
                throw new \RuntimeException('Invalid issuer.');
            }

            if (($payload['aud'] ?? null) !== config('api_tj.inbound.audience')) {
                throw new \RuntimeException('Invalid audience.');
            }

            if (! in_array($expectedScope, $scopeValues, true)) {
                throw new \RuntimeException('Invalid scope.');
            }

            if ($issuedAt <= 0 || $expiresAt <= 0 || $expiresAt <= $issuedAt) {
                throw new \RuntimeException('Invalid token lifetime.');
            }

            if (($expiresAt - $issuedAt) > 600) {
                throw new \RuntimeException('Token lifetime exceeds maximum allowed window.');
            }

            if ($expiresAt < now()->timestamp) {
                throw new \RuntimeException('Expired token.');
            }

            $jti = (string) ($payload['jti'] ?? '');
            if ($jti === '') {
                throw new \RuntimeException('Missing jti.');
            }

            $cacheKey = 'api_tj.jwt.jti.'.$jti;
            if (! Cache::add($cacheKey, true, now()->addSeconds(max(60, $expiresAt - now()->timestamp)))) {
                throw new \RuntimeException('Duplicated jti.');
            }

            $request->attributes->set('api_tj_jwt_payload', $payload);
        } catch (\Throwable $exception) {
            return response()->json([
                'accepted' => false,
                'status' => 'unauthorized',
                'message' => 'Invalid integration token.',
            ], 401);
        }

        return $next($request);
    }

    private function extractBearerToken(Request $request): ?string
    {
        $header = trim((string) $request->header('Authorization', ''));
        if (! str_starts_with($header, 'Bearer ')) {
            return null;
        }

        return trim(substr($header, 7));
    }
}

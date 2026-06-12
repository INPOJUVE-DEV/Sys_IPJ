<?php

namespace App\Services\Integrations\Security;

use App\Services\Integrations\Security\Contracts\JwtCodec;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use RuntimeException;
use Throwable;

class FirebaseJwtCodec implements JwtCodec
{
    public function encode(array $payload, array $headers, string $privateKey): string
    {
        $this->assertPackageInstalled();

        try {
            return JWT::encode($payload, $privateKey, 'RS256', $headers['kid'] ?? null, $headers);
        } catch (Throwable $exception) {
            throw new RuntimeException('Unable to sign integration JWT.', previous: $exception);
        }
    }

    public function decode(string $token, string $publicKey, array $allowedAlgorithms = ['RS256']): array
    {
        $this->assertPackageInstalled();

        if (! in_array('RS256', $allowedAlgorithms, true)) {
            throw new RuntimeException('RS256 must be allowed for integration JWT verification.');
        }

        try {
            $decoded = JWT::decode($token, new Key($publicKey, 'RS256'));

            return json_decode(json_encode($decoded, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new RuntimeException('Unable to verify integration JWT.', previous: $exception);
        }
    }

    private function assertPackageInstalled(): void
    {
        if (! class_exists(JWT::class) || ! class_exists(Key::class)) {
            throw new RuntimeException('firebase/php-jwt is not installed.');
        }
    }
}

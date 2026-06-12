<?php

namespace App\Services\Integrations\Security;

use App\Services\Integrations\Security\Contracts\JwtCodec;
use Illuminate\Support\Str;
use RuntimeException;

class IntegrationJwtSigner
{
    public function __construct(private readonly JwtCodec $codec)
    {
    }

    public function makeToken(string $audience, string $scope, ?string $issuer = null): string
    {
        $config = config('integrations.outbound');
        $resolvedIssuer = trim((string) ($issuer ?: ($config['issuer'] ?? '')));
        $subject = trim((string) ($config['subject'] ?? ''));
        $kid = trim((string) ($config['kid'] ?? ''));
        $privateKeyPath = trim((string) ($config['private_key_path'] ?? ''));
        $ttlSeconds = max(60, (int) ($config['ttl_seconds'] ?? 600));

        if ($resolvedIssuer === '' || $subject === '' || $kid === '' || $privateKeyPath === '') {
            throw new RuntimeException('Integration outbound JWT configuration is incomplete.');
        }

        $privateKey = @file_get_contents($privateKeyPath);
        if (! is_string($privateKey) || trim($privateKey) === '') {
            throw new RuntimeException('Integration outbound private key is unreadable.');
        }

        $issuedAt = now()->timestamp;
        $expiresAt = $issuedAt + $ttlSeconds;

        $payload = [
            'iss' => $resolvedIssuer,
            'sub' => $subject,
            'aud' => $audience,
            'scope' => $scope,
            'jti' => (string) Str::uuid(),
            'iat' => $issuedAt,
            'exp' => $expiresAt,
        ];

        $headers = [
            'typ' => 'JWT',
            'alg' => 'RS256',
            'kid' => $kid,
        ];

        return $this->codec->encode($payload, $headers, $privateKey);
    }
}

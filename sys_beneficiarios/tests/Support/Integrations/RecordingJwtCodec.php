<?php

namespace Tests\Support\Integrations;

use App\Services\Integrations\Security\Contracts\JwtCodec;

class RecordingJwtCodec implements JwtCodec
{
    /**
     * @var array<string, mixed>
     */
    public array $payload = [];

    /**
     * @var array<string, mixed>
     */
    public array $headers = [];

    public string $privateKey = '';

    public function encode(array $payload, array $headers, string $privateKey): string
    {
        $this->payload = $payload;
        $this->headers = $headers;
        $this->privateKey = $privateKey;

        return 'encoded.integration.jwt';
    }

    public function decode(string $token, string $publicKey, array $allowedAlgorithms = ['RS256']): array
    {
        return [];
    }
}

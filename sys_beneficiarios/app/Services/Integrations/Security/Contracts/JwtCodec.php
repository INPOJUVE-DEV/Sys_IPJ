<?php

namespace App\Services\Integrations\Security\Contracts;

interface JwtCodec
{
    public function encode(array $payload, array $headers, string $privateKey): string;

    public function decode(string $token, string $publicKey, array $allowedAlgorithms = ['RS256']): array;
}

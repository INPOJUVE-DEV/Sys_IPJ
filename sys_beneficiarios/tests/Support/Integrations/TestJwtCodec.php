<?php

namespace Tests\Support\Integrations;

use App\Services\Integrations\Security\Contracts\JwtCodec;

class TestJwtCodec implements JwtCodec
{
    public function encode(array $payload, array $headers, string $privateKey): string
    {
        return $this->base64UrlEncode(json_encode($headers, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
            .'.'.$this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
            .'.signature';
    }

    public function decode(string $token, string $publicKey, array $allowedAlgorithms = ['RS256']): array
    {
        [$header, $payload] = explode('.', $token);

        return json_decode($this->base64UrlDecode($payload), true);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;
        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($value, '-_', '+/')) ?: '';
    }
}

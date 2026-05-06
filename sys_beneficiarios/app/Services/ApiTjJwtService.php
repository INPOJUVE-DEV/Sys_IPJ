<?php

namespace App\Services;

use RuntimeException;

class ApiTjJwtService
{
    public function verify(string $jwt, array $config): array
    {
        [$encodedHeader, $encodedPayload, $encodedSignature] = $this->splitJwt($jwt);

        $header = $this->decodeSegment($encodedHeader);
        $payload = $this->decodeSegment($encodedPayload);
        $signature = $this->base64UrlDecode($encodedSignature);

        if (($header['alg'] ?? null) !== 'RS256') {
            throw new RuntimeException('Invalid JWT algorithm.');
        }

        if (($header['kid'] ?? null) !== ($config['kid'] ?? null)) {
            throw new RuntimeException('Invalid JWT key id.');
        }

        $publicKey = $config['public_key'] ?? null;
        if (! is_string($publicKey) || trim($publicKey) === '') {
            throw new RuntimeException('Missing JWT public key.');
        }

        $verified = openssl_verify(
            $encodedHeader.'.'.$encodedPayload,
            $signature,
            $publicKey,
            OPENSSL_ALGO_SHA256
        );

        if ($verified !== 1) {
            throw new RuntimeException('Invalid JWT signature.');
        }

        return [
            'header' => $header,
            'payload' => $payload,
        ];
    }

    public function sign(array $payload, array $headers, string $privateKeyPath): string
    {
        $privateKey = @file_get_contents($privateKeyPath);
        if (! is_string($privateKey) || trim($privateKey) === '') {
            throw new RuntimeException('Unable to read JWT private key.');
        }

        $encodedHeader = $this->base64UrlEncode(json_encode($headers, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $encodedPayload = $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $signed = openssl_sign(
            $encodedHeader.'.'.$encodedPayload,
            $signature,
            $privateKey,
            OPENSSL_ALGO_SHA256
        );

        if (! $signed) {
            throw new RuntimeException('Unable to sign JWT.');
        }

        return $encodedHeader.'.'.$encodedPayload.'.'.$this->base64UrlEncode($signature);
    }

    private function splitJwt(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new RuntimeException('Malformed JWT.');
        }

        return $parts;
    }

    private function decodeSegment(string $segment): array
    {
        $decoded = json_decode($this->base64UrlDecode($segment), true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Invalid JWT payload.');
        }

        return $decoded;
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

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw new RuntimeException('Invalid base64url payload.');
        }

        return $decoded;
    }
}

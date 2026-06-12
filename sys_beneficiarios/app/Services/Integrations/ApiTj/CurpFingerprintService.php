<?php

namespace App\Services\Integrations\ApiTj;

use RuntimeException;

class CurpFingerprintService
{
    public function normalize(string $curp): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($curp)) ?? '');
    }

    public function hash(string $curp): string
    {
        $normalized = $this->normalize($curp);
        if ($normalized === '') {
            throw new RuntimeException('Beneficiary CURP is missing.');
        }

        $secret = (string) config('integrations.outbound.hash_secret');
        if (trim($secret) === '') {
            throw new RuntimeException('Integration outbound CURP hash secret is missing.');
        }

        return hash_hmac('sha256', $normalized, $secret);
    }

    public function mask(string $curp): string
    {
        $normalized = $this->normalize($curp);
        if ($normalized === '') {
            throw new RuntimeException('Beneficiary CURP is missing.');
        }

        $prefix = substr($normalized, 0, min(4, strlen($normalized)));
        $suffix = strlen($normalized) > 4 ? substr($normalized, -2) : '';
        $maskedLength = max(0, strlen($normalized) - strlen($prefix) - strlen($suffix));

        return $prefix.str_repeat('*', $maskedLength).$suffix;
    }
}

<?php

namespace App\Support;

use App\Models\Seccion;

class SeccionResolver
{
    public static function extractFromIne(?string $value): ?string
    {
        $digits = preg_replace('/\D/', '', trim((string) ($value ?? '')));
        if ($digits === '') {
            return null;
        }

        if (strlen($digits) >= 5) {
            return substr($digits, 0, 5);
        }

        if (strlen($digits) >= 4) {
            return substr($digits, 0, 4);
        }

        return null;
    }

    public static function normalize(?string $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        $digitsOnly = preg_replace('/\D/', '', $value);
        if ($digitsOnly === '') {
            return strtoupper($value);
        }

        return str_pad(ltrim($digitsOnly, '0'), 4, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<string>
     */
    public static function candidates(?string $value): array
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return [];
        }

        $base = strtoupper($value);
        $digitsOnly = preg_replace('/\D/', '', $base);
        $candidates = [$base];

        if ($digitsOnly !== '') {
            $digitCandidates = array_filter(array_unique([
                $digitsOnly,
                substr($digitsOnly, 0, 5),
                substr($digitsOnly, 0, 4),
            ]));

            foreach ($digitCandidates as $digitCandidate) {
                $trimmed = ltrim($digitCandidate, '0');
                $trimmed = $trimmed === '' ? '0' : $trimmed;

                $candidates[] = $digitCandidate;
                $candidates[] = $trimmed;
                $candidates[] = str_pad($trimmed, 4, '0', STR_PAD_LEFT);
                $candidates[] = str_pad($trimmed, 5, '0', STR_PAD_LEFT);
            }
        }

        return array_values(array_filter(array_unique($candidates)));
    }

    public static function resolve(?string $value): ?Seccion
    {
        $candidates = self::candidates($value);
        if (empty($candidates)) {
            return null;
        }

        return Seccion::whereIn('seccional', $candidates)->first();
    }

    public static function resolveFromIne(?string $value): ?Seccion
    {
        $seccional = self::extractFromIne($value);
        if (! $seccional) {
            return null;
        }

        return self::resolve($seccional);
    }
}

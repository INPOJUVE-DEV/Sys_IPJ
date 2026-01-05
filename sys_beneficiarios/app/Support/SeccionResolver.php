<?php

namespace App\Support;

use App\Models\Seccion;

class SeccionResolver
{
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
        $noLeading = ltrim($base, '0');

        return array_values(array_filter(array_unique([
            $base,
            $noLeading,
            str_pad($noLeading, 4, '0', STR_PAD_LEFT),
        ])));
    }

    public static function resolve(?string $value): ?Seccion
    {
        $candidates = self::candidates($value);
        if (empty($candidates)) {
            return null;
        }

        return Seccion::whereIn('seccional', $candidates)->first();
    }
}

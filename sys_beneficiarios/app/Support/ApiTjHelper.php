<?php

namespace App\Support;

class ApiTjHelper
{
    private const CURP_REGEX = '/^[A-Z][AEIOUX][A-Z]{2}\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])[HM](AS|BC|BS|CC|CL|CM|CS|CH|DF|DG|GT|GR|HG|JC|MC|MN|MS|NT|NL|OC|PL|QT|QR|SP|SL|SR|TC|TS|TL|VZ|YN|ZS|NE)[B-DF-HJ-NP-TV-Z]{3}[A-Z\d]\d$/i';

    public static function maskCurp(?string $curp): ?string
    {
        $curp = strtoupper(trim((string) $curp));
        if ($curp === '') {
            return null;
        }

        if (strlen($curp) <= 6) {
            return $curp;
        }

        return substr($curp, 0, 4).'**********'.substr($curp, -2);
    }

    public static function normalizeCurp(?string $curp): string
    {
        return strtoupper(trim((string) $curp));
    }

    public static function hashCurp(?string $curp, string $secret): string
    {
        return hash_hmac('sha256', self::normalizeCurp($curp), $secret);
    }

    public static function isValidCurp(?string $curp): bool
    {
        $curp = self::normalizeCurp($curp);

        return $curp !== '' && preg_match(self::CURP_REGEX, $curp) === 1;
    }

    public static function isDigitalFolio(?string $folio): bool
    {
        $folio = strtoupper(trim((string) $folio));

        return str_starts_with($folio, 'TD-');
    }

    public static function isPhysicalFolio(?string $folio): bool
    {
        $folio = trim((string) $folio);

        return $folio !== '' && preg_match('/^\d+$/', $folio) === 1;
    }

    public static function sanitizeResponseBody(mixed $body): ?string
    {
        if ($body === null || $body === '') {
            return null;
        }

        $json = is_string($body) ? $body : json_encode($body, JSON_UNESCAPED_UNICODE);
        if (! is_string($json) || $json === '') {
            return null;
        }

        return mb_substr($json, 0, 4000);
    }
}

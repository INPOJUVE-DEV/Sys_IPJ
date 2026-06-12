<?php

namespace App\Services\Integrations\Inbound;

use Illuminate\Encryption\Encrypter;
use RuntimeException;

class InboundPayloadEncrypter
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function encrypt(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return $this->makeEncrypter()->encryptString($json);
    }

    private function makeEncrypter(): Encrypter
    {
        $configuredKey = trim((string) config('integrations.payload_encryption_key'));
        if ($configuredKey === '') {
            throw new RuntimeException('Integration payload encryption key is not configured.');
        }

        if (str_starts_with($configuredKey, 'base64:')) {
            $decoded = base64_decode(substr($configuredKey, 7), true);
            if ($decoded === false) {
                throw new RuntimeException('Integration payload encryption key is malformed.');
            }

            $key = $decoded;
        } else {
            $key = hash('sha256', $configuredKey, true);
        }

        return new Encrypter($key, 'AES-256-CBC');
    }
}

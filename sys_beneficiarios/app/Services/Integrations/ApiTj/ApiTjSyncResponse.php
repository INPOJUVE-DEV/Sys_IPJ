<?php

namespace App\Services\Integrations\ApiTj;

class ApiTjSyncResponse
{
    /**
     * @param  array<string, mixed>  $body
     */
    public function __construct(
        public readonly int $statusCode,
        public readonly array $body,
    ) {
    }

    public function accepted(): bool
    {
        if ($this->statusCode < 200 || $this->statusCode >= 300) {
            return false;
        }

        if (array_key_exists('accepted', $this->body)) {
            return (bool) $this->body['accepted'];
        }

        return true;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function results(): array
    {
        $results = $this->body['results'] ?? [];

        if (! is_array($results)) {
            return [];
        }

        return array_values(array_filter($results, static fn ($value) => is_array($value)));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resultForIndex(int $index): ?array
    {
        foreach ($this->results() as $result) {
            if ((int) ($result['index'] ?? -1) === $index) {
                return $result;
            }
        }

        return null;
    }

    public function message(): ?string
    {
        $message = $this->body['message'] ?? $this->body['error'] ?? null;

        return is_string($message) && trim($message) !== ''
            ? trim($message)
            : null;
    }
}

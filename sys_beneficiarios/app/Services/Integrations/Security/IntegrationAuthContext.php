<?php

namespace App\Services\Integrations\Security;

use App\Models\Integrations\IntegrationClient;

class IntegrationAuthContext
{
    /**
     * @param  array<string, mixed>  $claims
     * @param  array<int, string>  $scopes
     */
    public function __construct(
        public readonly IntegrationClient $client,
        public readonly array $claims,
        public readonly array $scopes,
        public readonly string $issuer,
        public readonly ?string $subject,
        public readonly string $audience,
        public readonly string $jti,
    ) {
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }
}

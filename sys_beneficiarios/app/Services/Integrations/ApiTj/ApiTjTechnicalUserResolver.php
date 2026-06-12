<?php

namespace App\Services\Integrations\ApiTj;

use App\Models\User;
use RuntimeException;

class ApiTjTechnicalUserResolver
{
    public function resolve(): User
    {
        $email = trim((string) config('integrations.api_tj.integration_user_email'));
        if ($email === '') {
            throw new RuntimeException('API_TJ integration user email is not configured.');
        }

        $user = User::query()->where('email', $email)->first();
        if (! $user) {
            throw new RuntimeException("API_TJ technical integration user [{$email}] was not found.");
        }

        return $user;
    }
}

<?php

namespace App\Http\Middleware;

use App\Services\Integrations\Security\IntegrationJwtVerifier;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateIntegrationJwt
{
    public function __construct(private readonly IntegrationJwtVerifier $verifier)
    {
    }

    public function handle(Request $request, Closure $next, string $requiredScope): Response
    {
        $context = $this->verifier->verify($request, $requiredScope);

        $request->attributes->set('integration_auth', $context);

        return $next($request);
    }
}

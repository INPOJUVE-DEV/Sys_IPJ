<?php

namespace App\Http\Middleware;

use App\Services\Integrations\Security\IntegrationJwtVerifier;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateIntegrationJwt
{
    public function __construct(private readonly IntegrationJwtVerifier $verifier)
    {
    }

    public function handle(Request $request, Closure $next, string $requiredScope): Response
    {
        try {
            $context = $this->verifier->verify($request, $requiredScope);
        } catch (AuthenticationException) {
            return response()->json([
                'accepted' => false,
                'status' => 'unauthorized',
                'message' => 'Token de integracion invalido',
            ], 401);
        } catch (AuthorizationException) {
            return response()->json([
                'accepted' => false,
                'status' => 'forbidden',
                'message' => 'Permisos insuficientes',
            ], 403);
        }

        $request->attributes->set('integration_auth', $context);

        return $next($request);
    }
}

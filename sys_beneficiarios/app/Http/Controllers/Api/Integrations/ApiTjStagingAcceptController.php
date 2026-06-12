<?php

namespace App\Http\Controllers\Api\Integrations;

use App\Http\Controllers\Controller;
use App\Services\Integrations\ApiTj\ApiTjStagingAcceptService;
use App\Services\Integrations\Security\IntegrationAuthContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ApiTjStagingAcceptController extends Controller
{
    public function __invoke(Request $request, ApiTjStagingAcceptService $service): JsonResponse
    {
        $auth = $request->attributes->get('integration_auth');
        if (! $auth instanceof IntegrationAuthContext) {
            throw new RuntimeException('Integration auth context is missing.');
        }

        return $service->accept($request->all(), $auth)->toResponse();
    }
}

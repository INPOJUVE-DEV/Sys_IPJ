<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiTjInboundRequest;
use App\Services\ApiTjInboundService;
use Illuminate\Http\Request;

class ApiTjBeneficiarioController extends Controller
{
    public function __construct(private readonly ApiTjInboundService $service)
    {
    }

    public function store(Request $request)
    {
        $payload = $request->all();
        $externalRequestId = (string) ($payload['external_request_id'] ?? '');
        $existing = $externalRequestId !== ''
            ? ApiTjInboundRequest::where('external_request_id', $externalRequestId)->first()
            : null;

        $result = $this->service->process($payload, $existing);

        return response()->json($result['body'], $result['status_code']);
    }
}

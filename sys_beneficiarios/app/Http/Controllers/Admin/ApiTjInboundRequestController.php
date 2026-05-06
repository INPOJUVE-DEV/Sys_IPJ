<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiTjInboundRequest;
use App\Services\ApiTjInboundService;

class ApiTjInboundRequestController extends Controller
{
    public function __construct(private readonly ApiTjInboundService $service)
    {
    }

    public function index()
    {
        $requests = ApiTjInboundRequest::with('beneficiario')
            ->latest('received_at')
            ->paginate(25);

        return view('admin.api_tj.requests.index', compact('requests'));
    }

    public function show(ApiTjInboundRequest $requestRecord)
    {
        $requestRecord->load('beneficiario');

        return view('admin.api_tj.requests.show', compact('requestRecord'));
    }

    public function reprocess(ApiTjInboundRequest $requestRecord)
    {
        abort_unless($requestRecord->status === ApiTjInboundRequest::STATUS_ERROR, 422);

        $payload = $requestRecord->payload_json ?? [];
        $result = $this->service->process($payload, $requestRecord, true);

        return redirect()
            ->route('admin.api-tj.requests.show', $requestRecord)
            ->with('status', 'Solicitud reprocesada con resultado: '.$result['body']['status']);
    }
}

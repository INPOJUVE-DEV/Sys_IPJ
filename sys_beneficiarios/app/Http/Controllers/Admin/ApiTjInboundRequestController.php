<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiTjInboundRequest;
use App\Services\ApiTjInboundService;
use Illuminate\Support\Facades\Schema;

class ApiTjInboundRequestController extends Controller
{
    public function __construct(private readonly ApiTjInboundService $service)
    {
    }

    public function index()
    {
        abort_unless(Schema::hasTable('api_tj_inbound_requests'), 503, 'Faltan migraciones de API_TJ para consultar solicitudes inbound.');

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
        abort_unless(in_array($requestRecord->status, [
            ApiTjInboundRequest::STATUS_FAILED,
            ApiTjInboundRequest::STATUS_ERROR,
        ], true), 422);

        $payload = $requestRecord->payload_json ?? [];
        $result = $this->service->processBatch($payload, $requestRecord, true);

        return redirect()
            ->route('admin.api-tj.requests.show', $requestRecord)
            ->with('status', 'Solicitud reprocesada. Aceptados: '.($result['body']['accepted_count'] ?? 0).', rechazados: '.($result['body']['rejected_count'] ?? 0).'.');
    }
}

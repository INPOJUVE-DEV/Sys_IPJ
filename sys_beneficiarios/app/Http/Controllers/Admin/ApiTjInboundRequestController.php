<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Integrations\IntegrationInboundRequest;
use App\Services\Integrations\ApiTj\ApiTjOperationalStatusService;
use Illuminate\Http\Request;

class ApiTjInboundRequestController extends Controller
{
    public function __construct(
        private readonly ApiTjOperationalStatusService $statusService,
    ) {
    }

    public function index(Request $request)
    {
        $filters = $request->only(['status', 'source_system', 'from', 'to']);

        $requests = IntegrationInboundRequest::query()
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['source_system'] ?? null, fn ($query, $value) => $query->where('source_system', $value))
            ->when($filters['from'] ?? null, fn ($query, $value) => $query->whereDate('received_at', '>=', $value))
            ->when($filters['to'] ?? null, fn ($query, $value) => $query->whereDate('received_at', '<=', $value))
            ->latest('received_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.integrations.api_tj.inbound_requests.index', [
            'requests' => $requests,
            'filters' => $filters,
            'statusSummary' => $this->statusService->summary(),
        ]);
    }

    public function show(IntegrationInboundRequest $inboundRequest)
    {
        return view('admin.integrations.api_tj.inbound_requests.show', [
            'inboundRequest' => $inboundRequest,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Integrations\IntegrationSyncRun;
use App\Services\Integrations\ApiTj\ApiTjOperationalStatusService;
use Illuminate\Http\Request;

class ApiTjSyncRunController extends Controller
{
    public function __construct(
        private readonly ApiTjOperationalStatusService $statusService,
    ) {
    }

    public function index(Request $request)
    {
        $filters = $request->only(['status', 'requested_by', 'from', 'to']);

        $runs = IntegrationSyncRun::query()
            ->with('requestedBy')
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['requested_by'] ?? null, fn ($query, $value) => $query->where('requested_by', $value))
            ->when($filters['from'] ?? null, fn ($query, $value) => $query->whereDate('created_at', '>=', $value))
            ->when($filters['to'] ?? null, fn ($query, $value) => $query->whereDate('created_at', '<=', $value))
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.integrations.api_tj.sync_runs.index', [
            'runs' => $runs,
            'filters' => $filters,
            'statusSummary' => $this->statusService->summary(),
        ]);
    }

    public function show(IntegrationSyncRun $run)
    {
        $run->load([
            'requestedBy',
            'items' => fn ($query) => $query->with('beneficiario')->orderBy('created_at'),
        ]);

        return view('admin.integrations.api_tj.sync_runs.show', [
            'run' => $run,
        ]);
    }
}

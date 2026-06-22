<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Integrations\IntegrationSyncRun;
use App\Services\Integrations\ApiTj\CardholderSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ApiTjCardholderSyncController extends Controller
{
    public function store(Request $request, CardholderSyncService $service): RedirectResponse
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        $limit = (int) ($validated['limit'] ?? config('integrations.outbound.manual_sync_limit', 100));
        $run = $service->queueLimited($request->user(), $limit);

        if ($run->status === IntegrationSyncRun::STATUS_FAILED) {
            return redirect()
                ->route('admin.integraciones.api_tj.sync-runs.show', $run)
                ->with('error', $run->error_message ?: 'No se pudo encolar la sincronizacion manual API_TJ.');
        }

        $message = $run->status === IntegrationSyncRun::STATUS_SUCCESS
            ? 'Sin items elegibles dentro del lote solicitado; la corrida cerro sin envios.'
            : "Sincronizacion API_TJ encolada correctamente para un lote de hasta {$limit} beneficiarios.";

        return redirect()
            ->route('admin.integraciones.api_tj.sync-runs.show', $run)
            ->with('status', $message);
    }
}

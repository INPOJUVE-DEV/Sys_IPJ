<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Integrations\ApiTj\CardholderSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Models\Integrations\IntegrationSyncRun;

class ApiTjCardholderSyncController extends Controller
{
    public function store(Request $request, CardholderSyncService $service): RedirectResponse
    {
        $run = $service->queue($request->user());

        $message = $run->status === IntegrationSyncRun::STATUS_SUCCESS
            ? 'Sin items pendientes para sincronizar; la corrida cerro sin envios.'
            : 'Sincronizacion API_TJ encolada correctamente.';

        return redirect()
            ->route('admin.integraciones.api_tj.sync-runs.show', $run)
            ->with('status', $message);
    }
}
